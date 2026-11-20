<?php
/**
 * KISMEC Intern Task & Project Monitoring System
 * Login Portal
 */

declare(strict_types=1);

require_once __DIR__ . '/config/setting.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = 'Please enter your email and password.';
    } else {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            if ($user['status'] !== 'active') {
                $error = 'Your account has been deactivated. Please contact your supervisor or administrator.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int)$user['user_id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['department'] = $user['department'];
                $_SESSION['supervisor_id'] = $user['supervisor_id'];

                header('Location: ' . BASE_URL . '/dashboard.php');
                exit;
            }
        } else {
            $error = 'Invalid email or password. Please try again.';
        }
    }
}

$pageTitle = 'Sign In';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, viewport-fit=cover">
  <title>Sign In | <?= APP_NAME ?></title>

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <!-- Glassmorphism Stylesheet -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/glass.css">

  <style>
    .login-container {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1.5rem;
    }
    .login-card {
      max-width: 460px;
      width: 100%;
      background: rgba(15, 23, 42, 0.75);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
      border: 1px solid rgba(255, 255, 255, 0.2);
      border-radius: 20px;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6);
      padding: 2.5rem;
    }
    .demo-pill {
      font-size: 0.75rem;
      padding: 0.35rem 0.65rem;
      border-radius: 8px;
      border: 1px solid rgba(255, 255, 255, 0.15);
      background: rgba(255, 255, 255, 0.05);
      color: #cbd5e1;
      cursor: pointer;
      transition: all 0.2s ease;
    }
    .demo-pill:hover {
      background: rgba(99, 102, 241, 0.3);
      border-color: #6366f1;
      color: #fff;
    }
    @media (max-width: 480px) {
      .login-container {
        padding: 1rem;
      }
      .login-card {
        padding: 1.5rem 1.25rem;
        border-radius: 16px;
      }
      .demo-pill {
        flex: 1;
        text-align: center;
        padding: 0.5rem 0.65rem;
      }
    }
  </style>
</head>
<body>

<div class="login-container">
  <div class="login-card">
    <div style="text-align:center;margin-bottom:2rem;">
      <div style="display:inline-flex;padding:0.75rem;border-radius:14px;background:linear-gradient(135deg, rgba(99,102,241,0.2), rgba(56,189,248,0.2));border:1px solid rgba(99,102,241,0.4);margin-bottom:1rem;">
        <svg width="36" height="36" fill="none" stroke="#6366f1" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
        </svg>
      </div>
      <h1 style="font-size:1.45rem;font-weight:800;letter-spacing:-0.02em;color:#fff;">KISMEC Intern Operations</h1>
      <p style="font-size:0.85rem;color:var(--text-muted);margin-top:0.35rem;">Task & Project Lifecycle Monitoring System</p>
    </div>

    <?php if (!empty($error)): ?>
      <div class="glass-toast error" style="margin-bottom:1.5rem;animation:none;max-width:none;">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="<?= BASE_URL ?>/index.php">
      <div class="form-group">
        <label class="form-label" for="login_email">Official Email Address</label>
        <input type="email" name="email" id="login_email" class="glass-input" placeholder="e.g. name@kismec.org.my" required autocomplete="email">
      </div>

      <div class="form-group" style="margin-bottom:1.5rem;">
        <label class="form-label" for="login_password">Password</label>
        <input type="password" name="password" id="login_password" class="glass-input" placeholder="••••••••••••" required autocomplete="current-password">
      </div>

      <button type="submit" class="glass-btn glass-btn-primary" style="width:100%;padding:0.75rem;font-size:0.95rem;">
        Sign In to Portal
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
      </button>
    </form>

    <!-- Quick Demo Logins -->
    <div style="margin-top:2rem;padding-top:1.5rem;border-top:1px solid rgba(255,255,255,0.1);">
      <span style="font-size:0.75rem;color:var(--text-muted);text-transform:uppercase;font-weight:700;display:block;margin-bottom:0.75rem;letter-spacing:0.05em;">
        Quick Role Autofill (Evaluation)
      </span>
      <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        <button type="button" class="demo-pill" onclick="fillCreds('admin@kismec.org.my', 'Admin@123')">
          👑 Admin
        </button>
        <button type="button" class="demo-pill" onclick="fillCreds('supervisor@kismec.org.my', 'Supervisor@123')">
          👔 Supervisor
        </button>
        <button type="button" class="demo-pill" onclick="fillCreds('intern@kismec.org.my', 'Intern@123')">
          🎓 Intern
        </button>
      </div>
    </div>
  </div>
</div>

<script>
  function fillCreds(email, pass) {
    document.getElementById('login_email').value = email;
    document.getElementById('login_password').value = pass;
  }
</script>

</body>
</html>
