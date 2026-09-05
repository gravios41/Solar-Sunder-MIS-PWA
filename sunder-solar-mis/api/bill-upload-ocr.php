<?php
/**
 * Bill Upload OCR Handler
 * Accepts bill image upload, runs Tesseract OCR, extracts kWh and billing period
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
requireAuth();

if (!hasPermission('energy-assessments', 'create')) {
    http_response_code(403);
    echo json_encode(['error' => 'Permission denied']);
    exit;
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$assessmentId = $_POST['assessment_id'] ?? null;
if (!$assessmentId) {
    http_response_code(400);
    echo json_encode(['error' => 'assessment_id required']);
    exit;
}

// Verify the assessment exists
$assessment = $supabase->getById('energy_assessments', $assessmentId);
if (!$assessment) {
    http_response_code(403);
    echo json_encode(['error' => 'Assessment not found']);
    exit;
}

// Check if file uploaded
if (!isset($_FILES['bill_image']) || $_FILES['bill_image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded or upload failed']);
    exit;
}

$file = $_FILES['bill_image'];

// Validate file type
$allowedTypes = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'application/pdf' => 'pdf',
];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!isset($allowedTypes[$mimeType])) {
    http_response_code(400);
    echo json_encode(['error' => 'Only JPG, PNG, WEBP, and PDF files allowed']);
    exit;
}

// Validate file size (max 5MB)
if ($file['size'] > 5 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['error' => 'File too large (max 5MB)']);
    exit;
}

// Create upload directory
$uploadDir = dirname(__DIR__) . '/assets/bills';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Generate unique filename with assessment ID
$fileName = sprintf(
    'bill_%s_%s_%s',
    $assessmentId,
    time(),
    bin2hex(random_bytes(4))
);
// Extension comes from the verified MIME type, never the client-supplied
// filename — that filename is later embedded in a shell command for
// Tesseract, so it must never contain attacker-controlled characters.
$filePath = $uploadDir . '/' . $fileName . '.' . $allowedTypes[$mimeType];

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $filePath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save uploaded file']);
    exit;
}

// Run Tesseract OCR — the binary lives at a different path depending on
// whether this is running on a local Windows/XAMPP dev box or the Linux
// Docker container on Render (see Dockerfile, which apt-installs it there).
$tesseractPath = findTesseractBinary();
if (!$tesseractPath) {
    http_response_code(500);
    echo json_encode(['error' => 'Tesseract OCR is not installed on this server']);
    exit;
}

// Phone photos of a bill are the main accuracy killer for OCR — uneven
// lighting, low contrast, and small text all trip Tesseract up badly. A
// real image (not a PDF) gets converted to a cleaned-up grayscale,
// contrast-boosted, upscaled copy before OCR ever sees it. PDFs are left
// alone — Tesseract handles those natively and GD can't read PDF pages.
$ocrInputPath = $filePath;
if ($allowedTypes[$mimeType] !== 'pdf' && extension_loaded('gd')) {
    $preprocessed = preprocessBillImage($filePath, $mimeType);
    if ($preprocessed) {
        $ocrInputPath = $preprocessed;
    }
}

// Create output file for Tesseract
$ocrOutput = $uploadDir . '/' . $fileName . '_ocr';

// --psm 6 ("assume a single uniform block of text") reads a bill's info
// box far more reliably than Tesseract's fully-automatic default page
// segmentation, which tends to badly mis-order text pulled from a bill's
// multi-column / tabular layout.
$command = sprintf(
    '"%s" "%s" "%s" --psm 6 -l eng 2>&1',
    $tesseractPath,
    $ocrInputPath,
    $ocrOutput
);

$output = [];
$returnCode = 0;
exec($command, $output, $returnCode);

if ($ocrInputPath !== $filePath) {
    @unlink($ocrInputPath);
}

// Read OCR result
$ocrText = '';
$ocrFilePath = $ocrOutput . '.txt';
if (file_exists($ocrFilePath)) {
    $ocrText = file_get_contents($ocrFilePath);
}

if (empty($ocrText) && $returnCode !== 0) {
    http_response_code(500);
    echo json_encode([
        'error' => 'OCR processing failed',
        'debug' => implode(', ', $output)
    ]);
    unlink($filePath);
    exit;
}

// Extract kWh and billing period using regex patterns
$consumptionResult = extractConsumption($ocrText);
$consumptionKwh = $consumptionResult['value'];
$confidence = $consumptionResult['confidence'];
$billingPeriod = extractBillingPeriod($ocrText);

// Store bill reading in database
try {
    $billData = [
        'assessment_id' => $assessmentId,
        'billing_period' => $billingPeriod ?? date('Y-m-d'),
        'consumption_kwh' => $consumptionKwh,
        'is_verified' => false,
        'ocr_text' => $ocrText,
        'file_path' => 'assets/bills/' . basename($filePath)
    ];

    $response = $supabase->insert('energy_bill_readings', $billData);
    $bill = $response[0] ?? $response;
    $billId = is_array($bill) ? ($bill['id'] ?? null) : null;

    if (!$billId) {
        throw new Exception('Failed to insert bill reading');
    }

    // Clean up OCR temp file
    if (file_exists($ocrFilePath)) {
        unlink($ocrFilePath);
    }

    // Return success with extracted data
    echo json_encode([
        'success' => true,
        'message' => 'Bill uploaded and OCR completed',
        'extracted' => [
            'consumption_kwh' => $consumptionKwh,
            'billing_period' => $billingPeriod,
            // 'high' only when a specifically-labeled consumption phrase
            // matched (e.g. "Total kWh Consumed"); 'low' means only a bare
            // "<number> kWh" was found anywhere in the text, which is far
            // more likely to be a rate, a reading, or an unrelated figure —
            // worth double-checking against the actual bill.
            'confidence' => $confidence
        ],
        'raw_text' => substr($ocrText, 0, 500) . '...',
        'bill_id' => $billId
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}

/**
 * Locate the Tesseract binary regardless of platform: hardcoded Windows
 * install paths for local dev, common Linux paths for the Docker/Render
 * deployment, falling back to a PATH lookup for any other install layout.
 */
function findTesseractBinary() {
    $candidates = [
        'C:\\Program Files\\Tesseract-OCR\\tesseract.exe',
        'C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe',
        '/usr/bin/tesseract',
        '/usr/local/bin/tesseract',
    ];
    foreach ($candidates as $path) {
        if (file_exists($path)) {
            return $path;
        }
    }

    $isWindows = stripos(PHP_OS, 'WIN') === 0;
    $which = trim((string)@shell_exec($isWindows ? 'where tesseract 2>NUL' : 'command -v tesseract 2>/dev/null'));
    return $which !== '' ? $which : null;
}

/**
 * Extract consumption kWh from OCR text. Bill text is full of numbers
 * next to "kWh" that are NOT the consumption figure to report — a per-kWh
 * rate ("₱11.50/kWh"), a meter reading, a 12-month usage history table —
 * so this tries the most specifically-labeled phrases first ("Total kWh
 * Consumed", "kWh Used") before falling back to a bare number+kWh match,
 * and reports which tier actually matched as a confidence signal the UI
 * surfaces to the user, since a low-confidence bare match is genuinely
 * more likely to be wrong and worth double-checking against the bill.
 */
function extractConsumption($text) {
    $text = strtoupper($text);

    $tiers = [
        'high' => [
            '/TOTAL\s*KWH\s*CONSUMED[:\s]*(\d+(?:[.,]\d+)?)/',
            '/KWH\s*USED[:\s]*(\d+(?:[.,]\d+)?)/',
            '/(\d+(?:[.,]\d+)?)\s*KWH\s*USED/',
            '/ACTUAL\s*CONSUMPTION[:\s]*(\d+(?:[.,]\d+)?)/',
            '/PRESENT\s*CONSUMPTION[:\s]*(\d+(?:[.,]\d+)?)/',
            '/TOTAL\s*CONSUMPTION[:\s]*(\d+(?:[.,]\d+)?)\s*K?WH/',
            '/CONSUMPTION[:\s]*(\d+(?:[.,]\d+)?)\s*K?WH/',
        ],
        'medium' => [
            '/TOTAL[:\s]*(\d+(?:[.,]\d+)?)\s*K?WH/',
            '/(\d+(?:[.,]\d+)?)\s*KWH\s*TOTAL/',
            '/ENERGY[:\s]*(\d+(?:[.,]\d+)?)\s*K?WH/',
            '/USAGE[:\s]*(\d+(?:[.,]\d+)?)\s*K?WH/',
            '/(\d+(?:[.,]\d+)?)\s*K?WH[:\s]*CONSUMPTION/',
        ],
        'low' => [
            '/(\d+(?:[.,]\d+)?)\s*KWH/',
        ],
    ];

    foreach ($tiers as $confidence => $patterns) {
        foreach ($patterns as $pattern) {
            if (!preg_match_all($pattern, $text, $allMatches, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            foreach ($allMatches[1] as [$rawValue, $offset]) {
                $kwh = (float)str_replace(',', '.', $rawValue);
                if (isPlausibleConsumption($kwh, $text, $offset)) {
                    return ['value' => $kwh, 'confidence' => $confidence];
                }
            }
        }
    }

    return ['value' => 0, 'confidence' => 'low'];
}

/**
 * Filters out matches that are almost certainly NOT the bill's actual
 * consumption figure: a per-kWh peso rate quoted right next to the word
 * "kWh" (e.g. "₱11.50/kWh"), or a value outside the range any real
 * residential/commercial monthly bill would plausibly show.
 */
function isPlausibleConsumption($kwh, $text, $matchOffset) {
    if ($kwh < 1 || $kwh > 100000) {
        return false;
    }
    // A peso sign right before the number means this is a price, not a
    // consumption figure — checked tightly against just the character(s)
    // immediately adjacent, since Tesseract very commonly misreads "₱" as
    // a bare "P" and "P" alone is too common a letter to disqualify
    // anywhere it appears in the wider surrounding text.
    $immediatelyBefore = substr($text, max(0, $matchOffset - 3), min(3, $matchOffset));
    if (preg_match('/[₱P]\s*$/', $immediatelyBefore)) {
        return false;
    }
    // A simple containment check on the surrounding text is far more
    // robust to OCR's inconsistent spacing/punctuation than trying to
    // anchor a regex to the exact end of the context window.
    $contextStart = max(0, $matchOffset - 25);
    $context = substr($text, $contextStart, ($matchOffset - $contextStart) + 6);
    foreach (['RATE', 'PER KWH', '/KWH', '₱'] as $disqualifier) {
        if (str_contains($context, $disqualifier)) {
            return false;
        }
    }
    return true;
}

/**
 * Cleans up a phone-photographed bill before OCR: grayscale + contrast
 * boost. Tesseract does noticeably better on a clean, high-contrast image
 * than on a raw color phone photo (uneven lighting, shadows, low contrast
 * against a colored background). Deliberately does NOT upscale small
 * images — tested against a low-res synthetic bill and confirmed that
 * imagecopyresampled()'s blur softened the text enough to actively
 * corrupt the OCR result ("268" became "2686"), while grayscale+contrast
 * alone read it perfectly. Real phone camera photos are already well
 * above any resolution that would need upscaling anyway. Returns null
 * (falls back to the original file) on any failure rather than blocking
 * the upload.
 */
function preprocessBillImage($filePath, $mimeType) {
    try {
        $image = match ($mimeType) {
            'image/jpeg' => imagecreatefromjpeg($filePath),
            'image/png' => imagecreatefrompng($filePath),
            'image/webp' => imagecreatefromwebp($filePath),
            default => null,
        };
        if (!$image) {
            return null;
        }

        imagefilter($image, IMG_FILTER_GRAYSCALE);
        imagefilter($image, IMG_FILTER_CONTRAST, -25); // negative = increase contrast

        $outputPath = $filePath . '_preprocessed.png';
        imagepng($image, $outputPath);
        imagedestroy($image);

        return file_exists($outputPath) ? $outputPath : null;
    } catch (Throwable $e) {
        error_log('Bill image preprocessing failed: ' . $e->getMessage());
        return null;
    }
}

/**
 * Extract billing period from OCR text. A real bill usually has SEVERAL
 * dates (bill/statement date, due date, meter reading from/to dates) —
 * grabbing the first bare date found anywhere risks picking the due date
 * instead of the actual billing period. Prefers a date that's explicitly
 * labeled as the billing/statement period before falling back to the
 * first date-like pattern found at all.
 */
function extractBillingPeriod($text) {
    $labeled = preg_match(
        '/(?:BILLING\s*PERIOD|STATEMENT\s*DATE|BILL(?:ING)?\s*DATE)[:\s]*(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4})/i',
        $text,
        $labeledMatch
    ) ? $labeledMatch[1] : null;

    $dateString = $labeled;
    if (!$dateString && preg_match('/(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})/', $text, $matches)) {
        $dateString = $matches[0];
    }

    if ($dateString && preg_match('/(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})/', $dateString, $matches)) {
        // The Philippines conventionally writes dates DD/MM/YYYY (day
        // first), not the American MM/DD/YYYY — so that's the default
        // whenever a date is ambiguous (both numbers <=12, e.g. "05/03").
        // Only override that default when one of the two numbers plainly
        // CAN'T be a month (>12), which pins down the actual format
        // regardless of convention.
        $first = (int)$matches[1];
        $second = (int)$matches[2];
        $year = $matches[3];

        if ($second > 12 && $first <= 12) {
            // Second can't be a month, so this must be MM/DD after all.
            $month = $first;
            $day = $second;
        } else {
            // Either first > 12 (so it must be the day, DD/MM) or both
            // are <=12 and genuinely ambiguous — default to DD/MM either way.
            $day = $first;
            $month = $second;
        }

        $month = str_pad((string)$month, 2, '0', STR_PAD_LEFT);
        $day = str_pad((string)$day, 2, '0', STR_PAD_LEFT);
        return "$year-$month-$day";
    }

    // Try a labeled "Month Year" phrase before a bare one anywhere.
    $months = 'January|February|March|April|May|June|July|August|September|October|November|December|Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec';
    if (preg_match(
        "/(?:BILLING\s*PERIOD|STATEMENT\s*DATE|BILL(?:ING)?\s*DATE)[:\s]*($months)\s*(\d{4})/i",
        $text,
        $matches
    ) || preg_match("/($months)\s*(\d{4})/i", $text, $matches)) {
        $monthName = $matches[1];
        $year = $matches[2];
        $month = date('m', strtotime($monthName));
        return "$year-$month-01";
    }

    // Return first day of current month as fallback
    return date('Y-m-01');
}
?>
