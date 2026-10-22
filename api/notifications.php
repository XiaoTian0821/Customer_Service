<?php
/**
 * api/notifications.php – fetch unread notifications count & mark read.
 * GET  – returns list for the logged-in user
 * POST { id } or { all: true } – mark read
 */
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
if (!is_logged_in()) json_out(['ok' => false, 'error' => 'Authentication required.'], 401);

$me = current_user();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? '');
    if (!is_string($sent) || $sent === '' || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        json_out(['ok' => false, 'error' => 'Invalid session.'], 419);
    }
    $in = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    if (!empty($in['all'])) {
        $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = :u')->execute([':u' => $me['id']]);
    } else {
        $nid = (int)($in['id'] ?? 0);
        $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :u')->execute([':id' => $nid, ':u' => $me['id']]);
    }
    json_out(['ok' => true, 'message' => 'Marked as read.']);
}

$s = $pdo->prepare('SELECT * FROM notifications WHERE user_id = :u ORDER BY is_read ASC, created_at DESC LIMIT 30');
$s->execute([':u' => $me['id']]);
$rows = $s->fetchAll();
$unread = (int) $pdo->prepare('SELECT COUNT(*) c FROM notifications WHERE user_id = :u AND is_read = 0')->execute([':u' => $me['id']]) ? 0 : 0;
// simpler unread count
$u = $pdo->prepare('SELECT COUNT(*) c FROM notifications WHERE user_id = :u AND is_read = 0');
$u->execute([':u' => $me['id']]);
$unread = (int) $u->fetch()['c'];

json_out(['ok' => true, 'data' => $rows, 'unread' => $unread]);
