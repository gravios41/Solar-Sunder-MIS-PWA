<?php
// api/avatar-upload.php
// Uploads a profile picture to Supabase Storage and saves the URL to the user record

header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// ── DELETE: remove avatar ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $userId  = (int)$_SESSION['user_id'];
    $oldUser = $supabase->getById('users', $userId);

    if (!empty($oldUser['avatar_url'])) {
        $oldFile = basename(parse_url($oldUser['avatar_url'], PHP_URL_PATH));
        if ($oldFile) {
            $delCh = curl_init(SUPABASE_URL . '/storage/v1/object/avatars/' . $oldFile);
            curl_setopt_array($delCh, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST  => 'DELETE',
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
                    'apikey: '               . SUPABASE_SERVICE_KEY,
                ],
            ]);
            curl_exec($delCh);
            curl_close($delCh);
        }
    }

    $supabase->update('users', $userId, ['avatar_url' => null, 'updated_at' => date('Y-m-d H:i:s')]);
    $_SESSION['avatar_url'] = '';

    echo json_encode(['success' => true, 'message' => 'Profile picture removed.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE   => 'File too large (server limit).',
        UPLOAD_ERR_FORM_SIZE  => 'File too large.',
        UPLOAD_ERR_PARTIAL    => 'Upload was incomplete.',
        UPLOAD_ERR_NO_FILE    => 'No file uploaded.',
    ];
    $code = $_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE;
    echo json_encode(['success' => false, 'error' => $uploadErrors[$code] ?? 'Upload failed.']);
    exit();
}

$file     = $_FILES['avatar'];
$maxSize  = 2 * 1024 * 1024; // 2 MB
$allowed  = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];

// Validate size
if ($file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'error' => 'Image must be under 2 MB.']);
    exit();
}

// Validate MIME type using finfo (not trusting $_FILES['type'])
$finfo    = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowed)) {
    echo json_encode(['success' => false, 'error' => 'Only JPEG, PNG, and WebP images are allowed.']);
    exit();
}

$ext      = ($mimeType === 'image/png') ? 'png' : (($mimeType === 'image/webp') ? 'webp' : 'jpg');
$userId   = (int)$_SESSION['user_id'];
$filename = "user_{$userId}_" . time() . ".{$ext}";

// Upload to Supabase Storage
$storageUrl = SUPABASE_URL . '/storage/v1/object/avatars/' . $filename;
$fileData   = file_get_contents($file['tmp_name']);

$ch = curl_init($storageUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST  => 'POST',
    CURLOPT_POSTFIELDS     => $fileData,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
        'apikey: '               . SUPABASE_SERVICE_KEY,
        'Content-Type: '         . $mimeType,
        'x-upsert: true',
    ],
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr) {
    echo json_encode(['success' => false, 'error' => 'Upload failed: ' . $curlErr]);
    exit();
}

if ($httpCode < 200 || $httpCode >= 300) {
    $body = json_decode($response, true);
    echo json_encode(['success' => false, 'error' => $body['message'] ?? "Storage error ($httpCode)"]);
    exit();
}

// Build public URL
$publicUrl = SUPABASE_URL . '/storage/v1/object/public/avatars/' . $filename;

// Delete old avatar from storage if it exists
$oldUser = $supabase->getById('users', $userId);
if (!empty($oldUser['avatar_url'])) {
    $oldPath = parse_url($oldUser['avatar_url'], PHP_URL_PATH);
    $oldFile = basename($oldPath);
    if ($oldFile && $oldFile !== $filename) {
        $delCh = curl_init(SUPABASE_URL . '/storage/v1/object/avatars/' . $oldFile);
        curl_setopt_array($delCh, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'DELETE',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
                'apikey: '               . SUPABASE_SERVICE_KEY,
            ],
        ]);
        curl_exec($delCh);
        curl_close($delCh);
    }
}

// Save URL to users table
try {
    $supabase->update('users', $userId, [
        'avatar_url' => $publicUrl,
        'updated_at' => date('Y-m-d H:i:s'),
    ]);

    // Keep session in sync
    $_SESSION['avatar_url'] = $publicUrl;

    echo json_encode(['success' => true, 'avatar_url' => $publicUrl, 'message' => 'Profile picture updated.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Saved to storage but failed to update profile: ' . $e->getMessage()]);
}
