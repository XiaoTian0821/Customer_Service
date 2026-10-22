<?php
/** chat.php – the AI assistant conversation UI. */
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_login(); // customers (and staff browsing) may open chat
require_once __DIR__ . '/includes/ai_service.php';

$me = current_user();
$pageTitle = 'AI Assistant';

// Load or start a conversation for this customer
$conversationId = (int) ($_GET['id'] ?? 0);
if ($conversationId > 0) {
    $s = db()->prepare('SELECT * FROM conversations WHERE id = :id AND customer_id = :cid');
    $s->execute([':id' => $conversationId, ':cid' => $me['id']]);
    $conv = $s->fetch();
    if (!$conv) $conversationId = 0;
} else {
    $conv = null;
}
if (!$conv) {
    // start a new one
    $ins = db()->prepare('INSERT INTO conversations (customer_id, status) VALUES (:c, "active")');
    $ins->execute([':c' => $me['id']]);
    $conversationId = (int) db()->lastInsertId();
    $conv = ['id' => $conversationId, 'status' => 'active', 'intent' => null, 'sentiment' => null, 'confidence' => null, 'ai_provider' => null, 'human_status' => null];
}

// Transcripts for the page (most recent 60)
$history = [];
try {
    $s = db()->prepare('SELECT * FROM conversation_messages WHERE conversation_id = :id ORDER BY created_at ASC, id ASC LIMIT 60');
    $s->execute([':id' => $conversationId]);
    $history = $s->fetchAll();
} catch (Throwable $e) {}

$nsScripts = ['chat.js'];
require __DIR__ . '/includes/header.php';
?>
<div class="container-fluid ns-chat-wrap py-3">
  <div class="row g-3 h-100">
    <!-- Sidebar (conversation list) -->
    <aside class="col-lg-3 col-xl-2 ns-chat-side">
      <div class="d-flex align-items-center gap-2 mb-3">
        <span class="ns-chat-side-ico"><i class="fa-solid fa-headset"></i></span>
        <div><div class="ns-chat-side-title">Nova Assistant</div><small class="text-muted">Always available</small></div>
      </div>
      <button id="newChatBtn" class="ns-btn-cta w-100 mb-3"><i class="fa-solid fa-plus me-1"></i> New chat</button>
      <div class="ns-chat-side-sub mb-2">Your chats</div>
      <ul class="ns-chat-side-list" id="chatList">
        <?php
        try {
          $s = db()->prepare('SELECT * FROM conversations WHERE customer_id = :c ORDER BY updated_at DESC LIMIT 15');
          $s->execute([':c' => $me['id']]);
          $convs = $s->fetchAll();
          foreach ($convs as $c) {
            $active = $c['id'] == $conversationId;
            $intent = e($c['intent'] ?: 'Chat');
            $statusBadge = match($c['status']) {
              'ai_resolved' => 'ns-tag-ok', 'human_resolved' => 'ns-tag-info',
              'escalated' => 'ns-tag-warn', 'closed' => 'ns-tag-mute',
              default => 'ns-tag-live'
            };
        ?>
        <li class="ns-chat-side-item <?= $active ? 'is-active' : '' ?>" data-id="<?= $c['id'] ?>">
          <div class="d-flex justify-content-between align-items-center">
            <strong><?= $active ? 'Current' : ($c['intent'] ? ucfirst($c['intent']) : 'Conversation') ?></strong>
            <span class="<?= $statusBadge ?>"><?= e(str_replace('_',' ', $c['status'])) ?></span>
          </div>
          <small class="text-muted d-block mt-1"><?= time_ago($c['updated_at']) ?></small>
        </li>
        <?php } } catch (Throwable $e) {} ?>
      </ul>
      <?php if (has_role('super_admin','staff')): ?>
        <a href="<?= url('admin/conversations.php') ?>" class="ns-link-sub"><i class="fa-solid fa-shield-halved me-1"></i> Staff view</a>
      <?php endif; ?>
    </aside>

    <!-- Chat pane -->
    <section class="col-lg-9 col-xl-10">
      <div class="ns-chat-card h-100">
        <div class="ns-chat-head">
          <div>
            <div class="d-flex align-items-center gap-2">
              <span class="ns-avatar ns-avatar-ai"><i class="fa-solid fa-bolt-lightning"></i></span>
              <div><strong>Nova</strong><small class="d-block text-muted" id="convStatus">● AI Assistant</small></div>
            </div>
          </div>
          <div class="ns-chat-head-actions">
            <button id="clearChatBtn" class="ns-icon-btn" title="Clear conversation" aria-label="Clear conversation"><i class="fa-solid fa-rotate-left"></i></button>
            <button id="talkHumanBtn" class="ns-btn-ghost" title="Talk to a human"><i class="fa-solid fa-headset me-1"></i> Talk to a human</button>
          </div>
        </div>

        <div id="chatTranscript" class="ns-chat-transcript" aria-live="polite">
          <?php if (!$history): ?>
            <div class="ns-empty text-center my-auto">
              <div class="ns-empty-ico"><i class="fa-solid fa-comments"></i></div>
              <h4>How can I help you today?</h4>
              <p class="text-muted">Ask about your service, billing, plans, or troubleshooting. You can type or use your voice.</p>
              <div class="ns-suggest-chips d-flex flex-wrap gap-2 justify-content-center">
                <button class="ns-chip" data-q="How do I reset my password?">Reset my password</button>
                <button class="ns-chip" data-q="Which plan is right for my family?">Best plan for my family</button>
                <button class="ns-chip" data-q="My router has no internet, what should I do?">Router troubleshooting</button>
                <button class="ns-chip" data-q="What is your refund policy?">Refund policy</button>
              </div>
            </div>
          <?php else: ?>
            <?php foreach ($history as $m): ?>
              <?php
              $isAi = $m['sender_type'] === 'ai';
              $isCustomer = $m['sender_type'] === 'customer';
              $isStaff = $m['sender_type'] === 'staff';
              ?>
              <div class="ns-msgrow <?= $isCustomer ? 'is-customer' : ($isStaff ? 'is-staff' : 'is-ai') ?>">
                <div class="ns-msg <?= $isCustomer ? 'ns-msg-customer' : ($isStaff ? 'ns-msg-staff' : 'ns-msg-ai') ?>">
                  <?php if ($isStaff): ?><span class="ns-msg-badge">Support</span><?php endif; ?>
                  <?= nl2br(e($m['content'])) ?>
                  <?php if ($isAi && $m['sources']): ?>
                    <div class="ns-src"><i class="fa-solid fa-book-open me-1"></i> <?= e(implode(' · ', array_map(fn($s)=>$s['title'] ?? '', json_decode((string)$m['sources'], true) ?: []))) ?></div>
                  <?php endif; ?>
                  <?php if ($isAi): ?>
                    <div class="ns-msg-meta">
                      <span class="ns-conf"><i class="fa-solid fa-gauge-simple"></i> Confidence <?= round((float)$m['confidence']*100) ?>%</span>
                      <?php if (!empty($m['ai_provider'])): ?><span class="ns-prov"><?= e($m['ai_provider']) ?></span><?php endif; ?>
                    </div>
                    <div class="ns-feedback d-flex gap-2">
                      <button class="ns-fb" data-fb="helpful" data-msg="<?= $m['id'] ?>"><i class="fa-solid fa-thumbs-up"></i> Helpful</button>
                      <button class="ns-fb" data-fb="not_helpful" data-msg="<?= $m['id'] ?>"><i class="fa-solid fa-thumbs-down"></i> Not helpful</button>
                    </div>
                  <?php endif; ?>
                </div>
                <small class="ns-msg-time"><?= time_ago($m['created_at']) ?></small>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- Voice controls -->
        <div class="ns-voice-bar" id="voiceBar" aria-hidden="false">
          <button id="playBtn" class="ns-icon-btn" title="Play last answer" disabled><i class="fa-solid fa-volume-high"></i></button>
          <select id="voiceSelect" class="form-select form-select-sm ns-voice-sel" aria-label="Voice"></select>
          <span class="ns-voice-note">Voice support varies by browser</span>
        </div>

        <!-- Input -->
        <div class="ns-chat-input">
          <button id="micBtn" class="ns-icon-btn" title="Speak" aria-label="Voice input"><i class="fa-solid fa-microphone"></i></button>
          <textarea id="chatInput" class="form-control" rows="1" placeholder="Type a message, or use the microphone…"></textarea>
          <button id="sendBtn" class="ns-btn-cta" title="Send"><i class="fa-solid fa-paper-plane"></i></button>
        </div>
        <div class="ns-chat-foot d-flex flex-wrap gap-2">
          <small class="text-muted">Nova answers from verified knowledge. It may connect you to a human if needed.</small>
        </div>
      </div>
    </section>
  </div>
</div>

<script>
  window.NS_CHAT = {
    base: <?= json_encode(app_base()) ?>,
    conversationId: <?= $conversationId ?>,
    csrf: <?= json_encode(csrf_token()) ?>,
    suggestions: <?= json_encode(array_map(fn($s)=>$s, [])) ?>
  };
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
