<?php
/** login.php – sign-in page for all roles. */
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect(has_role('super_admin','staff','content_mgr') ? 'admin/index.php' : 'index.php');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim($_POST['email'] ?? '');
    $pw    = $_POST['password'] ?? '';
    if ($email === '' || $pw === '') {
        $errors[] = 'Please enter your email and password.';
    } elseif (strlen($email) > 160 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (!$errors && attempt_login($email, $pw)) {
        // Route by role
        $u = current_user();
        $intended = $_SESSION['intended'] ?? null;
        unset($_SESSION['intended']);
        if ($u && in_array($u['role'], ['super_admin','staff','content_mgr'], true)) {
            redirect('admin/index.php');
        }
        redirect($intended ?: 'index.php');
    }
    if (!$errors) $errors[] = 'Email or password is incorrect, or the account is disabled.';
}
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sign in · NovaServe</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<link href="<?= url('assets/css/style.css') ?>" rel="stylesheet">
</head>
<body class="ns-auth">
<div class="ns-auth-card">
  <div class="ns-auth-brand"><span class="ns-brand-mark"><i class="fa-solid fa-bolt-lightning"></i></span> Nova<span>Serve</span></div>
  <h1 class="ns-auth-title">Welcome back</h1>
  <p class="ns-auth-sub">Sign in to reach our AI assistant, your tickets, and help centre.</p>

  <?php foreach ($errors as $err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endforeach; ?>

  <form method="post" novalidate>
    <?= csrf_field() ?>
    <div class="mb-3">
      <label class="form-label">Email</label>
      <input type="email" name="email" class="form-control" value="<?= e($_POST['email'] ?? '') ?>" required autofocus>
    </div>
    <div class="mb-3">
      <label class="form-label">Password</label>
      <div class="ns-pw-wrap">
        <input type="password" name="password" class="form-control" required>
        <button type="button" class="ns-pw-toggle" aria-label="Show password"><i class="fa-solid fa-eye"></i></button>
      </div>
    </div>
    <button class="ns-btn-cta w-100" type="submit"><i class="fa-solid fa-right-to-bracket me-1"></i> Sign in</button>
  </form>

  <div class="ns-auth-alt">
    <span>New to NovaServe?</span> <a href="<?= url('register.php') ?>">Create an account</a>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>window.NS_BASE=<?= json_encode(app_base().'/') ?>;</script>
<script src="<?= url('assets/js/common.js') ?>"></script>
</body></html>
