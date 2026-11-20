<?php
/**
 * KISMEC Intern Task & Project Monitoring System
 * User & Role Management (Admin / Supervisor)
 */

declare(strict_types=1);

require_once __DIR__ . '/config/setting.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/report_helper.php';

requireRole('admin');

$currentUser = getCurrentUser();
$pdo = getDBConnection();

$filterRole = trim($_GET['role'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');

$where = [];
$params = [];

if (!empty($filterRole)) {
    $where[] = 'u.role = :role';
    $params[':role'] = $filterRole;
}

if (!empty($filterStatus)) {
    $where[] = 'u.status = :status';
    $params[':status'] = $filterStatus;
}

$whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
    SELECT 
        u.*,
        s.full_name AS supervisor_name
    FROM users u
    LEFT JOIN users s ON u.supervisor_id = s.user_id
    $whereSql
    ORDER BY u.role ASC, u.full_name ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Fetch supervisors list for assignment dropdown
$supervisors = $pdo->query('SELECT user_id, full_name, department FROM users WHERE role = "supervisor" AND status = "active" ORDER BY full_name ASC')->fetchAll();

$pageTitle = 'User & Intern Management';
require_once __DIR__ . '/includes/header.php';
?>

<main class="app-container">
  <div class="page-header">
    <div>
      <h1 class="page-title">
        <svg width="28" height="28" fill="none" stroke="#6366f1" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
        User & Intern Directory
      </h1>
      <p class="page-subtitle">Manage system users, assign supervisors to interns, and govern internship tenure dates.</p>
    </div>

    <button type="button" class="glass-btn glass-btn-primary" onclick="openUserModal()">
      <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
      Add New User
    </button>
  </div>

  <!-- Role Filter Pills -->
  <div style="display:flex;gap:0.5rem;margin-bottom:1.5rem;flex-wrap:wrap;">
    <a href="<?= BASE_URL ?>/users.php" class="glass-btn glass-btn-sm <?= empty($filterRole) ? 'glass-btn-primary' : '' ?>">All Users</a>
    <a href="<?= BASE_URL ?>/users.php?role=supervisor" class="glass-btn glass-btn-sm <?= $filterRole === 'supervisor' ? 'glass-btn-primary' : '' ?>">Supervisors</a>
    <a href="<?= BASE_URL ?>/users.php?role=staff" class="glass-btn glass-btn-sm <?= $filterRole === 'staff' ? 'glass-btn-primary' : '' ?>">Staff</a>
    <a href="<?= BASE_URL ?>/users.php?role=intern" class="glass-btn glass-btn-sm <?= $filterRole === 'intern' ? 'glass-btn-primary' : '' ?>">Interns</a>
    <a href="<?= BASE_URL ?>/users.php?role=admin" class="glass-btn glass-btn-sm <?= $filterRole === 'admin' ? 'glass-btn-primary' : '' ?>">Administrators</a>
  </div>

  <!-- Users Table -->
  <div class="glass-panel">
    <div class="glass-table-wrapper">
      <table class="glass-table">
        <thead>
          <tr>
            <th>User</th>
            <th>Role</th>
            <th>Department</th>
            <th>Assigned Supervisor</th>
            <th>Internship Tenure</th>
            <th>Status</th>
            <th>Created At</th>
            <th style="text-align:right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($users)): ?>
            <tr>
              <td colspan="8" style="text-align:center;padding:3rem;color:var(--text-muted);">
                No user accounts found.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($users as $u): ?>
              <tr>
                <td>
                  <div style="display:flex;align-items:center;gap:0.75rem;">
                    <div class="user-avatar" style="width:34px;height:34px;font-size:0.85rem;">
                      <?= strtoupper(substr($u['full_name'], 0, 1)) ?>
                    </div>
                    <div>
                      <strong style="color:#fff;"><?= htmlspecialchars($u['full_name']) ?></strong>
                      <div style="font-size:0.75rem;color:var(--text-muted);"><?= htmlspecialchars($u['email']) ?></div>
                    </div>
                  </div>
                </td>
                <td>
                  <span class="glass-badge <?= $u['role'] === 'admin' ? 'glass-badge-purple' : ($u['role'] === 'supervisor' ? 'glass-badge-indigo' : ($u['role'] === 'staff' ? 'glass-badge-blue' : 'glass-badge-teal')) ?>">
                    <?= ucfirst($u['role']) ?>
                  </span>
                </td>
                <td><?= htmlspecialchars($u['department'] ?: '—') ?></td>
                <td>
                  <?= !empty($u['supervisor_name']) ? htmlspecialchars($u['supervisor_name']) : '<span style="color:var(--text-muted);">—</span>' ?>
                </td>
                <td>
                  <?php if ($u['role'] === 'intern' && (!empty($u['intern_start_date']) || !empty($u['intern_end_date']))): ?>
                    <small style="color:#e2e8f0;">
                      <?= formatDisplayDate($u['intern_start_date']) ?> &rarr; <?= formatDisplayDate($u['intern_end_date']) ?>
                    </small>
                  <?php else: ?>
                    <span style="color:var(--text-muted);">—</span>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="glass-badge <?= $u['status'] === 'active' ? 'glass-badge-success' : 'glass-badge-danger' ?>">
                    <?= ucfirst($u['status']) ?>
                  </span>
                </td>
                <td><small><?= formatDisplayDate($u['created_at']) ?></small></td>
                <td style="text-align:right;white-space:nowrap;">
                  <button type="button" class="glass-btn glass-btn-sm" style="margin-right:0.25rem;" onclick="openUserModal(<?= htmlspecialchars(json_encode($u), ENT_QUOTES, 'UTF-8') ?>)">
                    Edit
                  </button>
                  <?php if ($u['user_id'] !== $currentUser['user_id']): ?>
                    <button type="button" class="glass-btn glass-btn-sm <?= $u['status'] === 'active' ? 'glass-btn-danger' : 'glass-btn-success' ?>" onclick="toggleUserStatus(<?= $u['user_id'] ?>, '<?= $u['status'] ?>')">
                      <?= $u['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
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

<!-- Modal: Add / Edit User -->
<div id="modalUser" class="glass-modal-backdrop">
  <div class="glass-modal-content">
    <div class="modal-header">
      <h3 class="modal-title" id="user_modal_title">Add User Account</h3>
      <button type="button" class="modal-close" data-close-modal>&times;</button>
    </div>

    <form id="formUser">
      <input type="hidden" name="user_id" id="user_id">

      <div class="form-group">
        <label class="form-label" for="user_full_name">Full Name</label>
        <input type="text" name="full_name" id="user_full_name" class="glass-input" placeholder="e.g. Nurul Aisyah Binti Razak" required>
      </div>

      <div class="form-group">
        <label class="form-label" for="user_email">Official Email Address</label>
        <input type="email" name="email" id="user_email" class="glass-input" placeholder="name@kismec.org.my" required>
      </div>

      <div class="form-group">
        <label class="form-label" for="user_password">
          Password <span id="password_hint" style="color:var(--text-muted);font-weight:400;font-size:0.75rem;">(Minimum 6 characters)</span>
        </label>
        <input type="password" name="password" id="user_password" class="glass-input" placeholder="••••••••••••">
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
        <div class="form-group">
          <label class="form-label" for="user_role">System Role</label>
          <select name="role" id="user_role" class="glass-select" required>
            <option value="intern">Intern</option>
            <option value="staff">Staff</option>
            <option value="supervisor">Supervisor</option>
            <option value="admin">Administrator</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label" for="user_department">Department / Centre</label>
          <input type="text" name="department" id="user_department" class="glass-input" placeholder="e.g. Smart Automation & IoT">
        </div>
      </div>

      <!-- Intern Specific Fields -->
      <div id="intern_specific_fields" style="display:none;padding:1rem;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.1);border-radius:12px;margin-bottom:1.25rem;">
        <div class="form-group">
          <label class="form-label" for="user_supervisor_id">Assign Direct Supervisor</label>
          <select name="supervisor_id" id="user_supervisor_id" class="glass-select">
            <option value="">-- No Direct Supervisor --</option>
            <?php foreach ($supervisors as $sp): ?>
              <option value="<?= $sp['user_id'] ?>">
                <?= htmlspecialchars($sp['full_name']) ?> (<?= htmlspecialchars($sp['department'] ?: 'Supervisor') ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Mandatory Date Input Standard for intern_start_date & intern_end_date -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
          <div class="form-group" style="margin-bottom:0;">
            <label class="form-label" for="intern_start_date">Internship Start Date</label>
            <div class="glass-date-group">
              <input type="text" name="intern_start_date" id="intern_start_date" class="glass-input flatpickr-input" placeholder="Select start date" data-default-today="true">
              <button type="button" class="glass-date-toggle" data-toggle aria-label="Open Calendar">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
              </button>
            </div>
          </div>

          <div class="form-group" style="margin-bottom:0;">
            <label class="form-label" for="intern_end_date">Internship End Date</label>
            <div class="glass-date-group">
              <input type="text" name="intern_end_date" id="intern_end_date" class="glass-input flatpickr-input" placeholder="Select end date">
              <button type="button" class="glass-date-toggle" data-toggle aria-label="Open Calendar">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
              </button>
            </div>
          </div>
        </div>
      </div>

      <div style="display:flex;justify-content:flex-end;gap:0.75rem;margin-top:1.5rem;">
        <button type="button" class="glass-btn" data-close-modal>Cancel</button>
        <button type="submit" class="glass-btn glass-btn-primary">Save User</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
