<?php
/**
 * API: Update Task Stage & Upload Media Evidence
 * Handles State Machine Transitions, Remarks, Budgets, and Attachments
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

$currentUser = getCurrentUser();
$taskId = (int)($_POST['task_id'] ?? 0);
$targetStage = trim($_POST['target_stage'] ?? '');
$remarks = trim($_POST['remarks'] ?? '');

if ($taskId <= 0 || empty($targetStage)) {
    jsonResponse(['success' => false, 'message' => 'Task ID and target stage are required.'], 422);
}

if (!hasTaskAccess($taskId, $currentUser)) {
    jsonResponse(['success' => false, 'message' => 'You do not have permission to modify this task.'], 403);
}

$pdo = getDBConnection();
$stmtTask = $pdo->prepare('SELECT * FROM tasks WHERE task_id = :task_id LIMIT 1');
$stmtTask->execute([':task_id' => $taskId]);
$task = $stmtTask->fetch();

if (!$task) {
    jsonResponse(['success' => false, 'message' => 'Task not found.'], 404);
}

$allowedStages = [
    'ASSIGNED', 'PLANNING', 'BUDGET_PENDING', 'BUDGET_APPROVED',
    'IN_EXECUTION', 'REVISION_REQUIRED', 'COMPLETED_PENDING_REVIEW', 'APPROVED'
];

if (!in_array($targetStage, $allowedStages, true)) {
    jsonResponse(['success' => false, 'message' => 'Invalid stage transition.'], 422);
}

// Role-based State Machine Verification
$role = $currentUser['role'];
$currentStage = $task['current_stage'];

if ($role === 'intern') {
    $internAllowedTransitions = [
        'ASSIGNED'                 => ['PLANNING'],
        'PLANNING'                 => ['BUDGET_PENDING', 'IN_EXECUTION'],
        'BUDGET_APPROVED'          => ['IN_EXECUTION'],
        'IN_EXECUTION'             => ['IN_EXECUTION', 'COMPLETED_PENDING_REVIEW'],
        'REVISION_REQUIRED'        => ['IN_EXECUTION', 'COMPLETED_PENDING_REVIEW'],
        'COMPLETED_PENDING_REVIEW' => [] // intern cannot change until supervisor reviews
    ];

    $permitted = $internAllowedTransitions[$currentStage] ?? [];
    if (!in_array($targetStage, $permitted, true) && $targetStage !== $currentStage) {
        jsonResponse(['success' => false, 'message' => "Interns cannot transition task from {$currentStage} to {$targetStage}."], 403);
    }
} elseif ($role === 'supervisor') {
    // Supervisors can review completed tasks, approve budgets, or request revisions
    if ($currentStage === 'COMPLETED_PENDING_REVIEW' && !in_array($targetStage, ['APPROVED', 'REVISION_REQUIRED'], true)) {
        jsonResponse(['success' => false, 'message' => 'Supervisors must choose Approved or Revision Required.'], 422);
    }
}

try {
    $pdo->beginTransaction();

    $completedAt = $task['completed_at'];
    $approvedAt = $task['approved_at'];

    // Update completion timestamps
    if ($targetStage === 'COMPLETED_PENDING_REVIEW' && empty($completedAt)) {
        $completedAt = date('Y-m-d H:i:s');
    } elseif ($targetStage === 'APPROVED') {
        $approvedAt = date('Y-m-d H:i:s');
        if (empty($completedAt)) {
            $completedAt = date('Y-m-d H:i:s');
        }
    } elseif ($targetStage === 'REVISION_REQUIRED') {
        // Reset completed_at on revision so intern can submit fresh completion timestamp later
        $completedAt = null;
    }

    // Update tasks table
    $stmtUpdateTask = $pdo->prepare('
        UPDATE tasks 
        SET current_stage = :stage, 
            completed_at = :completed_at, 
            approved_at = :approved_at 
        WHERE task_id = :task_id
    ');
    $stmtUpdateTask->execute([
        ':stage'        => $targetStage,
        ':completed_at' => $completedAt,
        ':approved_at'  => $approvedAt,
        ':task_id'      => $taskId
    ]);

    // Insert Process Audit Log
    $logRemarks = $remarks;
    if (empty($logRemarks)) {
        $logRemarks = "Stage transitioned to {$targetStage}.";
    }

    $referenceUrls = trim($_POST['reference_urls'] ?? '');

    $stmtLog = $pdo->prepare('
        INSERT INTO task_process_logs (task_id, actor_id, stage_name, remarks, reference_urls, logged_at)
        VALUES (:task_id, :actor_id, :stage_name, :remarks, :reference_urls, NOW())
    ');
    $stmtLog->execute([
        ':task_id'        => $taskId,
        ':actor_id'       => $currentUser['user_id'],
        ':stage_name'     => $targetStage,
        ':remarks'        => $logRemarks,
        ':reference_urls' => !empty($referenceUrls) ? $referenceUrls : null
    ]);
    $logId = (int)$pdo->lastInsertId();

    // Handle Budget Request if target stage is BUDGET_PENDING
    if ($targetStage === 'BUDGET_PENDING') {
        $budgetAmount = (float)($_POST['budget_amount'] ?? 0);
        $budgetJustification = trim($_POST['budget_justification'] ?? '');

        if ($budgetAmount <= 0 || empty($budgetJustification)) {
            $pdo->rollBack();
            jsonResponse(['success' => false, 'message' => 'Please provide a valid budget amount (> 0) and justification.'], 422);
        }

        $stmtBudget = $pdo->prepare('
            INSERT INTO budget_requests (task_id, amount, justification, status, requested_at)
            VALUES (:task_id, :amount, :justification, "pending", NOW())
        ');
        $stmtBudget->execute([
            ':task_id'       => $taskId,
            ':amount'        => $budgetAmount,
            ':justification' => $budgetJustification
        ]);
    }

    // Handle Media Evidence Uploads (multiple files)
    if (!empty($_FILES['evidence_files']['name'][0])) {
        $year = date('Y');
        $month = date('m');
        $targetDirRel = UPLOAD_DIR_REL . '/' . $year . '/' . $month;
        $targetDirAbs = ROOT_PATH . '/' . $targetDirRel;

        if (!is_dir($targetDirAbs)) {
            mkdir($targetDirAbs, 0755, true);
        }

        $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'mp4', 'mov'];
        $imageExts = ['jpg', 'jpeg', 'png', 'webp'];
        $videoExts = ['mp4', 'mov'];

        $fileCount = count($_FILES['evidence_files']['name']);
        for ($i = 0; $i < $fileCount; $i++) {
            $error = $_FILES['evidence_files']['error'][$i];
            if ($error !== UPLOAD_ERR_OK) {
                continue;
            }

            $origName = $_FILES['evidence_files']['name'][$i];
            $tmpPath = $_FILES['evidence_files']['tmp_name'][$i];
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

            if (!in_array($ext, $allowedExts, true)) {
                continue; // Ignore unsupported file types
            }

            $fileType = in_array($ext, $videoExts, true) ? 'video' : 'image';
            $uniqueName = 'evidence_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
            $destRel = $targetDirRel . '/' . $uniqueName;
            $destAbs = $targetDirAbs . '/' . $uniqueName;

            if (move_uploaded_file($tmpPath, $destAbs)) {
                $stmtMedia = $pdo->prepare('
                    INSERT INTO task_media (log_id, file_path, file_type, uploaded_at)
                    VALUES (:log_id, :file_path, :file_type, NOW())
                ');
                $stmtMedia->execute([
                    ':log_id'    => $logId,
                    ':file_path' => $destRel,
                    ':file_type' => $fileType
                ]);
            }
        }
    }

    $pdo->commit();

    jsonResponse([
        'success' => true,
        'message' => 'Task stage successfully updated to ' . $targetStage . '!',
        'new_stage' => $targetStage
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Update task stage error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Database error while updating task stage.'], 500);
}
