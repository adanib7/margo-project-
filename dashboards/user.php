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

    <a class="tarjeta" href="#">
      <div class="tarjeta-overlay"></div>
      <div class="tarjeta-cabecera">
        <div class="icono icono-primary">
         <span class="material-symbols-outlined">event</span>
        </div>
        <h2>Mis Reservas</h2>
      </div>
      <p class="tarjeta-texto">Revisa tus reservas.</p>
    </a>

    <a class="tarjeta" href="#">
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
  <div class="modal-contenedor">
    <div class="modal-encabezado">
      <div class="modal-icono" id="modalReservaIcono">
        <span class="material-symbols-outlined">table_restaurant</span>
      </div>
      <div>
        <h2 class="modal-titulo" id="modalReservaTitulo">Reservar mesa</h2>
        <p class="modal-subtitulo">Completá los datos y te confirmamos la reserva.</p>
      </div>
      <button class="modal-cerrar" id="btnCerrarModalReserva" aria-label="Cerrar">
        <span class="material-symbols-outlined">close</span>
      </button>
    </div>

    <form class="modal-form" id="formReserva" novalidate>
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
        <label class="campo-etiqueta" for="rFecha">Fecha</label>
        <div class="campo-input-wrapper">
          <span class="campo-icono material-symbols-outlined">calendar_month</span>
          <input class="campo-input" type="date" id="rFecha" name="fecha" required>
        </div>
        <span class="campo-error" id="rErrorFecha"></span>
      </div>

      <div class="campo-grupo">
        <label class="campo-etiqueta" for="rHora">Horario</label>
        <div class="campo-input-wrapper">
          <span class="campo-icono material-symbols-outlined">schedule</span>
          <select class="campo-input campo-select" id="rHora" name="hora" required>
            <option value="" disabled selected>Elegí un horario</option>
            <?php foreach ($franjasHorarias as $franja => $horas): ?>
              <optgroup label="<?= htmlspecialchars($franja, ENT_QUOTES, 'UTF-8') ?>">
                <?php foreach ($horas as $hora): ?>
                  <option value="<?= $hora ?>"><?= $hora ?> hs</option>
                <?php endforeach; ?>
              </optgroup>
            <?php endforeach; ?>
          </select>
        </div>
        <span class="campo-error" id="rErrorHora"></span>
      </div>

      <div class="campo-grupo">
        <label class="campo-etiqueta" for="rPersonas">Cantidad de personas</label>
        <div class="campo-input-wrapper">
          <span class="campo-icono material-symbols-outlined">groups</span>
          <input class="campo-input" type="number" id="rPersonas" name="personas" min="1" max="20" value="2" required>
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

      <div class="modal-acciones">
        <button type="button" class="boton-secundario" id="btnCancelarModalReserva">Cancelar</button>
        <button type="submit" class="boton-accion boton-modal-submit" id="btnSubmitReserva">
          <span class="material-symbols-outlined">check</span>
          <span id="btnSubmitReservaTexto">Confirmar reserva</span>
        </button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  const BASE = '<?= BASE_URL ?>';

  const btnAbrir   = document.getElementById('btnAbrirReserva');
  const modal      = document.getElementById('modalReserva');
  const form       = document.getElementById('formReserva');
  const btnSubmit  = document.getElementById('btnSubmitReserva');
  const inputFecha = document.getElementById('rFecha');

  inputFecha.min = new Date().toISOString().split('T')[0];

  btnAbrir.addEventListener('click', e => {
    e.preventDefault();
    abrirModal();
  });

  document.getElementById('btnCerrarModalReserva').addEventListener('click', cerrarModal);
  document.getElementById('btnCancelarModalReserva').addEventListener('click', cerrarModal);
  modal.addEventListener('click', e => { if (e.target === modal) cerrarModal(); });
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && modal.classList.contains('modal-visible')) cerrarModal();
  });

  function abrirModal() {
    modal.classList.add('modal-visible');
    document.body.classList.add('modal-abierto');
    document.getElementById('rNombre').focus();
  }

  function cerrarModal() {
    modal.classList.remove('modal-visible');
    document.body.classList.remove('modal-abierto');
    limpiarErrores();
    setLoading(false);
  }

  function limpiarErrores() {
    ['rErrorNombre', 'rErrorFecha', 'rErrorHora', 'rErrorPersonas'].forEach(id => {
      document.getElementById(id).textContent = '';
    });
    form.querySelectorAll('.campo-wrapper-error').forEach(el => el.classList.remove('campo-wrapper-error'));
  }

  function marcarError(inputId, errorId, texto) {
    const el = document.getElementById(inputId);
    el.closest('.campo-input-wrapper').classList.add('campo-wrapper-error');
    document.getElementById(errorId).textContent = texto;
  }

  function setLoading(on) {
    btnSubmit.disabled = on;
    const icono = btnSubmit.querySelector('.material-symbols-outlined');
    icono.textContent = on ? 'progress_activity' : 'check';
    icono.classList.toggle('icono-spin', on);
    document.getElementById('btnSubmitReservaTexto').textContent = on ? 'Reservando…' : 'Confirmar reserva';
  }

  function validar() {
    let ok = true;
    const nombre   = document.getElementById('rNombre').value.trim();
    const fecha    = document.getElementById('rFecha').value;
    const hora     = document.getElementById('rHora').value;
    const personas = parseInt(document.getElementById('rPersonas').value, 10);

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
    return ok;
  }

  form.addEventListener('submit', async e => {
    e.preventDefault();
    limpiarErrores();
    if (!validar()) return;

    setLoading(true);

    const payload = {
      nombre:     document.getElementById('rNombre').value.trim(),
      fecha:      document.getElementById('rFecha').value,
      hora:       document.getElementById('rHora').value,
      personas:   parseInt(document.getElementById('rPersonas').value, 10),
      comentario: document.getElementById('rComentario').value.trim(),
    };

    try {
      const res  = await fetch(BASE + '/api/crear_reserva.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify(payload),
      });
      const data = await res.json();

      if (data.ok) {
        cerrarModal();
        mostrarToast(data.mensaje, 'exito');
        form.reset();
        document.getElementById('rNombre').value = <?= json_encode($_SESSION['usuario_logueado']) ?>;
        document.getElementById('rPersonas').value = 2;
      } else if (data.errores) {
        const map = {
          nombre:   ['rNombre',   'rErrorNombre'],
          fecha:    ['rFecha',    'rErrorFecha'],
          hora:     ['rHora',     'rErrorHora'],
          personas: ['rPersonas', 'rErrorPersonas'],
        };
        Object.entries(data.errores).forEach(([campo, msg]) => {
          if (map[campo]) marcarError(map[campo][0], map[campo][1], msg);
        });
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
