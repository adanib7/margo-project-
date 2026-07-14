<?php
$roleLabels = [
    'usuario'    => 'Usuario',
    'admin'      => 'Administrador',
    'superadmin' => 'Superadmin',
];
$rol = $_SESSION['rol'] ?? 'usuario';
?>
<nav class="barra-superior">
  <div class="marca">
    <img src="<?= buildUrl('/assets/img/logo-horizontal-verde.png') ?>" alt="El Corralín de Campanal" class="marca-logo">
  </div>


  <div class="acciones-usuario">
    <span class="nombre-usuario"><?= htmlspecialchars($_SESSION['usuario_logueado'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
    <a href="<?= buildUrl('/public/login.php?logout=1') ?>" class="boton-salir">Cerrar sesión</a>
  </div>
</nav>
