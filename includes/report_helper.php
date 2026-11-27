<?php
/**
 * KISMEC Intern Task & Project Monitoring System
 * Report, SLA, & UI Badge Helpers
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/setting.php';
require_once __DIR__ . '/../config/db.php';

/**
 * Calculate Real-time SLA Status
 * Compares due_date against completed_at or NOW()
 * Green: On Track (or Completed On-Time)
 * Amber: Due Within 48 Hours
 * Red: Overdue / Completed Late
 */
function calculateSlaStatus(string $dueDate, ?string $completedAt = null): array {
    try {
        $due = new DateTime($dueDate);
    } catch (Exception $e) {
        return [
            'status'     => 'unknown',
            'label'      => 'Unknown',
            'badge_cls'  => 'glass-badge-secondary',
            'text_cls'   => 'text-slate-400',
            'is_overdue' => false,
            'hours_diff' => 0,
            'summary'    => 'Invalid due date'
        ];
    }

    // 1. Task has already been completed
    if (!empty($completedAt)) {
        try {
            $completed = new DateTime($completedAt);
            if ($completed <= $due) {
                $diff = $due->diff($completed);
                return [
                    'status'     => 'completed_on_time',
                    'label'      => 'Completed On-Time',
                    'badge_cls'  => 'glass-badge-success',
                    'text_cls'   => 'text-emerald-400',
                    'is_overdue' => false,
                    'hours_diff' => round(($due->getTimestamp() - $completed->getTimestamp()) / 3600, 1),
                    'summary'    => 'Finished within target deadline'
                ];
            } else {
                $lateHours = round(($completed->getTimestamp() - $due->getTimestamp()) / 3600, 1);
                return [
                    'status'     => 'completed_late',
                    'label'      => 'Completed Late',
                    'badge_cls'  => 'glass-badge-danger',
                    'text_cls'   => 'text-rose-400',
                    'is_overdue' => true,
                    'hours_diff' => $lateHours,
                    'summary'    => "Completed {$lateHours} hrs after deadline"
                ];
            }
        } catch (Exception $e) {
            // fallback
        }
    }

    // 2. Task is still in progress (compare due_date against NOW())
    $now = new DateTime();
    $nowTs = $now->getTimestamp();
    $dueTs = $due->getTimestamp();

    if ($nowTs > $dueTs) {
        $overdueHours = round(($nowTs - $dueTs) / 3600, 1);
        $days = floor($overdueHours / 24);
        $summary = $days > 0 ? "Overdue by {$days}d " . round($overdueHours % 24) . "h" : "Overdue by {$overdueHours}h";
        return [
            'status'     => 'overdue',
            'label'      => 'Overdue',
            'badge_cls'  => 'glass-badge-danger',
            'text_cls'   => 'text-rose-400',
            'is_overdue' => true,
            'hours_diff' => $overdueHours,
            'summary'    => $summary
        ];
    } else {
        $remainingHours = round(($dueTs - $nowTs) / 3600, 1);
        if ($remainingHours <= 48) {
            $summary = $remainingHours < 1 ? "Due in < 1 hr" : "Due in " . round($remainingHours) . " hrs";
            return [
                'status'     => 'due_soon',
                'label'      => 'Due Within 48h',
                'badge_cls'  => 'glass-badge-warning',
                'text_cls'   => 'text-amber-400',
                'is_overdue' => false,
                'hours_diff' => $remainingHours,
                'summary'    => $summary
            ];
        } else {
            $days = floor($remainingHours / 24);
            return [
                'status'     => 'on_track',
                'label'      => 'On Track',
                'badge_cls'  => 'glass-badge-success',
                'text_cls'   => 'text-emerald-400',
                'is_overdue' => false,
                'hours_diff' => $remainingHours,
                'summary'    => "Due in {$days} days"
            ];
        }
    }
}

/**
 * Format Process Stage Badge HTML
 */
function formatStageBadge(string $stage): string {
    $map = [
        'ASSIGNED'                 => ['label' => 'Assigned', 'class' => 'glass-badge-cyan'],
        'PLANNING'                 => ['label' => 'Planning', 'class' => 'glass-badge-indigo'],
        'BUDGET_PENDING'           => ['label' => 'Budget Pending', 'class' => 'glass-badge-warning'],
        'BUDGET_APPROVED'          => ['label' => 'Budget Approved', 'class' => 'glass-badge-teal'],
        'IN_EXECUTION'             => ['label' => 'In Execution', 'class' => 'glass-badge-blue'],
        'REVISION_REQUIRED'        => ['label' => 'Revision Required', 'class' => 'glass-badge-danger'],
        'COMPLETED_PENDING_REVIEW' => ['label' => 'Pending Review', 'class' => 'glass-badge-purple'],
        'APPROVED'                 => ['label' => 'Approved', 'class' => 'glass-badge-success']
    ];

    $info = $map[$stage] ?? ['label' => $stage, 'class' => 'glass-badge-secondary'];
    return sprintf('<span class="glass-badge %s">%s</span>', htmlspecialchars($info['class']), htmlspecialchars($info['label']));
}

/**
 * Format Priority Badge HTML
 */
function formatPriorityBadge(string $priority): string {
    $p = strtolower($priority);
    $map = [
        'high'   => ['label' => 'High', 'class' => 'glass-badge-danger'],
        'medium' => ['label' => 'Medium', 'class' => 'glass-badge-warning'],
        'low'    => ['label' => 'Low', 'class' => 'glass-badge-neutral']
    ];
    $info = $map[$p] ?? ['label' => ucfirst($priority), 'class' => 'glass-badge-neutral'];
    return sprintf('<span class="glass-badge %s">%s</span>', htmlspecialchars($info['class']), htmlspecialchars($info['label']));
}

/**
 * Format Budget Status Badge HTML
 */
function formatBudgetBadge(string $status): string {
    $s = strtolower($status);
    $map = [
        'approved' => ['label' => 'Approved', 'class' => 'glass-badge-success'],
        'pending'  => ['label' => 'Pending Review', 'class' => 'glass-badge-warning'],
        'rejected' => ['label' => 'Rejected', 'class' => 'glass-badge-danger']
    ];
    $info = $map[$s] ?? ['label' => ucfirst($status), 'class' => 'glass-badge-neutral'];
    return sprintf('<span class="glass-badge %s">%s</span>', htmlspecialchars($info['class']), htmlspecialchars($info['label']));
}

/**
 * Format User-Friendly Datetime string
 */
function formatDisplayDateTime(?string $datetime): string {
    if (empty($datetime)) {
        return '—';
    }
    try {
        $dt = new DateTime($datetime);
        return $dt->format('d M Y, h:i A');
    } catch (Exception $e) {
        return $datetime;
    }
}

/**
 * Format User-Friendly Date string
 */
function formatDisplayDate(?string $date): string {
    if (empty($date)) {
        return '—';
    }
    try {
        $dt = new DateTime($date);
        return $dt->format('d M Y');
    } catch (Exception $e) {
        return $date;
    }
}

/**
 * Format Compact Date string (dd/mm/yy)
 */
function formatShortDate(?string $date): string {
    if (empty($date)) {
        return '—';
    }
    try {
        $dt = new DateTime($date);
        return $dt->format('d/m/y');
    } catch (Exception $e) {
        return $date;
    }
}

/**
 * Parse multiline or comma-separated reference URLs into structured links
 */
function parseReferenceUrls(?string $rawUrls): array {
    if (empty($rawUrls)) {
        return [];
    }
    $lines = preg_split('/[\r\n,]+/', trim($rawUrls));
    $links = [];
    foreach ($lines as $line) {
        $u = trim($line);
        if (empty($u)) {
            continue;
        }
        $target = (str_starts_with($u, 'http://') || str_starts_with($u, 'https://')) ? $u : ('https://' . $u);
        $display = preg_replace('#^https?://(www\.)?#i', '', $u);
        if (strlen($display) > 45) {
            $display = substr($display, 0, 42) . '...';
        }
        $links[] = [
            'url'     => $target,
            'raw'     => $u,
            'display' => $display
        ];
    }
    return $links;
}

/**
 * Fetch Full Task Report Data with Process Logs, Media, and Budget
 * Fully compatible with MariaDB 10.4.32 and MySQL 5.7.44
 */
function getTaskFullDetails(PDO $pdo, int $taskId): ?array {
    $stmt = $pdo->prepare('
        SELECT 
            t.*,
            COALESCE(s.full_name, sup.full_name, "Unassigned") AS supervisor_name,
            COALESCE(s.email, sup.email, "") AS supervisor_email,
            COALESCE(s.department, sup.department, "") AS supervisor_dept,
            COALESCE(s.role, sup.role, "supervisor") AS supervisor_role,
            i.full_name AS intern_name,
            i.role AS intern_role,
            i.email AS intern_email,
            i.department AS intern_dept,
            i.intern_start_date,
            i.intern_end_date
        FROM tasks t
        LEFT JOIN users s ON t.supervisor_id = s.user_id
        INNER JOIN users i ON t.intern_id = i.user_id
        LEFT JOIN users sup ON i.supervisor_id = sup.user_id
        WHERE t.task_id = :task_id
        LIMIT 1
    ');
    $stmt->execute([':task_id' => $taskId]);
    $task = $stmt->fetch();

    if (!$task) {
        return null;
    }

    // SLA Calculation
    $task['sla'] = calculateSlaStatus($task['due_date'], $task['completed_at']);

    // Fetch Audit Logs chronologically (oldest to newest for vertical timeline)
    $stmtLogs = $pdo->prepare('
        SELECT 
            l.log_id,
            l.task_id,
            l.actor_id,
            l.stage_name,
            l.remarks,
            l.reference_urls,
            l.logged_at,
            u.full_name AS actor_name,
            u.role AS actor_role
        FROM task_process_logs l
        INNER JOIN users u ON l.actor_id = u.user_id
        WHERE l.task_id = :task_id
        ORDER BY l.logged_at ASC, l.log_id ASC
    ');
    $stmtLogs->execute([':task_id' => $taskId]);
    $logs = $stmtLogs->fetchAll();

    // Fetch media attachments grouped by log_id
    $logIds = array_column($logs, 'log_id');
    $mediaByLog = [];

    if (!empty($logIds)) {
        $inClause = implode(',', array_fill(0, count($logIds), '?'));
        $stmtMedia = $pdo->prepare("
            SELECT media_id, log_id, file_path, file_type, uploaded_at 
            FROM task_media 
            WHERE log_id IN ($inClause)
            ORDER BY media_id ASC
        ");
        $stmtMedia->execute($logIds);
        $mediaRows = $stmtMedia->fetchAll();

        foreach ($mediaRows as $m) {
            $m['full_url'] = resolveMediaUrl($m['file_path']);
            $m['local_path'] = resolveMediaPath($m['file_path']);
            $mediaByLog[$m['log_id']][] = $m;
        }
    }

    // Attach media and parsed reference URLs to corresponding logs
    foreach ($logs as &$log) {
        $log['media'] = $mediaByLog[$log['log_id']] ?? [];
        $log['parsed_references'] = parseReferenceUrls($log['reference_urls'] ?? '');
        $log['raw_remarks'] = $log['remarks'] ?? '';
        $log['raw_reference_urls'] = $log['reference_urls'] ?? '';
    }
    unset($log);

    $task['logs'] = $logs;

    // Fetch Budget Request(s)
    $stmtBudget = $pdo->prepare('
        SELECT 
            b.*,
            u.full_name AS reviewer_name,
            u.role AS reviewer_role
        FROM budget_requests b
        LEFT JOIN users u ON b.reviewed_by = u.user_id
        WHERE b.task_id = :task_id
        ORDER BY b.requested_at DESC
    ');
    $stmtBudget->execute([':task_id' => $taskId]);
    $task['budgets'] = $stmtBudget->fetchAll();

    return $task;
}
