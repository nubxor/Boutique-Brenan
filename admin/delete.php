<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_post_request();
require_login();
verify_csrf();

$pdo = db();
$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    redirect('/admin/index.php?notice=invalid');
}

$stmt = $pdo->prepare('SELECT image FROM dresses WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$dress = $stmt->fetch();

if (!$dress) {
    redirect('/admin/index.php?notice=missing');
}

$image = basename((string)($dress['image'] ?? ''));

try {
    $pdo->beginTransaction();
    $delete = $pdo->prepare('DELETE FROM dresses WHERE id = :id');
    $delete->execute([':id' => $id]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error al eliminar prenda: ' . $e->getMessage());
    http_response_code(500);
    exit('No fue posible eliminar la prenda.');
}

if ($image !== '') {
    delete_image_if_exists($image);
}

redirect('/admin/index.php?notice=deleted');
