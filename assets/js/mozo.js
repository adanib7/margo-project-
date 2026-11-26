// Vista del mozo en su propio celular: abrir mesas, cargar pedidos y avisar que piden la cuenta.
// El cobro queda en la caja (no hace falta POS ni tablet en cada mozo).
(() => {
  const { api, accion, plata, hayModalAbierto, abrirSelector } = Caja;
  const e = escaparHtml;
  const $ = id => document.getElementById(id);

  const st = { datos: null, filtro: 'todas', mesaId: null, cuenta: null, firma: '', comensales: 2 };

  async function refrescar() {
    try {
      st.datos = await api('/api/caja_estado.php');
    } catch (err) {
      if (!st.datos) $('mzMesas').innerHTML = `<p class="cj-vacio-chico" style="grid-column:1/-1">${e(err.message)}</p>`;
      return;
    }
    pintarMesas();
    if (st.mesaId) await cargarCuenta();
  }

  const mesaSel = () => st.datos?.mesas.find(m => m.id === st.mesaId);
  const esMia = m => m.cuenta && m.cuenta.mozo_id === st.datos.usuario_id;

  function pintarMesas() {
    const { mesas } = st.datos;
    const mias = mesas.filter(esMia);
    $('mzAbiertas').textContent = mias.length;
    let lista = mesas;
    if (st.filtro === 'mias') lista = mias;
    if (st.filtro === 'libres') lista = mesas.filter(m => !m.cuenta);
    if (!mesas.length) {
      $('mzMesas').innerHTML = '<p class="cj-vacio-chico" style="grid-column:1/-1">Todavía no hay mesas cargadas en el plano. Pedile al encargado que lo arme.</p>';
      return;
    }
    $('mzMesas').innerHTML = lista.length ? lista.map(m => {
      const c = m.cuenta, r = m.reserva;
      const est = !c ? (r ? 'reservada' : 'libre') : c.estado === 'pide_cuenta' ? 'cuenta' : 'comiendo';
      const ajena = c && c.mozo_id && c.mozo_id !== st.datos.usuario_id && st.datos.rol === 'mozo';
      const sel = m.id === st.mesaId;
      const info = c ? `${c.comensales} pers. · ${c.minutos} min${c.mozo ? ' · ' + c.mozo : ''}`
        : r ? `${r.nombre} · ${r.personas} pers.` : `hasta ${m.capacidad} personas`;
      const etiquetas = { libre: 'libre', reservada: 'reserva ' + (r && r.hora), cuenta: 'pide cuenta', comiendo: 'comiendo' };
      return `<button type="button" class="mz-mesa ${est} ${ajena ? 'ajena' : ''} ${sel ? 'seleccionada' : ''}" data-mesa="${m.id}" aria-pressed="${sel}">
        <span class="mz-mesa-fila"><span class="mz-mesa-num">${m.numero}</span><span class="mz-mesa-estado">${e(etiquetas[est])}</span></span>
        <span class="mz-mesa-total">${c ? plata(c.total) : '—'}</span>
        <span class="mz-mesa-info">${e(info)}</span>
        ${c && r ? `<span class="mz-mesa-alerta">Reservada ${e(r.hora)} · ${r.minutos > 0 ? 'en ' + r.minutos + ' min' : 'ya'}</span>` : ''}
      </button>`;
    }).join('') : '<p class="cj-vacio-chico" style="grid-column:1/-1">No hay mesas en esta vista.</p>';
  }

  async function cargarCuenta(forzar = false) {
    const m = mesaSel();
    if (!m) return cerrarHoja();
    if (!m.cuenta) {
      if (st.cuenta || forzar || st.firma !== 'libre') { st.cuenta = null; st.firma = 'libre'; pintarHoja(); }
      return;
    }
    try {
      const r = await api('/api/caja_cuenta.php?id=' + m.cuenta.id);
      aplicar(r.cuenta, forzar);
    } catch (err) { mostrarToast(err.message, 'error'); }
  }

  function aplicar(cuenta, forzar = true) {
    const firma = JSON.stringify({ ...cuenta, minutos: 0 });
    if (forzar || firma !== st.firma) { st.cuenta = cuenta; st.firma = firma; pintarHoja(); }
  }

  function pintarHoja() {
    const m = mesaSel();
    const hoja = $('mzHoja');
    if (!m) return;
    const c = st.cuenta;
    let html = '<button type="button" class="mz-hoja-asa" data-acc="cerrar" aria-label="Cerrar"></button>';
    if (!c) {
      st.comensales = Math.min(st.comensales, 40);
      const r = st.ignorarReserva === m.id ? null : m.reserva;
      html += `<div class="mz-hoja-cab"><h2>Mesa ${m.numero}</h2><span class="cj-stat-label">${r ? 'Reservada' : 'Libre'}</span></div>
        <p class="mz-hoja-info">Hasta ${m.capacidad} personas.</p>`;
      if (r) {
        html += `<div class="cj-reserva-card" style="margin-bottom:.8rem">
            <p><strong>${e(r.nombre)}</strong> · ${r.personas} pers. · ${e(r.hora)} (${r.minutos > 0 ? 'en ' + r.minutos + ' min' : r.minutos === 0 ? 'ahora' : 'hace ' + (-r.minutos) + ' min'})</p>
            ${r.comentario ? `<p class="cj-reserva-nota">${e(r.comentario)}</p>` : ''}</div>
          <button type="button" class="cj-cobrar" data-acc="llego" style="margin-bottom:.6rem"><span class="material-symbols-outlined" aria-hidden="true">how_to_reg</span>Llegó la reserva</button>
          <button type="button" class="cj-enlace cj-sentar-otros" data-acc="otros" style="width:100%"><span class="material-symbols-outlined" aria-hidden="true">warning</span>Sentar a otras personas</button>`;
        hoja.innerHTML = html;
        return;
      }
      html += `
        <div class="cj-partes" style="margin-bottom:1rem"><span id="mzEtqCom">¿Cuántos se sientan?</span>
          <span class="cj-stepper" role="group" aria-labelledby="mzEtqCom">
            <button type="button" data-com="-1" aria-label="Uno menos"><span class="material-symbols-outlined">remove</span></button>
            <output>${st.comensales}</output>
            <button type="button" data-com="1" aria-label="Uno más"><span class="material-symbols-outlined">add</span></button>
          </span></div>
        <button type="button" class="cj-cobrar" data-acc="abrir"><span class="material-symbols-outlined" aria-hidden="true">group_add</span>Abrir mesa y tomar pedido</button>`;
    } else {
      const activos = c.items.filter(i => !i.anulado);
      html += `<div class="mz-hoja-cab"><h2>Mesa ${c.mesa_numero}</h2><span class="cj-mono">${plata(c.total)}</span></div>
        <p class="mz-hoja-info">${c.comensales} personas · ${c.minutos} min${c.pagado > 0 ? ' · ya pagaron ' + plata(c.pagado) : ''}</p>
        <div class="cj-acciones">
          <button type="button" class="cj-accion principal" data-acc="agregar"><span class="material-symbols-outlined" aria-hidden="true">add</span>Pedido</button>
          <button type="button" class="cj-accion" data-acc="${c.estado === 'pide_cuenta' ? 'reabrir' : 'pedir'}" ${activos.length ? '' : 'disabled'}>
            <span class="material-symbols-outlined" aria-hidden="true">${c.estado === 'pide_cuenta' ? 'restaurant' : 'notifications_active'}</span>${c.estado === 'pide_cuenta' ? 'Sigue comiendo' : 'Pide la cuenta'}</button>
          <button type="button" class="cj-accion" data-acc="mas"><span class="material-symbols-outlined" aria-hidden="true">group_add</span>+1 persona</button>
        </div>`;
      if (c.asientos.length > 1) {
        html += `<div style="display:flex;gap:6px;margin-bottom:.9rem">${c.asientos.map(a => `
          <div style="flex:1;border-radius:10px;padding:8px 4px;text-align:center;background:${a.pagado ? '#caecbc' : '#fafaf4'};color:#264220">
            <div style="font-family:Montserrat,sans-serif;font-weight:800;font-size:.85rem">${e(a.letra)}</div>
            <div class="cj-mono" style="font-size:.68rem;margin-top:2px">${a.pagado ? 'pagó' : plata(a.total)}</div></div>`).join('')}</div>`;
      }
      html += c.items.length ? '<ul class="cj-items">' + c.items.map(i => `
        <li class="cj-item ${i.anulado ? 'anulado' : ''}">
          <span class="cj-asiento-mini ${i.asiento ? '' : 'compartido'}">${i.asiento || '∗'}</span>
          <span class="cj-item-cant">${i.cantidad}×</span>
          <span class="cj-item-info"><span class="cj-item-nombre">${e(i.nombre)}</span>${i.nota ? `<span class="cj-item-nota">${e(i.nota)}</span>` : ''}
            <span class="cj-item-nota" style="color:#73796f;font-style:normal">${i.hora}${i.impreso ? ' · en cocina' : ' · esperando impresión'}</span></span>
          <span class="cj-item-total">${plata(i.total)}</span>
          ${!i.anulado && !i.impreso && c.pagado <= 0 ? `<button type="button" class="cj-item-quitar" data-quitar="${i.id}" aria-label="Quitar ${e(i.nombre)}"><span class="material-symbols-outlined">close</span></button>` : ''}
        </li>`).join('') + '</ul>' : '<p class="cj-vacio-chico">Sin pedidos todavía. Tocá «Pedido».</p>';
      html += `<p class="cj-nota-fiscal" style="margin-top:.8rem"><span class="material-symbols-outlined" aria-hidden="true">point_of_sale</span>
        Cobra la caja. Cuando pidan la cuenta tocá «Pide la cuenta»: la mesa late en la caja y ahí se imprime.</p>`;
    }
    hoja.innerHTML = html;
  }

  function abrirHoja() {
    $('mzHoja').classList.add('abierta');
    $('mzHoja').setAttribute('aria-hidden', 'false');
    $('mzFondo').classList.add('visible');
  }
  function cerrarHoja() {
    st.mesaId = null; st.cuenta = null; st.firma = '';
    $('mzHoja').classList.remove('abierta');
    $('mzHoja').setAttribute('aria-hidden', 'true');
    $('mzFondo').classList.remove('visible');
    if (st.datos) pintarMesas();
  }

  // "Sentar a otras personas" en una mesa reservada: muestra el selector de
  // comensales normal; el servidor igual va a frenar y pedir confirmación.
  function pintarHojaLibre() {
    st.ignorarReserva = st.mesaId;
    pintarHoja();
  }

  // Abre la mesa; si choca con una reserva pide confirmación y sugiere mesas libres.
  async function abrirMesa(body) {
    try {
      const r = await accion('abrir_mesa', body);
      mostrarToast(r.mensaje, 'exito');
      aplicar(r.cuenta);
      await refrescar();
      abrirSelector(r.cuenta, cuenta => { aplicar(cuenta); refrescar(); });
    } catch (err) {
      if (err.datos?.codigo !== 'mesa_reservada') return mostrarToast(err.message, 'error', 5000);
      const alt = (err.datos.alternativas || []).map(m => m.numero).join(', ');
      const ok = confirm(`${err.datos.mensaje}\n\n${alt ? 'Mesas libres sin reserva: ' + alt + '.\n\n' : ''}¿Sentarlos igual en esta mesa? Van a tener que liberarla antes de que llegue la reserva.`);
      if (ok) abrirMesa({ ...body, forzar: true });
    }
  }

  async function ejecutar(fn) {
    try {
      const r = await fn();
      if (r.mensaje) mostrarToast(r.mensaje, 'exito');
      if (r.cuenta) aplicar(r.cuenta);
      await refrescar();
      return r;
    } catch (err) { mostrarToast(err.message, 'error'); return null; }
  }

  $('mzMesas').addEventListener('click', ev => {
    const b = ev.target.closest('[data-mesa]');
    if (!b) return;
    st.mesaId = Number(b.dataset.mesa);
    st.firma = ''; st.cuenta = null; st.comensales = 2;
    pintarMesas();
    cargarCuenta(true).then(abrirHoja);
  });
  $('mzFondo').addEventListener('click', cerrarHoja);
  document.addEventListener('keydown', ev => { if (ev.key === 'Escape' && st.mesaId && !hayModalAbierto()) cerrarHoja(); });

  document.querySelector('.mz-filtro').addEventListener('click', ev => {
    const b = ev.target.closest('[data-filtro]');
    if (!b) return;
    st.filtro = b.dataset.filtro;
    document.querySelectorAll('.mz-filtro .cj-tab').forEach(x => {
      const on = x === b;
      x.classList.toggle('activo', on);
      x.setAttribute('aria-pressed', String(on));
    });
    pintarMesas();
  });

  $('mzHoja').addEventListener('click', async ev => {
    const b = ev.target.closest('button');
    if (!b || b.disabled) return;
    const c = st.cuenta;
    if (b.dataset.com) {
      st.comensales = Math.max(1, Math.min(40, st.comensales + Number(b.dataset.com)));
      return pintarHoja();
    }
    if (b.dataset.quitar) return ejecutar(() => accion('anular_item', { item_id: Number(b.dataset.quitar) }));
    switch (b.dataset.acc) {
      case 'cerrar': return cerrarHoja();
      case 'abrir': return abrirMesa({ mesa_id: st.mesaId, comensales: st.comensales });
      case 'llego': return abrirMesa({ mesa_id: st.mesaId, reserva_id: mesaSel().reserva.id });
      case 'otros': return pintarHojaLibre();
      case 'agregar': return abrirSelector(c, cuenta => { aplicar(cuenta); refrescar(); });
      case 'pedir': return ejecutar(() => accion('pedir_cuenta', { cuenta_id: c.id }));
      case 'reabrir': return ejecutar(() => accion('reabrir', { cuenta_id: c.id }));
      case 'mas': return ejecutar(() => accion('cambiar_comensales', { cuenta_id: c.id, comensales: c.comensales + 1 }));
    }
  });

  async function ciclo() {
    if (!document.hidden && !hayModalAbierto()) await refrescar();
    setTimeout(ciclo, 6000);
  }
  document.addEventListener('visibilitychange', () => { if (!document.hidden) refrescar(); });
  refrescar().then(() => setTimeout(ciclo, 6000));
})();
