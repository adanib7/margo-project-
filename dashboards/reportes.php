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
(function () {
  const BASE = '<?= BASE_URL ?>';
  const $ = id => document.getElementById(id);

  let preset = '30d';
  let desde  = '';
  let hasta  = '';

  function esc(s) {
    return String(s ?? '')
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  const nf = new Intl.NumberFormat('es-ES');
  const cf = new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR' });

  function query() {
    const p = new URLSearchParams();
    if (desde && hasta) { p.set('desde', desde); p.set('hasta', hasta); }
    else                { p.set('preset', preset); }
    return p.toString();
  }

  /** Barra horizontal proporcional al máximo de la serie. */
  function barra(valor, max, clase) {
    const pct = max > 0 ? Math.round(valor / max * 100) : 0;
    return '<span class="rep-barra"><span class="rep-barra-fill ' + (clase || '') + '" style="width:' + pct + '%"></span></span>';
  }

  function variacion(v) {
    if (v === null || v === undefined) return '<span class="rep-var rep-var-sb">sin base previa</span>';
    if (v === 0) return '<span class="rep-var">sin cambios</span>';
    const sube = v > 0;
    return '<span class="rep-var ' + (sube ? 'rep-var-sube' : 'rep-var-baja') + '">'
      + '<span class="material-symbols-outlined">' + (sube ? 'trending_up' : 'trending_down') + '</span>'
      + (sube ? '+' : '') + v + '% vs. período previo</span>';
  }

  // ── Bloques ──────────────────────────────────────────────────────────────
  function bloqueResumen(s) {
    return '<section class="rep-bloque"><div class="rep-kpis">'
      + '<div class="rep-kpi"><span class="rep-kpi-label">Reservas</span>'
      +   '<span class="rep-kpi-valor">' + nf.format(s.reservas) + '</span>' + variacion(s.var_reservas) + '</div>'
      + '<div class="rep-kpi"><span class="rep-kpi-label">Comensales</span>'
      +   '<span class="rep-kpi-valor">' + nf.format(s.comensales) + '</span>' + variacion(s.var_comensales) + '</div>'
      + '<div class="rep-kpi"><span class="rep-kpi-label">Media por reserva</span>'
      +   '<span class="rep-kpi-valor">' + s.media.toFixed(1).replace('.', ',') + '</span>'
      +   '<span class="rep-var">personas por mesa</span></div>'
      + '<div class="rep-kpi"><span class="rep-kpi-label">Canceladas</span>'
      +   '<span class="rep-kpi-valor">' + s.tasa_cancelacion + '%</span>'
      +   '<span class="rep-var">' + nf.format(s.canceladas) + ' de ' + nf.format(s.total) + '</span></div>'
      + '<div class="rep-kpi"><span class="rep-kpi-label">Antelación media</span>'
      +   '<span class="rep-kpi-valor">' + s.antelacion.toFixed(1).replace('.', ',') + '</span>'
      +   '<span class="rep-var">días antes de venir</span></div>'
      + '</div></section>';
  }

  function bloqueCuando(dias, franjas) {
    const maxD = Math.max(1, ...dias.map(d => d.reservas));
    const maxF = Math.max(1, ...franjas.map(f => f.reservas));

    const filasD = dias.map(d =>
      '<div class="rep-fila' + (d.cerrado ? ' rep-fila-cerrada' : '') + '">'
      + '<span class="rep-fila-nom">' + esc(d.nombre) + '</span>'
      + barra(d.reservas, maxD, 'rep-verde')
      + '<span class="rep-fila-val">' + d.reservas + (d.cerrado ? ' <em>cerrado</em>' : '') + '</span>'
      + '</div>').join('');

    const filasF = franjas.length
      ? franjas.map(f =>
          '<div class="rep-fila">'
          + '<span class="rep-fila-nom">' + esc(f.hora) + '</span>'
          + barra(f.reservas, maxF, 'rep-dorado')
          + '<span class="rep-fila-val">' + f.reservas + '</span>'
          + '</div>').join('')
      : '<p class="cfg-preview-vacio">Sin reservas en el período.</p>';

    return '<section class="rep-bloque"><h2 class="rep-titulo">¿Cuándo viene la gente?</h2>'
      + '<div class="rep-dos-col">'
      + '<div><h3 class="rep-subtitulo">Por día de la semana</h3>' + filasD + '</div>'
      + '<div><h3 class="rep-subtitulo">Por horario</h3>' + filasF + '</div>'
      + '</div></section>';
  }

  function bloqueMesas(mesas) {
    if (!mesas.length) {
      return '<section class="rep-bloque"><h2 class="rep-titulo">Uso de las mesas</h2>'
        + '<p class="cfg-preview-vacio">Todavía no hay un plano de mesas cargado.</p></section>';
    }
    const max = Math.max(1, ...mesas.map(m => m.reservas));
    const filas = mesas.map(m =>
      '<div class="rep-fila">'
      + '<span class="rep-fila-nom">Mesa ' + m.numero + ' <em>' + m.capacidad + ' pers.</em></span>'
      + barra(m.reservas, max, m.reservas === 0 ? 'rep-gris' : 'rep-verde')
      + '<span class="rep-fila-val">' + m.reservas
      +   (m.reservas === 0 ? ' <em>sin uso</em>' : ' <em>· ' + Math.min(100, m.llenado) + '% llena</em>')
      + '</span></div>').join('');

    return '<section class="rep-bloque"><h2 class="rep-titulo">Uso de las mesas</h2>'
      + filas
      + '<p class="campo-ayuda" style="margin-top:.75rem">«% llena» es cuántos comensales trae de media respecto a su capacidad. '
      + 'Una mesa con poco uso o poco llenado puede sobrar en el plano.</p>'
      + '</section>';
  }

  function bloqueClientes(clientes) {
    if (!clientes.length) return '';
    const max = Math.max(1, ...clientes.map(c => c.reservas));
    const filas = clientes.map(c =>
      '<div class="rep-fila">'
      + '<span class="rep-fila-nom">' + esc(c.nombre) + '</span>'
      + barra(c.reservas, max, 'rep-verde')
      + '<span class="rep-fila-val">' + c.reservas + '</span></div>').join('');

    return '<section class="rep-bloque"><h2 class="rep-titulo">Clientes que repiten</h2>' + filas + '</section>';
  }

  function bloqueInventario(inv) {
    if (!inv.articulos) return '';
    const max = Math.max(1, ...inv.categorias.map(c => c.valor));
    const filas = inv.categorias.map(c =>
      '<div class="rep-fila">'
      + '<span class="rep-fila-nom">' + esc(c.categoria) + '</span>'
      + barra(c.valor, max, 'rep-dorado')
      + '<span class="rep-fila-val">' + cf.format(c.valor) + '</span></div>').join('');

    return '<section class="rep-bloque"><h2 class="rep-titulo">Inventario <em class="rep-nota">estado de hoy</em></h2>'
      + '<div class="rep-kpis rep-kpis-3">'
      + '<div class="rep-kpi"><span class="rep-kpi-label">Valor del stock</span><span class="rep-kpi-valor">' + cf.format(inv.valor) + '</span></div>'
      + '<div class="rep-kpi"><span class="rep-kpi-label">Artículos</span><span class="rep-kpi-valor">' + inv.articulos + '</span></div>'
      + '<div class="rep-kpi"><span class="rep-kpi-label">Bajo mínimo</span><span class="rep-kpi-valor' + (inv.bajos ? ' rep-alerta' : '') + '">' + inv.bajos + '</span></div>'
      + '</div>' + filas + '</section>';
  }

  // ── Carga ────────────────────────────────────────────────────────────────
  async function cargar() {
    $('repContenido').innerHTML =
      '<div class="gu-estado"><span class="material-symbols-outlined icono-spin" style="font-size:2rem;color:#264220">progress_activity</span><p>Calculando…</p></div>';

    const q = query();
    $('btnCsv').href = BASE + '/api/reportes_csv.php?' + q;
    $('btnPdf').href = BASE + '/api/reportes_pdf.php?' + q;

    try {
      const res  = await fetch(BASE + '/api/reportes.php?' + q);
      const data = await res.json();
      if (!data.ok) throw new Error(data.mensaje || 'Error al calcular.');

      $('repEtiqueta').textContent = data.periodo.etiqueta + ' · ' + data.periodo.dias + ' días';
      $('repDesde').value = data.periodo.desde;
      $('repHasta').value = data.periodo.hasta;

      $('repContenido').innerHTML =
          bloqueResumen(data.resumen)
        + bloqueCuando(data.dias_semana, data.franjas)
        + bloqueMesas(data.mesas)
        + bloqueClientes(data.clientes)
        + bloqueInventario(data.inventario);
    } catch (e) {
      $('repContenido').innerHTML =
        '<div class="gu-estado"><span class="material-symbols-outlined" style="font-size:2rem;color:#b91c1c">error</span><p>' + esc(e.message) + '</p></div>';
    }
  }

  document.querySelectorAll('.filtro-btn[data-preset]').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.filtro-btn[data-preset]').forEach(b => b.classList.remove('filtro-activo'));
      btn.classList.add('filtro-activo');
      preset = btn.dataset.preset;
      desde = hasta = '';
      cargar();
    });
  });

  $('btnRango').addEventListener('click', () => {
    if (!$('repDesde').value || !$('repHasta').value) return;
    desde = $('repDesde').value;
    hasta = $('repHasta').value;
    document.querySelectorAll('.filtro-btn[data-preset]').forEach(b => b.classList.remove('filtro-activo'));
    cargar();
  });

  cargar();
})();
</script>

<?php require_once '../includes/footer.php'; ?>
