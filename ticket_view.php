<?php
/** ticket_view.php – one ticket: messages, reply, attachment upload. */
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_login();
$me = current_user();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) redirect('ticket.php');

$stmt = db()->prepare('SELECT t.*, c.name AS category_name, u.name AS assigned_name
    FROM support_tickets t LEFT JOIN categories c ON c.id = t.category_id LEFT JOIN users u ON u.id = t.assigned_to WHERE t.id = :id');
$stmt->execute([':id' => $id]);
$t = $stmt->fetch();
if (!$t) redirect('ticket.php');
if ((int) $t['customer_id'] !== (int) $me['id'] && !has_role('staff','super_admin')) {
    http_response_code(403); exit('Forbidden');
}

// Fetch all messages including internal notes for staff
$isStaff = has_role('staff','super_admin');
$mStmt = db()->prepare('SELECT tm.*, u.name AS sender_name FROM ticket_messages tm LEFT JOIN users u ON u.id = tm.sender_id WHERE tm.ticket_id = :id ORDER BY tm.created_at, tm.id');
$mStmt->execute([':id' => $id]);
$messages = $mStmt->fetchAll();

// Attachments
$aStmt = db()->prepare('SELECT * FROM ticket_attachments WHERE ticket_id = :id ORDER BY created_at');
$aStmt->execute([':id' => $id]);
$attachments = $aStmt->fetchAll();

$errors = [];

// Handle reply POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reply') {
    verify_csrf();
    $body = trim($_POST['body'] ?? '');
    if (mb_strlen($body) < 2) {
        $errors[] = 'Your reply is too short.';
    } else {
        $pdo = db();
        $pdo->prepare('INSERT INTO ticket_messages (ticket_id, sender_type, sender_id, body) VALUES (:t, :st, :s, :b)')
            ->execute([':t' => $id, ':st' => $isStaff ? 'staff' : 'customer', ':s' => $me['id'], ':b' => $body]);
        log_activity($me['id'], 'ticket_replied', "Replied on ticket {$t['ticket_no']}", client_ip(), $id);
        if ($isStaff) {
            // notify customer
            $pdo->prepare('INSERT INTO notifications (user_id, type, title, body, link) VALUES (:c, "ticket_replied", "Reply on " . :no, "A support staff replied to your ticket.", "ticket_view.php?id=" . :id2)')
                ->execute([':c' => $t['customer_id'], ':no' => $t['ticket_no'], ':id2' => $id]);
        }
        flash('success', 'Reply posted.');
        redirect('ticket_view.php?id=' . $id);
    }
}

// Handle file upload POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload') {
    verify_csrf();
    $uploadErrors = [];
    if (!empty($_FILES['attachment']) && ($_FILES['attachment']['error'] ?? 0) === UPLOAD_ERR_OK) {
        $f = $_FILES['attachment'];
        $allowedExt = ['jpg','jpeg','png','pdf','doc','docx','txt'];
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            $uploadErrors[] = 'File type not allowed. Accepted: JPG, PNG, PDF, DOC, DOCX, TXT.';
        } elseif ($f['size'] > MAX_UPLOAD_BYTES) {
            $uploadErrors[] = 'File exceeds 5 MB limit.';
        } else {
            // MIME sniff check
            $mime = '';
            if (function_exists('finfo_open')) {
                $fi = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($fi, $f['tmp_name']);
                finfo_close($fi);
            }
            $allowedMime = ['image/jpeg','image/png','application/pdf','application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document','text/plain'];
            if ($mime !== '' && !in_array($mime, $allowedMime, true)) {
                $uploadErrors[] = 'File content does not match its type. Please upload a valid file.';
            }
        }
        if (!$uploadErrors) {
            $stored = date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $dest = __DIR__ . '/uploads/tickets/' . $stored;
            @mkdir(dirname($dest), 0755, true);
            if (move_uploaded_file($f['tmp_name'], $dest)) {
                db()->prepare('INSERT INTO ticket_attachments (ticket_id, message_id, stored_name, original_name, mime_type, size_bytes, uploaded_by)
                    VALUES (:t, NULL, :sn, :on, :m, :sz, :u)')
                    ->execute([':t' => $id, ':sn' => $stored, ':on' => $f['name'], ':m' => $mime, ':sz' => $f['size'], ':u' => $me['id']]);
                flash('success', 'File uploaded.');
            } else {
                $uploadErrors[] = 'Upload failed. Please try again.';
            }
        }
    } else {
        $uploadErrors[] = 'No file selected or upload failed.';
    }
    if ($uploadErrors) {
        foreach ($uploadErrors as $u) $errors[] = $u;
    } else {
        redirect('ticket_view.php?id=' . $id);
    }
}

// Staff: update status/priority/assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'staff_update' && $isStaff) {
    verify_csrf();
    $pdo = db();
    $newStatus = in_array($_POST['status'] ?? '', ['open','pending','in_progress','resolved','closed'], true) ? $_POST['status'] : null;
    $newPrio   = in_array($_POST['priority'] ?? '', ['low','medium','high','urgent'], true) ? $_POST['priority'] : null;
    $newAssign = (int) ($_POST['assigned_to'] ?? 0);
    $upd = [];
    $params = [':id' => $id];
    if ($newStatus) { $upd[] = 'status = :status'; $params[':status'] = $newStatus; }
    if ($newPrio)   { $upd[] = 'priority = :prio'; $params[':prio'] = $newPrio; }
    if ($newAssign > 0) { $upd[] = 'assigned_to = :a'; $params[':a'] = $newAssign; }
    if ($upd) {
        $pdo->prepare('UPDATE support_tickets SET ' . implode(', ', $upd) . ' WHERE id = :id')->execute($params);
        log_activity($me['id'], 'ticket_updated', "Updated ticket {$t['ticket_no']}", client_ip(), $id);
        flash('success', 'Ticket updated.');
        redirect('ticket_view.php?id=' . $id);
    }
}

$staffList = db()->query("SELECT id, name FROM users WHERE role IN ('staff','super_admin') AND status = 'active' ORDER BY name")->fetchAll();

$nsScripts = ['ticket.js'];
require __DIR__ . '/includes/header.php';
?>
<div class="container ns-page">
  <nav class="ns-breadcrumb"><a href="<?= url('ticket.php') ?>">My Tickets</a><i class="fa-solid fa-chevron-right"></i><span><?= e($t['ticket_no']) ?></span></nav>

  <div class="ns-ticket-detail">
    <div class="ns-ticket-detail-head">
      <div>
        <h1 class="ns-h1 mb-1"><?= e($t['subject']) ?></h1>
        <div class="ns-ticket-tags">
          <span class="ns-ticket-no"><?= e($t['ticket_no']) ?></span>
          <span class="ns-tag-mute"><?= e($t['category_name'] ?? 'General') ?></span>
          <span class="prio-<?= $t['priority'] ?>"><?= e(ucfirst($t['priority'])) ?></span>
          <span class="ns-status-badge"><?= e(str_replace('_',' ', $t['status'])) ?></span>
        </div>
      </div>
      <div class="ns-ticket-meta">
        <small class="text-muted"><i class="fa-regular fa-clock me-1"></i>Created <?= fmt_date($t['created_at']) ?></small>
        <?php if ($t['assigned_name']): ?><small class="text-muted"><i class="fa-solid fa-user-tie me-1"></i>Assigned to <?= e($t['assigned_name']) ?></small><?php endif; ?>
      </div>
    </div>

    <?php if ($errors): ?><div class="alert alert-danger"><?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?>

    <!-- Message thread -->
    <div class="ns-msg-thread">
      <?php foreach ($messages as $m):
        // hide internal notes from customers
        if (!$isStaff && (int) $m['is_internal']) continue;
        $cls = $m['is_internal'] ? 'is-internal' : (($m['sender_type'] === 'staff' ? 'is-staff' : 'is-customer'));
      ?>
        <div class="ns-thread-msg <?= $cls ?>">
          <div class="ns-thread-head">
            <strong><?= e($m['sender_type'] === 'customer' ? 'Customer' : ($m['is_internal'] ? 'Internal Note' : 'Support Staff')) ?></strong>
            <small class="text-muted ms-2"><?= time_ago($m['created_at']) ?></small>
          </div>
          <div class="ns-thread-body"><?= nl2br(e($m['body'])) ?></div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Attachments -->
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

    <!-- Reply box -->
    <div class="ns-reply-box">
      <h5 class="ns-h3">
        <?= $isStaff ? 'Reply (visible to customer)' : 'Reply' ?>
      </h5>
      <form method="post" class="row g-2">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="reply">
        <div class="col-12">
          <textarea name="body" class="form-control" rows="3" placeholder="Write your reply…" required></textarea>
        </div>
        <?php if ($isStaff): ?>
          <div class="col-12"><label class="form-label small"><input type="checkbox" name="internal_note" class="form-check-input me-1"> Add as internal note (hidden from customer)</label></div>
        <?php endif; ?>
        <div class="col-12"><button class="ns-btn-cta" type="submit"><i class="fa-solid fa-paper-plane me-1"></i> Post reply</button></div>
      </form>

      <!-- File upload -->
      <form method="post" enctype="multipart/form-data" class="ns-upload-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="upload">
        <label class="form-label small mb-1">Attach a file <span class="text-muted">(JPG, PNG, PDF, DOC, DOCX, TXT · max 5 MB)</span></label>
        <div class="d-flex gap-2">
          <input type="file" name="attachment" class="form-control form-control-sm" id="attachFile">
          <button class="ns-btn-ghost flex-shrink-0" type="submit"><i class="fa-solid fa-cloud-arrow-up me-1"></i>Upload</button>
        </div>
      </form>
    </div>

    <!-- Staff controls -->
    <?php if ($isStaff): ?>
      <div class="ns-staff-controls">
        <form method="post" class="row g-2 align-items-end">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="staff_update">
          <div class="col-auto">
            <label class="form-label small">Status</label>
            <select name="status" class="form-select form-select-sm">
              <option value="open">Open</option><option value="pending">Pending</option>
              <option value="in_progress">In Progress</option><option value="resolved">Resolved</option><option value="closed">Closed</option>
            </select>
          </div>
          <div class="col-auto">
            <label class="form-label small">Priority</label>
            <select name="priority" class="form-select form-select-sm">
              <option value="low">Low</option><option value="medium">Medium</option>
              <option value="high">High</option><option value="urgent">Urgent</option>
            </select>
          </div>
          <div class="col-auto">
            <label class="form-label small">Assign to</label>
            <select name="assigned_to" class="form-select form-select-sm">
              <option value="">Unassigned</option>
              <?php foreach ($staffList as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="col-auto"><button class="ns-btn-cta btn-sm" type="submit">Save</button></div>
        </form>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
