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
    'status' => (string)($_GET['status'] ?? 'available'),
    'min_price' => trim((string)($_GET['min_price'] ?? '')),
    'max_price' => trim((string)($_GET['max_price'] ?? '')),
    'sold_date' => trim((string)($_GET['sold_date'] ?? '')),
    'sort' => (string)($_GET['sort'] ?? 'newest'),
];

$allowedStatus = ['available', 'sold', 'all'];
if (!in_array($filters['status'], $allowedStatus, true)) {
    $filters['status'] = 'available';
}

$dresses = build_public_query($pdo, $filters);

$availableCount = (int)$pdo->query("SELECT COUNT(*) FROM dresses WHERE status='available'")->fetchColumn();
$soldCount = (int)$pdo->query("SELECT COUNT(*) FROM dresses WHERE status='sold'")->fetchColumn();
$categoryCount = (int)$pdo->query("SELECT COUNT(DISTINCT category) FROM dresses WHERE category <> ''")->fetchColumn();

$categoryCountSql = "SELECT category, COUNT(*) AS total FROM dresses";
$categoryCountParams = [];
if ($filters['status'] !== 'all') {
    $categoryCountSql .= " WHERE status = :status";
    $categoryCountParams['status'] = $filters['status'];
}
$categoryCountSql .= " GROUP BY category ORDER BY category ASC";
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
    $group = ($dress['status'] === 'sold' && $filters['status'] === 'all')
        ? $category . ' · Vendidos'
        : $category;
    $groups[$group][] = $dress;
}

$advancedFiltersActive =
    $filters['size'] !== 'all' ||
    $filters['min_price'] !== '' ||
    $filters['max_price'] !== '' ||
    $filters['sold_date'] !== '' ||
    $filters['sort'] !== 'newest';

$catalogUrl = static function (array $changes = [], array $remove = []) use ($filters): string {
    $params = array_merge($filters, $changes);
    foreach ($remove as $key) {
        unset($params[$key]);
    }
    foreach ($params as $key => $value) {
        if ($value === '' || ($value === 'all' && in_array($key, ['size'], true))) {
            unset($params[$key]);
        }
    }
    return BASE_URL . '/index.php?' . http_build_query($params) . '#catalogo';
};

$page_title = 'Catálogo Digital | Brenan Boutique';
include __DIR__ . '/includes/header.php';
?>

<header class="hero compact-hero">
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
        <a class="btn primary" href="#catalogo">Explorar catálogo</a>
        <?php if (is_logged_in()): ?>
          <a class="btn" href="<?= BASE_URL ?>/admin/index.php">Administrar</a>
        <?php endif; ?>
      </div>
    </nav>

    <section class="hero-card hero-card-compact">
      <div>
        <span class="eyebrow">✦ Brenan Boutique</span>
        <h1>Prendas seleccionadas para hacer especial cada momento.</h1>
        <p>Busca por nombre o entra directamente a una categoría. Los filtros adicionales permanecen disponibles sin ocupar espacio en pantalla.</p>
      </div>

      <div class="stats compact-stats" aria-label="Resumen del catálogo">
        <div class="stat"><strong><?= $availableCount ?></strong><span>Disponibles</span></div>
        <div class="stat"><strong><?= $soldCount ?></strong><span>Vendidos</span></div>
        <div class="stat"><strong><?= $categoryCount ?></strong><span>Categorías</span></div>
      </div>
    </section>
  </div>
</header>

<section class="catalog-controls" id="catalogo">
  <div class="wrap">
    <div class="category-browser">
      <div class="category-browser-head">
        <div>
          <span class="catalog-kicker">Explorar por categoría</span>
          <h2>¿Qué tipo de prenda buscas?</h2>
        </div>
        <?php if ($filters['category'] !== 'all' || $filters['q'] !== '' || $advancedFiltersActive || $filters['status'] !== 'available'): ?>
          <a class="clear-catalog-link" href="<?= BASE_URL ?>/index.php#catalogo">Limpiar selección</a>
        <?php endif; ?>
      </div>

      <nav class="category-chips" aria-label="Categorías de ropa" data-category-chips>
        <a
          class="category-chip <?= $filters['category'] === 'all' ? 'is-active' : '' ?>"
          href="<?= e($catalogUrl(['category' => 'all'], ['size', 'min_price', 'max_price', 'sold_date'])) ?>"
          <?= $filters['category'] === 'all' ? 'aria-current="page"' : '' ?>
        >
          <span>Todas</span>
          <strong><?= $visibleCategoryTotal ?></strong>
        </a>
        <?php foreach ($categories as $category): ?>
          <?php $count = $categoryCounts[$category] ?? 0; ?>
          <a
            class="category-chip <?= $filters['category'] === $category ? 'is-active' : '' ?> <?= $count === 0 ? 'is-empty' : '' ?>"
            href="<?= e($catalogUrl(['category' => $category], ['size', 'min_price', 'max_price', 'sold_date'])) ?>"
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
        <input type="hidden" name="status" value="<?= e($filters['status']) ?>">

        <label class="main-search-field">
          <span class="sr-only">Buscar prendas</span>
          <span class="search-symbol" aria-hidden="true">⌕</span>
          <input
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
              <span>Fecha de venta</span>
              <input type="date" name="sold_date" value="<?= e($filters['sold_date']) ?>">
            </label>

            <label>
              <span>Ordenar</span>
              <select name="sort">
                <option value="newest" <?= selected($filters['sort'], 'newest') ?>>Más recientes</option>
                <option value="price-asc" <?= selected($filters['sort'], 'price-asc') ?>>Menor precio</option>
                <option value="price-desc" <?= selected($filters['sort'], 'price-desc') ?>>Mayor precio</option>
                <option value="size" <?= selected($filters['sort'], 'size') ?>>Por talla</option>
                <option value="category" <?= selected($filters['sort'], 'category') ?>>Por categoría</option>
                <option value="sold-date" <?= selected($filters['sort'], 'sold-date') ?>>Fecha de venta</option>
              </select>
            </label>

            <div class="advanced-filter-actions">
              <button class="btn primary" type="submit">Aplicar filtros</button>
              <a class="btn" href="<?= e($catalogUrl([], ['size', 'min_price', 'max_price', 'sold_date', 'sort'])) ?>">Restablecer</a>
            </div>
          </div>
        </details>
      </form>

      <nav class="status-tabs" aria-label="Disponibilidad">
        <a class="status-tab <?= $filters['status'] === 'available' ? 'is-active' : '' ?>" href="<?= e($catalogUrl(['status' => 'available'], ['sold_date'])) ?>">
          Disponibles <strong><?= $availableCount ?></strong>
        </a>
        <a class="status-tab <?= $filters['status'] === 'all' ? 'is-active' : '' ?>" href="<?= e($catalogUrl(['status' => 'all'], ['sold_date'])) ?>">
          Todas <strong><?= $availableCount + $soldCount ?></strong>
        </a>
        <a class="status-tab <?= $filters['status'] === 'sold' ? 'is-active' : '' ?>" href="<?= e($catalogUrl(['status' => 'sold'])) ?>">
          Vendidas <strong><?= $soldCount ?></strong>
        </a>
      </nav>
    </div>
  </div>
</section>

<?php $catalogImageIndex = 0; ?>
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

  <?php if (!$dresses): ?>
    <div class="empty">
      <h2>No hay prendas con esos filtros</h2>
      <p>Ajusta la búsqueda o agrega prendas desde el panel administrativo.</p>
    </div>
  <?php endif; ?>

  <?php foreach ($groups as $title => $items): ?>
    <section class="size-section">
      <div class="section-head">
        <div>
          <h2><?= e($title) ?></h2>
          <p><?= count($items) ?> prenda<?= count($items) === 1 ? '' : 's' ?> encontrada<?= count($items) === 1 ? '' : 's' ?></p>
        </div>
        <span class="pill"><?= str_contains($title, 'Vendidos') ? 'Historial' : 'Categoría' ?></span>
      </div>

      <div class="grid">
        <?php foreach ($items as $dress): ?>
          <?php $productUrl = BASE_URL . '/product.php?id=' . (int)$dress['id']; ?>
          <article
            id="prenda-<?= (int)$dress['id'] ?>"
            class="dress-card <?= $dress['status'] === 'sold' ? 'is-sold' : '' ?>"
            data-product-card
          >
            <?php if ($dress['status'] === 'sold'): ?>
              <div class="sold-ribbon">Vendido</div>
            <?php endif; ?>

            <div class="photo <?= $dress['image_fit'] === 'contain' ? 'contain' : '' ?>">
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
                <span class="badge <?= $dress['status'] === 'sold' ? 'sold' : 'available' ?>"><?= status_label($dress['status']) ?></span>
                <?php if ($dress['status'] === 'sold' && !empty($dress['sold_date'])): ?>
                  <span class="badge">Venta: <?= e($dress['sold_date']) ?></span>
                <?php endif; ?>
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
                  data-product-status="<?= e(status_label($dress['status'])) ?>"
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
