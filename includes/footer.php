<footer class="site-footer">
  <div class="wrap">
    <p>Brenan Boutique · Catálogo digital de prendas</p>
  </div>
</footer>
<?php
$jsPath = __DIR__ . '/../assets/js/app.js';
$jsVersion = 'v19-' . (is_file($jsPath) ? (string)filemtime($jsPath) : '1');
?>
<script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= e($jsVersion) ?>"></script>
</body>
</html>
