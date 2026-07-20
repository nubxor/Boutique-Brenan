<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

$pdo = db();
ensure_categories_schema($pdo);

$stmt = $pdo->query("SELECT * FROM dresses ORDER BY created_at DESC");
$dresses = $stmt->fetchAll();

$message = (string)($_GET['message'] ?? '');

$page_title = 'Panel administrativo | Brenan Boutique';
include __DIR__ . '/../includes/header.php';
?>

<header class="admin-header">
  <div class="wrap admin-top">
    <a class="brand" href="<?= BASE_URL ?>/index.php">
      <span class="logo">BB</span>
      <span>
        <strong>Brenan Boutique</strong>
        <small>Panel administrativo</small>
      </span>
    </a>

    <div class="top-actions">
      <a class="btn" href="<?= BASE_URL ?>/index.php" target="_blank">Ver catálogo</a>
      <a class="btn danger" href="<?= BASE_URL ?>/admin/logout.php">Salir</a>
    </div>
  </div>
</header>

<main class="wrap admin-page">
  <div class="section-head">
    <div>
      <h1>Prendas registradas</h1>
      <p>Agrega, edita, elimina y administra la disponibilidad de las prendas.</p>
    </div>
    <div class="top-actions">
      <a class="btn" href="<?= BASE_URL ?>/admin/optimize-images.php">Reparar y optimizar imágenes</a>
      <a class="btn primary" href="<?= BASE_URL ?>/admin/dress-form.php">+ Agregar prenda</a>
    </div>
  </div>

  <?php if ($message): ?>
    <div class="alert success"><?= e($message) ?></div>
  <?php endif; ?>

  <section class="panel">
    <div class="table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Foto</th>
            <th>Prenda</th>
            <th>Categoría</th>
            <th>Talla</th>
            <th>Precio</th>
            <th>Estado</th>
            <th>Fecha venta</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$dresses): ?>
            <tr><td colspan="8">No hay prendas registradas.</td></tr>
          <?php endif; ?>

          <?php foreach ($dresses as $dress): ?>
            <tr>
              <td>
                <?php if (!empty($dress['image'])): ?>
                  <?php $thumbFallbacks = json_encode(image_fallback_urls($dress['image']), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]'; ?>
                  <img
                    class="thumb"
                    src="<?= e(image_public_url($dress['image'], 480)) ?>"
                    data-image-fallbacks="<?= e($thumbFallbacks) ?>"
                    alt="<?= e($dress['name']) ?>"
                    loading="lazy"
                    decoding="async"
                  >
                <?php else: ?>
                  <span class="thumb no-thumb">—</span>
                <?php endif; ?>
              </td>
              <td><strong><?= e($dress['name']) ?></strong></td>
              <td><span class="badge category"><?= e((string)$dress['category']) ?></span></td>
              <td><?= e($dress['size']) ?></td>
              <td><?= money_mx($dress['price']) ?></td>
              <td><span class="badge <?= $dress['status'] === 'sold' ? 'sold' : 'available' ?>"><?= status_label($dress['status']) ?></span></td>
              <td><?= $dress['sold_date'] ? e($dress['sold_date']) : '—' ?></td>
              <td>
                <div class="actions">
                  <a class="btn small" href="<?= BASE_URL ?>/admin/dress-form.php?id=<?= (int)$dress['id'] ?>">Editar</a>

                  <form method="post" action="<?= BASE_URL ?>/admin/toggle-status.php" class="inline-form">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="id" value="<?= (int)$dress['id'] ?>">
                    <button class="btn small soft" type="submit"><?= $dress['status'] === 'sold' ? 'Disponible' : 'Vendido' ?></button>
                  </form>

                  <form method="post" action="<?= BASE_URL ?>/admin/delete.php" class="inline-form" onsubmit="return confirm('¿Eliminar esta prenda?');">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="id" value="<?= (int)$dress['id'] ?>">
                    <button class="btn small danger" type="submit">Eliminar</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
