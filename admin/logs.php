<?php
/** admin/logs.php – audit / activity log viewer (super_admin). */
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('super_admin');

$pageTitle = 'Activity Log';
$pdo = db();

$actionFilter = $_GET['action'] ?? '';
$q = trim($_GET['q'] ?? '');
$limit = 200;

$sql = 'SELECT l.*, u.name AS actor_name FROM activity_log l LEFT JOIN users u ON u.id = l.actor_id WHERE 1=1';
$params = [];
if ($actionFilter !== '') { $sql .= ' AND l.action = :act'; $params[':act'] = $actionFilter; }
if ($q !== '') { $sql .= ' AND l.description LIKE :q'; $params[':q'] = "%{$q}%"; }
$sql .= ' ORDER BY l.created_at DESC, l.id DESC LIMIT ' . $limit;
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$logs = $stmt->fetchAll();

$distinctActions = $pdo->query('SELECT DISTINCT action FROM activity_log ORDER BY action')->fetchAll(PDO::FETCH_COLUMN);

require __DIR__ . '/../includes/admin_header.php';
?>

<div class="ns-admin-row">
  <div class="ns-card flex-fill">
    <div class="ns-card-head">
      <h5><i class="fa-solid fa-clipboard-list me-2"></i>Activity Log</h5>
      <form method="get" class="d-flex gap-2">
        <input type="search" name="q" class="form-control form-control-sm" placeholder="Search descriptions…" value="<?= e($q) ?>">
        <select name="action" class="form-select form-select-sm">
          <option value="">All actions</option>
          <?php foreach ($distinctActions as $a): ?><option value="<?= e($a) ?>" <?= $actionFilter===$a?'selected':'' ?>><?= e($a) ?></option><?php endforeach; ?>
        </select>
        <button class="btn btn-sm ns-btn-cta" type="submit">Filter</button>
      </form>
    </div>
    <div class="table-responsive">
      <table class="table ns-admin-table">
        <thead><tr><th>Time</th><th>Actor</th><th>Action</th><th>Description</th><th>IP</th></tr></thead>
        <tbody>
          <?php if (!$logs): ?><tr><td colspan="5" class="text-center text-muted py-4">No log entries match your filter.</td></tr><?php endif; ?>
          <?php foreach ($logs as $l): ?>
          <tr>
            <td><small class="text-muted"><?= fmt_date($l['created_at']) ?></small></td>
            <td><?= e($l['actor_name'] ?: 'System') ?></td>
            <td><span class="ns-tag-mute"><?= e($l['action']) ?></span></td>
            <td><small><?= e($l['description']) ?></small></td>
            <td><small class="text-muted"><?= e($l['ip_address'] ?: '—') ?></small></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
