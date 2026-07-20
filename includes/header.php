<?php
if (!isset($page_title)) {
    $page_title = 'Catálogo Digital | Brenan Boutique';
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

$isAdminPage = str_contains((string)($_SERVER['SCRIPT_NAME'] ?? ''), '/admin/');
$cssPath = __DIR__ . '/../assets/css/styles.css';
$cssVersion = is_file($cssPath) ? (int)filemtime($cssPath) : 1;
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($page_title) ?></title>
  <meta name="description" content="Catálogo digital de prendas Brenan Boutique.">
  <?php if ($isAdminPage): ?>
    <meta name="robots" content="noindex,nofollow,noarchive">
  <?php endif; ?>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/styles.css?v=<?= $cssVersion ?>">
</head>
<body>
