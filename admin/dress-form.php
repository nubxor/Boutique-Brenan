<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

$pdo = db();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$dress = [
    'id' => 0,
    'name' => '',
    'size' => 'M',
    'price' => '',
    'status' => 'available',
    'sold_date' => '',
    'image' => '',
    'image_fit' => 'cover',
];

if ($id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM dresses WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $found = $stmt->fetch();

    if (!$found) {
        redirect('/admin/index.php?message=Vestido no encontrado');
    }

    $dress = $found;
}

$sizes = get_sizes($pdo);
foreach (['XS','S','M','L','XL','XXL','Única'] as $defaultSize) {
    if (!in_array($defaultSize, $sizes, true)) {
        $sizes[] = $defaultSize;
    }
}

$page_title = ($id > 0 ? 'Editar vestido' : 'Agregar vestido') . ' | Brenan Boutique';
include __DIR__ . '/../includes/header.php';
?>

<header class="admin-header">
  <div class="wrap admin-top">
    <a class="brand" href="<?= BASE_URL ?>/admin/index.php">
      <span class="logo">BB</span>
      <span>
        <strong>Brenan Boutique</strong>
        <small><?= $id > 0 ? 'Editar vestido' : 'Nuevo vestido' ?></small>
      </span>
    </a>

    <div class="top-actions">
      <a class="btn" href="<?= BASE_URL ?>/admin/index.php">Volver</a>
      <a class="btn danger" href="<?= BASE_URL ?>/admin/logout.php">Salir</a>
    </div>
  </div>
</header>

<main class="wrap admin-page">
  <section class="form-panel">
    <div class="panel-head">
      <h1><?= $id > 0 ? 'Editar vestido' : 'Agregar vestido' ?></h1>
      <p>Registra la fotografía, talla, precio y disponibilidad del vestido.</p>
    </div>

    <form class="dress-form" method="post" action="<?= BASE_URL ?>/admin/save.php" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="id" value="<?= (int)$dress['id'] ?>">
      <input type="hidden" name="current_image" value="<?= e($dress['image']) ?>">

      <div class="form-grid">
        <div class="upload-box">
          <label>
            <span>Fotografía principal</span>
            <input type="file" name="image" accept="image/*">
          </label>

          <div class="preview">
            <?php if (!empty($dress['image'])): ?>
              <?php $previewFallbacks = json_encode(image_fallback_urls($dress['image']), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]'; ?>
              <img
                src="<?= e(image_public_url($dress['image'], 480)) ?>"
                data-image-fallbacks="<?= e($previewFallbacks) ?>"
                alt="<?= e($dress['name']) ?>"
                decoding="async"
              >
            <?php else: ?>
              <span>Vista previa</span>
            <?php endif; ?>
          </div>

          <small>JPG, PNG, WEBP o GIF, máximo 5 MB. Al guardar, el sistema crea una imagen principal JPG compatible y versiones ligeras JPG/WebP para celular.</small>
          <div class="upload-optimization-status" data-upload-status hidden aria-live="polite"></div>
        </div>

        <div class="fields">
          <label>
            <span>Nombre o código</span>
            <input type="text" name="name" value="<?= e($dress['name']) ?>" required placeholder="Ej. Vestido satín rosa">
          </label>

          <div class="two">
            <label>
              <span>Talla</span>
              <input list="size-options" name="size" value="<?= e($dress['size']) ?>" required placeholder="M">
              <datalist id="size-options">
                <?php foreach ($sizes as $size): ?>
                  <option value="<?= e($size) ?>">
                <?php endforeach; ?>
              </datalist>
            </label>

            <label>
              <span>Precio</span>
              <input type="number" name="price" min="0" step="1" value="<?= e((string)$dress['price']) ?>" required placeholder="850">
            </label>
          </div>

          <div class="two">
            <label>
              <span>Estado</span>
              <select name="status">
                <option value="available" <?= selected($dress['status'], 'available') ?>>Disponible</option>
                <option value="sold" <?= selected($dress['status'], 'sold') ?>>Vendido</option>
              </select>
            </label>

            <label>
              <span>Fecha de venta</span>
              <input type="date" name="sold_date" value="<?= e((string)$dress['sold_date']) ?>">
            </label>
          </div>

          <label>
            <span>Ajuste de fotografía</span>
            <select name="image_fit">
              <option value="cover" <?= selected($dress['image_fit'], 'cover') ?>>Recorte elegante</option>
              <option value="contain" <?= selected($dress['image_fit'], 'contain') ?>>Imagen completa</option>
            </select>
          </label>

          <div class="form-actions">
            <button class="btn primary" type="submit">Guardar vestido</button>
            <a class="btn" href="<?= BASE_URL ?>/admin/index.php">Cancelar</a>
          </div>
        </div>
      </div>
    </form>
  </section>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
