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

// Create output file for Tesseract
$ocrOutput = $uploadDir . '/' . $fileName . '_ocr';

// Execute Tesseract with proper escaping
$command = sprintf(
    '"%s" "%s" "%s" 2>&1',
    $tesseractPath,
    $filePath,
    $ocrOutput
);

$output = [];
$returnCode = 0;
exec($command, $output, $returnCode);

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
$consumptionKwh = extractConsumption($ocrText);
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
            'confidence' => $consumptionKwh > 0 ? 'high' : 'low'
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
 * Extract consumption kWh from OCR text
 * Looks for patterns like "123 kWh", "123 KWH", "Consumption: 123"
 */
function extractConsumption($text) {
    $text = strtoupper($text);

    // Common patterns for electricity consumption
    $patterns = [
        '/CONSUMPTION[:\s]*(\d+(?:[.,]\d+)?)\s*K?WH/i',
        '/(\d+(?:[.,]\d+)?)\s*K?WH[:\s]*CONSUMPTION/i',
        '/TOTAL[:\s]*(\d+(?:[.,]\d+)?)\s*K?WH/i',
        '/(\d+(?:[.,]\d+)?)\s*KWH\s*TOTAL/i',
        '/ENERGY[:\s]*(\d+(?:[.,]\d+)?)\s*K?WH/i',
        '/USAGE[:\s]*(\d+(?:[.,]\d+)?)\s*K?WH/i',
        '/(\d+(?:[.,]\d+)?)\s*KWH/i'
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $text, $matches)) {
            $value = str_replace(',', '.', $matches[1]);
            $kwh = (float)$value;
            if ($kwh > 0) {
                return $kwh;
            }
        }
    }

    return 0;
}

/**
 * Extract billing period from OCR text
 * Looks for date patterns like "01/01/2025" or "January 2025"
 */
function extractBillingPeriod($text) {
    // Bills are normally MM/DD/YYYY. If the first number can't be a month
    // (>12), it must actually be DD/MM/YYYY instead — swap in that case.
    if (preg_match('/(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})/', $text, $matches)) {
        $first = (int)$matches[1];
        $second = (int)$matches[2];
        $year = $matches[3];

        if ($first > 12 && $second <= 12) {
            $month = $second;
            $day = $first;
        } else {
            $month = $first;
            $day = $second;
        }

        $month = str_pad((string)$month, 2, '0', STR_PAD_LEFT);
        $day = str_pad((string)$day, 2, '0', STR_PAD_LEFT);
        return "$year-$month-$day";
    }

    // Try Month Year format
    $months = 'January|February|March|April|May|June|July|August|September|October|November|December|Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec';
    if (preg_match("/($months)\s*(\d{4})/i", $text, $matches)) {
        $monthName = $matches[1];
        $year = $matches[2];
        $month = date('m', strtotime($monthName));
        return "$year-$month-01";
    }

    // Return first day of current month as fallback
    return date('Y-m-01');
}
?>
