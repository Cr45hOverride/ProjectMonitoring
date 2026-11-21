<?php
/**
 * ============================================================================
 * KISMEC Intern Task & Project Monitoring System
 * File: config/setting.php
 * ============================================================================
 *
 * PURPOSE OF THIS FILE:
 * This file serves as the "Central Control Panel" for system configurations.
 * Every page and API endpoint in this project loads this file first.
 *
 * It takes care of:
 * 1. Enforcing strict PHP type safety.
 * 2. Setting the official Malaysian timezone (Asia/Kuala_Lumpur).
 * 3. Detecting the project's physical location on the computer/server disk (ROOT_PATH).
 * 4. Auto-detecting whether we are running locally (XAMPP localhost) or on the live
 *    production server (cPanel https://kismec.org.my/jupiter/intern) and building
 *    the correct web address (BASE_URL).
 * 5. Managing upload paths and web URLs for media evidence files.
 * 6. Providing helper functions to resolve image and video paths anywhere in the app.
 * ============================================================================
 */

// Step 1: Enforce Strict Typing
// ----------------------------------------------------------------------------
// By enabling strict_types, PHP will strictly verify that function arguments
// and return values match their declared types (e.g., string must be string,
// int must be int). This prevents hidden bugs from silent automatic conversions.
declare(strict_types=1);

// Step 2: Timezone Setting
// ----------------------------------------------------------------------------
// Ensure all date and time operations (date(), time(), strtotime(), new DateTime())
// use Malaysian Standard Time (GMT+8 / Asia/Kuala_Lumpur) rather than the server's
// default UTC time. This ensures accurate SLA tracking and audit timestamps.
date_default_timezone_set('Asia/Kuala_Lumpur');

// Step 3: Physical Disk Path Resolver (ROOT_PATH)
// ----------------------------------------------------------------------------
// We need to know where our project files live on the hard drive/SSD so that PHP
// can safely include files, save uploaded media, and embed images into PDFs.
//
// __DIR__ gives the folder of this file (e.g. "C:\xampp\htdocs\Intern\config").
// realpath(__DIR__ . '/..') moves up one level to the main project folder.
// str_replace('\\', '/', ...) standardizes all backslashes to forward slashes
// so file paths work identically on both Windows (XAMPP) and Linux (cPanel).
$rootPath = str_replace('\\', '/', realpath(__DIR__ . '/..'));

// define() creates a global constant that cannot be accidentally overwritten.
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', $rootPath); // Example: "C:/xampp/htdocs/Intern"
}

// Step 4: Environment Detection & Web URL Builder (BASE_URL)
// ----------------------------------------------------------------------------
// When writing web applications, links and images need full web addresses
// (e.g., "http://localhost/Intern/dashboard.php").
//
// Instead of manually changing URLs every time code is copied from XAMPP to
// production, this block automatically detects where the application is running:
//
// A) Local Environment: localhost, 127.0.0.1, or local command line (CLI).
// B) Production Environment: live server (kismec.org.my).
$httpHost = $_SERVER['HTTP_HOST'] ?? '';
$serverName = $_SERVER['SERVER_NAME'] ?? '';

// Check if running on localhost or 127.0.0.1 or command-line interface (CLI)
$isLocal = empty($httpHost) ||
    in_array($httpHost, ['localhost', '127.0.0.1', '::1'], true) ||
    in_array($serverName, ['localhost', '127.0.0.1', '::1'], true) ||
    str_starts_with($httpHost, 'localhost:') ||
    str_starts_with($httpHost, '127.0.0.1:');

if ($isLocal) {
    // --- LOCAL DEVELOPMENT (XAMPP) ---
    if (!empty($httpHost)) {
        // Detect whether the connection is secure (https://) or standard (http://)
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';

        // Auto-detect any sub-folder under XAMPP htdocs (e.g. "/Intern" or "/jupiter/intern")
        $docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
        $appDir = str_replace('\\', '/', ROOT_PATH);

        if (!empty($docRoot) && str_starts_with($appDir, $docRoot)) {
            $subPath = trim(str_replace($docRoot, '', $appDir), '/');
            $baseUrl = $protocol . $httpHost . ($subPath !== '' ? '/' . $subPath : '');
        } else {
            // Default fallback for standard local XAMPP setup
            $baseUrl = 'http://localhost/intern';
        }
    } else {
        // Fallback for background tasks or command line scripts (CLI) where $_SERVER is empty
        $baseUrl = 'http://localhost/intern';
    }
} else {
    // --- LIVE PRODUCTION SERVER ---
    // Production cPanel hosting URL (without trailing slash)
    // Production path: https://kismec.org.my/jupiter/project_management
    $baseUrl = 'https://kismec.org.my/jupiter/project_management';
}

// Save the resulting web address into the global constant BASE_URL
if (!defined('BASE_URL')) {
    define('BASE_URL', rtrim($baseUrl, '/')); // e.g. "http://localhost/Intern" or "https://kismec.org.my/jupiter/project_management"
}

// Step 4b: Production Database Configuration Constants (cPanel)
// ----------------------------------------------------------------------------
// When uploaded to your live cPanel hosting server, config/db.php automatically
// reads these constants to connect to your production MySQL database:
//
// Server:   localhost
// Database: kismec_project_management
// Username: kismec_project_management
// Password: kismec_project_management
//
// (We also define alternatives in case your cPanel setup created names with spaces)
if (!defined('PROD_DB_HOST')) {
    define('PROD_DB_HOST', 'localhost');
}
if (!defined('PROD_DB_NAME')) {
    define('PROD_DB_NAME', 'kismec_project_management');
}
if (!defined('PROD_DB_USER')) {
    define('PROD_DB_USER', 'kismec_project_management');
}
if (!defined('PROD_DB_PASS')) {
    define('PROD_DB_PASS', 'kismec_project_management');
}

// Alternative variations (with literal space) for resilient fallback
if (!defined('PROD_DB_NAME_ALT')) {
    define('PROD_DB_NAME_ALT', 'kismec_ project_management');
}
if (!defined('PROD_DB_USER_ALT')) {
    define('PROD_DB_USER_ALT', 'kismec_ project_management');
}
if (!defined('PROD_DB_PASS_ALT')) {
    define('PROD_DB_PASS_ALT', 'kismec_ project_management');
}

// Step 5: Upload Directory Configuration
// ----------------------------------------------------------------------------
// GOLDEN RULE FOR FILE UPLOADS:
// Never store full hardcoded server paths (like "C:/xampp/htdocs/Intern/uploads...")
// in the database! Why? Because if you migrate the database to cPanel Linux,
// "C:/xampp" won't exist and all image links will break.
//
// Instead, we only store clean relative paths (e.g. "uploads/evidence/2026/11/photo.jpg")
// in MySQL, and resolve them dynamically using these three constants:
//
// 1. UPLOAD_DIR_REL: Relative path stored in MySQL.
// 2. UPLOAD_PATH:    Physical hard disk directory where PHP saves files.
// 3. UPLOAD_URL:     Web URL used by the browser to display images.

if (!defined('UPLOAD_DIR_REL')) {
    define('UPLOAD_DIR_REL', 'uploads/evidence');
}

if (!defined('UPLOAD_PATH')) {
    define('UPLOAD_PATH', ROOT_PATH . '/' . UPLOAD_DIR_REL); // e.g. "C:/xampp/htdocs/Intern/uploads/evidence"
}

if (!defined('UPLOAD_URL')) {
    define('UPLOAD_URL', BASE_URL . '/' . UPLOAD_DIR_REL);   // e.g. "http://localhost/Intern/uploads/evidence"
}

// Step 6: Application Metadata Constants
// ----------------------------------------------------------------------------
// Central place to define project name and currency symbol for consistency across all pages.
if (!defined('APP_NAME')) {
    define('APP_NAME', 'KISMEC Intern Task & Project Monitoring System');
}

// WHY WE USE APP_CURRENCY (AND NOT JUST CURRENCY_SYMBOL):
// On Linux/cPanel servers (such as kismec.org.my), PHP provides built-in POSIX/nl_langinfo
// constants, one of which is `CURRENCY_SYMBOL` representing the integer value 262145.
// On Windows (XAMPP), this constant does not exist, so defining CURRENCY_SYMBOL worked locally.
// But on Linux, PHP's built-in integer constant took precedence, printing "262145 450.00"!
// To guarantee 100% cross-platform compatibility across Windows and Linux, we use APP_CURRENCY.
if (!defined('APP_CURRENCY')) {
    define('APP_CURRENCY', 'RM'); // Malaysian Ringgit
}

// Fallback constant for backwards compatibility (only defined if not already reserved by PHP)
if (!defined('CURRENCY_SYMBOL')) {
    define('CURRENCY_SYMBOL', 'RM');
}

// Step 7: Automatic Directory Creation
// ----------------------------------------------------------------------------
// If the uploads directory does not exist yet (e.g. on fresh setup or new server),
// create it automatically with read/write permissions (0755).
if (!is_dir(UPLOAD_PATH)) {
    @mkdir(UPLOAD_PATH, 0755, true);
}

// Step 8: Path Resolution Helper Functions
// ----------------------------------------------------------------------------

/**
 * resolveMediaUrl(?string $relativePath): string
 *
 * Takes a relative path from the database (e.g. "uploads/evidence/2026/11/img.jpg")
 * and converts it into a clickable full web URL for browser display.
 *
 * Example:
 *   Input:  "uploads/evidence/2026/11/robot.jpg"
 *   Output: "http://localhost/Intern/uploads/evidence/2026/11/robot.jpg"
 *
 * Also safely handles files that are already full URLs (e.g. external links).
 *
 * @param string|null $relativePath Relative path stored in database
 * @return string Full web URL accessible by browser, or empty string
 */
function resolveMediaUrl(?string $relativePath): string
{
    // Return empty if no path was provided
    if (empty($relativePath)) {
        return '';
    }

    // If the path is already an absolute web address (http:// or https://), return it directly
    if (str_starts_with($relativePath, 'http://') || str_starts_with($relativePath, 'https://')) {
        return $relativePath;
    }

    // Otherwise, combine BASE_URL with the clean relative path
    return BASE_URL . '/' . ltrim($relativePath, '/');
}

/**
 * resolveMediaPath(?string $relativePath): string
 *
 * Takes a relative path from the database (e.g. "uploads/evidence/2026/11/img.jpg")
 * and converts it into the physical filesystem path on the server's hard drive.
 *
 * Why is this needed?
 * 1. PDF Generation (Dompdf): Dompdf embeds images much faster and reliably
 *    when reading local disk files directly rather than fetching via HTTP requests.
 * 2. File Operations: When deleting a task or audit log, PHP's unlink() requires
 *    the exact hard disk path to delete the physical file.
 *
 * Example:
 *   Input:  "uploads/evidence/2026/11/robot.jpg"
 *   Output: "C:/xampp/htdocs/Intern/uploads/evidence/2026/11/robot.jpg"
 *
 * @param string|null $relativePath Relative path stored in database
 * @return string Physical disk path on server hard drive, or empty string
 */
function resolveMediaPath(?string $relativePath): string
{
    // Return empty if no path was provided
    if (empty($relativePath)) {
        return '';
    }

    // Combine ROOT_PATH with the clean relative path
    return ROOT_PATH . '/' . ltrim($relativePath, '/');
}
