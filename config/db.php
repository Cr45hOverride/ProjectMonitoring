<?php
/**
 * KISMEC Intern Task & Project Monitoring System
 * Centralized Database Connection using PDO
 * Dual-compatible with XAMPP MariaDB 10.4.32 and MySQL 5.7.44-cll-lve
 */

declare(strict_types=1);

require_once __DIR__ . '/setting.php';

// Database Credentials Configuration (Can be overridden via environment variables)
$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbName = getenv('DB_NAME') ?: 'intern';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$dbPort = getenv('DB_PORT') ?: '3306';

// For production cPanel, adjust credentials if defined in server env or constants
if (defined('PROD_DB_NAME') && !empty(PROD_DB_NAME)) {
    $dbName = PROD_DB_NAME;
    $dbUser = PROD_DB_USER;
    $dbPass = PROD_DB_PASS;
    $dbHost = PROD_DB_HOST ?? 'localhost';
}

function getDBConnection(): PDO
{
    static $pdoInstance = null;

    if ($pdoInstance !== null) {
        return $pdoInstance;
    }

    global $dbHost, $dbName, $dbUser, $dbPass, $dbPort;

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $dbHost, $dbPort, $dbName);

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false, // Enforce real prepared statements per spec
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '+08:00'"
    ];

    try {
        $pdoInstance = new PDO($dsn, $dbUser, $dbPass, $options);
        return $pdoInstance;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        if (defined('IS_API') && IS_API) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Database connection failed. Please ensure the database server is running.'
            ]);
            exit;
        } else {
            die('<div style="font-family:sans-serif;padding:30px;background:#1e1b4b;color:#f87171;border-radius:12px;margin:50px auto;max-width:600px;border:1px solid rgba(255,255,255,0.2);">' .
                '<h2 style="margin-top:0;">Database Connection Error</h2>' .
                '<p>Unable to connect to the database. Please check your configuration in <code>config/db.php</code>.</p>' .
                '<p><small>' . htmlspecialchars($e->getMessage()) . '</small></p>' .
                '</div>');
        }
    }
}

// Global variable reference for procedural files
$pdo = getDBConnection();
