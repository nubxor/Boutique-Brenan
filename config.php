<?php
/**
 * GALLETITAS - CONFIGURACIÓN GENERAL
 */

declare(strict_types=1);

/**
 * Carga las variables del archivo .env sin sobrescribir las variables que el
 * servidor ya haya definido.
 */
function load_env(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        [$name, $value] = array_pad(explode('=', $line, 2), 2, '');
        $name = trim($name);

        if ($name === '' || getenv($name) !== false) {
            continue;
        }

        $value = trim($value);
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

function env(string $name, ?string $default = null): string
{
    $value = getenv($name);

    if ($value === false) {
        if ($default !== null) {
            return $default;
        }

        throw new RuntimeException("La variable de entorno {$name} no está definida.");
    }

    return $value;
}

load_env(__DIR__ . '/.env');

// Datos de conexión MySQL.
define('DB_HOST', env('DB_HOST'));
define('DB_NAME', env('DB_NAME'));
define('DB_USER', env('DB_USER'));
define('DB_PASS', env('DB_PASS'));
define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));

// URL base del catálogo.
// Si lo instalas en la raíz del dominio, deja: ''
// Si lo instalas en una carpeta, por ejemplo /catalogo, escribe: '/catalogo'
define('BASE_URL', '');

// Carpeta donde se guardan las imágenes.
define('UPLOAD_DIR', __DIR__ . '/uploads');
define('UPLOAD_URL', BASE_URL . '/uploads');

// Tamaño máximo de imagen en bytes. 5 MB.
define('MAX_IMAGE_SIZE', 5 * 1024 * 1024);

// Usuario inicial del panel.
// IMPORTANTE: cambia esta contraseña después de instalar.
define('DEFAULT_ADMIN_USER', 'admin');
define('DEFAULT_ADMIN_PASS', 'admin123');

// Seguridad de sesión.
define('SESSION_NAME', 'galletitas_admin_session');
