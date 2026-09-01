<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/check_auth.php';
requireRole('admin', 'superadmin');
$pageTitle = 'Panel de Administración';
$pageCSS = '../assets/css/dashboard.css';
$showDashboardBottomNav = true;
require_once '../includes/header.php';
?>
<?php require_once '../includes/nav.php'; ?>

<main class="contenido-principal">
  <header class="seccion-encabezado">
    <div>
      <h1 class="titulo-pagina">¡Bienvenido, Admin!</h1>
      <p class="subtitulo-pagina">Resumen y acceso rápido a la gestión del restaurante.</p>
    </div>
    <div class="resumen-rapido">
      <div class="icono">
        <span class="material-symbols-outlined icon-fill">event_available</span>
      </div>
    </div>
  </header>

  <div class="grilla-tarjetas">
    <a class="tarjeta" href="#">
      <div class="tarjeta-overlay"></div>
      <div class="tarjeta-cabecera">
        <div class="icono icono-primary">
          <span class="material-symbols-outlined">event_available</span>
        </div>
        <h2>Reservaciones</h2>
      </div>
      <p class="tarjeta-texto">Gestiona las reservas de mesas, confirmaciones y listas de espera para el día.</p>
    </a>

    <a class="tarjeta" href="#">
      <div class="tarjeta-overlay"></div>
      <div class="tarjeta-cabecera">
        <div class="icono">
          <span class="material-symbols-outlined">grid_view</span>
        </div>
        <h2>Plano de Mesas</h2>
      </div>
      <p class="tarjeta-texto">Visualiza y organiza la disposición del comedor y la asignación de mesas.</p>
    </a>

    <a class="tarjeta" href="<?= buildUrl('/dashboards/inventario.php') ?>">
      <div class="tarjeta-overlay"></div>
      <div class="tarjeta-cabecera">
        <div class="icono">
          <span class="material-symbols-outlined">inventory_2</span>
        </div>
        <h2>Inventario</h2>
      </div>
      <p class="tarjeta-texto">Controla el stock de ingredientes, vinos y suministros esenciales.</p>
    </a>

    <a class="tarjeta" href="#">
      <div class="tarjeta-overlay"></div>
      <div class="tarjeta-cabecera">
        <div class="icono">
          <span class="material-symbols-outlined">settings</span>
        </div>
        <h2>Configuración</h2>
      </div>
      <p class="tarjeta-texto">Ajustes del sistema, gestión de usuarios y preferencias del restaurante.</p>
    </a>
  </div>

  <div class="area-accion">
    <button class="boton-accion">
      <span class="material-symbols-outlined">add</span>
      Nueva Reserva Rápida
    </button>
  </div>
</main>

<?php require_once '../includes/footer.php'; ?>
