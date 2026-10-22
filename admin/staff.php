<?php
/** admin/staff.php – manage support staff accounts (super_admin). */
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('super_admin');

$pageTitle = 'Support Staff';
$pdo = db();

// Promote a customer to staff, or create a new staff user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_staff') {
    verify_csrf();
    $name = trim($_POST['name'] ?? ''); $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? ''); $pw = $_POST['password'] ?? '';
    if (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pw) < 8) {
        flash('error', 'Provide a valid name, email, and 8+ character password.');
    } else {
        try {
            $pdo->prepare('INSERT INTO users (role, name, email, phone, password, status) VALUES ("staff", :n, :e, :p, :pw, "active")')
                ->execute([':n'=>$name, ':e'=>$email, ':p'=>$phone, ':pw'=>password_hash($pw, PASSWORD_DEFAULT)]);
            log_activity($pdo->lastInsertId(), 'staff_created', "Support staff created: {$name}", client_ip());
            flash('success', 'Support staff account created.');
        } catch (Throwable $e) { flash('error', 'Could not create staff (email may already exist).'); }
    }
    redirect('admin/staff.php');
}

// Demote / disable staff
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_staff') {
    verify_csrf();
    $uid = (int) ($_POST['staff_id'] ?? 0);
    $status = in_array($_POST['status'] ?? '', ['active','disabled'], true) ? $_POST['status'] : 'active';
    $role = in_array($_POST['role'] ?? '', ['staff','super_admin'], true) ? $_POST['role'] : 'staff';
    if ($uid !== (int) current_user()['id']) {
        $pdo->prepare('UPDATE users SET status = :s, role = :r WHERE id = :id')->execute([':s'=>$status, ':r'=>$role, ':id'=>$uid]);
        log_activity(current_user()['id'], 'staff_updated', "Updated staff {$uid}", client_ip(), $uid);
        flash('success', 'Staff updated.');
    } else {
        flash('error', 'You cannot modify your own account here.');
    }
    redirect('admin/staff.php');
}

$staff = $pdo->query("SELECT u.*, (SELECT COUNT(*) FROM support_tickets t WHERE t.assigned_to = u.id) AS ticket_count
    FROM users u WHERE u.role IN ('staff','super_admin') AND u.status = 'active' ORDER BY u.name")->fetchAll();

require __DIR__ . '/../includes/admin_header.php';
?>

<div class="ns-admin-row">
  <div class="ns-card flex-fill">
    <div class="ns-card-head"><h5><i class="fa-solid fa-user-shield me-2"></i>Support Staff</h5></div>
    <div class="table-responsive">
      <table class="table ns-admin-table">
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Active Tickets</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
          <?php foreach ($staff as $s): ?>
          <tr>
            <td><strong><?= e($s['name']) ?></strong><br><small class="text-muted"><?= e($s['phone'] ?: '—') ?></small></td>
            <td><?= e($s['email']) ?></td>
            <td><span class="ns-role-badge ns-role-<?= e($s['role']) ?>"><?= e(role_label($s['role'])) ?></span></td>
            <td><span class="ns-tag-mute"><?= $s['ticket_count'] ?></span></td>
            <td class="text-end">
              <form method="post" class="d-flex gap-1 justify-content-end">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_staff"><input type="hidden" name="staff_id" value="<?= $s['id'] ?>">
                <select name="role" class="form-select form-select-sm" onchange="this.form.submit()">
                  <option value="staff" <?= $s['role']==='staff'?'selected':'' ?>>Support Staff</option>
                  <option value="super_admin" <?= $s['role']==='super_admin'?'selected':'' ?>>Super Admin</option>
                </select>
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                  <option value="active" <?= $s['status']==='active'?'selected':'' ?>>Active</option>
                  <option value="disabled" <?= $s['status']==='disabled'?'selected':'' ?>>Disabled</option>
                </select>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="ns-card mt-4">
  <div class="ns-card-head"><h5><i class="fa-solid fa-user-plus me-2"></i>Add Support Staff</h5></div>
  <form method="post" class="row g-2">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create_staff">
    <div class="col-md-3"><label class="form-label small">Name</label><input name="name" class="form-control" required></div>
    <div class="col-md-3"><label class="form-label small">Email</label><input type="email" name="email" class="form-control" required></div>
    <div class="col-md-2"><label class="form-label small">Phone</label><input name="phone" class="form-control"></div>
    <div class="col-md-3"><label class="form-label small">Password</label><input type="password" name="password" class="form-control" required minlength="8"></div>
    <div class="col-md-1 align-self-end"><button class="ns-btn-cta w-100" type="submit">Add</button></div>
  </form>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
