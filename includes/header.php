<?php
if (!isset($page_title)) {
    $page_title = 'Catálogo Digital | Brenan Boutique';
}

$page_description = isset($page_description)
    ? trim((string)$page_description)
    : 'Catálogo digital de prendas Brenan Boutique.';
$canonical_url = isset($canonical_url) ? trim((string)$canonical_url) : '';
$og_image_url = isset($og_image_url) ? trim((string)$og_image_url) : '';
$og_type = isset($og_type) ? trim((string)$og_type) : 'website';

header_remove('X-Powered-By');
header('Cache-Control: no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
header('X-Permitted-Cross-Domain-Policies: none');
header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; object-src 'none'; script-src 'self'; style-src 'self'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; media-src 'self'; manifest-src 'self'");

$isAdminPage = str_contains((string)($_SERVER['SCRIPT_NAME'] ?? ''), '/admin/');
$cssPath = __DIR__ . '/../assets/css/styles.css';
$cssVersion = 'v19-' . (is_file($cssPath) ? (string)filemtime($cssPath) : '1');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#f8e5ec">
  <title><?= e($page_title) ?></title>
  <meta name="description" content="<?= e($page_description) ?>">
  <?php if ($canonical_url !== ''): ?>
    <link rel="canonical" href="<?= e($canonical_url) ?>">
  <?php endif; ?>
  <meta property="og:locale" content="es_MX">
  <meta property="og:type" content="<?= e($og_type) ?>">
  <meta property="og:site_name" content="Brenan Boutique">
  <meta property="og:title" content="<?= e($page_title) ?>">
  <meta property="og:description" content="<?= e($page_description) ?>">
  <?php if ($canonical_url !== ''): ?>
    <meta property="og:url" content="<?= e($canonical_url) ?>">
  <?php endif; ?>
  <?php if ($og_image_url !== ''): ?>
    <meta property="og:image" content="<?= e($og_image_url) ?>">
    <meta property="og:image:alt" content="<?= e($page_title) ?>">
  <?php endif; ?>
  <meta name="twitter:card" content="<?= $og_image_url !== '' ? 'summary_large_image' : 'summary' ?>">
  <meta name="twitter:title" content="<?= e($page_title) ?>">
  <meta name="twitter:description" content="<?= e($page_description) ?>">
  <?php if ($og_image_url !== ''): ?>
    <meta name="twitter:image" content="<?= e($og_image_url) ?>">
  <?php endif; ?>
  <?php if ($isAdminPage): ?>
    <meta name="robots" content="noindex,nofollow,noarchive">
  <?php endif; ?>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/styles.css?v=<?= $cssVersion ?>">
</head>
<body>
