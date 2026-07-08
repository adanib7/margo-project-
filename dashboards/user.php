<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/check_auth.php';
requireLogin();
$pageTitle = 'Dashboard de Usuario';
$pageCSS = '../assets/css/dashboard.css';
$showDashboardBottomNav = true;
require_once '../includes/header.php';
?>
<?php require_once '../includes/nav.php'; ?>

<main class="contenido-principal">
  <header class="seccion-encabezado">
    <div>
      <h1 class="titulo-pagina">¡Bienvenido, <?= htmlspecialchars($_SESSION['usuario_logueado'], ENT_QUOTES, 'UTF-8') ?>!</h1>
      <p class="subtitulo-pagina">Gestiona tus reservas y tu información de cuenta.</p>
    </div>
  </header>

  <div class="grilla-tarjetas">
    <a class="tarjeta" href="#">
      <div class="tarjeta-overlay"></div>
      <div class="tarjeta-cabecera">
        <div class="icono icono-primary">
         <span class="material-symbols-outlined">event</span>
        </div>
        <h2>Mis Reservas</h2>
      </div>
      <p class="tarjeta-texto">Revisa tus reservas.</p>
    </a>

    <a class="tarjeta" href="#">
      <div class="tarjeta-overlay"></div>
      <div class="tarjeta-cabecera">
        <div class="icono">
          <span class="material-symbols-outlined">person</span>
        </div>
        <h2>Mi Perfil</h2>
      </div>
      <p class="tarjeta-texto">Actualiza tus datos y revisa tu información de cuenta.</p>
    </a>
  </div>
</main>

<?php require_once '../includes/footer.php'; ?>
