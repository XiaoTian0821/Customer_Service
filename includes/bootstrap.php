<?php
/**
 * includes/bootstrap.php
 * Global bootstrap for every NovaServe page.
 *
 * - Loads config + db
 * - Starts a hardened session
 * - Registers the error/exception handlers
 * - Provides global helper functions
 * - Defines APP_BASE for URL building
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

// ---------------------------------------------------------------------------
// App base URL (auto-detected so it works in htdocs subfolders or subfolders
// under a subdomain on cPanel). Override with APP_URL if auto-detect is wrong.
// ---------------------------------------------------------------------------
function app_base(): string
{
    if (defined('APP_URL') && APP_URL !== '') {
        return APP_URL;
    }
    // e.g. /Customer_Service or /customer_service/
    $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    // strip common page names so we keep just the folder
    return rtrim($script, '/');
}

// ---------------------------------------------------------------------------
// Error handling
// ---------------------------------------------------------------------------
set_error_handler(function (int $severity, string $message, string $file, int $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function (Throwable $e) {
    error_log('NovaServe exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    if (defined('APP_DEBUG') && APP_DEBUG) {
        echo '<pre>' . htmlspecialchars((string) $e) . '</pre>';
    } else {
        echo '<!doctype html><html><head><title>Something went wrong</title></head>'
            . '<body style="font-family:sans-serif;padding:40px;text-align:center;color:#334">
            <h1>Something went wrong.</h1>
            <p>Our team has been notified. Please try again later.</p></body></html>';
    }
});

// ---------------------------------------------------------------------------
// Session: hardened
// ---------------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 0) == 443,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('NOVASERVE_SESS');
    session_start();
}
