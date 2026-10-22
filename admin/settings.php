<?php
/** admin/settings.php – system settings (company info, policies) (super_admin). */
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('super_admin');

$pageTitle = 'System Settings';
$pdo = db();

$settings = [];
try {
    $rows = $pdo->query('SELECT setting_key, setting_value FROM system_settings')->fetchAll();
    foreach ($rows as $r) $settings[$r['setting_key']] = $r['setting_value'];
} catch (Throwable $e) {}

$saved = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_settings') {
    verify_csrf();
    $fields = ['company_name','company_phone','company_email','company_address','business_hours','support_hours','tagline'];
    $stmt = $pdo->prepare('INSERT INTO system_settings (setting_key, setting_value) VALUES (:k, :v)
                           ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    foreach ($fields as $f) {
        $stmt->execute([':k' => $f, ':v' => trim($_POST[$f] ?? '')]);
    }
    log_activity(current_user()['id'], 'settings_changed', 'System settings updated', client_ip());
    flash('success', 'System settings saved.');
    $rows = $pdo->query('SELECT setting_key, setting_value FROM system_settings')->fetchAll();
    $settings = [];
    foreach ($rows as $r) $settings[$r['setting_key']] = $r['setting_value'];
}

$g = fn(string $k) => e($settings[$k] ?? '');

require __DIR__ . '/../includes/admin_header.php';
?>

<div class="ns-admin-row">
  <form method="post" class="col-12 col-xl-9">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_settings">
    <div class="ns-card">
      <div class="ns-card-head"><h5><i class="fa-solid fa-building me-2"></i>Company Information</h5></div>
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label small">Company name</label><input name="company_name" class="form-control" value="<?= $g('company_name') ?>"></div>
        <div class="col-md-6"><label class="form-label small">Tagline</label><input name="tagline" class="form-control" value="<?= $g('tagline') ?>"></div>
        <div class="col-md-6"><label class="form-label small">Support phone</label><input name="company_phone" class="form-control" value="<?= $g('company_phone') ?>"></div>
        <div class="col-md-6"><label class="form-label small">Support email</label><input name="company_email" class="form-control" value="<?= $g('company_email') ?>"></div>
        <div class="col-12"><label class="form-label small">Address</label><input name="company_address" class="form-control" value="<?= $g('company_address') ?>"></div>
        <div class="col-md-6"><label class="form-label small">Business hours</label><input name="business_hours" class="form-control" value="<?= $g('business_hours') ?>"></div>
        <div class="col-md-6"><label class="form-label small">Support hours</label><input name="support_hours" class="form-control" value="<?= $g('support_hours') ?>"></div>
      </div>
      <div class="mt-3"><button class="ns-btn-cta" type="submit"><i class="fa-solid fa-save me-1"></i>Save settings</button></div>
    </div>
  </form>
  <div class="col-12 col-xl-3 d-none d-xl-block">
    <div class="ns-card ns-sticky">
      <div class="ns-card-head"><h5><i class="fa-solid fa-circle-info me-2"></i>About</h5></div>
      <p class="text-muted small">These values appear on the customer-facing portal. Update them to reflect your organisation's real contact details and policies.</p>
      <div class="ns-note-box"><i class="fa-solid fa-lock me-1"></i> AI provider keys are managed in <a href="<?= url('admin/ai_settings.php') ?>">AI Settings</a>.</div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
