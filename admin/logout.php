<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_post_request();
require_login();
verify_csrf();
logout_admin();
redirect('/index.php');
