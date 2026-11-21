<?php
/**
 * KISMEC Intern Task & Project Monitoring System
 * Budget Requests & Resource Governance
 */

declare(strict_types=1);

require_once __DIR__ . '/config/setting.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/report_helper.php';

requireLogin();

$currentUser = getCurrentUser();
$pdo = getDBConnection();

$filterStatus = strtolower(trim($_GET['status'] ?? ''));

$where = [];
$params = [];

if (isSupervisor()) {
  $where[] = 't.supervisor_id = :uid';
  $params[':uid'] = $currentUser['user_id'];
} elseif (isIntern()) {
  $where[] = 't.intern_id = :uid';
  $params[':uid'] = $currentUser['user_id'];
}

if (!empty($filterStatus) && in_array($filterStatus, ['pending', 'approved', 'rejected'], true)) {
  $where[] = 'b.status = :status';
  $params[':status'] = $filterStatus;
}

$whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
    SELECT 
        b.*,
        t.title AS task_title,
        t.current_stage AS task_stage,
        t.supervisor_id,
        t.intern_id,
        i.full_name AS intern_name,
        CASE 
            WHEN s.role = 'supervisor' THEN s.full_name
            WHEN sup.user_id IS NOT NULL THEN sup.full_name
            ELSE s.full_name 
        END AS supervisor_name,
        r.full_name AS reviewer_name
    FROM budget_requests b
    INNER JOIN tasks t ON b.task_id = t.task_id
    INNER JOIN users i ON t.intern_id = i.user_id
    LEFT JOIN users s ON t.supervisor_id = s.user_id
    LEFT JOIN users sup ON i.supervisor_id = sup.user_id
    LEFT JOIN users r ON b.reviewed_by = r.user_id
    $whereSql
    ORDER BY b.requested_at DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$budgets = $stmt->fetchAll();

// KPI sums
$sumRequested = 0;
$sumApproved = 0;
$countPending = 0;

foreach ($budgets as $b) {
  $sumRequested += (float) $b['amount'];
  if ($b['status'] === 'approved') {
    $sumApproved += (float) $b['amount'];
  }
  if ($b['status'] === 'pending') {
    $countPending++;
  }
}

$pageTitle = 'Budget Approvals & Governance';
require_once __DIR__ . '/includes/header.php';
?>

<main class="app-container">
  <div class="page-header">
    <div>
      <h1 class="page-title">
        <svg width="28" height="28" fill="none" stroke="#6366f1" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2"
            d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        Financial & Resource Budget Governance
      </h1>
      <p class="page-subtitle">Hardware acquisition, sensor procurement, and training rig resource requests.</p>
    </div>
  </div>

  <!-- KPI Cards -->
  <div class="stats-grid">
    <div class="glass-panel stat-card">
      <div class="stat-icon stat-icon-indigo">
        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
        </svg>
      </div>
      <div>
        <div class="stat-value"><?= APP_CURRENCY ?> <?= number_format($sumRequested, 2) ?></div>
        <div class="stat-label">Total Amount Requested</div>
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
        <div class="stat-value"><?= APP_CURRENCY ?> <?= number_format($sumApproved, 2) ?></div>
        <div class="stat-label">Approved & Disbursed</div>
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
        <div class="stat-value"><?= $countPending ?></div>
        <div class="stat-label">Pending Supervisor Review</div>
      </div>
    </div>
  </div>

  <!-- Filter Pills -->
  <div style="display:flex;gap:0.5rem;margin-bottom:1.5rem;flex-wrap:wrap;">
    <a href="<?= BASE_URL ?>/budgets.php"
      class="glass-btn glass-btn-sm <?= empty($filterStatus) ? 'glass-btn-primary' : '' ?>">All Requests</a>
    <a href="<?= BASE_URL ?>/budgets.php?status=pending"
      class="glass-btn glass-btn-sm <?= $filterStatus === 'pending' ? 'glass-btn-primary' : '' ?>">Pending</a>
    <a href="<?= BASE_URL ?>/budgets.php?status=approved"
      class="glass-btn glass-btn-sm <?= $filterStatus === 'approved' ? 'glass-btn-primary' : '' ?>">Approved</a>
    <a href="<?= BASE_URL ?>/budgets.php?status=rejected"
      class="glass-btn glass-btn-sm <?= $filterStatus === 'rejected' ? 'glass-btn-primary' : '' ?>">Rejected</a>
  </div>

  <!-- Budgets Table -->
  <div class="glass-panel">
    <div class="glass-table-wrapper">
      <table class="glass-table">
        <thead>
          <tr>
            <th>Ref</th>
            <th>Associated Task</th>
            <th>Assign To</th>
            <th>Amount</th>
            <th>Justification</th>
            <th>Requested Date</th>
            <th>Reviewer</th>
            <th>Status</th>
            <?php if (!isIntern()): ?>
              <th style="text-align:right;">Actions</th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($budgets)): ?>
            <tr>
              <td colspan="<?= !isIntern() ? '9' : '8' ?>"
                style="text-align:center;padding:3rem 1rem;color:var(--text-muted);">
                No budget requests found for this filter.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($budgets as $b): ?>
              <tr>
                <td><span style="font-weight:700;color:var(--text-muted);font-size:0.8rem;">#B-<?= $b['budget_id'] ?></span>
                </td>
                <td>
                  <strong><?= htmlspecialchars($b['task_title']) ?></strong>
                  <div style="margin-top:0.2rem;"><?= formatStageBadge($b['task_stage']) ?></div>
                </td>
                <td><?= htmlspecialchars($b['intern_name']) ?></td>
                <td>
                  <strong style="font-size:1.05rem;color:#38bdf8;"><?= APP_CURRENCY ?>
                    <?= number_format((float) $b['amount'], 2) ?></strong>
                </td>
                <td>
                  <p style="font-size:0.825rem;color:#cbd5e1;max-width:320px;line-height:1.4;">
                    <?= htmlspecialchars($b['justification']) ?>
                  </p>
                </td>
                <td><small><?= formatDisplayDateTime($b['requested_at']) ?></small></td>
                <td>
                  <?php if (!empty($b['reviewer_name'])): ?>
                    <span style="font-weight:600;color:#fff;"><?= htmlspecialchars($b['reviewer_name']) ?></span>
                    <br><small style="color:var(--text-muted);"><?= formatDisplayDateTime($b['reviewed_at']) ?></small>
                  <?php else: ?>
                    <span style="color:var(--text-muted);">Awaiting Review</span>
                  <?php endif; ?>
                </td>
                <td><?= formatBudgetBadge($b['status']) ?></td>
                <?php if (!isIntern()): ?>
                  <td style="text-align:right;white-space:nowrap;">
                    <?php if ($b['status'] === 'pending'): ?>
                      <button type="button" class="glass-btn glass-btn-sm glass-btn-success" style="margin-right:0.25rem;"
                        onclick="reviewBudget(<?= $b['budget_id'] ?>, 'approved')">
                        Approve
                      </button>
                      <button type="button" class="glass-btn glass-btn-sm glass-btn-danger"
                        onclick="reviewBudget(<?= $b['budget_id'] ?>, 'rejected')">
                        Reject
                      </button>
                    <?php else: ?>
                      <span style="font-size:0.75rem;color:var(--text-muted);">Finalized</span>
                    <?php endif; ?>
                  </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>