<?php
require_once __DIR__ . '/../includes/sesion.php';
iniciarSesion();
require_once '../includes/config.php';
require_once '../includes/check_auth.php';
requireRole('admin', 'superadmin');

$pageTitle = 'Cierre de caja';
$pageCSS = '../assets/css/dashboard.css';
$pageCSSExtra = ['caja.css'];
$showDashboardBottomNav = true;

require_once '../includes/header.php';
?>
<?php require_once '../includes/nav.php'; ?>

<main class="contenido-principal cj">
  <header class="cj-encabezado">
    <div>
      <a href="<?= buildUrl('/dashboards/caja.php') ?>" class="enlace-volver"><span class="material-symbols-outlined" aria-hidden="true">arrow_back</span>Volver al salón</a>
      <p class="cj-eyebrow" id="ccEyebrow">Facturación · turno</p>
      <h1 class="titulo-pagina">Cierre de caja</h1>
      <p class="subtitulo-pagina">La tira de la izquierda se arma sola durante el turno. Contá el efectivo y cerrá.</p>
    </div>
  </header>

  <div id="ccContenido">
    <div class="gu-estado"><span class="material-symbols-outlined icono-spin" style="font-size:2rem;color:#264220" aria-hidden="true">progress_activity</span><p>Cargando el turno…</p></div>
  </div>

  <section class="cj-panel" style="margin-top:1.5rem" aria-labelledby="ccHistTit">
    <div class="cj-panel-cab"><h2 class="cj-panel-titulo" id="ccHistTit">Últimos cierres</h2>
      <a class="cj-enlace" href="<?= buildUrl('/dashboards/ventas.php') ?>"><span class="material-symbols-outlined" aria-hidden="true">receipt_long</span>Ver historial de ventas</a></div>
    <div style="overflow-x:auto" id="ccHistorial"><p class="cj-vacio-chico">Sin cierres todavía.</p></div>
  </section>
</main>

<script>
  const BASE = '<?= BASE_URL ?>';
</script>
<script src="<?= jsUrl('caja_comun.js') ?>"></script>
<script src="<?= jsUrl('cierre_caja.js') ?>"></script>

<?php require_once '../includes/footer.php'; ?>
