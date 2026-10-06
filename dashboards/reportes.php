<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/check_auth.php';
requireRole('admin', 'superadmin');

$pageTitle = 'Reportes';
$pageCSS = '../assets/css/dashboard.css';
$showDashboardBottomNav = true;

$esSuperadmin = ($_SESSION['rol'] ?? '') === 'superadmin';
$volverUrl = $esSuperadmin
    ? buildUrl('/dashboards/superadmin.php')
    : buildUrl('/dashboards/admin.php');

require_once '../includes/header.php';
?>
<?php require_once '../includes/nav.php'; ?>

<main class="contenido-principal">

  <header class="seccion-encabezado">
    <div>
      <a href="<?= $volverUrl ?>" class="enlace-volver">
        <span class="material-symbols-outlined">arrow_back</span>
        Panel principal
      </a>
      <h1 class="titulo-pagina" style="margin-top:.5rem">Reportes</h1>
      <p class="subtitulo-pagina">Cómo viene funcionando el salón.</p>
    </div>
    <div class="rep-descargas">
      <a class="boton-secundario" id="btnCsv" href="#">
        <span class="material-symbols-outlined">table_view</span> CSV
      </a>
      <a class="boton-accion" id="btnPdf" href="#" target="_blank" rel="noopener">
        <span class="material-symbols-outlined">picture_as_pdf</span> PDF
      </a>
    </div>
  </header>

  <!-- ── Período ── -->
  <div class="rep-periodo">
    <div class="filtros-rol" role="group" aria-label="Período">
      <button class="filtro-btn" data-preset="7d">7 días</button>
      <button class="filtro-btn filtro-activo" data-preset="30d">30 días</button>
      <button class="filtro-btn" data-preset="90d">90 días</button>
      <button class="filtro-btn" data-preset="mes">Este mes</button>
      <button class="filtro-btn" data-preset="mes_anterior">Mes anterior</button>
      <button class="filtro-btn" data-preset="anio">Este año</button>
    </div>
    <div class="rep-rango">
      <div class="campo-input-wrapper">
        <span class="campo-icono material-symbols-outlined">calendar_month</span>
        <input class="campo-input" type="date" id="repDesde" aria-label="Desde">
      </div>
      <span class="rep-rango-sep">a</span>
      <div class="campo-input-wrapper">
        <input class="campo-input" type="date" id="repHasta" aria-label="Hasta">
      </div>
      <button type="button" class="boton-secundario" id="btnRango">Aplicar</button>
    </div>
  </div>

  <p class="rep-etiqueta" id="repEtiqueta">—</p>

  <div id="repContenido">
    <div class="gu-estado">
      <span class="material-symbols-outlined icono-spin" style="font-size:2rem;color:#264220">progress_activity</span>
      <p>Calculando…</p>
    </div>
  </div>

</main>

<script>
  const BASE = '<?= BASE_URL ?>';
</script>
<script src="<?= jsUrl('reportes.js') ?>"></script>

<?php require_once '../includes/footer.php'; ?>
