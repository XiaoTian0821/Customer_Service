<?php
/** conversations.php – a customer's chat history. */
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_login();
$me = current_user();
$pageTitle = 'My Conversations';

$convs = db()->prepare('SELECT * FROM conversations WHERE customer_id = :c ORDER BY updated_at DESC');
$convs->execute([':c' => $me['id']]);
$rows = $convs->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<div class="container ns-page">
  <div class="ns-page-head"><div><h1 class="ns-h1">My Conversations</h1>
    <p class="ns-lead">Your chat history with Nova. Open any conversation to continue it.</p></div>
  <a class="ns-btn-cta" href="<?= url('chat.php') ?>"><i class="fa-solid fa-plus me-1"></i>New chat</a></div>

  <?php if (!$rows): ?>
    <div class="ns-empty text-center py-5"><i class="fa-solid fa-comments ns-empty-ico"></i><p>No conversations yet. Start one in the chat room.</p></div>
  <?php else: ?>
    <div class="ns-conv-list">
      <?php foreach ($rows as $c): ?>
        <a class="ns-conv-card" href="<?= url('chat.php?id=' . $c['id']) ?>">
          <div class="ns-conv-head">
            <strong><?= e($c['intent'] ? ucfirst(str_replace('_',' ',$c['intent'])) : 'Conversation') ?></strong>
            <span class="ns-tag-mute"><?= time_ago($c['updated_at']) ?></span>
          </div>
          <div class="ns-conv-foot d-flex gap-2 flex-wrap">
            <span class="ns-status-badge <?= $c['status']==='escalated' ? 'is-warn' : '' ?>"><?= e(str_replace('_',' ', $c['status'])) ?></span>
            <?php if ($c['confidence']): ?><span class="ns-tag-mute">Confidence <?= round((float)$c['confidence']*100) ?>%</span><?php endif; ?>
            <?php if ($c['ai_provider']): ?><span class="ns-tag-mute"><?= e($c['ai_provider']) ?></span><?php endif; ?>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
