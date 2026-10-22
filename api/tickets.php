<?php
/**
 * api/tickets.php – list and create tickets.
 * GET  – list tickets for the logged-in customer (or all for staff)
 * POST { subject, category_id, description, priority } – create a ticket
 */
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
if (!is_logged_in()) json_out(['ok' => false, 'error' => 'Authentication required.'], 401);

$me = current_user();
$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    // CSRF
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? '');
    if (!is_string($sent) || $sent === '' || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        json_out(['ok' => false, 'error' => 'Invalid session.'], 419);
    }
    if (!in_array($me['role'], ['customer','staff','super_admin'], true)) {
        json_out(['ok' => false, 'error' => 'Forbidden.'], 403);
    }
    $in = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $subject = trim((string)($in['subject'] ?? ''));
    $desc    = trim((string)($in['description'] ?? ''));
    $catId   = (int)($in['category_id'] ?? 0);
    $prio    = in_array($in['priority'] ?? '', ['low','medium','high','urgent'], true) ? $in['priority'] : 'medium';

    if (mb_strlen($subject) < 5) json_out(['ok' => false, 'error' => 'Subject is too short.']);
    if (mb_strlen($desc) < 10)   json_out(['ok' => false, 'error' => 'Please describe the issue in more detail.']);

    $no = 'NS-' . date('Ymd') . '-' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
    $pdo->prepare('INSERT INTO support_tickets (ticket_no, customer_id, subject, category_id, description, priority, status)
                   VALUES (:no, :c, :s, :cat, :d, :p, "open")')
        ->execute([':no' => $no, ':c' => $me['id'], ':s' => $subject, ':cat' => $catId ?: null, ':d' => $desc, ':p' => $prio]);
    $tid = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO ticket_messages (ticket_id, sender_type, sender_id, body) VALUES (:t, "customer", :s, :b)')
        ->execute([':t' => $tid, ':s' => $me['id'], ':b' => $desc]);
    $pdo->prepare('INSERT INTO notifications (user_id, type, title, body, link)
                   SELECT id, "ticket_created", "New ticket " . :no, :s, "admin/tickets.php" FROM users WHERE role IN ("staff","super_admin")')
        ->execute([':no' => $no, ':s' => $subject]);
    log_activity($me['id'], 'ticket_created', "Ticket {$no} via API: {$subject}", client_ip(), $tid);
    json_out(['ok' => true, 'ticket_id' => $tid, 'ticket_no' => $no]);
}

// GET: list
if (in_array($me['role'], ['staff','super_admin'], true)) {
    $rows = $pdo->query('SELECT t.*, u.name AS customer FROM support_tickets t LEFT JOIN users u ON u.id = t.customer_id ORDER BY t.updated_at DESC')->fetchAll();
} else {
    $s = $pdo->prepare('SELECT t.*, u.name AS customer FROM support_tickets t LEFT JOIN users u ON u.id = t.customer_id WHERE t.customer_id = :c ORDER BY t.updated_at DESC');
    $s->execute([':c' => $me['id']]);
    $rows = $s->fetchAll();
}
json_out(['ok' => true, 'data' => $rows]);
