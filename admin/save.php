<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
verify_csrf();

$pdo = db();
ensure_categories_schema($pdo);

$id = (int)($_POST['id'] ?? 0);
$name = trim((string)($_POST['name'] ?? ''));
$category = normalize_category((string)($_POST['category'] ?? ''));
$size = trim((string)($_POST['size'] ?? ''));
$price = (float)($_POST['price'] ?? 0);
$status = (string)($_POST['status'] ?? 'available');
$soldDate = trim((string)($_POST['sold_date'] ?? ''));
$imageFit = (string)($_POST['image_fit'] ?? 'cover');
$currentImage = trim((string)($_POST['current_image'] ?? ''));

if ($name === '' || $category === '' || $size === '' || $price < 0) {
    exit('Faltan datos obligatorios.');
}

if (!in_array($status, ['available', 'sold'], true)) {
    $status = 'available';
}

if (!in_array($imageFit, ['cover', 'contain'], true)) {
    $imageFit = 'cover';
}

if ($status === 'sold' && $soldDate === '') {
    $soldDate = date('Y-m-d');
}

if ($status === 'available') {
    $soldDate = null;
}

try {
    $newImage = upload_image($_FILES['image'] ?? []);
} catch (RuntimeException $e) {
    exit($e->getMessage());
}

$image = $newImage ?: $currentImage;

if ($id > 0) {
    $stmt = $pdo->prepare("UPDATE dresses SET name=:name, category=:category, size=:size, price=:price, status=:status, sold_date=:sold_date, image=:image, image_fit=:image_fit, updated_at=NOW() WHERE id=:id");
    $stmt->execute([
        ':name' => $name,
        ':category' => $category,
        ':size' => $size,
        ':price' => $price,
        ':status' => $status,
        ':sold_date' => $soldDate,
        ':image' => $image,
        ':image_fit' => $imageFit,
        ':id' => $id,
    ]);

    if ($newImage && $currentImage && $newImage !== $currentImage) {
        delete_image_if_exists($currentImage);
    }

    redirect('/admin/index.php?message=Prenda actualizada correctamente');
}

$stmt = $pdo->prepare("INSERT INTO dresses (name, category, size, price, status, sold_date, image, image_fit, created_at, updated_at) VALUES (:name, :category, :size, :price, :status, :sold_date, :image, :image_fit, NOW(), NOW())");
$stmt->execute([
    ':name' => $name,
    ':category' => $category,
    ':size' => $size,
    ':price' => $price,
    ':status' => $status,
    ':sold_date' => $soldDate,
    ':image' => $image,
    ':image_fit' => $imageFit,
]);

redirect('/admin/index.php?message=Prenda agregada correctamente');
