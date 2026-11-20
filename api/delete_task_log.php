<?php
/**
 * API: Delete Task Process Log (Chronological Audit Trail Entry)
 * Accessible by: Admin Only
 * Deletes audit log entry, associated disk media evidence files, and synchronizes task stage
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

$logId = (int)($input['log_id'] ?? 0);
if ($logId <= 0) {
    jsonResponse(['success' => false, 'message' => 'Valid Log ID is required.'], 422);
}

$pdo = getDBConnection();

// Fetch target log entry and its associated task
$stmtLog = $pdo->prepare('
    SELECT l.log_id, l.task_id, l.stage_name, l.remarks, t.title as task_title, t.current_stage
    FROM task_process_logs l
    INNER JOIN tasks t ON l.task_id = t.task_id
    WHERE l.log_id = :log_id
    LIMIT 1
');
$stmtLog->execute([':log_id' => $logId]);
$log = $stmtLog->fetch();

if (!$log) {
    jsonResponse(['success' => false, 'message' => 'Audit log entry not found or already deleted.'], 404);
}

$taskId = (int)$log['task_id'];

try {
    $pdo->beginTransaction();

    // 1. Retrieve all media files attached to this log and delete from disk
    $stmtMedia = $pdo->prepare('
        SELECT file_path 
        FROM task_media
        WHERE log_id = :log_id
    ');
    $stmtMedia->execute([':log_id' => $logId]);
    $mediaFiles = $stmtMedia->fetchAll(PDO::FETCH_COLUMN);

    foreach ($mediaFiles as $filePath) {
        $fullPath = resolveMediaPath($filePath);
        if (!empty($fullPath) && file_exists($fullPath) && is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

    // 2. Delete the audit log entry (cascades to task_media in DB)
    $stmtDelete = $pdo->prepare('DELETE FROM task_process_logs WHERE log_id = :log_id');
    $stmtDelete->execute([':log_id' => $logId]);

    // 3. Synchronize task state with remaining audit logs
    $stmtLatestLog = $pdo->prepare('
        SELECT stage_name, logged_at 
        FROM task_process_logs 
        WHERE task_id = :task_id 
        ORDER BY logged_at DESC, log_id DESC 
        LIMIT 1
    ');
    $stmtLatestLog->execute([':task_id' => $taskId]);
    $latestRemaining = $stmtLatestLog->fetch();

    if ($latestRemaining) {
        $newStage = $latestRemaining['stage_name'];
        $stmtUpdateStage = $pdo->prepare('
            UPDATE tasks 
            SET current_stage = :current_stage 
            WHERE task_id = :task_id
        ');
        $stmtUpdateStage->execute([
            ':current_stage' => $newStage,
            ':task_id'       => $taskId
        ]);
    } else {
        // Fallback to ASSIGNED if no logs remain
        $pdo->prepare('UPDATE tasks SET current_stage = "ASSIGNED" WHERE task_id = :task_id')
            ->execute([':task_id' => $taskId]);
    }

    // 4. Synchronize completion timestamp if no completion log remains
    $stmtCheckCompleted = $pdo->prepare('
        SELECT COUNT(*) FROM task_process_logs 
        WHERE task_id = :task_id AND stage_name IN ("COMPLETED_PENDING_REVIEW", "APPROVED")
    ');
    $stmtCheckCompleted->execute([':task_id' => $taskId]);
    if ((int)$stmtCheckCompleted->fetchColumn() === 0) {
        $pdo->prepare('UPDATE tasks SET completed_at = NULL WHERE task_id = :task_id')
            ->execute([':task_id' => $taskId]);
    }

    // 5. Synchronize approval timestamp if no approved log remains
    $stmtCheckApproved = $pdo->prepare('
        SELECT COUNT(*) FROM task_process_logs 
        WHERE task_id = :task_id AND stage_name = "APPROVED"
    ');
    $stmtCheckApproved->execute([':task_id' => $taskId]);
    if ((int)$stmtCheckApproved->fetchColumn() === 0) {
        $pdo->prepare('UPDATE tasks SET approved_at = NULL WHERE task_id = :task_id')
            ->execute([':task_id' => $taskId]);
    }

    $pdo->commit();

    jsonResponse([
        'success' => true,
        'message' => 'Audit log entry #' . $logId . ' (' . $log['stage_name'] . ') was successfully deleted.',
        'log_id'  => $logId,
        'task_id' => $taskId
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Delete task log error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Database error while deleting audit log entry.'], 500);
}
