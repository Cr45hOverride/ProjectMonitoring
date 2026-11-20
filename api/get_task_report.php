<?php
/**
 * API: Get Task Full Report (Live AJAX Preview for reports.php)
 * Returns detailed task data, SLA verdict, timeline, and media
 */

declare(strict_types=1);

define('IS_API', true);

require_once __DIR__ . '/../config/setting.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/report_helper.php';

requireLogin();

$taskId = (int)($_GET['task_id'] ?? 0);
if ($taskId <= 0) {
    jsonResponse(['success' => false, 'message' => 'Task ID is required.'], 422);
}

$currentUser = getCurrentUser();
if (!hasTaskAccess($taskId, $currentUser)) {
    jsonResponse(['success' => false, 'message' => 'You do not have permission to view this task report.'], 403);
}

$pdo = getDBConnection();
$task = getTaskFullDetails($pdo, $taskId);

if (!$task) {
    jsonResponse(['success' => false, 'message' => 'Task not found.'], 404);
}

// Format extra fields for frontend presentation
$task['stage_badge'] = formatStageBadge($task['current_stage']);
$task['priority_badge'] = formatPriorityBadge($task['priority']);
$task['formatted_start_date'] = formatDisplayDateTime($task['start_date']);
$task['formatted_due_date'] = formatDisplayDateTime($task['due_date']);
$task['formatted_completed_at'] = formatDisplayDateTime($task['completed_at']);
$task['formatted_approved_at'] = formatDisplayDateTime($task['approved_at']);

if (!empty($task['logs'])) {
    foreach ($task['logs'] as &$log) {
        $log['stage_badge'] = formatStageBadge($log['stage_name']);
        $log['formatted_logged_at'] = formatDisplayDateTime($log['logged_at']);
    }
    unset($log);
}

if (!empty($task['budgets'])) {
    foreach ($task['budgets'] as &$b) {
        $b['status_badge'] = formatBudgetBadge($b['status']);
        $b['formatted_requested_at'] = formatDisplayDateTime($b['requested_at']);
        $b['formatted_reviewed_at'] = formatDisplayDateTime($b['reviewed_at']);
    }
    unset($b);
}

jsonResponse([
    'success' => true,
    'task'    => $task
]);
