<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$error = '';

if (is_logged_in()) {
    redirect('/admin/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if (login_admin($username, $password)) {
        redirect('/admin/index.php');
    }

    $remaining = login_lock_remaining();
    if ($remaining > 0) {
        http_response_code(429);
        $minutes = max(1, (int)ceil($remaining / 60));
        $error = "Demasiados intentos. Vuelve a intentarlo en aproximadamente {$minutes} minuto(s).";
    } else {
        $error = 'Usuario o contraseña incorrectos.';
    }
}

$page_title = 'Acceso administrativo | Brenan Boutique';
include __DIR__ . '/../includes/header.php';
?>

<main class="login-screen">
  <form class="login-card" method="post" autocomplete="on">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <div class="logo large">BB</div>
    <h1>Panel Brenan Boutique</h1>
    <p>Acceso exclusivo para la administración del catálogo.</p>

    <?php if ($error): ?>
      <div class="alert danger" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <label>
      <span>Usuario</span>
      <input type="text" name="username" required autocomplete="username" autocapitalize="none" spellcheck="false">
    </label>

    <label>
      <span>Contraseña</span>
      <input type="password" name="password" required autocomplete="current-password">
    </label>

    <button class="btn primary block" type="submit">Entrar</button>
    <a class="btn block" href="<?= BASE_URL ?>/index.php">Volver al catálogo</a>

    <small>La sesión se cierra automáticamente después de 30 minutos de inactividad.</small>
  </form>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
