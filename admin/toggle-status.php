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

$stmt = $pdo->prepare('SELECT status, sold_date FROM dresses WHERE id = :id');
$stmt->execute([':id' => $id]);
$dress = $stmt->fetch();

if ($dress) {
    if ($dress['status'] === 'sold') {
        $update = $pdo->prepare("UPDATE dresses SET status='available', sold_date=NULL, updated_at=NOW() WHERE id=:id");
        $update->execute([':id' => $id]);
    } else {
        $date = $dress['sold_date'] ?: date('Y-m-d');
        $update = $pdo->prepare("UPDATE dresses SET status='sold', sold_date=:sold_date, updated_at=NOW() WHERE id=:id");
        $update->execute([':sold_date' => $date, ':id' => $id]);
    }
}

redirect('/admin/index.php?message=Estado actualizado correctamente');
