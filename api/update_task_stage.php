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

// Perspective-based State Machine Verification (Hierarchical: Assignee vs Supervisor)
$role = $currentUser['role'];
$userId = (int)$currentUser['user_id'];
$currentStage = $task['current_stage'];

$isAssignee = ($userId === (int)$task['intern_id']);
$isSupervisor = ($userId === (int)$task['supervisor_id']);
$isElevated = in_array($role, ['admin', 'manager'], true);

if ($role === 'hod' && !empty($currentUser['department'])) {
    $stmtDept = $pdo->prepare('SELECT department FROM users WHERE user_id = :uid LIMIT 1');
    $stmtDept->execute([':uid' => $task['intern_id']]);
    $assigneeDept = $stmtDept->fetchColumn();
    if (strtolower(trim((string)$assigneeDept)) === strtolower(trim($currentUser['department']))) {
        $isElevated = true;
    }
}

// 1. If user is acting as the assignee (Task Performer, e.g. Intern, Staff, or Supervisor assigned by HOD)
if ($isAssignee && !$isElevated) {
    $assigneeAllowedTransitions = [
        'ASSIGNED'                 => ['PLANNING'],
        'PLANNING'                 => ['BUDGET_PENDING', 'IN_EXECUTION'],
        'BUDGET_APPROVED'          => ['IN_EXECUTION'],
        'IN_EXECUTION'             => ['IN_EXECUTION', 'COMPLETED_PENDING_REVIEW'],
        'REVISION_REQUIRED'        => ['IN_EXECUTION', 'COMPLETED_PENDING_REVIEW'],
        'COMPLETED_PENDING_REVIEW' => [] // Assignee cannot approve their own task
    ];

    $permitted = $assigneeAllowedTransitions[$currentStage] ?? [];
    if (!in_array($targetStage, $permitted, true) && $targetStage !== $currentStage) {
        jsonResponse(['success' => false, 'message' => "Assignees cannot transition task from {$currentStage} to {$targetStage}."], 403);
    }
} elseif ($isSupervisor || $isElevated) {
    // 2. Supervisor / Reviewer evaluating the task
    if ($currentStage === 'COMPLETED_PENDING_REVIEW' && !in_array($targetStage, ['APPROVED', 'REVISION_REQUIRED'], true) && $targetStage !== $currentStage) {
        jsonResponse(['success' => false, 'message' => 'Reviewers must choose Approved or Revision Required.'], 422);
    }
}

try {
    $pdo->beginTransaction();

    // Determine custom activity datetime (allows user to log delayed progress)
    $loggedAtInput = trim($_POST['logged_at'] ?? '');
    $loggedAt = date('Y-m-d H:i:s');
    if (!empty($loggedAtInput)) {
        try {
            $dt = new DateTime($loggedAtInput);
            $loggedAt = $dt->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            $loggedAt = date('Y-m-d H:i:s');
        }
    }

    $completedAt = $task['completed_at'];
    $approvedAt = $task['approved_at'];

    // Update completion timestamps
    if ($targetStage === 'COMPLETED_PENDING_REVIEW' && empty($completedAt)) {
        $completedAt = $loggedAt;
    } elseif ($targetStage === 'APPROVED') {
        $approvedAt = $loggedAt;
        if (empty($completedAt)) {
            $completedAt = $loggedAt;
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
        VALUES (:task_id, :actor_id, :stage_name, :remarks, :reference_urls, :logged_at)
    ');
    $stmtLog->execute([
        ':task_id'        => $taskId,
        ':actor_id'       => $currentUser['user_id'],
        ':stage_name'     => $targetStage,
        ':remarks'        => $logRemarks,
        ':reference_urls' => !empty($referenceUrls) ? $referenceUrls : null,
        ':logged_at'      => $loggedAt
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
            VALUES (:task_id, :amount, :justification, "pending", :requested_at)
        ');
        $stmtBudget->execute([
            ':task_id'       => $taskId,
            ':amount'        => $budgetAmount,
            ':justification' => $budgetJustification,
            ':requested_at'  => $loggedAt
        ]);
    }

    // Auto-approve pending budget requests if supervisor/admin transitions stage to BUDGET_APPROVED
    // (or directly to IN_EXECUTION from BUDGET_PENDING)
    if ($targetStage === 'BUDGET_APPROVED' || ($currentStage === 'BUDGET_PENDING' && $targetStage === 'IN_EXECUTION')) {
        $stmtApproveBudget = $pdo->prepare('
            UPDATE budget_requests 
            SET status = "approved", 
                reviewed_by = :reviewer, 
                reviewed_at = :reviewed_at 
            WHERE task_id = :task_id AND status = "pending"
        ');
        $stmtApproveBudget->execute([
            ':reviewer'    => $currentUser['user_id'],
            ':reviewed_at' => $loggedAt,
            ':task_id'     => $taskId
        ]);
    }

    // Auto-reject pending budget requests if supervisor/admin moves back to PLANNING from BUDGET_PENDING
    if ($currentStage === 'BUDGET_PENDING' && $targetStage === 'PLANNING') {
        $stmtRejectBudget = $pdo->prepare('
            UPDATE budget_requests 
            SET status = "rejected", 
                reviewed_by = :reviewer, 
                reviewed_at = :reviewed_at 
            WHERE task_id = :task_id AND status = "pending"
        ');
        $stmtRejectBudget->execute([
            ':reviewer'    => $currentUser['user_id'],
            ':reviewed_at' => $loggedAt,
            ':task_id'     => $taskId
        ]);
    }

    // Handle Media Evidence Uploads (multiple files supported)
    if (isset($_FILES['evidence_files'])) {
        $rawFiles = $_FILES['evidence_files'];
        $fileList = [];

        if (is_array($rawFiles['name'])) {
            $count = count($rawFiles['name']);
            for ($i = 0; $i < $count; $i++) {
                if (!empty($rawFiles['name'][$i]) && $rawFiles['error'][$i] === UPLOAD_ERR_OK) {
                    $fileList[] = [
                        'name'     => $rawFiles['name'][$i],
                        'tmp_name' => $rawFiles['tmp_name'][$i],
                        'error'    => $rawFiles['error'][$i],
                        'size'     => $rawFiles['size'][$i]
                    ];
                }
            }
        } elseif (!empty($rawFiles['name']) && $rawFiles['error'] === UPLOAD_ERR_OK) {
            $fileList[] = [
                'name'     => $rawFiles['name'],
                'tmp_name' => $rawFiles['tmp_name'],
                'error'    => $rawFiles['error'],
                'size'     => $rawFiles['size']
            ];
        }

        if (!empty($fileList)) {
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

            $stmtMedia = $pdo->prepare('
                INSERT INTO task_media (log_id, file_path, file_type, uploaded_at)
                VALUES (:log_id, :file_path, :file_type, NOW())
            ');

            foreach ($fileList as $f) {
                $origName = $f['name'];
                $tmpPath = $f['tmp_name'];
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

                if (!in_array($ext, $allowedExts, true)) {
                    continue; // Skip unsupported file types
                }

                $fileType = in_array($ext, $videoExts, true) ? 'video' : 'image';
                $uniqueName = 'evidence_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                $destRel = $targetDirRel . '/' . $uniqueName;
                $destAbs = $targetDirAbs . '/' . $uniqueName;

                if (move_uploaded_file($tmpPath, $destAbs)) {
                    $stmtMedia->execute([
                        ':log_id'    => $logId,
                        ':file_path' => $destRel,
                        ':file_type' => $fileType
                    ]);
                }
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
