// Carta y precios: alta, edición, pausa y datos fiscales del local.
(() => {
  const { api, plata, abrirModal, cerrarModal } = Caja;
  const e = escaparHtml;
  const $ = id => document.getElementById(id);
  let datos = null, categoria = '', busqueda = '';

  async function cargar() {
    try {
      datos = await api('/api/carta.php?todos=1');
    } catch (err) {
      $('ctTabla').innerHTML = `<div class="gu-estado"><p>${e(err.message)}</p></div>`;
      return;
    }
    pintar();
    const a = datos.ajustes;
    $('ajRazon').value = a.razon_social;
    $('ajRut').value = a.rut;
    $('ajPropina').value = a.propina_sugerida;
    $('ajFondo').value = a.fondo_sugerido;
  }

  function pintar() {
    const q = busqueda.trim().toLowerCase();
    const lista = datos.productos.filter(p => (!categoria || p.categoria === categoria) && (!q || p.nombre.toLowerCase().includes(q)));
    if (!lista.length) {
      $('ctTabla').innerHTML = '<div class="gu-estado"><span class="material-symbols-outlined" style="font-size:2.5rem;color:#73796f" aria-hidden="true">restaurant_menu</span><p>No hay productos en esta vista.</p></div>';
      return;
    }
    $('ctTabla').innerHTML = `<table class="gu-tabla ct-tabla"><thead><tr><th>Producto</th><th>Categoría</th><th>Precio</th><th>IVA</th><th>Va a</th><th class="col-acciones"><span class="sr-only">Acciones</span></th></tr></thead><tbody>
      ${lista.map(p => `<tr class="${p.activo ? '' : 'ct-pausado'}">
        <td><span class="gu-nombre">${e(p.nombre)}</span>${p.activo ? '' : ' <span class="badge-rol badge-usuario">Pausado</span>'}</td>
        <td>${e(datos.categorias[p.categoria] || p.categoria)}</td>
        <td class="cj-mono">${plata(p.precio)}</td>
        <td>${e(datos.iva[p.iva] || p.iva)}</td>
        <td>${e(datos.destinos[p.destino] || p.destino)}</td>
        <td><div class="gu-acciones">
          <button type="button" class="btn-tabla" data-editar="${p.id}" aria-label="Editar ${e(p.nombre)}"><span class="material-symbols-outlined">edit</span></button>
          <button type="button" class="btn-tabla" data-pausar="${p.id}" aria-label="${p.activo ? 'Pausar' : 'Activar'} ${e(p.nombre)}" title="${p.activo ? 'Pausar (no se puede pedir)' : 'Volver a ofrecer'}"><span class="material-symbols-outlined">${p.activo ? 'pause_circle' : 'play_circle'}</span></button>
        </div></td></tr>`).join('')}
      </tbody></table>`;
  }

  function abrirProducto(p) {
    $('prTitulo').textContent = p ? 'Editar producto' : 'Nuevo producto';
    $('prId').value = p?.id || '';
    $('prNombre').value = p?.nombre || '';
    $('prCategoria').value = p?.categoria || categoria || 'principales';
    $('prPrecio').value = p ? p.precio : '';
    $('prIva').value = p?.iva || 'basica';
    $('prDestino').value = p?.destino || 'cocina';
    $('prActivo').checked = p ? p.activo : true;
    $('btnEliminarProducto').hidden = !p;
    ['prErrorNombre', 'prErrorPrecio'].forEach(id => { $(id).textContent = ''; });
    abrirModal('modalProducto');
  }

  async function guardar(p) {
    try {
      const r = await api('/api/carta.php', { accion: 'guardar', ...p });
      mostrarToast(r.mensaje, 'exito');
      return true;
    } catch (err) {
      const errores = err.datos?.errores || {};
      $('prErrorNombre').textContent = errores.nombre || '';
      $('prErrorPrecio').textContent = errores.precio || '';
      mostrarToast(err.message, 'error');
      return false;
    }
  }

  $('btnNuevoProducto').addEventListener('click', () => abrirProducto(null));
  $('formProducto').addEventListener('submit', async ev => {
    ev.preventDefault();
    const ok = await guardar({
      id: Number($('prId').value) || 0, nombre: $('prNombre').value, categoria: $('prCategoria').value,
      precio: $('prPrecio').value, iva: $('prIva').value, destino: $('prDestino').value, activo: $('prActivo').checked,
    });
    if (ok) { cerrarModal('modalProducto'); cargar(); }
  });
  $('btnEliminarProducto').addEventListener('click', async () => {
    const id = Number($('prId').value);
    if (!id || !confirm('¿Eliminar este producto de la carta?')) return;
    try {
      const r = await api('/api/carta.php', { accion: 'eliminar', id });
      mostrarToast(r.mensaje, 'exito');
      cerrarModal('modalProducto');
      cargar();
    } catch (err) { mostrarToast(err.message, 'error'); }
  });

  $('ctTabla').addEventListener('click', async ev => {
    const b = ev.target.closest('button');
    if (!b) return;
    const p = datos.productos.find(x => x.id === Number(b.dataset.editar || b.dataset.pausar));
    if (!p) return;
    if (b.dataset.editar) return abrirProducto(p);
    if (await guardar({ ...p, activo: !p.activo })) cargar();
  });

  $('ctFiltros').addEventListener('click', ev => {
    const b = ev.target.closest('[data-cat]');
    if (!b) return;
    categoria = b.dataset.cat;
    document.querySelectorAll('#ctFiltros .filtro-btn').forEach(x => x.classList.toggle('filtro-activo', x === b));
    pintar();
  });
  $('ctBuscar').addEventListener('input', ev => { busqueda = ev.target.value; if (datos) pintar(); });

  $('ajRut').addEventListener('input', ev => { ev.target.value = ev.target.value.replace(/\D/g, ''); });
  $('formAjustes').addEventListener('submit', async ev => {
    ev.preventDefault();
    $('ajErrorRut').textContent = ''; $('ajErrorPropina').textContent = '';
    try {
      const r = await api('/api/carta.php', {
        accion: 'ajustes', razon_social: $('ajRazon').value, rut: $('ajRut').value,
        propina_sugerida: Number($('ajPropina').value) || 0, fondo_sugerido: Number($('ajFondo').value) || 0,
      });
      mostrarToast(r.mensaje, 'exito');
    } catch (err) {
      const errores = err.datos?.errores || {};
      $('ajErrorRut').textContent = errores.rut || '';
      $('ajErrorPropina').textContent = errores.propina_sugerida || '';
      mostrarToast(err.message, 'error');
    }
  });

  cargar();
})();
