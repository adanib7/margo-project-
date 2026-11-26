// Cierre de caja: reporte Z en vivo, ventas por hora y arqueo billete por billete.
(() => {
  const { api, plata, imprimir } = Caja;
  const e = escaparHtml;
  const $ = id => document.getElementById(id);
  const ICONOS = { efectivo: 'payments', debito: 'credit_card', credito: 'credit_score', transferencia: 'qr_code_2' };
  const TINTE = { 2000: '#d9cfe8', 1000: '#f3d9b5', 500: '#cfe0ef', 200: '#f2c9c2', 100: '#e9dcae', 50: '#d4e8cf', 20: '#e3e3dc' };

  let datos = null;
  let conteo = {};
  let cerrando = false;

  async function cargar() {
    try {
      datos = await api('/api/caja_turno.php');
    } catch (err) {
      $('ccContenido').innerHTML = `<div class="gu-estado"><p>${e(err.message)}</p></div>`;
      return;
    }
    pintarHistorial();
    if (!datos.turno) return pintarSinTurno();
    datos.denominaciones.forEach(d => { if (conteo[d] === undefined) conteo[d] = 0; });
    pintarTurno();
  }

  function pintarSinTurno() {
    $('ccEyebrow').textContent = 'Facturación · caja cerrada';
    $('ccContenido').innerHTML = `<div class="cj-aviso cerrada">
      <span class="material-symbols-outlined" aria-hidden="true">lock</span>
      <div class="cj-aviso-texto"><strong>No hay una caja abierta</strong>Abrila con el efectivo que queda en el cajón para empezar a cobrar.</div>
      <form id="ccAbrir"><label for="ccFondo">Fondo inicial</label>
        <input class="cj-input-plata" type="number" id="ccFondo" placeholder="Ej. 3000" min="0" step="1" inputmode="numeric" value="${Number(datos.fondo_sugerido) || 0}">
        <button type="submit" class="cj-boton-dorado"><span class="material-symbols-outlined" aria-hidden="true">lock_open</span>Abrir caja</button></form>
    </div>`;
    $('ccAbrir').addEventListener('submit', async ev => {
      ev.preventDefault();
      try {
        const r = await api('/api/caja_turno.php', { accion: 'abrir', fondo: Number($('ccFondo').value) || 0 });
        mostrarToast(r.mensaje, 'exito');
        cargar();
      } catch (err) { mostrarToast(err.message, 'error'); }
    });
  }

  function contado() {
    return datos.denominaciones.reduce((s, d) => s + d * (conteo[d] || 0), 0);
  }

  function pintarTurno() {
    const t = datos.turno, r = datos.resumen;
    const abierto = new Date(t.abierto_en.replace(' ', 'T'));
    $('ccEyebrow').textContent = `Facturación · turno #${t.id} · abierto ${abierto.toLocaleDateString('es-UY', { day: '2-digit', month: 'short' })} ${t.abierto_en.slice(11, 16)}`;
    const esperado = t.fondo + r.efectivo_cobrado;
    const maxHora = Math.max(1, ...r.horas.map(h => h.monto));
    const medios = Object.entries(r.medios);

    $('ccContenido').innerHTML = `<div class="cc-layout">
      <section class="cc-tira" aria-label="Reporte del turno">
        <div class="cj-zig arriba" aria-hidden="true"></div>
        <div class="cc-papel">
          <div class="cc-bloque cc-centro"><h2>REPORTE X · TURNO #${t.id}</h2><div>Abierto por ${e(t.abierto_por)}</div><div>${e(t.abierto_en.slice(0, 16))} → en curso</div></div>
          <div class="cc-bloque"><div class="cc-bloque-tit">COBROS POR MEDIO</div>
            ${medios.map(([, m]) => `<div class="cc-fila"><span>${e(m.label)} (${m.cantidad})</span><span>${plata(m.monto)}</span></div>`).join('')}
            <div class="cc-fila total"><span>TOTAL</span><span>${plata(r.total)}</span></div></div>
          <div class="cc-bloque"><div class="cc-bloque-tit">COMPROBANTES</div>
            <div class="cc-fila"><span>e-Ticket</span><span>${r.comprobantes.eticket}</span></div>
            <div class="cc-fila"><span>e-Factura</span><span>${r.comprobantes.efactura}</span></div>
            <div class="cc-fila" style="color:#785a1a;font-weight:700"><span>Enviados a DGI</span><span>sin integrar</span></div></div>
          <div class="cc-bloque"><div class="cc-bloque-tit">IVA (incluido)</div>
            <div class="cc-fila"><span>Tasa básica 22%</span><span>${plata(r.comprobantes.iva_basica)}</span></div>
            ${r.comprobantes.iva_minima ? `<div class="cc-fila"><span>Tasa mínima 10%</span><span>${plata(r.comprobantes.iva_minima)}</span></div>` : ''}
            <div class="cc-fila"><span>Neto gravado</span><span>${plata(r.comprobantes.neto_basica + r.comprobantes.neto_minima)}</span></div></div>
          <div class="cc-bloque"><div class="cc-bloque-tit">PROPINAS</div>
            <div class="cc-fila"><span>Total</span><span>${plata(r.propina_total)}</span></div>
            ${r.propinas.map(p => `<div class="cc-fila"><span>${e(p.mozo)}</span><span>${plata(p.monto)}</span></div>`).join('')}</div>
          <div class="cc-bloque"><div class="cc-bloque-tit">ARQUEO EFECTIVO</div>
            <div class="cc-fila"><span>Fondo inicial</span><span>${plata(t.fondo)}</span></div>
            <div class="cc-fila"><span>Efectivo + propinas</span><span>${plata(r.efectivo_cobrado)}</span></div>
            <div class="cc-fila"><span>Esperado</span><span>${plata(esperado)}</span></div>
            <div class="cc-fila"><span>Contado</span><span id="ccTiraContado">${plata(contado())}</span></div>
            <div class="cc-fila" style="font-weight:700" id="ccTiraDif"></div></div>
        </div>
        <div class="cj-zig" aria-hidden="true"></div>
      </section>

      <div class="cc-derecha">
        <section class="cj-panel" aria-labelledby="ccNocheTit">
          <div class="cj-panel-cab"><h2 class="cj-panel-titulo" id="ccNocheTit">El turno, hora por hora</h2>
            <span style="font-size:.8rem;color:#434840">${r.cobros} ${r.cobros === 1 ? 'cobro' : 'cobros'}</span></div>
          ${r.horas.length ? `<div class="cc-barras">${r.horas.map(h => `
            <div class="cc-barra ${h.monto === maxHora ? 'pico' : ''}"><span>${plata(h.monto)}</span><div style="height:${Math.max(4, Math.round(h.monto / maxHora * 120))}px"></div><span>${e(h.hora)}</span></div>`).join('')}</div>`
            : '<p class="cj-vacio-chico">Todavía no hubo cobros en este turno.</p>'}
          <div class="cc-medios">${medios.map(([k, m]) => {
            const pct = r.total ? Math.round(m.monto / r.total * 100) : 0;
            return `<div class="cc-medio"><div class="cc-medio-tit"><span class="material-symbols-outlined" aria-hidden="true">${ICONOS[k] || 'payments'}</span>${e(m.label)}</div>
              <div class="cc-medio-monto">${plata(m.monto)}</div><div class="cc-medio-barra"><div style="width:${pct}%"></div></div>
              <div style="font-size:.7rem;color:#434840;margin-top:4px">${pct}% del total</div></div>`;
          }).join('')}</div>
        </section>

        <section class="cj-panel" aria-labelledby="ccArqueoTit">
          <div class="cj-panel-cab">
            <div><h2 class="cj-panel-titulo" id="ccArqueoTit">Contá el cajón</h2>
              <p style="margin:.25rem 0 0;font-size:.85rem;color:#434840">Cantidad de cada billete y moneda. La diferencia se actualiza al toque.</p></div>
            <div class="cc-dif" id="ccDif" aria-live="polite"></div>
          </div>
          <div class="cc-billetes">${datos.denominaciones.map(d => `
            <label class="cc-billete">
              <span class="cc-billete-valor ${d <= 10 ? 'moneda' : ''}" style="${d > 10 ? 'background:' + (TINTE[d] || '#d9dbd2') : ''}">$${d.toLocaleString('es-UY')}</span>
              <input type="number" min="0" step="1" inputmode="numeric" data-den="${d}" value="${conteo[d] || ''}" placeholder="0" aria-label="Cantidad de ${d > 10 ? 'billetes' : 'monedas'} de $${d}">
              <span class="cc-billete-sub" data-sub="${d}">${plata(d * (conteo[d] || 0))}</span>
            </label>`).join('')}</div>
          <div class="campo-grupo" style="margin-top:1rem">
            <label class="campo-etiqueta" for="ccNota">Nota del cierre <span class="campo-opcional">(opcional)</span></label>
            <textarea class="campo-textarea" id="ccNota" rows="2" maxlength="255" placeholder="Ej. faltante por vuelto mal dado en mesa 4"></textarea>
          </div>
          <div class="cc-pie">
            <span style="display:flex;align-items:center;gap:.4rem;font-size:.85rem;color:${datos.cuentas_abiertas ? '#b91c1c' : '#434840'}">
              <span class="material-symbols-outlined" aria-hidden="true">${datos.cuentas_abiertas ? 'warning' : 'info'}</span>
              ${datos.cuentas_abiertas ? `Hay ${datos.cuentas_abiertas} ${datos.cuentas_abiertas === 1 ? 'mesa abierta' : 'mesas abiertas'}: cobralas antes de cerrar.` : 'Al cerrar se guarda el reporte Z y se imprime.'}
            </span>
            <span style="display:flex;gap:.5rem;flex-wrap:wrap">
              <button type="button" class="cj-enlace" id="ccImprimirX"><span class="material-symbols-outlined" aria-hidden="true">print</span>Imprimir parcial</button>
              <button type="button" class="cj-cobrar" style="width:auto;padding:.9rem 1.3rem" id="ccCerrar" ${datos.cuentas_abiertas ? 'disabled' : ''}>
                <span class="material-symbols-outlined" aria-hidden="true">lock</span>Cerrar caja e imprimir Z</button>
            </span>
          </div>
        </section>
      </div>
    </div>`;
    actualizarDiferencia();

    $('ccContenido').querySelector('.cc-billetes').addEventListener('input', ev => {
      const d = ev.target.dataset.den;
      if (!d) return;
      conteo[d] = Math.max(0, Math.floor(Number(ev.target.value) || 0));
      document.querySelector(`[data-sub="${d}"]`).textContent = plata(d * conteo[d]);
      actualizarDiferencia();
    });
    $('ccImprimirX').addEventListener('click', () => imprimir('tipo=z&turno=' + t.id));
    $('ccCerrar').addEventListener('click', cerrar);
  }

  function actualizarDiferencia() {
    const esperado = datos.turno.fondo + datos.resumen.efectivo_cobrado;
    const c = contado(), dif = Math.round((c - esperado) * 100) / 100;
    const tipo = Math.abs(dif) < 0.01 ? 'cuadra' : dif < 0 ? 'faltan' : 'sobran';
    const texto = (dif > 0 ? '+ ' : dif < 0 ? '− ' : '') + plata(Math.abs(dif));
    $('ccDif').className = 'cc-dif ' + tipo;
    $('ccDif').innerHTML = `<small>${{ cuadra: 'Cuadra', faltan: 'Faltan', sobran: 'Sobran' }[tipo]}</small><span class="cj-mono">${texto}</span>`;
    $('ccTiraContado').textContent = plata(c);
    $('ccTiraDif').innerHTML = `<span>Diferencia</span><span>${texto}</span>`;
    $('ccTiraDif').style.color = { cuadra: '#264220', faltan: '#b91c1c', sobran: '#785a1a' }[tipo];
  }

  async function cerrar() {
    if (cerrando) return;
    const esperado = datos.turno.fondo + datos.resumen.efectivo_cobrado;
    const dif = contado() - esperado;
    if (Math.abs(dif) >= 1 && !confirm(`El arqueo no cuadra (${dif > 0 ? 'sobran' : 'faltan'} ${plata(Math.abs(dif))}). ¿Cerrar igual la caja?`)) return;
    cerrando = true;
    $('ccCerrar').disabled = true;
    try {
      const r = await api('/api/caja_turno.php', { accion: 'cerrar', arqueo: conteo, nota: $('ccNota').value });
      mostrarToast(r.mensaje, 'exito');
      imprimir('tipo=z&turno=' + r.turno_id);
      conteo = {};
      await cargar();
    } catch (err) {
      mostrarToast(err.message, 'error', 5000);
      $('ccCerrar').disabled = false;
    } finally {
      cerrando = false;
    }
  }

  function pintarHistorial() {
    const h = datos.historial;
    $('ccHistorial').innerHTML = h.length ? `<table class="cc-historial"><thead><tr><th>Turno</th><th>Cierre</th><th>Cerró</th><th>Ventas</th><th>Diferencia</th><th><span class="sr-only">Reporte</span></th></tr></thead><tbody>
      ${h.map(t => `<tr><td>#${t.id}</td><td>${e(t.cerrado_en.slice(0, 16))}</td><td>${e(t.cerrado_por || '')}</td><td class="cj-mono">${plata(t.total)}</td>
        <td class="cj-mono" style="color:${Math.abs(t.diferencia) < 0.01 ? '#264220' : t.diferencia < 0 ? '#b91c1c' : '#785a1a'}">${t.diferencia > 0 ? '+ ' : ''}${plata(t.diferencia)}</td>
        <td><button type="button" class="btn-tabla" data-z="${t.id}" aria-label="Ver reporte Z del turno ${t.id}"><span class="material-symbols-outlined">receipt_long</span></button></td></tr>`).join('')}
      </tbody></table>` : '<p class="cj-vacio-chico">Sin cierres todavía.</p>';
  }
  $('ccHistorial').addEventListener('click', ev => {
    const b = ev.target.closest('[data-z]');
    if (b) imprimir('tipo=z&turno=' + b.dataset.z + '&ver=1');
  });

  cargar();
})();
