<?php
/**
 * includes/admin_header.php – admin chrome.
 * Requires: $pageTitle, and current user gated by the calling page.
 */
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

$u = current_user();
if (!$u || !in_array($u['role'], ['super_admin','staff','content_mgr'], true)) {
    http_response_code(403);
    exit('You do not have permission to access the admin console.');
}

$pageTitle = $pageTitle ?? 'Dashboard';

$unread = 0;
try {
    $s = db()->prepare('SELECT COUNT(*) c FROM notifications WHERE user_id = :id AND is_read = 0');
    $s->execute([':id' => $u['id']]);
    $unread = (int) $s->fetch()['c'];
} catch (Throwable $e) {}

$active = basename($_SERVER['SCRIPT_NAME']);

// Nav grouped by section
$nav = [
    'Overview' => [
        ['index.php','Dashboard','fa-gauge'],
        ['conversations.php','Conversations','fa-comments'],
        ['tickets.php','Tickets','fa-ticket'],
        ['escalations.php','Escalations','fa-headset'],
    ],
    'Content' => [
        ['faqs.php','FAQs','fa-circle-question'],
        ['knowledge.php','Knowledge','fa-book'],
        ['categories.php','Categories','fa-tags'],
    ],
    'Analytics' => [
        ['analytics.php','Analytics','fa-chart-line'],
        ['logs.php','Activity Log','fa-clipboard-list'],
    ],
];
if (in_array($u['role'], ['super_admin'], true)) {
    $nav['System'] = [
        ['users.php','Users','fa-users'],
        ['ai_settings.php','AI Settings','fa-microchip'],
        ['settings.php','System Settings','fa-gear'],
        ['notifications.php','Notifications','fa-bell'],
    ];
}
// content managers can also manage content pages
if (in_array($u['role'], ['content_mgr','super_admin'], true)) {
    $nav['Content'] = array_merge($nav['Content'], []);
}
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($pageTitle) ?> · NovaServe Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<link href="<?= url('assets/css/admin.css') ?>" rel="stylesheet">
</head>
<body class="ns-admin">
<div class="ns-admin-shell">
  <aside class="ns-admin-side">
    <div class="ns-admin-brand">
      <span class="ns-brand-mark"><i class="fa-solid fa-bolt-lightning"></i></span>
      <span class="ns-brand-text">Nova<span>Serve</span></span>
    </div>
    <div class="ns-admin-role-badge"><i class="fa-solid fa-shield-halved"></i> <?= e(role_label($u['role'])) ?></div>

    <nav class="ns-admin-nav">
      <?php foreach ($nav as $group => $items): ?>
        <div class="ns-nav-group"><?= e($group) ?></div>
        <?php foreach ($items as $item):
          [$file, $label, $ico] = $item;
          $isActive = ($active === $file) || ($file === 'index.php' && $active === 'admin.php');
        ?>
        <a class="ns-admin-link <?= $isActive ? 'is-active' : '' ?>" href="<?= url('admin/' . $file) ?>">
          <i class="fa-solid <?= e($ico) ?>"></i> <?= e($label) ?>
        </a>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </nav>

    <div class="ns-admin-side-foot">
      <a href="<?= url('index.php') ?>"><i class="fa-solid fa-arrow-left me-1"></i> Back to portal</a>
      <a href="<?= url('logout.php') ?>" class="text-danger"><i class="fa-solid fa-right-from-bracket me-1"></i> Sign out</a>
    </div>
  </aside>

  <main class="ns-admin-main">
    <header class="ns-admin-topbar">
      <div class="d-flex align-items-center gap-3">
        <h1 class="ns-admin-title mb-0"><?= e($pageTitle) ?></h1>
      </div>
      <div class="d-flex align-items-center gap-3">
        <a class="ns-admin-bell" href="<?= url('admin/notifications.php') ?>" title="Notifications">
          <i class="fa-solid fa-bell"></i>
          <?php if ($unread > 0): ?><span class="ns-admin-bell-dot"><?= $unread ?></span><?php endif; ?>
        </a>
        <div class="ns-admin-user">
          <span class="ns-avatar"><?= e(ucwords(explode(' ', $u['name'])[0])[0]) ?></span>
          <div class="ns-admin-user-meta"><strong><?= e($u['name']) ?></strong><small><?= e(role_label($u['role'])) ?></small></div>
        </div>
      </div>
    </header>
    <div class="ns-admin-content">
      <?= render_flash() ?>
