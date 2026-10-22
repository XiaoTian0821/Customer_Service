<?php
/** admin/notifications.php – view notifications for all users (super_admin). */
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('super_admin');

$pageTitle = 'Notifications';
$pdo = db();

// Mark-all-read for a specific user (admin action)
if (($_GET['mark_all'] ?? '') !== '') {
    $uid = (int) $_GET['mark_all'];
    $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = :u')->execute([':u' => $uid]);
    flash('success', 'All notifications for that user marked read.');
    redirect('admin/notifications.php');
}

$notifs = $pdo->query('SELECT n.*, u.name AS user_name FROM notifications n LEFT JOIN users u ON u.id = n.user_id
    ORDER BY n.is_read ASC, n.created_at DESC LIMIT 200')->fetchAll();

require __DIR__ . '/../includes/admin_header.php';
?>

<div class="ns-admin-row">
  <div class="ns-card flex-fill">
    <div class="ns-card-head"><h5><i class="fa-solid fa-bell me-2"></i>System Notifications</h5></div>
    <div class="table-responsive">
      <table class="table ns-admin-table">
        <thead><tr><th>Recipient</th><th>Type</th><th>Title</th><th>Status</th><th>Created</th></tr></thead>
        <tbody>
          <?php if (!$notifs): ?><tr><td colspan="5" class="text-center text-muted py-4">No notifications recorded.</td></tr><?php endif; ?>
          <?php foreach ($notifs as $n): ?>
          <tr class="<?= $n['is_read'] ? '' : 'ns-row-unread' ?>">
            <td><strong><?= e($n['user_name'] ?: '—') ?></strong></td>
            <td><span class="ns-tag-mute"><?= e($n['type']) ?></span></td>
            <td><?= e($n['title']) ?> <?php if ($n['body']): ?><small class="text-muted d-block"><?= e($n['body']) ?></small><?php endif; ?></td>
            <td><span class="ns-status-badge <?= $n['is_read'] ? '' : 'is-warn' ?>"><?= $n['is_read'] ? 'Read' : 'Unread' ?></span></td>
            <td><small class="text-muted"><?= time_ago($n['created_at']) ?></small></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
