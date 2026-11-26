// Utilidades compartidas por la caja (caja.js) y la vista del mozo (mozo.js).
// Requiere comun.js (escaparHtml, mostrarToast) y la constante BASE de la página.
const Caja = (() => {
  const formato = new Intl.NumberFormat('es-UY', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
  const plata = n => {
    const v = Math.round((Number(n) || 0) * 100) / 100;
    return (v < 0 ? '− ' : '') + '$ ' + formato.format(Math.abs(v));
  };
  const e = escaparHtml;

  async function api(ruta, datos) {
    const opciones = datos === undefined
      ? { headers: { Accept: 'application/json' } }
      : { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify(datos) };
    let res;
    try {
      res = await fetch(BASE + ruta, opciones);
    } catch {
      throw new Error('Sin conexión. Revisá internet e intentá de nuevo.');
    }
    if (res.status === 401) {
      window.location.href = BASE + '/ingresar?sesion=expirada';
      throw new Error('Tu sesión terminó.');
    }
    let json;
    try { json = await res.json(); } catch { throw new Error('Respuesta inesperada del servidor.'); }
    if (!json.ok) {
      const error = new Error(json.mensaje || 'No se pudo completar la acción.');
      error.datos = json;
      throw error;
    }
    return json;
  }

  const accion = (nombre, datos = {}) => api('/api/caja_accion.php', { accion: nombre, ...datos });

  function imprimir(query) {
    const ventana = window.open(BASE + '/includes/caja_imprimir.php?' + query, '_blank', 'width=420,height=720');
    if (!ventana) mostrarToast('El navegador bloqueó la ventana de impresión. Permitila para este sitio.', 'error', 5000);
  }

  /* ── Modales simples (mismas clases que el resto del panel) ── */
  function abrirModal(id) {
    const m = document.getElementById(id);
    m.classList.add('modal-visible');
    document.body.classList.add('modal-abierto');
    setTimeout(() => m.querySelector('[autofocus], input, select, button:not(.modal-cerrar)')?.focus(), 50);
  }
  function cerrarModal(id) {
    document.getElementById(id).classList.remove('modal-visible');
    if (!document.querySelector('.modal-fondo.modal-visible')) document.body.classList.remove('modal-abierto');
  }
  document.addEventListener('click', ev => {
    const cerrar = ev.target.closest('[data-cerrar]');
    if (cerrar) cerrarModal(cerrar.dataset.cerrar);
    else if (ev.target.classList.contains('modal-fondo')) cerrarModal(ev.target.id);
  });
  document.addEventListener('keydown', ev => {
    if (ev.key !== 'Escape') return;
    const abiertos = document.querySelectorAll('.modal-fondo.modal-visible');
    if (abiertos.length) cerrarModal(abiertos[abiertos.length - 1].id);
  });
  const hayModalAbierto = () => !!document.querySelector('.modal-fondo.modal-visible');

  const letras = n => Array.from({ length: Math.max(0, Math.min(26, n)) }, (_, i) => String.fromCharCode(65 + i));

  /* ── Selector de carta: arma un pedido y lo envía a la cuenta ── */
  let carta = null;
  async function cargarCarta(forzar = false) {
    if (!carta || forzar) carta = await api('/api/carta.php');
    return carta;
  }

  const selector = { cuenta: null, pedido: [], asiento: '', categoria: '', busqueda: '', alEnviar: null, enviando: false };

  async function abrirSelector(cuenta, alEnviar) {
    try { await cargarCarta(); } catch (err) { mostrarToast(err.message, 'error'); return; }
    Object.assign(selector, { cuenta, pedido: [], asiento: '', busqueda: '', alEnviar, enviando: false });
    selector.categoria = selector.categoria && carta.productos.some(p => p.categoria === selector.categoria)
      ? selector.categoria : (carta.productos[0]?.categoria || '');
    document.getElementById('selTitulo').textContent = cuenta.mesa_numero ? 'Pedido · mesa ' + cuenta.mesa_numero : 'Pedido · mostrador';
    document.getElementById('selBuscar').value = '';
    pintarSelector();
    abrirModal('modalCarta');
  }

  function pintarSelector() {
    const cats = Object.entries(carta.categorias).filter(([k]) => carta.productos.some(p => p.categoria === k));
    document.getElementById('selCategorias').innerHTML = cats.map(([k, label]) =>
      `<button type="button" class="cj-tab ${k === selector.categoria && !selector.busqueda ? 'activo' : ''}" data-cat="${e(k)}" aria-pressed="${k === selector.categoria}">${e(label)}</button>`).join('');

    const asientos = letras(selector.cuenta.comensales);
    document.getElementById('selAsientos').innerHTML =
      [['', 'Compartido'], ...asientos.map(l => [l, l])].map(([v, label]) =>
        `<button type="button" class="cj-asiento-chip ${selector.asiento === v ? 'activo' : ''}" data-asiento="${v}" aria-pressed="${selector.asiento === v}">${e(label)}</button>`).join('');

    const q = selector.busqueda.trim().toLowerCase();
    const lista = carta.productos.filter(p => q ? p.nombre.toLowerCase().includes(q) : p.categoria === selector.categoria);
    const grilla = document.getElementById('selProductos');
    const scrollGrilla = grilla.scrollTop;
    grilla.innerHTML = lista.length ? lista.map(p => {
      const enPedido = selector.pedido.filter(x => x.producto_id === p.id).reduce((s, x) => s + x.cantidad, 0);
      return `<button type="button" class="cj-producto ${enPedido ? 'elegido' : ''}" data-producto="${p.id}" aria-label="${e(p.nombre)}, ${plata(p.precio)}${enPedido ? ', ' + enPedido + ' en el pedido' : ''}">
        <span class="cj-producto-nombre">${e(p.nombre)}</span>
        <span class="cj-producto-precio">${plata(p.precio)}</span>
        ${enPedido ? `<span class="cj-producto-cant" aria-hidden="true">${enPedido}</span>` : ''}
      </button>`;
    }).join('') : '<p class="cj-vacio-chico" style="grid-column:1/-1">No hay productos que coincidan.</p>';
    grilla.scrollTop = scrollGrilla;

    const total = selector.pedido.reduce((s, x) => s + x.precio * x.cantidad, 0);
    const cant = selector.pedido.reduce((s, x) => s + x.cantidad, 0);
    document.getElementById('selResumen').textContent = cant ? `${cant} ${cant === 1 ? 'producto' : 'productos'} · ${plata(total)}` : '';
    // La nota va detrás de un botón: así cada línea ocupa una sola fila.
    document.getElementById('selPedido').innerHTML = selector.pedido.length ? selector.pedido.map((x, i) => `
      <li class="cj-pedido-fila">
        <div class="cj-pedido-linea">
          <span class="cj-asiento-mini ${x.asiento ? '' : 'compartido'}" title="${x.asiento ? 'Asiento ' + x.asiento : 'Compartido'}">${x.asiento || '∗'}</span>
          <span class="cj-pedido-nombre">${e(x.nombre)}${x.nota && !x.notaAbierta ? `<small>${e(x.nota)}</small>` : ''}</span>
          <span class="cj-pedido-precio">${plata(x.precio * x.cantidad)}</span>
          <button type="button" class="cj-nota-btn ${x.nota ? 'con-nota' : ''}" data-nota-toggle="${i}" aria-expanded="${!!x.notaAbierta}" aria-label="${x.nota ? 'Editar' : 'Agregar'} nota para ${e(x.nombre)}" title="Nota para cocina">
            <span class="material-symbols-outlined">${x.nota ? 'edit_note' : 'note_add'}</span></button>
          <span class="cj-stepper-mini">
            <button type="button" data-menos="${i}" aria-label="Uno menos de ${e(x.nombre)}"><span class="material-symbols-outlined">${x.cantidad === 1 ? 'delete' : 'remove'}</span></button>
            <span>${x.cantidad}</span>
            <button type="button" data-mas="${i}" aria-label="Uno más de ${e(x.nombre)}"><span class="material-symbols-outlined">add</span></button>
          </span>
        </div>
        ${x.notaAbierta ? `<input class="cj-nota" type="text" maxlength="160" data-nota="${i}" value="${e(x.nota)}" placeholder="Ej. sin sal, punto medio, sin cebolla…" aria-label="Nota para ${e(x.nombre)}">` : ''}
      </li>`).join('') : '<li class="cj-vacio-chico">Tocá los productos para armar el pedido.</li>';
    const btn = document.getElementById('selEnviar');
    btn.disabled = !cant || selector.enviando;
    btn.querySelector('span:last-child').textContent = cant ? `Enviar ${cant} · ${plata(total)}` : 'Enviar pedido';
  }

  function agregarProducto(id) {
    const p = carta.productos.find(x => x.id === id);
    if (!p) return;
    const existente = selector.pedido.find(x => x.producto_id === id && x.asiento === selector.asiento && !x.nota);
    if (existente) existente.cantidad++;
    else selector.pedido.push({ producto_id: id, nombre: p.nombre, precio: p.precio, cantidad: 1, asiento: selector.asiento, nota: '', notaAbierta: false });
    pintarSelector();
    // Mostrar la línea recién tocada al final de la lista.
    const fila = existente
      ? document.querySelectorAll('#selPedido .cj-pedido-fila')[selector.pedido.indexOf(existente)]
      : document.querySelector('#selPedido .cj-pedido-fila:last-child');
    fila?.scrollIntoView({ block: 'nearest' });
  }

  function iniciarSelector() {
    const modal = document.getElementById('modalCarta');
    if (!modal) return;
    modal.addEventListener('click', ev => {
      const t = ev.target.closest('button');
      if (!t) return;
      if (t.dataset.cat) { selector.categoria = t.dataset.cat; selector.busqueda = ''; document.getElementById('selBuscar').value = ''; pintarSelector(); }
      else if (t.dataset.asiento !== undefined) { selector.asiento = t.dataset.asiento; pintarSelector(); }
      else if (t.dataset.producto) agregarProducto(Number(t.dataset.producto));
      else if (t.dataset.notaToggle !== undefined) {
        const x = selector.pedido[t.dataset.notaToggle];
        x.nota = x.nota.trim();
        x.notaAbierta = !x.notaAbierta;
        pintarSelector();
        if (x.notaAbierta) document.querySelector(`#selPedido [data-nota="${t.dataset.notaToggle}"]`)?.focus();
      }
      else if (t.dataset.mas) { selector.pedido[t.dataset.mas].cantidad++; pintarSelector(); }
      else if (t.dataset.menos) {
        const x = selector.pedido[t.dataset.menos];
        if (--x.cantidad <= 0) selector.pedido.splice(t.dataset.menos, 1);
        pintarSelector();
      }
    });
    modal.addEventListener('input', ev => {
      if (ev.target.id === 'selBuscar') { selector.busqueda = ev.target.value; pintarSelector(); ev.target.focus(); }
      else if (ev.target.dataset.nota !== undefined) selector.pedido[ev.target.dataset.nota].nota = ev.target.value;
    });
    modal.addEventListener('keydown', ev => {
      const i = ev.target.dataset?.nota;
      if (i === undefined || ev.key !== 'Enter') return;
      ev.preventDefault();
      selector.pedido[i].nota = selector.pedido[i].nota.trim();
      selector.pedido[i].notaAbierta = false;
      pintarSelector();
      document.querySelector(`#selPedido [data-nota-toggle="${i}"]`)?.focus();
    });
    document.getElementById('selEnviar').addEventListener('click', async () => {
      if (!selector.pedido.length || selector.enviando) return;
      selector.enviando = true; pintarSelector();
      try {
        const r = await accion('agregar_items', {
          cuenta_id: selector.cuenta.id,
          items: selector.pedido.map(x => ({ producto_id: x.producto_id, cantidad: x.cantidad, asiento: x.asiento, nota: x.nota })),
        });
        cerrarModal('modalCarta');
        mostrarToast(r.mensaje, 'exito');
        selector.alEnviar?.(r.cuenta);
      } catch (err) {
        mostrarToast(err.message, 'error');
        if (err.datos?.mensaje?.includes('carta')) cargarCarta(true).then(pintarSelector);
      } finally {
        selector.enviando = false;
        if (document.getElementById('modalCarta').classList.contains('modal-visible')) pintarSelector();
      }
    });
  }
  document.addEventListener('DOMContentLoaded', iniciarSelector);

  return { api, accion, plata, imprimir, abrirModal, cerrarModal, hayModalAbierto, letras, abrirSelector, cargarCarta };
})();
