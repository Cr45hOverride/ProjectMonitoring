<?php
/**
 * API: Create New Task
 * Accessible by: Supervisor, Admin
 */

declare(strict_types=1);

define('IS_API', true);

require_once __DIR__ . '/../config/setting.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(['admin', 'supervisor']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request method.'], 405);
}

$currentUser = getCurrentUser();
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$internId = (int)($_POST['intern_id'] ?? 0);
$priority = strtolower(trim($_POST['priority'] ?? 'medium'));
$startDate = trim($_POST['start_date'] ?? '');
$dueDate = trim($_POST['due_date'] ?? '');

if (empty($title) || empty($internId) || empty($startDate) || empty($dueDate)) {
    jsonResponse(['success' => false, 'message' => 'Please fill in all required fields (Title, Intern, Start Date, Due Date).'], 422);
}

if (!in_array($priority, ['low', 'medium', 'high'], true)) {
    $priority = 'medium';
}

// Ensure selected intern exists and is active
$pdo = getDBConnection();
$stmtIntern = $pdo->prepare('SELECT user_id, role, status FROM users WHERE user_id = :id AND role = "intern" LIMIT 1');
$stmtIntern->execute([':id' => $internId]);
$intern = $stmtIntern->fetch();

if (!$intern || $intern['status'] !== 'active') {
    jsonResponse(['success' => false, 'message' => 'Selected intern is invalid or inactive.'], 422);
}

// Determine supervisor_id
$supervisorId = $currentUser['role'] === 'supervisor' ? $currentUser['user_id'] : (int)($_POST['supervisor_id'] ?? $currentUser['user_id']);

try {
    $pdo->beginTransaction();

    $stmtInsert = $pdo->prepare('
        INSERT INTO tasks (title, description, supervisor_id, intern_id, current_stage, priority, start_date, due_date, created_at)
        VALUES (:title, :description, :supervisor_id, :intern_id, "ASSIGNED", :priority, :start_date, :due_date, NOW())
    ');
    $stmtInsert->execute([
        ':title'         => $title,
        ':description'   => $description,
        ':supervisor_id' => $supervisorId,
        ':intern_id'     => $internId,
        ':priority'      => $priority,
        ':start_date'    => $startDate,
        ':due_date'      => $dueDate
    ]);

    $taskId = (int)$pdo->lastInsertId();

    // Create initial immutable process log for ASSIGNED
    $stmtLog = $pdo->prepare('
        INSERT INTO task_process_logs (task_id, actor_id, stage_name, remarks, logged_at)
        VALUES (:task_id, :actor_id, "ASSIGNED", :remarks, NOW())
    ');
    $initialRemarks = 'Task created and assigned to intern with ' . ucfirst($priority) . ' priority.';
    $stmtLog->execute([
        ':task_id'  => $taskId,
        ':actor_id' => $currentUser['user_id'],
        ':remarks'  => $initialRemarks
    ]);

    $pdo->commit();

    jsonResponse([
        'success' => true,
        'message' => 'Task successfully assigned!',
        'task_id' => $taskId
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Create task error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Database error while creating task.'], 500);
}
