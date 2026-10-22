<?php
/** register.php – create a new customer account. */
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) redirect('index.php');

$errors = [];
$old = [
    'name'    => $_POST['name'] ?? '',
    'email'   => $_POST['email'] ?? '',
    'phone'   => $_POST['phone'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name  = trim($old['name']);
    $email = strtolower(trim($old['email']));
    $phone = trim($old['phone']);
    $pw    = $_POST['password'] ?? '';
    $pw2   = $_POST['password2'] ?? '';

    if (mb_strlen($name) < 2) $errors[] = 'Please enter your full name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    if (strlen($pw) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($pw !== $pw2) $errors[] = 'Passwords do not match.';

    if (!$errors) {
        try {
            $check = db()->prepare('SELECT id FROM users WHERE email = :e LIMIT 1');
            $check->execute([':e' => $email]);
            if ($check->fetch()) {
                $errors[] = 'That email address is already registered.';
            } else {
                $hash = password_hash($pw, PASSWORD_DEFAULT);
                $stmt = db()->prepare(
                    'INSERT INTO users (role, name, email, phone, password, status)
                     VALUES ("customer", :n, :e, :p, :pw, "active")'
                );
                $stmt->execute([':n' => $name, ':e' => $email, ':p' => $phone, ':pw' => $hash]);
                log_activity((int) db()->lastInsertId(), 'customer_created', "New customer registered: {$name}");
                flash('success', 'Your account was created. You can now sign in and chat with our AI assistant.');
                redirect('login.php');
            }
        } catch (Throwable $e) {
            $errors[] = 'Registration failed: ' . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Create account · NovaServe</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<link href="<?= url('assets/css/style.css') ?>" rel="stylesheet">
</head>
<body class="ns-auth">
<div class="ns-auth-card">
  <div class="ns-auth-brand"><span class="ns-brand-mark"><i class="fa-solid fa-bolt-lightning"></i></span> Nova<span>Serve</span></div>
  <h1 class="ns-auth-title">Create your account</h1>
  <p class="ns-auth-sub">Join NovaServe to chat with our AI assistant, open support tickets, and track your service.</p>

  <?php foreach ($errors as $err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endforeach; ?>

  <form method="post" novalidate>
    <?= csrf_field() ?>
    <div class="mb-3">
      <label class="form-label">Full name</label>
      <input type="text" name="name" class="form-control" value="<?= e($old['name']) ?>" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Email</label>
      <input type="email" name="email" class="form-control" value="<?= e($old['email']) ?>" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Phone <span class="text-muted">(optional)</span></label>
      <input type="tel" name="phone" class="form-control" value="<?= e($old['phone']) ?>">
    </div>
    <div class="row">
      <div class="col-md-6 mb-3">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" required minlength="8">
      </div>
      <div class="col-md-6 mb-3">
        <label class="form-label">Confirm</label>
        <input type="password" name="password2" class="form-control" required minlength="8">
      </div>
    </div>
    <button class="ns-btn-cta w-100" type="submit"><i class="fa-solid fa-user-plus me-1"></i> Create account</button>
  </form>

  <div class="ns-auth-alt">
    <span>Already have an account?</span> <a href="<?= url('login.php') ?>">Sign in</a>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>window.NS_BASE=<?= json_encode(app_base().'/') ?>;</script>
<script src="<?= url('assets/js/common.js') ?>"></script>
</body></html>
