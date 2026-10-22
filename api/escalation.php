<?php
/**
 * api/escalation.php – explicit "talk to a human" request.
 * POST { conversation_id, reason }
 */
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
if (!is_logged_in()) json_out(['ok' => false, 'error' => 'Authentication required.'], 401);
$sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? '');
if (!is_string($sent) || $sent === '' || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
    json_out(['ok' => false, 'error' => 'Invalid session.'], 419);
}

$me = current_user();
$pdo = db();
$in = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$convId = (int) ($in['conversation_id'] ?? 0);
$reason = mb_substr(trim((string)($in['reason'] ?? 'Customer requested human support.')), 0, 255);

if ($convId <= 0) json_out(['ok' => false, 'error' => 'No conversation to escalate.']);

$s = $pdo->prepare('SELECT * FROM conversations WHERE id = :id');
$s->execute([':id' => $convId]);
$conv = $s->fetch();
if (!$conv) json_out(['ok' => false, 'error' => 'Conversation not found.'], 404);
if (in_array($me['role'], ['customer'], true) && (int)$conv['customer_id'] !== (int)$me['id']) {
    json_out(['ok' => false, 'error' => 'Forbidden.'], 403);
}

// Create escalation if not already pending
$chk = $pdo->prepare("SELECT id FROM escalations WHERE conversation_id = :c AND status IN ('pending','taken_over')");
$chk->execute([':c' => $convId]);
$existing = $chk->fetch();

if (!$existing) {
    $pdo->prepare('INSERT INTO escalations (conversation_id, customer_id, reason, status) VALUES (:c, :cust, :r, "pending")')
        ->execute([':c' => $convId, ':cust' => $me['id'], ':r' => $reason]);
}

$pdo->prepare("UPDATE conversations SET status = 'escalated', human_status = COALESCE(human_status, 'pending') WHERE id = :id")
    ->execute([':id' => $convId]);

$pdo->prepare('INSERT INTO notifications (user_id, type, title, body, link)
              SELECT id, "escalation", "Human support requested", :b, "admin/escalations.php"
              FROM users WHERE role IN ("staff","super_admin")')
    ->execute([':b' => "Conversation {$convId}: {$reason}"]);

log_activity($me['id'], 'escalated', "Human requested on conversation {$convId}: {$reason}", client_ip(), $convId);
json_out(['ok' => true, 'message' => 'We have notified our support team. A representative will take over this conversation shortly.']);
