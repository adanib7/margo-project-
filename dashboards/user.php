<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/check_auth.php';
requireLogin();
require_once '../includes/header.php';
?>
<link rel="stylesheet" href="../assets/css/dashboard.css" />
<?php require_once '../includes/nav.php'; ?>

<main class="dashboard-main role-usuario">
  <div class="dashboard-header">
    <h1>Mi Dashboard</h1>
    <p>Bienvenido, <strong><?= htmlspecialchars($_SESSION['usuario_logueado'], ENT_QUOTES, 'UTF-8') ?></strong>. Desde aquí podés gestionar tu cuenta.</p>
  </div>

  <div class="stats-row">
    <div class="stat-card role-usuario">
      <div class="stat-value">0</div>
      <div class="stat-label">Actividades</div>
    </div>
    <div class="stat-card role-usuario">
      <div class="stat-value">0</div>
      <div class="stat-label">Mensajes</div>
    </div>
    <div class="stat-card role-usuario">
      <div class="stat-value">0</div>
      <div class="stat-label">Notificaciones</div>
    </div>
  </div>

  <p class="section-title">Accesos rápidos</p>
  <div class="cards-grid">
    <div class="card role-usuario">
      <div class="card-icon">👤</div>
      <h3>Mi Perfil</h3>
      <p>Actualizá tu información personal, foto y contraseña.</p>
      <a href="#" class="card-link">Ver perfil →</a>
    </div>
    <div class="card role-usuario">
      <div class="card-icon">📋</div>
      <h3>Mis Actividades</h3>
      <p>Revisá el historial de tus actividades recientes en el sistema.</p>
      <a href="#" class="card-link">Ver actividades →</a>
    </div>
    <div class="card role-usuario">
      <div class="card-icon">🔔</div>
      <h3>Notificaciones</h3>
      <p>No tenés notificaciones nuevas por el momento.</p>
      <a href="#" class="card-link">Ver todas →</a>
    </div>
    <div class="card role-usuario">
      <div class="card-icon">🔒</div>
      <h3>Seguridad</h3>
      <p>Cambiá tu contraseña y revisá los accesos a tu cuenta.</p>
      <a href="#" class="card-link">Configurar →</a>
    </div>
  </div>
</main>

<?php require_once '../includes/footer.php'; ?>
