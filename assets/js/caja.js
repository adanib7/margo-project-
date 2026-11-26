// Caja en el salón: plano en vivo, cuenta de cada mesa, división y cobro.
(() => {
  const { api, accion, plata, imprimir, abrirModal, cerrarModal, hayModalAbierto, abrirSelector } = Caja;
  const e = escaparHtml;
  const $ = id => document.getElementById(id);

  const MEDIOS = [
    ['efectivo', 'Efectivo', 'payments'], ['debito', 'Débito', 'credit_card'],
    ['credito', 'Crédito', 'credit_score'], ['transferencia', 'Transf. / QR', 'qr_code_2'],
  ];

  const st = {
    datos: null,
    sel: null,            // { mesaId } o { cuentaId } (mostrador)
    cuenta: null,
    firma: '',
    cobrando: false,
    cobro: nuevoCobro(),
  };

  function nuevoCobro(anterior = {}) {
    return { modo: 'junta', asiento: null, partes: 2, propinaPct: 0, medio: anterior.medio || 'efectivo', cfe: 'eticket', rut: '', razon: '', email: '' };
  }

  /* ════════════ Datos ════════════ */
  async function refrescar() {
    try {
      st.datos = await api('/api/caja_estado.php');
    } catch (err) {
      if (!st.datos) $('cjPlano').innerHTML = `<div class="cj-plano-vacio"><span class="material-symbols-outlined" aria-hidden="true">error</span>${e(err.message)}</div>`;
      return;
    }
    pintarCabecera();
    pintarPlano();
    pintarMostrador();
    pintarReservas();
    await cargarCuentaSeleccionada();
  }

  function cuentaIdSeleccionada() {
    if (!st.sel) return null;
    if (st.sel.cuentaId) return st.sel.cuentaId;
    const mesa = st.datos?.mesas.find(m => m.id === st.sel.mesaId);
    return mesa?.cuenta?.id || null;
  }

  async function cargarCuentaSeleccionada(forzar = false) {
    const id = cuentaIdSeleccionada();
    if (!id) {
      if (st.cuenta || forzar || st.firma !== 'vacia') { st.cuenta = null; st.firma = 'vacia'; pintarCuenta(); }
      return;
    }
    try {
      const r = await api('/api/caja_cuenta.php?id=' + id);
      aplicarCuenta(r.cuenta, forzar);
    } catch (err) {
      st.cuenta = null; st.firma = ''; pintarCuenta();
    }
  }

  function aplicarCuenta(cuenta, forzar = true) {
    // Mostrador: si la cuenta se cerró, deja de estar seleccionada.
    if (st.sel?.cuentaId && !['abierta', 'pide_cuenta'].includes(cuenta.estado)) { st.sel = null; st.cuenta = null; st.firma = ''; pintarCuenta(); return; }
    const firma = JSON.stringify({ ...cuenta, minutos: 0 });
    if (st.cuenta?.id !== cuenta.id) st.cobro = nuevoCobro(st.cobro);
    const cambio = forzar || firma !== st.firma;
    st.cuenta = cuenta; st.firma = firma;
    // No repintar mientras se escribe un RUT o un correo: se perdería el foco.
    const escribiendo = document.activeElement?.closest?.('#cjCuenta input');
    if (cambio && !escribiendo) pintarCuenta();
  }

  /* ════════════ Cabecera, indicadores y avisos ════════════ */
  function pintarCabecera() {
    const d = st.datos;
    const abiertas = d.mesas.filter(m => m.cuenta).length + d.mostrador.length;
    const piden = d.mesas.filter(m => m.cuenta?.estado === 'pide_cuenta').length + d.mostrador.filter(c => c.estado === 'pide_cuenta').length;
    $('stAbiertas').textContent = abiertas;
    $('stPiden').textContent = piden;
    $('stCobrado').textContent = d.resumen ? plata(d.resumen.total) : '—';
    $('stCfe').textContent = d.resumen ? d.resumen.comprobantes : '—';

    const chip = $('cjChipTurno');
    chip.className = 'cj-chip-turno ' + (d.turno ? 'abierta' : 'cerrada');
    chip.textContent = d.turno ? `Caja abierta · desde ${d.turno.abierto_en}` : 'Caja cerrada';
    $('cjAvisoTurno').hidden = !!d.turno;

    $('cjAvisoComandas').hidden = !d.comandas_pendientes;
    $('cjComandasTexto').textContent = d.comandas_pendientes === 1 ? '1 pedido nuevo para cocina o barra' : `${d.comandas_pendientes} pedidos nuevos para cocina o barra`;
  }

  /* ════════════ Plano ════════════ */
  function estadoMesa(m) {
    if (!m.cuenta) return m.reserva ? 'reservada' : 'libre';
    return m.cuenta.estado === 'pide_cuenta' ? 'cuenta' : 'comiendo';
  }
  const cuandoLlega = r => r.minutos > 0 ? `en ${r.minutos} min` : r.minutos === 0 ? 'ahora' : `hace ${-r.minutos} min`;

  function pintarPlano() {
    const { mesas, plano } = st.datos;
    const cont = $('cjPlano');
    if (!mesas.length) {
      cont.innerHTML = `<div class="cj-plano-vacio"><span class="material-symbols-outlined" aria-hidden="true">table_restaurant</span>
        <p>Todavía no hay mesas en el plano.</p><a href="${BASE}/plano">Dibujar el plano de mesas</a>
        <p>Mientras tanto podés vender desde «Mostrador».</p></div>`;
      return;
    }
    const pct = (v, total) => (v / total * 100).toFixed(3) + '%';
    cont.innerHTML = mesas.map(m => {
      const est = estadoMesa(m);
      const sel = st.sel?.mesaId === m.id;
      const r = m.reserva;
      const sub = { libre: 'libre', reservada: r && r.hora, cuenta: 'cuenta', comiendo: m.cuenta && m.cuenta.minutos + ' min' }[est];
      const etiqueta = `Mesa ${m.numero}, ${{ libre: 'libre', reservada: 'reservada', cuenta: 'pide la cuenta', comiendo: 'comiendo' }[est]}`
        + (m.cuenta ? ', ' + plata(m.cuenta.total) : '') + (r ? `, reserva a las ${r.hora} para ${r.nombre}` : '');
      const alto = Math.max(m.ancho, m.alto) / 2;
      const pos = `left:${pct(m.pos_x, plano.ancho)};top:${pct(Math.max(14, m.pos_y - alto - 4), plano.alto)}`;
      let tag = '';
      // Ocupada y con reserva encima: alerta para apurar el recambio.
      if (m.cuenta && r) tag = `<span class="cj-mesa-tag alerta" style="${pos}">Reserva ${e(r.hora)} · ${cuandoLlega(r)}</span>`;
      else if (r) tag = `<span class="cj-mesa-tag reserva" style="${pos}">${e(r.nombre.split(' ')[0])} · ${r.personas}p</span>`;
      else if (m.cuenta && m.cuenta.total > 0) tag = `<span class="cj-mesa-tag cj-mono" style="${pos}">${plata(m.cuenta.total)} · ${m.cuenta.minutos}'</span>`;
      return `<button type="button" class="cj-mesa ${m.forma} ${est} ${sel ? 'seleccionada' : ''}" data-mesa="${m.id}" aria-pressed="${sel}" aria-label="${e(etiqueta)}"
          style="--giro:${Number(m.rotacion) || 0}deg;left:${pct(m.pos_x - m.ancho / 2, plano.ancho)};top:${pct(m.pos_y - m.alto / 2, plano.alto)};width:${pct(m.ancho, plano.ancho)};height:${pct(m.alto, plano.alto)}">
          <span class="cj-mesa-txt"><span class="cj-mesa-num">${m.numero}</span><span class="cj-mesa-sub">${e(sub)}</span></span>
        </button>${tag}`;
    }).join('');
  }

  function pintarMostrador() {
    const lista = st.datos.mostrador;
    $('cjMostrador').innerHTML = lista.length ? lista.map(c => {
      const sel = st.sel?.cuentaId === c.id;
      return `<button type="button" class="cj-mostrador-chip ${c.estado === 'pide_cuenta' ? 'cuenta' : ''} ${sel ? 'seleccionada' : ''}" data-cuenta="${c.id}" aria-pressed="${sel}">
        <span class="material-symbols-outlined" aria-hidden="true">shopping_bag</span>Venta #${c.id} <span class="cj-mono">${plata(c.total)}</span></button>`;
    }).join('') : '<span class="cj-vacio-chico">Sin ventas de mostrador abiertas.</span>';
  }

  /* ════════════ Panel de la cuenta ════════════ */
  function montoBase() {
    const c = st.cuenta, cb = st.cobro;
    if (!c || c.saldo <= 0) return 0;
    if (cb.modo === 'asiento') {
      const pendientes = c.asientos.filter(a => !a.pagado);
      const a = pendientes.find(x => x.letra === cb.asiento);
      if (!a) return 0;
      return pendientes.length === 1 ? c.saldo : Math.min(a.total, c.saldo);
    }
    if (cb.modo === 'iguales') return Math.round(c.saldo / Math.max(1, cb.partes) * 100) / 100;
    return c.saldo;
  }

  function pintarCuenta() {
    const panel = $('cjCuenta');
    const c = st.cuenta;
    const d = st.datos;
    if (!st.sel || !d) {
      panel.innerHTML = cabecera('Cuenta', 'Elegí una mesa', '') + `<div class="cj-cuenta-cuerpo"><div class="cj-vacio">
        <span class="material-symbols-outlined" aria-hidden="true">touch_app</span><p>Tocá una mesa del plano para ver su cuenta.</p></div></div><div class="cj-zig" aria-hidden="true"></div>`;
      return;
    }
    if (!c) {
      const mesa = d.mesas.find(m => m.id === st.sel.mesaId);
      if (!mesa) { st.sel = null; return pintarCuenta(); }
      const r = mesa.reserva;
      const cuerpo = r ? `
        <div class="cj-reserva-card">
          <div class="cj-reserva-card-cab"><span class="material-symbols-outlined" aria-hidden="true">event_available</span>
            <div><strong>Reservada a las ${e(r.hora)}</strong><span>${e(cuandoLlega(r))}</span></div></div>
          <p><strong>${e(r.nombre)}</strong> · ${r.personas} ${r.personas === 1 ? 'persona' : 'personas'}${r.telefono ? ' · ' + e(r.telefono) : ''}</p>
          ${r.comentario ? `<p class="cj-reserva-nota">${e(r.comentario)}</p>` : ''}
        </div>
        <button type="button" class="cj-cobrar" data-acc="llego" data-reserva="${r.id}"><span class="material-symbols-outlined" aria-hidden="true">how_to_reg</span>Llegó ${e(r.nombre.split(' ')[0])}: abrir la mesa</button>
        <button type="button" class="cj-enlace cj-sentar-otros" data-acc="abrir"><span class="material-symbols-outlined" aria-hidden="true">warning</span>Sentar a otras personas</button>`
        : `<div class="cj-vacio"><span class="material-symbols-outlined" aria-hidden="true">table_restaurant</span><p>Mesa libre.</p>
        <button type="button" class="cj-cobrar" data-acc="abrir"><span class="material-symbols-outlined" aria-hidden="true">group_add</span>Abrir mesa</button></div>`;
      panel.innerHTML = cabecera(r ? 'Reservada' : 'Cuenta', 'Mesa ' + mesa.numero, `Hasta ${mesa.capacidad} personas`)
        + `<div class="cj-cuenta-cuerpo">${cuerpo}</div><div class="cj-zig" aria-hidden="true"></div>`;
      return;
    }

    const cb = st.cobro;
    const titulo = c.mesa_numero ? 'Mesa ' + c.mesa_numero : 'Venta #' + c.id;
    const meta = `${c.comensales} ${c.comensales === 1 ? 'persona' : 'personas'} · ${c.minutos} min<br>${c.mozo ? e(c.mozo) : 'Sin mozo asignado'}`
      + (c.reserva ? `<br>Reserva de ${e(c.reserva.nombre)}` : '');
    const activos = c.items.filter(i => !i.anulado);
    const sinPagos = c.pagado <= 0;

    let html = cabecera(c.estado === 'pide_cuenta' ? 'Pide la cuenta' : 'Cuenta', titulo, meta) + '<div class="cj-cuenta-cuerpo">';
    html += `<div class="cj-acciones">
      <button type="button" class="cj-accion principal" data-acc="agregar"><span class="material-symbols-outlined" aria-hidden="true">add</span>Agregar</button>
      <button type="button" class="cj-accion" data-acc="precuenta" ${activos.length ? '' : 'disabled'}><span class="material-symbols-outlined" aria-hidden="true">receipt</span>Pre-cuenta</button>
      <button type="button" class="cj-accion" data-acc="${c.estado === 'pide_cuenta' ? 'reabrir' : 'pedir'}"><span class="material-symbols-outlined" aria-hidden="true">${c.estado === 'pide_cuenta' ? 'restaurant' : 'notifications_active'}</span>${c.estado === 'pide_cuenta' ? 'Sigue comiendo' : 'Pide cuenta'}</button>
      <button type="button" class="cj-accion" data-acc="ajustar"><span class="material-symbols-outlined" aria-hidden="true">tune</span>Ajustar</button>
    </div>`;

    if (!c.items.length) {
      html += `<div class="cj-vacio"><span class="material-symbols-outlined" aria-hidden="true">restaurant_menu</span><p>Todavía no hay pedidos. Tocá «Agregar» o esperá los del mozo.</p></div>`;
    } else {
      html += '<ul class="cj-items">' + c.items.map(i => `
        <li class="cj-item ${i.anulado ? 'anulado' : ''}">
          <span class="cj-asiento-mini ${i.asiento ? '' : 'compartido'}" title="${i.asiento ? 'Asiento ' + i.asiento : 'Compartido'}">${i.asiento || '∗'}</span>
          <span class="cj-item-cant">${i.cantidad}×</span>
          <span class="cj-item-info"><span class="cj-item-nombre">${e(i.nombre)}</span>
            ${i.nota ? `<span class="cj-item-nota">${e(i.nota)}</span>` : ''}
            ${i.anulado ? `<span class="cj-item-nota">Anulado: ${e(i.motivo_anulacion || '')}</span>` : ''}</span>
          <span class="cj-item-total">${plata(i.total)}</span>
          ${!i.anulado && sinPagos ? `<button type="button" class="cj-item-quitar" data-quitar="${i.id}" data-impreso="${i.impreso ? 1 : 0}" aria-label="Anular ${e(i.nombre)}"><span class="material-symbols-outlined">close</span></button>` : ''}
        </li>`).join('') + '</ul>';
    }

    if (c.saldo > 0) html += pintarCobro(c, cb);

    if (c.pagos.length) {
      html += '<div><p class="cj-subtitulo">Pagos registrados</p><ul class="cj-pagos">' + c.pagos.map(p => `
        <li class="cj-pago"><span class="material-symbols-outlined" aria-hidden="true">check_circle</span>
          <span>${p.hora} · ${e(medioLabel(p.medio))}${p.asiento ? ' · asiento ' + e(p.asiento) : ''} · <span class="cj-mono">${plata(p.monto)}</span>${p.propina ? ' + ' + plata(p.propina) + ' propina' : ''}</span>
          ${p.comprobante_id ? `<button type="button" data-ticket="${p.comprobante_id}" aria-label="Reimprimir ${e(p.comprobante || 'comprobante')}" title="Reimprimir ${e(p.comprobante || '')}"><span class="material-symbols-outlined">print</span></button>` : ''}
        </li>`).join('') + `</ul><div class="cj-suma" style="margin-top:.5rem"><span>Saldo pendiente</span><span class="cj-mono">${plata(c.saldo)}</span></div></div>`;
    }

    html += `<p class="cj-nota-fiscal"><span class="material-symbols-outlined" aria-hidden="true">info</span>Comprobantes con numeración interna. El envío a DGI se activa cuando se conecte el proveedor de facturación electrónica.</p>`;
    html += '</div><div class="cj-zig" aria-hidden="true"></div>';
    panel.innerHTML = html;
  }

  function pintarCobro(c, cb) {
    const asientosPend = c.asientos.filter(a => !a.pagado);
    if (cb.modo === 'asiento' && !asientosPend.length) cb.modo = 'junta';
    if (cb.modo === 'asiento' && !asientosPend.some(a => a.letra === cb.asiento)) cb.asiento = asientosPend[0]?.letra || null;

    const base = montoBase();
    const propina = Math.round(base * cb.propinaPct / 100);
    const ivaIncluido = c.total > 0 ? (c.desglose.iva_basica + c.desglose.iva_minima) * base / c.total : 0;
    const sugerida = st.datos.propina_sugerida;
    const opcionesPropina = [...new Set([0, 5, 10, sugerida])].filter(n => n >= 0).sort((a, b) => a - b);
    const sinTurno = !st.datos.turno;
    const btn = (attr, val, actual, label) => `<button type="button" ${attr}="${val}" aria-pressed="${String(val) === String(actual)}">${label}</button>`;

    let html = '<div><p class="cj-subtitulo" id="etqDividir">Cómo cobrar</p><div class="cj-segmentado" role="group" aria-labelledby="etqDividir">'
      + btn('data-modo', 'junta', cb.modo, c.pagado > 0 ? 'Todo el saldo' : 'Toda junta')
      + (asientosPend.length ? btn('data-modo', 'asiento', cb.modo, 'Por asiento') : '')
      + btn('data-modo', 'iguales', cb.modo, 'Partes iguales') + '</div></div>';

    if (cb.modo === 'asiento') {
      html += '<div class="cj-asientos">' + c.asientos.map(a => {
        const on = a.letra === cb.asiento && !a.pagado;
        const nombres = c.items.filter(i => a.items.includes(i.id)).map(i => (i.cantidad > 1 ? i.cantidad + '× ' : '') + i.nombre).join(', ');
        return `<div class="cj-asiento ${on ? 'activo' : ''} ${a.pagado ? 'pagado' : ''}">
          <span class="cj-asiento-mini">${e(a.letra)}</span>
          <span class="cj-asiento-detalle">${e(nombres)}${a.compartido ? ' + ' + plata(a.compartido) + ' compartido' : ''}</span>
          <span class="cj-mono">${plata(a.total)}</span>
          <button type="button" class="cj-asiento-btn" data-asiento="${e(a.letra)}" aria-pressed="${on}" ${a.pagado ? 'disabled' : ''}>${a.pagado ? 'Pagó' : on ? 'Cobrando' : 'Cobrar'}</button>
        </div>`;
      }).join('') + '</div>';
    }
    if (cb.modo === 'iguales') {
      html += `<div class="cj-partes"><span id="etqPartes">Dividir el saldo en</span>
        <span class="cj-stepper" role="group" aria-labelledby="etqPartes">
          <button type="button" data-partes="-1" aria-label="Una parte menos" ${cb.partes <= 1 ? 'disabled' : ''}><span class="material-symbols-outlined">remove</span></button>
          <output>${cb.partes}</output>
          <button type="button" data-partes="1" aria-label="Una parte más" ${cb.partes >= 30 ? 'disabled' : ''}><span class="material-symbols-outlined">add</span></button>
        </span></div>
        <p class="cj-vacio-chico" style="text-align:left;padding:0">${cb.partes} ${cb.partes === 1 ? 'parte' : 'partes'} de ${plata(base)} · se cobra de a una.</p>`;
    }

    html += `<div class="cj-sumas">
      <div class="cj-suma"><span>A cobrar ahora</span><span class="cj-mono">${plata(base)}</span></div>
      <div class="cj-suma"><span id="etqPropina">Propina</span><span class="cj-chips" role="group" aria-labelledby="etqPropina">
        ${opcionesPropina.map(p => `<button type="button" class="cj-chip" data-propina="${p}" aria-pressed="${cb.propinaPct === p}">${p ? p + '%' : 'No'}</button>`).join('')}
      </span></div>
      ${propina ? `<div class="cj-suma chica"><span>Propina (aparte del comprobante)</span><span class="cj-mono">${plata(propina)}</span></div>` : ''}
      <div class="cj-suma chica"><span>IVA incluido</span><span class="cj-mono">${plata(ivaIncluido)}</span></div>
    </div>
    <div class="cj-total"><span>Total</span><span class="cj-mono">${plata(base + propina)}</span></div>`;

    html += `<div><p class="cj-subtitulo" id="etqMedio">Medio de pago</p><div class="cj-medios" role="group" aria-labelledby="etqMedio">
      ${MEDIOS.map(([k, label, icono]) => `<button type="button" class="cj-medio" data-medio="${k}" aria-pressed="${cb.medio === k}"><span class="material-symbols-outlined" aria-hidden="true">${icono}</span>${label}</button>`).join('')}
    </div></div>`;

    html += `<div><p class="cj-subtitulo" id="etqCfe">Comprobante</p><div class="cj-cfe" role="group" aria-labelledby="etqCfe">
      <button type="button" data-cfe="eticket" aria-pressed="${cb.cfe === 'eticket'}"><strong>e-Ticket</strong><small>Consumidor final</small></button>
      <button type="button" data-cfe="efactura" aria-pressed="${cb.cfe === 'efactura'}"><strong>e-Factura</strong><small>Empresa con RUT</small></button>
    </div><div class="cj-campos">
      ${cb.cfe === 'efactura' ? `
        <label class="cj-campo">RUT<input type="text" inputmode="numeric" maxlength="12" class="cj-mono" data-campo="rut" value="${e(cb.rut)}" placeholder="12 dígitos" autocomplete="off"></label>
        <label class="cj-campo">Razón social<input type="text" maxlength="150" data-campo="razon" value="${e(cb.razon)}" placeholder="Empresa S.A." autocomplete="organization"></label>` : ''}
      <label class="cj-campo completo">Enviar comprobante a <span style="text-transform:none;font-weight:400;letter-spacing:0">(opcional)</span>
        <input type="email" maxlength="255" data-campo="email" value="${e(cb.email)}" placeholder="cliente@correo.com" autocomplete="email"></label>
    </div></div>`;

    html += `<button type="button" class="cj-cobrar" data-acc="cobrar" ${sinTurno || base <= 0 || st.cobrando ? 'disabled' : ''}>
      <span class="material-symbols-outlined" aria-hidden="true">${sinTurno ? 'lock' : 'point_of_sale'}</span>
      ${sinTurno ? 'Abrí la caja para cobrar' : st.cobrando ? 'Cobrando…' : 'Cobrar ' + plata(base + propina) + (cb.modo === 'asiento' && cb.asiento ? ' · asiento ' + e(cb.asiento) : cb.modo === 'iguales' ? ' · 1 de ' + cb.partes : '')}
    </button>`;
    return html;
  }

  const cabecera = (eyebrow, titulo, meta) => `<div class="cj-cuenta-cab"><div><small>${e(eyebrow)}</small><h2>${e(titulo)}</h2></div><div class="cj-cuenta-meta">${meta}</div></div>`;
  const medioLabel = k => (MEDIOS.find(m => m[0] === k) || [k, k])[1];

  /* ════════════ Acciones del panel ════════════ */
  $('cjCuenta').addEventListener('click', async ev => {
    const b = ev.target.closest('button');
    if (!b || b.disabled) return;
    const c = st.cuenta, cb = st.cobro;
    if (b.dataset.modo) { cb.modo = b.dataset.modo; return pintarCuenta(); }
    if (b.dataset.asiento) { cb.asiento = b.dataset.asiento; return pintarCuenta(); }
    if (b.dataset.partes) { cb.partes = Math.max(1, Math.min(30, cb.partes + Number(b.dataset.partes))); return pintarCuenta(); }
    if (b.dataset.propina !== undefined) { cb.propinaPct = Number(b.dataset.propina); return pintarCuenta(); }
    if (b.dataset.medio) { cb.medio = b.dataset.medio; return pintarCuenta(); }
    if (b.dataset.cfe) { cb.cfe = b.dataset.cfe; return pintarCuenta(); }
    if (b.dataset.ticket) return imprimir('tipo=comprobante&id=' + b.dataset.ticket);
    if (b.dataset.quitar) {
      if (b.dataset.impreso === '1') return pedirMotivo('item', Number(b.dataset.quitar));
      return ejecutar(() => accion('anular_item', { item_id: Number(b.dataset.quitar) }));
    }
    switch (b.dataset.acc) {
      case 'abrir': return abrirModalMesa();
      case 'llego': return llegoReserva(Number(b.dataset.reserva));
      case 'agregar': return abrirSelector(c, cuenta => { aplicarCuenta(cuenta); refrescar(); });
      case 'precuenta': return imprimir('tipo=precuenta&cuenta=' + c.id);
      case 'pedir': return ejecutar(() => accion('pedir_cuenta', { cuenta_id: c.id }));
      case 'reabrir': return ejecutar(() => accion('reabrir', { cuenta_id: c.id }));
      case 'ajustar': return abrirAjustes();
      case 'cobrar': return cobrar();
    }
  });

  $('cjCuenta').addEventListener('input', ev => {
    const campo = ev.target.dataset.campo;
    if (!campo) return;
    if (campo === 'rut') ev.target.value = ev.target.value.replace(/\D/g, '');
    st.cobro[campo] = ev.target.value;
    ev.target.removeAttribute('aria-invalid');
  });

  async function ejecutar(fn, mensajeOk = true) {
    try {
      const r = await fn();
      if (mensajeOk && r.mensaje) mostrarToast(r.mensaje, 'exito');
      if (r.cuenta) aplicarCuenta(r.cuenta);
      await refrescar();
      return r;
    } catch (err) {
      mostrarToast(err.message, 'error');
      return null;
    }
  }

  async function cobrar() {
    const c = st.cuenta, cb = st.cobro;
    const base = montoBase();
    if (base <= 0 || st.cobrando) return;
    const propina = Math.round(base * cb.propinaPct / 100);
    st.cobrando = true; pintarCuenta();
    try {
      const r = await accion('cobrar', {
        cuenta_id: c.id, monto: base, propina, medio: cb.medio,
        asiento: cb.modo === 'asiento' ? cb.asiento : null,
        cfe: { tipo: cb.cfe, rut: cb.rut, razon_social: cb.razon, email: cb.email },
      });
      $('cobradoSub').textContent = r.cerrada ? 'La mesa quedó libre en el plano.' : 'Queda saldo pendiente en la cuenta.';
      $('cobradoSello').innerHTML = (r.cerrada ? 'Cobrada' : 'Pago parcial') + `<br><span class="cj-mono" style="font-size:.7rem;letter-spacing:0">${new Date().toLocaleTimeString('es-UY', { hour: '2-digit', minute: '2-digit' })}</span>`;
      $('cobradoDetalle').innerHTML = `<strong>${e(r.comprobante.nombre)}</strong><br>${plata(r.comprobante.total)}${r.comprobante.propina ? ' + ' + plata(r.comprobante.propina) + ' de propina' : ''} · ${e(medioLabel(cb.medio))}`;
      $('btnImprimirComprobante').dataset.id = r.comprobante.id;
      if (cb.modo === 'iguales') cb.partes = Math.max(1, cb.partes - 1);
      const modo = cb.modo, partes = cb.partes;
      st.cobro = nuevoCobro(cb);
      if (!r.cerrada && modo !== 'junta') { st.cobro.modo = modo; st.cobro.partes = partes; }
      st.cobrando = false;
      if (r.cerrada && st.sel?.cuentaId) st.sel = null;
      abrirModal('modalCobrado');
      if (r.cuenta && !r.cerrada) aplicarCuenta(r.cuenta);
      await refrescar();
    } catch (err) {
      st.cobrando = false;
      mostrarToast(err.message, 'error', 5000);
      pintarCuenta();
      const campo = err.datos?.campo;
      if (campo) {
        const input = document.querySelector(`#cjCuenta [data-campo="${campo === 'razon_social' ? 'razon' : campo}"]`);
        input?.setAttribute('aria-invalid', 'true'); input?.focus();
      }
    }
  }
  $('btnImprimirComprobante').addEventListener('click', ev => {
    imprimir('tipo=comprobante&id=' + ev.currentTarget.dataset.id);
    cerrarModal('modalCobrado');
  });

  /* ════════════ Selección en el plano y mostrador ════════════ */
  $('cjPlano').addEventListener('click', ev => {
    const b = ev.target.closest('[data-mesa]');
    if (!b) return;
    st.sel = { mesaId: Number(b.dataset.mesa) };
    st.firma = '';
    pintarPlano(); pintarMostrador();
    cargarCuentaSeleccionada(true);
    if (window.matchMedia('(max-width: 900px)').matches) $('cjCuenta').scrollIntoView({ behavior: 'smooth', block: 'start' });
  });
  $('cjMostrador').addEventListener('click', ev => {
    const b = ev.target.closest('[data-cuenta]');
    if (!b) return;
    st.sel = { cuentaId: Number(b.dataset.cuenta) };
    st.firma = '';
    pintarPlano(); pintarMostrador();
    cargarCuentaSeleccionada(true);
  });
  $('btnMostrador').addEventListener('click', async () => {
    const r = await ejecutar(() => accion('abrir_mesa', { comensales: 1 }), false);
    if (!r) return;
    st.sel = { cuentaId: r.cuenta.id };
    aplicarCuenta(r.cuenta);
    pintarMostrador();
    abrirSelector(r.cuenta, cuenta => { aplicarCuenta(cuenta); refrescar(); });
  });

  /* ════════════ Abrir mesa ════════════ */
  let comensalesAbrir = 2;
  function abrirModalMesa() {
    const mesa = st.datos.mesas.find(m => m.id === st.sel?.mesaId);
    if (!mesa) return;
    comensalesAbrir = Math.min(2, mesa.capacidad) || 2;
    $('abrirComensales').textContent = comensalesAbrir;
    $('abrirTitulo').textContent = 'Abrir mesa ' + mesa.numero;
    const mozos = st.datos.mozos || [];
    $('abrirMozoGrupo').hidden = !mozos.length;
    $('abrirMozo').innerHTML = '<option value="">Sin asignar</option>' + mozos.map(m => `<option value="${m.id}">${e(m.nombre)}</option>`).join('');
    abrirModal('modalAbrir');
  }
  $('modalAbrir').addEventListener('click', ev => {
    const b = ev.target.closest('[data-paso]');
    if (!b) return;
    comensalesAbrir = Math.max(1, Math.min(40, comensalesAbrir + Number(b.dataset.paso)));
    $('abrirComensales').textContent = comensalesAbrir;
  });
  $('formAbrir').addEventListener('submit', async ev => {
    ev.preventDefault();
    cerrarModal('modalAbrir');
    await abrirMesa({ mesa_id: st.sel.mesaId, comensales: comensalesAbrir, mozo_id: Number($('abrirMozo').value) || null });
  });

  /* ════════════ Reservas: abrir con reserva y evitar pisarlas ════════════ */
  // Abre una mesa. Si choca con una reserva, el servidor responde 409 y se
  // muestran las salidas posibles en vez de abrirla igual.
  async function abrirMesa(body) {
    try {
      const r = await accion('abrir_mesa', body);
      mostrarToast(r.mensaje, 'exito');
      st.sel = body.mesa_id ? { mesaId: body.mesa_id } : { cuentaId: r.cuenta.id };
      st.firma = '';
      aplicarCuenta(r.cuenta);
      await refrescar();
      abrirSelector(r.cuenta, cuenta => { aplicarCuenta(cuenta); refrescar(); });
      return true;
    } catch (err) {
      if (err.datos?.codigo === 'mesa_reservada') mostrarReservada(err.datos, body);
      else mostrarToast(err.message, 'error', 5000);
      return false;
    }
  }

  let pendiente = null;
  const botonMesa = (m, attr) => `<button type="button" class="cj-mesa-opcion" ${attr}="${m.id}">
      <strong>Mesa ${m.numero}</strong><span>hasta ${m.capacidad}</span></button>`;

  function mostrarReservada(datos, body) {
    pendiente = body;
    const r = datos.reserva;
    $('resTitulo').textContent = 'Esta mesa está reservada';
    $('resSub').textContent = datos.mensaje;
    $('resCuerpo').innerHTML = `
      <div class="cj-reserva-card">
        <p><strong>${e(r.nombre)}</strong> · ${r.personas} ${r.personas === 1 ? 'persona' : 'personas'} · ${e(r.hora)} (${e(cuandoLlega(r))})${r.telefono ? ' · ' + e(r.telefono) : ''}</p>
        ${r.comentario ? `<p class="cj-reserva-nota">${e(r.comentario)}</p>` : ''}
        <button type="button" class="cj-enlace" data-llego-reserva="${r.id}"><span class="material-symbols-outlined" aria-hidden="true">how_to_reg</span>Son ellos: llegó la reserva</button>
      </div>
      ${datos.alternativas?.length ? `<p class="cj-subtitulo" style="margin-top:1rem">Mesas libres sin reserva</p>
        <div class="cj-mesas-opciones">${datos.alternativas.map(m => botonMesa(m, 'data-alt')).join('')}</div>`
        : '<p class="cj-vacio-chico">No hay otras mesas libres sin reserva para esa cantidad de personas.</p>'}
      <div class="modal-acciones" style="margin-top:1.25rem">
        <button type="button" class="boton-secundario" data-cerrar="modalReservada">Cancelar</button>
        <button type="button" class="boton-peligro" data-forzar="1"><span class="material-symbols-outlined">warning</span>Sentar igual</button>
      </div>`;
    abrirModal('modalReservada');
  }

  // Llegó una reserva: a su mesa si está libre; si no tiene mesa o está ocupada, se elige otra.
  function llegoReserva(id) {
    const r = st.datos.reservas.find(x => x.id === id);
    if (!r) return mostrarToast('Esa reserva ya no está pendiente.', 'error');
    const suMesa = r.mesa_id && st.datos.mesas.find(m => m.id === r.mesa_id);
    if (suMesa && !suMesa.cuenta) return abrirMesa({ mesa_id: r.mesa_id, reserva_id: r.id });
    pendiente = { reserva_id: r.id };
    const libres = st.datos.mesas.filter(m => !m.cuenta && (!m.reserva || m.reserva.id === r.id))
      .sort((a, b) => (a.capacidad < r.personas) - (b.capacidad < r.personas) || a.capacidad - b.capacidad || a.numero - b.numero);
    $('resTitulo').textContent = `Llegó ${r.nombre}`;
    $('resSub').textContent = suMesa ? `Su mesa (${suMesa.numero}) todavía está ocupada. Elegí dónde sentarlos.` : `Reserva de las ${r.hora} sin mesa asignada. Elegí dónde sentarlos.`;
    $('resCuerpo').innerHTML = libres.length
      ? `<p class="cj-subtitulo">${r.personas} ${r.personas === 1 ? 'persona' : 'personas'} · mesas libres</p>
         <div class="cj-mesas-opciones">${libres.map(m => botonMesa(m, 'data-alt').replace('class="cj-mesa-opcion"', `class="cj-mesa-opcion${m.capacidad < r.personas ? ' chica' : ''}"`)).join('')}</div>`
      : '<p class="cj-vacio-chico">No hay mesas libres ahora. Avisales la espera o usá «Mostrador».</p>';
    abrirModal('modalReservada');
  }

  $('modalReservada').addEventListener('click', async ev => {
    const b = ev.target.closest('button');
    if (!b || !pendiente) return;
    const body = pendiente;
    if (b.dataset.alt) { cerrarModal('modalReservada'); return abrirMesa({ ...body, mesa_id: Number(b.dataset.alt), forzar: false }); }
    if (b.dataset.forzar) { cerrarModal('modalReservada'); return abrirMesa({ ...body, forzar: true }); }
    if (b.dataset.llegoReserva) { cerrarModal('modalReservada'); return abrirMesa({ mesa_id: body.mesa_id, reserva_id: Number(b.dataset.llegoReserva) }); }
  });

  function pintarReservas() {
    const lista = st.datos.reservas || [];
    $('cjReservas').innerHTML = lista.length ? lista.map(r => {
      const estado = r.vencida ? ['No llegó', 'vencida'] : r.minutos < 0 ? ['Llega tarde', 'tarde'] : r.minutos <= 30 ? ['En ' + r.minutos + ' min', 'pronto'] : [r.hora, ''];
      return `<li class="cj-reserva ${estado[1]}">
        <span class="cj-reserva-hora cj-mono">${e(r.hora)}</span>
        <span class="cj-reserva-info"><strong>${e(r.nombre)}</strong>
          <span>${r.personas} pers. · ${r.mesa_numero ? 'mesa ' + r.mesa_numero : 'sin mesa'}${r.comentario ? ' · ' + e(r.comentario) : ''}</span></span>
        <span class="cj-reserva-estado">${e(estado[0])}</span>
        <button type="button" class="cj-asiento-btn" data-llego="${r.id}">Llegó</button>
      </li>`;
    }).join('') : '<li class="cj-vacio-chico">No quedan reservas pendientes para hoy.</li>';
  }
  $('cjReservas').addEventListener('click', ev => {
    const b = ev.target.closest('[data-llego]');
    if (b) llegoReserva(Number(b.dataset.llego));
  });

  /* ════════════ Ajustes: comensales, mover, anular ════════════ */
  let comensalesAjuste = 1;
  function abrirAjustes() {
    const c = st.cuenta;
    comensalesAjuste = c.comensales;
    $('moverComensales').textContent = comensalesAjuste;
    const libres = st.datos.mesas.filter(m => !m.cuenta);
    $('moverMesa').innerHTML = libres.length
      ? libres.map(m => `<option value="${m.id}">Mesa ${m.numero} · hasta ${m.capacidad}</option>`).join('')
      : '<option value="">No hay mesas libres</option>';
    $('btnMover').disabled = !libres.length;
    $('btnAnularCuenta').hidden = c.pagado > 0;
    abrirModal('modalMover');
  }
  $('modalMover').addEventListener('click', ev => {
    const b = ev.target.closest('[data-paso]');
    if (!b) return;
    comensalesAjuste = Math.max(1, Math.min(40, comensalesAjuste + Number(b.dataset.paso)));
    $('moverComensales').textContent = comensalesAjuste;
  });
  $('btnGuardarComensales').addEventListener('click', async () => {
    const r = await ejecutar(() => accion('cambiar_comensales', { cuenta_id: st.cuenta.id, comensales: comensalesAjuste }));
    if (r) cerrarModal('modalMover');
  });
  $('btnMover').addEventListener('click', async () => {
    const mesaId = Number($('moverMesa').value);
    if (!mesaId) return;
    const r = await ejecutar(() => accion('mover_mesa', { cuenta_id: st.cuenta.id, mesa_id: mesaId }));
    if (!r) return;
    cerrarModal('modalMover');
    st.sel = { mesaId };
    refrescar();
  });
  $('btnAnularCuenta').addEventListener('click', () => { cerrarModal('modalMover'); pedirMotivo('cuenta', st.cuenta.id); });

  let anulando = null;
  function pedirMotivo(tipo, id) {
    anulando = { tipo, id };
    $('anularTitulo').textContent = tipo === 'cuenta' ? 'Anular la cuenta' : 'Anular producto';
    $('anularSub').textContent = tipo === 'cuenta' ? 'La mesa queda libre y no se cobra nada.' : 'Ya salió para cocina: queda registrado quién lo anuló y por qué.';
    $('anularMotivo').value = '';
    abrirModal('modalAnular');
  }
  $('formAnular').addEventListener('submit', async ev => {
    ev.preventDefault();
    const motivo = $('anularMotivo').value.trim();
    if (!motivo || !anulando) return;
    const r = await ejecutar(() => anulando.tipo === 'cuenta'
      ? accion('anular_cuenta', { cuenta_id: anulando.id, motivo })
      : accion('anular_item', { item_id: anulando.id, motivo }));
    if (!r) return;
    cerrarModal('modalAnular');
    if (anulando.tipo === 'cuenta' && st.sel?.cuentaId) st.sel = null;
    anulando = null;
    refrescar();
  });

  /* ════════════ Turno y comandas ════════════ */
  $('formAbrirTurno').addEventListener('submit', async ev => {
    ev.preventDefault();
    const boton = ev.submitter || ev.target.querySelector('button');
    boton.disabled = true;
    await ejecutar(() => api('/api/caja_turno.php', { accion: 'abrir', fondo: Number($('fondoInicial').value) || 0 }));
    boton.disabled = false;
    pintarCuenta();
  });
  $('btnComandas').addEventListener('click', () => {
    imprimir('tipo=comandas');
    setTimeout(refrescar, 2500);
  });

  /* ════════════ Ciclo de actualización ════════════ */
  async function ciclo() {
    if (!document.hidden && !hayModalAbierto()) await refrescar();
    setTimeout(ciclo, 5000);
  }
  document.addEventListener('visibilitychange', () => { if (!document.hidden) refrescar(); });

  pintarCuenta();
  refrescar().then(() => setTimeout(ciclo, 5000));
})();
