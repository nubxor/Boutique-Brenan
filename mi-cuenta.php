<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$destination = is_logged_in() ? '/admin/index.php' : '/admin/login.php';
header('Location: ' . BASE_URL . $destination, true, 303);
exit;
