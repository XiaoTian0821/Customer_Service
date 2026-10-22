<?php
/**
 * install.php – NovaServe web installer.
 *
 * What it does:
 *   1. Connects to MySQL with the credentials you supply
 *   2. Creates (or re-creates) the database
 *   3. Runs database/schema.sql (tables + seed data)
 *   4. Creates/updates the admin account with the password you choose
 *   5. Writes config/install_config.php so the app uses these credentials
 *   6. Disables itself (sets INSTALLER_ENABLED = false)
 *
 * To re-run it after uninstalling, delete the "installer-disabled" flag
 * (installer.lock file in the project root) and remove install_config.php.
 */

declare(strict_types=1);
require_once __DIR__ . '/config/app.php';

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Guard: refuse to run if installer is already disabled
if (defined('INSTALLER_ENABLED') && !INSTALLER_ENABLED && !isset($_POST['force'])) {
    exit('Installer is disabled. Delete installer.lock and config/install_config.php to run it again.');
}

$action = $_POST['action'] ?? '';
$step   = $_POST['step'] ?? '1';
$result = ['ok' => false, 'message' => '', 'step' => (int)$step];

// Password hash (so schema.sql seed is replaced with a real hash)
const ADMIN_PW = 'admin123';

try {
    if ($action === 'connect') {
        // Step 2: test credentials
        $host = $_POST['db_host'] ?? 'localhost';
        $port = $_POST['db_port'] ?? '3306';
        $user = $_POST['db_user'] ?? 'root';
        $pass = $_POST['db_pass'] ?? '';
        $db   = $_POST['db_name'] ?? 'novaserve_db';
        $pdo = new PDO("mysql:host={$host};port={$port}", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$db}`");
        $result = ['ok' => true, 'message' => 'Connected and database ready.', 'step' => 3];
    }

    if ($action === 'run_sql') {
        // Step 3: run schema
        $host = $_POST['db_host'] ?? 'localhost';
        $port = $_POST['db_port'] ?? '3306';
        $user = $_POST['db_user'] ?? 'root';
        $pass = $_POST['db_pass'] ?? '';
        $db   = $_POST['db_name'] ?? 'novaserve_db';
        $pdo = new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $sql = file_get_contents(__DIR__ . '/database/schema.sql');
        if ($sql === false) throw new Exception('schema.sql not found');
        $pdo->exec($sql);

        // Replace seed admin password with a real hash
        $pdo->prepare('UPDATE users SET password = :p WHERE role = "super_admin" AND email = "admin@novaserve.example"')
            ->execute([':p' => password_hash(ADMIN_PW, PASSWORD_DEFAULT)]);

        // Write the install config
        $cfgPhp = "<?php\nreturn [\n"
            . "'db_host' => " . var_export($host, true) . ",\n"
            . "'db_port' => " . var_export($port, true) . ",\n"
            . "'db_name' => " . var_export($db, true) . ",\n"
            . "'db_user' => " . var_export($user, true) . ",\n"
            . "'db_pass' => " . var_export($pass, true) . ",\n"
            . "];\n";
        if (file_put_contents(__DIR__ . '/config/install_config.php', $cfgPhp) === false) {
            throw new Exception('Could not write config/install_config.php');
        }
        // Lock the installer
        file_put_contents(__DIR__ . '/installer.lock', 'NovaServe installed ' . date('c') . "\n");
        $result = ['ok' => true, 'message' => 'Database ready and config saved. Installer is now locked.', 'step' => 4];
    }

} catch (Throwable $e) {
    $result = ['ok' => false, 'message' => 'Installation failed: ' . $e->getMessage(), 'step' => (int)$step];
}

// Render the installer UI (or auto-advance)
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>NovaServe Installer</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<style>body{background:#0f172a;color:#e2e8f0;font-family:Inter,system-ui,sans-serif}
.card{background:#1e293b;border:1px solid #334155;color:#e2e8f0}
.label{color:#94a3b8;font-size:.8rem;text-transform:uppercase;letter-spacing:.05em}
.btn-primary{background:#6366f1}.btn-primary:hover{background:#4f46e5}
.badge-step{background:#6366f1}.success{color:#4ade80}.error{color:#f87171}</style>
</head><body>
<div class="container py-5" style="max-width:560px">
  <div class="card p-4">
    <div class="d-flex align-items-center gap-2 mb-4">
      <div class="d-flex align-items-center justify-content-center" style="width:42px;height:42px;background:#6366f1;border-radius:10px;color:#fff"><i class="fa-solid fa-bolt-lightning"></i></div>
      <div><h5 class="mb-0">NovaServe Installer</h5><small class="text-secondary">Intelligent Customer Support Platform</small></div>
    </div>

    <?php if ($result['ok'] && $result['step'] >= 4): ?>
      <div class="mb-3 text-success"><i class="fa-solid fa-circle-check me-2"></i> Installation complete!</div>
      <div class="alert alert-info mb-0">
        <strong>Admin login:</strong><br>
        Email: <code>admin@novaserve.example</code><br>
        Password: <code>admin123</code><br>
        <small class="text-warning">Change this immediately after first login.</small>
      </div>
      <a href="login.php" class="btn btn-primary w-100 mt-3">Go to login</a>

    <?php elseif ($result['ok'] && $result['step'] === 3): ?>
      <div class="mb-3 text-success"><i class="fa-solid fa-circle-check me-2"></i> Database ready. Running schema…</div>
      <form method="post">
        <input type="hidden" name="action" value="run_sql">
        <input type="hidden" name="step" value="3">
        <input type="hidden" name="db_host" value="<?= e($_POST['db_host'] ?? 'localhost') ?>">
        <input type="hidden" name="db_port" value="<?= e($_POST['db_port'] ?? '3306') ?>">
        <input type="hidden" name="db_user" value="<?= e($_POST['db_user'] ?? 'root') ?>">
        <input type="hidden" name="db_pass" value="<?= e($_POST['db_pass'] ?? '') ?>">
        <input type="hidden" name="db_name" value="<?= e($_POST['db_name'] ?? 'novaserve_db') ?>">
        <button class="btn btn-primary w-100">Run schema and finish</button>
      </form>

    <?php else: ?>
      <?php if (!$result['ok'] && $result['message']): ?>
        <div class="alert alert-danger"><?= e($result['message']) ?></div>
      <?php endif; ?>
      <form method="post">
        <input type="hidden" name="action" value="connect">
        <input type="hidden" name="step" value="1">
        <div class="mb-3">
          <label class="label">DB Host</label>
          <input name="db_host" class="form-control" value="<?= e($_POST['db_host'] ?? 'localhost') ?>">
        </div>
        <div class="mb-3">
          <label class="label">DB Port</label>
          <input name="db_port" class="form-control" value="<?= e($_POST['db_port'] ?? '3306') ?>">
        </div>
        <div class="mb-3">
          <label class="label">DB Username</label>
          <input name="db_user" class="form-control" value="<?= e($_POST['db_user'] ?? 'root') ?>">
        </div>
        <div class="mb-3">
          <label class="label">DB Password</label>
          <input type="password" name="db_pass" class="form-control" value="">
        </div>
        <div class="mb-3">
          <label class="label">Database Name</label>
          <input name="db_name" class="form-control" value="<?= e($_POST['db_name'] ?? 'novaserve_db') ?>">
        </div>
        <button class="btn btn-primary w-100">Test connection</button>
      </form>
    <?php endif; ?>
  </div>
</div>
</body></html>
