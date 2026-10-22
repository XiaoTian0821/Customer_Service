<?php
/**
 * api/chat.php – NovaServe AI chat endpoint.
 *
 * POST (JSON or form):
 *   { "message": "...", "conversation_id": 123, "language": "auto" }
 * Response:
 *   { ok, conversation_id, reply: { answer, intent, sentiment, confidence,
 *     provider, sources[], suggestions[], escalated, grounded } }
 *
 * Requires: a logged-in customer. CSRF token via header X-CSRF-Token.
 * The AI provider keys stay server-side; never returned.
 */
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ai_service.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    json_out(['ok' => false, 'error' => 'Authentication required.'], 401);
}

// CSRF
$sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? '');
if (!is_string($sent) || $sent === '' || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
    json_out(['ok' => false, 'error' => 'Invalid or expired session.'], 419);
}

$me = current_user();

// Allow customers and staff (staff can test)
if (!in_array($me['role'], ['customer','staff','super_admin'], true)) {
    json_out(['ok' => false, 'error' => 'You cannot use the chat endpoint.'], 403);
}

$method = $_SERVER['REQUEST_METHOD'];
$message = trim($_POST['message'] ?? '');
if ($method === 'POST' && empty($message) && str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'json')) {
    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    $message = trim($in['message'] ?? '');
}

if (mb_strlen($message) < 1) {
    json_out(['ok' => false, 'error' => 'Please type a message.']);
}

$conversationId = (int) ($_POST['conversation_id'] ?? ($method==='POST' ? json_decode(file_get_contents('php://input'), true) : ['conversation_id'=>0])['conversation_id'] ?? 0);
$language = $_POST['language'] ?? ($_SESSION['lang'] ?? 'auto');
$language = in_array($language, ['en','ms','zh','auto'], true) ? $language : 'auto';

$pdo = db();

// Verify the conversation belongs to this customer
if ($conversationId > 0) {
    $s = $pdo->prepare('SELECT * FROM conversations WHERE id = :id AND customer_id = :c');
    $s->execute([':id' => $conversationId, ':c' => $me['id']]);
    $conv = $s->fetch();
    if (!$conv) json_out(['ok' => false, 'error' => 'Conversation not found.'], 404);
} else {
    $ins = $pdo->prepare('INSERT INTO conversations (customer_id, status) VALUES (:c, "active")');
    $ins->execute([':c' => $me['id']]);
    $conversationId = (int) $pdo->lastInsertId();
    $conv = ['id' => $conversationId, 'status' => 'active'];
}

// Store the customer message
$pdo->prepare('INSERT INTO conversation_messages (conversation_id, sender_type, sender_id, content, intent, sentiment)
               VALUES (:c, "customer", :s, :m, :i, :sent)')
    ->execute([
        ':c'    => $conversationId,
        ':s'    => $me['id'],
        ':m'    => $message,
        ':i'    => NovaAI::detectIntent($message),
        ':sent' => NovaAI::detectSentiment($message),
    ]);
$lastMsgId = (int) $pdo->lastInsertId();

// Build recent history (last 8) for context
$hist = $pdo->prepare('SELECT sender_type, content FROM conversation_messages WHERE conversation_id = :c AND id < :mid ORDER BY id DESC LIMIT 8');
$hist->execute([':c' => $conversationId, ':mid' => $lastMsgId]);
$historyRows = $hist->fetchAll();
$history = array_map(fn($r) => ['role' => $r['sender_type'] === 'ai' ? 'assistant' : 'user', 'content' => $r['content']], array_reverse($historyRows));

// Run the AI pipeline
$ai = new NovaAI($pdo);
$reply = $ai->answer($message, $history, $language);

// Persist the AI response
$pdo->prepare('INSERT INTO conversation_messages (conversation_id, sender_type, sender_id, content, intent, sentiment, confidence, ai_provider, sources)
               VALUES (:c, "ai", NULL, :m, :i, :sent, :conf, :prov, :src)')
    ->execute([
        ':c'    => $conversationId,
        ':m'    => $reply['answer'],
        ':i'    => $reply['intent'],
        ':sent' => $reply['sentiment'],
        ':conf' => $reply['confidence'],
        ':prov' => $reply['provider'],
        ':src'  => json_encode($reply['sources'], JSON_UNESCAPED_UNICODE),
    ]);
$aiMsgId = (int) $pdo->lastInsertId();

// Update the conversation rollup
$status = 'active';
$humanStatus = null;
if ($reply['escalated']) {
    $status = 'escalated';
    $humanStatus = 'pending';
    // Create escalation record + notify staff
    $pdo->prepare('INSERT INTO escalations (conversation_id, customer_id, reason, status) VALUES (:c, :cust, :r, "pending")')
        ->execute([
            ':c'    => $conversationId,
            ':cust' => $me['id'],
            ':r'    => 'Auto-escalated: intent=' . $reply['intent'] . ', sentiment=' . $reply['sentiment'] . ', confidence=' . $reply['confidence'],
        ]);
    $pdo->prepare('INSERT INTO notifications (user_id, type, title, body, link)
                   SELECT id, "escalation", "Conversation escalated", :b, "admin/escalations.php"
                   FROM users WHERE role IN ("staff","super_admin")')
        ->execute([':b' => 'A conversation needs attention.']);
    log_activity($me['id'], 'escalated', "Conversation {$conversationId} auto-escalated", client_ip(), $conversationId);
} elseif ($reply['confidence'] >= 0.75) {
    $status = 'ai_resolved';
}
$pdo->prepare('UPDATE conversations SET status = :st, intent = :i, sentiment = :sent, confidence = :conf, ai_provider = :prov,
               human_status = COALESCE(:hs, human_status), escalated_at = CASE WHEN :es THEN NOW() ELSE escalated_at END WHERE id = :id')
    ->execute([
        ':st' => $status, ':i' => $reply['intent'], ':sent' => $reply['sentiment'],
        ':conf' => $reply['confidence'], ':prov' => $reply['provider'],
        ':hs' => $humanStatus, ':es' => $reply['escalated'] ? 1 : 0, ':id' => $conversationId,
    ]);

log_activity($me['id'], 'ai_message', "AI replied in conversation {$conversationId} (" . $reply['provider'] . ")", client_ip(), $conversationId);

json_out([
    'ok' => true,
    'conversation_id' => $conversationId,
    'message_id' => $aiMsgId,
    'reply' => [
        'answer'      => $reply['answer'],
        'intent'      => $reply['intent'],
        'sentiment'   => $reply['sentiment'],
        'confidence'  => $reply['confidence'],
        'provider'    => $reply['provider'],
        'sources'     => $reply['sources'],
        'suggestions' => $reply['suggestions'],
        'escalated'   => $reply['escalated'],
        'grounded'    => $reply['grounded'],
    ],
]);
