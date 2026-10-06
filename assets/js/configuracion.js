(function () {

  const $ = id => document.getElementById(id);

  function esc(s) {
    return String(s ?? '')
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  // ── Vista previa de la dirección ─────────────────────────────────────────
  function pintarDireccion() {
    const calle  = $('locDireccion').value.trim();
    const cp     = $('locCp').value.trim();
    const ciudad = $('locCiudad').value.trim();
    const prov   = $('locProvincia').value.trim();

    let localidad = [cp, ciudad].filter(Boolean).join(' ');
    if (prov) localidad += ' (' + prov + ')';

    const txt = [calle, localidad].filter(Boolean).join(' · ');
    $('previewDireccion').textContent = txt || '—';
  }

  ['locDireccion', 'locCp', 'locCiudad', 'locProvincia'].forEach(id =>
    $(id).addEventListener('input', pintarDireccion));
  pintarDireccion();

  // ── Vista previa de los horarios ─────────────────────────────────────────
  function slots(inicio, fin, intervalo) {
    const aMin = h => { const [x, y] = h.split(':').map(Number); return x * 60 + y; };
    const ini = aMin(inicio), end = aMin(fin);
    if (isNaN(ini) || isNaN(end) || end < ini) return [];
    const out = [];
    for (let t = ini; t <= end && out.length <= 60; t += intervalo) {
      out.push(String(Math.floor(t / 60)).padStart(2, '0') + ':' + String(t % 60).padStart(2, '0'));
    }
    return out;
  }

  function pintarPreview() {
    const intervalo = parseInt($('intervalo').value, 10) || 30;
    let html = '';

    [['almuerzo', 'Almuerzo'], ['cena', 'Cena']].forEach(([t, label]) => {
      if (!$(t + 'Activo').checked) return;
      const s = slots($(t + 'Inicio').value, $(t + 'Fin').value, intervalo);
      if (!s.length) return;
      html += '<div class="cfg-preview-turno"><span class="cfg-preview-turno-nom">' + label + '</span>'
            + '<div class="cfg-preview-chips">'
            + s.map(h => '<span class="cfg-chip">' + h + '</span>').join('')
            + '</div></div>';
    });

    $('previewHorarios').innerHTML = html || '<p class="cfg-preview-vacio">No hay ningún turno activo: el cliente no podría reservar.</p>';
  }

  ['almuerzoActivo', 'almuerzoInicio', 'almuerzoFin', 'cenaActivo', 'cenaInicio', 'cenaFin', 'intervalo']
    .forEach(id => $(id).addEventListener('change', pintarPreview));
  pintarPreview();

  // ── Días de la semana ────────────────────────────────────────────────────
  document.querySelectorAll('.chkDia').forEach(chk => {
    chk.addEventListener('change', () => {
      chk.closest('.cfg-dia').classList.toggle('cfg-dia-on', chk.checked);
    });
  });

  // ── Cierres puntuales ────────────────────────────────────────────────────
  const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

  function fechaLegible(iso) {
    const [a, m, d] = iso.split('-').map(Number);
    return d + ' ' + MESES[m - 1] + ' ' + a;
  }

  function pintarFechas() {
    if (!fechasCerradas.length) {
      $('listaFechas').innerHTML = '<p class="cfg-preview-vacio">Sin cierres puntuales cargados.</p>';
      return;
    }
    $('listaFechas').innerHTML = fechasCerradas.map(f =>
      '<span class="cfg-fecha-chip">' + esc(fechaLegible(f))
      + '<button type="button" class="cfg-fecha-x" data-fecha="' + esc(f) + '" aria-label="Quitar">'
      + '<span class="material-symbols-outlined">close</span></button></span>'
    ).join('');

    $('listaFechas').querySelectorAll('.cfg-fecha-x').forEach(b => {
      b.addEventListener('click', () => {
        fechasCerradas = fechasCerradas.filter(f => f !== b.dataset.fecha);
        pintarFechas();
      });
    });
  }

  $('btnAddFecha').addEventListener('click', () => {
    const v = $('nuevaFecha').value;
    if (!v) return;
    if (!fechasCerradas.includes(v)) {
      fechasCerradas.push(v);
      fechasCerradas.sort();
      pintarFechas();
    }
    $('nuevaFecha').value = '';
  });

  pintarFechas();

  // ── Guardar ──────────────────────────────────────────────────────────────
  const btnGuardar = $('btnGuardar');

  function limpiarErrores() {
    document.querySelectorAll('[id^="err_"]').forEach(el => el.textContent = '');
    document.querySelectorAll('.campo-wrapper-error').forEach(el => el.classList.remove('campo-wrapper-error'));
  }

  function setLoading(on) {
    btnGuardar.disabled = on;
    const ic = btnGuardar.querySelector('.material-symbols-outlined');
    ic.textContent = on ? 'progress_activity' : 'save';
    ic.classList.toggle('icono-spin', on);
  }

  $('formConfig').addEventListener('submit', async e => {
    e.preventDefault();
    limpiarErrores();
    setLoading(true);

    const payload = {
      'local.nombre':    $('locNombre').value.trim(),
      'local.eslogan':   $('locEslogan').value.trim(),
      'local.direccion': $('locDireccion').value.trim(),
      'local.cp':        $('locCp').value.trim(),
      'local.ciudad':    $('locCiudad').value.trim(),
      'local.provincia': $('locProvincia').value.trim(),
      'local.telefono':  $('locTelefono').value.trim(),
      'local.email':     $('locEmail').value.trim(),
      'local.sitio_url': $('locSitio').value.trim(),

      'horario.almuerzo_activo': $('almuerzoActivo').checked,
      'horario.almuerzo_inicio': $('almuerzoInicio').value,
      'horario.almuerzo_fin':    $('almuerzoFin').value,
      'horario.cena_activo':     $('cenaActivo').checked,
      'horario.cena_inicio':     $('cenaInicio').value,
      'horario.cena_fin':        $('cenaFin').value,
      'horario.intervalo_min':   parseInt($('intervalo').value, 10),
      'horario.dias_cerrados':   Array.from(document.querySelectorAll('.chkDia:checked')).map(c => parseInt(c.value, 10)),
      'horario.fechas_cerradas': fechasCerradas,

      'reservas.max_personas':         parseInt($('maxPersonas').value, 10),
      'reservas.antelacion_min_horas': parseInt($('antMin').value, 10),
      'reservas.antelacion_max_dias':  parseInt($('antMax').value, 10),
      'reservas.duracion_horas':       parseInt($('duracion').value, 10),
      'reservas.cortesia_min':         parseInt($('cortesia').value, 10),
      'reservas.auto_confirmar':       $('autoConfirmar').checked,
    };

    try {
      const res  = await fetch(BASE + '/api/guardar_configuracion.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const data = await res.json();

      if (data.ok) {
        mostrarToast(data.mensaje, 'exito');
      } else if (data.errores) {
        Object.keys(data.errores).forEach(k => {
          const el = $('err_' + k);
          if (el) el.textContent = data.errores[k];
        });
        mostrarToast('Revisá los campos marcados.', 'error');
      } else {
        mostrarToast(data.mensaje || 'Error inesperado.', 'error');
      }
    } catch {
      mostrarToast('No se pudo conectar con el servidor.', 'error');
    } finally {
      setLoading(false);
    }
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
})();
