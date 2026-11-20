<?php
/**
 * API: Update Process Log Activity (Remarks & Reference URLs)
 * Accessible by: Admin, Supervisor, and Intern on their tasks
 */

declare(strict_types=1);

define('IS_API', true);

require_once __DIR__ . '/../config/setting.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request method.'], 405);
}

// Accept JSON payload or standard POST
$input = json_decode(file_get_contents('php://input'), true);
if (empty($input)) {
    $input = $_POST;
}

$currentUser = getCurrentUser();
$logId = (int)($input['log_id'] ?? 0);
$remarks = trim($input['remarks'] ?? '');
$referenceUrls = trim($input['reference_urls'] ?? '');
$loggedAtInput = trim($input['logged_at'] ?? '');

if ($logId <= 0) {
    jsonResponse(['success' => false, 'message' => 'Valid Log ID is required.'], 422);
}

if (empty($remarks)) {
    jsonResponse(['success' => false, 'message' => 'Remarks cannot be empty.'], 422);
}

$pdo = getDBConnection();

// Fetch log and associated task
$stmtLog = $pdo->prepare('
    SELECT l.*, t.supervisor_id, t.intern_id
    FROM task_process_logs l
    INNER JOIN tasks t ON l.task_id = t.task_id
    WHERE l.log_id = :id
    LIMIT 1
');
$stmtLog->execute([':id' => $logId]);
$log = $stmtLog->fetch();

if (!$log) {
    jsonResponse(['success' => false, 'message' => 'Activity log not found.'], 404);
}

// Permission Check:
// Admin can edit any
// Supervisor can edit if supervisor on task
// Intern can edit if intern on task
$canEdit = false;
if (isAdmin()) {
    $canEdit = true;
} elseif (isSupervisor() && (int)$log['supervisor_id'] === $currentUser['user_id']) {
    $canEdit = true;
} elseif (isIntern() && (int)$log['intern_id'] === $currentUser['user_id']) {
    $canEdit = true;
}

if (!$canEdit) {
    jsonResponse(['success' => false, 'message' => 'You do not have permission to edit this activity log.'], 403);
}

try {
    $updateFields = [
        'remarks = :remarks',
        'reference_urls = :reference_urls'
    ];
    $params = [
        ':remarks'        => $remarks,
        ':reference_urls' => !empty($referenceUrls) ? $referenceUrls : null,
        ':id'             => $logId
    ];

    if (!empty($loggedAtInput)) {
        try {
            $dt = new DateTime($loggedAtInput);
            $updateFields[] = 'logged_at = :logged_at';
            $params[':logged_at'] = $dt->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            // ignore invalid date
        }
    }

    $setClause = implode(', ', $updateFields);
    $stmtUpdate = $pdo->prepare("UPDATE task_process_logs SET $setClause WHERE log_id = :id");
    $stmtUpdate->execute($params);

    jsonResponse([
        'success'        => true,
        'message'        => 'Activity log remarks and reference URLs updated successfully!',
        'log_id'         => $logId,
        'task_id'        => (int)$log['task_id'],
        'remarks'        => $remarks,
        'reference_urls' => $referenceUrls
    ]);

} catch (Exception $e) {
    error_log('Update task log error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Database error while updating activity log.'], 500);
}
