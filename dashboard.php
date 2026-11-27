<?php
/**
 * KISMEC Intern Task & Project Monitoring System
 * Operations Dashboard
 */

declare(strict_types=1);

require_once __DIR__ . '/config/setting.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/report_helper.php';

requireLogin();

$currentUser = getCurrentUser();
$pdo = getDBConnection();

// Build query conditions based on role and multi-level hierarchy
$whereClause = '';
$params = [];

$myUid = (int)$currentUser['user_id'];
$myRole = $currentUser['role'];
$myDept = trim($currentUser['department'] ?? '');

if (isIntern() || isStaff()) {
  $whereClause = 'WHERE t.intern_id = :uid';
  $params[':uid'] = $myUid;
} elseif (isSupervisor()) {
  $whereClause = 'WHERE (t.supervisor_id = :uid_sup OR t.intern_id = :uid_int)';
  $params[':uid_sup'] = $myUid;
  $params[':uid_int'] = $myUid;
} elseif (isHod()) {
  if (!empty($myDept)) {
    $whereClause = 'WHERE (t.supervisor_id = :uid_sup OR t.intern_id = :uid_int OR i.department = :dept_i OR s.department = :dept_s)';
    $params[':uid_sup'] = $myUid;
    $params[':uid_int'] = $myUid;
    $params[':dept_i'] = $myDept;
    $params[':dept_s'] = $myDept;
  } else {
    $whereClause = 'WHERE (t.supervisor_id = :uid_sup OR t.intern_id = :uid_int)';
    $params[':uid_sup'] = $myUid;
    $params[':uid_int'] = $myUid;
  }
}
// Admin and Manager: no whereClause (view entire organization)

// Fetch all relevant tasks for KPI metrics & SLA inspection
$sqlTasks = "
    SELECT 
        t.*,
        COALESCE(s.full_name, 'Unassigned') AS supervisor_name,
        s.role AS supervisor_role,
        i.full_name AS intern_name,
        i.role AS intern_role
    FROM tasks t
    LEFT JOIN users s ON t.supervisor_id = s.user_id
    INNER JOIN users i ON t.intern_id = i.user_id
    $whereClause
    ORDER BY t.due_date ASC
";
$stmtTasks = $pdo->prepare($sqlTasks);
$stmtTasks->execute($params);
$allTasks = $stmtTasks->fetchAll();

// Calculate KPIs & SLA stats
$kpiTotal = count($allTasks);
$kpiInExecution = 0;
$kpiPendingReview = 0;
$kpiApproved = 0;
$kpiOverdue = 0;
$kpiDueSoon = 0;
$kpiOnTrack = 0;

$urgentTasks = [];
$stageCounts = [
  'ASSIGNED' => 0,
  'PLANNING' => 0,
  'BUDGET_PENDING' => 0,
  'BUDGET_APPROVED' => 0,
  'IN_EXECUTION' => 0,
  'REVISION_REQUIRED' => 0,
  'COMPLETED_PENDING_REVIEW' => 0,
  'APPROVED' => 0,
];

foreach ($allTasks as &$t) {
  $stage = $t['current_stage'];
  if (isset($stageCounts[$stage])) {
    $stageCounts[$stage]++;
  }

  if ($stage === 'IN_EXECUTION')
    $kpiInExecution++;
  if ($stage === 'COMPLETED_PENDING_REVIEW')
    $kpiPendingReview++;
  if ($stage === 'APPROVED')
    $kpiApproved++;

  $sla = calculateSlaStatus($t['due_date'], $t['completed_at']);
  $t['sla'] = $sla;

  if ($sla['status'] === 'overdue' || $sla['status'] === 'completed_late') {
    $kpiOverdue++;
    if ($stage !== 'APPROVED') {
      $urgentTasks[] = $t;
    }
  } elseif ($sla['status'] === 'due_soon') {
    $kpiDueSoon++;
    if ($stage !== 'APPROVED') {
      $urgentTasks[] = $t;
    }
  } else {
    $kpiOnTrack++;
  }
}
unset($t);

// Fetch Budget KPIs
$sqlBudget = "
    SELECT 
        SUM(b.amount) AS total_requested,
        SUM(CASE WHEN b.status = 'approved' THEN b.amount ELSE 0 END) AS total_approved,
        COUNT(b.budget_id) AS total_requests,
        SUM(CASE WHEN b.status = 'pending' THEN 1 ELSE 0 END) AS total_pending
    FROM budget_requests b
    INNER JOIN tasks t ON b.task_id = t.task_id
    LEFT JOIN users s ON t.supervisor_id = s.user_id
    INNER JOIN users i ON t.intern_id = i.user_id
    $whereClause
";
$stmtBudget = $pdo->prepare($sqlBudget);
$stmtBudget->execute($params);
$budgetKpis = $stmtBudget->fetch();

$totalRequested = (float) ($budgetKpis['total_requested'] ?? 0);
$totalApproved = (float) ($budgetKpis['total_approved'] ?? 0);

$pageTitle = 'Operations Dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<main class="app-container">
  <!-- Page Header -->
  <div class="page-header">
    <div>
      <h1 class="page-title">
        <svg width="28" height="28" fill="none" stroke="#6366f1" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M13 10V3L4 14h7v7l9-11h-7z" />
        </svg>
        Command & Operations Dashboard
      </h1>
      <p class="page-subtitle">Real-time workflow monitoring, task stage lifecycle, and SLA governance.</p>
    </div>

    <div style="display:flex;gap:0.75rem;">
      <?php if (canAssignTasks()): ?>
        <a href="<?= BASE_URL ?>/tasks.php" class="glass-btn glass-btn-primary">
          <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Assign New Task
        </a>
      <?php endif; ?>
      <a href="<?= BASE_URL ?>/reports.php" class="glass-btn">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        Generate Report
      </a>
    </div>
  </div>

  <!-- KPI Metric Cards Grid -->
  <div class="stats-grid">
    <div class="glass-panel stat-card">
      <div class="stat-icon stat-icon-indigo">
        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
        </svg>
      </div>
      <div>
        <div class="stat-value"><?= $kpiTotal ?></div>
        <div class="stat-label">Total Assigned Tasks</div>
      </div>
    </div>

    <div class="glass-panel stat-card">
      <div class="stat-icon stat-icon-amber">
        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </div>
      <div>
        <div class="stat-value"><?= $kpiDueSoon ?></div>
        <div class="stat-label">Due Within 48 Hours</div>
      </div>
    </div>

    <div class="glass-panel stat-card">
      <div class="stat-icon stat-icon-rose">
        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
      </div>
      <div>
        <div class="stat-value"><?= $kpiOverdue ?></div>
        <div class="stat-label">SLA Overdue / Late</div>
      </div>
    </div>

    <div class="glass-panel stat-card">
      <div class="stat-icon stat-icon-emerald">
        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </div>
      <div>
        <div class="stat-value"><?= $kpiApproved ?></div>
        <div class="stat-label">Completed & Approved</div>
      </div>
    </div>

    <div class="glass-panel stat-card">
      <div class="stat-icon stat-icon-indigo">
        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </div>
      <div>
        <div class="stat-value"><?= APP_CURRENCY ?> <?= number_format($totalApproved, 2) ?></div>
        <div class="stat-label">Approved Budgets</div>
      </div>
    </div>
  </div>

  <!-- SLA Urgent Attention Section (If any) -->
  <?php if (!empty($urgentTasks)): ?>
    <div class="glass-panel" style="margin-bottom:2rem;border-left:4px solid var(--color-warning);">
      <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1rem;">
        <svg width="24" height="24" fill="none" stroke="#f59e0b" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
        <h2 style="font-size:1.15rem;font-weight:700;color:#fff;">SLA Timeline Alerts (Attention Required)</h2>
      </div>

      <div class="glass-table-wrapper">
        <table class="glass-table">
          <thead>
            <tr>
              <th>Task Title</th>
              <th>Assign To</th>
              <th>Stage</th>
              <th>Due Date</th>
              <th>SLA Verdict</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($urgentTasks as $ut): ?>
              <tr>
                <td>
                  <strong><?= htmlspecialchars($ut['title']) ?></strong>
                </td>
                <td><?= htmlspecialchars($ut['intern_name']) ?></td>
                <td><?= formatStageBadge($ut['current_stage']) ?></td>
                <td><?= formatDisplayDateTime($ut['due_date']) ?></td>
                <td>
                  <span class="glass-badge <?= $ut['sla']['badge_cls'] ?>">
                    <?= $ut['sla']['label'] ?> (<?= $ut['sla']['summary'] ?>)
                  </span>
                </td>
                <td>
                  <button type="button" class="glass-btn glass-btn-sm glass-btn-primary" onclick="openStageModal(<?= htmlspecialchars(json_encode([
                    'task_id' => $ut['task_id'],
                    'title' => $ut['title'],
                    'current_stage' => $ut['current_stage'],
                    'current_stage_badge' => formatStageBadge($ut['current_stage'])
                  ]), ENT_QUOTES, 'UTF-8') ?>)">
                    Update Stage
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

  <!-- Stage Lifecycle Distribution Overview -->
  <div class="glass-panel" style="margin-bottom:2rem;">
    <h2
      style="font-size:1.15rem;font-weight:700;color:#fff;margin-bottom:1rem;display:flex;align-items:center;gap:0.5rem;">
      <svg width="20" height="20" fill="none" stroke="#6366f1" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
      </svg>
      Workflow Stage Pipeline
    </h2>

    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(130px, 1fr));gap:0.75rem;">
      <?php foreach ($stageCounts as $stg => $cnt): ?>
        <div class="glass-card-sm" style="text-align:center;padding:0.75rem;">
          <div style="font-size:1.4rem;font-weight:800;color:#fff;"><?= $cnt ?></div>
          <div style="margin-top:0.25rem;"><?= formatStageBadge($stg) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Recent Tasks Table -->
  <div class="glass-panel">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;">
      <h2 style="font-size:1.15rem;font-weight:700;color:#fff;display:flex;align-items:center;gap:0.5rem;">
        <svg width="20" height="20" fill="none" stroke="#38bdf8" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
        </svg>
        Active Task Portfolio
      </h2>
      <a href="<?= BASE_URL ?>/tasks.php" class="glass-btn glass-btn-sm">View All Tasks &rarr;</a>
    </div>

    <div class="glass-table-wrapper">
      <table class="glass-table">
        <thead>
          <tr>
            <th>Ref</th>
            <th>Task Title</th>
            <th>Assign To</th>
            <th>Stage</th>
            <th>Priority</th>
            <th>Target Due</th>
            <th>SLA Status</th>
            <th style="text-align:right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($allTasks)): ?>
            <tr>
              <td colspan="8" style="text-align:center;padding:2rem;color:var(--text-muted);">
                No tasks found in your portfolio.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach (array_slice($allTasks, 0, 10) as $t): ?>
              <tr id="task-row-<?= $t['task_id'] ?>">
                <td><span style="font-size:0.8rem;color:var(--text-muted);font-weight:700;">#<?= $t['task_id'] ?></span>
                </td>
                <td>
                  <strong><?= htmlspecialchars($t['title']) ?></strong>
                  <?php if ((int)$t['intern_id'] === $myUid): ?>
                    <span class="glass-badge glass-badge-indigo" style="font-size:0.65rem;padding:0.1rem 0.35rem;display:inline-block;margin-left:0.25rem;">Assigned to You</span>
                  <?php elseif ((int)$t['supervisor_id'] === $myUid): ?>
                    <span class="glass-badge glass-badge-purple" style="font-size:0.65rem;padding:0.1rem 0.35rem;display:inline-block;margin-left:0.25rem;">Supervising</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?= htmlspecialchars($t['intern_name']) ?>
                  <?php if (!empty($t['intern_role'])): ?>
                    <small style="color:var(--text-muted);display:block;font-size:0.7rem;text-transform:uppercase;">
                      <?= htmlspecialchars($t['intern_role']) ?>
                    </small>
                  <?php endif; ?>
                </td>
                <td><?= formatStageBadge($t['current_stage']) ?></td>
                <td><?= formatPriorityBadge($t['priority']) ?></td>
                <td><?= formatDisplayDateTime($t['due_date']) ?></td>
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
                    'intern_id' => (int)$t['intern_id'],
                    'supervisor_id' => (int)$t['supervisor_id']
                  ]), ENT_QUOTES, 'UTF-8') ?>)">
                    Update
                  </button>
                  <a href="<?= BASE_URL ?>/reports.php?task_id=<?= $t['task_id'] ?>" class="glass-btn glass-btn-sm"
                    title="View Full Report">
                    Report
                  </a>
                  <?php if (isAdmin()): ?>
                    <button type="button" class="glass-btn glass-btn-sm glass-btn-danger" style="margin-left:0.25rem;"
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>