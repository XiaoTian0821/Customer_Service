<?php
/** logout.php – end the session and return home. */
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
logout();
redirect('index.php');
