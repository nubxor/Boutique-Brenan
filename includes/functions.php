<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function money_mx(float|int|string $amount): string
{
    return '$' . number_format((float) $amount, 0, '.', ',');
}

function selected(string $current, string $value): string
{
    return $current === $value ? 'selected' : '';
}

function checked(bool $condition): string
{
    return $condition ? 'checked' : '';
}

function redirect(string $path): never
{
    header('Location: ' . BASE_URL . $path);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        exit('Token de seguridad inválido.');
    }
}

function status_label(string $status): string
{
    return $status === 'sold' ? 'Vendido' : 'Disponible';
}

function get_sizes(PDO $pdo): array
{
    $base = ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'Única'];
    $stmt = $pdo->query("SELECT DISTINCT size FROM dresses WHERE size IS NOT NULL AND size <> '' ORDER BY FIELD(size,'XS','S','M','L','XL','XXL','Única'), size ASC");
    $custom = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $sizes = [];
    foreach (array_merge($base, $custom) as $size) {
        if ($size !== '' && !in_array($size, $sizes, true)) {
            $sizes[] = $size;
        }
    }
    return $sizes;
}

function upload_image(array $file): ?string
{
    if (empty($file['name']) || (int)$file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ((int)$file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('No se pudo subir la imagen.');
    }

    if ((int)$file['size'] > MAX_IMAGE_SIZE) {
        throw new RuntimeException('La imagen excede el tamaño máximo permitido de 5 MB.');
    }

    $tmp = $file['tmp_name'];
    $info = @getimagesize($tmp);
    if ($info === false) {
        throw new RuntimeException('El archivo seleccionado no es una imagen válida.');
    }

    $allowed = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_WEBP => 'webp',
        IMAGETYPE_GIF  => 'gif',
    ];

    $type = $info[2] ?? null;
    if (!isset($allowed[$type])) {
        throw new RuntimeException('Formato no permitido. Usa JPG, PNG, WEBP o GIF.');
    }

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    $filename = date('YmdHis') . '-' . bin2hex(random_bytes(8)) . '.' . $allowed[$type];
    $destination = rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($tmp, $destination)) {
        throw new RuntimeException('No fue posible guardar la imagen en el servidor.');
    }

    return $filename;
}

function delete_image_if_exists(?string $filename): void
{
    if (!$filename) {
        return;
    }

    $path = rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . basename($filename);
    if (is_file($path)) {
        @unlink($path);
    }
}

function build_public_query(PDO $pdo, array $filters): array
{
    $where = [];
    $params = [];

    if (($filters['status'] ?? 'available') !== 'all') {
        $where[] = 'status = :status';
        $params[':status'] = $filters['status'];
    }

    if (!empty($filters['size']) && $filters['size'] !== 'all') {
        $where[] = 'size = :size';
        $params[':size'] = $filters['size'];
    }

    if (!empty($filters['q'])) {
        $where[] = '(name LIKE :q OR size LIKE :q)';
        $params[':q'] = '%' . $filters['q'] . '%';
    }

    if ($filters['min_price'] !== '') {
        $where[] = 'price >= :min_price';
        $params[':min_price'] = (float)$filters['min_price'];
    }

    if ($filters['max_price'] !== '') {
        $where[] = 'price <= :max_price';
        $params[':max_price'] = (float)$filters['max_price'];
    }

    if (!empty($filters['sold_date'])) {
        $where[] = 'sold_date = :sold_date';
        $params[':sold_date'] = $filters['sold_date'];
    }

    $orderBy = match ($filters['sort'] ?? 'newest') {
        'price-asc' => 'price ASC, created_at DESC',
        'price-desc' => 'price DESC, created_at DESC',
        'size' => "FIELD(size,'XS','S','M','L','XL','XXL','Única'), size ASC, price ASC",
        'sold-date' => 'sold_date DESC, created_at DESC',
        default => 'created_at DESC'
    };

    $sql = 'SELECT * FROM dresses';
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY ' . $orderBy;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}
