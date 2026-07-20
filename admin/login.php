<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$error = '';

if (is_logged_in()) {
    redirect('/admin/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if (login_admin($username, $password)) {
        redirect('/admin/index.php');
    }

    $error = 'Usuario o contraseña incorrectos.';
}

$page_title = 'Iniciar sesión | Brenan Boutique';
include __DIR__ . '/../includes/header.php';
?>

<main class="login-screen">
  <form class="login-card" method="post">
    <div class="logo large">BB</div>
    <h1>Panel Brenan Boutique</h1>
    <p>Ingresa para administrar vestidos, fotografías, tallas, precios y ventas.</p>

    <?php if ($error): ?>
      <div class="alert danger"><?= e($error) ?></div>
    <?php endif; ?>

    <label>
      <span>Usuario</span>
      <input type="text" name="username" required autocomplete="username">
    </label>

    <label>
      <span>Contraseña</span>
      <input type="password" name="password" required autocomplete="current-password">
    </label>

    <button class="btn primary block" type="submit">Entrar</button>
    <a class="btn block" href="<?= BASE_URL ?>/index.php">Volver al catálogo</a>

    <small>Acceso inicial: admin / admin123. Cámbialo en config.php.</small>
  </form>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
