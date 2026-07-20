<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? null) === '443');

    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => BASE_URL !== '' ? BASE_URL : '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
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
        . 'brenan_login_' . login_client_key() . '.json';
}

function read_login_attempts(): array
{
    $path = login_attempt_path();
    if (!is_file($path) || !is_readable($path)) {
        return ['attempts' => 0, 'window_started' => time(), 'locked_until' => 0];
    }

    $raw = @file_get_contents($path);
    $data = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($data)) {
        return ['attempts' => 0, 'window_started' => time(), 'locked_until' => 0];
    }

    return [
        'attempts' => max(0, (int)($data['attempts'] ?? 0)),
        'window_started' => (int)($data['window_started'] ?? time()),
        'locked_until' => max(0, (int)($data['locked_until'] ?? 0)),
    ];
}

function write_login_attempts(array $state): void
{
    @file_put_contents(
        login_attempt_path(),
        json_encode($state, JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );
}

function login_lock_remaining(): int
{
    $state = read_login_attempts();
    return max(0, $state['locked_until'] - time());
}

function record_login_failure(): void
{
    $now = time();
    $state = read_login_attempts();

    if (($now - $state['window_started']) > LOGIN_ATTEMPT_WINDOW) {
        $state = ['attempts' => 0, 'window_started' => $now, 'locked_until' => 0];
    }

    $state['attempts']++;
    if ($state['attempts'] >= LOGIN_MAX_ATTEMPTS) {
        $state['attempts'] = 0;
        $state['window_started'] = $now;
        $state['locked_until'] = $now + LOGIN_LOCKOUT_SECONDS;
    }

    write_login_attempts($state);
}

function clear_login_failures(): void
{
    $path = login_attempt_path();
    if (is_file($path)) {
        @unlink($path);
    }
}

function is_logged_in(): bool
{
    if (empty($_SESSION['admin_logged_in']) || empty($_SESSION['admin_user'])) {
        return false;
    }

    $lastActivity = (int)($_SESSION['last_activity'] ?? 0);
    if ($lastActivity > 0 && (time() - $lastActivity) > SESSION_IDLE_TIMEOUT) {
        logout_admin();
        return false;
    }

    $_SESSION['last_activity'] = time();
    return true;
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: ' . BASE_URL . '/admin/login.php');
        exit;
    }

    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

function login_admin(string $username, string $password): bool
{
    if (login_lock_remaining() > 0) {
        return false;
    }

    $userMatches = hash_equals(ADMIN_USER, $username);
    $passwordMatches = password_verify($password, ADMIN_PASSWORD_HASH);

    if ($userMatches && $passwordMatches) {
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_user'] = ADMIN_USER;
        $_SESSION['last_activity'] = time();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        clear_login_failures();
        return true;
    }

    record_login_failure();
    usleep(350000);
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
                'samesite' => $params['samesite'] ?? 'Lax',
            ]
        );
    }

    session_destroy();
}
