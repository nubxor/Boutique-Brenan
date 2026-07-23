<footer class="site-footer">
  <div class="wrap site-footer-row">
    <p>Brennan Boutique · Catálogo digital de prendas</p>
    <a class="footer-account-link" href="<?= BASE_URL ?>/mi-cuenta.php">Mi cuenta</a>
  </div>
</footer>
<?php
$jsPath = __DIR__ . '/../assets/js/app.js';
$jsVersion = 'v23-' . (is_file($jsPath) ? (string)filemtime($jsPath) : '1');
?>
<script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= e($jsVersion) ?>"></script>
</body>
</html>
