<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/check_auth.php';
requireLogin();
$pageTitle = 'Dashboard de Usuario';
$pageCSS = '../assets/css/dashboard.css';
$showDashboardBottomNav = true;
require_once '../includes/header.php';

$franjasHorarias = [
    'Almuerzo' => ['12:00', '12:30', '13:00', '13:30', '14:00', '14:30', '15:00'],
    'Cena'     => ['20:00', '20:30', '21:00', '21:30', '22:00', '22:30', '23:00'],
];
?>
<?php require_once '../includes/nav.php'; ?>

<main class="contenido-principal">
  <header class="seccion-encabezado">
    <div>
      <h1 class="titulo-pagina">¡Bienvenido, <?= htmlspecialchars($_SESSION['usuario_logueado'], ENT_QUOTES, 'UTF-8') ?>!</h1>
      <p class="subtitulo-pagina">Gestiona tus reservas y tu información de cuenta.</p>
    </div>
  </header>

  <div class="grilla-tarjetas">
    <a class="tarjeta tarjeta-destacada" href="#" id="btnAbrirReserva">
      <div class="tarjeta-overlay"></div>
      <div class="tarjeta-cabecera">
        <div class="icono icono-destacado">
         <span class="material-symbols-outlined">table_restaurant</span>
        </div>
        <h2>Reservar Mesa</h2>
      </div>
      <p class="tarjeta-texto">Elegí el día, el horario y la cantidad de personas para tu reserva.</p>
      <span class="tarjeta-cta">Reservar ahora <span class="material-symbols-outlined">arrow_forward</span></span>
    </a>

    <a class="tarjeta" href="<?= buildUrl('/dashboards/mis-reservas.php') ?>">
      <div class="tarjeta-overlay"></div>
      <div class="tarjeta-cabecera">
        <div class="icono icono-primary">
         <span class="material-symbols-outlined">event</span>
        </div>
        <h2>Mis Reservas</h2>
      </div>
      <p class="tarjeta-texto">Revisa tus reservas.</p>
    </a>

    <a class="tarjeta" href="<?= buildUrl('/dashboards/mi_perfil.php') ?>">
      <div class="tarjeta-overlay"></div>
      <div class="tarjeta-cabecera">
        <div class="icono">
          <span class="material-symbols-outlined">person</span>
        </div>
        <h2>Mi Perfil</h2>
      </div>
      <p class="tarjeta-texto">Actualiza tus datos y revisa tu información de cuenta.</p>
    </a>
  </div>
</main>

<!-- ══════════════════════════════════════
     MODAL: Reservar mesa
════════════════════════════════════════ -->
<div class="modal-fondo" id="modalReserva" role="dialog" aria-modal="true" aria-labelledby="modalReservaTitulo">
  <div class="modal-contenedor modal-contenedor-reserva">
    <div class="modal-encabezado modal-encabezado-reserva">
      <button class="modal-cerrar modal-cerrar-reserva" id="btnCerrarModalReserva" aria-label="Cerrar">
        <span class="material-symbols-outlined">close</span>
      </button>
      <div class="modal-icono-reserva">
        <span class="material-symbols-outlined">table_restaurant</span>
      </div>
      <h2 class="modal-titulo-reserva" id="modalReservaTitulo">Reservar mesa</h2>
      <p class="modal-subtitulo-reserva">Elegí el día, el horario y te guardamos el lugar.</p>

      <div class="pasos-indicador" aria-hidden="true">
        <div class="paso-dot" data-paso-dot="1"><span>1</span></div>
        <div class="paso-linea" data-paso-linea="1"></div>
        <div class="paso-dot" data-paso-dot="2"><span>2</span></div>
        <div class="paso-linea" data-paso-linea="2"></div>
        <div class="paso-dot" data-paso-dot="3"><span>3</span></div>
      </div>
      <p class="paso-contador" id="pasoContador">Paso 1 de 3</p>
    </div>

    <form class="modal-form modal-form-pasos" id="formReserva" novalidate>

      <!-- Paso 1: Cuándo -->
      <div class="modal-paso modal-paso-activo" data-paso="1">
        <h3 class="paso-titulo">¿Cuándo querés venir?</h3>

        <div class="campo-grupo">
          <label class="campo-etiqueta" for="rFecha">Fecha</label>
          <div class="campo-input-wrapper">
            <span class="campo-icono material-symbols-outlined">calendar_month</span>
            <input class="campo-input" type="date" id="rFecha" name="fecha" required>
          </div>
          <span class="campo-error" id="rErrorFecha"></span>
        </div>

        <div class="campo-grupo">
          <label class="campo-etiqueta">Horario</label>
          <input type="hidden" id="rHora" name="hora" required>
          <?php foreach ($franjasHorarias as $franja => $horas): ?>
            <div class="horario-grupo">
              <span class="horario-grupo-titulo"><?= htmlspecialchars($franja, ENT_QUOTES, 'UTF-8') ?></span>
              <div class="horario-chips">
                <?php foreach ($horas as $hora): ?>
                  <button type="button" class="horario-chip" data-hora="<?= $hora ?>"><?= $hora ?></button>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
          <span class="campo-error" id="rErrorHora"></span>
        </div>
      </div>

      <!-- Paso 2: Cuántos -->
      <div class="modal-paso" data-paso="2">
        <h3 class="paso-titulo">¿Cuántos son?</h3>

        <div class="campo-grupo">
          <label class="campo-etiqueta">Cantidad de personas</label>
          <input type="hidden" id="rPersonas" name="personas" value="2" required>
          <div class="stepper-personas">
            <button type="button" class="stepper-btn" id="btnPersonasMenos" aria-label="Menos personas">
              <span class="material-symbols-outlined">remove</span>
            </button>
            <div class="stepper-valor">
              <span id="personasValor">2</span>
              <span class="stepper-sub">personas</span>
            </div>
            <button type="button" class="stepper-btn" id="btnPersonasMas" aria-label="Más personas">
              <span class="material-symbols-outlined">add</span>
            </button>
          </div>
          <span class="campo-error" id="rErrorPersonas"></span>
        </div>

        <div class="campo-grupo">
          <label class="campo-etiqueta" for="rComentario">
            Pedido especial <span class="campo-opcional">(opcional)</span>
          </label>
          <textarea class="campo-textarea" id="rComentario" name="comentario" rows="2"
                    placeholder="Ej. mesa junto a la ventana, cumpleaños, etc."></textarea>
        </div>
      </div>

      <!-- Paso 3: Confirmá -->
      <div class="modal-paso" data-paso="3">
        <h3 class="paso-titulo">Confirmá tu reserva</h3>

        <div class="campo-grupo">
          <label class="campo-etiqueta" for="rNombre">Nombre completo</label>
          <div class="campo-input-wrapper">
            <span class="campo-icono material-symbols-outlined">person</span>
            <input class="campo-input" type="text" id="rNombre" name="nombre"
                   value="<?= htmlspecialchars($_SESSION['usuario_logueado'], ENT_QUOTES, 'UTF-8') ?>" required>
          </div>
          <span class="campo-error" id="rErrorNombre"></span>
        </div>

        <div class="campo-grupo">
          <label class="campo-etiqueta" for="rTelefono">Teléfono de contacto</label>
          <div class="campo-input-wrapper">
            <span class="campo-icono material-symbols-outlined">phone</span>
            <input class="campo-input" type="tel" id="rTelefono" name="telefono" placeholder="Ej. +34 600 123 456" required>
          </div>
          <span class="campo-error" id="rErrorTelefono"></span>
        </div>

        <div class="resumen-reserva">
          <div class="resumen-fila"><span>Fecha</span><strong id="resumenFecha">—</strong></div>
          <div class="resumen-fila"><span>Hora</span><strong id="resumenHora">—</strong></div>
          <div class="resumen-fila"><span>Personas</span><strong id="resumenPersonas">—</strong></div>
          <div class="resumen-fila"><span>Teléfono</span><strong id="resumenTelefono">—</strong></div>
        </div>
      </div>

      <div class="modal-acciones modal-acciones-pasos">
        <button type="button" class="boton-secundario" id="btnPasoIzquierda">Cancelar</button>
        <button type="submit" class="boton-accion boton-modal-submit" id="btnPasoDerecha">
          <span class="material-symbols-outlined" id="iconoPasoDerecha">arrow_forward</span>
          <span id="textoPasoDerecha">Continuar</span>
        </button>
      </div>
    </form>

    <div class="modal-exito" id="reservaExito" hidden>
      <div class="modal-exito-icono">
        <span class="material-symbols-outlined">check_circle</span>
      </div>
      <h3 class="modal-exito-titulo">¡Reserva confirmada!</h3>
      <p class="modal-exito-texto">Guardá el código, te va a servir el día de tu visita.</p>
      <div class="modal-exito-codigo" id="exitoCodigo"></div>

      <div class="modal-exito-datos">
        <div class="dato"><span class="etiqueta">Fecha</span><span class="valor" id="exitoFecha"></span></div>
        <div class="dato"><span class="etiqueta">Hora</span><span class="valor" id="exitoHora"></span></div>
        <div class="dato"><span class="etiqueta">Personas</span><span class="valor" id="exitoPersonas"></span></div>
        <div class="dato"><span class="etiqueta">Teléfono</span><span class="valor" id="exitoTelefono"></span></div>
      </div>

      <div class="modal-exito-acciones">
        <a class="boton-secundario" id="exitoCalendario" href="#" target="_blank" rel="noopener">
          <span class="material-symbols-outlined">event</span> Agregar al calendario
        </a>
        <a class="boton-accion" id="exitoComprobante" href="#" target="_blank" rel="noopener">
          <span class="material-symbols-outlined">receipt_long</span> Ver comprobante
        </a>
      </div>

      <button type="button" class="boton-secundario modal-exito-cerrar" id="btnCerrarExito">Cerrar</button>
    </div>
  </div>
</div>

<script>
(function () {
  const BASE = '<?= BASE_URL ?>';

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
  const PERSONAS_MIN = 1;
  const PERSONAS_MAX = 20;
  const TOTAL_PASOS = 3;
  let pasoActual = 1;

  const PASO_DE_CAMPO = {
    fecha: 1, hora: 1,
    personas: 2,
    telefono: 3,
    nombre: 3,
  };

  inputFecha.min = new Date().toISOString().split('T')[0];
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
    valor = Math.min(PERSONAS_MAX, Math.max(PERSONAS_MIN, valor));
    inputPersonas.value = valor;
    personasValor.textContent = valor;
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
    actualizarIndicador();
    actualizarBotones();
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
    if (pasoActual === 3) actualizarResumen();

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

    document.getElementById('resumenFecha').textContent = formatearFecha(fecha);
    document.getElementById('resumenHora').textContent  = hora ? `${hora} hs` : '—';
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
    document.getElementById('exitoPersonas').textContent = reserva.personas;
    document.getElementById('exitoTelefono').textContent = reserva.telefono || '—';
    document.getElementById('exitoCalendario').href = BASE + '/api/reserva_ics.php?codigo=' + encodeURIComponent(reserva.codigo);
    document.getElementById('exitoComprobante').href = BASE + '/comprobante.php?codigo=' + encodeURIComponent(reserva.codigo);
  }

  function limpiarErrores() {
    ['rErrorNombre', 'rErrorFecha', 'rErrorHora', 'rErrorPersonas', 'rErrorTelefono'].forEach(id => {
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
    if (!personas || personas < 1 || personas > 20) {
      marcarError('rPersonas', 'rErrorPersonas', 'Ingresá entre 1 y 20 personas.');
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
    } else if (paso === 3) {
      if (!document.getElementById('rNombre').value.trim()) {
        marcarError('rNombre', 'rErrorNombre', 'El nombre es obligatorio.');
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
      personas:   parseInt(document.getElementById('rPersonas').value, 10),
      comentario: document.getElementById('rComentario').value.trim(),
      telefono:   document.getElementById('rTelefono').value.trim(),
        body:    JSON.stringify(payload),
      });
      const data = await res.json();

      if (data.ok) {
        setLoading(false);
        mostrarExito(data);
        form.reset();
        document.getElementById('rNombre').value = <?= json_encode($_SESSION['usuario_logueado']) ?>;
        document.getElementById('rTelefono').value = '';
        document.querySelectorAll('.horario-chip').forEach(c => c.classList.remove('horario-chip-activo'));
        setPersonas(2);
      } else if (data.errores) {
        const map = {
          nombre:   ['rNombre',   'rErrorNombre'],
          fecha:    ['rFecha',    'rErrorFecha'],
          hora:     ['rHora',     'rErrorHora'],
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
</script>

<?php require_once '../includes/footer.php'; ?>
