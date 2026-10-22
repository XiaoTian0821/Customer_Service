<?php
/** index.php – NovaServe customer-portal home. */
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Support Home';

// Load company info from DB when possible
$settings = [];
try {
    $rows = db()->query('SELECT setting_key, setting_value FROM system_settings')->fetchAll();
    foreach ($rows as $r) $settings[$r['setting_key']] = $r['setting_value'];
} catch (Throwable $e) {}

$company   = $settings['company_name']   ?: COMPANY_NAME;
$phone     = $settings['company_phone']  ?: COMPANY_PHONE;
$email     = $settings['company_email']  ?: COMPANY_EMAIL;
$address   = $settings['company_address']?: COMPANY_ADDRESS;
$bizHours  = $settings['business_hours'] ?: BUSINESS_HOURS;
$suppHours = $settings['support_hours']  ?: SUPPORT_HOURS;

$popularFaqs = [];
try {
    $popularFaqs = db()->query(
        'SELECT question, view_count FROM faq_items WHERE is_active = 1 ORDER BY view_count DESC LIMIT 3'
    )->fetchAll();
} catch (Throwable $e) {}

$nsScripts = ['home.js'];
require __DIR__ . '/includes/header.php';
?>

<!-- HERO -->
<section class="ns-hero">
  <div class="container">
    <div class="row align-items-center g-4">
      <div class="col-lg-7">
        <span class="ns-hero-eyebrow"><i class="fa-solid fa-sparkles"></i> AI-powered support, always on</span>
        <h1 class="ns-hero-title">Ask <span class="ns-grad">Nova</span> anything.<br>Get an answer in seconds.</h1>
        <p class="ns-hero-sub">Meet <strong>Nova</strong>, your intelligent assistant. It answers from our verified knowledge base, listens to your voice, and hands you to a human the moment it should.</p>
        <div class="d-flex flex-wrap gap-2">
          <?php if (is_logged_in()): ?>
            <a class="ns-btn-cta btn-lg" href="<?= url('chat.php') ?>"><i class="fa-solid fa-message me-2"></i>Start a chat</a>
            <a class="ns-btn-ghost btn-lg" href="<?= url('ticket.php') ?>"><i class="fa-regular fa-circle-question me-2"></i>Open a ticket</a>
          <?php else: ?>
            <a class="ns-btn-cta btn-lg" href="<?= url('login.php') ?>"><i class="fa-solid fa-right-to-bracket me-2"></i>Sign in to chat</a>
            <a class="ns-btn-ghost btn-lg" href="<?= url('register.php') ?>"><i class="fa-solid fa-user-plus me-2"></i>Create account</a>
          <?php endif; ?>
        </div>
        <div class="ns-hero-stats">
          <div><span data-count-to="<?= count($popularFaqs) + 45 ?>"><?= count($popularFaqs) + 45 ?>+</span><small>FAQs</small></div>
          <div><span>24/7</span><small>AI available</small></div>
          <div><span>3</span><small>Languages</small></div>
        </div>
      </div>
      <div class="col-lg-5">
        <div class="ns-hero-card">
          <div class="ns-hero-card-head"><span class="ns-dot"></span> Nova · AI Assistant</div>
          <div class="ns-hero-preview">
            <div class="ns-msg ns-msg-customer">How do I reset my password?</div>
            <div class="ns-msg ns-msg-ai">Select <strong>"Forgot password"</strong> on the sign-in page, enter your email, and follow the link. It expires in 30 minutes.</div>
          </div>
          <div class="ns-hero-card-foot"><span class="ns-badge-ok"><i class="fa-solid fa-check"></i> Answered from knowledge base</span></div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- QUICK SEARCH -->
<section class="ns-section">
  <div class="container">
    <div class="ns-searchbar">
      <i class="fa-solid fa-magnifying-glass"></i>
      <form id="kbSearchForm" class="d-flex" action="<?= url('knowledge.php') ?>" method="get">
        <input type="search" name="q" placeholder="Search our knowledge base… e.g. router, billing, plans" class="form-control" aria-label="Search knowledge base">
        <button class="ns-btn-cta ms-2" type="submit">Search</button>
      </form>
    </div>
  </div>
</section>

<!-- SERVICE HIGHLIGHTS -->
<section class="ns-section">
  <div class="container">
    <div class="text-center mb-4"><h2 class="ns-h2">How we help</h2><p class="ns-lead">Four ways to reach the right answer.</p></div>
    <div class="row g-4">
      <div class="col-md-6 col-lg-3"><div class="ns-feature"><div class="ns-feature-ico"><i class="fa-solid fa-robot"></i></div><h5>AI Assistant</h5><p>Instant, knowledge-grounded answers with a voice option.</p></div></div>
      <div class="col-md-6 col-lg-3"><div class="ns-feature"><div class="ns-feature-ico"><i class="fa-solid fa-book"></i></div><h5>Knowledge Base</h5><p>Guides, policies, and troubleshooting steps you can search.</p></div></div>
      <div class="col-md-6 col-lg-3"><div class="ns-feature"><div class="ns-feature-ico"><i class="fa-solid fa-circle-question"></i></div><h5>FAQs</h5><p>Quick answers to the questions our customers ask most.</p></div></div>
      <div class="col-md-6 col-lg-3"><div class="ns-feature"><div class="ns-feature-ico"><i class="fa-solid fa-headset"></i></div><h5>Human Support</h5><p>Escalate to a real person whenever you need one.</p></div></div>
    </div>
  </div>
</section>

<!-- POPULAR FAQ + CONTACT -->
<section class="ns-section ns-section-alt">
  <div class="container">
    <div class="row g-4">
      <div class="col-lg-7">
        <h3 class="ns-h3 mb-3">Popular questions</h3>
        <?php if ($popularFaqs): ?>
          <div class="ns-faq-list">
            <?php foreach ($popularFaqs as $f): ?>
              <div class="ns-faq-row"><i class="fa-solid fa-caret-right"></i><a href="<?= url('faq.php') ?>"><?= e($f['question']) ?></a></div>
            <?php endforeach; ?>
          </div>
          <a class="link-primary" href="<?= url('faq.php') ?>">View all FAQs <i class="fa-solid fa-arrow-right"></i></a>
        <?php else: ?>
          <p class="text-muted">Browse the FAQ to find quick answers to common questions.</p>
        <?php endif; ?>
      </div>
      <div class="col-lg-5">
        <div class="ns-contact-card">
          <h4 class="ns-h3">Reach us directly</h4>
          <div class="ns-contact-row"><i class="fa-solid fa-phone"></i><div><small>Phone</small><br><?= e($phone) ?></div></div>
          <div class="ns-contact-row"><i class="fa-regular fa-envelope"></i><div><small>Email</small><br><?= e($email) ?></div></div>
          <div class="ns-contact-row"><i class="fa-solid fa-location-dot"></i><div><small>Office</small><br><?= e($address) ?></div></div>
          <div class="ns-contact-row"><i class="fa-regular fa-clock"></i><div><small>Hours</small><br><?= e($bizHours) ?></div></div>
          <hr>
          <small class="text-muted"><i class="fa-solid fa-shield me-1"></i> AI support available <?= e($suppHours) ?></small>
        </div>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
