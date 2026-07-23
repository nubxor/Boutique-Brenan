<?php
/**
 * BRENNAN BOUTIQUE - CONFIGURACIÓN GENERAL
 */

declare(strict_types=1);

// Producción: los errores se registran en el servidor, pero nunca se muestran al visitante.
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
header_remove('X-Powered-By');

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

    return (string)$value;
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
define('MAX_IMAGE_PIXELS', 25_000_000);
define('MAX_IMAGE_EDGE', 10_000);

/*
 * Acceso administrativo.
 * Las variables del archivo .env tienen prioridad. El respaldo incluido es
 * exclusivo de esta entrega y debe cambiarse después del primer acceso.
 */
define('ADMIN_USER', trim(env('ADMIN_USER', 'mi_cuenta')));
define('ADMIN_PASSWORD_HASH', env('ADMIN_PASSWORD_HASH', '$2y$12$.Okf4mLzlkXA6W8B1lRYwu4CnoOdE/n7xbqdHVLcG8JMeu0IQuuWy'));

$passwordInfo = password_get_info(ADMIN_PASSWORD_HASH);
if (ADMIN_USER === '' || (($passwordInfo['algoName'] ?? 'unknown') === 'unknown')) {
    throw new RuntimeException('La configuración del acceso administrativo no es válida.');
}

// Protección del inicio de sesión y de la sesión administrativa.
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_ATTEMPT_WINDOW', 15 * 60);
define('LOGIN_LOCKOUT_SECONDS', 15 * 60);
define('SESSION_IDLE_TIMEOUT', 30 * 60);
define('SESSION_ABSOLUTE_TIMEOUT', 8 * 60 * 60);
define('SESSION_REGENERATE_INTERVAL', 10 * 60);
define('SESSION_NAME', 'brennan_boutique_account');
