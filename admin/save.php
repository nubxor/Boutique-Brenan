<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_post_request();
require_login();
verify_csrf();

$pdo = db();
ensure_categories_schema($pdo);

$id = (int)($_POST['id'] ?? 0);
$name = trim((string)($_POST['name'] ?? ''));
$category = normalize_category((string)($_POST['category'] ?? ''));
$size = trim((string)($_POST['size'] ?? ''));
$priceRaw = trim((string)($_POST['price'] ?? ''));
$status = (string)($_POST['status'] ?? 'available');
$soldDate = trim((string)($_POST['sold_date'] ?? ''));
$imageFit = (string)($_POST['image_fit'] ?? 'cover');

$length = static fn(string $value): int => function_exists('mb_strlen')
    ? mb_strlen($value, 'UTF-8')
    : strlen($value);

if (
    $name === '' || $length($name) > 180
    || $category === '' || $length($category) > 60
    || $size === '' || $length($size) > 30
    || preg_match('/[\x00-\x1F\x7F]/u', $name . $category . $size)
    || !is_numeric($priceRaw)
) {
    http_response_code(422);
    exit('Los datos de la prenda no son válidos.');
}

$price = round((float)$priceRaw, 2);
if ($price < 0 || $price > 99_999_999.99) {
    http_response_code(422);
    exit('El precio no es válido.');
}

if (!in_array($status, ['available', 'sold'], true)) {
    $status = 'available';
}

if (!in_array($imageFit, ['cover', 'contain'], true)) {
    $imageFit = 'cover';
}

if ($status === 'sold') {
    if ($soldDate === '') {
        $soldDate = date('Y-m-d');
    }

    $dateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $soldDate);
    if (!$dateObject || $dateObject->format('Y-m-d') !== $soldDate) {
        http_response_code(422);
        exit('La fecha de venta no es válida.');
    }
} else {
    $soldDate = null;
}

$existingImage = '';
if ($id > 0) {
    $existingStmt = $pdo->prepare('SELECT image FROM dresses WHERE id = :id LIMIT 1');
    $existingStmt->execute([':id' => $id]);
    $existing = $existingStmt->fetch();

    if (!$existing) {
        http_response_code(404);
        exit('La prenda que intentas editar ya no existe.');
    }

    $existingImage = basename((string)($existing['image'] ?? ''));
}

$newImage = null;
try {
    $newImage = upload_image($_FILES['image'] ?? []);
    $image = $newImage ?: $existingImage;

    $pdo->beginTransaction();

    if ($id > 0) {
        $stmt = $pdo->prepare(
            'UPDATE dresses SET name=:name, category=:category, size=:size, price=:price, status=:status, sold_date=:sold_date, image=:image, image_fit=:image_fit, updated_at=NOW() WHERE id=:id'
        );
        $stmt->execute([
            ':name' => $name,
            ':category' => $category,
            ':size' => $size,
            ':price' => $price,
            ':status' => $status,
            ':sold_date' => $soldDate,
            ':image' => $image !== '' ? $image : null,
            ':image_fit' => $imageFit,
            ':id' => $id,
        ]);
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO dresses (name, category, size, price, status, sold_date, image, image_fit, created_at, updated_at) VALUES (:name, :category, :size, :price, :status, :sold_date, :image, :image_fit, NOW(), NOW())'
        );
        $stmt->execute([
            ':name' => $name,
            ':category' => $category,
            ':size' => $size,
            ':price' => $price,
            ':status' => $status,
            ':sold_date' => $soldDate,
            ':image' => $image !== '' ? $image : null,
            ':image_fit' => $imageFit,
        ]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($newImage) {
        delete_image_if_exists($newImage);
    }
    error_log('Error al guardar prenda: ' . $e->getMessage());
    http_response_code(500);
    exit('No fue posible guardar la prenda. Inténtalo nuevamente.');
}

if ($newImage && $existingImage !== '' && $newImage !== $existingImage) {
    delete_image_if_exists($existingImage);
}

redirect('/admin/index.php?notice=' . ($id > 0 ? 'updated' : 'created'));
