<?php
/**
 * api/feedback.php – submit feedback on an AI message.
 * POST { message_id, rating: helpful|not_helpful, comment }
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
$in = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$messageId = (int) ($in['message_id'] ?? 0);
$rating    = in_array($in['rating'] ?? '', ['helpful','not_helpful'], true) ? $in['rating'] : '';
$comment   = mb_substr(trim((string)($in['comment'] ?? '')), 0, 400);

if ($messageId <= 0 || $rating === '') json_out(['ok' => false, 'error' => 'Missing message or rating.']);

$pdo = db();
// Confirm the message is an AI message and belongs to one of this customer's conversations
$s = $pdo->prepare('SELECT m.conversation_id FROM conversation_messages m WHERE m.id = :id AND m.sender_type = "ai"');
$s->execute([':id' => $messageId]);
$row = $s->fetch();
if (!$row) json_out(['ok' => false, 'error' => 'Message not found.'], 404);

$c = $pdo->prepare('SELECT customer_id FROM conversations WHERE id = :cid');
$c->execute([':cid' => $row['conversation_id']]);
$conv = $c->fetch();
if (!$conv || (int)$conv['customer_id'] !== (int)$me['id']) {
    json_out(['ok' => false, 'error' => 'Forbidden.'], 403);
}

$pdo->prepare('INSERT INTO conversation_feedback (message_id, rating, comment) VALUES (:m, :r, :c)
               ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment)')
    ->execute([':m' => $messageId, ':r' => $rating, ':c' => $comment !== '' ? $comment : null]);

log_activity($me['id'], 'feedback', "Feedback ($rating) on message {$messageId}", client_ip(), $messageId);
json_out(['ok' => true, 'message' => 'Thanks for your feedback!']);
