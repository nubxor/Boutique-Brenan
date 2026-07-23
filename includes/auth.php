<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

function request_is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Strict');

    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => BASE_URL !== '' ? BASE_URL : '/',
        'domain' => '',
        'secure' => request_is_https(),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

function require_post_request(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit('Método no permitido.');
    }
}

function login_client_key(): string
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    return hash('sha256', $ip);
}

function login_attempt_path(): string
{
    return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . 'brennan_login_' . login_client_key() . '.json';
}

function default_login_state(): array
{
    return ['attempts' => 0, 'window_started' => time(), 'locked_until' => 0];
}

/**
 * Lee o modifica de forma atómica el contador de intentos.
 */
function with_login_state(callable $callback): array
{
    $path = login_attempt_path();
    $handle = @fopen($path, 'c+');
    if ($handle === false) {
        $state = default_login_state();
        return $callback($state);
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            $state = default_login_state();
            return $callback($state);
        }

        rewind($handle);
        $raw = stream_get_contents($handle);
        $data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        $state = is_array($data) ? [
            'attempts' => max(0, (int)($data['attempts'] ?? 0)),
            'window_started' => (int)($data['window_started'] ?? time()),
            'locked_until' => max(0, (int)($data['locked_until'] ?? 0)),
        ] : default_login_state();

        $newState = $callback($state);
        if (!is_array($newState)) {
            $newState = $state;
        }

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($newState, JSON_UNESCAPED_SLASHES) ?: '{}');
        fflush($handle);
        flock($handle, LOCK_UN);

        return $newState;
    } finally {
        fclose($handle);
    }
}

function login_lock_remaining(): int
{
    $state = with_login_state(static fn(array $state): array => $state);
    return max(0, (int)$state['locked_until'] - time());
}

function record_login_failure(): void
{
    with_login_state(static function (array $state): array {
        $now = time();

        if (($now - (int)$state['window_started']) > LOGIN_ATTEMPT_WINDOW) {
            $state = ['attempts' => 0, 'window_started' => $now, 'locked_until' => 0];
        }

        $state['attempts'] = (int)$state['attempts'] + 1;
        if ($state['attempts'] >= LOGIN_MAX_ATTEMPTS) {
            $state['attempts'] = 0;
            $state['window_started'] = $now;
            $state['locked_until'] = $now + LOGIN_LOCKOUT_SECONDS;
        }

        return $state;
    });
}

function clear_login_failures(): void
{
    $path = login_attempt_path();
    if (is_file($path)) {
        @unlink($path);
    }
}

function current_user_agent_hash(): string
{
    return hash('sha256', (string)($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'));
}

function is_logged_in(): bool
{
    if (empty($_SESSION['admin_logged_in']) || empty($_SESSION['admin_user'])) {
        return false;
    }

    $now = time();
    $lastActivity = (int)($_SESSION['last_activity'] ?? 0);
    $loginStarted = (int)($_SESSION['login_started'] ?? 0);
    $storedAgent = (string)($_SESSION['user_agent_hash'] ?? '');

    if (
        ($lastActivity > 0 && ($now - $lastActivity) > SESSION_IDLE_TIMEOUT)
        || ($loginStarted > 0 && ($now - $loginStarted) > SESSION_ABSOLUTE_TIMEOUT)
        || $storedAgent === ''
        || !hash_equals($storedAgent, current_user_agent_hash())
    ) {
        logout_admin();
        return false;
    }

    $lastRegenerated = (int)($_SESSION['last_regenerated'] ?? 0);
    if (($now - $lastRegenerated) > SESSION_REGENERATE_INTERVAL) {
        session_regenerate_id(true);
        $_SESSION['last_regenerated'] = $now;
    }

    $_SESSION['last_activity'] = $now;
    return true;
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: ' . BASE_URL . '/admin/login.php', true, 303);
        exit;
    }

    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('X-Robots-Tag: noindex, nofollow, noarchive', true);
}

function login_admin(string $username, string $password): bool
{
    if (login_lock_remaining() > 0) {
        return false;
    }

    // Se verifica siempre el hash para reducir diferencias de tiempo observables.
    $passwordMatches = password_verify($password, ADMIN_PASSWORD_HASH);
    $userMatches = hash_equals(ADMIN_USER, $username);

    if ($userMatches && $passwordMatches) {
        session_regenerate_id(true);
        $now = time();
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_user'] = ADMIN_USER;
        $_SESSION['last_activity'] = $now;
        $_SESSION['login_started'] = $now;
        $_SESSION['last_regenerated'] = $now;
        $_SESSION['user_agent_hash'] = current_user_agent_hash();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        clear_login_failures();
        return true;
    }

    record_login_failure();
    usleep(400000);
    return false;
}

function logout_admin(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => (bool)$params['secure'],
                'httponly' => (bool)$params['httponly'],
                'samesite' => $params['samesite'] ?? 'Strict',
            ]
        );
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}
