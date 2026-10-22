<?php
/**
 * api/lang.php – persist the user's language preference to the session.
 * POST { language: en|ms|zh, csrf }
 */
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) json_out(['ok' => false, 'error' => 'Authentication required.'], 401);

$sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? '');
if (!is_string($sent) || $sent === '' || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
    json_out(['ok' => false, 'error' => 'Invalid session.'], 419);
}

$language = $_POST['language'] ?? '';
$language = in_array($language, ['en','ms','zh'], true) ? $language : 'auto';
$_SESSION['lang'] = $language;

json_out(['ok' => true, 'message' => 'Language set to ' . $language]);
