<?php
/** admin/users.php – manage users (super_admin only). */
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('super_admin');

$pageTitle = 'User Management';
$pdo = db();

// Create
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_user') {
    verify_csrf();
    $name = trim($_POST['name'] ?? ''); $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? ''); $role = $_POST['role'] ?? 'customer'; $pw = $_POST['password'] ?? '';
    if (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pw) < 8) {
        flash('error', 'Please provide a valid name, email, and an 8+ character password.');
    } else {
        $validRoles = ['customer','staff','content_mgr','super_admin'];
        if (!in_array($role, $validRoles, true)) $role = 'customer';
        try {
            $pdo->prepare('INSERT INTO users (role, name, email, phone, password, status) VALUES (:r, :n, :e, :p, :pw, "active")')
                ->execute([':r'=>$role, ':n'=>$name, ':e'=>$email, ':p'=>$phone, ':pw'=>password_hash($pw, PASSWORD_DEFAULT)]);
            log_activity($pdo->lastInsertId(), 'customer_created', "User {$name} created by admin");
            flash('success', 'User created.');
        } catch (Throwable $e) { flash('error', 'Could not create user (email may already exist).'); }
    }
    redirect('admin/users.php');
}

// Update role/status/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_user') {
    verify_csrf();
    $uid = (int) ($_POST['user_id'] ?? 0);
    if ($uid === (int) $pdo->query('SELECT id FROM users WHERE email = ' . $pdo->quote(current_user()['email']))->fetch()['id']) {
        flash('error', 'You cannot change your own account.');
    } else {
        $role = $_POST['role'] ?? ''; $status = $_POST['status'] ?? 'active';
        if (in_array($role, ['customer','staff','content_mgr','super_admin'], true)) {
            $pdo->prepare('UPDATE users SET role = :r, status = :s WHERE id = :id')->execute([':r'=>$role, ':s'=>$status, ':id'=>$uid]);
            log_activity(current_user()['id'], 'user_updated', "Updated user {$uid}");
            flash('success', 'User updated.');
        }
    }
    redirect('admin/users.php');
}

// Delete
if (($_GET['delete'] ?? '') !== '') {
    $uid = (int) $_GET['delete'];
    if ($uid !== (int) current_user()['id']) {
        $pdo->prepare('DELETE FROM users WHERE id = :id')->execute([':id' => $uid]);
        log_activity(current_user()['id'], 'user_deleted', "Deleted user {$uid}");
        flash('success', 'User deleted.');
    }
    redirect('admin/users.php');
}

// List with search
$q = trim($_GET['q'] ?? '');
$sql = 'SELECT * FROM users';
$w = []; $p = [];
if ($q !== '') { $sql .= ' WHERE name LIKE :q OR email LIKE :q'; $p[':q'] = "%{$q}%"; }
$sql .= ' ORDER BY created_at DESC LIMIT 200';
$stmt = $pdo->prepare($sql); $stmt->execute($p);
$users = $stmt->fetchAll();

require __DIR__ . '/../includes/admin_header.php';
?>

<div class="ns-admin-row">
  <div class="ns-card flex-fill">
    <div class="ns-card-head d-flex justify-content-between align-items-center">
      <h5><i class="fa-solid fa-users me-2"></i>All Users</h5>
      <form method="get" class="d-flex gap-2">
        <input type="search" name="q" class="form-control form-control-sm" placeholder="Search name or email…" value="<?= e($q) ?>">
        <button class="btn btn-sm ns-btn-cta" type="submit"><i class="fa-solid fa-magnifying-glass"></i></button>
      </form>
    </div>
    <div class="table-responsive">
      <table class="table ns-admin-table">
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last Login</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
          <?php foreach ($users as $usr): ?>
          <tr>
            <td><strong><?= e($usr['name']) ?></strong><br><small class="text-muted"><?= e($usr['phone'] ?: '—') ?></small></td>
            <td><?= e($usr['email']) ?></td>
            <td><span class="ns-role-badge ns-role-<?= e($usr['role']) ?>"><?= e(role_label($usr['role'])) ?></span></td>
            <td><span class="ns-status-badge"><?= e($usr['status']) ?></span></td>
            <td><small class="text-muted"><?= $usr['last_login_at'] ? fmt_date($usr['last_login_at']) : '—' ?></small></td>
            <td class="text-end">
              <a class="ns-admin-act" href="?delete=<?= $usr['id'] ?>" onclick="return confirm('Delete this user? This cannot be undone.')"><i class="fa-solid fa-trash text-danger"></i></a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="ns-card mt-4">
  <div class="ns-card-head"><h5><i class="fa-solid fa-user-plus me-2"></i>Create User</h5></div>
  <form method="post" class="row g-2">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create_user">
    <div class="col-md-4"><label class="form-label small">Full name</label><input name="name" class="form-control" required></div>
    <div class="col-md-4"><label class="form-label small">Email</label><input type="email" name="email" class="form-control" required></div>
    <div class="col-md-4"><label class="form-label small">Phone</label><input name="phone" class="form-control"></div>
    <div class="col-md-4"><label class="form-label small">Role</label>
      <select name="role" class="form-select"><option value="customer">Customer</option><option value="staff">Support Staff</option><option value="content_mgr">Content Manager</option><option value="super_admin">Super Admin</option></select></div>
    <div class="col-md-4"><label class="form-label small">Password</label><input type="password" name="password" class="form-control" required minlength="8"></div>
    <div class="col-md-4"><button class="ns-btn-cta" type="submit">Create user</button></div>
  </form>
</div>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
