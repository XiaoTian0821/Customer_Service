<?php
/** notifications.php – list & mark-as-read for the logged-in user. */
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_login();
$me = current_user();
$pageTitle = 'Notifications';

// Mark one read
if (($_GET['read'] ?? '') !== '') {
    $nid = (int) $_GET['read'];
    db()->prepare('UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :u')->execute([':id' => $nid, ':u' => $me['id']]);
    redirect('notifications.php');
}

// Mark all read
if (($_GET['mark_all'] ?? '') === '1') {
    db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = :u')->execute([':u' => $me['id']]);
    flash('success', 'All notifications marked as read.');
    redirect('notifications.php');
}

$notifs = db()->prepare('SELECT * FROM notifications WHERE user_id = :u ORDER BY is_read ASC, created_at DESC');
$notifs->execute([':u' => $me['id']]);
$rows = $notifs->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<div class="container ns-page">
  <div class="ns-page-head"><div><h1 class="ns-h1">Notifications</h1>
    <p class="ns-lead">Tickets, replies, and escalations involving you.</p></div>
  <a class="ns-btn-ghost" href="<?= url('notifications.php?mark_all=1') ?>"><i class="fa-solid fa-check-double me-1"></i>Mark all read</a></div>

  <?php if (!$rows): ?>
    <div class="ns-empty text-center py-5"><i class="fa-regular fa-bell-slash ns-empty-ico"></i><p>No notifications.</p></div>
  <?php else: ?>
    <div class="ns-notif-list">
      <?php foreach ($rows as $n): ?>
        <div class="ns-notif <?= $n['is_read'] ? '' : 'is-unread' ?>">
          <div class="ns-notif-ico"><i class="fa-solid fa-bell"></i></div>
          <div class="ns-notif-body">
            <strong><?= e($n['title']) ?></strong>
            <?php if ($n['body']): ?><p class="mb-0"><?= e($n['body']) ?></p><?php endif; ?>
            <small class="text-muted"><?= time_ago($n['created_at']) ?></small>
          </div>
          <div class="ns-notif-actions">
            <?php if (!$n['is_read']): ?>
              <a class="ns-notif-read" href="<?= url('notifications.php?read=' . $n['id']) ?>">Mark read</a>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
