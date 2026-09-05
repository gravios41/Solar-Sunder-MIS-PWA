<?php
// config/functions.php
// Helper functions for the application

// Format currency
function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}

// Format date
function formatDate($date, $format = 'M d, Y') {
    if (!$date) return '-';
    return date($format, strtotime($date));
}

// Format datetime
function formatDateTime($datetime, $format = 'M d, Y h:i A') {
    if (!$datetime) return '-';
    return date($format, strtotime($datetime));
}

// Get status badge HTML
function getStatusBadge($status) {
    $badges = [
        'active' => 'badge-success',
        'inactive' => 'badge-danger',
        'pending' => 'badge-warning',
        'completed' => 'badge-success',
        'in_progress' => 'badge-info',
        'planning' => 'badge-secondary',
        'installation' => 'badge-warning',
        'on_hold' => 'badge-danger',
        'approved' => 'badge-success',
        'rejected' => 'badge-danger',
        'draft' => 'badge-secondary',
        'under_review' => 'badge-info',
        'scheduled' => 'badge-warning'
    ];
    
    $class = $badges[$status] ?? 'badge-secondary';
    $label = ucwords(str_replace('_', ' ', $status));
    
    return "<span class=\"badge $class\">$label</span>";
}

// Get priority badge
function getPriorityBadge($priority) {
    $badges = [
        'low' => 'badge-secondary',
        'medium' => 'badge-info',
        'high' => 'badge-warning',
        'urgent' => 'badge-danger'
    ];
    
    $class = $badges[$priority] ?? 'badge-secondary';
    $label = ucfirst($priority);
    
    return "<span class=\"badge $class\">$label</span>";
}

// Escape HTML
function escape($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Generate random code
function generateCode($prefix, $length = 6) {
    return $prefix . str_pad(rand(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
}

// Log activity - CORRECTED VERSION
function logActivity($userId, $action, $module, $description) {
    // Access global supabase connection
    global $supabase;
    
    // If $supabase is not set or is null, try to get it from the $GLOBALS array
    if (!isset($supabase) || $supabase === null) {
        if (isset($GLOBALS['supabase']) && $GLOBALS['supabase'] !== null) {
            $supabase = $GLOBALS['supabase'];
        } else {
            error_log("Supabase connection not available in logActivity() function");
            return false;
        }
    }
    
    // Check if supabase has the insert method
    if (!method_exists($supabase, 'insert')) {
        error_log("Supabase client doesn't have 'insert' method");
        return false;
    }
    
    try {
        // Direct insert using SupabaseClient's insert method
        $result = $supabase->insert('activity_logs', [
            'user_id' => $userId,
            'action' => $action,
            'module' => $module,
            'description' => $description,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log("Error in logActivity: " . $e->getMessage());
        return false;
    }
}

// Show toast message (stores in session for next page load)
function setToast($message, $type = 'success') {
    $_SESSION['toast'] = ['message' => $message, 'type' => $type];
}

// Display toast if exists
function displayToast() {
    if (isset($_SESSION['toast'])) {
        $toast = $_SESSION['toast'];
        unset($_SESSION['toast']);
        echo "<script>showToast('{$toast['message']}', '{$toast['type']}');</script>";
    }
}

// Check if page is accessible
function checkPageAccess($module) {
    if (!hasPermission($module, 'view')) {
        setToast('You do not have permission to access this page', 'error');
        header('Location: ' . SITE_URL . firstAccessibleModuleUrl());
        exit();
    }
}

// The first module (in priority order) the current role actually has 'view'
// on — used as a safe redirect target so a role locked out of dashboard.php
// itself (e.g. admin) doesn't get bounced right back into another denial.
function firstAccessibleModuleUrl() {
    $priority = ['dashboard', 'tasks', 'customers', 'quotations', 'projects', 'installations', 'reports', 'inventory', 'energy-assessments'];
    foreach ($priority as $module) {
        if (hasPermission($module, 'view')) {
            return "modules/$module.php";
        }
    }
    return 'auth/logout.php';
}

// Get user initials
function getUserInitials($name) {
    $words = explode(' ', $name);
    $initials = '';
    foreach ($words as $word) {
        $initials .= strtoupper(substr($word, 0, 1));
    }
    return substr($initials, 0, 2);
}

// Pagination helper
function paginate($total, $currentPage, $perPage = 20) {
    $totalPages = ceil($total / $perPage);
    $prevPage = max(1, $currentPage - 1);
    $nextPage = min($totalPages, $currentPage + 1);

    return [
        'current' => $currentPage,
        'total' => $totalPages,
        'prev' => $prevPage,
        'next' => $nextPage,
        'per_page' => $perPage,
        'offset' => ($currentPage - 1) * $perPage
    ];
}

// Archive a record: copies to archives table, then hard-deletes from original table
function archiveRecord($entityType, $id) {
    global $supabase;
    $codeFields = [
        'tasks'         => 'task_title',
        'projects'      => 'project_name',
        'customers'     => 'name',
        'quotations'    => 'quotation_number',
        'installations' => 'installation_code',
        'inventory'     => 'item_name',
        'reports'       => 'report_name',
        'users'         => 'full_name',
    ];
    $record = $supabase->getById($entityType, $id);
    if (!$record) {
        throw new Exception("Record not found in '$entityType' (ID: $id)");
    }
    $codeField  = $codeFields[$entityType] ?? 'id';
    $entityCode = $record[$codeField] ?? "ID: {$record['id']}";

    // Pass $record as a PHP array — json_encode in the HTTP layer serialises it
    // as a proper JSON object, which Supabase accepts for JSONB columns.
    // Passing json_encode($record) here would double-encode it into a string.
    $archiveResult = $supabase->insert('archives', [
        'entity_type'      => $entityType,
        'entity_id'        => (int)$id,
        'entity_code'      => $entityCode,
        'record_data'      => $record,
        'archived_by'      => (int)($_SESSION['user_id'] ?? 0),
        'archived_by_name' => $_SESSION['full_name'] ?? 'Unknown',
        'archived_at'      => date('Y-m-d H:i:s'),
        'created_at'       => date('Y-m-d H:i:s'),
    ]);
    if (!$archiveResult) {
        throw new Exception("Failed to insert into archives table. Make sure the 'archives' table exists in Supabase.");
    }
    $supabase->delete($entityType, $id);
    return true;
}

// Called whenever a task's status becomes 'completed'. If every task on that
// task's project is now completed too, the whole pipeline is closed out:
// the project and its installation(s) are marked completed, and a
// completion report is auto-generated. This is the "Task -> Report" link
// shared by both automated approval chains (Assessment/Quotation -> Project
// -> Installation -> Task -> Report) — kept as a single function so both
// task-checklist-api.php and tasks-api.php can call it without duplicating
// the completion logic.
function completeProjectPipelineIfDone($supabase, $projectId) {
    if (!$projectId) return;

    try {
        $project = $supabase->getById('projects', $projectId);
        if (!$project || ($project['status'] ?? '') === 'completed') {
            return; // no project, or pipeline already closed out
        }

        $tasks = $supabase->getAll('tasks', ['project_id' => 'eq.' . $projectId, 'deleted_at' => 'is.null', 'select' => 'status']) ?: [];
        if (empty($tasks)) return;

        foreach ($tasks as $t) {
            if (($t['status'] ?? '') !== 'completed') {
                return; // still work left to do
            }
        }

        $today = date('Y-m-d');

        $supabase->update('projects', $projectId, [
            'status'     => 'completed',
            'progress'   => 100,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $installations = $supabase->getAll('installations', ['project_id' => 'eq.' . $projectId, 'deleted_at' => 'is.null']) ?: [];
        foreach ($installations as $inst) {
            $supabase->update('installations', $inst['id'], [
                'status'     => 'completed',
                'progress'   => 100,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $customer = !empty($project['customer_id']) ? $supabase->getById('customers', $project['customer_id']) : null;

        $supabase->insert('reports', [
            'report_name'    => sprintf('Installation Completion — %s (%s)', $project['project_name'] ?? 'Project', $customer['name'] ?? 'Customer'),
            'report_type'    => 'Installations',
            'period'         => date('F Y'),
            'date_from'      => $project['start_date'] ?? null,
            'date_to'        => $today,
            'generated_date' => $today,
            'format'         => 'PDF',
            'file_size'      => '—',
            'created_at'     => date('Y-m-d H:i:s'),
        ]);

        logActivity($_SESSION['user_id'] ?? 0, 'update', 'projects', "All tasks complete — project \"{$project['project_name']}\" and its installation auto-marked completed, completion report generated");
    } catch (Exception $e) {
        error_log('completeProjectPipelineIfDone failed: ' . $e->getMessage());
    }
}
?>