<?php
/**
 * includes/header.php
 * Customer-portal page chrome. Expects $pageTitle to be set before include.
 * Sets up the navbar; body content is rendered between this and footer.php.
 */

declare(strict_types=1);
require_once __DIR__ . '/auth.php';

$user = current_user();
$pageTitle = $pageTitle ?? 'Support Home';

// Unread notifications for the bell
$unreadNotif = 0;
if ($user) {
    try {
        $s = db()->prepare('SELECT COUNT(*) c FROM notifications WHERE user_id = :id AND is_read = 0');
        $s->execute([':id' => $user['id']]);
        $unreadNotif = (int) $s->fetch()['c'];
    } catch (Throwable $e) {}
}

// Active language
$lang = $_SESSION['lang'] ?? 'en';

function lang_label(string $l): string {
    return match($l) { 'ms' => 'Bahasa Melayu', 'zh' => '中文', default => 'English' };
}
?>
<!doctype html>
<html lang="<?= $lang === 'ms' ? 'ms' : ($lang === 'zh' ? 'zh' : 'en') ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> · NovaServe</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<link href="<?= url('assets/css/style.css') ?>" rel="stylesheet">
</head>
<body class="ns-customer">
<nav class="ns-navbar navbar navbar-expand-lg fixed-top">
  <div class="container">
    <a class="ns-brand" href="<?= url('index.php') ?>">
      <span class="ns-brand-mark"><i class="fa-solid fa-bolt-lightning"></i></span>
      <span class="ns-brand-text">Nova<span>Serve</span></span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nsNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="nsNav">
      <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
        <li class="nav-item"><a class="ns-navlink" href="<?= url('index.php') ?>">Home</a></li>
        <li class="nav-item"><a class="ns-navlink" href="<?= url('faq.php') ?>">FAQ</a></li>
        <li class="nav-item"><a class="ns-navlink" href="<?= url('knowledge.php') ?>">Knowledge</a></li>
        <li class="nav-item"><a class="ns-navlink" href="<?= url('ticket.php') ?>">Support Tickets</a></li>
        <?php if ($user): ?>
          <li class="nav-item"><a class="ns-navlink" href="<?= url('conversations.php') ?>">My Chats</a></li>
          <li class="nav-item dropdown">
            <a class="ns-navlink dropdown-toggle" href="#" data-bs-toggle="dropdown">
              <i class="fa-solid fa-bell me-1"></i>
              <?php if ($unreadNotif > 0): ?><span class="ns-badge-dot"><?= $unreadNotif ?></span><?php endif; ?>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="<?= url('notifications.php') ?>"><i class="fa-solid fa-bell me-2"></i>Notifications</a></li>
            </ul>
          </li>
          <li class="nav-item dropdown">
            <a class="ns-navlink dropdown-toggle" href="#" data-bs-toggle="dropdown">
              <i class="fa-regular fa-circle-user me-1"></i> <?= e(explode(' ', $user['name'])[0]) ?>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="<?= url('profile.php') ?>"><i class="fa-solid fa-user me-2"></i>My Profile</a></li>
              <?php if (has_role('super_admin','staff','content_mgr')): ?>
                <li><a class="dropdown-item" href="<?= url('admin/index.php') ?>"><i class="fa-solid fa-shield-halved me-2"></i>Dashboard</a></li>
              <?php endif; ?>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item text-danger" href="<?= url('logout.php') ?>"><i class="fa-solid fa-right-from-bracket me-2"></i>Sign out</a></li>
            </ul>
          </li>
        <?php else: ?>
          <li class="nav-item"><a class="ns-navlink" href="<?= url('login.php') ?>">Sign in</a></li>
          <li class="nav-item"><a class="ns-btn-cta" href="<?= url('register.php') ?>">Create account</a></li>
        <?php endif; ?>
        <li class="nav-item ms-lg-2">
          <div class="ns-lang">
            <select id="langSelect" class="form-select form-select-sm ns-lang-select" aria-label="Language">
              <option value="en" <?= $lang==='en'?'selected':'' ?>>English</option>
              <option value="ms" <?= $lang==='ms'?'selected':'' ?>>Bahasa Melayu</option>
              <option value="zh" <?= $lang==='zh'?'selected':'' ?>>中文</option>
            </select>
          </div>
        </li>
      </ul>
    </div>
  </div>
</nav>
<main class="ns-main">
<?= render_flash() ?>
