(function () {

  const btnAbrir   = document.getElementById('btnAbrirReserva');
  const modal      = document.getElementById('modalReserva');
  const form       = document.getElementById('formReserva');
  const btnDerecha  = document.getElementById('btnPasoDerecha');
  const btnIzquierda = document.getElementById('btnPasoIzquierda');
  const iconoPasoDerecha = document.getElementById('iconoPasoDerecha');
  const textoPasoDerecha = document.getElementById('textoPasoDerecha');
  const inputFecha = document.getElementById('rFecha');
  const exito      = document.getElementById('reservaExito');
  const inputHora     = document.getElementById('rHora');
  const inputPersonas = document.getElementById('rPersonas');
  const inputTelefono = document.getElementById('rTelefono');
  const personasValor = document.getElementById('personasValor');
  const horarioChips  = Array.from(document.querySelectorAll('.horario-chip'));
  const inputMesa     = document.getElementById('rMesa');
  const planoWrap     = document.getElementById('planoReservaWrap');
  const planoLienzo   = document.getElementById('planoReservaLienzo');
  const planoEstado   = document.getElementById('planoReservaEstado');
  const planoHint     = document.getElementById('planoReservaHint');
  const modalContenedor = modal.querySelector('.modal-contenedor-reserva');
  const PERSONAS_MIN = 1;
  const PLANO_W = 900, PLANO_H = 560;
  const TOTAL_PASOS = 4;
  let pasoActual = 1;
  let planoData = null;
  let mesaCapacidad = PERSONAS_MAX;
  let personasMax = PERSONAS_MAX;

  const PASO_DE_CAMPO = {
    fecha: 1, hora: 1,
    mesa: 2,
    personas: 3,
    telefono: 4,
    nombre: 4,
  };

  // Rango del calendario según la antelación máxima configurada.
  const hoyISO = new Date().toISOString().split('T')[0];
  inputFecha.min = hoyISO;
  if (ANT_MAX_DIAS > 0) {
    const tope = new Date();
    tope.setDate(tope.getDate() + ANT_MAX_DIAS);
    inputFecha.max = tope.toISOString().split('T')[0];
  }

  /** ¿El restaurante cierra ese día? (día de la semana o fecha puntual) */
  function diaCerrado(iso) {
    if (FECHAS_CERRADAS.includes(iso)) return true;
    const [a, m, d] = iso.split('-').map(Number);
    const js = new Date(a, m - 1, d).getDay();   // 0 = domingo
    const iso7 = js === 0 ? 7 : js;              // 1 = lunes … 7 = domingo
    return DIAS_CERRADOS.includes(iso7);
  }

  /** ¿Ese horario respeta la antelación mínima? */
  function horaConAntelacion(iso, hora) {
    const [a, m, d] = iso.split('-').map(Number);
    const [hh, mm]  = hora.split(':').map(Number);
    const cuando = new Date(a, m - 1, d, hh, mm);
    return cuando.getTime() >= Date.now() + ANT_MIN_HORAS * 3600 * 1000;
  }
  inputFecha.addEventListener('change', onFechaChange);

  document.querySelectorAll('.horario-chip').forEach(chip => {
    chip.addEventListener('click', () => {
      document.querySelectorAll('.horario-chip').forEach(c => c.classList.remove('horario-chip-activo'));
      chip.classList.add('horario-chip-activo');
      inputHora.value = chip.dataset.hora;
      document.getElementById('rErrorHora').textContent = '';
    });
  });

  function setPersonas(valor) {
    valor = Math.min(personasMax, Math.max(PERSONAS_MIN, valor));
    inputPersonas.value = valor;
    personasValor.textContent = valor;
  }

  // ── Plano de mesas (paso 2) ──────────────────────────────────────────────
  function escalarPlano() {
    const ancho = planoWrap.clientWidth;
    if (!ancho) return;
    const esc = ancho / PLANO_W;
    planoLienzo.style.transform = `scale(${esc})`;
    planoWrap.style.height = (PLANO_H * esc) + 'px';
  }
  window.addEventListener('resize', escalarPlano);

  async function cargarPlano() {
    inputMesa.value = '';
    mesaCapacidad = PERSONAS_MAX;
    planoLienzo.innerHTML = '';
    planoEstado.hidden = false;
    planoEstado.textContent = 'Cargando plano…';
    planoHint.textContent = 'Tocá una mesa libre para elegirla.';
    document.getElementById('rErrorMesa').textContent = '';

    const fecha = inputFecha.value;
    const hora  = inputHora.value;

    try {
      const res  = await fetch(BASE + '/api/plano_disponible.php?fecha=' + encodeURIComponent(fecha) + '&hora=' + encodeURIComponent(hora));
      const data = await res.json();
      if (!data.ok) throw new Error(data.mensaje || 'No se pudo cargar el plano.');
      planoData = data;
      dibujarPlano(data.mesas);
    } catch (e) {
      planoEstado.hidden = false;
      planoEstado.textContent = e.message;
    }
  }

  function dibujarPlano(mesas) {
    planoLienzo.innerHTML = '';

    if (!mesas.length) {
      planoEstado.hidden = false;
      planoEstado.textContent = 'El restaurante todavía no cargó el plano de mesas.';
      return;
    }
    planoEstado.hidden = true;

    mesas.forEach(m => {
      const el = document.createElement('button');
      el.type = 'button';
      el.className = 'pr-mesa ' + (m.forma === 'redonda' ? 'redonda' : 'cuadrada') + (m.ocupada ? ' ocupada' : '');
      el.dataset.id = m.id;
      el.style.width  = m.ancho + 'px';
      el.style.height = m.alto + 'px';
      el.style.left   = (m.pos_x - m.ancho / 2) + 'px';
      el.style.top    = (m.pos_y - m.alto / 2) + 'px';
      el.style.transform = 'rotate(' + m.rotacion + 'deg)';
      el.textContent = m.numero;
      el.title = m.ocupada
        ? `Mesa ${m.numero} · ocupada en esta franja`
        : `Mesa ${m.numero} · hasta ${m.capacidad} personas`;
      if (m.ocupada) {
        el.disabled = true;
      } else {
        el.addEventListener('click', () => elegirMesa(m, el));
      }
      planoLienzo.appendChild(el);
    });

    requestAnimationFrame(escalarPlano);
  }

  function elegirMesa(m, el) {
    planoLienzo.querySelectorAll('.pr-mesa.elegida').forEach(x => x.classList.remove('elegida'));
    el.classList.add('elegida');
    inputMesa.value = m.id;
    mesaCapacidad = m.capacidad;
    document.getElementById('rErrorMesa').textContent = '';
    planoHint.textContent = `Elegiste la mesa ${m.numero} (hasta ${m.capacidad} personas).`;
  }

  function mesaSeleccionada() {
    if (!inputMesa.value || !planoData) return null;
    return planoData.mesas.find(x => String(x.id) === String(inputMesa.value)) || null;
  }

  function actualizarCapacidadHint() {
    personasMax = Math.max(PERSONAS_MIN, Math.min(PERSONAS_MAX, mesaCapacidad || PERSONAS_MAX));
    setPersonas(parseInt(inputPersonas.value, 10) || 2);
    const hint = document.getElementById('mesaCapacidadHint');
    hint.textContent = (mesaCapacidad && mesaCapacidad < PERSONAS_MAX)
      ? `Esta mesa admite hasta ${mesaCapacidad} personas.`
      : '';
  }

  function aplicarAnchoModal() {
    modalContenedor.classList.toggle('modal-plano-activo', pasoActual === 2);
    if (pasoActual === 2) {
      requestAnimationFrame(escalarPlano);
      setTimeout(escalarPlano, 240);
    }
  }

  async function onFechaChange() {
    const fecha = inputFecha.value;
    inputHora.value = '';
    horarioChips.forEach(chip => {
      chip.style.display = '';
      chip.classList.remove('horario-chip-activo', 'horario-chip-ocupado');
      chip.title = '';
    });

    if (!fecha) return;

    // Día de cierre: no se muestra ningún horario.
    if (diaCerrado(fecha)) {
      horarioChips.forEach(chip => chip.style.display = 'none');
      document.getElementById('rErrorFecha').textContent = 'Ese día el restaurante está cerrado. Elegí otro.';
      return;
    }
    document.getElementById('rErrorFecha').textContent = '';

    // Antelación mínima: escondo los horarios que ya no llegan.
    horarioChips.forEach(chip => {
      if (!horaConAntelacion(fecha, chip.dataset.hora)) {
        chip.style.display = 'none';
      }
    });

    await cargarHorariosOcupados(fecha);
  }

  async function cargarHorariosOcupados(fecha) {
    try {
      const res = await fetch(BASE + '/api/ocupaciones_horarios.php?fecha=' + encodeURIComponent(fecha));
      const data = await res.json();
      if (!data.ok || !Array.isArray(data.horarios)) return;

      const ocupados = new Set(data.horarios.map(h => h.slice(0, 5)));
      horarioChips.forEach(chip => {
        if (ocupados.has(chip.dataset.hora)) {
          chip.style.display = 'none';
          chip.classList.remove('horario-chip-activo');
          chip.classList.add('horario-chip-ocupado');
          chip.title = 'Horario ocupado';
        }
      });
    } catch (error) {
      // Si falla la carga de horarios ocupados, no bloqueamos la reserva.
    }
  }

  document.getElementById('btnPersonasMenos').addEventListener('click', () => {
    setPersonas(parseInt(inputPersonas.value, 10) - 1);
  });
  document.getElementById('btnPersonasMas').addEventListener('click', () => {
    setPersonas(parseInt(inputPersonas.value, 10) + 1);
  });

  btnAbrir.addEventListener('click', e => {
    e.preventDefault();
    abrirModal();
  });

  btnIzquierda.addEventListener('click', () => {
    if (pasoActual === 1) {
      cerrarModal();
    } else {
      irAPaso(pasoActual - 1);
    }
  });

  document.getElementById('btnCerrarModalReserva').addEventListener('click', cerrarModal);
  document.getElementById('btnCerrarExito').addEventListener('click', cerrarModal);
  modal.addEventListener('click', e => { if (e.target === modal) cerrarModal(); });
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && modal.classList.contains('modal-visible')) cerrarModal();
  });

  function abrirModal() {
    modal.classList.add('modal-visible');
    document.body.classList.add('modal-abierto');
    resetPasos();
    document.getElementById('rFecha').focus();
  }

  function cerrarModal() {
    modal.classList.remove('modal-visible');
    document.body.classList.remove('modal-abierto');
    limpiarErrores();
    setLoading(false);
    mostrarFormulario();
  }

  function mostrarFormulario() {
    form.hidden  = false;
    exito.hidden = true;
    resetPasos();
  }

  function resetPasos() {
    document.querySelectorAll('.modal-paso').forEach(p => {
      p.classList.remove('modal-paso-activo', 'entra-derecha', 'entra-izquierda');
    });
    document.querySelector('.modal-paso[data-paso="1"]').classList.add('modal-paso-activo');
    pasoActual = 1;
    inputMesa.value = '';
    planoData = null;
    mesaCapacidad = PERSONAS_MAX;
    personasMax = PERSONAS_MAX;
    document.getElementById('mesaCapacidadHint').textContent = '';
    actualizarIndicador();
    actualizarBotones();
    aplicarAnchoModal();
  }

  function irAPaso(nuevo) {
    if (nuevo === pasoActual) return;
    const direccion = nuevo > pasoActual ? 'adelante' : 'atras';
    const panelAnterior = form.querySelector(`.modal-paso[data-paso="${pasoActual}"]`);
    const panelNuevo    = form.querySelector(`.modal-paso[data-paso="${nuevo}"]`);

    panelAnterior.classList.remove('modal-paso-activo');
    panelNuevo.classList.add('modal-paso-activo');

    const claseEntrada = direccion === 'adelante' ? 'entra-derecha' : 'entra-izquierda';
    panelNuevo.classList.add(claseEntrada);
    panelNuevo.addEventListener('animationend', () => {
      panelNuevo.classList.remove(claseEntrada);
    }, { once: true });

    pasoActual = nuevo;
    actualizarIndicador();
    actualizarBotones();
    aplicarAnchoModal();
    if (pasoActual === 2) cargarPlano();
    if (pasoActual === 3) actualizarCapacidadHint();
    if (pasoActual === 4) actualizarResumen();

    const primerCampo = panelNuevo.querySelector('input:not([type="hidden"]), textarea');
    if (primerCampo) primerCampo.focus({ preventScroll: true });
  }

  function actualizarIndicador() {
    document.querySelectorAll('.paso-dot').forEach(dot => {
      const n = parseInt(dot.dataset.pasoDot, 10);
      dot.classList.toggle('paso-dot-activo', n === pasoActual);
      dot.classList.toggle('paso-dot-completo', n < pasoActual);
    });
    document.querySelectorAll('.paso-linea').forEach(linea => {
      const n = parseInt(linea.dataset.pasoLinea, 10);
      linea.classList.toggle('paso-linea-completa', n < pasoActual);
    });
    document.getElementById('pasoContador').textContent = `Paso ${pasoActual} de ${TOTAL_PASOS}`;
  }

  function actualizarBotones() {
    btnIzquierda.textContent = pasoActual === 1 ? 'Cancelar' : 'Atrás';
    if (pasoActual === TOTAL_PASOS) {
      iconoPasoDerecha.textContent = 'check';
      textoPasoDerecha.textContent = 'Confirmar reserva';
    } else {
      iconoPasoDerecha.textContent = 'arrow_forward';
      textoPasoDerecha.textContent = 'Continuar';
    }
  }

  function actualizarResumen() {
    const fecha    = document.getElementById('rFecha').value;
    const hora     = document.getElementById('rHora').value;
    const personas = document.getElementById('rPersonas').value;
    const telefono = document.getElementById('rTelefono').value.trim();

    const mesa = mesaSeleccionada();

    document.getElementById('resumenFecha').textContent = formatearFecha(fecha);
    document.getElementById('resumenHora').textContent  = hora ? `${hora} hs` : '—';
    document.getElementById('resumenMesa').textContent  = mesa ? `Mesa ${mesa.numero}` : '—';
    document.getElementById('resumenPersonas').textContent = personas;
    document.getElementById('resumenTelefono').textContent = telefono || '—';
  }

  function formatearFecha(fecha) {
    if (!fecha) return '—';
    const [anio, mes, dia] = fecha.split('-');
    return `${dia}/${mes}/${anio}`;
  }

  function mostrarExito(reserva) {
    form.hidden  = true;
    exito.hidden = false;

    const [anio, mes, dia] = reserva.fecha.split('-');
    document.getElementById('exitoCodigo').textContent   = reserva.codigo;
    document.getElementById('exitoFecha').textContent    = `${dia}/${mes}/${anio}`;
    document.getElementById('exitoHora').textContent     = `${reserva.hora}hs`;
    document.getElementById('exitoMesa').textContent     = reserva.mesa ? `Mesa ${reserva.mesa}` : '—';
    document.getElementById('exitoPersonas').textContent = reserva.personas;
    document.getElementById('exitoTelefono').textContent = reserva.telefono || '—';
    const cod = encodeURIComponent(reserva.codigo);
    document.getElementById('exitoPdf').href         = BASE + '/includes/comprobante_pdf.php?codigo=' + cod;
    document.getElementById('exitoCalendario').href  = BASE + '/api/reserva_ics.php?codigo=' + cod;
    document.getElementById('exitoComprobante').href = BASE + '/includes/comprobante.php?codigo=' + cod;
  }

  function limpiarErrores() {
    ['rErrorNombre', 'rErrorFecha', 'rErrorHora', 'rErrorMesa', 'rErrorPersonas', 'rErrorTelefono'].forEach(id => {
      document.getElementById(id).textContent = '';
    });
    form.querySelectorAll('.campo-wrapper-error').forEach(el => el.classList.remove('campo-wrapper-error'));
  }

  function marcarError(inputId, errorId, texto) {
    const el = document.getElementById(inputId);
    const wrapper = el.closest('.campo-input-wrapper');
    if (wrapper) wrapper.classList.add('campo-wrapper-error');
    document.getElementById(errorId).textContent = texto;
  }

  function setLoading(on) {
    btnDerecha.disabled = on;
    iconoPasoDerecha.textContent = on ? 'progress_activity' : 'check';
    iconoPasoDerecha.classList.toggle('icono-spin', on);
    textoPasoDerecha.textContent = on ? 'Reservando…' : 'Confirmar reserva';
  }

  function validar() {
    let ok = true;
    const nombre   = document.getElementById('rNombre').value.trim();
    const fecha    = document.getElementById('rFecha').value;
    const hora     = document.getElementById('rHora').value;
    const personas = parseInt(document.getElementById('rPersonas').value, 10);
    const telefono = document.getElementById('rTelefono').value.trim();

    if (!nombre) {
      marcarError('rNombre', 'rErrorNombre', 'El nombre es obligatorio.');
      ok = false;
    }
    if (!fecha) {
      marcarError('rFecha', 'rErrorFecha', 'Elegí una fecha.');
      ok = false;
    }
    if (!hora) {
      marcarError('rHora', 'rErrorHora', 'Elegí un horario.');
      ok = false;
    }
    if (!inputMesa.value) {
      marcarError('rMesa', 'rErrorMesa', 'Elegí una mesa del plano.');
      ok = false;
    }
    if (!personas || personas < 1 || personas > personasMax) {
      marcarError('rPersonas', 'rErrorPersonas', `Ingresá entre 1 y ${personasMax} personas.`);
      ok = false;
    }
    if (!telefono) {
      marcarError('rTelefono', 'rErrorTelefono', 'Ingresá un teléfono de contacto.');
      ok = false;
    } else if (!/^[0-9+()\s-]{6,25}$/.test(telefono)) {
      marcarError('rTelefono', 'rErrorTelefono', 'Ingresá un teléfono válido.');
      ok = false;
    }
    return ok;
  }

  function validarPaso(paso) {
    limpiarErrores();
    let ok = true;

    if (paso === 1) {
      if (!document.getElementById('rFecha').value) {
        marcarError('rFecha', 'rErrorFecha', 'Elegí una fecha.');
        ok = false;
      }
      if (!inputHora.value) {
        marcarError('rHora', 'rErrorHora', 'Elegí un horario.');
        ok = false;
      }
    } else if (paso === 2) {
      if (!inputMesa.value) {
        marcarError('rMesa', 'rErrorMesa', 'Elegí una mesa del plano.');
        ok = false;
      }
    }
    return ok;
  }

  form.addEventListener('submit', async e => {
    e.preventDefault();

    if (pasoActual < TOTAL_PASOS) {
      if (validarPaso(pasoActual)) irAPaso(pasoActual + 1);
      return;
    }

    limpiarErrores();
    if (!validar()) return;

    setLoading(true);

    const payload = {
      nombre:     document.getElementById('rNombre').value.trim(),
      fecha:      document.getElementById('rFecha').value,
      hora:       document.getElementById('rHora').value,
      mesa_id:    parseInt(inputMesa.value, 10) || 0,
      personas:   parseInt(document.getElementById('rPersonas').value, 10),
      comentario: document.getElementById('rComentario').value.trim(),
      telefono:   document.getElementById('rTelefono').value.trim(),
    };

    try {
      const res = await fetch(BASE + '/api/crear_reserva.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json;charset=utf-8',
        },
        body: JSON.stringify(payload),
      });
      const data = await res.json();

      if (data.ok) {
        setLoading(false);
        mostrarExito(data);
        form.reset();
        document.getElementById('rNombre').value = USUARIO_NOMBRE;
        document.getElementById('rTelefono').value = '';
        document.querySelectorAll('.horario-chip').forEach(c => c.classList.remove('horario-chip-activo'));
        inputMesa.value = '';
        planoData = null;
        mesaCapacidad = PERSONAS_MAX;
        personasMax = PERSONAS_MAX;
        setPersonas(2);
      } else if (data.errores) {
        const map = {
          nombre:   ['rNombre',   'rErrorNombre'],
          fecha:    ['rFecha',    'rErrorFecha'],
          hora:     ['rHora',     'rErrorHora'],
          mesa:     ['rMesa',     'rErrorMesa'],
          personas: ['rPersonas', 'rErrorPersonas'],
          telefono: ['rTelefono', 'rErrorTelefono'],
        };
        const campos = Object.keys(data.errores).filter(c => map[c]);
        const pasoConError = Math.min(...campos.map(c => PASO_DE_CAMPO[c] || TOTAL_PASOS));
        if (pasoConError < pasoActual) irAPaso(pasoConError);
        campos.forEach(campo => marcarError(map[campo][0], map[campo][1], data.errores[campo]));
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

  function mostrarToast(texto, tipo) {
    const t = document.createElement('div');
    t.className = 'toast toast-' + tipo;
    t.innerHTML = `<span class="material-symbols-outlined toast-icono">${tipo === 'exito' ? 'check_circle' : 'error'}</span><span>${texto}</span>`;
    document.body.appendChild(t);
    t.getBoundingClientRect();
    t.classList.add('toast-visible');
    setTimeout(() => {
      t.classList.remove('toast-visible');
      t.addEventListener('transitionend', () => t.remove(), { once: true });
    }, 3500);
  }
})();
