<?php if (!empty($showDashboardBottomNav)): ?>
<nav class="nav-inferior">
  <a class="item-nav" href="#">
    <span class="material-symbols-outlined mb-1">home</span>
    <span class="texto-nav">Inicio</span>
  </a>
  <a class="item-nav" href="#">
    <span class="material-symbols-outlined mb-1">restaurant_menu</span>
    <span class="texto-nav">Reservas</span>
  </a>
  <a class="item-nav activo" href="#">
    <span class="material-symbols-outlined mb-1 icon-fill">receipt_long</span>
    <span class="texto-nav">Pedidos</span>
  </a>
  <a class="item-nav" href="#">
    <span class="material-symbols-outlined mb-1">person</span>
    <span class="texto-nav">Cuenta</span>
  </a>
</nav>
<?php endif; ?>

<footer class="pie-pagina">
  <div>
    <img src="<?= buildUrl('/assets/img/logo-horizontal-verde.png') ?>" alt="El Corralín de Campanal" class="footer-logo">
    <p class="texto-pie">© 2026 El Corralín de Campanal. Todos los derechos reservados.</p>
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
</body>
</html>
