<?php
/**
 * KISMEC Intern Task & Project Monitoring System
 * Centralized Application Settings & Path Resolver
 */

declare(strict_types=1);

// Set default timezone for all date/time calculations
date_default_timezone_set('Asia/Kuala_Lumpur');

// Define physical root directory path (normalized without trailing slash)
$rootPath = str_replace('\\', '/', realpath(__DIR__ . '/..'));
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', $rootPath);
}

// Environment Detection & URL Configuration
$httpHost = $_SERVER['HTTP_HOST'] ?? '';
$serverName = $_SERVER['SERVER_NAME'] ?? '';
$isLocal = empty($httpHost) ||
    in_array($httpHost, ['localhost', '127.0.0.1', '::1'], true) ||
    in_array($serverName, ['localhost', '127.0.0.1', '::1'], true) ||
    str_starts_with($httpHost, 'localhost:') ||
    str_starts_with($httpHost, '127.0.0.1:');

if ($isLocal) {
    if (!empty($httpHost)) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        // Auto-detect base directory relative to server document root
        $docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
        $appDir = str_replace('\\', '/', ROOT_PATH);
        if (!empty($docRoot) && str_starts_with($appDir, $docRoot)) {
            $subPath = trim(str_replace($docRoot, '', $appDir), '/');
            $baseUrl = $protocol . $httpHost . ($subPath !== '' ? '/' . $subPath : '');
        } else {
            // Fallback for local development per specification
            $baseUrl = 'http://localhost/intern';
        }
    } else {
        // CLI or background process fallback
        $baseUrl = 'http://localhost/intern';
    }
} else {
    // Production URL (no trailing slash)
    $baseUrl = 'https://kismec.org.my/jupiter/intern';
}

if (!defined('BASE_URL')) {
    define('BASE_URL', rtrim($baseUrl, '/'));
}

// Upload Directory Configurations
if (!defined('UPLOAD_DIR_REL')) {
    define('UPLOAD_DIR_REL', 'uploads/evidence');
}

if (!defined('UPLOAD_PATH')) {
    define('UPLOAD_PATH', ROOT_PATH . '/' . UPLOAD_DIR_REL);
}

if (!defined('UPLOAD_URL')) {
    define('UPLOAD_URL', BASE_URL . '/' . UPLOAD_DIR_REL);
}

// Application Metadata
if (!defined('APP_NAME')) {
    define('APP_NAME', 'KISMEC Intern Task & Project Monitoring System');
}

if (!defined('CURRENCY_SYMBOL')) {
    define('CURRENCY_SYMBOL', 'RM');
}

// Ensure the upload directory exists
if (!is_dir(UPLOAD_PATH)) {
    @mkdir(UPLOAD_PATH, 0755, true);
}

/**
 * Helper to resolve stored relative file path to full absolute URL
 */
function resolveMediaUrl(?string $relativePath): string
{
    if (empty($relativePath)) {
        return '';
    }
    // If already absolute URL
    if (str_starts_with($relativePath, 'http://') || str_starts_with($relativePath, 'https://')) {
        return $relativePath;
    }
    return BASE_URL . '/' . ltrim($relativePath, '/');
}

/**
 * Helper to resolve stored relative file path to full local filesystem path (for Dompdf/server checks)
 */
function resolveMediaPath(?string $relativePath): string
{
    if (empty($relativePath)) {
        return '';
    }
    return ROOT_PATH . '/' . ltrim($relativePath, '/');
}
