<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
verify_csrf();

$pdo = db();
$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    redirect('/admin/index.php?message=ID inválido');
}

$stmt = $pdo->prepare('SELECT image FROM dresses WHERE id = :id');
$stmt->execute([':id' => $id]);
$dress = $stmt->fetch();

if ($dress) {
    delete_image_if_exists($dress['image'] ?? null);
    $delete = $pdo->prepare('DELETE FROM dresses WHERE id = :id');
    $delete->execute([':id' => $id]);
}

redirect('/admin/index.php?message=Vestido eliminado correctamente');
