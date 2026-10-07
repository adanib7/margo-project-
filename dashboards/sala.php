<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/check_auth.php';
requireRole('admin', 'superadmin');

$pageTitle = 'Sala en vivo';
$pageCSS = '../assets/css/dashboard.css';
$volverUrl = ($_SESSION['rol'] ?? '') === 'superadmin'
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
      <h1 class="titulo-pagina" style="margin-top:.5rem">Sala en vivo</h1>
      <p class="subtitulo-pagina">Qué mesas están ocupadas, cuáles se liberan y quién llega, a cualquier hora del día.</p>
    </div>
  </header>

  <!-- ── Resumen de la hora elegida ── -->
  <div class="gu-stats">
    <div class="gu-stat">
      <div class="icono icono-gold"><span class="material-symbols-outlined">table_restaurant</span></div>
      <div>
        <p class="texto-etiqueta">Mesas ocupadas</p>
        <p class="numero-resumen" id="statOcupadas">—</p>
      </div>
    </div>
    <div class="gu-stat">
      <div class="icono"><span class="material-symbols-outlined">groups</span></div>
      <div>
        <p class="texto-etiqueta">Comensales en sala</p>
        <p class="numero-resumen" id="statComensales">—</p>
      </div>
    </div>
    <div class="gu-stat">
      <div class="icono"><span class="material-symbols-outlined">event_available</span></div>
      <div>
        <p class="texto-etiqueta">Reservas del día</p>
        <p class="numero-resumen" id="statReservas">—</p>
      </div>
    </div>
  </div>

  <!-- ── Día y hora ── -->
  <div class="sala-controles">
    <div class="campo-input-wrapper sala-fecha">
      <span class="campo-icono material-symbols-outlined">calendar_today</span>
      <input class="campo-input" type="date" id="salaFecha">
    </div>
    <div class="sala-tiempo">
      <span class="sala-hora" id="salaHora">--:--</span>
      <input type="range" id="salaSlider" step="1" aria-label="Hora del día">
    </div>
    <button type="button" class="boton-secundario sala-ahora" id="salaAhora">
      <span class="sala-punto"></span> Ahora
    </button>
  </div>

  <div class="sala-grilla">
    <!-- ── Plano ── -->
    <section class="sala-plano-tarjeta">
      <div class="plano-reserva-leyenda">
        <span class="pr-lg pr-libre">Libre</span>
        <span class="pr-lg sala-lg-proxima">Llega en menos de 1 h</span>
        <span class="pr-lg sala-lg-ocupada">Ocupada</span>
      </div>
      <div class="plano-reserva-wrap" id="salaWrap">
        <div class="plano-reserva-lienzo" id="salaLienzo"></div>
        <div class="plano-reserva-estado" id="salaEstado">Cargando sala…</div>
      </div>
    </section>

    <!-- ── Reservas del día ── -->
    <aside class="sala-lista-tarjeta">
      <h2 class="sala-lista-titulo">Reservas del día</h2>
      <div id="salaLista" class="sala-lista"></div>
    </aside>
  </div>
</main>

<script>
  const BASE = '<?= BASE_URL ?>';
</script>
<script src="<?= jsUrl('sala.js') ?>"></script>

<?php require_once '../includes/footer.php'; ?>
