<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = db();
ensure_categories_schema($pdo);
$sizes = get_sizes($pdo);
$categories = get_categories($pdo, false);

$filters = [
    'q' => trim((string)($_GET['q'] ?? '')),
    'category' => (string)($_GET['category'] ?? 'all'),
    'size' => (string)($_GET['size'] ?? 'all'),
    'min_price' => trim((string)($_GET['min_price'] ?? '')),
    'max_price' => trim((string)($_GET['max_price'] ?? '')),
    'sort' => (string)($_GET['sort'] ?? 'newest'),
];

$filters['status'] = 'available';

$dresses = build_public_query($pdo, $filters);

$availableCount = (int)$pdo->query("SELECT COUNT(*) FROM dresses WHERE status='available'")->fetchColumn();
$categoryCount = (int)$pdo->query("SELECT COUNT(DISTINCT category) FROM dresses WHERE category <> ''")->fetchColumn();

$categoryCountSql = "SELECT category, COUNT(*) AS total FROM dresses WHERE status = 'available' GROUP BY category ORDER BY category ASC";
$categoryCountParams = [];
$categoryCountStmt = $pdo->prepare($categoryCountSql);
$categoryCountStmt->execute($categoryCountParams);
$categoryCounts = [];
foreach ($categoryCountStmt->fetchAll() as $row) {
    $categoryCounts[normalize_category((string)$row['category'])] = (int)$row['total'];
}
$visibleCategoryTotal = array_sum($categoryCounts);

$groups = [];
foreach ($dresses as $dress) {
    $category = normalize_category((string)($dress['category'] ?? 'Otros'));
    $groups[$category][] = $dress;
}

$advancedFiltersActive =
    $filters['size'] !== 'all' ||
    $filters['min_price'] !== '' ||
    $filters['max_price'] !== '' ||
    $filters['sort'] !== 'newest';

$showNewArrivals =
    $filters['q'] === '' &&
    $filters['category'] === 'all' &&
    $filters['size'] === 'all' &&
    $filters['min_price'] === '' &&
    $filters['max_price'] === '' &&
    $filters['sort'] === 'newest';

$newArrivals = $showNewArrivals ? array_slice($dresses, 0, 6) : [];


$heroFeature = $newArrivals[0] ?? ($dresses[0] ?? null);
$heroFeatureImage = '';
$heroFeatureFallbackJson = '[]';
$heroFeatureUrl = '';
if ($heroFeature) {
    $heroFeatureUrl = BASE_URL . '/product.php?id=' . (int)$heroFeature['id'];
    if (!empty($heroFeature['image'])) {
        $heroFeatureImage = image_public_url($heroFeature['image'], 480);
        $heroFeatureFallbacks = image_fallback_urls($heroFeature['image']);
        $heroFeatureFallbackJson = json_encode($heroFeatureFallbacks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]';
    }
}

$catalogUrl = static function (array $changes = [], array $remove = []) use ($filters): string {
    $params = array_merge($filters, $changes);
    foreach ($remove as $key) {
        unset($params[$key]);
    }
    foreach ($params as $key => $value) {
        if ($value === '' || ($value === 'all' && in_array($key, ['size', 'category'], true))) {
            unset($params[$key]);
        }
    }
    return BASE_URL . '/index.php?' . http_build_query($params) . '#catalogo';
};

$page_title = 'Catálogo Digital | Brennan Boutique';
include __DIR__ . '/includes/header.php';
?>

<header class="hero compact-hero">
  <div class="wrap">
    <nav class="topbar topbar-editorial">
      <a class="brand" href="<?= BASE_URL ?>/index.php" aria-label="Brennan Boutique">
        <img class="brand-mark" src="<?= BASE_URL ?>/assets/img/logo-brennan-boutique-v2.png" alt="Brennan Boutique">
        <span class="brand-copy">
          <strong>Brennan Boutique</strong>
          <small>Catálogo digital de prendas</small>
        </span>
      </a>

      <div class="top-actions top-actions-editorial">
        <button type="button" class="icon-action" data-focus-search aria-label="Buscar prendas">
          <span aria-hidden="true">⌕</span>
        </button>
        <a class="icon-action" href="<?= BASE_URL ?>/index.php?view=favorites#catalogo" aria-label="Ver favoritos">
          <span aria-hidden="true">♡</span>
        </a>
        <?php if (is_logged_in()): ?>
          <a class="btn" href="<?= BASE_URL ?>/admin/index.php">Administrar</a>
        <?php endif; ?>
      </div>
    </nav>

    <section class="editorial-hero" aria-labelledby="editorial-hero-title">
      <div class="editorial-copy">
        <a class="editorial-brand-lockup" href="<?= BASE_URL ?>/index.php" aria-label="Brennan Boutique">
          <img src="<?= BASE_URL ?>/assets/img/logo-brennan-boutique-v2.png" alt="Brennan Boutique">
          <span>Brennan Boutique</span>
        </a>
                <h1 id="editorial-hero-title" class="editorial-title">Encuentra algo especial para ti.</h1>
        <p class="editorial-subtitle">Prendas seleccionadas por Brennan Boutique para ayudarte a descubrir el estilo ideal de forma rápida y bonita.</p>

        <div class="editorial-meta" aria-label="Resumen del catálogo">
          <span class="editorial-pill"><strong><?= $availableCount ?></strong> disponibles</span>
          <span class="editorial-pill"><strong><?= $categoryCount ?></strong> categorías</span>
        </div>

        <div class="editorial-actions">
          <button class="btn primary" type="button" data-focus-search>Buscar prendas</button>
          <a class="btn soft" href="#categorias">Ver categorías</a>
        </div>
      </div>

      <?php if ($heroFeature): ?>
        <article class="hero-feature-card">
          <a class="hero-feature-photo <?= ($heroFeature['image_fit'] ?? '') === 'contain' ? 'contain' : '' ?> <?= $heroFeatureImage !== '' ? 'image-pending' : '' ?>" href="<?= e($heroFeatureUrl) ?>">
            <?php if ($heroFeatureImage !== ''): ?>
              <img
                src="<?= e($heroFeatureImage) ?>"
                data-image-fallbacks="<?= e($heroFeatureFallbackJson) ?>"
                alt="<?= e((string)$heroFeature['name']) ?>"
                loading="eager"
                fetchpriority="high"
                decoding="async"
                width="480"
                height="648"
              >
            <?php else: ?>
              <span>Sin foto</span>
            <?php endif; ?>
          </a>

          <div class="hero-feature-content">
            <span class="hero-feature-tag">Nuevo ingreso</span>
            <h2><a href="<?= e($heroFeatureUrl) ?>"><?= e((string)$heroFeature['name']) ?></a></h2>
            <p><?= e((string)($heroFeature['category'] ?? 'Prenda')) ?> · Talla <?= e((string)$heroFeature['size']) ?></p>
            <div class="hero-feature-footer">
              <strong><?= e(money_mx($heroFeature['price'])) ?></strong>
              <a class="btn soft small" href="<?= e($heroFeatureUrl) ?>">Ver prenda</a>
            </div>
          </div>
        </article>
      <?php endif; ?>
    </section>
  </div>
</header>

<section class="catalog-controls" id="catalogo">
  <div class="wrap">
    <div class="category-browser" id="categorias">
      <div class="category-browser-head">
        <div>
          <span class="catalog-kicker">Explorar por categoría</span>
          <h2>¿Qué tipo de prenda buscas?</h2>
        </div>
        <?php if ($filters['category'] !== 'all' || $filters['q'] !== '' || $advancedFiltersActive): ?>
          <a class="clear-catalog-link" href="<?= BASE_URL ?>/index.php#catalogo">Limpiar selección</a>
        <?php endif; ?>
      </div>

      <nav class="category-chips" aria-label="Categorías de ropa" data-category-chips>
        <a
          class="category-chip <?= $filters['category'] === 'all' ? 'is-active' : '' ?>"
          href="<?= e($catalogUrl(['category' => 'all'], ['size', 'min_price', 'max_price'])) ?>"
          <?= $filters['category'] === 'all' ? 'aria-current="page"' : '' ?>
        >
          <span>Todas</span>
          <strong><?= $visibleCategoryTotal ?></strong>
        </a>
        <?php foreach ($categories as $category): ?>
          <?php $count = $categoryCounts[$category] ?? 0; ?>
          <a
            class="category-chip <?= $filters['category'] === $category ? 'is-active' : '' ?> <?= $count === 0 ? 'is-empty' : '' ?>"
            href="<?= e($catalogUrl(['category' => $category], ['size', 'min_price', 'max_price'])) ?>"
            <?= $filters['category'] === $category ? 'aria-current="page"' : '' ?>
          >
            <span><?= e($category) ?></span>
            <strong><?= $count ?></strong>
          </a>
        <?php endforeach; ?>
      </nav>
    </div>

    <div class="search-panel">
      <form class="catalog-search-form" method="get" action="<?= BASE_URL ?>/index.php" data-catalog-search>
        <input type="hidden" name="category" value="<?= e($filters['category']) ?>">

        <label class="main-search-field">
          <span class="sr-only">Buscar prendas</span>
          <span class="search-symbol" aria-hidden="true">⌕</span>
          <input
            id="busqueda-prendas"
            type="search"
            name="q"
            value="<?= e($filters['q']) ?>"
            placeholder="Buscar por nombre, categoría o talla"
            autocomplete="off"
          >
        </label>
        <button class="btn primary search-submit" type="submit">Buscar</button>

        <details class="advanced-filter-panel" <?= $advancedFiltersActive ? 'open' : '' ?>>
          <summary>
            <span>Filtros adicionales</span>
            <?php if ($advancedFiltersActive): ?><span class="active-filter-dot">Activos</span><?php endif; ?>
          </summary>

          <div class="advanced-filter-grid">
            <label>
              <span>Talla</span>
              <select name="size">
                <option value="all">Todas</option>
                <?php foreach ($sizes as $size): ?>
                  <option value="<?= e($size) ?>" <?= selected($filters['size'], $size) ?>><?= e($size) ?></option>
                <?php endforeach; ?>
              </select>
            </label>

            <label>
              <span>Precio mínimo</span>
              <input type="number" name="min_price" min="0" value="<?= e($filters['min_price']) ?>" placeholder="$0">
            </label>

            <label>
              <span>Precio máximo</span>
              <input type="number" name="max_price" min="0" value="<?= e($filters['max_price']) ?>" placeholder="$9999">
            </label>

            <label>
              <span>Ordenar</span>
              <select name="sort">
                <option value="newest" <?= selected($filters['sort'], 'newest') ?>>Más recientes</option>
                <option value="price-asc" <?= selected($filters['sort'], 'price-asc') ?>>Menor precio</option>
                <option value="price-desc" <?= selected($filters['sort'], 'price-desc') ?>>Mayor precio</option>
                <option value="size" <?= selected($filters['sort'], 'size') ?>>Por talla</option>
                <option value="category" <?= selected($filters['sort'], 'category') ?>>Por categoría</option>
              </select>
            </label>

            <div class="advanced-filter-actions">
              <button class="btn primary" type="submit">Aplicar filtros</button>
              <a class="btn" href="<?= e($catalogUrl([], ['size', 'min_price', 'max_price', 'sort'])) ?>">Restablecer</a>
            </div>
          </div>
        </details>
      </form>
    </div>
  </div>
</section>

<?php $catalogImageIndex = 0; ?>
<?php if ($newArrivals): ?>
<section class="new-arrivals wrap" aria-labelledby="new-arrivals-title">
  <div class="new-arrivals-head">
    <div>
      <span class="catalog-kicker">Recién agregadas</span>
      <h2 id="new-arrivals-title">Nuevos ingresos</h2>
    </div>
    <a href="#catalogo" class="clear-catalog-link">Ver todo el catálogo</a>
  </div>

  <div class="new-arrivals-strip" aria-label="Nuevos ingresos">
    <?php foreach ($newArrivals as $newDress): ?>
      <?php
        $newProductUrl = BASE_URL . '/product.php?id=' . (int)$newDress['id'];
        $newImage = !empty($newDress['image']) ? image_public_url($newDress['image'], 480) : '';
        $newFallbacks = !empty($newDress['image']) ? image_fallback_urls($newDress['image']) : [];
        $newFallbackJson = json_encode($newFallbacks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]';
      ?>
      <article class="new-arrival-card" data-favorite-item="<?= (int)$newDress['id'] ?>">
        <a class="new-arrival-photo <?= $newDress['image_fit'] === 'contain' ? 'contain' : '' ?> <?= $newImage !== '' ? 'image-pending' : '' ?>" href="<?= e($newProductUrl) ?>">
          <?php if ($newImage !== ''): ?>
            <img
              src="<?= e($newImage) ?>"
              data-image-fallbacks="<?= e($newFallbackJson) ?>"
              alt="<?= e((string)$newDress['name']) ?>"
              loading="lazy"
              decoding="async"
              width="320"
              height="432"
            >
          <?php else: ?>
            <span>Sin foto</span>
          <?php endif; ?>
        </a>
        <button
          class="favorite-button favorite-button-small"
          type="button"
          data-favorite-toggle
          data-product-id="<?= (int)$newDress['id'] ?>"
          data-product-name="<?= e((string)$newDress['name']) ?>"
          aria-pressed="false"
          aria-label="Guardar <?= e((string)$newDress['name']) ?> en favoritos"
        ><span aria-hidden="true">♡</span></button>
        <a class="new-arrival-info" href="<?= e($newProductUrl) ?>">
          <strong><?= e((string)$newDress['name']) ?></strong>
          <span>Talla <?= e((string)$newDress['size']) ?> · <?= e(money_mx($newDress['price'])) ?></span>
        </a>
      </article>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<main class="wrap catalog">
  <div class="catalog-summary" aria-live="polite">
    <div>
      <strong><?= count($dresses) ?></strong>
      <span>prenda<?= count($dresses) === 1 ? '' : 's' ?> encontrada<?= count($dresses) === 1 ? '' : 's' ?></span>
    </div>
    <div class="catalog-summary-copy">
      <?php if ($filters['category'] !== 'all'): ?>
        Categoría: <strong><?= e($filters['category']) ?></strong>
      <?php else: ?>
        Todas las categorías
      <?php endif; ?>
      <?php if ($filters['q'] !== ''): ?>
        · Búsqueda: <strong><?= e($filters['q']) ?></strong>
      <?php endif; ?>
    </div>
  </div>

  <div class="favorites-mode-banner" data-favorites-banner hidden>
    <span>Mostrando tus prendas favoritas guardadas en este dispositivo.</span>
    <a class="btn small" href="<?= BASE_URL ?>/index.php#catalogo">Ver todas</a>
  </div>

  <div class="favorites-empty" data-favorites-empty hidden>
    <span class="favorites-empty-icon" aria-hidden="true">♡</span>
    <h2>Aún no guardas prendas favoritas</h2>
    <p>Toca el corazón de una prenda para encontrarla rápidamente después.</p>
  </div>

  <?php if (!$dresses): ?>
    <div class="empty">
      <h2>No hay prendas con esos filtros</h2>
      <p>Ajusta la búsqueda o agrega prendas desde el panel administrativo.</p>
    </div>
  <?php endif; ?>

  <?php foreach ($groups as $title => $items): ?>
    <section class="size-section" data-product-section>
      <div class="section-head">
        <div>
          <h2><?= e($title) ?></h2>
          <p><?= count($items) ?> prenda<?= count($items) === 1 ? '' : 's' ?> encontrada<?= count($items) === 1 ? '' : 's' ?></p>
        </div>
        <span class="pill"><?= 'Categoría' ?></span>
      </div>

      <div class="grid">
        <?php foreach ($items as $dress): ?>
          <?php $productUrl = BASE_URL . '/product.php?id=' . (int)$dress['id']; ?>
          <article
            id="prenda-<?= (int)$dress['id'] ?>"
            class="dress-card"
            data-product-card
            data-favorite-item="<?= (int)$dress['id'] ?>"
            data-product-category="<?= e((string)$dress['category']) ?>"
          >

            <button
              class="favorite-button"
              type="button"
              data-favorite-toggle
              data-product-id="<?= (int)$dress['id'] ?>"
              data-product-name="<?= e((string)$dress['name']) ?>"
              aria-pressed="false"
              aria-label="Guardar <?= e((string)$dress['name']) ?> en favoritos"
            ><span aria-hidden="true">♡</span></button>

            <div class="photo <?= $dress['image_fit'] === 'contain' ? 'contain' : '' ?> <?= !empty($dress['image']) ? 'image-pending' : '' ?>">
              <?php if (!empty($dress['image'])): ?>
                <?php
                  $catalogImageIndex++;
                  $thumbnailUrl = image_public_url($dress['image'], 480);
                  $fallbackSrcset = image_srcset($dress['image'], 'fallback');
                  $webpSrcset = image_srcset($dress['image'], 'webp');
                  $imageFallbacks = image_fallback_urls($dress['image']);
                  $imageFallbackJson = json_encode($imageFallbacks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]';
                  $isPriorityImage = $catalogImageIndex <= 2;
                ?>
                <button
                  class="photo-zoom"
                  type="button"
                  data-lightbox-src="<?= e(image_public_url($dress['image'])) ?>"
                  data-lightbox-fallbacks="<?= e($imageFallbackJson) ?>"
                  data-lightbox-alt="<?= e($dress['name']) ?>"
                  aria-label="Ampliar fotografía de <?= e($dress['name']) ?>"
                >
                  <picture>
                    <?php if ($webpSrcset !== ''): ?>
                      <source
                        type="image/webp"
                        srcset="<?= e($webpSrcset) ?>"
                        sizes="(max-width: 460px) calc(100vw - 22px), (max-width: 760px) calc(50vw - 23px), (max-width: 1100px) calc(33vw - 28px), 280px"
                      >
                    <?php endif; ?>
                    <img
                      class="catalog-image"
                      src="<?= e($thumbnailUrl) ?>"
                      <?php if ($fallbackSrcset !== ''): ?>srcset="<?= e($fallbackSrcset) ?>"<?php endif; ?>
                      sizes="(max-width: 460px) calc(100vw - 22px), (max-width: 760px) calc(50vw - 23px), (max-width: 1100px) calc(33vw - 28px), 280px"
                      data-image-fallbacks="<?= e($imageFallbackJson) ?>"
                      alt="<?= e($dress['name']) ?>"
                      width="480"
                      height="648"
                      loading="<?= $isPriorityImage ? 'eager' : 'lazy' ?>"
                      fetchpriority="<?= $isPriorityImage ? 'high' : 'low' ?>"
                      decoding="async"
                    >
                  </picture>
                </button>
              <?php else: ?>
                <div class="no-photo">Sin foto</div>
              <?php endif; ?>

              <div class="card-bar">
                <strong>Talla <?= e($dress['size']) ?></strong>
                <strong><?= money_mx($dress['price']) ?></strong>
              </div>
            </div>

            <div class="dress-info">
              <h3><a class="product-name-link" href="<?= e($productUrl) ?>"><?= e($dress['name']) ?></a></h3>
              <div class="meta">
                <span class="badge category"><?= e((string)$dress['category']) ?></span>
                <span class="badge available">Disponible</span>
              </div>

              <div class="card-actions">
                <a class="btn primary small product-detail-link" href="<?= e($productUrl) ?>">Ver detalles</a>
                <button
                  class="btn soft small share-product"
                  type="button"
                  data-share-product
                  data-product-id="<?= (int)$dress['id'] ?>"
                  data-product-url="<?= e($productUrl) ?>"
                  data-product-name="<?= e($dress['name']) ?>"
                  data-product-category="<?= e((string)$dress['category']) ?>"
                  data-product-size="<?= e((string)$dress['size']) ?>"
                  data-product-price="<?= e(money_mx($dress['price'])) ?>"
                  data-product-status="Disponible"
                  aria-label="Compartir <?= e($dress['name']) ?>"
                >
                  <span class="share-icon" aria-hidden="true">↗</span>
                  <span>Compartir</span>
                </button>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>
</main>

<nav class="mobile-bottom-nav" aria-label="Navegación rápida">
  <a href="<?= BASE_URL ?>/index.php" class="mobile-nav-item">
    <span aria-hidden="true">⌂</span><small>Inicio</small>
  </a>
  <a href="#categorias" class="mobile-nav-item">
    <span aria-hidden="true">▦</span><small>Categorías</small>
  </a>
  <button type="button" class="mobile-nav-item" data-focus-search>
    <span aria-hidden="true">⌕</span><small>Buscar</small>
  </button>
  <a href="<?= BASE_URL ?>/index.php?view=favorites#catalogo" class="mobile-nav-item" data-favorites-link>
    <span aria-hidden="true">♡</span><small>Favoritos</small><b data-favorites-count>0</b>
  </a>
</nav>

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
      <button class="lightbox-control zoom-level" type="button" data-zoom-reset aria-label="Mostrar fotografía completa">
        <span data-zoom-level>Completa</span>
      </button>
      <button class="lightbox-control" type="button" data-zoom-in aria-label="Acercar fotografía">+</button>
      <button class="lightbox-control close" type="button" data-lightbox-close aria-label="Cerrar fotografía">×</button>
    </div>

    <div class="lightbox-caption" data-lightbox-title>Detalle de la prenda</div>
    <div class="lightbox-loading" data-lightbox-loading aria-live="polite">Cargando fotografía…</div>
  </section>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
