<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/check_auth.php';
requireRole('superadmin');
$pageTitle = 'Panel Super Administrador';
$pageCSS = BASE_URL . '/assets/css/dashboard.css';
$showDashboardBottomNav = true;
require_once '../includes/header.php';
?>
<?php require_once '../includes/nav.php'; ?>

<main class="contenido-principal">
  <header class="seccion-encabezado">
    <div>
      <h1 class="titulo-pagina">Panel Super Administrador</h1>
    </div>
  </header>

  <div class="grilla-tarjetas">
    <a class="tarjeta" href="#">
      <div class="tarjeta-overlay"></div>
      <div class="tarjeta-cabecera">
        <div class="icono icono-primary">
          <span class="material-symbols-outlined">people</span>
        </div>
        <h2>Gestión de Usuarios</h2>
      </div>
      <p class="tarjeta-texto">Crear, editar y revisar cuentas de usuarios, admins y superadmins.</p>
    </a>

    <a class="tarjeta" href="#">
      <div class="tarjeta-overlay"></div>
      <div class="tarjeta-cabecera">
        <div class="icono">
          <span class="material-symbols-outlined">shield</span>
        </div>
        <h2>Roles y Permisos</h2>
      </div>
      <p class="tarjeta-texto">Configuración de accesos y actualización de permisos en el sistema.</p>
    </a>

    <a class="tarjeta" href="#">
      <div class="tarjeta-overlay"></div>
      <div class="tarjeta-cabecera">
        <div class="icono">
          <span class="material-symbols-outlined">settings</span>
        </div>
        <h2>Configuración</h2>
      </div>
      <p class="tarjeta-texto">Ajustes globales del sistema y mantenimiento de la plataforma.</p>
    </a>

    <a class="tarjeta" href="#">
      <div class="tarjeta-overlay"></div>
      <div class="tarjeta-cabecera">
        <div class="icono">
          <span class="material-symbols-outlined">insights</span>
        </div>
        <h2>Reportes</h2>
      </div>
      <p class="tarjeta-texto">Analiza el desempeño global y consulta datos de uso.</p>
    </a>
  </div>

  <div class="area-accion">
    <button class="boton-accion">
      <span class="material-symbols-outlined">add</span>
      Crear Admin
    </button>
  </div>
</main>

<?php require_once '../includes/footer.php'; ?>
