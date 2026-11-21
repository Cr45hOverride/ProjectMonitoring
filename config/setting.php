<?php
/**
 * ============================================================================
 * KISMEC Intern Task & Project Monitoring System
 * File: config/setting.php
 * ============================================================================
 * System Settings, Base URL & Upload Paths
 */

declare(strict_types=1);

// 1. Timezone Configuration (Malaysian Standard Time GMT+8)
// ----------------------------------------------------------------------------
date_default_timezone_set('Asia/Kuala_Lumpur');

// 2. Physical Run Folder (Project Root Directory on Disk)
// ----------------------------------------------------------------------------
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', str_replace('\\', '/', realpath(__DIR__ . '/..')));
}

// 3. System Web URL (BASE_URL)
// ----------------------------------------------------------------------------
// You can set this manually if desired (e.g. define('BASE_URL', 'http://jupiter.kismec.org.my/intern');)
// Otherwise, it auto-detects the current running domain and folder:
if (!defined('BASE_URL')) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $rawDocRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
    $docRoot = !empty($rawDocRoot) ? str_replace('\\', '/', realpath($rawDocRoot) ?: '') : '';
    $subDir = ($docRoot !== '' && str_starts_with(ROOT_PATH, $docRoot))
        ? trim(str_replace($docRoot, '', ROOT_PATH), '/')
        : basename(ROOT_PATH);
    define('BASE_URL', rtrim($scheme . $host . ($subDir !== '' ? '/' . $subDir : ''), '/'));
}

// 4. Upload Location (Subfolder 'uploads' from current run folder)
// ----------------------------------------------------------------------------
if (!defined('UPLOAD_DIR_REL')) {
    define('UPLOAD_DIR_REL', 'uploads');
}
if (!defined('UPLOAD_PATH')) {
    define('UPLOAD_PATH', ROOT_PATH . '/' . UPLOAD_DIR_REL); // e.g. C:/xampp/htdocs/Intern/uploads
}
if (!defined('UPLOAD_URL')) {
    define('UPLOAD_URL', BASE_URL . '/' . UPLOAD_DIR_REL);   // e.g. http://localhost/intern/uploads
}

// Ensure the 'uploads' directory exists on the disk
if (!is_dir(UPLOAD_PATH)) {
    @mkdir(UPLOAD_PATH, 0755, true);
}

// 5. System Constants
// ----------------------------------------------------------------------------
if (!defined('APP_NAME')) {
    define('APP_NAME', 'KISMEC Intern Task & Project Monitoring System');
}

// Note: APP_CURRENCY is used to avoid PHP's Linux POSIX CURRENCY_SYMBOL constant conflict (262145)
if (!defined('APP_CURRENCY')) {
    define('APP_CURRENCY', 'RM'); // Malaysian Ringgit
}
if (!defined('CURRENCY_SYMBOL')) {
    define('CURRENCY_SYMBOL', 'RM');
}

// 6. Helper Functions for Media Paths and URLs
// ----------------------------------------------------------------------------

/**
 * Converts a relative path from DB into a full web URL for browser display.
 */
function resolveMediaUrl(?string $path): string
{
    if (empty($path)) {
        return '';
    }
    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        return $path;
    }
    return BASE_URL . '/' . ltrim($path, '/');
}

/**
 * Converts a relative path from DB into the absolute disk path for file operations / PDF generation.
 */
function resolveMediaPath(?string $path): string
{
    if (empty($path)) {
        return '';
    }
    return ROOT_PATH . '/' . ltrim($path, '/');
}
