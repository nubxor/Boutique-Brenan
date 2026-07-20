<?php
/**
 * BRENAN BOUTIQUE - CONFIGURACIÓN GENERAL
 */

declare(strict_types=1);

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

define('DB_HOST', env('DB_HOST'));
define('DB_NAME', env('DB_NAME'));
define('DB_USER', env('DB_USER'));
define('DB_PASS', env('DB_PASS'));
define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));

define('BASE_URL', '');

define('UPLOAD_DIR', __DIR__ . '/uploads');
define('UPLOAD_URL', BASE_URL . '/uploads');
define('MAX_IMAGE_SIZE', 5 * 1024 * 1024);

// Acceso administrativo. La contraseña se almacena únicamente como hash.
define('ADMIN_USER', env('ADMIN_USER', 'admin'));
define('ADMIN_PASSWORD_HASH', env('ADMIN_PASSWORD_HASH', '$2y$12$.0yhfd5PQfVkrm0XqOsZLeLj590X.oqGlT/vd.HctQdDa2V1m0KPe'));

// Protección del inicio de sesión.
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_ATTEMPT_WINDOW', 15 * 60);
define('LOGIN_LOCKOUT_SECONDS', 15 * 60);
define('SESSION_IDLE_TIMEOUT', 30 * 60);
define('SESSION_NAME', 'brenan_boutique_admin_session');
