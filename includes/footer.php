<footer class="site-footer">
  <div class="wrap">
    <p>Brenan Boutique · Catálogo digital de prendas</p>
  </div>
</footer>
<?php
$jsPath = __DIR__ . '/../assets/js/app.js';
$jsVersion = is_file($jsPath) ? (int)filemtime($jsPath) : 1;
?>
<script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= $jsVersion ?>"></script>
</body>
</html>
