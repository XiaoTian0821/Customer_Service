<?php
/** ticket.php – customer's tickets + new-ticket form. */
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_login();
$me = current_user();
if (has_role('super_admin','staff','content_mgr')) {
    // staff see the admin ticket console
    redirect('admin/tickets.php');
}

$pageTitle = 'My Support Tickets';
$categories = db()->query("SELECT * FROM categories WHERE scope IN ('both','faq') AND is_active = 1 ORDER BY sort")->fetchAll();

$errors = [];
$old = ['subject' => $_POST['subject'] ?? '', 'category' => $_POST['category_id'] ?? '', 'description' => $_POST['description'] ?? ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ticket_action'] ?? '') === 'create') {
    verify_csrf();
    $subject = trim($old['subject']);
    $desc    = trim($old['description']);
    $catId   = (int) ($old['category'] ?: 0);
    $prio    = in_array($_POST['priority'] ?? '', ['low','medium','high','urgent'], true) ? $_POST['priority'] : 'medium';

    if (mb_strlen($subject) < 5) $errors[] = 'Please give your ticket a clearer subject.';
    if (mb_strlen($desc) < 10)   $errors[] = 'Please describe the issue in a little more detail.';

    if (!$errors) {
        $no = 'NS-' . date('Ymd') . '-' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
        $pdo = db();
        $pdo->prepare('INSERT INTO support_tickets (ticket_no, customer_id, subject, category_id, description, priority, status)
                       VALUES (:no, :c, :s, :cat, :d, :p, "open")')
            ->execute([':no' => $no, ':c' => $me['id'], ':s' => $subject, ':cat' => $catId ?: null, ':d' => $desc, ':p' => $prio]);
        $tid = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO ticket_messages (ticket_id, sender_type, sender_id, body) VALUES (:t, "customer", :s, :b)')
            ->execute([':t' => $tid, ':s' => $me['id'], ':b' => $desc]);
        // notify staff
        $pdo->prepare('INSERT INTO notifications (user_id, type, title, body, link)
                      SELECT id, "ticket_created", "New ticket " . :no, :s, "admin/tickets.php" FROM users WHERE role IN ("staff","super_admin")')
            ->execute([':no' => $no, ':s' => $subject]);
        log_activity($me['id'], 'ticket_created', "Ticket {$no} created: {$subject}", client_ip(), $tid);
        flash('success', "Ticket {$no} submitted. Our team will be with you shortly.");
        redirect('ticket.php');
    }
}

$myTickets = db()->prepare('SELECT t.*, u.name AS customer_name, u2.name AS assigned_name, COUNT(tm.id) AS msg_count
    FROM support_tickets t LEFT JOIN users u ON u.id = t.customer_id
    LEFT JOIN users u2 ON u2.id = t.assigned_to
    LEFT JOIN ticket_messages tm ON tm.ticket_id = t.id AND tm.is_internal = 0
    WHERE t.customer_id = :c GROUP BY t.id ORDER BY t.updated_at DESC');
$myTickets->execute([':c' => $me['id']]);
$tickets = $myTickets->fetchAll();

$nsScripts = ['ticket.js'];
require __DIR__ . '/includes/header.php';
?>
<div class="container ns-page">
  <div class="ns-page-head">
    <div><h1 class="ns-h1">My Support Tickets</h1>
      <p class="ns-lead">Track your requests, reply in-thread, and attach supporting files.</p></div>
    <a class="ns-btn-cta" href="#newTicket" id="openNewTicket"><i class="fa-solid fa-plus me-1"></i> New ticket</a>
  </div>

  <?php if (!$tickets): ?>
    <div class="ns-empty text-center py-5"><i class="fa-solid fa-inbox ns-empty-ico"></i>
      <p>You have no tickets yet. Open one below and our team will follow up.</p></div>
  <?php else: ?>
    <div class="ns-ticket-list">
      <?php
      $statusBadge = ['open'=>'ns-tag-live','pending'=>'ns-tag-warn','in_progress'=>'ns-tag-info','resolved'=>'ns-tag-ok','closed'=>'ns-tag-mute'];
      $prioBadge   = ['low'=>'prio-low','medium'=>'prio-med','high'=>'prio-high','urgent'=>'prio-urg'];
      foreach ($tickets as $t): ?>
        <a class="ns-ticket-card" href="<?= url('ticket_view.php?id=' . $t['id']) ?>">
          <div class="ns-ticket-top">
            <span class="ns-ticket-no"><?= e($t['ticket_no']) ?></span>
            <span class="<?= $statusBadge[$t['status']] ?>"><?= e(str_replace('_',' ', $t['status'])) ?></span>
            <span class="<?= $prioBadge[$t['priority']] ?>"><?= e(ucfirst($t['priority'])) ?></span>
          </div>
          <strong class="ns-ticket-subject"><?= e($t['subject']) ?></strong>
          <div class="ns-ticket-meta">
            <small class="text-muted"><i class="fa-regular fa-message me-1"></i><?= $t['msg_count'] ?> messages</small>
            <small class="text-muted ms-auto"><i class="fa-regular fa-clock me-1"></i>Updated <?= time_ago($t['updated_at']) ?></small>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- New ticket form (collapsible) -->
  <section id="newTicket" class="ns-ticket-form d-none">
    <h3 class="ns-h3 mb-3">Open a new ticket</h3>
    <?php foreach ($errors as $err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post" id="ticketForm" class="row g-3">
      <?= csrf_field() ?>
      <input type="hidden" name="ticket_action" value="create">
      <div class="col-12 col-md-8">
        <label class="form-label">Subject</label>
        <input name="subject" class="form-control" value="<?= e($old['subject']) ?>" required>
      </div>
      <div class="col-6 col-md-4">
        <label class="form-label">Category</label>
        <select name="category_id" class="form-select">
          <option value="">— General —</option>
          <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>" <?= $old['category'] == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-12">
        <label class="form-label">Priority</label>
        <select name="priority" class="form-select"><option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option><option value="urgent">Urgent</option></select>
      </div>
      <div class="col-12">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="4" required><?= e($old['description']) ?></textarea>
      </div>
      <div class="col-12"><button class="ns-btn-cta" type="submit">Submit ticket</button>
        <a class="ns-btn-ghost ms-2" href="#newTicket" id="closeNewTicket">Cancel</a></div>
    </form>
  </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
