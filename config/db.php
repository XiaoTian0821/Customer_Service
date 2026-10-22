<?php
/**
 * config/db.php
 * PDO database connection. Loads values from app.php and the persisted
 * install config (config/install_config.php) when present.
 *
 * Usage:
 *   require_once __DIR__ . '/../includes/db.php';
 *   $pdo = db();
 */

declare(strict_types=1);

require_once __DIR__ . '/app.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    // install_config.php overrides app.php constants when it exists
    $cfgFile = __DIR__ . '/install_config.php';
    if (is_file($cfgFile)) {
        $cfg = require $cfgFile;
        $host = $cfg['db_host'] ?? DB_HOST;
        $port = $cfg['db_port'] ?? DB_PORT;
        $name = $cfg['db_name'] ?? DB_NAME;
        $user = $cfg['db_user'] ?? DB_USER;
        $pass = $cfg['db_pass'] ?? DB_PASS;
    } else {
        $host = DB_HOST;
        $port = DB_PORT;
        $name = DB_NAME;
        $user = DB_USER;
        $pass = DB_PASS;
    }

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        // Never leak raw PDO exception to customers.
        error_log('DB connection failed: ' . $e->getMessage());
        http_response_code(500);
        if (APP_DEBUG) {
            exit('Database connection failed: ' . htmlspecialchars($e->getMessage()));
        }
        exit('Something went wrong. Please try again later.');
    }
    return $pdo;
}
