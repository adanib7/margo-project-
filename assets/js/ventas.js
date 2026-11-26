// Historial de ventas: filtros por período, resumen, comprobantes, anulaciones y detalle.
(() => {
  const { api, plata, imprimir, abrirModal } = Caja;
  const e = escaparHtml;
  const $ = id => document.getElementById(id);
  const MEDIOS = { efectivo: 'Efectivo', debito: 'Débito', credito: 'Crédito', transferencia: 'Transf. / QR' };
  const ICONOS = { efectivo: 'payments', debito: 'credit_card', credito: 'credit_score', transferencia: 'qr_code_2' };

  const st = { vista: 'ventas', pagina: 1, datos: null, pedido: 0 };

  // Fechas locales (no UTC) en formato YYYY-MM-DD.
  const iso = d => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  const fechaCorta = s => { const [y, m, d] = s.split('-'); return `${d}/${m}`; };

  function aplicarRango(r) {
    const [ay, am, ad] = HOY_SERVIDOR.split('-').map(Number);
    const hoy = new Date(ay, am - 1, ad);
    let desde = new Date(hoy), hasta = new Date(hoy);
    if (r === 'ayer') { desde.setDate(hoy.getDate() - 1); hasta = new Date(desde); }
    if (r === '7') desde.setDate(hoy.getDate() - 6);
    if (r === 'mes') desde = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
    $('vtDesde').value = iso(desde);
    $('vtHasta').value = iso(hasta);
    marcarRango(r);
  }
  function marcarRango(r) {
    document.querySelectorAll('[data-rango]').forEach(b => {
      const on = b.dataset.rango === r;
      b.classList.toggle('activo', on);
      b.setAttribute('aria-pressed', String(on));
    });
  }

  function parametros(extra = {}) {
    const p = new URLSearchParams({ desde: $('vtDesde').value, hasta: $('vtHasta').value, medio: $('vtMedio').value, tipo: $('vtTipo').value, q: $('vtBuscar').value.trim(), ...extra });
    return p.toString();
  }

  async function cargar() {
    const n = ++st.pedido;
    try {
      const d = await api('/api/ventas.php?' + parametros({ pagina: st.pagina }));
      if (n !== st.pedido) return; // llegó una respuesta vieja
      st.datos = d;
      pintar();
    } catch (err) {
      $('vtTabla').innerHTML = `<div class="gu-estado"><p>${e(err.message)}</p></div>`;
    }
  }

  function pintar() {
    const d = st.datos, r = d.resumen;
    $('vtTotal').textContent = plata(r.total);
    $('vtCuentas').textContent = r.cuentas;
    $('vtPromedio').textContent = plata(r.promedio);
    $('vtPropina').textContent = plata(r.propina);
    $('vtIva').textContent = `IVA incluido ${plata(r.iva)} · ${r.comprobantes} ${r.comprobantes === 1 ? 'comprobante' : 'comprobantes'}`;
    $('vtSerieTit').textContent = d.un_dia ? 'Ventas por hora' : 'Ventas por día';

    const max = Math.max(1, ...d.serie.map(s => s.monto));
    $('vtSerie').innerHTML = d.serie.length ? `<div class="cc-barras">${d.serie.map(s => `
      <div class="cc-barra ${s.monto === max ? 'pico' : ''}" title="${e(s.etiqueta)}: ${plata(s.monto)}">
        <span>${plata(s.monto)}</span><div style="height:${Math.max(4, Math.round(s.monto / max * 110))}px"></div>
        <span>${e(d.un_dia ? s.etiqueta : fechaCorta(s.etiqueta))}</span></div>`).join('')}</div>`
      : '<p class="cj-vacio-chico">Sin ventas en este período.</p>';

    $('vtMedios').innerHTML = Object.entries(r.medios).map(([k, m]) => {
      const pct = r.total ? Math.round(m.monto / r.total * 100) : 0;
      return `<div class="cc-medio"><div class="cc-medio-tit"><span class="material-symbols-outlined" aria-hidden="true">${ICONOS[k]}</span>${e(m.label)}</div>
        <div class="cc-medio-monto">${plata(m.monto)}</div><div class="cc-medio-barra"><div style="width:${pct}%"></div></div>
        <div class="vt-nota">${m.cantidad} · ${pct}%</div></div>`;
    }).join('');

    const topMax = Math.max(1, ...d.top.map(t => t.cantidad));
    $('vtTop').innerHTML = d.top.length ? d.top.map(t => `
      <li><span class="vt-top-nombre">${e(t.nombre)}</span><span class="vt-top-cant">${t.cantidad}</span>
        <span class="vt-top-barra"><span style="width:${Math.round(t.cantidad / topMax * 100)}%"></span></span></li>`).join('')
      : '<li class="cj-vacio-chico">Sin datos.</li>';

    $('vtAnulCant').textContent = d.anulaciones.length ? `(${d.anulaciones.length})` : '';
    pintarTabla();
  }

  function pintarTabla() {
    const d = st.datos;
    const varios = d.desde !== d.hasta;
    if (st.vista === 'anulaciones') {
      $('vtPaginas').innerHTML = '';
      $('vtTabla').innerHTML = d.anulaciones.length ? `<table class="gu-tabla vt-tabla vt-anul"><thead><tr>
          <th>${varios ? 'Fecha' : 'Hora'}</th><th>Mesa</th><th>Qué se anuló</th><th>Monto</th><th>Motivo y quién</th></tr></thead><tbody>
        ${d.anulaciones.map(a => `<tr class="vt-fila" data-cuenta="${a.cuenta_id}" tabindex="0">
          <td class="cj-mono">${varios ? fechaCorta(a.fecha) + ' ' : ''}${a.hora}</td>
          <td>${a.mesa ? 'Mesa ' + a.mesa : 'Mostrador'}</td>
          <td>${a.tipo === 'cuenta' ? '<span class="badge-rol badge-estado-cancelada">Cuenta</span> ' : ''}${e(a.detalle)}</td>
          <td class="cj-mono">${plata(a.monto)}</td>
          <td class="vt-motivo">${e(a.motivo || '—')}</td></tr>`).join('')}
        </tbody></table>` : '<div class="gu-estado"><span class="material-symbols-outlined" style="font-size:2.5rem;color:#73796f" aria-hidden="true">verified</span><p>No hubo anulaciones en este período.</p></div>';
      return;
    }
    $('vtTabla').innerHTML = d.comprobantes.length ? `<table class="gu-tabla vt-tabla"><thead><tr>
        <th>${varios ? 'Fecha' : 'Hora'}</th><th>Comprobante</th><th>Mesa</th><th class="vt-col-mozo">Mozo</th><th>Medio</th><th class="vt-num">Total</th><th class="vt-num vt-col-propina">Propina</th><th class="col-acciones"><span class="sr-only">Ticket</span></th></tr></thead><tbody>
      ${d.comprobantes.map(c => `<tr class="vt-fila" data-cuenta="${c.cuenta_id}" tabindex="0" aria-label="Ver venta ${e(c.nombre)}">
        <td class="cj-mono">${varios ? fechaCorta(c.fecha) + ' ' : ''}${c.hora}</td>
        <td><span class="vt-comp">${e(c.nombre)}</span>${c.rut ? `<span class="vt-sub">${e(c.receptor || '')} · RUT ${e(c.rut)}</span>` : ''}</td>
        <td>${c.mesa ? 'Mesa ' + c.mesa : 'Mostrador'}${c.asiento ? ' · ' + e(c.asiento) : ''}</td>
        <td class="vt-col-mozo">${e(c.mozo || '—')}</td>
        <td>${e(MEDIOS[c.medio] || c.medio)}</td>
        <td class="cj-mono vt-num">${plata(c.total)}</td>
        <td class="cj-mono vt-num vt-col-propina">${c.propina ? plata(c.propina) : '—'}</td>
        <td><button type="button" class="btn-tabla" data-ticket="${c.id}" aria-label="Reimprimir ${e(c.nombre)}" title="Reimprimir"><span class="material-symbols-outlined">print</span></button></td>
      </tr>`).join('')}</tbody></table>`
      : '<div class="gu-estado"><span class="material-symbols-outlined" style="font-size:2.5rem;color:#73796f" aria-hidden="true">receipt_long</span><p>No hay ventas con estos filtros.</p></div>';

    $('vtPaginas').innerHTML = d.paginas > 1 ? `
      <button type="button" class="cj-enlace" data-pag="${d.pagina - 1}" ${d.pagina <= 1 ? 'disabled' : ''}><span class="material-symbols-outlined" aria-hidden="true">chevron_left</span>Anteriores</button>
      <span>Página ${d.pagina} de ${d.paginas}</span>
      <button type="button" class="cj-enlace" data-pag="${d.pagina + 1}" ${d.pagina >= d.paginas ? 'disabled' : ''}>Siguientes<span class="material-symbols-outlined" aria-hidden="true">chevron_right</span></button>` : '';
  }

  /* ── Detalle de una venta ── */
  async function verVenta(id) {
    let c;
    try { c = (await api('/api/ventas.php?cuenta=' + id)).cuenta; } catch (err) { return mostrarToast(err.message, 'error'); }
    const [y, m, d] = c.fecha.split('-');
    $('vtDetTitulo').textContent = (c.mesa_numero ? 'Mesa ' + c.mesa_numero : 'Mostrador') + ` · ${d}/${m}/${y}`;
    $('vtDetSub').textContent = `${c.abierta_en}${c.cerrada_en ? ' → ' + c.cerrada_en.slice(11, 16) : ''} · ${c.comensales} ${c.comensales === 1 ? 'persona' : 'personas'}${c.mozo ? ' · ' + c.mozo : ''}`;
    const estado = { cobrada: ['Cobrada', 'badge-estado-confirmada'], anulada: ['Anulada', 'badge-estado-cancelada'], abierta: ['Abierta', 'badge-estado-pendiente'], pide_cuenta: ['Pide la cuenta', 'badge-estado-pendiente'] }[c.estado] || [c.estado, ''];
    $('vtDetCuerpo').innerHTML = `
      <p style="margin:0 0 .75rem"><span class="badge-rol ${estado[1]}">${estado[0]}</span>${c.motivo_anulacion ? ` <span class="vt-motivo">${e(c.motivo_anulacion)}</span>` : ''}</p>
      <ul class="cj-items">${c.items.map(i => `
        <li class="cj-item ${i.anulado ? 'anulado' : ''}">
          <span class="cj-asiento-mini ${i.asiento ? '' : 'compartido'}">${i.asiento || '∗'}</span>
          <span class="cj-item-cant">${i.cantidad}×</span>
          <span class="cj-item-info"><span class="cj-item-nombre">${e(i.nombre)}</span>
            ${i.nota ? `<span class="cj-item-nota">${e(i.nota)}</span>` : ''}
            ${i.anulado ? `<span class="cj-item-nota">Anulado: ${e(i.motivo_anulacion || '')}</span>` : ''}
            <span class="cj-item-nota" style="color:#73796f;font-style:normal">${i.hora}${i.cargado_por ? ' · ' + e(i.cargado_por) : ''}</span></span>
          <span class="cj-item-total">${plata(i.total)}</span></li>`).join('') || '<li class="cj-vacio-chico">Sin productos.</li>'}</ul>
      <div class="cj-total" style="margin:.6rem 0 1rem"><span>Total</span><span class="cj-mono">${plata(c.total)}</span></div>
      ${c.pagos.length ? `<p class="cj-subtitulo">Pagos y comprobantes</p><ul class="cj-pagos">${c.pagos.map(p => `
        <li class="cj-pago"><span class="material-symbols-outlined" aria-hidden="true">check_circle</span>
          <span>${p.hora} · ${e(MEDIOS[p.medio] || p.medio)}${p.asiento ? ' · asiento ' + e(p.asiento) : ''} · <span class="cj-mono">${plata(p.monto)}</span>${p.propina ? ' + ' + plata(p.propina) : ''}<br><strong>${e(p.comprobante || '')}</strong></span>
          ${p.comprobante_id ? `<button type="button" data-ticket="${p.comprobante_id}" aria-label="Reimprimir ${e(p.comprobante || '')}"><span class="material-symbols-outlined">print</span></button>` : ''}
        </li>`).join('')}</ul>` : ''}`;
    abrirModal('modalVenta');
  }

  /* ── Eventos ── */
  document.querySelector('.vt-rangos').addEventListener('click', ev => {
    const b = ev.target.closest('[data-rango]');
    if (!b) return;
    aplicarRango(b.dataset.rango);
    st.pagina = 1; cargar();
  });
  ['vtDesde', 'vtHasta'].forEach(id => $(id).addEventListener('change', () => { marcarRango(''); st.pagina = 1; cargar(); }));
  ['vtMedio', 'vtTipo'].forEach(id => $(id).addEventListener('change', () => { st.pagina = 1; cargar(); }));
  let espera;
  $('vtBuscar').addEventListener('input', () => { clearTimeout(espera); espera = setTimeout(() => { st.pagina = 1; cargar(); }, 300); });

  document.querySelector('.vt-pestanas').addEventListener('click', ev => {
    const b = ev.target.closest('[data-vista]');
    if (!b || !st.datos) return;
    st.vista = b.dataset.vista;
    document.querySelectorAll('.vt-pestanas [data-vista]').forEach(x => {
      const on = x === b;
      x.classList.toggle('activo', on);
      x.setAttribute('aria-selected', String(on));
    });
    pintarTabla();
  });

  document.addEventListener('click', ev => {
    const t = ev.target.closest('[data-ticket]');
    if (t) { ev.stopPropagation(); return imprimir('tipo=comprobante&id=' + t.dataset.ticket + '&ver=1'); }
    const pag = ev.target.closest('[data-pag]');
    if (pag && !pag.disabled) { st.pagina = Number(pag.dataset.pag); cargar(); window.scrollTo({ top: $('vtTabla').offsetTop - 120, behavior: 'smooth' }); return; }
    const fila = ev.target.closest('.vt-fila');
    if (fila) verVenta(fila.dataset.cuenta);
  });
  $('vtTabla').addEventListener('keydown', ev => {
    const fila = ev.target.closest('.vt-fila');
    if (fila && (ev.key === 'Enter' || ev.key === ' ')) { ev.preventDefault(); verVenta(fila.dataset.cuenta); }
  });

  $('vtCsv').addEventListener('click', () => { window.location.href = BASE + '/api/ventas.php?' + parametros({ formato: 'csv' }); });

  aplicarRango('hoy');
  cargar();
})();
