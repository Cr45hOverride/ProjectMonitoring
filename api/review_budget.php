<?php
/**
 * API: Review Budget Request (Approve / Reject)
 * Accessible by: Supervisor, Admin
 */

declare(strict_types=1);

define('IS_API', true);

require_once __DIR__ . '/../config/setting.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(['admin', 'manager', 'hod', 'supervisor']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request method.'], 405);
}

// Accept JSON payload or standard POST
$input = json_decode(file_get_contents('php://input'), true);
if (empty($input)) {
    $input = $_POST;
}

$currentUser = getCurrentUser();
$budgetId = (int)($input['budget_id'] ?? 0);
$status = strtolower(trim($input['status'] ?? ''));

if ($budgetId <= 0 || !in_array($status, ['approved', 'rejected'], true)) {
    jsonResponse(['success' => false, 'message' => 'Invalid budget ID or status action.'], 422);
}

$pdo = getDBConnection();

$stmtBudget = $pdo->prepare('
    SELECT b.*, t.title, t.supervisor_id, t.intern_id
    FROM budget_requests b
    INNER JOIN tasks t ON b.task_id = t.task_id
    WHERE b.budget_id = :id
    LIMIT 1
');
$stmtBudget->execute([':id' => $budgetId]);
$budget = $stmtBudget->fetch();

if (!$budget) {
    jsonResponse(['success' => false, 'message' => 'Budget request not found.'], 404);
}

// Assignee cannot approve their own budget request
if ((int)$budget['intern_id'] === $currentUser['user_id']) {
    jsonResponse(['success' => false, 'message' => 'Assignees cannot approve their own budget requests.'], 403);
}

// Check review authority: must be task supervisor, admin, manager, or HOD with task access
$isTaskSupervisor = ((int)$budget['supervisor_id'] === $currentUser['user_id']);
$isElevated = in_array($currentUser['role'], ['admin', 'manager'], true);
$isHodOfTask = ($currentUser['role'] === 'hod' && hasTaskAccess((int)$budget['task_id'], $currentUser));

if (!$isTaskSupervisor && !$isElevated && !$isHodOfTask) {
    jsonResponse(['success' => false, 'message' => 'You do not have permission to review budgets for this task.'], 403);
}

try {
    $pdo->beginTransaction();

    // Update budget status
    $stmtUpdate = $pdo->prepare('
        UPDATE budget_requests 
        SET status = :status, reviewed_by = :reviewer, reviewed_at = NOW() 
        WHERE budget_id = :id
    ');
    $stmtUpdate->execute([
        ':status'   => $status,
        ':reviewer' => $currentUser['user_id'],
        ':id'       => $budgetId
    ]);

    // Update corresponding task stage and create audit log
    $targetStage = ($status === 'approved') ? 'BUDGET_APPROVED' : 'PLANNING';
    $stmtTask = $pdo->prepare('UPDATE tasks SET current_stage = :stage WHERE task_id = :task_id');
    $stmtTask->execute([
        ':stage'   => $targetStage,
        ':task_id' => $budget['task_id']
    ]);

    $logRemarks = ($status === 'approved')
        ? 'Budget request of RM ' . number_format((float)$budget['amount'], 2) . ' approved by ' . $currentUser['full_name'] . '.'
        : 'Budget request of RM ' . number_format((float)$budget['amount'], 2) . ' rejected by ' . $currentUser['full_name'] . '. Task reverted to Planning.';

    $stmtLog = $pdo->prepare('
        INSERT INTO task_process_logs (task_id, actor_id, stage_name, remarks, logged_at)
        VALUES (:task_id, :actor_id, :stage_name, :remarks, NOW())
    ');
    $stmtLog->execute([
        ':task_id'    => $budget['task_id'],
        ':actor_id'   => $currentUser['user_id'],
        ':stage_name' => $targetStage,
        ':remarks'    => $logRemarks
    ]);

    $pdo->commit();

    jsonResponse([
        'success' => true,
        'message' => 'Budget request ' . $status . ' successfully!'
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Review budget error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Database error while reviewing budget.'], 500);
}
