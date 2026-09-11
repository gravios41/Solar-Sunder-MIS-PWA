<?php
// api/users-api.php
// REST API for Users

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/mailer.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

global $supabase;

$method = $_SERVER['REQUEST_METHOD'];

// Check permission for user management
if (!hasPermission('users', 'view') && $method !== 'PUT') {
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit();
}

switch ($method) {
    case 'GET':
        handleGetUsers();
        break;
    case 'POST':
        handlePostUser();
        break;
    case 'PUT':
        handlePutUser();
        break;
    case 'DELETE':
        handleDeleteUser();
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}

function handleGetUsers() {
    global $supabase;
    
    $id = $_GET['id'] ?? null;
    
    try {
        if ($id) {
            $user = $supabase->getById('users', $id);
            unset($user['password']);
            echo json_encode(['success' => true, 'data' => $user]);
            return;
        }
        
        $users = $supabase->getAll('users');
        
        foreach ($users as &$user) {
            unset($user['password']);
        }
        
        echo json_encode(['success' => true, 'data' => $users]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function handlePostUser() {
    global $supabase;
    
    if (!hasPermission('users', 'create')) {
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || !isset($data['username']) || !isset($data['email']) || !isset($data['full_name']) || !isset($data['role'])) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        return;
    }

    // Only a super_admin may create another super_admin.
    if (($_SESSION['user_role'] ?? '') !== 'super_admin' && $data['role'] === 'super_admin') {
        echo json_encode(['success' => false, 'error' => 'Only a Super Admin can create a Super Admin account.']);
        return;
    }

    try {
        $existing = $supabase->from('users')->select('*')->eq('username', $data['username'])->execute();
        if (!empty($existing)) {
            echo json_encode(['success' => false, 'error' => 'Username already exists']);
            return;
        }
        
        $existing = $supabase->from('users')->select('*')->eq('email', $data['email'])->execute();
        if (!empty($existing)) {
            echo json_encode(['success' => false, 'error' => 'Email already exists']);
            return;
        }
        
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        $data['is_active'] = $data['is_active'] ?? 1;
        
        if (!isset($data['password']) || empty($data['password'])) {
            $data['password'] = 'password123';
        }
        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);

        $result = $supabase->insert('users', $data);
        
        if ($result) {
            $created = is_array($result) && isset($result[0]) ? $result[0] : $result;
            unset($created['password']);
            logActivity($_SESSION['user_id'], 'create', 'users', "Created user: {$data['full_name']}");
            echo json_encode(['success' => true, 'data' => $created, 'message' => 'User created successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to create user']);
        }
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function handlePutUser() {
    global $supabase;
    
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $_GET['id'] ?? ($data['id'] ?? null);
    unset($data['id']);

    if (!$id || !$data) {
        echo json_encode(['success' => false, 'error' => 'Invalid data']);
        return;
    }

    $isSelf       = ($id == $_SESSION['user_id']);
    $myRole       = $_SESSION['user_role'] ?? '';
    $isSuperAdmin = ($myRole === 'super_admin');
    $isAdmin      = in_array($myRole, ['owner', 'super_admin'], true);

    if (!$isSelf && !hasPermission('users', 'edit')) {
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        return;
    }

    // Owner and super_admin can edit their own account fully; other roles
    // must use the request flow (api/profile-change-request.php) for email/phone.
    if ($isSelf && !$isAdmin) {
        unset($data['email'], $data['phone']);
    }

    // Owner may not modify a Super Admin account, nor grant the Super Admin role.
    // Snapshot the target's current record (used for the owner/super-admin
    // guard below and for the "your details were changed" notification).
    $targetBefore = (!$isSelf) ? $supabase->getById('users', $id) : null;

    if ($myRole === 'owner') {
        if (!$isSelf && $targetBefore && ($targetBefore['role'] ?? '') === 'super_admin') {
            echo json_encode(['success' => false, 'error' => 'You cannot modify a Super Admin account.']);
            return;
        }
        if (isset($data['role']) && $data['role'] === 'super_admin') {
            echo json_encode(['success' => false, 'error' => 'You cannot assign the Super Admin role.']);
            return;
        }
    }

    try {
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        if (isset($data['current_password']) && isset($data['new_password'])) {
            $user = $supabase->getById('users', $id);
            if (!$user) {
                echo json_encode(['success' => false, 'error' => 'User not found']);
                return;
            }
            // Support both a bcrypt hash (accounts that went through password
            // reset, or created after this fix) and legacy plaintext rows —
            // same dual-mode check api/auth-api.php's login uses. A pure
            // string comparison here always failed for any hashed password.
            $storedPassword = (string) ($user['password'] ?? '');
            $currentValid = (password_get_info($storedPassword)['algo'] !== false)
                ? password_verify($data['current_password'], $storedPassword)
                : hash_equals($storedPassword, (string) $data['current_password']);
            if (!$currentValid) {
                echo json_encode(['success' => false, 'error' => 'Current password is incorrect']);
                return;
            }
            // Always store the new password hashed, not plaintext.
            $data['password'] = password_hash($data['new_password'], PASSWORD_DEFAULT);
            unset($data['current_password']);
            unset($data['new_password']);
        } elseif (isset($data['password']) && $data['password'] !== '') {
            // Admin setting a user's password directly (User Management) —
            // also hash it rather than storing it plaintext.
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        if (isset($data['password']) && empty($data['password'])) {
            unset($data['password']);
        }

        // Friendly duplicate-email check — without this, a collision only
        // surfaces as a raw "duplicate key value violates unique constraint"
        // error from Supabase instead of a message an admin can act on.
        if (isset($data['email'])) {
            $emailOwner = $supabase->from('users')->select('id')->ilike('email', $data['email'])->execute();
            $emailOwner = !empty($emailOwner) ? $emailOwner[0] : null;
            if ($emailOwner && (int)$emailOwner['id'] !== (int)$id) {
                echo json_encode(['success' => false, 'error' => 'That email address is already in use by another account.']);
                return;
            }
        }

        $result = $supabase->update('users', $id, $data);

        if ($result) {
            unset($result['password']);
            
            if ($isSelf) {
                $_SESSION['full_name'] = $result['full_name'] ?? $_SESSION['full_name'];
                $_SESSION['email'] = $result['email'] ?? $_SESSION['email'];
            }
            
            logActivity($_SESSION['user_id'], 'update', 'users', "Updated user ID: $id");

            // Tell the affected user when an admin changes their email or phone
            // (closes the loop on a profile-change request they submitted).
            if (!$isSelf && $targetBefore) {
                notifyContactDetailChange($targetBefore, $result, $_SESSION['full_name'] ?? 'An administrator');
            }

            echo json_encode(['success' => true, 'data' => $result, 'message' => 'User updated successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update user']);
        }
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function handleDeleteUser() {
    global $supabase;
    
    if (!hasPermission('users', 'delete')) {
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        return;
    }
    
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'User ID required']);
        return;
    }
    
    if ($id == $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'error' => 'Cannot delete your own account']);
        return;
    }

    // Owner may not archive a Super Admin account.
    if (($_SESSION['user_role'] ?? '') === 'owner') {
        $target = $supabase->getById('users', $id);
        if ($target && ($target['role'] ?? '') === 'super_admin') {
            echo json_encode(['success' => false, 'error' => 'You cannot archive a Super Admin account.']);
            return;
        }
    }

    try {
        $result = archiveRecord('users', $id);
        if ($result) {
            logActivity($_SESSION['user_id'], 'archive', 'users', "Archived record ID: $id");
            echo json_encode(['success' => true, 'message' => 'Record archived successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to archive record']);
        }
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Email the affected user after an admin changes their email and/or phone.
 * Sends to the new address (if email changed) plus the old one, so the user
 * always gets a copy. Failures are logged, never fatal.
 */
function notifyContactDetailChange($before, $after, $adminName) {
    if (!function_exists('emailShell')) return;

    $oldEmail = strtolower(trim((string)($before['email'] ?? '')));
    $newEmail = strtolower(trim((string)($after['email'] ?? '')));
    $oldPhone = preg_replace('/[^\d+]/', '', (string)($before['phone'] ?? ''));
    $newPhone = preg_replace('/[^\d+]/', '', (string)($after['phone'] ?? ''));

    $emailChanged = $newEmail !== '' && $newEmail !== $oldEmail;
    $phoneChanged = $newPhone !== $oldPhone;
    if (!$emailChanged && !$phoneChanged) return;

    $e = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
    $td  = 'padding:9px 14px;border:1px solid #e2e8f0;font-size:14px';
    $tdH = $td . ';background:#f8fafc;text-align:left;font-size:12px;text-transform:uppercase;letter-spacing:0.04em;color:#64748b';

    $rows = '';
    if ($emailChanged) {
        $rows .= '<tr><td style="' . $td . ';font-weight:600">Email</td>'
              . '<td style="' . $td . ';color:#94a3b8">' . $e($before['email'] ?? '—') . '</td>'
              . '<td style="' . $td . ';color:#c2410c;font-weight:700">' . $e($after['email'] ?? '') . '</td></tr>';
    }
    if ($phoneChanged) {
        $rows .= '<tr><td style="' . $td . ';font-weight:600">Phone</td>'
              . '<td style="' . $td . ';color:#94a3b8">' . $e($before['phone'] ?: '—') . '</td>'
              . '<td style="' . $td . ';color:#c2410c;font-weight:700">' . $e($after['phone'] ?? '') . '</td></tr>';
    }

    $body = '<p>Hello ' . $e($after['full_name'] ?: $after['username']) . ',</p>'
        . '<p><strong>' . $e($adminName) . '</strong> updated your contact details on your Sunder Solar MIS account:</p>'
        . '<table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:8px 0 20px;width:100%">'
        . '<tr><th style="' . $tdH . '">Field</th><th style="' . $tdH . '">Previous</th><th style="' . $tdH . '">Now</th></tr>'
        . $rows . '</table>'
        . '<p style="font-size:13px;color:#64748b;padding-top:16px;border-top:1px solid #e2e8f0">'
        . 'If you did not request this change, contact an administrator immediately.</p>';

    $html = emailShell('Your contact details were updated', $body, $adminName . ' updated your account details.');

    $recipients = array_unique(array_filter([
        $after['email'] ?? '',
        $before['email'] ?? '',
    ], function ($a) { return filter_var($a, FILTER_VALIDATE_EMAIL); }));

    foreach ($recipients as $to) {
        try {
            sendAppEmail($to, 'Your Sunder Solar MIS contact details were updated', $html);
        } catch (Throwable $e) {
            error_log('notifyContactDetailChange: failed for ' . $to . ' — ' . $e->getMessage());
        }
    }
}
?>