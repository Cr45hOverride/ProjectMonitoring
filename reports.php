<?php
/**
 * KISMEC Intern Task & Project Monitoring System
 * Interactive Task Report Viewer & Audit Dashboard
 */

declare(strict_types=1);

require_once __DIR__ . '/config/setting.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/report_helper.php';

requireLogin();

$currentUser = getCurrentUser();
$pdo = getDBConnection();

$selectedTaskId = (int)($_GET['task_id'] ?? 0);

// Fetch accessible tasks for dropdown selector
$where = '';
$params = [];

$myUid = (int)$currentUser['user_id'];
$myRole = $currentUser['role'];
$myDept = trim($currentUser['department'] ?? '');

if (isIntern() || isStaff()) {
    $where = 'WHERE t.intern_id = :uid';
    $params[':uid'] = $myUid;
} elseif (isSupervisor()) {
    $where = 'WHERE (t.supervisor_id = :uid_sup OR t.intern_id = :uid_int)';
    $params[':uid_sup'] = $myUid;
    $params[':uid_int'] = $myUid;
} elseif (isHod()) {
    if (!empty($myDept)) {
        $where = 'WHERE (t.supervisor_id = :uid_sup OR t.intern_id = :uid_int OR i.department = :dept)';
        $params[':uid_sup'] = $myUid;
        $params[':uid_int'] = $myUid;
        $params[':dept'] = $myDept;
    } else {
        $where = 'WHERE (t.supervisor_id = :uid_sup OR t.intern_id = :uid_int)';
        $params[':uid_sup'] = $myUid;
        $params[':uid_int'] = $myUid;
    }
}

$sqlTasks = "
    SELECT t.task_id, t.title, t.current_stage, i.full_name AS intern_name
    FROM tasks t
    INNER JOIN users i ON t.intern_id = i.user_id
    $where
    ORDER BY t.created_at DESC
";
$stmtTasks = $pdo->prepare($sqlTasks);
$stmtTasks->execute($params);
$taskList = $stmtTasks->fetchAll();

$pageTitle = 'Task Audit Report Viewer';
require_once __DIR__ . '/includes/header.php';
?>

<main class="app-container">
  <div class="page-header">
    <div>
      <h1 class="page-title">
        <svg width="28" height="28" fill="none" stroke="#6366f1" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        Audit Report & SLA Lifecycle Viewer
      </h1>
      <p class="page-subtitle">Interactive full-fidelity preview with media evidence inspection and print-ready A4 PDF export.</p>
    </div>

    <div>
      <a id="btnExportPdf" href="<?= BASE_URL ?>/export_report_pdf.php<?= $selectedTaskId > 0 ? '?task_id=' . $selectedTaskId : '' ?>" target="_blank" class="glass-btn glass-btn-primary" style="display:<?= $selectedTaskId > 0 ? 'inline-flex' : 'none' ?>;">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        Export Official PDF Report
      </a>
    </div>
  </div>

  <!-- Task Selector Dropdown Card -->
  <div class="glass-panel" style="margin-bottom:1.5rem;padding:1.25rem;">
    <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
      <label for="selectReportTask" style="font-weight:700;font-size:0.95rem;color:#fff;display:flex;align-items:center;gap:0.5rem;white-space:nowrap;">
        <svg width="20" height="20" fill="none" stroke="#38bdf8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"/></svg>
        Select Task Portfolio:
      </label>

      <div style="flex:1;min-width:280px;">
        <select id="selectReportTask" class="glass-select" onchange="loadTaskReport(this.value)">
          <option value="">-- Choose an active or completed task to audit --</option>
          <?php foreach ($taskList as $tk): ?>
            <option value="<?= $tk['task_id'] ?>" <?= $selectedTaskId === (int)$tk['task_id'] ? 'selected' : '' ?>>
              #<?= $tk['task_id'] ?> &bull; <?= htmlspecialchars($tk['title']) ?> (<?= htmlspecialchars($tk['intern_name']) ?> &bull; <?= $tk['current_stage'] ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </div>

  <!-- Dynamic Live Glassmorphism Preview Area (AJAX Loaded) -->
  <div id="reportPreviewArea" class="glass-panel" style="min-height:360px;">
    <!-- Rendered via JS loadTaskReport() -->
  </div>
</main>

<!-- Modal: Edit Activity Log (Remarks & Reference URLs) -->
<div id="modalEditLog" class="glass-modal-backdrop">
  <div class="glass-modal-content">
    <div class="modal-header">
      <div>
        <h3 class="modal-title">Edit Activity Log</h3>
        <p id="edit_log_subtitle" style="font-size:0.85rem;color:var(--text-muted);margin-top:0.2rem;"></p>
      </div>
      <button type="button" class="modal-close" data-close-modal>&times;</button>
    </div>

    <form id="formEditLog">
      <input type="hidden" name="log_id" id="edit_log_id">
      <input type="hidden" name="task_id" id="edit_log_task_id">

      <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1.25rem;">
        <span style="font-size:0.85rem;color:var(--text-muted);">Process Stage:</span>
        <span id="edit_log_stage_badge"></span>
      </div>

      <!-- Activity Date & Time Input (Mandatory Date Input Standard with Calendar Icon) -->
      <div class="form-group">
        <label class="form-label" for="edit_log_logged_at">
          Activity Date & Time
          <span style="font-weight:400;color:var(--text-muted);font-size:0.75rem;">(Adjust if correcting activity timestamp)</span>
        </label>
        <div class="glass-date-group">
          <input type="text" name="logged_at" id="edit_log_logged_at" class="glass-input flatpickr-input" placeholder="Select date & time" data-enable-time="true" required>
          <button type="button" class="glass-date-toggle" data-toggle aria-label="Open Calendar">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
          </button>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="edit_log_remarks">Process Remarks / Detailed Documentation</label>
        <textarea name="remarks" id="edit_log_remarks" class="glass-textarea" rows="4" placeholder="Update technical remarks, fix typos, or clarify milestone deliverables..." required></textarea>
      </div>

      <div class="form-group">
        <label class="form-label" for="edit_log_reference_urls">
          Reference Links & Technical URLs
          <span style="font-weight:400;color:var(--text-muted);font-size:0.75rem;">(e.g. GitHub repos, Google Drive, datasheets — one per line)</span>
        </label>
        <textarea name="reference_urls" id="edit_log_reference_urls" class="glass-textarea" rows="3" placeholder="https://github.com/...&#10;https://docs.google.com/..."></textarea>
      </div>

      <div style="display:flex;justify-content:space-between;align-items:center;margin-top:1.5rem;flex-wrap:wrap;gap:0.75rem;">
        <div>
          <?php if (isAdmin()): ?>
            <button type="button" id="btnModalDeleteLog" class="glass-btn glass-btn-sm" style="background:rgba(239,68,68,0.2);color:#fca5a5;border:1px solid rgba(239,68,68,0.4);" title="Permanently delete this audit entry">
              <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
              Delete Entry
            </button>
          <?php endif; ?>
        </div>
        <div style="display:flex;gap:0.75rem;">
          <button type="button" class="glass-btn" data-close-modal>Cancel</button>
          <button type="submit" class="glass-btn glass-btn-primary">Save Changes</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    const selector = document.getElementById('selectReportTask');
    if (selector && selector.value) {
      loadTaskReport(selector.value);
    } else {
      loadTaskReport('');
    }
  });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
