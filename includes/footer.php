<footer class="pie-pagina">
  <div>
    <img src="<?= buildUrl('/assets/img/logo-horizontal-verde.png') ?>" alt="<?= htmlspecialchars(cfg('local.nombre'), ENT_QUOTES, 'UTF-8') ?>" class="footer-logo">
    <p class="texto-pie">© <?= date('Y') ?> <?= htmlspecialchars(cfg('local.nombre'), ENT_QUOTES, 'UTF-8') ?>. Todos los derechos reservados.</p>
  </div>
  <div class="lista-pie">
    <a class="enlace-pie" href="#">Política de privacidad</a>
    <a class="enlace-pie" href="#">Términos del servicio</a>
  </div>
  <div class="lista-pie">
    <a class="enlace-pie" href="#">Contacto</a>
    <a class="enlace-pie" href="#">Ubicación</a>
  </div>
</footer>
<?php if (in_array($_SESSION['rol'] ?? '', ['admin', 'superadmin'], true)): ?>
<!-- Avisos en vivo de reservas nuevas (solo admin y superadmin) -->
<script src="<?= jsUrl('avisos.js') ?>"
        data-api="<?= buildUrl('/api/reservas_nuevas.php') ?>"
        data-reservas="<?= buildUrl('/dashboards/reservas.php') ?>"></script>
<?php endif; ?>
</body>
</html>
