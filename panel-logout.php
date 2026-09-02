<?php
declare(strict_types=1);
require __DIR__ . '/api/_auth.php';

start_secure_session();
$_SESSION = [];
session_destroy();
header('Location: panel-login');
exit;
