<?php
/** admin/ai_settings.php – configure the AI engine (super_admin only). */
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('super_admin');

$pageTitle = 'AI Settings';
$pdo = db();

// Load current settings
$settings = [];
try {
    $rows = $pdo->query('SELECT setting_key, setting_value FROM ai_settings')->fetchAll();
    foreach ($rows as $r) $settings[$r['setting_key']] = $r['setting_value'];
} catch (Throwable $e) {}

$saved = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    verify_csrf();
    $toSave = [
        'enabled' => !empty($_POST['enabled']) ? '1' : '0',
        'primary_endpoint' => trim($_POST['primary_endpoint'] ?? ''),
        'primary_api_key'  => trim($_POST['primary_api_key'] ?? ''),
        'primary_model'    => trim($_POST['primary_model'] ?? ''),
        'fallback_endpoint' => trim($_POST['fallback_endpoint'] ?? ''),
        'fallback_api_key'  => trim($_POST['fallback_api_key'] ?? ''),
        'fallback_model'    => trim($_POST['fallback_model'] ?? ''),
        'temperature' => $_POST['temperature'] ?? '',
        'max_tokens' => $_POST['max_tokens'] ?? '',
        'confidence_threshold' => $_POST['confidence_threshold'] ?? '',
        'system_prompt' => trim($_POST['system_prompt'] ?? ''),
    ];
    // Only store the api key if a value was entered (avoids overwriting with blank)
    if ($toSave['primary_api_key'] === '' && !empty($_POST['primary_api_key_empty'])) $toSave['primary_api_key'] = '';
    if ($toSave['fallback_api_key'] === '' && !empty($_POST['fallback_api_key_empty'])) $toSave['fallback_api_key'] = '';

    $stmt = $pdo->prepare('INSERT INTO ai_settings (setting_key, setting_value) VALUES (:k, :v)
                           ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    foreach ($toSave as $k => $v) {
        $stmt->execute([':k' => $k, ':v' => $v]);
    }
    log_activity(current_user()['id'], 'ai_config_changed', 'AI configuration updated', client_ip());
    flash('success', 'AI settings saved. Changes take effect immediately.');
    // Re-load
    $rows = $pdo->query('SELECT setting_key, setting_value FROM ai_settings')->fetchAll();
    $settings = [];
    foreach ($rows as $r) $settings[$r['setting_key']] = $r['setting_value'];
}

$g = fn(string $k) => e($settings[$k] ?? '');

require __DIR__ . '/../includes/admin_header.php';
?>

<div class="ns-admin-row">
  <form method="post" class="col-12 col-xl-8">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">

    <div class="ns-card mb-4">
      <div class="ns-card-head d-flex justify-content-between align-items-center">
        <h5><i class="fa-solid fa-toggle-on me-2"></i>AI Assistant</h5>
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" name="enabled" id="aiEnabled" <?= !empty($settings['enabled']) ? 'checked' : '' ?>>
          <label class="form-check-label" for="aiEnabled">AI enabled</label>
        </div>
      </div>
      <p class="text-muted small mb-0">When disabled, the local knowledge-base fallback is used for every question.</p>
    </div>

    <div class="ns-card mb-4">
      <div class="ns-card-head"><h5><i class="fa-solid fa-microchip me-2"></i>Primary Provider</h5></div>
      <div class="row g-3">
        <div class="col-12"><label class="form-label small">API endpoint</label>
          <input name="primary_endpoint" class="form-control" value="<?= $g('primary_endpoint') ?>" placeholder="https://api.openai.com/v1/chat/completions"></div>
        <div class="col-md-6"><label class="form-label small">API key <span class="text-muted">(server-side only)</span></label>
          <input type="password" name="primary_api_key" class="form-control" value="<?= $g('primary_api_key') ?>" placeholder="sk-…" autocomplete="off"></div>
        <div class="col-md-6"><label class="form-label small">Model</label>
          <input name="primary_model" class="form-control" value="<?= $g('primary_model') ?>" placeholder="gpt-4o-mini"></div>
      </div>
    </div>

    <div class="ns-card mb-4">
      <div class="ns-card-head"><h5><i class="fa-solid fa-shield me-2"></i>Fallback Provider</h5></div>
      <div class="row g-3">
        <div class="col-12"><label class="form-label small">API endpoint</label>
          <input name="fallback_endpoint" class="form-control" value="<?= $g('fallback_endpoint') ?>"></div>
        <div class="col-md-6"><label class="form-label small">API key</label>
          <input type="password" name="fallback_api_key" class="form-control" value="<?= $g('fallback_api_key') ?>" placeholder="Leave blank to reuse primary" autocomplete="off"></div>
        <div class="col-md-6"><label class="form-label small">Model</label>
          <input name="fallback_model" class="form-control" value="<?= $g('fallback_model') ?>"></div>
      </div>
    </div>

    <div class="ns-card mb-4">
      <div class="ns-card-head"><h5><i class="fa-solid fa-sliders me-2"></i>Generation</h5></div>
      <div class="row g-3">
        <div class="col-md-4"><label class="form-label small">Temperature (0–1)</label>
          <input type="number" step="0.1" min="0" max="1" name="temperature" class="form-control" value="<?= $g('temperature') ?>"></div>
        <div class="col-md-4"><label class="form-label small">Max tokens</label>
          <input type="number" min="50" max="4096" name="max_tokens" class="form-control" value="<?= $g('max_tokens') ?>"></div>
        <div class="col-md-4"><label class="form-label small">Confidence threshold (0–1)</label>
          <input type="number" step="0.05" min="0" max="1" name="confidence_threshold" class="form-control" value="<?= $g('confidence_threshold') ?>">
          <small class="text-muted">Below this, Nova offers a human.</small></div>
      </div>
    </div>

    <div class="ns-card mb-4">
      <div class="ns-card-head"><h5><i class="fa-solid fa-robot me-2"></i>System Prompt</h5></div>
      <textarea name="system_prompt" rows="6" class="form-control" style="font-family:monospace;font-size:.85rem"><?= $g('system_prompt') ?></textarea>
    </div>

    <div class="d-flex gap-2">
      <button class="ns-btn-cta" type="submit"><i class="fa-solid fa-save me-1"></i>Save settings</button>
    </div>
  </form>

  <div class="col-12 col-xl-4 d-none d-xl-block">
    <div class="ns-card ns-sticky">
      <div class="ns-card-head"><h5><i class="fa-solid fa-circle-info me-2"></i>How it works</h5></div>
      <ol class="ns-flow-list">
        <li><strong>Primary AI</strong> handles the request.</li>
        <li>If it fails, <strong>Fallback AI</strong> is tried.</li>
        <li>If both fail, the <strong>local knowledge base</strong> returns a grounded answer.</li>
      </ol>
      <div class="ns-note-box">
        <i class="fa-solid fa-shield-halved me-1"></i> API keys are stored server-side and never sent to the browser.
      </div>
      <div class="ns-note-box">
        <i class="fa-solid fa-language me-1"></i> The AI responds in the customer's language (English, Bahasa Melayu, Chinese).
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
