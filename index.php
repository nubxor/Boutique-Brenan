<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = db();
$sizes = get_sizes($pdo);

$filters = [
    'q' => trim((string)($_GET['q'] ?? '')),
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
$sizeCount = (int)$pdo->query("SELECT COUNT(DISTINCT size) FROM dresses")->fetchColumn();

$groups = [];
foreach ($dresses as $dress) {
    $group = ($dress['status'] === 'sold' && $filters['status'] !== 'available')
        ? 'Vendidos · Talla ' . $dress['size']
        : 'Talla ' . $dress['size'];
    $groups[$group][] = $dress;
}

$page_title = 'Catálogo Digital | Brenan Boutique';
include __DIR__ . '/includes/header.php';
?>

<header class="hero">
  <div class="wrap">
    <nav class="topbar">
      <a class="brand" href="<?= BASE_URL ?>/index.php" aria-label="Brenan Boutique">
        <span class="logo">BB</span>
        <span>
          <strong>Brenan Boutique</strong>
          <small>Catálogo digital de vestidos</small>
        </span>
      </a>

      <div class="top-actions">
        <a class="btn" href="#catalogo">Ver catálogo</a>
        <?php if (is_logged_in()): ?>
          <a class="btn primary" href="<?= BASE_URL ?>/admin/index.php">Panel administrativo</a>
        <?php else: ?>
          <a class="btn primary" href="<?= BASE_URL ?>/admin/login.php">Administrar</a>
        <?php endif; ?>
      </div>
    </nav>

    <section class="hero-card">
      <div>
        <span class="eyebrow">✦ Colección disponible</span>
        <h1>Vestidos listos para compartir con tus clientas.</h1>
        <p>Consulta vestidos por talla, precio y disponibilidad. Las fotografías se ajustan al catálogo sin deformarse y cada tarjeta muestra claramente talla y precio.</p>

        <div class="hero-buttons">
          <a class="btn primary" href="#catalogo">Explorar vestidos</a>
          <a class="btn" href="<?= BASE_URL ?>/admin/login.php">Entrar al panel</a>
        </div>
      </div>

      <div class="stats">
        <div class="stat"><strong><?= $availableCount ?></strong><span>Disponibles</span></div>
        <div class="stat"><strong><?= $soldCount ?></strong><span>Vendidos</span></div>
        <div class="stat"><strong><?= $sizeCount ?></strong><span>Tallas</span></div>
      </div>
    </section>
  </div>
</header>

<section class="toolbar" id="catalogo">
  <div class="wrap">
    <button class="btn primary block mobile-filter-toggle" type="button" data-toggle-filters>☰ Mostrar filtros</button>

    <form class="filters" method="get" action="<?= BASE_URL ?>/index.php" data-filters>
      <label>
        <span>Buscar</span>
        <input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Nombre o talla...">
      </label>

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
        <span>Disponibilidad</span>
        <select name="status">
          <option value="available" <?= selected($filters['status'], 'available') ?>>Disponibles</option>
          <option value="all" <?= selected($filters['status'], 'all') ?>>Todos</option>
          <option value="sold" <?= selected($filters['status'], 'sold') ?>>Vendidos</option>
        </select>
      </label>

      <label>
        <span>Precio mín.</span>
        <input type="number" name="min_price" min="0" value="<?= e($filters['min_price']) ?>" placeholder="$0">
      </label>

      <label>
        <span>Precio máx.</span>
        <input type="number" name="max_price" min="0" value="<?= e($filters['max_price']) ?>" placeholder="$9999">
      </label>

      <label>
        <span>Fecha venta</span>
        <input type="date" name="sold_date" value="<?= e($filters['sold_date']) ?>">
      </label>

      <label>
        <span>Ordenar</span>
        <select name="sort">
          <option value="newest" <?= selected($filters['sort'], 'newest') ?>>Más recientes</option>
          <option value="price-asc" <?= selected($filters['sort'], 'price-asc') ?>>Menor precio</option>
          <option value="price-desc" <?= selected($filters['sort'], 'price-desc') ?>>Mayor precio</option>
          <option value="size" <?= selected($filters['sort'], 'size') ?>>Por talla</option>
          <option value="sold-date" <?= selected($filters['sort'], 'sold-date') ?>>Fecha de venta</option>
        </select>
      </label>

      <button class="btn primary" type="submit">Aplicar</button>
      <a class="btn" href="<?= BASE_URL ?>/index.php#catalogo">Limpiar</a>
    </form>
  </div>
</section>

<main class="wrap catalog">
  <?php if (!$dresses): ?>
    <div class="empty">
      <h2>No hay vestidos con esos filtros</h2>
      <p>Ajusta la búsqueda o agrega vestidos desde el panel administrativo.</p>
    </div>
  <?php endif; ?>

  <?php foreach ($groups as $title => $items): ?>
    <section class="size-section">
      <div class="section-head">
        <div>
          <h2><?= e($title) ?></h2>
          <p><?= count($items) ?> vestido<?= count($items) === 1 ? '' : 's' ?> encontrado<?= count($items) === 1 ? '' : 's' ?></p>
        </div>
        <span class="pill"><?= str_contains($title, 'Vendidos') ? 'Historial' : 'Disponible' ?></span>
      </div>

      <div class="grid">
        <?php foreach ($items as $dress): ?>
          <article class="dress-card <?= $dress['status'] === 'sold' ? 'is-sold' : '' ?>">
            <?php if ($dress['status'] === 'sold'): ?>
              <div class="sold-ribbon">Vendido</div>
            <?php endif; ?>

            <div class="photo <?= $dress['image_fit'] === 'contain' ? 'contain' : '' ?>">
              <?php if (!empty($dress['image'])): ?>
                <button
                  class="photo-zoom"
                  type="button"
                  data-lightbox-src="<?= UPLOAD_URL . '/' . e($dress['image']) ?>"
                  data-lightbox-alt="<?= e($dress['name']) ?>"
                  aria-label="Ampliar fotografía de <?= e($dress['name']) ?>"
                >
                  <img src="<?= UPLOAD_URL . '/' . e($dress['image']) ?>" alt="<?= e($dress['name']) ?>" loading="lazy">
                  <span class="zoom-hint" aria-hidden="true">⌕ Ver detalle</span>
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
              <h3><?= e($dress['name']) ?></h3>
              <div class="meta">
                <span class="badge <?= $dress['status'] === 'sold' ? 'sold' : 'available' ?>"><?= status_label($dress['status']) ?></span>
                <?php if ($dress['status'] === 'sold' && !empty($dress['sold_date'])): ?>
                  <span class="badge">Venta: <?= e($dress['sold_date']) ?></span>
                <?php endif; ?>
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
  <section class="lightbox-dialog" role="dialog" aria-modal="true" aria-label="Vista ampliada de la prenda">
    <div class="lightbox-toolbar">
      <div class="lightbox-title" data-lightbox-title>Detalle de la prenda</div>
      <div class="lightbox-actions" aria-label="Controles de zoom">
        <button class="lightbox-control" type="button" data-zoom-out aria-label="Alejar imagen">−</button>
        <button class="lightbox-control zoom-level" type="button" data-zoom-reset aria-label="Restablecer zoom">100%</button>
        <button class="lightbox-control" type="button" data-zoom-in aria-label="Acercar imagen">+</button>
        <button class="lightbox-control close" type="button" data-lightbox-close aria-label="Cerrar imagen">×</button>
      </div>
    </div>
    <div class="lightbox-stage" data-lightbox-stage>
      <img data-lightbox-image src="" alt="">
    </div>
    <p class="lightbox-help">Usa los botones + y −, la rueda del mouse o toca dos veces la imagen para acercar.</p>
  </section>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
