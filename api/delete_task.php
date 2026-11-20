<?php
/**
 * API: Delete Task
 * Accessible by: Admin Only
 * Deletes task, associated disk media evidence, and cascades to logs & budgets
 */

declare(strict_types=1);

define('IS_API', true);

require_once __DIR__ . '/../config/setting.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request method.'], 405);
}

// Accept JSON payload or standard POST
$input = json_decode(file_get_contents('php://input'), true);
if (empty($input)) {
    $input = $_POST;
}

$taskId = (int)($input['task_id'] ?? 0);
if ($taskId <= 0) {
    jsonResponse(['success' => false, 'message' => 'Valid Task ID is required.'], 422);
}

$pdo = getDBConnection();

// Verify task exists
$stmt = $pdo->prepare('SELECT task_id, title FROM tasks WHERE task_id = :id LIMIT 1');
$stmt->execute([':id' => $taskId]);
$task = $stmt->fetch();

if (!$task) {
    jsonResponse(['success' => false, 'message' => 'Task not found or already deleted.'], 404);
}

try {
    $pdo->beginTransaction();

    // 1. Retrieve all attached media files to clean up from disk
    $stmtMedia = $pdo->prepare('
        SELECT m.file_path 
        FROM task_media m
        INNER JOIN task_process_logs l ON m.log_id = l.log_id
        WHERE l.task_id = :task_id
    ');
    $stmtMedia->execute([':task_id' => $taskId]);
    $mediaFiles = $stmtMedia->fetchAll(PDO::FETCH_COLUMN);

    foreach ($mediaFiles as $filePath) {
        $fullPath = resolveMediaPath($filePath);
        if (!empty($fullPath) && file_exists($fullPath) && is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

    // 2. Delete task from database (foreign key constraints cascade to logs, media, and budget_requests)
    $stmtDelete = $pdo->prepare('DELETE FROM tasks WHERE task_id = :id');
    $stmtDelete->execute([':id' => $taskId]);

    $pdo->commit();

    jsonResponse([
        'success' => true,
        'message' => 'Task #' . $taskId . ' ("' . $task['title'] . '") and all associated audit records were deleted successfully.',
        'task_id' => $taskId
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Delete task error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Database error while deleting task.'], 500);
}
