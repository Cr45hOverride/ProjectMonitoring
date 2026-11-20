<?php
/**
 * API: User Management (Create, Edit, Toggle Status)
 * Accessible by: Admin (full), Supervisor (view/intern tenure)
 */

declare(strict_types=1);

define('IS_API', true);

require_once __DIR__ . '/../config/setting.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request method.'], 405);
}

// Accept JSON payload or standard POST
$input = json_decode(file_get_contents('php://input'), true);
if (empty($input)) {
    $input = $_POST;
}

$action = trim($input['action'] ?? 'save');
$pdo = getDBConnection();

if ($action === 'toggle_status') {
    $userId = (int)($input['user_id'] ?? 0);
    $status = strtolower(trim($input['status'] ?? ''));

    if ($userId <= 0 || !in_array($status, ['active', 'inactive'], true)) {
        jsonResponse(['success' => false, 'message' => 'Invalid user ID or status.'], 422);
    }

    // Do not deactivate self
    if ($userId === (int)$_SESSION['user_id']) {
        jsonResponse(['success' => false, 'message' => 'You cannot deactivate your own administrative account.'], 400);
    }

    $stmt = $pdo->prepare('UPDATE users SET status = :status WHERE user_id = :id');
    $stmt->execute([':status' => $status, ':id' => $userId]);

    jsonResponse(['success' => true, 'message' => "User status updated to {$status}."]);
}

// Create or Edit User
$userId = (int)($input['user_id'] ?? 0);
$fullName = trim($input['full_name'] ?? '');
$email = strtolower(trim($input['email'] ?? ''));
$password = trim($input['password'] ?? '');
$role = strtolower(trim($input['role'] ?? 'intern'));
$department = trim($input['department'] ?? '');
$supervisorId = !empty($input['supervisor_id']) ? (int)$input['supervisor_id'] : null;
$startDate = !empty($input['intern_start_date']) ? trim($input['intern_start_date']) : null;
$endDate = !empty($input['intern_end_date']) ? trim($input['intern_end_date']) : null;

if (empty($fullName) || empty($email) || !in_array($role, ['admin', 'supervisor', 'staff', 'intern'], true)) {
    jsonResponse(['success' => false, 'message' => 'Please provide valid full name, email, and role.'], 422);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['success' => false, 'message' => 'Invalid email address format.'], 422);
}

// Check for unique email
$stmtCheck = $pdo->prepare('SELECT user_id FROM users WHERE email = :email AND user_id != :id LIMIT 1');
$stmtCheck->execute([':email' => $email, ':id' => $userId]);
if ($stmtCheck->fetch()) {
    jsonResponse(['success' => false, 'message' => 'A user with this email address already exists.'], 422);
}

try {
    if ($userId > 0) {
        // Edit User
        if (!empty($password)) {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare('
                UPDATE users 
                SET full_name = :full_name, email = :email, password_hash = :hash, role = :role, 
                    department = :department, supervisor_id = :supervisor_id, 
                    intern_start_date = :start_date, intern_end_date = :end_date 
                WHERE user_id = :id
            ');
            $stmt->execute([
                ':full_name'     => $fullName,
                ':email'         => $email,
                ':hash'          => $hash,
                ':role'          => $role,
                ':department'    => $department,
                ':supervisor_id' => $supervisorId,
                ':start_date'    => $startDate,
                ':end_date'      => $endDate,
                ':id'            => $userId
            ]);
        } else {
            $stmt = $pdo->prepare('
                UPDATE users 
                SET full_name = :full_name, email = :email, role = :role, 
                    department = :department, supervisor_id = :supervisor_id, 
                    intern_start_date = :start_date, intern_end_date = :end_date 
                WHERE user_id = :id
            ');
            $stmt->execute([
                ':full_name'     => $fullName,
                ':email'         => $email,
                ':role'          => $role,
                ':department'    => $department,
                ':supervisor_id' => $supervisorId,
                ':start_date'    => $startDate,
                ':end_date'      => $endDate,
                ':id'            => $userId
            ]);
        }

        jsonResponse(['success' => true, 'message' => 'User account updated successfully!']);

    } else {
        // Create User
        if (empty($password) || strlen($password) < 6) {
            jsonResponse(['success' => false, 'message' => 'Password is required and must be at least 6 characters.'], 422);
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare('
            INSERT INTO users (full_name, email, password_hash, role, department, supervisor_id, intern_start_date, intern_end_date, status, created_at)
            VALUES (:full_name, :email, :hash, :role, :department, :supervisor_id, :start_date, :end_date, "active", NOW())
        ');
        $stmt->execute([
            ':full_name'     => $fullName,
            ':email'         => $email,
            ':hash'          => $hash,
            ':role'          => $role,
            ':department'    => $department,
            ':supervisor_id' => $supervisorId,
            ':start_date'    => $startDate,
            ':end_date'      => $endDate
        ]);

        jsonResponse(['success' => true, 'message' => 'New user account created successfully!']);
    }

} catch (Exception $e) {
    error_log('Manage user error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Database error while saving user.'], 500);
}
