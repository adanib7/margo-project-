<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/check_auth.php';
require_once '../includes/inventario_db.php';
requireRole('admin', 'superadmin');

$pageTitle = 'Inventario';
$pageCSS = '../assets/css/dashboard.css';
$showDashboardBottomNav = true;

$volverUrl = ($_SESSION['rol'] ?? '') === 'superadmin'
    ? buildUrl('/dashboards/superadmin.php')
    : buildUrl('/dashboards/admin.php');

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
      <h1 class="titulo-pagina" style="margin-top:.5rem">Inventario</h1>
      <p class="subtitulo-pagina">Controlá el stock de ingredientes, bebidas y suministros del local.</p>
    </div>
    <button class="boton-accion" id="btnNuevoItem">
      <span class="material-symbols-outlined">add</span>
      Nuevo artículo
    </button>
  </header>

  <!-- ── Stats ── -->
  <div class="gu-stats">
    <div class="gu-stat">
      <div class="icono"><span class="material-symbols-outlined">inventory_2</span></div>
      <div>
        <p class="texto-etiqueta">Artículos</p>
        <p class="numero-resumen" id="statArticulos">—</p>
      </div>
    </div>
    <div class="gu-stat">
      <div class="icono icono-gold"><span class="material-symbols-outlined">warning</span></div>
      <div>
        <p class="texto-etiqueta">Bajo stock</p>
        <p class="numero-resumen" id="statBajoStock">—</p>
      </div>
    </div>
    <div class="gu-stat">
      <div class="icono icono-primary"><span class="material-symbols-outlined">payments</span></div>
      <div>
        <p class="texto-etiqueta">Valor del inventario</p>
        <p class="numero-resumen" id="statValor">—</p>
      </div>
    </div>
  </div>

  <!-- ── Barra búsqueda + filtros ── -->
  <div class="gu-barra">
    <div class="campo-input-wrapper gu-busqueda">
      <span class="campo-icono material-symbols-outlined">search</span>
      <input class="campo-input" type="text" id="inputBusqueda" placeholder="Buscar por nombre o proveedor…">
    </div>
    <div class="filtros-rol" role="group" aria-label="Filtrar por categoría">
      <button class="filtro-btn filtro-activo" data-cat="">Todas</button>
      <?php foreach (INV_CATEGORIAS as $slug => $label): ?>
        <button class="filtro-btn" data-cat="<?= htmlspecialchars($slug, ENT_QUOTES) ?>"><?= htmlspecialchars($label, ENT_QUOTES) ?></button>
      <?php endforeach; ?>
      <button class="filtro-btn filtro-bajo" data-cat="__bajo__">
        <span class="material-symbols-outlined" style="font-size:1rem;vertical-align:-2px">warning</span>
        Bajo stock
      </button>
    </div>
  </div>

  <!-- ── Tabla ── -->
  <div class="gu-tabla-wrap" id="invTablaWrap">
    <div class="gu-estado">
      <span class="material-symbols-outlined icono-spin" style="font-size:2rem;color:#264220">progress_activity</span>
      <p>Cargando inventario…</p>
    </div>
  </div>

</main>

<!-- ══════════════════════════════════════
     MODAL: Crear / Editar artículo
════════════════════════════════════════ -->
<div class="modal-fondo" id="modalItem" role="dialog" aria-modal="true" aria-labelledby="modalItemTitulo">
  <div class="modal-contenedor">
    <div class="modal-encabezado">
      <div class="modal-icono" id="modalItemIcono">
        <span class="material-symbols-outlined">add</span>
      </div>
      <div>
        <h2 class="modal-titulo" id="modalItemTitulo">Nuevo artículo</h2>
        <p class="modal-subtitulo" id="modalItemSubtitulo">Completá los datos del artículo a inventariar.</p>
      </div>
      <button class="modal-cerrar" id="btnCerrarModalItem" aria-label="Cerrar">
        <span class="material-symbols-outlined">close</span>
      </button>
    </div>

    <form class="modal-form" id="formItem" novalidate>
      <input type="hidden" id="itemId" value="">

      <div class="campo-grupo">
        <label class="campo-etiqueta" for="iNombre">Nombre</label>
        <div class="campo-input-wrapper">
          <span class="campo-icono material-symbols-outlined">label</span>
          <input class="campo-input" type="text" id="iNombre" name="nombre" placeholder="Ej. Faba de la Granja" maxlength="120" required>
        </div>
        <span class="campo-error" id="iErrorNombre"></span>
      </div>

      <div class="inv-form-fila">
        <div class="campo-grupo">
          <label class="campo-etiqueta" for="iCategoria">Categoría</label>
          <div class="campo-input-wrapper">
            <span class="campo-icono material-symbols-outlined">category</span>
            <select class="campo-input campo-select" id="iCategoria" name="categoria" required>
              <?php foreach (INV_CATEGORIAS as $slug => $label): ?>
                <option value="<?= htmlspecialchars($slug, ENT_QUOTES) ?>"><?= htmlspecialchars($label, ENT_QUOTES) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <span class="campo-error" id="iErrorCategoria"></span>
        </div>

        <div class="campo-grupo">
          <label class="campo-etiqueta" for="iUnidad">Unidad</label>
          <div class="campo-input-wrapper">
            <span class="campo-icono material-symbols-outlined">straighten</span>
            <select class="campo-input campo-select" id="iUnidad" name="unidad" required>
              <?php foreach (INV_UNIDADES as $slug => $label): ?>
                <option value="<?= htmlspecialchars($slug, ENT_QUOTES) ?>"><?= htmlspecialchars($label, ENT_QUOTES) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <span class="campo-error" id="iErrorUnidad"></span>
        </div>
      </div>

      <div class="inv-form-fila">
        <div class="campo-grupo">
          <label class="campo-etiqueta" for="iStock">Stock actual</label>
          <div class="campo-input-wrapper">
            <span class="campo-icono material-symbols-outlined">inventory</span>
            <input class="campo-input" type="number" id="iStock" name="stock" min="0" step="0.01" placeholder="0" required>
          </div>
          <span class="campo-error" id="iErrorStock"></span>
        </div>

        <div class="campo-grupo">
          <label class="campo-etiqueta" for="iStockMinimo">Stock mínimo</label>
          <div class="campo-input-wrapper">
            <span class="campo-icono material-symbols-outlined">notification_important</span>
            <input class="campo-input" type="number" id="iStockMinimo" name="stock_minimo" min="0" step="0.01" placeholder="0" required>
          </div>
          <span class="campo-error" id="iErrorStockMinimo"></span>
        </div>
      </div>

      <div class="inv-form-fila">
        <div class="campo-grupo">
          <label class="campo-etiqueta" for="iPrecio">Precio unitario (€)</label>
          <div class="campo-input-wrapper">
            <span class="campo-icono material-symbols-outlined">euro</span>
            <input class="campo-input" type="number" id="iPrecio" name="precio_unitario" min="0" step="0.01" placeholder="0.00" required>
          </div>
          <span class="campo-error" id="iErrorPrecio"></span>
        </div>

        <div class="campo-grupo">
          <label class="campo-etiqueta" for="iProveedor">Proveedor <span class="campo-opcional">(opcional)</span></label>
          <div class="campo-input-wrapper">
            <span class="campo-icono material-symbols-outlined">local_shipping</span>
            <input class="campo-input" type="text" id="iProveedor" name="proveedor" placeholder="Ej. Llagar Trabanco" maxlength="120">
          </div>
          <span class="campo-error" id="iErrorProveedor"></span>
        </div>
      </div>

      <div class="modal-acciones">
        <button type="button" class="boton-secundario" id="btnCancelarModalItem">Cancelar</button>
        <button type="submit" class="boton-accion boton-modal-submit" id="btnSubmitItem">
          <span class="material-symbols-outlined">check</span>
          <span id="btnSubmitItemTexto">Crear artículo</span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ══════════════════════════════════════
     MODAL: Confirmar eliminación
════════════════════════════════════════ -->
<div class="modal-fondo" id="modalEliminarItem" role="dialog" aria-modal="true" aria-labelledby="modalEliminarItemTitulo">
  <div class="modal-contenedor modal-contenedor-sm">
    <div class="modal-encabezado">
      <div class="modal-icono modal-icono-danger">
        <span class="material-symbols-outlined">delete</span>
      </div>
      <div>
        <h2 class="modal-titulo" id="modalEliminarItemTitulo">Eliminar artículo</h2>
        <p class="modal-subtitulo">Esta acción no se puede deshacer.</p>
      </div>
      <button class="modal-cerrar" id="btnCerrarModalEliminarItem" aria-label="Cerrar">
        <span class="material-symbols-outlined">close</span>
      </button>
    </div>

    <p class="eliminar-descripcion">
      ¿Estás seguro de que querés eliminar <strong id="eliminarItemTarget"></strong> del inventario?
    </p>

    <div class="modal-acciones">
      <button type="button" class="boton-secundario" id="btnCancelarEliminarItem">Cancelar</button>
      <button type="button" class="boton-peligro" id="btnConfirmarEliminarItem">
        <span class="material-symbols-outlined">delete</span>
        Eliminar
      </button>
    </div>
  </div>
</div>

<script>
(function () {
  const BASE = '<?= BASE_URL ?>';

  // ── Estado ───────────────────────────────────────────────────────────────
  let filtroCat  = '';
  let soloBajo   = false;
  let busqueda   = '';
  let debounceT  = null;
  let eliminarId = null;
  let modoEditar = false;
  let catLabels  = {};
  let uniLabels  = {};

  // ── Refs ─────────────────────────────────────────────────────────────────
  const tablaWrap = document.getElementById('invTablaWrap');
  const modalItem = document.getElementById('modalItem');
  const formItem  = document.getElementById('formItem');
  const modalEliminar = document.getElementById('modalEliminarItem');
  const btnSubmit = document.getElementById('btnSubmitItem');

  const CAMPOS = {
    nombre:          ['iNombre',       'iErrorNombre'],
    categoria:       ['iCategoria',    'iErrorCategoria'],
    unidad:          ['iUnidad',       'iErrorUnidad'],
    stock:           ['iStock',        'iErrorStock'],
    stock_minimo:    ['iStockMinimo',  'iErrorStockMinimo'],
    precio_unitario: ['iPrecio',       'iErrorPrecio'],
    proveedor:       ['iProveedor',    'iErrorProveedor'],
  };

  // ── Helpers ──────────────────────────────────────────────────────────────
  function esc(str) {
    return String(str ?? '')
      .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
      .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
  }

  const nf = new Intl.NumberFormat('es-ES', { maximumFractionDigits: 2 });
  const cf = new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR' });

  function formatFecha(str) {
    const d = new Date((str || '').replace(' ', 'T'));
    if (isNaN(d)) return '—';
    return d.toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' });
  }

  function mostrarEstado(html) {
    tablaWrap.innerHTML = `<div class="gu-estado">${html}</div>`;
  }

  // ── Cargar inventario ────────────────────────────────────────────────────
  async function cargarInventario() {
    mostrarEstado('<span class="material-symbols-outlined icono-spin" style="font-size:2rem;color:#264220">progress_activity</span><p>Cargando…</p>');

    const params = new URLSearchParams();
    if (busqueda)  params.set('q', busqueda);
    if (filtroCat) params.set('categoria', filtroCat);
    if (soloBajo)  params.set('bajo', '1');

    try {
      const res  = await fetch(BASE + '/api/listar_inventario.php?' + params);
      const data = await res.json();
      if (!data.ok) throw new Error(data.mensaje || 'Error al cargar.');

      catLabels = data.categorias || {};
      uniLabels = data.unidades || {};
      actualizarStats(data.totales);
      renderTabla(data.items);
    } catch (e) {
      mostrarEstado(`<span class="material-symbols-outlined" style="font-size:2rem;color:#b91c1c">error</span><p>${esc(e.message)}</p>`);
    }
  }

  function actualizarStats(t) {
    document.getElementById('statArticulos').textContent = t.articulos ?? 0;
    document.getElementById('statBajoStock').textContent = t.bajo_stock ?? 0;
    document.getElementById('statValor').textContent     = cf.format(t.valor_total ?? 0);
  }

  // ── Render tabla ─────────────────────────────────────────────────────────
  function renderTabla(items) {
    if (!items.length) {
      mostrarEstado('<span class="material-symbols-outlined" style="font-size:2.5rem;color:#73796f">search_off</span><p>No se encontraron artículos.</p>');
      return;
    }

    const filas = items.map(it => {
      const uni = uniLabels[it.unidad] || it.unidad;
      const cat = catLabels[it.categoria] || it.categoria;
      const bajo = it.bajo_stock
        ? '<span class="badge-rol inv-badge-bajo" title="Stock por debajo del mínimo">Bajo</span>'
        : '';
      return `
        <tr data-id="${it.id}">
          <td>
            <div class="gu-usuario-celda">
              <span class="inv-cat-dot inv-cat-${esc(it.categoria)}"></span>
              <span class="gu-nombre">${esc(it.nombre)}</span>
            </div>
          </td>
          <td>${esc(cat)}</td>
          <td>
            <div class="inv-stock-cell">
              <button class="inv-step" data-delta="-1" title="Restar 1">
                <span class="material-symbols-outlined">remove</span>
              </button>
              <span class="inv-stock-num${it.bajo_stock ? ' es-bajo' : ''}">${nf.format(it.stock)}</span>
              <button class="inv-step" data-delta="1" title="Sumar 1">
                <span class="material-symbols-outlined">add</span>
              </button>
              <span class="inv-unidad">${esc(uni)}</span>
              ${bajo}
            </div>
          </td>
          <td class="gu-fecha">${nf.format(it.stock_minimo)} ${esc(uni)}</td>
          <td>${cf.format(it.precio_unitario)}</td>
          <td>${cf.format(it.stock * it.precio_unitario)}</td>
          <td class="gu-email">${esc(it.proveedor || '—')}</td>
          <td class="gu-fecha">${formatFecha(it.actualizado_en)}</td>
          <td>
            <div class="gu-acciones">
              <button class="btn-tabla btn-editar" title="Editar"
                data-item='${esc(JSON.stringify(it))}'>
                <span class="material-symbols-outlined">edit</span>
              </button>
              <button class="btn-tabla btn-eliminar" title="Eliminar"
                data-id="${it.id}" data-nombre="${esc(it.nombre)}">
                <span class="material-symbols-outlined">delete</span>
              </button>
            </div>
          </td>
        </tr>`;
    }).join('');

    tablaWrap.innerHTML = `
      <table class="gu-tabla inv-tabla">
        <thead>
          <tr>
            <th>Artículo</th>
            <th>Categoría</th>
            <th>Stock</th>
            <th>Mínimo</th>
            <th>Precio unit.</th>
            <th>Valor</th>
            <th>Proveedor</th>
            <th>Actualizado</th>
            <th class="col-acciones">Acciones</th>
          </tr>
        </thead>
        <tbody>${filas}</tbody>
      </table>`;

    tablaWrap.querySelectorAll('.btn-editar').forEach(btn => {
      btn.addEventListener('click', () => abrirModalEditar(JSON.parse(btn.dataset.item)));
    });
    tablaWrap.querySelectorAll('.btn-eliminar').forEach(btn => {
      btn.addEventListener('click', () => abrirModalEliminar(btn.dataset.id, btn.dataset.nombre));
    });
    tablaWrap.querySelectorAll('.inv-step').forEach(btn => {
      btn.addEventListener('click', () => ajustarStock(btn));
    });
  }

  // ── Ajuste rápido de stock ───────────────────────────────────────────────
  async function ajustarStock(btn) {
    const fila  = btn.closest('tr');
    const id    = parseInt(fila.dataset.id, 10);
    const delta = parseFloat(btn.dataset.delta);
    fila.querySelectorAll('.inv-step').forEach(b => b.disabled = true);

    try {
      const res  = await fetch(BASE + '/api/ajustar_stock.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ id, delta }),
      });
      const data = await res.json();
      if (data.ok) {
        cargarInventario();
      } else {
        mostrarToast(data.mensaje || 'No se pudo ajustar.', 'error');
        fila.querySelectorAll('.inv-step').forEach(b => b.disabled = false);
      }
    } catch {
      mostrarToast('No se pudo conectar con el servidor.', 'error');
      fila.querySelectorAll('.inv-step').forEach(b => b.disabled = false);
    }
  }

  // ── Búsqueda y filtros ───────────────────────────────────────────────────
  document.getElementById('inputBusqueda').addEventListener('input', e => {
    clearTimeout(debounceT);
    busqueda = e.target.value.trim();
    debounceT = setTimeout(cargarInventario, 320);
  });

  document.querySelectorAll('.filtro-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.filtro-btn').forEach(b => b.classList.remove('filtro-activo'));
      btn.classList.add('filtro-activo');
      const cat = btn.dataset.cat;
      if (cat === '__bajo__') {
        soloBajo = true;  filtroCat = '';
      } else {
        soloBajo = false; filtroCat = cat;
      }
      cargarInventario();
    });
  });

  // ══════════════════════════════════════════════════════════════════════════
  // MODAL: Crear / Editar
  // ══════════════════════════════════════════════════════════════════════════
  function abrirModal(modal) {
    modal.classList.add('modal-visible');
    document.body.classList.add('modal-abierto');
  }
  function cerrarModal(modal) {
    modal.classList.remove('modal-visible');
    document.body.classList.remove('modal-abierto');
  }

  function limpiarErrores() {
    Object.values(CAMPOS).forEach(([, errId]) => document.getElementById(errId).textContent = '');
    modalItem.querySelectorAll('.campo-wrapper-error').forEach(el => el.classList.remove('campo-wrapper-error'));
  }
  function marcarError(campo, texto) {
    const [inputId, errId] = CAMPOS[campo] || [];
    if (!inputId) return;
    document.getElementById(inputId).closest('.campo-input-wrapper').classList.add('campo-wrapper-error');
    document.getElementById(errId).textContent = texto;
  }

  function setLoading(on) {
    btnSubmit.disabled = on;
    const ic = btnSubmit.querySelector('.material-symbols-outlined');
    ic.textContent = on ? 'progress_activity' : 'check';
    ic.classList.toggle('icono-spin', on);
  }

  function abrirModalCrear() {
    modoEditar = false;
    formItem.reset();
    document.getElementById('itemId').value = '';
    document.getElementById('modalItemIcono').innerHTML = '<span class="material-symbols-outlined">add</span>';
    document.getElementById('modalItemTitulo').textContent   = 'Nuevo artículo';
    document.getElementById('modalItemSubtitulo').textContent = 'Completá los datos del artículo a inventariar.';
    document.getElementById('btnSubmitItemTexto').textContent = 'Crear artículo';
    limpiarErrores();
    abrirModal(modalItem);
    document.getElementById('iNombre').focus();
  }

  function abrirModalEditar(it) {
    modoEditar = true;
    document.getElementById('itemId').value       = it.id;
    document.getElementById('iNombre').value       = it.nombre;
    document.getElementById('iCategoria').value    = it.categoria;
    document.getElementById('iUnidad').value       = it.unidad;
    document.getElementById('iStock').value        = it.stock;
    document.getElementById('iStockMinimo').value  = it.stock_minimo;
    document.getElementById('iPrecio').value       = it.precio_unitario;
    document.getElementById('iProveedor').value    = it.proveedor || '';
    document.getElementById('modalItemIcono').innerHTML = '<span class="material-symbols-outlined">edit</span>';
    document.getElementById('modalItemTitulo').textContent   = 'Editar artículo';
    document.getElementById('modalItemSubtitulo').textContent = `Editando «${it.nombre}».`;
    document.getElementById('btnSubmitItemTexto').textContent = 'Guardar cambios';
    limpiarErrores();
    abrirModal(modalItem);
    document.getElementById('iNombre').focus();
  }

  function cerrarModalItem() {
    cerrarModal(modalItem);
    formItem.reset();
    limpiarErrores();
    setLoading(false);
  }

  document.getElementById('btnNuevoItem').addEventListener('click', abrirModalCrear);
  document.getElementById('btnCerrarModalItem').addEventListener('click', cerrarModalItem);
  document.getElementById('btnCancelarModalItem').addEventListener('click', cerrarModalItem);
  modalItem.addEventListener('click', e => { if (e.target === modalItem) cerrarModalItem(); });

  function validarFront() {
    let ok = true;
    const v = id => document.getElementById(id).value.trim();

    if (!v('iNombre')) { marcarError('nombre', 'El nombre es obligatorio.'); ok = false; }

    [['iStock','stock'], ['iStockMinimo','stock_minimo'], ['iPrecio','precio_unitario']].forEach(([id, campo]) => {
      const raw = v(id);
      if (raw === '' || isNaN(Number(raw))) { marcarError(campo, 'Ingresá un número válido.'); ok = false; }
      else if (Number(raw) < 0) { marcarError(campo, 'No puede ser negativo.'); ok = false; }
    });
    return ok;
  }

  formItem.addEventListener('submit', async e => {
    e.preventDefault();
    limpiarErrores();
    if (!validarFront()) return;

    setLoading(true);

    const payload = {
      nombre:          document.getElementById('iNombre').value.trim(),
      categoria:       document.getElementById('iCategoria').value,
      unidad:          document.getElementById('iUnidad').value,
      stock:           document.getElementById('iStock').value,
      stock_minimo:    document.getElementById('iStockMinimo').value,
      precio_unitario: document.getElementById('iPrecio').value,
      proveedor:       document.getElementById('iProveedor').value.trim(),
    };

    let endpoint = BASE + '/api/crear_item.php';
    if (modoEditar) {
      endpoint = BASE + '/api/editar_item.php';
      payload.id = parseInt(document.getElementById('itemId').value, 10);
    }

    try {
      const res  = await fetch(endpoint, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify(payload),
      });
      const data = await res.json();

      if (data.ok) {
        cerrarModalItem();
        mostrarToast(data.mensaje, 'exito');
        cargarInventario();
      } else if (data.errores) {
        Object.entries(data.errores).forEach(([campo, msg]) => marcarError(campo, msg));
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
  // MODAL: Eliminar
  // ══════════════════════════════════════════════════════════════════════════
  function abrirModalEliminar(id, nombre) {
    eliminarId = parseInt(id, 10);
    document.getElementById('eliminarItemTarget').textContent = `«${nombre}»`;
    abrirModal(modalEliminar);
  }
  function cerrarModalEliminar() {
    cerrarModal(modalEliminar);
    eliminarId = null;
  }

  document.getElementById('btnCerrarModalEliminarItem').addEventListener('click', cerrarModalEliminar);
  document.getElementById('btnCancelarEliminarItem').addEventListener('click', cerrarModalEliminar);
  modalEliminar.addEventListener('click', e => { if (e.target === modalEliminar) cerrarModalEliminar(); });

  document.getElementById('btnConfirmarEliminarItem').addEventListener('click', async () => {
    if (!eliminarId) return;
    const btn = document.getElementById('btnConfirmarEliminarItem');
    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-outlined icono-spin">progress_activity</span> Eliminando…';

    try {
      const res  = await fetch(BASE + '/api/eliminar_item.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ id: eliminarId }),
      });
      const data = await res.json();
      if (data.ok) {
        cerrarModalEliminar();
        mostrarToast(data.mensaje, 'exito');
        cargarInventario();
      } else {
        mostrarToast(data.mensaje || 'Error al eliminar.', 'error');
      }
    } catch {
      mostrarToast('No se pudo conectar con el servidor.', 'error');
    } finally {
      btn.disabled = false;
      btn.innerHTML = '<span class="material-symbols-outlined">delete</span> Eliminar';
    }
  });

  document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    if (modalItem.classList.contains('modal-visible'))     cerrarModalItem();
    if (modalEliminar.classList.contains('modal-visible')) cerrarModalEliminar();
  });

  // ══════════════════════════════════════════════════════════════════════════
  // Toast
  // ══════════════════════════════════════════════════════════════════════════
  function mostrarToast(texto, tipo) {
    const t = document.createElement('div');
    t.className = 'toast toast-' + tipo;
    t.innerHTML = `<span class="material-symbols-outlined toast-icono">${tipo === 'exito' ? 'check_circle' : 'error'}</span><span>${esc(texto)}</span>`;
    document.body.appendChild(t);
    t.getBoundingClientRect();
    t.classList.add('toast-visible');
    setTimeout(() => {
      t.classList.remove('toast-visible');
      t.addEventListener('transitionend', () => t.remove(), { once: true });
    }, 3500);
  }

  // ── Arranque ─────────────────────────────────────────────────────────────
  cargarInventario();
})();
</script>

<?php require_once '../includes/footer.php'; ?>
