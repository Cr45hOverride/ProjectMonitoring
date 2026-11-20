<?php
/**
 * KISMEC Intern Task & Project Monitoring System
 * Session Authentication & Role Authorization Helper
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/setting.php';
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) {
        // Configure secure session parameters
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_start();
    } else {
        @session_start();
    }
}

/**
 * Check if a user is logged in
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

/**
 * Get current authenticated user details
 */
function getCurrentUser(): ?array {
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'user_id'     => (int)$_SESSION['user_id'],
        'full_name'   => $_SESSION['full_name'] ?? 'User',
        'email'       => $_SESSION['email'] ?? '',
        'role'        => $_SESSION['user_role'] ?? 'intern',
        'department'  => $_SESSION['department'] ?? '',
        'supervisor_id' => $_SESSION['supervisor_id'] ?? null
    ];
}

/**
 * Require login or redirect / return 401
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        if (defined('IS_API') && IS_API) {
            jsonResponse(['success' => false, 'message' => 'Unauthorized: Please log in.'], 401);
        }
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

/**
 * Require specific role(s)
 * @param string|array $allowedRoles
 */
function requireRole($allowedRoles): void {
    requireLogin();
    $roles = is_array($allowedRoles) ? $allowedRoles : [$allowedRoles];
    $currentRole = $_SESSION['user_role'] ?? '';

    if (!in_array($currentRole, $roles, true)) {
        if (defined('IS_API') && IS_API) {
            jsonResponse(['success' => false, 'message' => 'Forbidden: Insufficient privileges.'], 403);
        }
        header('Location: ' . BASE_URL . '/dashboard.php?error=forbidden');
        exit;
    }
}

/**
 * Check role helpers
 */
function isAdmin(): bool {
    return isLoggedIn() && (($_SESSION['user_role'] ?? '') === 'admin');
}

function isSupervisor(): bool {
    return isLoggedIn() && (($_SESSION['user_role'] ?? '') === 'supervisor');
}

function isStaff(): bool {
    return isLoggedIn() && (($_SESSION['user_role'] ?? '') === 'staff');
}

function isIntern(): bool {
    return isLoggedIn() && (($_SESSION['user_role'] ?? '') === 'intern');
}

/**
 * Check if the current user has access to a specific task
 */
function hasTaskAccess(int $taskId, ?array $user = null): bool {
    if ($user === null) {
        $user = getCurrentUser();
    }
    if (!$user) {
        return false;
    }
    if ($user['role'] === 'admin' || $user['role'] === 'staff') {
        return true;
    }

    $pdo = getDBConnection();
    $stmt = $pdo->prepare('SELECT supervisor_id, intern_id FROM tasks WHERE task_id = :task_id LIMIT 1');
    $stmt->execute([':task_id' => $taskId]);
    $task = $stmt->fetch();

    if (!$task) {
        return false;
    }

    if ($user['role'] === 'supervisor') {
        return (int)$task['supervisor_id'] === $user['user_id'];
    }

    if ($user['role'] === 'intern') {
        return (int)$task['intern_id'] === $user['user_id'];
    }

    return false;
}

/**
 * Generate CSRF Token
 */
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF Token
 */
function verifyCsrfToken(?string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitize string input
 */
function sanitize(?string $input): string {
    if ($input === null) {
        return '';
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Standard JSON Response emitter
 */
function jsonResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
