<?php
/**
 * ============================================================================
 * KISMEC Intern Task & Project Monitoring System
 * File: config/db.php
 * ============================================================================
 *
 * PURPOSE OF THIS FILE:
 * This file manages the database connection for the entire application.
 * It uses PHP's modern PDO (PHP Data Objects) extension to securely connect
 * to MySQL / MariaDB using prepared statements, which protects against SQL injection.
 *
 * DUAL-ENVIRONMENT COMPATIBILITY:
 * This file is 100% environment-aware:
 *
 * 1. LOCAL DEVELOPMENT (XAMPP on your computer):
 *    - Host:     127.0.0.1 / localhost
 *    - Database: intern
 *    - User:     root
 *    - Password: (empty)
 *
 * 2. LIVE PRODUCTION SERVER (cPanel on https://kismec.org.my/jupiter/project_management):
 *    - Host:     localhost
 *    - Database: kismec_project_management
 *    - User:     kismec_project_management
 *    - Password: kismec_project_management
 *
 * When you upload this project to your cPanel hosting, you DO NOT need to edit
 * this file! It automatically switches to the production credentials.
 * ============================================================================
 */

declare(strict_types=1);

// Step 1: Load General Application Settings
// ----------------------------------------------------------------------------
// We require setting.php first so we have access to $isLocal, BASE_URL, and PROD_DB_* constants.
require_once __DIR__ . '/setting.php';

// Step 2: Determine Database Credentials Based on Current Environment
// ----------------------------------------------------------------------------
// Check if running locally (XAMPP localhost) or on the live production server (cPanel).
if (!empty($isLocal)) {
    // --- LOCAL XAMPP ENVIRONMENT ---
    // Connect to your local MariaDB/MySQL database
    $dbHost = getenv('DB_HOST') ?: '127.0.0.1';
    $dbName = getenv('DB_NAME') ?: 'intern';
    $dbUser = getenv('DB_USER') ?: 'root';
    $dbPass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
    $dbPort = getenv('DB_PORT') ?: '3306';
} else {
    // --- LIVE PRODUCTION SERVER (cPanel) ---
    // Automatically uses your cPanel database credentials
    $dbHost = defined('PROD_DB_HOST') ? PROD_DB_HOST : 'localhost';
    $dbName = defined('PROD_DB_NAME') ? PROD_DB_NAME : 'kismec_project_management';
    $dbUser = defined('PROD_DB_USER') ? PROD_DB_USER : 'kismec_project_management';
    $dbPass = defined('PROD_DB_PASS') ? PROD_DB_PASS : 'kismec_project_management';
    $dbPort = '3306';
}

/**
 * getDBConnection(): PDO
 *
 * Creates or retrieves the singleton PDO database connection instance.
 * Using a singleton pattern ensures we only open ONE database connection
 * per page request, saving memory and server CPU resources.
 *
 * @return PDO Active database connection object
 */
function getDBConnection(): PDO
{
    // The static variable persists across multiple calls inside the same page load.
    static $pdoInstance = null;

    if ($pdoInstance !== null) {
        return $pdoInstance;
    }

    global $dbHost, $dbName, $dbUser, $dbPass, $dbPort, $isLocal;

    // DSN (Data Source Name): Tells PDO the database type, host, port, db name, and charset
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $dbHost, $dbPort, $dbName);

    // PDO Options for Security, Performance, and Error Handling:
    $options = [
        // 1. ATTR_ERRMODE => ERRMODE_EXCEPTION:
        // Automatically throws a PDOException whenever a SQL query fails,
        // allowing try-catch blocks to catch and safely log errors.
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

        // 2. ATTR_DEFAULT_FETCH_MODE => FETCH_ASSOC:
        // Returns query results as clean associative arrays ($row['task_id'], $row['title'])
        // rather than repeating duplicate numeric indices ($row[0], $row[1]).
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

        // 3. ATTR_EMULATE_PREPARES => false:
        // CRITICAL FOR SECURITY: Forces MySQL to use real native prepared statements
        // on the database engine. This provides 100% mathematical immunity against SQL injection!
        PDO::ATTR_EMULATE_PREPARES => false,

        // 4. MYSQL_ATTR_INIT_COMMAND:
        // Enforces full 4-byte UTF-8 encoding (supports emojis, special characters) and
        // sets MySQL's internal session timezone to Malaysian Standard Time (+08:00).
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '+08:00'"
    ];

    try {
        // Attempt Primary Connection
        $pdoInstance = new PDO($dsn, $dbUser, $dbPass, $options);
        return $pdoInstance;

    } catch (PDOException $e) {
        // Fallback for Production: In case cPanel database or password has a literal space
        if (empty($isLocal)) {
            $altName = defined('PROD_DB_NAME_ALT') ? PROD_DB_NAME_ALT : 'kismec_ project_management';
            $altUser = defined('PROD_DB_USER_ALT') ? PROD_DB_USER_ALT : 'kismec_ project_management';
            $altPass = defined('PROD_DB_PASS_ALT') ? PROD_DB_PASS_ALT : 'kismec_ project_management';

            $dsnAlt = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $dbHost, $dbPort, $altName);

            try {
                $pdoInstance = new PDO($dsnAlt, $altUser, $altPass, $options);
                return $pdoInstance;
            } catch (PDOException $eAlt) {
                // Keep original exception for error logging
            }
        }

        // Log the exact error to server logs for debugging
        error_log("Database connection failed: " . $e->getMessage());

        // Clean user-friendly error response
        if (defined('IS_API') && IS_API) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Database connection failed. Please ensure the database server is running and credentials in config/db.php are correct.'
            ]);
            exit;
        } else {
            // Friendly error card for web browser visitors
            die('
                <div style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;padding:30px;background:#0f172a;color:#f87171;border-radius:14px;margin:60px auto;max-width:620px;border:1px solid rgba(239,68,68,0.3);box-shadow:0 25px 60px rgba(0,0,0,0.6);">
                    <h2 style="margin-top:0;color:#ef4444;display:flex;align-items:center;gap:10px;">
                        <span>&#9888;</span> Database Connection Error
                    </h2>
                    <p style="color:#e2e8f0;line-height:1.6;">
                        Unable to connect to the database <code>' . htmlspecialchars($dbName) . '</code> on host <code>' . htmlspecialchars($dbHost) . '</code>.
                    </p>
                    <p style="color:#94a3b8;font-size:0.85rem;line-height:1.5;">
                        <strong>For Live cPanel Hosting:</strong> Verify that you have created the MySQL database, created the user, and attached the user to the database with <strong>ALL PRIVILEGES</strong> inside your cPanel "MySQL Databases" panel.
                    </p>
                    <div style="margin-top:15px;padding:12px;background:rgba(0,0,0,0.3);border-radius:8px;font-size:0.8rem;color:#cbd5e1;word-break:break-all;">
                        <strong>System Diagnostic Error:</strong> ' . htmlspecialchars($e->getMessage()) . '
                    </div>
                </div>
            ');
        }
    }
}

// Global variable reference for procedural PHP files
$pdo = getDBConnection();
