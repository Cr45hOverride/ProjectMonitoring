<?php
/**
 * KISMEC Intern Task & Project Monitoring System
 * Task & Project Portfolio Management
 */

declare(strict_types=1);

require_once __DIR__ . '/config/setting.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/report_helper.php';

requireLogin();

$currentUser = getCurrentUser();
$pdo = getDBConnection();

// Fetch filter parameters
$filterStage = trim($_GET['stage'] ?? '');
$filterPriority = trim($_GET['priority'] ?? '');
$filterSla = trim($_GET['sla'] ?? '');
$filterSearch = trim($_GET['q'] ?? '');

$where = [];
$params = [];

if (isSupervisor()) {
  $where[] = 't.supervisor_id = :uid';
  $params[':uid'] = $currentUser['user_id'];
} elseif (isIntern()) {
  $where[] = 't.intern_id = :uid';
  $params[':uid'] = $currentUser['user_id'];
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
        CASE 
            WHEN s.role = 'supervisor' THEN s.full_name
            WHEN sup.user_id IS NOT NULL THEN sup.full_name
            ELSE s.full_name 
        END AS supervisor_name,
        i.full_name AS intern_name
    FROM tasks t
    LEFT JOIN users s ON t.supervisor_id = s.user_id
    INNER JOIN users i ON t.intern_id = i.user_id
    LEFT JOIN users sup ON i.supervisor_id = sup.user_id
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

// Fetch active interns for Task Assignment dropdown (Supervisor / Admin)
$interns = [];
$supervisors = [];
if (!isIntern()) {
  $sqlInterns = 'SELECT user_id, full_name, department, supervisor_id FROM users WHERE role = "intern" AND status = "active"';
  if (isSupervisor()) {
    $sqlInterns .= ' AND (supervisor_id = ' . (int) $currentUser['user_id'] . ' OR supervisor_id IS NULL)';
  }
  $sqlInterns .= ' ORDER BY full_name ASC';
  $interns = $pdo->query($sqlInterns)->fetchAll();

  if (!isSupervisor()) {
    $supervisors = $pdo->query('SELECT user_id, full_name, department FROM users WHERE role = "supervisor" AND status = "active" ORDER BY full_name ASC')->fetchAll();
  }
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
      <p class="page-subtitle">Track stage progression, review milestone evidence, and govern SLA deadlines.</p>
    </div>

    <?php if (!isIntern()): ?>
      <button type="button" class="glass-btn glass-btn-primary" onclick="openModal('modalCreateTask')">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Assign New Task
      </button>
    <?php endif; ?>
  </div>

  <!-- Search & Filter Controls -->
  <div class="glass-panel" style="margin-bottom:1.5rem;padding:1rem 1.25rem;">
    <form method="GET" action="<?= BASE_URL ?>/tasks.php"
      style="display:flex;flex-wrap:wrap;gap:0.75rem;align-items:center;">
      <div style="flex:2;min-width:200px;">
        <input type="text" name="q" value="<?= htmlspecialchars($filterSearch) ?>" class="glass-input"
          placeholder="Search task title, description, or intern...">
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
        <a href="<?= BASE_URL ?>/tasks.php" class="glass-btn glass-btn-sm">Reset</a>
      </div>
    </form>
  </div>

  <!-- Tasks Table -->
  <div class="glass-panel">
    <div class="glass-table-wrapper">
      <table class="glass-table">
        <thead>
          <tr>
            <th>Ref</th>
            <th>Task Title & Scope</th>
            <th>Assign to</th>
            <th>Supervisor</th>
            <th>Current Stage</th>
            <th>Priority</th>
            <th>Start Date</th>
            <th>Due Date</th>
            <th>SLA Status</th>
            <th style="text-align:right;">Actions</th>
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
                <td><?= htmlspecialchars($t['intern_name']) ?></td>
                <td><?= htmlspecialchars($t['supervisor_name']) ?></td>
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
                    'current_stage_badge' => formatStageBadge($t['current_stage'])
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

<!-- Modal: Assign New Task (Supervisor / Admin) -->
<?php if (!isIntern()): ?>
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
          <label class="form-label" for="task_intern_id">Assign to Intern</label>
          <select name="intern_id" id="task_intern_id" class="glass-select" required>
            <option value="">-- Select Intern --</option>
            <?php foreach ($interns as $in): ?>
              <option value="<?= $in['user_id'] ?>" data-supervisor-id="<?= $in['supervisor_id'] ?? '' ?>">
                <?= htmlspecialchars($in['full_name']) ?> (<?= htmlspecialchars($in['department'] ?: 'Intern') ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <?php if (!isSupervisor()): ?>
          <div class="form-group">
            <label class="form-label" for="task_supervisor_id">Direct Supervisor</label>
            <select name="supervisor_id" id="task_supervisor_id" class="glass-select">
              <option value="">-- Auto-detect from Intern Profile --</option>
              <?php foreach ($supervisors as $sp): ?>
                <option value="<?= $sp['user_id'] ?>">
                  <?= htmlspecialchars($sp['full_name']) ?> (<?= htmlspecialchars($sp['department'] ?: 'Supervisor') ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>

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