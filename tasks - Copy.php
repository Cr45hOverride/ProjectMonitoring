<?php
/**
 * KISMEC Intern Task & Project Monitoring System
 * Task & Project Portfolio Management (Multi-Level Hierarchy)
 */

declare(strict_types=1);

require_once __DIR__ . '/config/setting.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/report_helper.php';

requireLogin();

$currentUser = getCurrentUser();
$pdo = getDBConnection();

$myUid = (int) $currentUser['user_id'];
$myRole = $currentUser['role'];
$myDept = trim($currentUser['department'] ?? '');

// Fetch filter parameters
$filterView = trim($_GET['view'] ?? '');
$filterStage = trim($_GET['stage'] ?? '');
$filterPriority = trim($_GET['priority'] ?? '');
$filterSla = trim($_GET['sla'] ?? '');
$filterSearch = trim($_GET['q'] ?? '');

// Portfolio Counts for View Perspective Tabs
$stmtCountAssigned = $pdo->prepare('SELECT COUNT(*) FROM tasks WHERE intern_id = :uid');
$stmtCountAssigned->execute([':uid' => $myUid]);
$countAssigned = (int) $stmtCountAssigned->fetchColumn();

$stmtCountSupervised = $pdo->prepare('SELECT COUNT(*) FROM tasks WHERE supervisor_id = :uid');
$stmtCountSupervised->execute([':uid' => $myUid]);
$countSupervised = (int) $stmtCountSupervised->fetchColumn();

if ($myRole === 'admin' || $myRole === 'manager') {
  $countTotal = (int) $pdo->query('SELECT COUNT(*) FROM tasks')->fetchColumn();
} elseif ($myRole === 'hod') {
  $stmtCountHod = $pdo->prepare('
        SELECT COUNT(DISTINCT t.task_id) FROM tasks t
        LEFT JOIN users i ON t.intern_id = i.user_id
        LEFT JOIN users s ON t.supervisor_id = s.user_id
        WHERE t.supervisor_id = :uid OR t.intern_id = :uid OR i.department = :dept OR s.department = :dept
    ');
  $stmtCountHod->execute([':uid' => $myUid, ':dept' => $myDept]);
  $countTotal = (int) $stmtCountHod->fetchColumn();
} elseif ($myRole === 'supervisor') {
  $stmtCountSup = $pdo->prepare('SELECT COUNT(*) FROM tasks WHERE supervisor_id = :uid OR intern_id = :uid');
  $stmtCountSup->execute([':uid' => $myUid]);
  $countTotal = (int) $stmtCountSup->fetchColumn();
} else {
  $countTotal = $countAssigned;
}

$where = [];
$params = [];

// Apply hierarchical perspective filtering
if (isIntern() || isStaff()) {
  $where[] = 't.intern_id = :my_uid';
  $params[':my_uid'] = $myUid;
} else {
  if ($filterView === 'assigned') {
    $where[] = 't.intern_id = :my_uid';
    $params[':my_uid'] = $myUid;
  } elseif ($filterView === 'supervised') {
    $where[] = 't.supervisor_id = :my_uid';
    $params[':my_uid'] = $myUid;
  } elseif ($filterView === 'dept' && isHod() && !empty($myDept)) {
    $where[] = '(i.department = :my_dept OR s.department = :my_dept OR t.supervisor_id = :my_uid OR t.intern_id = :my_uid)';
    $params[':my_dept'] = $myDept;
    $params[':my_uid'] = $myUid;
  } else {
    // Default View: show all relevant tasks
    if ($myRole === 'supervisor') {
      $where[] = '(t.supervisor_id = :my_uid OR t.intern_id = :my_uid)';
      $params[':my_uid'] = $myUid;
    } elseif ($myRole === 'hod') {
      if (!empty($myDept)) {
        $where[] = '(i.department = :my_dept OR s.department = :my_dept OR t.supervisor_id = :my_uid OR t.intern_id = :my_uid)';
        $params[':my_dept'] = $myDept;
        $params[':my_uid'] = $myUid;
      } else {
        $where[] = '(t.supervisor_id = :my_uid OR t.intern_id = :my_uid)';
        $params[':my_uid'] = $myUid;
      }
    }
    // Admin and Manager see all tasks by default
  }
}

if (!empty($filterStage)) {
  $where[] = 't.current_stage = :stage';
  $params[':stage'] = $filterStage;
}

if (!empty($filterPriority)) {
  $where[] = 't.priority = :priority';
  $params[':priority'] = $filterPriority;
}

if (!empty($filterSearch)) {
  $where[] = '(t.title LIKE :q OR t.description LIKE :q OR i.full_name LIKE :q)';
  $params[':q'] = '%' . $filterSearch . '%';
}

$whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
    SELECT 
        t.*,
        COALESCE(s.full_name, 'Unassigned') AS supervisor_name,
        s.role AS supervisor_role,
        s.department AS supervisor_dept,
        i.full_name AS intern_name,
        i.role AS intern_role,
        i.department AS intern_dept
    FROM tasks t
    LEFT JOIN users s ON t.supervisor_id = s.user_id
    INNER JOIN users i ON t.intern_id = i.user_id
    $whereSql
    ORDER BY t.created_at DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tasks = $stmt->fetchAll();

// Filter SLA in PHP memory if requested
$filteredTasks = [];
foreach ($tasks as $task) {
  $sla = calculateSlaStatus($task['due_date'], $task['completed_at']);
  $task['sla'] = $sla;

  if (!empty($filterSla)) {
    if ($filterSla === 'overdue' && ($sla['status'] === 'overdue' || $sla['status'] === 'completed_late')) {
      $filteredTasks[] = $task;
    } elseif ($filterSla === 'due_soon' && $sla['status'] === 'due_soon') {
      $filteredTasks[] = $task;
    } elseif ($filterSla === 'on_track' && ($sla['status'] === 'on_track' || $sla['status'] === 'completed_on_time')) {
      $filteredTasks[] = $task;
    }
  } else {
    $filteredTasks[] = $task;
  }
}

// Fetch active assignees and supervisors for Task Assignment modal
$assignees = [];
$supervisors = [];
if (canAssignTasks()) {
  if ($myRole === 'admin') {
    $assignees = $pdo->query('
            SELECT user_id, full_name, role, department, supervisor_id 
            FROM users 
            WHERE status = "active" 
            ORDER BY FIELD(role, "manager", "hod", "supervisor", "staff", "intern"), full_name ASC
        ')->fetchAll();
  } elseif ($myRole === 'manager') {
    $assignees = $pdo->query('
            SELECT user_id, full_name, role, department, supervisor_id 
            FROM users 
            WHERE role IN ("hod", "supervisor", "staff", "intern") AND status = "active" 
            ORDER BY FIELD(role, "hod", "supervisor", "staff", "intern"), full_name ASC
        ')->fetchAll();
  } elseif ($myRole === 'hod') {
    $stmtA = $pdo->prepare('
            SELECT user_id, full_name, role, department, supervisor_id 
            FROM users 
            WHERE role IN ("supervisor", "staff", "intern") AND status = "active"
              AND (department = :dept OR supervisor_id = :uid OR :dept = "")
            ORDER BY FIELD(role, "supervisor", "staff", "intern"), full_name ASC
        ');
    $stmtA->execute([':dept' => $myDept, ':uid' => $myUid]);
    $assignees = $stmtA->fetchAll();
    if (empty($assignees)) {
      $assignees = $pdo->query('SELECT user_id, full_name, role, department, supervisor_id FROM users WHERE role IN ("supervisor", "staff", "intern") AND status = "active" ORDER BY full_name ASC')->fetchAll();
    }
  } elseif ($myRole === 'supervisor') {
    $stmtA = $pdo->prepare('
            SELECT user_id, full_name, role, department, supervisor_id 
            FROM users 
            WHERE role IN ("staff", "intern") AND status = "active"
              AND (supervisor_id = :uid OR department = :dept OR supervisor_id IS NULL)
            ORDER BY FIELD(role, "staff", "intern"), full_name ASC
        ');
    $stmtA->execute([':uid' => $myUid, ':dept' => $myDept]);
    $assignees = $stmtA->fetchAll();
    if (empty($assignees)) {
      $assignees = $pdo->query('SELECT user_id, full_name, role, department, supervisor_id FROM users WHERE role IN ("staff", "intern") AND status = "active" ORDER BY full_name ASC')->fetchAll();
    }
  }

  $supervisors = $pdo->query('
        SELECT user_id, full_name, role, department 
        FROM users 
        WHERE role IN ("admin", "manager", "hod", "supervisor") AND status = "active" 
        ORDER BY FIELD(role, "manager", "hod", "supervisor", "admin"), full_name ASC
    ')->fetchAll();
}

$pageTitle = 'Task & Project Portfolio';
require_once __DIR__ . '/includes/header.php';
?>

<main class="app-container">
  <div class="page-header">
    <div>
      <h1 class="page-title">
        <svg width="28" height="28" fill="none" stroke="#6366f1" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2"
            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
        </svg>
        Tasks & Project Portfolios
      </h1>
      <p class="page-subtitle">Track stage progression, review milestone evidence, and govern multi-level task
        supervision.</p>
    </div>

    <?php if (canAssignTasks()): ?>
      <button type="button" class="glass-btn glass-btn-primary" onclick="openModal('modalCreateTask')">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Assign New Task
      </button>
    <?php endif; ?>
  </div>

  <!-- Dual Perspective Tabs (Assigned to Me vs Supervised by Me) -->
  <?php if (!isIntern() && !isStaff()): ?>
    <div style="display:flex;gap:0.5rem;margin-bottom:1.25rem;flex-wrap:wrap;align-items:center;">
      <a href="<?= BASE_URL ?>/tasks.php<?= !empty($filterStage) ? '?stage=' . urlencode($filterStage) : '' ?>"
        class="glass-btn glass-btn-sm <?= empty($filterView) ? 'glass-btn-primary' : '' ?>">
        <?= ($myRole === 'admin' || $myRole === 'manager') ? 'All Organization Tasks' : (($myRole === 'hod') ? 'Department Overview' : 'All My Relevant Tasks') ?>
        <span class="glass-badge"
          style="margin-left:0.35rem;padding:0.15rem 0.45rem;font-size:0.75rem;"><?= $countTotal ?></span>
      </a>
      <a href="<?= BASE_URL ?>/tasks.php?view=assigned<?= !empty($filterStage) ? '&stage=' . urlencode($filterStage) : '' ?>"
        class="glass-btn glass-btn-sm <?= $filterView === 'assigned' ? 'glass-btn-primary' : '' ?>">
        Assigned to Me (My Tasks)
        <span class="glass-badge glass-badge-indigo"
          style="margin-left:0.35rem;padding:0.15rem 0.45rem;font-size:0.75rem;"><?= $countAssigned ?></span>
      </a>
      <a href="<?= BASE_URL ?>/tasks.php?view=supervised<?= !empty($filterStage) ? '&stage=' . urlencode($filterStage) : '' ?>"
        class="glass-btn glass-btn-sm <?= $filterView === 'supervised' ? 'glass-btn-primary' : '' ?>">
        Supervised by Me
        <span class="glass-badge glass-badge-purple"
          style="margin-left:0.35rem;padding:0.15rem 0.45rem;font-size:0.75rem;"><?= $countSupervised ?></span>
      </a>
      <?php if ($myRole === 'hod' && !empty($myDept)): ?>
        <a href="<?= BASE_URL ?>/tasks.php?view=dept<?= !empty($filterStage) ? '&stage=' . urlencode($filterStage) : '' ?>"
          class="glass-btn glass-btn-sm <?= $filterView === 'dept' ? 'glass-btn-primary' : '' ?>">
          <?= htmlspecialchars($myDept) ?> Centre
        </a>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <!-- Search & Filter Controls -->
  <div class="glass-panel" style="margin-bottom:1.5rem;padding:1rem 1.25rem;">
    <form method="GET" action="<?= BASE_URL ?>/tasks.php"
      style="display:flex;flex-wrap:wrap;gap:0.75rem;align-items:center;">
      <?php if (!empty($filterView)): ?>
        <input type="hidden" name="view" value="<?= htmlspecialchars($filterView) ?>">
      <?php endif; ?>

      <div style="flex:2;min-width:200px;">
        <input type="text" name="q" value="<?= htmlspecialchars($filterSearch) ?>" class="glass-input"
          placeholder="Search task title, description, or assignee...">
      </div>

      <div style="flex:1;min-width:140px;">
        <select name="stage" class="glass-select">
          <option value="">All Stages</option>
          <option value="ASSIGNED" <?= $filterStage === 'ASSIGNED' ? 'selected' : '' ?>>Assigned</option>
          <option value="PLANNING" <?= $filterStage === 'PLANNING' ? 'selected' : '' ?>>Planning</option>
          <option value="BUDGET_PENDING" <?= $filterStage === 'BUDGET_PENDING' ? 'selected' : '' ?>>Budget Pending</option>
          <option value="BUDGET_APPROVED" <?= $filterStage === 'BUDGET_APPROVED' ? 'selected' : '' ?>>Budget Approved
          </option>
          <option value="IN_EXECUTION" <?= $filterStage === 'IN_EXECUTION' ? 'selected' : '' ?>>In Execution</option>
          <option value="REVISION_REQUIRED" <?= $filterStage === 'REVISION_REQUIRED' ? 'selected' : '' ?>>Revision Required
          </option>
          <option value="COMPLETED_PENDING_REVIEW" <?= $filterStage === 'COMPLETED_PENDING_REVIEW' ? 'selected' : '' ?>>
            Pending Review</option>
          <option value="APPROVED" <?= $filterStage === 'APPROVED' ? 'selected' : '' ?>>Approved</option>
        </select>
      </div>

      <div style="flex:1;min-width:120px;">
        <select name="priority" class="glass-select">
          <option value="">All Priorities</option>
          <option value="high" <?= $filterPriority === 'high' ? 'selected' : '' ?>>High</option>
          <option value="medium" <?= $filterPriority === 'medium' ? 'selected' : '' ?>>Medium</option>
          <option value="low" <?= $filterPriority === 'low' ? 'selected' : '' ?>>Low</option>
        </select>
      </div>

      <div style="flex:1;min-width:130px;">
        <select name="sla" class="glass-select">
          <option value="">All SLA Statuses</option>
          <option value="on_track" <?= $filterSla === 'on_track' ? 'selected' : '' ?>>On Track</option>
          <option value="due_soon" <?= $filterSla === 'due_soon' ? 'selected' : '' ?>>Due Soon (48h)</option>
          <option value="overdue" <?= $filterSla === 'overdue' ? 'selected' : '' ?>>Overdue / Late</option>
        </select>
      </div>

      <div style="display:flex;gap:0.5rem;">
        <button type="submit" class="glass-btn glass-btn-primary glass-btn-sm">Filter</button>
        <a href="<?= BASE_URL ?>/tasks.php<?= !empty($filterView) ? '?view=' . urlencode($filterView) : '' ?>"
          class="glass-btn glass-btn-sm">Reset</a>
      </div>
    </form>
  </div>

  <!-- Tasks Table -->
  <div class="glass-panel">
    <div class="glass-table-wrapper">
      <table class="glass-table">
        <thead>

          <tr>
            <th style="width: 5%;">Ref</th>
            <th>Task Title &amp; Scope</th>
            <th style="width: 12%;">Assigned To</th>
            <th style="width: 12%;">Supervising Officer</th>
            <th style="width: 10%;">Current Stage</th>
            <th style="width: 8%;">Priority</th>
            <th style="width: 8%;">Start Date</th>
            <th style="width: 10%;">Due Date</th>
            <th style="width: 8%;">SLA Status</th>
            <th style="width: 12%;">Actions</th>
          </tr>

        </thead>
        <tbody>
          <?php if (empty($filteredTasks)): ?>
            <tr>
              <td colspan="10" style="text-align:center;padding:3rem 1rem;color:var(--text-muted);">
                No matching tasks found.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($filteredTasks as $t): ?>
              <tr id="task-row-<?= $t['task_id'] ?>" data-task-id="<?= $t['task_id'] ?>">
                <td><span style="font-weight:700;color:var(--text-muted);font-size:0.8rem;">#<?= $t['task_id'] ?></span>
                </td>
                <td>
                  <strong style="color:#fff;"><?= htmlspecialchars($t['title']) ?></strong>
                  <?php if (!empty($t['description'])): ?>
                    <p
                      style="font-size:0.75rem;color:var(--text-muted);margin-top:0.2rem;max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                      <?= htmlspecialchars($t['description']) ?>
                    </p>
                  <?php endif; ?>
                </td>
                <td>
                  <strong style="color:#f8fafc;"><?= htmlspecialchars($t['intern_name']) ?></strong>
                  <?php if (!empty($t['intern_role'])): ?>
                    <small style="color:var(--text-muted);display:block;font-size:0.7rem;text-transform:uppercase;">
                      <?= htmlspecialchars($t['intern_role']) ?>
                    </small>
                  <?php endif; ?>
                  <?php if ((int) $t['intern_id'] === $myUid): ?>
                    <span class="glass-badge glass-badge-indigo"
                      style="font-size:0.68rem;padding:0.1rem 0.4rem;display:inline-block;margin-top:0.2rem;">
                      You (Assignee)
                    </span>
                  <?php endif; ?>
                </td>
                <td>
                  <span style="color:#e2e8f0;"><?= htmlspecialchars($t['supervisor_name']) ?></span>
                  <?php if (!empty($t['supervisor_role'])): ?>
                    <small style="color:var(--text-muted);display:block;font-size:0.7rem;text-transform:uppercase;">
                      <?= htmlspecialchars($t['supervisor_role']) ?>
                    </small>
                  <?php endif; ?>
                  <?php if ((int) $t['supervisor_id'] === $myUid): ?>
                    <span class="glass-badge glass-badge-purple"
                      style="font-size:0.68rem;padding:0.1rem 0.4rem;display:inline-block;margin-top:0.2rem;">
                      You (Supervisor)
                    </span>
                  <?php endif; ?>
                </td>
                <td><?= formatStageBadge($t['current_stage']) ?></td>
                <td><?= formatPriorityBadge($t['priority']) ?></td>
                <td><small><?= formatDisplayDate($t['start_date']) ?></small></td>
                <td><small><?= formatDisplayDateTime($t['due_date']) ?></small></td>
                <td>
                  <span class="glass-badge <?= $t['sla']['badge_cls'] ?>">
                    <?= $t['sla']['label'] ?>
                  </span>
                </td>
                <td style="text-align:right;white-space:nowrap;">
                  <button type="button" class="glass-btn glass-btn-sm" style="margin-right:0.25rem;" onclick="openStageModal(<?= htmlspecialchars(json_encode([
                    'task_id' => $t['task_id'],
                    'title' => $t['title'],
                    'current_stage' => $t['current_stage'],
                    'current_stage_badge' => formatStageBadge($t['current_stage']),
                    'intern_id' => (int) $t['intern_id'],
                    'supervisor_id' => (int) $t['supervisor_id']
                  ]), ENT_QUOTES, 'UTF-8') ?>)">
                    Update
                  </button>
                  <a href="<?= BASE_URL ?>/reports.php?task_id=<?= $t['task_id'] ?>" class="glass-btn glass-btn-sm"
                    style="margin-right:0.25rem;" title="View Full Report">
                    Report
                  </a>
                  <?php if (isAdmin()): ?>
                    <button type="button" class="glass-btn glass-btn-sm glass-btn-danger"
                      onclick="deleteTask(<?= $t['task_id'] ?>, '<?= htmlspecialchars(addslashes($t['title'])) ?>')"
                      title="Permanently delete task and associated records">
                      <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                      Delete
                    </button>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>

<!-- Modal: Assign New Task (Supervisor / HOD / Manager / Admin) -->
<?php if (canAssignTasks()): ?>
  <div id="modalCreateTask" class="glass-modal-backdrop">
    <div class="glass-modal-content">
      <div class="modal-header">
        <h3 class="modal-title">Assign New Project / Task</h3>
        <button type="button" class="modal-close" data-close-modal>&times;</button>
      </div>

      <form id="formCreateTask">
        <div class="form-group">
          <label class="form-label" for="task_title">Task Title</label>
          <input type="text" name="title" id="task_title" class="glass-input"
            placeholder="e.g. PLC SCADA HMI Screen Configuration" required>
        </div>

        <div class="form-group">
          <label class="form-label" for="task_intern_id">Assign To (Subordinate / Assignee)</label>
          <select name="intern_id" id="task_intern_id" class="glass-select" required>
            <option value="">-- Select Assignee --</option>
            <?php foreach ($assignees as $as): ?>
              <option value="<?= $as['user_id'] ?>" data-supervisor-id="<?= $as['supervisor_id'] ?? '' ?>">
                <?= htmlspecialchars($as['full_name']) ?> (<?= strtoupper($as['role']) ?> &bull;
                <?= htmlspecialchars($as['department'] ?: 'General') ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label" for="task_supervisor_id">Supervising Officer / Reviewer</label>
          <select name="supervisor_id" id="task_supervisor_id" class="glass-select">
            <option value="">-- Auto-detect / Default to You --</option>
            <?php foreach ($supervisors as $sp): ?>
              <option value="<?= $sp['user_id'] ?>" <?= $sp['user_id'] === $myUid ? 'selected' : '' ?>>
                <?= htmlspecialchars($sp['full_name']) ?> (<?= strtoupper($sp['role']) ?> &bull;
                <?= htmlspecialchars($sp['department'] ?: 'General') ?>)
              </option>
            <?php endforeach; ?>
          </select>
          <small style="color:var(--text-muted);font-size:0.75rem;margin-top:0.35rem;display:block;">
            The designated supervisor who oversees progress, approves budgets, and performs final sign-off.
          </small>
        </div>

        <div class="form-group">
          <label class="form-label" for="task_priority">Priority</label>
          <select name="priority" id="task_priority" class="glass-select" required>
            <option value="low">Low Priority</option>
            <option value="medium" selected>Medium Priority</option>
            <option value="high">High Priority</option>
          </select>
        </div>

        <!-- Mandatory Date Input Standard for start_date & due_date -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
          <div class="form-group">
            <label class="form-label" for="task_start_date">Start Date & Time</label>
            <div class="glass-date-group">
              <input type="text" name="start_date" id="task_start_date" class="glass-input flatpickr-input"
                placeholder="Select start date" data-enable-time="true" data-default-today="true" required>
              <button type="button" class="glass-date-toggle" data-toggle aria-label="Open Calendar">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
              </button>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="task_due_date">Target Due Date & Time</label>
            <div class="glass-date-group">
              <input type="text" name="due_date" id="task_due_date" class="glass-input flatpickr-input"
                placeholder="Select due date" data-enable-time="true" data-default-today="true" required>
              <button type="button" class="glass-date-toggle" data-toggle aria-label="Open Calendar">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
              </button>
            </div>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="task_desc">Technical Scope & Instructions</label>
          <textarea name="description" id="task_desc" class="glass-textarea"
            placeholder="Detail deliverables, hardware platforms, safety standards, and expected milestones..."></textarea>
        </div>

        <div style="display:flex;justify-content:flex-end;gap:0.75rem;margin-top:1.5rem;">
          <button type="button" class="glass-btn" data-close-modal>Cancel</button>
          <button type="submit" class="glass-btn glass-btn-primary">Assign Task</button>
        </div>
      </form>
    </div>
  </div>
<?php endif; ?>

<!-- Slide-over / Modal: Update Task Stage & Upload Evidence -->
<div id="modalUpdateStage" class="glass-modal-backdrop">
  <div class="glass-modal-content">
    <div class="modal-header">
      <div>
        <h3 class="modal-title">Progress Stage & Upload Evidence</h3>
        <p id="task_modal_title" style="font-size:0.85rem;color:var(--text-muted);margin-top:0.2rem;"></p>
      </div>
      <button type="button" class="modal-close" data-close-modal>&times;</button>
    </div>

    <form id="formUpdateStage" enctype="multipart/form-data">
      <input type="hidden" name="task_id" id="update_task_id">
      <input type="hidden" name="current_stage_val" id="current_task_stage_val">

      <div style="margin-bottom:1rem;display:flex;align-items:center;gap:0.75rem;">
        <span style="font-size:0.85rem;color:var(--text-muted);">Current Stage:</span>
        <span id="current_stage_badge"></span>
      </div>

      <div class="form-group">
        <label class="form-label" for="target_stage_select">Target Transition Stage</label>
        <select name="target_stage" id="target_stage_select" class="glass-select" required>
          <!-- Populated dynamically via JS -->
        </select>
      </div>

      <!-- Activity Date & Time Input (Mandatory Date Input Standard with Calendar Icon) -->
      <div class="form-group">
        <label class="form-label" for="stage_logged_at">
          Activity Date & Time
          <span style="font-weight:400;color:var(--text-muted);font-size:0.75rem;">(Adjust if recording delayed
            progress)</span>
        </label>
        <div class="glass-date-group">
          <input type="text" name="logged_at" id="stage_logged_at" class="glass-input flatpickr-input"
            placeholder="Select activity date & time" data-enable-time="true" data-default-today="true" required>
          <button type="button" class="glass-date-toggle" data-toggle aria-label="Open Calendar">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
          </button>
        </div>
      </div>

      <!-- Conditional Budget Fields (Visible when target is BUDGET_PENDING) -->
      <div id="budget_request_fields"
        style="display:none;padding:1rem;background:rgba(99,102,241,0.1);border:1px solid rgba(99,102,241,0.3);border-radius:12px;margin-bottom:1.25rem;">
        <div class="form-group">
          <label class="form-label" for="budget_amount">Required Budget Amount (RM)</label>
          <input type="number" step="0.01" min="1" name="budget_amount" id="budget_amount" class="glass-input"
            placeholder="e.g. 250.00">
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label" for="budget_justification">Budget Justification & Technical Bill of
            Materials</label>
          <textarea name="budget_justification" id="budget_justification" class="glass-textarea"
            placeholder="Detail hardware items, vendor quotes, and component necessity..."></textarea>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="stage_remarks">Detailed Process Remarks / Milestone Documentation</label>
        <textarea name="remarks" id="stage_remarks" class="glass-textarea"
          placeholder="Detail technical progress, tests performed, or revision feedback..." required></textarea>
      </div>

      <!-- Additional Input: Reference Links & URLs for Future Reference -->
      <div class="form-group">
        <label class="form-label" for="stage_reference_urls">
          Reference Links / Technical URLs
          <span style="font-weight:400;color:var(--text-muted);font-size:0.75rem;">(e.g. GitHub repos, Google Drive,
            datasheets — one per line)</span>
        </label>
        <textarea name="reference_urls" id="stage_reference_urls" class="glass-textarea" rows="2"
          placeholder="e.g. https://github.com/kismec/project-repo&#10;https://docs.google.com/document/d/..."></textarea>
      </div>

      <!-- Media Evidence Upload Standard (Multi-Image / Multi-Video Dropzone) -->
      <div class="form-group">
        <label class="form-label" style="display:flex;justify-content:space-between;align-items:center;">
          <span>Attach Media Evidence</span>
          <span style="font-weight:400;color:#818cf8;font-size:0.75rem;">Multiple images &amp; videos supported</span>
        </label>
        <div class="glass-dropzone" id="stageEvidenceDropzone">
          <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:#818cf8;">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
          </svg>
          <div style="font-size:0.875rem;font-weight:600;margin-top:0.5rem;color:#fff;">
            Drag &amp; drop photos/videos or <span style="color:#818cf8;text-decoration:underline;">browse files</span>
          </div>
          <small style="color:var(--text-muted);display:block;margin-top:0.25rem;">
            JPG, PNG, GIF, MP4, MOV, WEBM (Max 50MB per file)
          </small>
          <input type="file" id="stage_evidence_input" multiple accept="image/*,video/*" style="display:none;">
        </div>

        <!-- Staged files preview list -->
        <div id="stagedEvidenceList"
          style="display:flex;flex-wrap:wrap;gap:0.5rem;margin-top:0.75rem;empty-cells:hide;"></div>
      </div>

      <div style="display:flex;justify-content:flex-end;gap:0.75rem;margin-top:1.5rem;">
        <button type="button" class="glass-btn" data-close-modal>Cancel</button>
        <button type="submit" class="glass-btn glass-btn-primary">Submit Stage Update</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<!-- Slide-over / Modal: Update Task Stage & Upload Evidence -->
<div id="modalUpdateStage" class="glass-modal-backdrop">
  <div class="glass-modal-content">
    <div class="modal-header">
      <div>
        <h3 class="modal-title">Progress Stage & Upload Evidence</h3>
        <p id="task_modal_title" style="font-size:0.85rem;color:var(--text-muted);margin-top:0.2rem;"></p>
      </div>
      <button type="button" class="modal-close" data-close-modal>&times;</button>
    </div>

    <form id="formUpdateStage" enctype="multipart/form-data">
      <input type="hidden" name="task_id" id="update_task_id">
      <input type="hidden" name="current_stage_val" id="current_task_stage_val">

      <div style="margin-bottom:1rem;display:flex;align-items:center;gap:0.75rem;">
        <span style="font-size:0.85rem;color:var(--text-muted);">Current Stage:</span>
        <span id="current_stage_badge"></span>
      </div>

      <div class="form-group">
        <label class="form-label" for="target_stage_select">Target Transition Stage</label>
        <select name="target_stage" id="target_stage_select" class="glass-select" required>
          <!-- Populated dynamically via JS -->
        </select>
      </div>

      <!-- Activity Date & Time Input (Mandatory Date Input Standard with Calendar Icon) -->
      <div class="form-group">
        <label class="form-label" for="stage_logged_at">
          Activity Date & Time
          <span style="font-weight:400;color:var(--text-muted);font-size:0.75rem;">(Adjust if recording delayed
            progress)</span>
        </label>
        <div class="glass-date-group">
          <input type="text" name="logged_at" id="stage_logged_at" class="glass-input flatpickr-input"
            placeholder="Select activity date & time" data-enable-time="true" data-default-today="true" required>
          <button type="button" class="glass-date-toggle" data-toggle aria-label="Open Calendar">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
          </button>
        </div>
      </div>

      <!-- Conditional Budget Fields (Visible when target is BUDGET_PENDING) -->
      <div id="budget_request_fields"
        style="display:none;padding:1rem;background:rgba(99,102,241,0.1);border:1px solid rgba(99,102,241,0.3);border-radius:12px;margin-bottom:1.25rem;">
        <div class="form-group">
          <label class="form-label" for="budget_amount">Required Budget Amount (RM)</label>
          <input type="number" step="0.01" min="1" name="budget_amount" id="budget_amount" class="glass-input"
            placeholder="e.g. 250.00">
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label" for="budget_justification">Budget Justification & Technical Bill of
            Materials</label>
          <textarea name="budget_justification" id="budget_justification" class="glass-textarea"
            placeholder="Detail hardware items, vendor quotes, and component necessity..."></textarea>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="stage_remarks">Detailed Process Remarks / Milestone Documentation</label>
        <textarea name="remarks" id="stage_remarks" class="glass-textarea"
          placeholder="Detail technical progress, tests performed, or revision feedback..." required></textarea>
      </div>

      <!-- Additional Input: Reference Links & URLs for Future Reference -->
      <div class="form-group">
        <label class="form-label" for="stage_reference_urls">
          Reference Links / Technical URLs
          <span style="font-weight:400;color:var(--text-muted);font-size:0.75rem;">(e.g. GitHub repos, Google Drive,
            datasheets — one per line)</span>
        </label>
        <textarea name="reference_urls" id="stage_reference_urls" class="glass-textarea" rows="2"
          placeholder="e.g. https://github.com/kismec/project-repo&#10;https://docs.google.com/document/d/..."></textarea>
      </div>

      <!-- Media Evidence Upload Standard (Multi-Image / Multi-Video Dropzone) -->
      <div class="form-group">
        <label class="form-label" style="display:flex;justify-content:space-between;align-items:center;">
          <span>Attach Media Evidence</span>
          <span style="font-weight:400;color:#818cf8;font-size:0.75rem;">Multiple images &amp; videos supported</span>
        </label>

        <div id="evidenceDropzone" class="glass-upload-zone" tabindex="0" role="button"
          aria-label="Upload multiple media evidence files">
          <input type="file" id="evidence_files" name="evidence_files[]" multiple
            accept=".jpg,.jpeg,.png,.webp,.mp4,.mov" style="display:none;">
          <div class="upload-zone-icon">
            <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
          </div>
          <div class="upload-zone-title">Click to choose or Drag &amp; Drop multiple files here</div>
          <div class="upload-zone-sub">Upload multiple images (JPG, PNG, WEBP) or videos (MP4, MOV). You can add files
            repeatedly.</div>
          <span class="upload-badge">+ Add Multiple Images / Videos</span>
        </div>

        <div id="mediaPreviewContainer"></div>
      </div>

      <div style="display:flex;justify-content:flex-end;gap:0.75rem;margin-top:1.5rem;">
        <button type="button" class="glass-btn" data-close-modal>Cancel</button>
        <button type="submit" class="glass-btn glass-btn-primary">Submit Stage Update</button>
      </div>
    </form>
  </div>
</div>

<script>
  // Open create modal if URL action=new
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get('action') === 'new') {
    openModal('modalCreateTask');
  }

  // Auto-select supervisor when intern is chosen in Assign Task modal
  const internSelect = document.getElementById('task_intern_id');
  if (internSelect) {
    internSelect.addEventListener('change', function () {
      const selectedOption = this.options[this.selectedIndex];
      const supId = selectedOption ? selectedOption.getAttribute('data-supervisor-id') : '';
      const supSelect = document.getElementById('task_supervisor_id');
      if (supSelect && supId) {
        supSelect.value = supId;
      }
    });
  }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>