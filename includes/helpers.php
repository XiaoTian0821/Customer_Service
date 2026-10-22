<?php
/**
 * includes/helpers.php
 * Output escaping, CSRF, flash messages, and small utilities.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/** Escape for HTML. */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Short alias for e(). */
function esc(?string $value): string
{
    return e($value);
}

/**
 * Ensure a string contains a full URL or absolute path; if not, prepend the
 * app base folder so relative asset URLs resolve correctly.
 */
function url(string $path = ''): string
{
    $base = rtrim(app_base(), '/');
    if (preg_match('#^https?:#i', $path)) {
        return $path;
    }
    while (str_starts_with($path, $base . $base . '/')) {
        $path = substr($path, strlen($base));
    }
    if ($path === $base || str_starts_with($path, $base . '/')) {
        return $path;
    }
    if (str_starts_with($path, '/')) {
        return $base . $path;
    }
    return $base . '/' . $path;
}

/** Redirect helper (terminates). */
function redirect(string $to): void
{
    header('Location: ' . url($to));
    exit;
}

/**
 * ---- CSRF ----
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/** Hidden input HTML for a CSRF form field. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/**
 * Verify the incoming CSRF token. On failure: kill the session, 419.
 * Expects $_POST['csrf'] (forms) or X-CSRF-Token header (AJAX).
 */
function verify_csrf(): void
{
    $sent = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($sent) || $sent === '' || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        // Regenerate on failure to break replay
        session_regenerate_id(true);
        unset($_SESSION['csrf']);
        http_response_code(419);
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
            header('Content-Type: application/json');
            exit(json_encode(['ok' => false, 'error' => 'Invalid or expired session. Please refresh and try again.']));
        }
        exit('Invalid or expired session. Please refresh the page and try again.');
    }
}

// ---------------------------------------------------------------------------
// Flash messages
// ---------------------------------------------------------------------------
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** Pop one flash of the given type; returns message or null. */
function take_flash(string $type = null): ?string
{
    $msgs = [];
    foreach ($_SESSION['flash'] ?? [] as $i => $f) {
        if ($type === null || $f['type'] === $type) {
            $msgs[] = $f;
            unset($_SESSION['flash'][$i]);
        }
    }
    if (count($msgs) === 0) return null;
    // Return first, keep the rest available for other components
    array_shift($_SESSION['flash']) !== null;
    foreach ($msgs as $m) {
        $_SESSION['flash'][] = $m;
    }
    $first = $msgs[0];
    return $first['message'];
}

/** Render all pending flashes as Bootstrap alerts. */
function render_flash(): string
{
    $out = '';
    if (empty($_SESSION['flash'])) return $out;
    $map = ['success' => 'alert-success', 'error' => 'alert-danger', 'warning' => 'alert-warning', 'info' => 'alert-info'];
    foreach ($_SESSION['flash'] as $f) {
        $cls = $map[$f['type']] ?? 'alert-info';
        $out .= '<div class="alert ' . $cls . ' alert-dismissible fade show" role="alert">'
             . e($f['message'])
             . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
    // consume
    $_SESSION['flash'] = [];
    return $out;
}

// ---------------------------------------------------------------------------
// Misc
// ---------------------------------------------------------------------------
/** Truncate text to N characters, adding ellipsis. */
function truncate(string $text, int $n = 120): string
{
    if (mb_strlen($text) <= $n) return $text;
    return mb_substr($text, 0, $n) . '…';
}

/** Current user IP (best effort; behind proxies use X-Forwarded-For). */
function client_ip(): string
{
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    if (str_contains($ip, ',')) {
        $ip = trim(explode(',', $ip)[0]);
    }
    return substr($ip, 0, 45);
}

/** Format an ISO datetime for display. */
function fmt_date(?string $ts, string $format = 'd M Y H:i'): string
{
    if (!$ts) return '—';
    try {
        return date($format, strtotime($ts));
    } catch (Exception $ex) {
        return $ts;
    }
}

/** Format relative time (e.g. "5 min ago"). */
function time_ago(?string $ts): string
{
    if (!$ts) return '—';
    $diff = time() - strtotime($ts);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hr ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    return fmt_date($ts, 'd M Y H:i');
}

/** Humanised file size. */
function human_size(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 1) . ' ' . $units[$i];
}

/** JSON response helper. */
function json_out(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}
