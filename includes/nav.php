<?php
$roleLabels = [
    'usuario'    => 'Usuario',
    'admin'      => 'Administrador',
    'superadmin' => 'Super Admin',
];
$rol = $_SESSION['rol'] ?? 'usuario';
?>
<nav class="navbar">
  <div class="nav-brand">Margo Project</div>
  <div class="nav-user">
    <span class="rol-badge rol-<?= htmlspecialchars($rol, ENT_QUOTES, 'UTF-8') ?>">
      <?= htmlspecialchars($roleLabels[$rol] ?? $rol, ENT_QUOTES, 'UTF-8') ?>
    </span>
    <span><?= htmlspecialchars($_SESSION['usuario_logueado'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
    <a href="<?= BASE_URL ?>/login.php?logout=1" class="btn-logout">Cerrar sesión</a>
  </div>
</nav>
