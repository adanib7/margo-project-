<?php
require_once __DIR__ . '/../includes/sesion.php';
iniciarSesion();
require_once '../includes/config.php';
require_once '../includes/check_auth.php';
require_once '../includes/facturacion_db.php';
requireRole('admin', 'superadmin');

$pageTitle = 'Historial de ventas';
$pageCSS = '../assets/css/dashboard.css';
$pageCSSExtra = ['caja.css'];
$showDashboardBottomNav = true;
$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

require_once '../includes/header.php';
?>
<?php require_once '../includes/nav.php'; ?>

<main class="contenido-principal cj">
  <header class="cj-encabezado">
    <div>
      <a href="<?= buildUrl('/dashboards/caja.php') ?>" class="enlace-volver"><span class="material-symbols-outlined" aria-hidden="true">arrow_back</span>Volver a la caja</a>
      <p class="cj-eyebrow">Caja y facturación</p>
      <h1 class="titulo-pagina">Historial de ventas</h1>
      <p class="subtitulo-pagina">Cada cobro con su comprobante. Tocá una venta para ver el detalle o reimprimir el ticket.</p>
    </div>
    <button type="button" class="cj-enlace" id="vtCsv"><span class="material-symbols-outlined" aria-hidden="true">download</span>Exportar CSV</button>
  </header>

  <section class="cj-panel vt-filtros" aria-label="Filtros">
    <div class="vt-rangos" role="group" aria-label="Período">
      <button type="button" class="cj-tab activo" data-rango="hoy" aria-pressed="true">Hoy</button>
      <button type="button" class="cj-tab" data-rango="ayer" aria-pressed="false">Ayer</button>
      <button type="button" class="cj-tab" data-rango="7" aria-pressed="false">7 días</button>
      <button type="button" class="cj-tab" data-rango="mes" aria-pressed="false">Este mes</button>
    </div>
    <div class="vt-campos">
      <label class="cj-campo">Desde<input type="date" id="vtDesde"></label>
      <label class="cj-campo">Hasta<input type="date" id="vtHasta"></label>
      <label class="cj-campo">Medio
        <select id="vtMedio" class="vt-select"><option value="">Todos</option>
          <?php foreach (FAC_MEDIOS as $k => $label): ?><option value="<?= $h($k) ?>"><?= $h($label) ?></option><?php endforeach; ?>
        </select></label>
      <label class="cj-campo">Comprobante
        <select id="vtTipo" class="vt-select"><option value="">Todos</option><option value="eticket">e-Ticket</option><option value="efactura">e-Factura</option></select></label>
      <label class="cj-campo vt-buscar">Buscar<input type="search" id="vtBuscar" placeholder="Nº, mesa, mozo, RUT o cliente" autocomplete="off"></label>
    </div>
  </section>

  <div class="cj-stats">
    <div class="cj-stat"><div class="cj-stat-label">Vendido</div><div class="cj-stat-valor" id="vtTotal">—</div></div>
    <div class="cj-stat"><div class="cj-stat-label">Mesas y ventas</div><div class="cj-stat-valor" id="vtCuentas">—</div></div>
    <div class="cj-stat"><div class="cj-stat-label">Promedio por mesa</div><div class="cj-stat-valor" id="vtPromedio">—</div></div>
    <div class="cj-stat dorado"><div class="cj-stat-label">Propinas</div><div class="cj-stat-valor" id="vtPropina">—</div></div>
  </div>

  <div class="vt-graficos">
    <section class="cj-panel" aria-labelledby="vtSerieTit">
      <div class="cj-panel-cab"><h2 class="cj-panel-titulo" id="vtSerieTit">Ventas por hora</h2><span class="vt-nota" id="vtIva"></span></div>
      <div id="vtSerie"></div>
      <div class="vt-medios" id="vtMedios"></div>
    </section>
    <section class="cj-panel" aria-labelledby="vtTopTit">
      <div class="cj-panel-cab"><h2 class="cj-panel-titulo" id="vtTopTit">Lo más pedido</h2></div>
      <ol class="vt-top" id="vtTop"></ol>
    </section>
  </div>

  <div class="mz-filtro vt-pestanas" role="tablist" aria-label="Qué ver">
    <button type="button" class="cj-tab activo" role="tab" aria-selected="true" data-vista="ventas" id="tabVentas">Comprobantes</button>
    <button type="button" class="cj-tab" role="tab" aria-selected="false" data-vista="anulaciones" id="tabAnul">Anulaciones <span id="vtAnulCant"></span></button>
  </div>

  <div class="gu-tabla-wrap" id="vtTabla">
    <div class="gu-estado"><span class="material-symbols-outlined icono-spin" style="font-size:2rem;color:#264220" aria-hidden="true">progress_activity</span><p>Cargando ventas…</p></div>
  </div>
  <div class="vt-paginas" id="vtPaginas"></div>
</main>

<div class="modal-fondo" id="modalVenta" role="dialog" aria-modal="true" aria-labelledby="vtDetTitulo">
  <div class="modal-contenedor vt-detalle">
    <div class="modal-encabezado">
      <div class="modal-icono"><span class="material-symbols-outlined">receipt_long</span></div>
      <div>
        <h2 class="modal-titulo" id="vtDetTitulo">Venta</h2>
        <p class="modal-subtitulo" id="vtDetSub"></p>
      </div>
      <button type="button" class="modal-cerrar" data-cerrar="modalVenta" aria-label="Cerrar"><span class="material-symbols-outlined">close</span></button>
    </div>
    <div id="vtDetCuerpo"></div>
  </div>
</div>

<script>
  const BASE = '<?= BASE_URL ?>';
  // Fecha del servidor: las ventas se guardan con este reloj, no con el del navegador.
  const HOY_SERVIDOR = '<?= date('Y-m-d') ?>';
</script>
<script src="<?= jsUrl('caja_comun.js') ?>"></script>
<script src="<?= jsUrl('ventas.js') ?>"></script>

<?php require_once '../includes/footer.php'; ?>
