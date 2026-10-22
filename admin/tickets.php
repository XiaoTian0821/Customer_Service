<?php
/** admin/tickets.php – manage all support tickets (staff + super_admin). */
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('super_admin','staff');

$pageTitle = 'Support Tickets';
$pdo = db();

// Status / assign update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_ticket') {
    verify_csrf();
    $tid = (int) ($_POST['ticket_id'] ?? 0);
    $status = in_array($_POST['status'] ?? '', ['open','pending','in_progress','resolved','closed'], true) ? $_POST['status'] : null;
    $prio = in_array($_POST['priority'] ?? '', ['low','medium','high','urgent'], true) ? $_POST['priority'] : null;
    $assign = (int) ($_POST['assigned_to'] ?? 0);
    $up = []; $params = [':id' => $tid];
    if ($status) { $up[] = 'status = :st'; $params[':st'] = $status; }
    if ($prio)   { $up[] = 'priority = :pr'; $params[':pr'] = $prio; }
    if ($assign > 0) { $up[] = 'assigned_to = :a'; $params[':a'] = $assign; }
    if ($up) {
        $pdo->prepare('UPDATE support_tickets SET ' . implode(', ', $up) . ' WHERE id = :id')->execute($params);
        log_activity(current_user()['id'], 'ticket_updated', "Updated ticket {$tid}", client_ip(), $tid);
        flash('success', 'Ticket updated.');
    }
    redirect('admin/tickets.php?' . ($_GET['status'] ?? ''));
}

// Search + filter
$q = trim($_GET['q'] ?? '');
$statusFilter = in_array($_GET['status'] ?? '', ['open','pending','in_progress','resolved','closed'], true) ? $_GET['status'] : '';

$staff = $pdo->query("SELECT id, name FROM users WHERE role IN ('staff','super_admin') AND status = 'active' ORDER BY name")->fetchAll();

$sql = 'SELECT t.*, u.name AS customer_name, u2.name AS assigned_name, c.name AS category_name,
               (SELECT COUNT(*) FROM ticket_messages tm WHERE tm.ticket_id = t.id AND tm.is_internal = 0) AS msg_count
        FROM support_tickets t
        LEFT JOIN users u ON u.id = t.customer_id
        LEFT JOIN users u2 ON u2.id = t.assigned_to
        LEFT JOIN categories c ON c.id = t.category_id
        WHERE 1=1';
$params = [];
if ($statusFilter !== '') { $sql .= ' AND t.status = :st'; $params[':st'] = $statusFilter; }
if ($q !== '') {
    $sql .= ' AND (t.ticket_no LIKE :q OR t.subject LIKE :q2 OR u.name LIKE :q3 OR u.email LIKE :q4)';
    $params[':q'] = "%{$q}%"; $params[':q2'] = "%{$q}%"; $params[':q3'] = "%{$q}%"; $params[':q4'] = "%{$q}%";
}
$sql .= ' ORDER BY t.updated_at DESC LIMIT 200';
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$tickets = $stmt->fetchAll();

// Status counts for quick filter chips
$counts = ['all' => 0];
foreach ($pdo->query('SELECT status, COUNT(*) c FROM support_tickets GROUP BY status')->fetchAll() as $r) {
    $counts[$r['status']] = (int)$r['c']; $counts['all'] += (int)$r['c'];
}

require __DIR__ . '/../includes/admin_header.php';
?>

<div class="ns-admin-row">
  <div class="ns-card flex-fill">
    <div class="ns-card-head">
      <div class="d-flex flex-wrap gap-2 align-items-center">
        <h5 class="me-3"><i class="fa-solid fa-ticket me-2"></i>Support Tickets</h5>
        <a class="ns-filter-chip <?= $statusFilter==='' ? 'is-active' : '' ?>" href="<?= url('admin/tickets.php') ?>">All (<?= $counts['all'] ?>)</a>
        <?php foreach (['open','pending','in_progress','resolved','closed'] as $s): ?>
          <a class="ns-filter-chip <?= $statusFilter===$s ? 'is-active' : '' ?>" href="<?= url('admin/tickets.php?status=' . $s) ?>"><?= e(ucfirst(str_replace('_',' ',$s))) ?> (<?= $counts[$s] ?? 0 ?>)</a>
        <?php endforeach; ?>
      </div>
      <form method="get" class="d-flex gap-2">
        <?php if ($statusFilter): ?><input type="hidden" name="status" value="<?= e($statusFilter) ?>"><?php endif; ?>
        <input type="search" name="q" class="form-control form-control-sm" placeholder="Search ticket no, subject, customer…" value="<?= e($q) ?>">
        <button class="btn btn-sm ns-btn-cta" type="submit"><i class="fa-solid fa-magnifying-glass"></i></button>
      </form>
    </div>
    <div class="table-responsive">
      <table class="table ns-admin-table">
        <thead><tr><th>Ticket</th><th>Customer</th><th>Priority</th><th>Status</th><th>Assigned</th><th class="text-end">Updated</th></tr></thead>
        <tbody>
          <?php if (!$tickets): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">No tickets found.</td></tr>
          <?php endif; ?>
          <?php foreach ($tickets as $t): ?>
          <tr>
            <td><a class="ns-link" href="<?= url('admin/ticket_view.php?id=' . $t['id']) ?>"><strong><?= e($t['ticket_no']) ?></strong><br><small class="text-muted"><?= e($t['subject']) ?></small></a></td>
            <td><?= e($t['customer_name'] ?: '—') ?><br><small class="text-muted"><?= e($t['category_name'] ?: 'General') ?></small></td>
            <td><span class="prio-<?= $t['priority'] ?>"><?= e(ucfirst($t['priority'])) ?></span></td>
            <td>
              <form method="post" class="d-flex gap-1">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_ticket"><input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
                <select name="status" class="form-select form-select-sm ns-status-sel" onchange="this.form.submit()">
                  <?php foreach (['open','pending','in_progress','resolved','closed'] as $s): ?><option value="<?= $s ?>" <?= $t['status']===$s?'selected':'' ?>><?= e(ucfirst(str_replace('_',' ',$s))) ?></option><?php endforeach; ?>
                </select>
              </form>
            </td>
            <td>
              <form method="post" class="d-flex gap-1">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_ticket"><input type="hidden" name="ticket_id" value="<?= $t['id'] ?>"><input type="hidden" name="status" value="<?= e($t['status']) ?>">
                <select name="assigned_to" class="form-select form-select-sm" onchange="this.form.submit()">
                  <option value="0">Unassigned</option>
                  <?php foreach ($staff as $s): ?><option value="<?= $s['id'] ?>" <?= (int)$t['assigned_to']===(int)$s['id']?'selected':'' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
                </select>
              </form>
            </td>
            <td class="text-end"><small class="text-muted"><?= time_ago($t['updated_at']) ?></small></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
