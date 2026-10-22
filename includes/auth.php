<?php
/**
 * includes/auth.php
 * Authentication + Role-Based Access Control.
 *
 * Roles (stored on users.role):
 *   super_admin   – full system control
 *   staff         – support staff
 *   content_mgr   – manages FAQ + knowledge base
 *   customer      – end user
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

/** The four application roles in descending privilege order. */
const ROLES = ['super_admin', 'staff', 'content_mgr', 'customer'];

/** Human labels for roles. */
function role_label(string $role): string
{
    return match ($role) {
        'super_admin' => 'Super Admin',
        'staff'       => 'Support Staff',
        'content_mgr' => 'Content Manager',
        default       => 'Customer',
    };
}

/**
 * Register a log event in the activity_log table (best-effort; never throws).
 */
function log_activity(int|string $userId, string $action, string $description, ?string $ip = null, ?int $targetId = null): void
{
    try {
        $pdo = db();
        $pdo->prepare(
            'INSERT INTO activity_log (actor_id, action, description, ip_address, target_id, created_at)
             VALUES (:aid, :act, :desc, :ip, :tid, NOW())'
        )->execute([
            ':aid'  => $userId,
            ':act'  => $action,
            ':desc' => $description,
            ':ip'   => $ip ?: client_ip(),
            ':tid'  => $targetId,
        ]);
    } catch (Throwable $e) {
        error_log('log_activity failed: ' . $e->getMessage());
    }
}

/** The currently logged-in user row, or null. */
function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    static $cached = null;
    if ($cached !== null) {
        return $cached['id'] == $_SESSION['user_id'] ? $cached['row'] : null;
    }
    try {
        $stmt = db()->prepare('SELECT * FROM users WHERE id = :id AND status = "active" LIMIT 1');
        $stmt->execute([':id' => $_SESSION['user_id']]);
        $row = $stmt->fetch() ?: null;
    } catch (Throwable $e) {
        $row = null;
    }
    $cached = ['id' => $_SESSION['user_id'], 'row' => $row];
    return $row;
}

/** Is anyone logged in? */
function is_logged_in(): bool
{
    return current_user() !== null;
}

/** True when the caller has any of the given roles. */
function has_role(string ...$roles): bool
{
    $u = current_user();
    if (!$u) return false;
    return in_array($u['role'], $roles, true);
}

/**
 * Gate the current request to one of the given roles.
 * Stops execution with 403 (or login redirect for guests) on failure.
 */
function require_role(string ...$roles): void
{
    $u = current_user();
    if (!$u) {
        // Store where we wanted to go so we can redirect back after login
        $_SESSION['intended'] = ($_SERVER['REQUEST_METHOD'] === 'POST' ? '/' : ($_SERVER['REQUEST_URI'] ?? '/'));
        redirect('login.php');
    }
    if (!in_array($u['role'], $roles, true)) {
        http_response_code(403);
        exit('You do not have permission to access this page.');
    }
}

/** Gate to an authenticated user of any role. */
function require_login(): void
{
    if (!is_logged_in()) {
        $_SESSION['intended'] = $_SERVER['REQUEST_URI'] ?? '/';
        redirect('login.php');
    }
}

/**
 * Log a user in. Updates session, rotates id, and persists the login.
 * @return bool success
 */
function attempt_login(string $email, string $password): bool
{
    $email = strtolower(trim($email));
    $stmt = db()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    $ok = $user && $user['status'] === 'active'
        && password_verify($password, $user['password']);

    // Always log the attempt (success or failure)
    log_activity(
        $user['id'] ?? 0,
        $ok ? 'login' : 'login_failed',
        $ok ? 'User logged in' : 'Failed login attempt',
        client_ip(),
        $user['id'] ?? null
    );

    if (!$ok) {
        return false;
    }

    // Rotate the session id to prevent fixation
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['role']    = $user['role'];
    $_SESSION['last_heartbeat'] = time();

    // Update last-login
    db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')
        ->execute([':id' => $user['id']]);

    return true;
}

function logout(): void
{
    $u = current_user();
    if ($u) {
        log_activity((int) $u['id'], 'logout', 'User logged out', client_ip());
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
