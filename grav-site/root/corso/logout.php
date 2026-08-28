<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';
hdLogout();
header('Location: login.php');
exit;
