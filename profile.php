<?php
/** profile.php – customer profile view & update. */
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_login();
$me = current_user();

$pageTitle = 'My Profile';

// Stats
$counts = [];
try {
    $counts['tickets'] = (int) db()->prepare('SELECT COUNT(*) c FROM support_tickets WHERE customer_id = :c')->execute([':c'=>$me['id']]) ? '' : '';
} catch (Throwable $e) {}

$t = db()->prepare('SELECT COUNT(*) c FROM support_tickets WHERE customer_id = :c');
$t->execute([':c' => $me['id']]);
$ticketCount = (int) $t->fetch()['c'];
$c = db()->prepare('SELECT COUNT(*) c FROM conversations WHERE customer_id = :c');
$c->execute([':c' => $me['id']]);
$convCount = (int) $c->fetch()['c'];

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    verify_csrf();
    $name  = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $pw    = $_POST['password'] ?? '';
    $pw2   = $_POST['password2'] ?? '';

    if (mb_strlen($name) < 2) $errors[] = 'Name must be at least 2 characters.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email.';

    if (!$errors) {
        try {
            // Check email not taken by another user
            $chk = db()->prepare('SELECT id FROM users WHERE email = :e AND id != :id');
            $chk->execute([':e' => $email, ':id' => $me['id']]);
            if ($chk->fetch()) {
                $errors[] = 'That email is already in use by another account.';
            } else {
                $upd = ['name' => $name, 'email' => $email, 'phone' => $phone];
                $db = db();
                if ($pw !== '' ) {
                    if (strlen($pw) < 8) { $errors[] = 'New password must be at least 8 characters.'; }
                    elseif ($pw !== $pw2) { $errors[] = 'Passwords do not match.'; }
                }
                if (!$errors) {
                    if ($pw !== '') $upd['password'] = password_hash($pw, PASSWORD_DEFAULT);
                    $fields = 'name = :name, email = :email, phone = :phone' . ($upd['password'] ? ', password = :password' : '');
                    $params = [':name' => $name, ':email' => $email, ':phone' => $phone];
                    if ($upd['password']) $params[':password'] = $upd['password'];
                    $db->prepare("UPDATE users SET {$fields} WHERE id = :id")->execute($params + [':id' => $me['id']]);
                    log_activity($me['id'], 'profile_updated', 'Profile updated', client_ip());
                    // refresh
                    $_SESSION['user_id'] = $me['id']; // keep session; force re-fetch
                    unset($GLOBALS['__user_cache']);
                    flash('success', 'Profile updated.');
                    redirect('profile.php');
                }
            }
        } catch (Throwable $e) {
            $errors[] = 'Update failed: ' . $e->getMessage();
        }
    }
}

require __DIR__ . '/includes/header.php';
?>
<div class="container ns-page">
  <div class="ns-page-head"><div><h1 class="ns-h1">My Profile</h1>
    <p class="ns-lead">Manage your details, contact info, and password.</p></div></div>

  <div class="row g-4">
    <div class="col-lg-4">
      <div class="ns-profile-card text-center">
        <div class="ns-avatar-lg"><?= e(ucwords(explode(' ', $me['name'])[0]))[0] ?></div>
        <h5 class="mt-2"><?= e($me['name']) ?></h5>
        <span class="ns-tag-mute"><?= e(role_label($me['role'])) ?></span>
        <div class="ns-profile-stats mt-3">
          <div><strong><?= $ticketCount ?></strong><small>Tickets</small></div>
          <div><strong><?= $convCount ?></strong><small>Chats</small></div>
          <div><strong><?= e(fmt_date($me['created_at'], 'M Y')) ?></strong><small>Joined</small></div>
        </div>
        <small class="text-muted d-block mt-3"><i class="fa-regular fa-clock me-1"></i>Last login: <?= $me['last_login_at'] ? fmt_date($me['last_login_at']) : '—' ?></small>
      </div>
    </div>

    <div class="col-lg-8">
      <div class="ns-form-card">
        <?php foreach ($errors as $err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endforeach; ?>
        <form method="post" class="row g-3">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="update">
          <div class="col-md-6"><label class="form-label">Full name</label><input name="name" class="form-control" value="<?= e($me['name']) ?>" required></div>
          <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= e($me['email']) ?>" required></div>
          <div class="col-12"><label class="form-label">Phone</label><input name="phone" class="form-control" value="<?= e($me['phone'] ?? '') ?>"></div>
          <hr>
          <div class="col-md-4"><label class="form-label">New password</label><input type="password" name="password" class="form-control" placeholder="Leave blank to keep"></div>
          <div class="col-md-4"><label class="form-label">Confirm</label><input type="password" name="password2" class="form-control"></div>
          <div class="col-12"><button class="ns-btn-cta" type="submit">Save changes</button></div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
