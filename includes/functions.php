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

/**
 * Indica si el servidor puede redimensionar y comprimir imágenes con GD.
 */
function image_optimizer_available(): bool
{
    return extension_loaded('gd')
        && function_exists('imagecreatetruecolor')
        && function_exists('imagecopyresampled');
}

function image_type_optimizer_supported(int $type): bool
{
    return match ($type) {
        IMAGETYPE_JPEG => function_exists('imagecreatefromjpeg'),
        IMAGETYPE_PNG => function_exists('imagecreatefrompng'),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp'),
        IMAGETYPE_GIF => function_exists('imagecreatefromgif'),
        default => false,
    };
}

/**
 * Crea un recurso GD desde un archivo validado.
 *
 * @return GdImage|resource
 */
function create_image_resource(string $path, int $type)
{
    return match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
        IMAGETYPE_PNG => @imagecreatefrompng($path),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
        IMAGETYPE_GIF => @imagecreatefromgif($path),
        default => false,
    };
}

/**
 * Corrige la orientación de fotografías tomadas con celular.
 *
 * @param GdImage|resource $image
 * @return GdImage|resource
 */
function apply_exif_orientation($image, string $path, int $type)
{
    if ($type !== IMAGETYPE_JPEG || !function_exists('exif_read_data')) {
        return $image;
    }

    $exif = @exif_read_data($path);
    $orientation = (int)($exif['Orientation'] ?? 1);

    $rotated = match ($orientation) {
        3 => @imagerotate($image, 180, 0),
        6 => @imagerotate($image, -90, 0),
        8 => @imagerotate($image, 90, 0),
        default => false,
    };

    if ($rotated !== false) {
        imagedestroy($image);
        return $rotated;
    }

    return $image;
}

/**
 * Guarda una versión redimensionada sin aumentar imágenes pequeñas.
 *
 * @param GdImage|resource $source
 */
function save_resized_image($source, string $destination, int $maxWidth, int $maxHeight, int $quality, string $format): bool
{
    $sourceWidth = imagesx($source);
    $sourceHeight = imagesy($source);

    if ($sourceWidth < 1 || $sourceHeight < 1) {
        return false;
    }

    $ratio = min($maxWidth / $sourceWidth, $maxHeight / $sourceHeight, 1);
    $targetWidth = max(1, (int)round($sourceWidth * $ratio));
    $targetHeight = max(1, (int)round($sourceHeight * $ratio));

    $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
    if ($canvas === false) {
        return false;
    }

    if ($format === 'webp') {
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $transparent);
    } else {
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $white);
    }

    $copied = imagecopyresampled(
        $canvas,
        $source,
        0,
        0,
        0,
        0,
        $targetWidth,
        $targetHeight,
        $sourceWidth,
        $sourceHeight
    );

    if (!$copied) {
        imagedestroy($canvas);
        return false;
    }

    $saved = $format === 'webp'
        ? imagewebp($canvas, $destination, $quality)
        : imagejpeg($canvas, $destination, $quality);

    imagedestroy($canvas);
    return $saved;
}

/**
 * Genera una imagen principal y dos resoluciones ligeras para catálogo.
 */
function create_optimized_image_set(string $sourcePath, int $sourceType, string $stem): string
{
    if (!image_optimizer_available()) {
        throw new RuntimeException('El servidor no tiene habilitada la extensión GD para optimizar imágenes.');
    }

    $source = create_image_resource($sourcePath, $sourceType);
    if ($source === false) {
        throw new RuntimeException('No fue posible procesar la imagen seleccionada.');
    }

    $source = apply_exif_orientation($source, $sourcePath, $sourceType);
    $format = function_exists('imagewebp') ? 'webp' : 'jpg';
    $extension = $format;

    $mainFilename = $stem . '.' . $extension;
    $mediumFilename = $stem . '-900.' . $extension;
    $smallFilename = $stem . '-480.' . $extension;

    $mainPath = rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . $mainFilename;
    $mediumPath = rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . $mediumFilename;
    $smallPath = rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . $smallFilename;

    $createdPaths = [];

    try {
        if (!save_resized_image($source, $mainPath, 1600, 2000, 82, $format)) {
            throw new RuntimeException('No fue posible crear la imagen optimizada.');
        }
        $createdPaths[] = $mainPath;

        if (!save_resized_image($source, $mediumPath, 900, 1200, 78, $format)) {
            throw new RuntimeException('No fue posible crear la resolución mediana.');
        }
        $createdPaths[] = $mediumPath;

        if (!save_resized_image($source, $smallPath, 480, 680, 75, $format)) {
            throw new RuntimeException('No fue posible crear la miniatura ligera.');
        }
        $createdPaths[] = $smallPath;
    } catch (Throwable $e) {
        foreach ($createdPaths as $createdPath) {
            if (is_file($createdPath)) {
                @unlink($createdPath);
            }
        }
        imagedestroy($source);
        throw $e;
    }

    imagedestroy($source);
    return $mainFilename;
}

/**
 * Genera las resoluciones 480 y 900 para una imagen que ya existe.
 * Conserva intacto el archivo original y no requiere cambios en la base de datos.
 */
function generate_existing_image_variants(string $filename, bool $force = false): array
{
    $filename = basename($filename);
    $sourcePath = rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . $filename;

    if (!is_file($sourcePath)) {
        return ['created' => 0, 'skipped' => 0, 'error' => 'Archivo no encontrado'];
    }

    if (!image_optimizer_available()) {
        return ['created' => 0, 'skipped' => 0, 'error' => 'La extensión GD no está habilitada'];
    }

    $info = @getimagesize($sourcePath);
    if ($info === false) {
        return ['created' => 0, 'skipped' => 0, 'error' => 'Imagen no válida'];
    }

    $sourceType = (int)($info[2] ?? 0);
    if (!image_type_optimizer_supported($sourceType)) {
        return ['created' => 0, 'skipped' => 0, 'error' => 'El formato no está habilitado en GD'];
    }

    $source = create_image_resource($sourcePath, $sourceType);
    if ($source === false) {
        return ['created' => 0, 'skipped' => 0, 'error' => 'Formato no compatible con GD'];
    }

    $source = apply_exif_orientation($source, $sourcePath, $sourceType);
    $format = function_exists('imagewebp') ? 'webp' : 'jpg';
    $stem = pathinfo($filename, PATHINFO_FILENAME);

    $variants = [
        ['suffix' => '900', 'width' => 900, 'height' => 1200, 'quality' => 78],
        ['suffix' => '480', 'width' => 480, 'height' => 680, 'quality' => 75],
    ];

    $created = 0;
    $skipped = 0;

    foreach ($variants as $variant) {
        $variantFilename = $stem . '-' . $variant['suffix'] . '.' . $format;
        $variantPath = rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . $variantFilename;

        if (!$force && is_file($variantPath)) {
            $skipped++;
            continue;
        }

        if (save_resized_image(
            $source,
            $variantPath,
            $variant['width'],
            $variant['height'],
            $variant['quality'],
            $format
        )) {
            $created++;
        }
    }

    imagedestroy($source);
    return ['created' => $created, 'skipped' => $skipped, 'error' => null];
}

function upload_image(array $file): ?string
{
    if (empty($file['name']) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('No se pudo subir la imagen.');
    }

    if ((int)($file['size'] ?? 0) > MAX_IMAGE_SIZE) {
        throw new RuntimeException('La imagen excede el tamaño máximo permitido de 5 MB.');
    }

    $tmp = (string)($file['tmp_name'] ?? '');
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

    $type = (int)($info[2] ?? 0);
    if (!isset($allowed[$type])) {
        throw new RuntimeException('Formato no permitido. Usa JPG, PNG, WEBP o GIF.');
    }

    if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0755, true) && !is_dir(UPLOAD_DIR)) {
        throw new RuntimeException('No fue posible crear la carpeta de imágenes.');
    }

    $stem = date('YmdHis') . '-' . bin2hex(random_bytes(8));

    if (image_optimizer_available() && image_type_optimizer_supported($type)) {
        return create_optimized_image_set($tmp, $type, $stem);
    }

    // Respaldo para servidores sin GD: guarda el archivo original.
    $filename = $stem . '.' . $allowed[$type];
    $destination = rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($tmp, $destination)) {
        throw new RuntimeException('No fue posible guardar la imagen en el servidor.');
    }

    return $filename;
}

/**
 * Devuelve el nombre de la variante disponible o el archivo original.
 */
function image_variant_filename(?string $filename, int $width): string
{
    $filename = basename((string)$filename);
    if ($filename === '') {
        return '';
    }

    $stem = pathinfo($filename, PATHINFO_FILENAME);
    foreach (['webp', 'jpg', 'jpeg', 'png'] as $extension) {
        $candidate = $stem . '-' . $width . '.' . $extension;
        $candidatePath = rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . $candidate;
        if (is_file($candidatePath)) {
            return $candidate;
        }
    }

    return $filename;
}

function image_public_url(?string $filename, ?int $width = null): string
{
    $resolved = $width === null
        ? basename((string)$filename)
        : image_variant_filename($filename, $width);

    return rtrim(UPLOAD_URL, '/') . '/' . rawurlencode($resolved);
}

function image_srcset(?string $filename): string
{
    $filename = basename((string)$filename);
    if ($filename === '') {
        return '';
    }

    $entries = [];
    foreach ([480, 900] as $width) {
        $variant = image_variant_filename($filename, $width);
        if ($variant !== $filename) {
            $entries[] = image_public_url($variant) . ' ' . $width . 'w';
        }
    }

    return implode(', ', $entries);
}

function delete_image_if_exists(?string $filename): void
{
    $filename = basename((string)$filename);
    if ($filename === '') {
        return;
    }

    $stem = pathinfo($filename, PATHINFO_FILENAME);
    $paths = [rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . $filename];

    foreach ([480, 900] as $width) {
        foreach (['webp', 'jpg', 'jpeg', 'png'] as $extension) {
            $paths[] = rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . $stem . '-' . $width . '.' . $extension;
        }
    }

    foreach (array_unique($paths) as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
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
