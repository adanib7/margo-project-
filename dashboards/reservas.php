<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/check_auth.php';
require_once '../includes/config_app.php';
requireRole('admin', 'superadmin');

$pageTitle = 'Reservas';
$pageCSS = '../assets/css/dashboard.css';
$showDashboardBottomNav = true;

$esSuperadmin = ($_SESSION['rol'] ?? '') === 'superadmin';
$volverUrl = $esSuperadmin
    ? buildUrl('/dashboards/superadmin.php')
    : buildUrl('/dashboards/admin.php');

$franjasHorarias = horarioFranjas();

require_once '../includes/header.php';
?>
<?php require_once '../includes/nav.php'; ?>

<main class="contenido-principal">

  <!-- ── Encabezado ── -->
  <header class="seccion-encabezado">
    <div>
      <a href="<?= $volverUrl ?>" class="enlace-volver">
        <span class="material-symbols-outlined">arrow_back</span>
        Panel principal
      </a>
      <h1 class="titulo-pagina" style="margin-top:.5rem">Reservas</h1>
      <p class="subtitulo-pagina">Consultá, confirmá, editá o cancelá las reservas del salón.</p>
    </div>
    <button class="boton-secundario" id="btnRefrescar">
      <span class="material-symbols-outlined">refresh</span>
      Actualizar
    </button>
  </header>

  <!-- ── Stats ── -->
  <div class="gu-stats res-stats">
    <div class="gu-stat">
      <div class="icono icono-primary"><span class="material-symbols-outlined">today</span></div>
      <div>
        <p class="texto-etiqueta">Reservas de hoy</p>
        <p class="numero-resumen" id="statHoy">—</p>
      </div>
    </div>
    <div class="gu-stat">
      <div class="icono icono-gold"><span class="material-symbols-outlined">schedule</span></div>
      <div>
        <p class="texto-etiqueta">Pendientes</p>
        <p class="numero-resumen" id="statPendientes">—</p>
      </div>
    </div>
    <div class="gu-stat">
      <div class="icono"><span class="material-symbols-outlined">event_available</span></div>
      <div>
        <p class="texto-etiqueta">Confirmadas</p>
        <p class="numero-resumen" id="statConfirmadas">—</p>
      </div>
    </div>
    <div class="gu-stat">
      <div class="icono"><span class="material-symbols-outlined">group</span></div>
      <div>
        <p class="texto-etiqueta">Comensales hoy</p>
        <p class="numero-resumen" id="statComensales">—</p>
      </div>
    </div>
  </div>

  <!-- ── Búsqueda + filtros ── -->
  <div class="gu-barra">
    <div class="campo-input-wrapper gu-busqueda">
      <span class="campo-icono material-symbols-outlined">search</span>
      <input class="campo-input" type="text" id="inputBusqueda" placeholder="Buscar por nombre, código o teléfono…">
    </div>
    <div class="filtros-rol" role="group" aria-label="Filtrar por estado">
      <button class="filtro-btn filtro-activo" data-estado="">Todos</button>
      <button class="filtro-btn" data-estado="pendiente">Pendientes</button>
      <button class="filtro-btn" data-estado="confirmada">Confirmadas</button>
      <button class="filtro-btn" data-estado="cancelada">Canceladas</button>
    </div>
  </div>

  <div class="res-barra-rango">
    <div class="filtros-rol" role="group" aria-label="Filtrar por fecha">
      <button class="filtro-btn filtro-activo" data-rango="proximas">Próximas</button>
      <button class="filtro-btn" data-rango="hoy">Hoy</button>
      <button class="filtro-btn" data-rango="manana">Mañana</button>
      <button class="filtro-btn" data-rango="semana">Esta semana</button>
      <button class="filtro-btn" data-rango="pasadas">Pasadas</button>
      <button class="filtro-btn" data-rango="">Todas</button>
    </div>
  </div>

  <!-- ── Tabla ── -->
  <div class="gu-tabla-wrap" id="resTablaWrap">
    <div class="gu-estado">
      <span class="material-symbols-outlined icono-spin" style="font-size:2rem;color:#264220">progress_activity</span>
      <p>Cargando reservas…</p>
    </div>
  </div>

</main>

<!-- ══════════════════════════════════════
     MODAL: Editar reserva
════════════════════════════════════════ -->
<div class="modal-fondo" id="modalReserva" role="dialog" aria-modal="true" aria-labelledby="modalReservaTitulo">
  <div class="modal-contenedor">
    <div class="modal-encabezado">
      <div class="modal-icono"><span class="material-symbols-outlined">edit_calendar</span></div>
      <div>
        <h2 class="modal-titulo" id="modalReservaTitulo">Editar reserva</h2>
        <p class="modal-subtitulo" id="modalReservaSubtitulo">Ajustá los datos de la reserva.</p>
      </div>
      <button class="modal-cerrar" id="btnCerrarModalReserva" aria-label="Cerrar">
        <span class="material-symbols-outlined">close</span>
      </button>
    </div>

    <form class="modal-form" id="formReserva" novalidate>
      <input type="hidden" id="rId" value="">

      <div class="campo-grupo">
        <label class="campo-etiqueta" for="rNombre">Nombre del cliente</label>
        <div class="campo-input-wrapper">
          <span class="campo-icono material-symbols-outlined">person</span>
          <input class="campo-input" type="text" id="rNombre" maxlength="255" required>
        </div>
        <span class="campo-error" id="rErrorNombre"></span>
      </div>

      <div class="inv-form-fila">
        <div class="campo-grupo">
          <label class="campo-etiqueta" for="rFecha">Fecha</label>
          <div class="campo-input-wrapper">
            <span class="campo-icono material-symbols-outlined">calendar_month</span>
            <input class="campo-input" type="date" id="rFecha" required>
          </div>
          <span class="campo-error" id="rErrorFecha"></span>
        </div>

        <div class="campo-grupo">
          <label class="campo-etiqueta" for="rHora">Hora</label>
          <div class="campo-input-wrapper">
            <span class="campo-icono material-symbols-outlined">schedule</span>
            <select class="campo-input campo-select" id="rHora" required>
              <?php foreach ($franjasHorarias as $franja => $horas): ?>
                <optgroup label="<?= htmlspecialchars($franja, ENT_QUOTES) ?>">
                  <?php foreach ($horas as $h): ?>
                    <option value="<?= $h ?>"><?= $h ?></option>
                  <?php endforeach; ?>
                </optgroup>
              <?php endforeach; ?>
            </select>
          </div>
          <span class="campo-error" id="rErrorHora"></span>
        </div>
      </div>

      <div class="inv-form-fila">
        <div class="campo-grupo">
          <label class="campo-etiqueta" for="rPersonas">Comensales</label>
          <div class="campo-input-wrapper">
            <span class="campo-icono material-symbols-outlined">group</span>
            <input class="campo-input" type="number" id="rPersonas" min="1" max="20" required>
          </div>
          <span class="campo-error" id="rErrorPersonas"></span>
        </div>

        <div class="campo-grupo">
          <label class="campo-etiqueta" for="rMesa">Mesa</label>
          <div class="campo-input-wrapper">
            <span class="campo-icono material-symbols-outlined">table_restaurant</span>
            <select class="campo-input campo-select" id="rMesa">
              <option value="0">Sin asignar</option>
            </select>
          </div>
          <span class="campo-error" id="rErrorMesa"></span>
        </div>
      </div>

      <div class="inv-form-fila">
        <div class="campo-grupo">
          <label class="campo-etiqueta" for="rTelefono">Teléfono <span class="campo-opcional">(opcional)</span></label>
          <div class="campo-input-wrapper">
            <span class="campo-icono material-symbols-outlined">phone</span>
            <input class="campo-input" type="tel" id="rTelefono" maxlength="50">
          </div>
          <span class="campo-error" id="rErrorTelefono"></span>
        </div>

        <div class="campo-grupo">
          <label class="campo-etiqueta" for="rEstado">Estado</label>
          <div class="campo-input-wrapper">
            <span class="campo-icono material-symbols-outlined">flag</span>
            <select class="campo-input campo-select" id="rEstado" required>
              <option value="pendiente">Pendiente</option>
              <option value="confirmada">Confirmada</option>
              <option value="cancelada">Cancelada</option>
            </select>
          </div>
          <span class="campo-error" id="rErrorEstado"></span>
        </div>
      </div>

      <div class="campo-grupo">
        <label class="campo-etiqueta" for="rComentario">Pedido especial <span class="campo-opcional">(opcional)</span></label>
        <textarea class="campo-textarea" id="rComentario" rows="2" maxlength="500"></textarea>
      </div>

      <div class="modal-acciones">
        <button type="button" class="boton-secundario" id="btnCancelarModalReserva">Cancelar</button>
        <button type="submit" class="boton-accion boton-modal-submit" id="btnSubmitReserva">
          <span class="material-symbols-outlined">check</span>
          <span>Guardar cambios</span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ══════════════════════════════════════
     MODAL: Confirmar acción (cancelar / eliminar)
════════════════════════════════════════ -->
<div class="modal-fondo" id="modalConfirmar" role="dialog" aria-modal="true" aria-labelledby="modalConfirmarTitulo">
  <div class="modal-contenedor">
    <div class="modal-encabezado">
      <div class="modal-icono modal-icono-danger" id="modalConfirmarIcono">
        <span class="material-symbols-outlined">event_busy</span>
      </div>
      <div>
        <h2 class="modal-titulo" id="modalConfirmarTitulo">Cancelar reserva</h2>
        <p class="modal-subtitulo" id="modalConfirmarSubtitulo">La mesa queda libre otra vez.</p>
      </div>
      <button class="modal-cerrar" id="btnCerrarModalConfirmar" aria-label="Cerrar">
        <span class="material-symbols-outlined">close</span>
      </button>
    </div>

    <p class="eliminar-descripcion" id="modalConfirmarTexto"></p>

    <div class="res-aviso-bloque" id="bloqueAviso">
      <label class="res-check">
        <input type="checkbox" id="chkAvisar" checked>
        <span>Avisar al cliente por correo</span>
      </label>
      <div class="campo-grupo" id="grupoMotivo">
        <label class="campo-etiqueta" for="txtMotivo">Motivo <span class="campo-opcional">(opcional, se incluye en el correo)</span></label>
        <textarea class="campo-textarea" id="txtMotivo" rows="2" maxlength="300" placeholder="Ej. cerramos por reforma ese día"></textarea>
      </div>
    </div>

    <div class="modal-acciones">
      <button type="button" class="boton-secundario" id="btnCancelarConfirmar">Volver</button>
      <button type="button" class="boton-peligro" id="btnAceptarConfirmar">
        <span class="material-symbols-outlined">check</span>
        <span id="btnAceptarConfirmarTexto">Cancelar reserva</span>
      </button>
    </div>
  </div>
</div>

<script>
(function () {
  const BASE = '<?= BASE_URL ?>';
  const ES_SUPERADMIN = <?= $esSuperadmin ? 'true' : 'false' ?>;

  // ── Estado ───────────────────────────────────────────────────────────────
  let filtroEstado = '';
  let filtroRango  = 'proximas';
  let busqueda     = '';
  let debounceT    = null;
  let mesas        = [];
  let hayPlano     = false;
  let accionPend   = null;   // { tipo:'cancelar'|'eliminar', id, nombre, codigo }

  const tablaWrap    = document.getElementById('resTablaWrap');
  const modalReserva = document.getElementById('modalReserva');
  const formReserva  = document.getElementById('formReserva');
  const modalConf    = document.getElementById('modalConfirmar');
  const btnSubmit    = document.getElementById('btnSubmitReserva');

  const CAMPOS = {
    nombre:   ['rNombre',   'rErrorNombre'],
    fecha:    ['rFecha',    'rErrorFecha'],
    hora:     ['rHora',     'rErrorHora'],
    personas: ['rPersonas', 'rErrorPersonas'],
    mesa:     ['rMesa',     'rErrorMesa'],
    telefono: ['rTelefono', 'rErrorTelefono'],
    estado:   ['rEstado',   'rErrorEstado'],
  };

  // ── Helpers ──────────────────────────────────────────────────────────────
  function esc(s) {
    return String(s ?? '')
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  const DIAS  = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];
  const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

  function fechaCorta(iso) {
    if (!iso) return '—';
    const [a, m, d] = iso.split('-').map(Number);
    const dt = new Date(a, m - 1, d);
    return DIAS[dt.getDay()] + ' ' + d + ' ' + MESES[m - 1];
  }

  function esHoy(iso) {
    const h = new Date();
    const s = h.getFullYear() + '-' + String(h.getMonth() + 1).padStart(2, '0') + '-' + String(h.getDate()).padStart(2, '0');
    return iso === s;
  }

  function labelEstado(e) {
    return { pendiente: 'Pendiente', confirmada: 'Confirmada', cancelada: 'Cancelada' }[e] || e;
  }

  function iniciales(nombre) {
    return String(nombre || '?').trim().split(/\s+/).slice(0, 2).map(w => w[0].toUpperCase()).join('');
  }

  function mostrarEstado(html) {
    tablaWrap.innerHTML = '<div class="gu-estado">' + html + '</div>';
  }

  // ── Cargar ───────────────────────────────────────────────────────────────
  async function cargar() {
    mostrarEstado('<span class="material-symbols-outlined icono-spin" style="font-size:2rem;color:#264220">progress_activity</span><p>Cargando…</p>');

    const params = new URLSearchParams();
    if (busqueda)     params.set('q', busqueda);
    if (filtroEstado) params.set('estado', filtroEstado);
    if (filtroRango)  params.set('rango', filtroRango);

    try {
      const res  = await fetch(BASE + '/api/listar_reservas.php?' + params);
      const data = await res.json();
      if (!data.ok) throw new Error(data.mensaje || 'Error al cargar.');

      mesas    = data.mesas || [];
      hayPlano = !!data.hay_plano;
      actualizarStats(data.totales);
      llenarSelectMesas();
      renderTabla(data.reservas);
    } catch (e) {
      mostrarEstado('<span class="material-symbols-outlined" style="font-size:2rem;color:#b91c1c">error</span><p>' + esc(e.message) + '</p>');
    }
  }

  function actualizarStats(t) {
    document.getElementById('statHoy').textContent         = t.hoy ?? 0;
    document.getElementById('statPendientes').textContent  = t.pendientes ?? 0;
    document.getElementById('statConfirmadas').textContent = t.confirmadas ?? 0;
    document.getElementById('statComensales').textContent  = t.comensales_hoy ?? 0;
  }

  function llenarSelectMesas() {
    const sel = document.getElementById('rMesa');
    sel.innerHTML = '<option value="0">Sin asignar</option>'
      + mesas.map(m => '<option value="' + m.id + '">Mesa ' + m.numero + ' · hasta ' + m.capacidad + '</option>').join('');
    sel.disabled = !hayPlano;
  }

  // ── Render tabla ─────────────────────────────────────────────────────────
  function renderTabla(reservas) {
    if (!reservas.length) {
      mostrarEstado('<span class="material-symbols-outlined" style="font-size:2.5rem;color:#73796f">event_busy</span><p>No hay reservas con esos filtros.</p>');
      return;
    }

    const filas = reservas.map(r => {
      const cancelada = r.estado === 'cancelada';
      const acciones = [];

      if (r.estado !== 'confirmada') {
        acciones.push('<button class="btn-tabla btn-confirmar" title="Confirmar" data-id="' + r.id + '">'
          + '<span class="material-symbols-outlined">check_circle</span></button>');
      }
      acciones.push('<button class="btn-tabla btn-editar" title="Editar" data-json=\'' + esc(JSON.stringify(r)) + '\'>'
        + '<span class="material-symbols-outlined">edit</span></button>');
      if (!cancelada) {
        acciones.push('<button class="btn-tabla btn-cancelar" title="Cancelar" data-id="' + r.id
          + '" data-nombre="' + esc(r.nombre) + '" data-codigo="' + esc(r.codigo) + '">'
          + '<span class="material-symbols-outlined">event_busy</span></button>');
      }
      if (ES_SUPERADMIN) {
        acciones.push('<button class="btn-tabla btn-eliminar" title="Eliminar" data-id="' + r.id
          + '" data-nombre="' + esc(r.nombre) + '" data-codigo="' + esc(r.codigo) + '">'
          + '<span class="material-symbols-outlined">delete</span></button>');
      }

      const contacto = [r.telefono, r.email].filter(Boolean).map(esc).join(' · ') || '—';
      const chipHoy  = esHoy(r.fecha) ? ' <span class="res-chip-hoy">hoy</span>' : '';

      return '<tr class="' + (cancelada ? 'res-fila-cancelada' : '') + '">'
        + '<td><span class="res-codigo">' + esc(r.codigo) + '</span></td>'
        + '<td><div class="gu-usuario-celda">'
        +   '<div class="gu-avatar">' + esc(iniciales(r.nombre)) + '</div>'
        +   '<div><span class="gu-nombre">' + esc(r.nombre) + '</span>'
        +   '<div class="res-contacto">' + contacto + '</div></div>'
        + '</div></td>'
        + '<td><span class="res-fecha">' + fechaCorta(r.fecha) + chipHoy + '</span>'
        +   '<div class="res-hora">' + esc(r.hora) + ' h</div></td>'
        + '<td>' + (r.mesa ? 'Mesa ' + r.mesa : '<span class="res-sin-mesa">sin asignar</span>') + '</td>'
        + '<td>' + r.personas + '</td>'
        + '<td><span class="badge-rol badge-estado-' + esc(r.estado) + '">' + labelEstado(r.estado) + '</span></td>'
        + '<td class="gu-email res-nota">' + (r.comentario ? esc(r.comentario) : '—') + '</td>'
        + '<td><div class="gu-acciones">' + acciones.join('') + '</div></td>'
        + '</tr>';
    }).join('');

    tablaWrap.innerHTML =
      '<table class="gu-tabla res-tabla"><thead><tr>'
      + '<th>Código</th><th>Cliente</th><th>Cuándo</th><th>Mesa</th>'
      + '<th>Pers.</th><th>Estado</th><th>Nota</th><th class="col-acciones">Acciones</th>'
      + '</tr></thead><tbody>' + filas + '</tbody></table>';

    tablaWrap.querySelectorAll('.btn-confirmar').forEach(b =>
      b.addEventListener('click', () => cambiarEstado(b.dataset.id, 'confirmada')));
    tablaWrap.querySelectorAll('.btn-editar').forEach(b =>
      b.addEventListener('click', () => abrirEditar(JSON.parse(b.dataset.json))));
    tablaWrap.querySelectorAll('.btn-cancelar').forEach(b =>
      b.addEventListener('click', () => pedirConfirmacion('cancelar', b.dataset)));
    tablaWrap.querySelectorAll('.btn-eliminar').forEach(b =>
      b.addEventListener('click', () => pedirConfirmacion('eliminar', b.dataset)));
  }

  // ── Cambiar estado rápido ────────────────────────────────────────────────
  async function cambiarEstado(id, estado) {
    try {
      const res  = await fetch(BASE + '/api/cambiar_estado_reserva.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: parseInt(id, 10), estado: estado }),
      });
      const data = await res.json();
      if (data.ok) {
        mostrarToast(data.mensaje, 'exito');
        cargar();
      } else {
        mostrarToast(data.mensaje || 'No se pudo cambiar el estado.', 'error');
      }
    } catch {
      mostrarToast('No se pudo conectar con el servidor.', 'error');
    }
  }

  // ── Filtros ──────────────────────────────────────────────────────────────
  document.getElementById('inputBusqueda').addEventListener('input', e => {
    clearTimeout(debounceT);
    busqueda = e.target.value.trim();
    debounceT = setTimeout(cargar, 320);
  });

  document.querySelectorAll('.filtro-btn[data-estado]').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.filtro-btn[data-estado]').forEach(b => b.classList.remove('filtro-activo'));
      btn.classList.add('filtro-activo');
      filtroEstado = btn.dataset.estado;
      cargar();
    });
  });

  document.querySelectorAll('.filtro-btn[data-rango]').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.filtro-btn[data-rango]').forEach(b => b.classList.remove('filtro-activo'));
      btn.classList.add('filtro-activo');
      filtroRango = btn.dataset.rango;
      cargar();
    });
  });

  document.getElementById('btnRefrescar').addEventListener('click', cargar);

  // ══════════════════════════════════════════════════════════════════════════
  // MODAL: editar
  // ══════════════════════════════════════════════════════════════════════════
  function abrirModal(m) { m.classList.add('modal-visible'); document.body.classList.add('modal-abierto'); }
  function cerrarModal(m) { m.classList.remove('modal-visible'); document.body.classList.remove('modal-abierto'); }

  function limpiarErrores() {
    Object.values(CAMPOS).forEach(par => document.getElementById(par[1]).textContent = '');
    modalReserva.querySelectorAll('.campo-wrapper-error').forEach(el => el.classList.remove('campo-wrapper-error'));
  }

  function marcarError(campo, texto) {
    const par = CAMPOS[campo];
    if (!par) return;
    const el = document.getElementById(par[0]);
    const w  = el.closest('.campo-input-wrapper');
    if (w) w.classList.add('campo-wrapper-error');
    document.getElementById(par[1]).textContent = texto;
  }

  function setLoading(on) {
    btnSubmit.disabled = on;
    const ic = btnSubmit.querySelector('.material-symbols-outlined');
    ic.textContent = on ? 'progress_activity' : 'check';
    ic.classList.toggle('icono-spin', on);
  }

  function abrirEditar(r) {
    document.getElementById('rId').value         = r.id;
    document.getElementById('rNombre').value     = r.nombre || '';
    document.getElementById('rFecha').value      = r.fecha || '';
    document.getElementById('rPersonas').value   = r.personas || 1;
    document.getElementById('rTelefono').value   = r.telefono || '';
    document.getElementById('rEstado').value     = r.estado || 'pendiente';
    document.getElementById('rComentario').value = r.comentario || '';

    // La hora guardada puede no estar entre las opciones fijas: la agrego si falta.
    const selHora = document.getElementById('rHora');
    if (r.hora && !Array.from(selHora.options).some(o => o.value === r.hora)) {
      selHora.insertAdjacentHTML('afterbegin', '<option value="' + esc(r.hora) + '">' + esc(r.hora) + '</option>');
    }
    selHora.value = r.hora || '';

    document.getElementById('rMesa').value = r.mesa_id || 0;
    document.getElementById('modalReservaSubtitulo').textContent =
      'Reserva ' + r.codigo + (r.email ? ' · ' + r.email : '');

    limpiarErrores();
    setLoading(false);
    abrirModal(modalReserva);
    document.getElementById('rNombre').focus();
  }

  function cerrarModalReserva() {
    cerrarModal(modalReserva);
    limpiarErrores();
    setLoading(false);
  }

  document.getElementById('btnCerrarModalReserva').addEventListener('click', cerrarModalReserva);
  document.getElementById('btnCancelarModalReserva').addEventListener('click', cerrarModalReserva);
  modalReserva.addEventListener('click', e => { if (e.target === modalReserva) cerrarModalReserva(); });

  formReserva.addEventListener('submit', async e => {
    e.preventDefault();
    limpiarErrores();

    const payload = {
      id:         parseInt(document.getElementById('rId').value, 10),
      nombre:     document.getElementById('rNombre').value.trim(),
      fecha:      document.getElementById('rFecha').value,
      hora:       document.getElementById('rHora').value,
      personas:   parseInt(document.getElementById('rPersonas').value, 10) || 0,
      mesa_id:    parseInt(document.getElementById('rMesa').value, 10) || 0,
      telefono:   document.getElementById('rTelefono').value.trim(),
      estado:     document.getElementById('rEstado').value,
      comentario: document.getElementById('rComentario').value.trim(),
    };

    if (!payload.nombre) { marcarError('nombre', 'El nombre es obligatorio.'); return; }
    if (!payload.fecha)  { marcarError('fecha', 'Elegí una fecha.'); return; }
    if (!payload.hora)   { marcarError('hora', 'Elegí un horario.'); return; }

    setLoading(true);
    try {
      const res  = await fetch(BASE + '/api/editar_reserva.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const data = await res.json();

      if (data.ok) {
        cerrarModalReserva();
        mostrarToast(data.mensaje, 'exito');
        cargar();
      } else if (data.errores) {
        Object.keys(data.errores).forEach(c => marcarError(c, data.errores[c]));
        setLoading(false);
      } else {
        mostrarToast(data.mensaje || 'Error inesperado.', 'error');
        setLoading(false);
      }
    } catch {
      mostrarToast('No se pudo conectar con el servidor.', 'error');
      setLoading(false);
    }
  });

  // ══════════════════════════════════════════════════════════════════════════
  // MODAL: confirmar (cancelar / eliminar)
  // ══════════════════════════════════════════════════════════════════════════
  function pedirConfirmacion(tipo, d) {
    accionPend = { tipo: tipo, id: parseInt(d.id, 10), nombre: d.nombre, codigo: d.codigo };

    const esCancelar = tipo === 'cancelar';
    document.getElementById('modalConfirmarIcono').innerHTML =
      '<span class="material-symbols-outlined">' + (esCancelar ? 'event_busy' : 'delete') + '</span>';
    document.getElementById('modalConfirmarTitulo').textContent =
      esCancelar ? 'Cancelar reserva' : 'Eliminar reserva';
    document.getElementById('modalConfirmarSubtitulo').textContent =
      esCancelar ? 'La mesa queda libre otra vez.' : 'Se borra del historial. No se puede deshacer.';
    document.getElementById('modalConfirmarTexto').innerHTML = esCancelar
      ? '¿Cancelar la reserva <strong>' + esc(d.codigo) + '</strong> de <strong>' + esc(d.nombre) + '</strong>?'
      : '¿Eliminar definitivamente la reserva <strong>' + esc(d.codigo) + '</strong> de <strong>' + esc(d.nombre) + '</strong>?';
    document.getElementById('btnAceptarConfirmarTexto').textContent =
      esCancelar ? 'Cancelar reserva' : 'Eliminar';

    // El aviso por correo solo tiene sentido al anular.
    document.getElementById('bloqueAviso').style.display = esCancelar ? '' : 'none';
    document.getElementById('chkAvisar').checked = true;
    document.getElementById('txtMotivo').value = '';
    document.getElementById('grupoMotivo').style.display = '';

    abrirModal(modalConf);
  }

  document.getElementById('chkAvisar').addEventListener('change', e => {
    document.getElementById('grupoMotivo').style.display = e.target.checked ? '' : 'none';
  });

  function cerrarConfirmar() { cerrarModal(modalConf); accionPend = null; }

  document.getElementById('btnCerrarModalConfirmar').addEventListener('click', cerrarConfirmar);
  document.getElementById('btnCancelarConfirmar').addEventListener('click', cerrarConfirmar);
  modalConf.addEventListener('click', e => { if (e.target === modalConf) cerrarConfirmar(); });

  document.getElementById('btnAceptarConfirmar').addEventListener('click', async () => {
    if (!accionPend) return;
    const btn  = document.getElementById('btnAceptarConfirmar');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-outlined icono-spin">progress_activity</span> Procesando…';

    const esCancelar = accionPend.tipo === 'cancelar';
    const url  = BASE + (esCancelar ? '/api/cambiar_estado_reserva.php' : '/api/eliminar_reserva.php');
    const body = esCancelar
      ? {
          id: accionPend.id,
          estado: 'cancelada',
          avisar: document.getElementById('chkAvisar').checked,
          motivo: document.getElementById('txtMotivo').value.trim(),
        }
      : { id: accionPend.id };

    try {
      const res  = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
      });
      const data = await res.json();
      if (data.ok) {
        cerrarConfirmar();
        mostrarToast(data.mensaje, 'exito');
        cargar();
      } else {
        mostrarToast(data.mensaje || 'No se pudo completar la acción.', 'error');
      }
    } catch {
      mostrarToast('No se pudo conectar con el servidor.', 'error');
    } finally {
      btn.disabled = false;
      btn.innerHTML = orig;
    }
  });

  document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    if (modalReserva.classList.contains('modal-visible')) cerrarModalReserva();
    if (modalConf.classList.contains('modal-visible'))    cerrarConfirmar();
  });

  // ── Toast ────────────────────────────────────────────────────────────────
  function mostrarToast(texto, tipo) {
    const t = document.createElement('div');
    t.className = 'toast toast-' + tipo;
    t.innerHTML = '<span class="material-symbols-outlined toast-icono">'
      + (tipo === 'exito' ? 'check_circle' : 'error') + '</span><span>' + esc(texto) + '</span>';
    document.body.appendChild(t);
    t.getBoundingClientRect();
    t.classList.add('toast-visible');
    setTimeout(() => {
      t.classList.remove('toast-visible');
      t.addEventListener('transitionend', () => t.remove(), { once: true });
    }, 3500);
  }

  cargar();
})();
</script>

<?php require_once '../includes/footer.php'; ?>
