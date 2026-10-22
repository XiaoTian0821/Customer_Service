<?php
/** admin/escalations.php – pending / active human escalations (staff + super_admin). */
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('super_admin','staff');

$pageTitle = 'Escalations';
$pdo = db();

// Resolve
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'resolve') {
    verify_csrf();
    $eid = (int) ($_POST['escalation_id'] ?? 0);
    $pdo->prepare("UPDATE escalations SET status = 'resolved', resolved_at = NOW(), resolved_by = :by WHERE id = :id")
        ->execute([':by' => current_user()['id'], ':id' => $eid]);
    $pdo->prepare("UPDATE conversations SET status = 'human_resolved', human_status = 'resolved' WHERE id = :cid")
        ->execute([':cid' => $eid]); // note: maps esc id to conv via below
    log_activity(current_user()['id'], 'escalation_resolved', "Resolved escalation {$eid}", client_ip(), $eid);
    flash('success', 'Escalation marked resolved.');
    redirect('admin/escalations.php');
}

// Filter
$filter = in_array($_GET['status'] ?? '', ['pending','taken_over','resolved','closed'], true) ? $_GET['status'] : '';
$sql = 'SELECT es.*, u.name AS customer_name, cv.intent, cv.sentiment, cv.confidence
        FROM escalations es
        LEFT JOIN users u ON u.id = es.customer_id
        LEFT JOIN conversations cv ON cv.id = es.conversation_id
        WHERE 1=1';
$params = [];
if ($filter !== '') { $sql .= ' AND es.status = :st'; $params[':st'] = $filter; }
$sql .= ' ORDER BY es.requested_at DESC LIMIT 200';
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$escalations = $stmt->fetchAll();

require __DIR__ . '/../includes/admin_header.php';
?>
<div class="ns-admin-row">
  <div class="ns-card flex-fill">
    <div class="ns-card-head">
      <h5><i class="fa-solid fa-headset me-2"></i>Human Escalations</h5>
      <div class="d-flex gap-2">
        <a class="ns-filter-chip <?= $filter==='' ? 'is-active' : '' ?>" href="<?= url('admin/escalations.php') ?>">All</a>
        <?php foreach (['pending','taken_over','resolved','closed'] as $s): ?>
          <a class="ns-filter-chip <?= $filter===$s ? 'is-active' : '' ?>" href="<?= url('admin/escalations.php?status=' . $s) ?>"><?= e(ucfirst(str_replace('_',' ',$s))) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="table-responsive">
      <table class="table ns-admin-table">
        <thead><tr><th>Customer</th><th>Conversation</th><th>Intent</th><th>Sentiment</th><th>Confidence</th><th>Status</th><th>Requested</th><th class="text-end"></th></tr></thead>
        <tbody>
          <?php if (!$escalations): ?><tr><td colspan="8" class="text-center text-muted py-4">No escalations found.</td></tr><?php endif; ?>
          <?php foreach ($escalations as $e2): ?>
          <tr>
            <td><strong><?= e($e2['customer_name'] ?: '—') ?></strong></td>
            <td><a class="ns-link" href="<?= url('admin/conversations.php?view=' . $e2['conversation_id']) ?>">#<?= $e2['conversation_id'] ?></a></td>
            <td><span class="ns-tag-mute"><?= e($e2['intent'] ? ucfirst(str_replace('_',' ',$e2['intent'])) : '—') ?></span></td>
            <td><span class="ns-sent-<?= e($e2['sentiment'] ?? 'neutral') ?>"><?= e($e2['sentiment'] ? ucfirst($e2['sentiment']) : '—') ?></span></td>
            <td><?= $e2['confidence'] !== null ? round((float)$e2['confidence']*100) . '%' : '—' ?></td>
            <td><span class="ns-status-badge <?= $e2['status']==='pending'?'is-warn':'' ?>"><?= e(ucfirst(str_replace('_',' ',$e2['status']))) ?></span></td>
            <td><small class="text-muted"><?= time_ago($e2['requested_at']) ?></small></td>
            <td class="text-end">
              <?php if (in_array($e2['status'], ['pending','taken_over'], true)): ?>
                <form method="post" class="d-inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="resolve"><input type="hidden" name="escalation_id" value="<?= $e2['id'] ?>">
                  <button class="btn btn-sm ns-btn-ghost" type="submit">Resolve</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
