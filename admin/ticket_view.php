<?php
/** admin/ticket_view.php – staff view of a single ticket (full thread + internal notes). */
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('super_admin','staff');

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) redirect('admin/tickets.php');

$stmt = $pdo->prepare('SELECT t.*, u.name AS customer_name, u.email AS customer_email, c.name AS category_name, u2.name AS assigned_name
    FROM support_tickets t LEFT JOIN users u ON u.id = t.customer_id
    LEFT JOIN users u2 ON u2.id = t.assigned_to LEFT JOIN categories c ON c.id = t.category_id WHERE t.id = :id');
$stmt->execute([':id' => $id]);
$t = $stmt->fetch();
if (!$t) redirect('admin/tickets.php');

$mStmt = $pdo->prepare('SELECT tm.*, u.name AS sender_name FROM ticket_messages tm LEFT JOIN users u ON u.id = tm.sender_id WHERE tm.ticket_id = :id ORDER BY tm.created_at, tm.id');
$mStmt->execute([':id' => $id]);
$messages = $mStmt->fetchAll();

$aStmt = $pdo->prepare('SELECT * FROM ticket_attachments WHERE ticket_id = :id ORDER BY created_at');
$aStmt->execute([':id' => $id]);
$attachments = $aStmt->fetchAll();

$staffList = $pdo->query("SELECT id, name FROM users WHERE role IN ('staff','super_admin') AND status = 'active' ORDER BY name")->fetchAll();

// Reply / internal note / status update
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reply') {
    verify_csrf();
    $body = trim($_POST['body'] ?? '');
    $internal = !empty($_POST['internal_note']);
    if (mb_strlen($body) < 1) $errors[] = 'Reply is empty.';
    else {
        $pdo->prepare('INSERT INTO ticket_messages (ticket_id, sender_type, sender_id, body, is_internal) VALUES (:t, "staff", :s, :b, :i)')
            ->execute([':t' => $id, ':s' => current_user()['id'], ':b' => $body, ':i' => $internal ? 1 : 0]);
        log_activity(current_user()['id'], 'ticket_replied', $internal ? 'Internal note on ticket ' . $t['ticket_no'] : 'Replied on ' . $t['ticket_no'], client_ip(), $id);
        if (!$internal) {
            $pdo->prepare('INSERT INTO notifications (user_id, type, title, body, link) VALUES (:c, "ticket_replied", "Reply on " . :no, "A support staff replied to your ticket.", "ticket_view.php?id=" . :id2)')
                ->execute([':c' => $t['customer_id'], ':no' => $t['ticket_no'], ':id2' => $id]);
        }
        flash('success', $internal ? 'Internal note added.' : 'Reply posted.');
        redirect('admin/ticket_view.php?id=' . $id);
    }
}

// File upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload') {
    verify_csrf();
    if (!empty($_FILES['attachment']) && ($_FILES['attachment']['error'] ?? 0) === UPLOAD_ERR_OK) {
        $f = $_FILES['attachment'];
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','pdf','doc','docx','txt'], true) && $f['size'] <= MAX_UPLOAD_BYTES) {
            $stored = date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            @mkdir(__DIR__ . '/../uploads/tickets', 0755, true);
            if (move_uploaded_file($f['tmp_name'], __DIR__ . '/../uploads/tickets/' . $stored)) {
                $pdo->prepare('INSERT INTO ticket_attachments (ticket_id, message_id, stored_name, original_name, size_bytes, uploaded_by)
                    VALUES (:t, NULL, :sn, :on, :sz, :u)')
                    ->execute([':t' => $id, ':sn' => $stored, ':on' => $f['name'], ':sz' => $f['size'], ':u' => current_user()['id']]);
                flash('success', 'File uploaded.');
            }
        } else {
            flash('error', 'Upload rejected (type or size).');
        }
    }
    redirect('admin/ticket_view.php?id=' . $id);
}

// Status / priority / assignment update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'staff_update') {
    verify_csrf();
    $status = in_array($_POST['status'] ?? '', ['open','pending','in_progress','resolved','closed'], true) ? $_POST['status'] : $t['status'];
    $prio   = in_array($_POST['priority'] ?? '', ['low','medium','high','urgent'], true) ? $_POST['priority'] : $t['priority'];
    $assign = (int) ($_POST['assigned_to'] ?? 0);
    $pdo->prepare('UPDATE support_tickets SET status = :st, priority = :pr, assigned_to = :a WHERE id = :id')
        ->execute([':st' => $status, ':pr' => $prio, ':a' => $assign ?: null, ':id' => $id]);
    log_activity(current_user()['id'], 'ticket_updated', "Updated {$t['ticket_no']} to {$status} / {$prio}", client_ip(), $id);
    flash('success', 'Ticket updated.');
    redirect('admin/ticket_view.php?id=' . $id);
}

$pageTitle = 'Ticket ' . $t['ticket_no'];
require __DIR__ . '/../includes/admin_header.php';
?>

<nav class="ns-breadcrumb">
  <a href="<?= url('admin/tickets.php') ?>">Support Tickets</a><i class="fa-solid fa-chevron-right"></i><span><?= e($t['ticket_no']) ?></span>
</nav>

<div class="ns-admin-row">
  <div class="col-12 col-xl-8">
    <div class="ns-card">
      <div class="ns-card-head">
        <div>
          <h4 class="mb-1"><?= e($t['subject']) ?></h4>
          <div class="ns-ticket-tags">
            <span class="ns-ticket-no"><?= e($t['ticket_no']) ?></span>
            <span class="prio-<?= $t['priority'] ?>"><?= e(ucfirst($t['priority'])) ?></span>
            <span class="ns-status-badge"><?= e(str_replace('_',' ', $t['status'])) ?></span>
            <span class="ns-tag-mute"><?= e($t['category_name'] ?: 'General') ?></span>
          </div>
        </div>
      </div>
      <div class="ns-card-body">
        <div class="ns-detail-meta mb-3">
          <span><i class="fa-solid fa-user me-1"></i><?= e($t['customer_name'] ?: '—') ?> (<?= e($t['customer_email'] ?: '—') ?>)</span>
          <span><i class="fa-regular fa-clock me-1"></i>Created <?= fmt_date($t['created_at']) ?></span>
          <?php if ($t['assigned_name']): ?><span><i class="fa-solid fa-user-tie me-1"></i><?= e($t['assigned_name']) ?></span><?php endif; ?>
        </div>

        <?php if ($errors): ?><div class="alert alert-danger"><?= e($errors[0]) ?></div><?php endif; ?>

        <div class="ns-msg-thread">
          <?php foreach ($messages as $m):
            $cls = $m['is_internal'] ? 'is-internal' : ($m['sender_type'] === 'staff' ? 'is-staff' : 'is-customer');
          ?>
            <div class="ns-thread-msg <?= $cls ?>">
              <div class="ns-thread-head">
                <strong><?= $m['sender_type'] === 'customer' ? 'Customer' : ($m['is_internal'] ? 'Internal Note' : 'Support Staff') ?></strong>
                <?php if ($m['sender_name']): ?><span class="text-muted">· <?= e($m['sender_name']) ?></span><?php endif; ?>
                <small class="text-muted ms-auto"><?= time_ago($m['created_at']) ?></small>
              </div>
              <div class="ns-thread-body"><?= nl2br(e($m['body'])) ?></div>
            </div>
          <?php endforeach; ?>
        </div>

        <?php if ($attachments): ?>
          <div class="ns-attachments">
            <h5 class="ns-h3"><i class="fa-solid fa-paperclip me-1"></i> Attachments</h5>
            <div class="ns-attach-list">
              <?php foreach ($attachments as $a): ?>
                <a class="ns-attach-item" href="<?= url('uploads/tickets/' . rawurlencode($a['stored_name'])) ?>" download target="_blank">
                  <i class="fa-solid fa-file"></i> <?= e($a['original_name']) ?> <small class="text-muted">(<?= human_size((int)$a['size_bytes']) ?>)</small>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <div class="ns-reply-box">
          <h5 class="ns-h3">Reply to customer</h5>
          <form method="post" class="row g-2">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reply">
            <div class="col-12"><textarea name="body" rows="3" class="form-control" placeholder="Write your reply…" required></textarea></div>
            <div class="col-12">
              <label class="form-check-label small me-3"><input type="checkbox" name="internal_note" class="form-check-input me-1"> Internal note</label>
              <button class="ns-btn-cta btn-sm" type="submit">Post reply</button>
            </div>
          </form>
          <form method="post" enctype="multipart/form-data" class="ns-upload-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="upload">
            <div class="d-flex gap-2 align-items-end">
              <div class="flex-grow-1">
                <label class="form-label small mb-1">Attach a file</label>
                <input type="file" name="attachment" class="form-control form-control-sm">
              </div>
              <button class="ns-btn-ghost btn-sm" type="submit">Upload</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 col-xl-4">
    <div class="ns-card ns-sticky">
      <div class="ns-card-head"><h5><i class="fa-solid fa-gear me-2"></i>Ticket Controls</h5></div>
      <form method="post" class="row g-2">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="staff_update">
        <div class="col-12"><label class="form-label small">Status</label>
          <select name="status" class="form-select">
            <?php foreach (['open','pending','in_progress','resolved','closed'] as $s): ?>
              <option value="<?= $s ?>" <?= $t['status']===$s?'selected':'' ?>><?= e(ucfirst(str_replace('_',' ',$s))) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="col-12"><label class="form-label small">Priority</label>
          <select name="priority" class="form-select">
            <?php foreach (['low','medium','high','urgent'] as $p): ?>
              <option value="<?= $p ?>" <?= $t['priority']===$p?'selected':'' ?>><?= e(ucfirst($p)) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="col-12"><label class="form-label small">Assign to</label>
          <select name="assigned_to" class="form-select">
            <option value="0">Unassigned</option>
            <?php foreach ($staffList as $s): ?><option value="<?= $s['id'] ?>" <?= (int)$t['assigned_to']===(int)$s['id']?'selected':'' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
          </select></div>
        <div class="col-12"><button class="ns-btn-cta" type="submit">Save changes</button></div>
      </form>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
