<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

$pdo = db();
$report = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    @set_time_limit(180);

    $force = !empty($_POST['force']);
    $images = $pdo->query("SELECT DISTINCT image FROM dresses WHERE image IS NOT NULL AND image <> ''")
        ->fetchAll(PDO::FETCH_COLUMN);

    $created = 0;
    $skipped = 0;
    $errors = [];

    foreach ($images as $image) {
        $result = generate_existing_image_variants((string)$image, $force);
        $created += (int)$result['created'];
        $skipped += (int)$result['skipped'];

        if (!empty($result['error'])) {
            $errors[] = basename((string)$image) . ': ' . $result['error'];
        }
    }

    $report = [
        'images' => count($images),
        'created' => $created,
        'skipped' => $skipped,
        'errors' => $errors,
    ];
}

$page_title = 'Optimizar imágenes | Brenan Boutique';
include __DIR__ . '/../includes/header.php';
?>

<header class="admin-header">
  <div class="wrap admin-top">
    <a class="brand" href="<?= BASE_URL ?>/admin/index.php">
      <span class="logo">BB</span>
      <span>
        <strong>Brenan Boutique</strong>
        <small>Optimización para smartphone</small>
      </span>
    </a>

    <div class="top-actions">
      <a class="btn" href="<?= BASE_URL ?>/admin/index.php">Volver al panel</a>
      <a class="btn" href="<?= BASE_URL ?>/index.php" target="_blank">Ver catálogo</a>
    </div>
  </div>
</header>

<main class="wrap admin-page">
  <section class="form-panel optimize-panel">
    <div class="panel-head">
      <h1>Optimizar fotografías existentes</h1>
      <p>Genera versiones ligeras de 480 y 900 píxeles para que el catálogo cargue más rápido en celular. Las fotografías originales se conservan para la vista ampliada.</p>
    </div>

    <?php if (!image_optimizer_available()): ?>
      <div class="alert danger">
        El servidor no tiene habilitada la extensión PHP GD. Solicita al proveedor de hospedaje que active <strong>GD</strong> para PHP y vuelve a ejecutar esta herramienta.
      </div>
    <?php endif; ?>

    <?php if ($report): ?>
      <?php if (!$report['errors']): ?>
        <div class="alert success">
          Optimización terminada: <?= (int)$report['images'] ?> fotografías revisadas, <?= (int)$report['created'] ?> archivos ligeros creados y <?= (int)$report['skipped'] ?> omitidos porque ya existían.
        </div>
      <?php else: ?>
        <div class="alert danger">
          Se terminó el proceso con <?= count($report['errors']) ?> archivo(s) que no pudieron procesarse.
        </div>
        <ul class="optimization-errors">
          <?php foreach ($report['errors'] as $error): ?>
            <li><?= e($error) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    <?php endif; ?>

    <div class="optimization-summary">
      <div>
        <strong>480 px</strong>
        <span>Miniatura para teléfonos y tarjetas pequeñas.</span>
      </div>
      <div>
        <strong>900 px</strong>
        <span>Resolución nítida para pantallas de alta densidad.</span>
      </div>
      <div>
        <strong>Original</strong>
        <span>Se utiliza únicamente al abrir la prenda a detalle.</span>
      </div>
    </div>

    <form method="post" class="optimization-form">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

      <label class="optimization-check">
        <input type="checkbox" name="force" value="1">
        <span>Volver a generar las versiones ligeras que ya existen.</span>
      </label>

      <div class="form-actions">
        <button class="btn primary" type="submit" <?= image_optimizer_available() ? '' : 'disabled' ?>>Optimizar ahora</button>
        <a class="btn" href="<?= BASE_URL ?>/admin/index.php">Cancelar</a>
      </div>
    </form>
  </section>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
