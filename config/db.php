<?php
/**
 * ============================================================================
 * KISMEC Intern Task & Project Monitoring System
 * File: config/db.php
 * ============================================================================
 * Database Connection Configuration
 * 
 * Instructions:
 * Edit the 4 settings below to connect to your database.
 * You can change them manually whenever you deploy or switch servers.
 */

declare(strict_types=1);

require_once __DIR__ . '/setting.php';

// ============================================================================
// DATABASE CONFIGURATION (EDIT MANUALLY HERE)
// ============================================================================
$dbHost = 'localhost';
$dbName = 'intern';                  // Local: 'intern' | Production: 'kismec_project_management'
$dbUser = 'root';                    // Local: 'root'   | Production: 'kismec_project_management'
$dbPass = '';                        // Local: ''       | Production: 'kismec_project_management'
$dbPort = '3306';

/**
 * Creates and returns the PDO database connection
 */
function getDBConnection(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    global $dbHost, $dbName, $dbUser, $dbPass, $dbPort;

    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '+08:00'"
    ];

    try {
        $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
        return $pdo;
    } catch (PDOException $e) {
        // Output clean connection error
        if (defined('IS_API') && IS_API) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Database connection error: ' . $e->getMessage()
            ]);
            exit;
        }

        die('
            <div style="font-family:sans-serif;padding:24px;background:#1e293b;color:#f87171;border-radius:10px;margin:50px auto;max-width:550px;">
                <h3 style="margin-top:0;">Database Connection Error</h3>
                <p style="color:#e2e8f0;">Unable to connect to database <strong>' . htmlspecialchars($dbName) . '</strong> on host <strong>' . htmlspecialchars($dbHost) . '</strong>.</p>
                <div style="padding:10px;background:#0f172a;border-radius:6px;font-size:0.85rem;color:#cbd5e1;word-break:break-all;">
                    ' . htmlspecialchars($e->getMessage()) . '
                </div>
            </div>
        ');
    }
}

// Global instance for all procedural scripts
$pdo = getDBConnection();
