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
    $records = $pdo->query("SELECT id, name, image FROM dresses WHERE image IS NOT NULL AND image <> '' ORDER BY id ASC")
        ->fetchAll(PDO::FETCH_ASSOC);

    $created = 0;
    $skipped = 0;
    $updated = 0;
    $errors = [];

    foreach ($records as $record) {
        $result = repair_image_record(
            $pdo,
            (int)$record['id'],
            (string)$record['image'],
            $force
        );

        $created += (int)$result['created'];
        $skipped += (int)$result['skipped'];
        $updated += !empty($result['updated']) ? 1 : 0;

        if (!empty($result['error'])) {
            $errors[] = (string)$record['name'] . ' (' . basename((string)$record['image']) . '): ' . $result['error'];
        }
    }

    $report = [
        'images' => count($records),
        'created' => $created,
        'skipped' => $skipped,
        'updated' => $updated,
        'errors' => $errors,
    ];
}

$page_title = 'Optimizar imágenes | Brennan Boutique';
include __DIR__ . '/../includes/header.php';
?>

<header class="admin-header">
  <div class="wrap admin-top">
    <a class="brand" href="<?= BASE_URL ?>/admin/index.php">
      <span class="logo">BB</span>
      <span>
        <strong>Brennan Boutique</strong>
        <small>Reparación y optimización de fotografías</small>
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
      <h1>Reparar y optimizar fotografías existentes</h1>
      <p>Comprueba cada fotografía, corrige referencias con extensión incompatible y genera respaldos JPG junto con versiones ligeras de 480 y 900 píxeles.</p>
    </div>

    <?php if (!image_optimizer_available()): ?>
      <div class="alert danger">
        El servidor no tiene habilitada la extensión PHP GD. Solicita al proveedor de hospedaje que active <strong>GD</strong> para PHP y vuelve a ejecutar esta herramienta.
      </div>
    <?php endif; ?>

    <?php if ($report): ?>
      <?php if (!$report['errors']): ?>
        <div class="alert success">
          Proceso terminado: <?= (int)$report['images'] ?> fotografías revisadas, <?= (int)$report['created'] ?> archivos creados, <?= (int)$report['updated'] ?> referencias reparadas y <?= (int)$report['skipped'] ?> archivos conservados porque ya eran válidos.
        </div>
      <?php else: ?>
        <div class="alert danger">
          Se terminó el proceso con <?= count($report['errors']) ?> fotografía(s) que no pudieron recuperarse automáticamente. Las demás sí fueron revisadas y reparadas.
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
        <strong>JPG compatible</strong>
        <span>Respaldo principal para evitar cuadros vacíos si WebP falla.</span>
      </div>
    </div>

    <form method="post" class="optimization-form">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

      <label class="optimization-check">
        <input type="checkbox" name="force" value="1">
        <span>Volver a crear también los respaldos y versiones que ya existen.</span>
      </label>

      <div class="form-actions">
        <button class="btn primary" type="submit" <?= image_optimizer_available() ? '' : 'disabled' ?>>Reparar y optimizar ahora</button>
        <a class="btn" href="<?= BASE_URL ?>/admin/index.php">Cancelar</a>
      </div>
    </form>
  </section>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
