<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = db();
ensure_categories_schema($pdo);

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$dress = null;

if (is_int($id) && $id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM dresses WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $dress = $stmt->fetch();
}

function public_absolute_url(string $path): string
{
    if ($path === '') {
        return '';
    }

    if (preg_match('~^https?://~i', $path)) {
        return $path;
    }

    $https = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
    $scheme = $https ? 'https' : 'http';
    $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));

    return $host === '' ? $path : $scheme . '://' . $host . '/' . ltrim($path, '/');
}

if (!$dress) {
    http_response_code(404);
    $page_title = 'Prenda no encontrada | Brenan Boutique';
    $page_description = 'La prenda solicitada no se encuentra disponible en el catálogo de Brenan Boutique.';
    include __DIR__ . '/includes/header.php';
    ?>
    <header class="product-header">
      <div class="wrap">
        <nav class="topbar">
          <a class="brand" href="<?= BASE_URL ?>/index.php" aria-label="Brenan Boutique">
            <span class="logo">BB</span>
            <span><strong>Brenan Boutique</strong><small>Catálogo digital de prendas</small></span>
          </a>
          <div class="top-actions"><a class="btn" href="<?= BASE_URL ?>/index.php#catalogo">Volver al catálogo</a></div>
        </nav>
      </div>
    </header>
    <main class="wrap product-page">
      <section class="empty product-not-found">
        <h1>Prenda no encontrada</h1>
        <p>El producto pudo ser eliminado o el enlace ya no está disponible.</p>
        <a class="btn primary" href="<?= BASE_URL ?>/index.php#catalogo">Explorar el catálogo</a>
      </section>
    </main>
    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

$category = normalize_category((string)($dress['category'] ?? 'Otros'));
$productPath = BASE_URL . '/product.php?id=' . (int)$dress['id'];
$canonical_url = public_absolute_url($productPath);
$page_title = (string)$dress['name'] . ' | Brenan Boutique';
$page_description = sprintf(
    '%s, categoría %s, talla %s, precio %s. Estado: %s en Brenan Boutique.',
    (string)$dress['name'],
    $category,
    (string)$dress['size'],
    money_mx($dress['price']),
    status_label((string)$dress['status'])
);
$og_type = 'product';
$mainImageUrl = !empty($dress['image']) ? image_public_url($dress['image']) : '';
$og_image_url = public_absolute_url($mainImageUrl);

$relatedStmt = $pdo->prepare(
    "SELECT * FROM dresses
     WHERE category = :category AND id <> :id
     ORDER BY CASE WHEN status = 'available' THEN 0 ELSE 1 END, created_at DESC
     LIMIT 4"
);
$relatedStmt->execute([
    ':category' => $category,
    ':id' => (int)$dress['id'],
]);
$related = $relatedStmt->fetchAll();

$imageFallbacks = !empty($dress['image']) ? image_fallback_urls($dress['image']) : [];
$imageFallbackJson = json_encode($imageFallbacks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]';
$fallbackSrcset = !empty($dress['image']) ? image_srcset($dress['image'], 'fallback') : '';
$webpSrcset = !empty($dress['image']) ? image_srcset($dress['image'], 'webp') : '';
$productImageUrl = !empty($dress['image']) ? image_public_url($dress['image'], 900) : '';

include __DIR__ . '/includes/header.php';
?>

<header class="product-header">
  <div class="wrap">
    <nav class="topbar">
      <a class="brand" href="<?= BASE_URL ?>/index.php" aria-label="Brenan Boutique">
        <span class="logo">BB</span>
        <span>
          <strong>Brenan Boutique</strong>
          <small>Catálogo digital de prendas</small>
        </span>
      </a>

      <div class="top-actions">
        <a class="btn" href="<?= BASE_URL ?>/index.php#catalogo">← Volver al catálogo</a>
        <?php if (is_logged_in()): ?>
          <a class="btn primary" href="<?= BASE_URL ?>/admin/dress-form.php?id=<?= (int)$dress['id'] ?>">Editar prenda</a>
        <?php endif; ?>
      </div>
    </nav>
  </div>
</header>

<main class="wrap product-page">
  <nav class="product-breadcrumb" aria-label="Ruta de navegación">
    <a href="<?= BASE_URL ?>/index.php">Inicio</a>
    <span aria-hidden="true">›</span>
    <a href="<?= BASE_URL ?>/index.php?category=<?= rawurlencode($category) ?>&status=all#catalogo"><?= e($category) ?></a>
    <span aria-hidden="true">›</span>
    <span><?= e((string)$dress['name']) ?></span>
  </nav>

  <article class="product-detail <?= $dress['status'] === 'sold' ? 'is-sold' : '' ?>">
    <section class="product-gallery" aria-label="Fotografía del producto">
      <?php if ($dress['status'] === 'sold'): ?>
        <div class="sold-ribbon product-sold-ribbon">Vendido</div>
      <?php endif; ?>

      <?php if ($productImageUrl !== ''): ?>
        <button
          class="product-main-photo photo-zoom <?= $dress['image_fit'] === 'contain' ? 'contain' : '' ?>"
          type="button"
          data-lightbox-src="<?= e(image_public_url($dress['image'])) ?>"
          data-lightbox-fallbacks="<?= e($imageFallbackJson) ?>"
          data-lightbox-alt="<?= e((string)$dress['name']) ?>"
          aria-label="Ampliar fotografía de <?= e((string)$dress['name']) ?>"
        >
          <picture>
            <?php if ($webpSrcset !== ''): ?>
              <source type="image/webp" srcset="<?= e($webpSrcset) ?>" sizes="(max-width: 760px) calc(100vw - 36px), 620px">
            <?php endif; ?>
            <img
              class="catalog-image"
              src="<?= e($productImageUrl) ?>"
              <?php if ($fallbackSrcset !== ''): ?>srcset="<?= e($fallbackSrcset) ?>"<?php endif; ?>
              sizes="(max-width: 760px) calc(100vw - 36px), 620px"
              data-image-fallbacks="<?= e($imageFallbackJson) ?>"
              alt="<?= e((string)$dress['name']) ?>"
              width="900"
              height="1200"
              loading="eager"
              fetchpriority="high"
              decoding="async"
            >
          </picture>
        </button>
        <p class="product-photo-hint">Toca la fotografía para verla completa y acercar los detalles.</p>
      <?php else: ?>
        <div class="product-main-photo product-no-photo">Sin fotografía disponible</div>
      <?php endif; ?>
    </section>

    <section class="product-summary">
      <div class="product-badges">
        <span class="badge category"><?= e($category) ?></span>
        <span class="badge <?= $dress['status'] === 'sold' ? 'sold' : 'available' ?>"><?= e(status_label((string)$dress['status'])) ?></span>
      </div>

      <h1><?= e((string)$dress['name']) ?></h1>
      <div class="product-price"><?= e(money_mx($dress['price'])) ?></div>
      <p class="product-intro">Prenda seleccionada por Brenan Boutique. Consulta sus características y comparte este enlace directamente con quien quieras.</p>

      <dl class="product-facts">
        <div><dt>Categoría</dt><dd><?= e($category) ?></dd></div>
        <div><dt>Talla</dt><dd><?= e((string)$dress['size']) ?></dd></div>
        <div><dt>Disponibilidad</dt><dd><?= e(status_label((string)$dress['status'])) ?></dd></div>
        <?php if ($dress['status'] === 'sold' && !empty($dress['sold_date'])): ?>
          <div><dt>Fecha de venta</dt><dd><?= e((string)$dress['sold_date']) ?></dd></div>
        <?php endif; ?>
      </dl>

      <div class="product-actions">
        <?php if ($productImageUrl !== ''): ?>
          <button
            class="btn primary product-photo-button"
            type="button"
            data-lightbox-src="<?= e(image_public_url($dress['image'])) ?>"
            data-lightbox-fallbacks="<?= e($imageFallbackJson) ?>"
            data-lightbox-alt="<?= e((string)$dress['name']) ?>"
          >Ver fotografía completa</button>
        <?php endif; ?>

        <button
          class="btn soft share-product"
          type="button"
          data-share-product
          data-product-id="<?= (int)$dress['id'] ?>"
          data-product-url="<?= e($productPath) ?>"
          data-product-name="<?= e((string)$dress['name']) ?>"
          data-product-category="<?= e($category) ?>"
          data-product-size="<?= e((string)$dress['size']) ?>"
          data-product-price="<?= e(money_mx($dress['price'])) ?>"
          data-product-status="<?= e(status_label((string)$dress['status'])) ?>"
          aria-label="Compartir <?= e((string)$dress['name']) ?>"
        ><span class="share-icon" aria-hidden="true">↗</span><span>Compartir prenda</span></button>

        <a class="btn" href="<?= BASE_URL ?>/index.php?category=<?= rawurlencode($category) ?>&status=all#catalogo">Ver más de <?= e($category) ?></a>
      </div>
    </section>
  </article>

  <?php if ($related): ?>
    <section class="related-products" aria-labelledby="related-title">
      <div class="section-head">
        <div>
          <h2 id="related-title">También puede interesarte</h2>
          <p>Más prendas de la categoría <?= e($category) ?>.</p>
        </div>
      </div>

      <div class="related-grid">
        <?php foreach ($related as $item): ?>
          <?php
            $relatedUrl = BASE_URL . '/product.php?id=' . (int)$item['id'];
            $relatedImage = !empty($item['image']) ? image_public_url($item['image'], 480) : '';
            $relatedFallbacks = !empty($item['image']) ? image_fallback_urls($item['image']) : [];
            $relatedFallbackJson = json_encode($relatedFallbacks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]';
          ?>
          <a class="related-card" href="<?= e($relatedUrl) ?>">
            <div class="related-photo <?= $item['image_fit'] === 'contain' ? 'contain' : '' ?>">
              <?php if ($relatedImage !== ''): ?>
                <img src="<?= e($relatedImage) ?>" data-image-fallbacks="<?= e($relatedFallbackJson) ?>" alt="<?= e((string)$item['name']) ?>" loading="lazy" decoding="async" width="480" height="648">
              <?php else: ?>
                <span>Sin foto</span>
              <?php endif; ?>
            </div>
            <div class="related-info">
              <strong><?= e((string)$item['name']) ?></strong>
              <span>Talla <?= e((string)$item['size']) ?> · <?= e(money_mx($item['price'])) ?></span>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>
</main>

<div class="lightbox" data-lightbox hidden aria-hidden="true">
  <div class="lightbox-backdrop" data-lightbox-close></div>
  <section class="lightbox-dialog" role="dialog" aria-modal="true" aria-label="Fotografía completa de la prenda">
    <div class="lightbox-stage" data-lightbox-stage>
      <div class="lightbox-scroll-content" data-lightbox-content>
        <div class="lightbox-canvas" data-lightbox-canvas>
          <img data-lightbox-image src="" alt="" decoding="async">
        </div>
      </div>
    </div>

    <div class="lightbox-actions" aria-label="Controles de imagen">
      <button class="lightbox-control" type="button" data-zoom-out aria-label="Alejar fotografía">−</button>
      <button class="lightbox-control zoom-level" type="button" data-zoom-reset aria-label="Mostrar fotografía completa"><span data-zoom-level>Completa</span></button>
      <button class="lightbox-control" type="button" data-zoom-in aria-label="Acercar fotografía">+</button>
      <button class="lightbox-control close" type="button" data-lightbox-close aria-label="Cerrar fotografía">×</button>
    </div>

    <div class="lightbox-caption" data-lightbox-title>Detalle de la prenda</div>
    <div class="lightbox-loading" data-lightbox-loading aria-live="polite">Cargando fotografía…</div>
  </section>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
