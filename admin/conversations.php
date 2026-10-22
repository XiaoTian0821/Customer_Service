<?php
/** admin/conversations.php – monitor AI conversations (staff + super_admin). */
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('super_admin','staff');

$pageTitle = 'Conversations';
$pdo = db();

// Detail view
$detail = null;
$detailMessages = [];
$detailEsc = [];
$detailId = (int) ($_GET['view'] ?? 0);
if ($detailId > 0) {
    $s = $pdo->prepare('SELECT cv.*, u.name AS customer_name FROM conversations cv LEFT JOIN users u ON u.id = cv.customer_id WHERE cv.id = :id');
    $s->execute([':id' => $detailId]);
    $detail = $s->fetch();
    if ($detail) {
        $mStmt = $pdo->prepare('SELECT m.*, u.name AS sender_name FROM conversation_messages m LEFT JOIN users u ON u.id = m.sender_id WHERE m.conversation_id = :cid ORDER BY m.created_at, m.id');
        $mStmt->execute([':cid' => $detailId]);
        $detailMessages = $mStmt->fetchAll();
        $esc = $pdo->prepare('SELECT * FROM escalations WHERE conversation_id = :cid ORDER BY requested_at DESC');
        $esc->execute([':cid' => $detailId]);
        $detailEsc = $esc->fetchAll();
    } else { redirect('admin/conversations.php'); }
}

// List
$filter = in_array($_GET['status'] ?? '', ['active','ai_resolved','human_resolved','escalated','closed'], true) ? $_GET['status'] : '';
$sql = 'SELECT cv.*, u.name AS customer_name FROM conversations cv LEFT JOIN users u ON u.id = cv.customer_id WHERE 1=1';
$params = [];
if ($filter !== '') { $sql .= ' AND cv.status = :st'; $params[':st'] = $filter; }
$sql .= ' ORDER BY cv.updated_at DESC LIMIT 200';
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$convs = $stmt->fetchAll();

// Takeover action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'takeover') {
    verify_csrf();
    $cid = (int) ($_POST['conversation_id'] ?? 0);
    $pdo->prepare("UPDATE conversations SET status = 'escalated', human_status = 'taken_over' WHERE id = :id")->execute([':id' => $cid]);
    $pdo->prepare('UPDATE escalations SET status = "taken_over", taken_over_by = :by, resolved_at = NOW() WHERE conversation_id = :cid AND status = "pending"')
        ->execute([':by' => current_user()['id'], ':cid' => $cid]);
    // system message
    $pdo->prepare("INSERT INTO conversation_messages (conversation_id, sender_type, content) VALUES (:cid, 'system', :c)")
        ->execute([':cid' => $cid, ':c' => 'A support representative has taken over this conversation.']);
    log_activity(current_user()['id'], 'human_takeover', "Took over conversation {$cid}", client_ip(), $cid);
    flash('success', 'You have taken over this conversation. It is now marked as escalated.');
    redirect('admin/conversations.php?view=' . $cid);
}

$nsAdminScripts = ['conversations.js'];
$nsInlineScript = '
window.NS_CONV = { base: ' . json_encode(app_base()) . ', csrf: ' . json_encode(csrf_token()) . ' };
';

require __DIR__ . '/../includes/admin_header.php';

if ($detail): ?>
<div class="ns-admin-row">
  <nav class="ns-breadcrumb"><a href="<?= url('admin/conversations.php') ?>">Conversations</a><i class="fa-solid fa-chevron-right"></i><span>#<?= $detail['id'] ?></span></nav>
  <div class="col-12 col-xl-8">
    <div class="ns-card">
      <div class="ns-card-head">
        <h5><i class="fa-solid fa-comments me-2"></i>Transcript · <?= e($detail['customer_name'] ?: 'Unknown') ?></h5>
        <div class="ns-conv-meta d-flex flex-wrap gap-2">
          <span class="ns-status-badge"><?= e(str_replace('_',' ', $detail['status'])) ?></span>
          <?php if ($detail['intent']): ?><span class="ns-tag-mute">Intent: <?= e(ucfirst(str_replace('_',' ',$detail['intent']))) ?></span><?php endif; ?>
          <?php if ($detail['sentiment']): ?><span class="ns-tag-mute">Sentiment: <?= e(ucfirst($detail['sentiment'])) ?></span><?php endif; ?>
          <?php if ($detail['confidence']): ?><span class="ns-tag-mute">Confidence: <?= round((float)$detail['confidence']*100) ?>%</span><?php endif; ?>
          <?php if ($detail['ai_provider']): ?><span class="ns-tag-mute">Provider: <?= e($detail['ai_provider']) ?></span><?php endif; ?>
        </div>
      </div>
      <div class="ns-conv-transcript">
        <?php foreach ($detailMessages as $m):
          $cls = $m['sender_type']==='customer' ? 'is-customer' : ($m['sender_type']==='ai' ? 'is-ai' : ($m['sender_type']==='staff' ? 'is-staff' : 'is-system'));
        ?>
        <div class="ns-transcript-msg <?= $cls ?>">
          <div class="ns-transcript-head">
            <strong><?= $m['sender_type']==='customer' ? 'Customer' : ($m['sender_type']==='ai' ? 'Nova (AI)' : ($m['sender_type']==='staff' ? 'Support' : 'System')) ?></strong>
            <?php if ($m['sender_name'] && $m['sender_type']==='staff'): ?><span class="text-muted">· <?= e($m['sender_name']) ?></span><?php endif; ?>
            <small class="text-muted ms-auto"><?= time_ago($m['created_at']) ?></small>
          </div>
          <div class="ns-transcript-body"><?= nl2br(e($m['content'])) ?></div>
          <?php if ($m['sender_type']==='ai' && $m['confidence']): ?>
            <div class="ns-transcript-foot"><small class="text-muted">Confidence <?= round((float)$m['confidence']*100) ?>% · <?= e($m['ai_provider'] ?: 'AI') ?></small></div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php if ($detail['status'] === 'escalated' || ($detail['human_status'] ?? '') === 'pending'): ?>
        <div class="ns-takeover-box">
          <form method="post" class="d-flex gap-2">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="takeover"><input type="hidden" name="conversation_id" value="<?= $detail['id'] ?>">
            <button class="ns-btn-cta" type="submit"><i class="fa-solid fa-hand-scissors me-1"></i> Take over this conversation</button>
          </form>
        </div>
      <?php endif; ?>
    </div>
  </div>
  <div class="col-12 col-xl-4">
    <div class="ns-card">
      <div class="ns-card-head"><h5><i class="fa-solid fa-headset me-2"></i>Escalations</h5></div>
      <?php if ($detailEsc): foreach ($detailEsc as $e2): ?>
        <div class="ns-esc-item">
          <div class="d-flex justify-content-between"><strong><?= e(ucfirst($e2['status'])) ?></strong><small><?= time_ago($e2['requested_at']) ?></small></div>
          <?php if ($e2['reason']): ?><small class="text-muted"><?= e($e2['reason']) ?></small><?php endif; ?>
        </div>
      <?php endforeach; ?>
      <?php else: ?><p class="text-muted small mb-0">No escalation records for this conversation.</p><?php endif; ?>
    </div>
  </div>
</div>
<?php else: ?>
<div class="ns-admin-row">
  <div class="ns-card flex-fill">
    <div class="ns-card-head">
      <h5><i class="fa-solid fa-comments me-2"></i>Conversations</h5>
      <div class="d-flex gap-2">
        <a class="ns-filter-chip <?= $filter==='' ? 'is-active' : '' ?>" href="<?= url('admin/conversations.php') ?>">All</a>
        <?php foreach (['active','escalated','ai_resolved','human_resolved','closed'] as $s): ?>
          <a class="ns-filter-chip <?= $filter===$s ? 'is-active' : '' ?>" href="<?= url('admin/conversations.php?status=' . $s) ?>"><?= e(ucfirst(str_replace('_',' ',$s))) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="table-responsive">
      <table class="table ns-admin-table">
        <thead><tr><th>Customer</th><th>Intent</th><th>Sentiment</th><th>Confidence</th><th>Status</th><th class="text-end">Updated</th></tr></thead>
        <tbody>
          <?php if (!$convs): ?><tr><td colspan="6" class="text-center text-muted py-4">No conversations yet.</td></tr><?php endif; ?>
          <?php foreach ($convs as $c): ?>
          <tr>
            <td><a class="ns-link" href="<?= url('admin/conversations.php?view=' . $c['id']) ?>"><strong>#<?= $c['id'] ?></strong><br><small class="text-muted"><?= e($c['customer_name'] ?: '—') ?></small></a></td>
            <td><span class="ns-tag-mute"><?= e($c['intent'] ? ucfirst(str_replace('_',' ',$c['intent'])) : '—') ?></span></td>
            <td><span class="ns-sent-<?= e($c['sentiment'] ?? 'neutral') ?>"><?= e($c['sentiment'] ? ucfirst($c['sentiment']) : '—') ?></span></td>
            <td><?= $c['confidence'] !== null ? round((float)$c['confidence']*100) . '%' : '—' ?></td>
            <td><span class="ns-status-badge <?= $c['status']==='escalated'?'is-warn':'' ?>"><?= e(str_replace('_',' ', $c['status'])) ?></span></td>
            <td class="text-end"><small class="text-muted"><?= time_ago($c['updated_at']) ?></small></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
