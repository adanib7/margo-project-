<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/check_auth.php';
require_once '../includes/plano_db.php';
requireRole('admin', 'superadmin');
$pageTitle = 'Panel de Administración';
$pageCSS = '../assets/css/dashboard.css';
$showDashboardBottomNav = true;

// Mesas disponibles para el alta rápida
$mesasPlano = [];
if ($conn !== null && planoLigadoAReservas($conn)) {
    $res = $conn->query("SELECT id, numero, capacidad FROM mesas ORDER BY numero ASC");
    if ($res) {
        while ($m = $res->fetch_assoc()) {
            $mesasPlano[] = $m;
        }
    }
}

$franjasHorarias = horarioFranjas();

require_once '../includes/header.php';
?>
<?php require_once '../includes/nav.php'; ?>

<main class="contenido-principal">
  <header class="seccion-encabezado">
    <div>
      <h1 class="titulo-pagina">¡Bienvenido, Admin!</h1>
      <p class="subtitulo-pagina">Resumen y acceso rápido a la gestión del restaurante.</p>
    </div>
    <div class="resumen-rapido">
      <div class="icono">
        <span class="material-symbols-outlined icon-fill">event_available</span>
      </div>
    </div>
  </header>

  <div class="grilla-tarjetas">
    <a class="tarjeta" href="<?= buildUrl("/dashboards/reservas.php") ?>">
      <div class="tarjeta-overlay"></div>
      <div class="tarjeta-cabecera">
        <div class="icono icono-primary">
          <span class="material-symbols-outlined">event_available</span>
        </div>
        <h2>Reservaciones</h2>
      </div>
      <p class="tarjeta-texto">Gestiona las reservas de mesas, confirmaciones y listas de espera para el día.</p>
    </a>

    <a class="tarjeta" href="<?= buildUrl("/dashboards/admin_plano.php") ?>">
      <div class="tarjeta-overlay"></div>
      <div class="tarjeta-cabecera">
        <div class="icono">
          <span class="material-symbols-outlined">grid_view</span>
        </div>
        <h2>Plano de Mesas</h2>
      </div>
      <p class="tarjeta-texto">Visualiza y organiza la disposición del comedor y la asignación de mesas.</p>
    </a>

    <a class="tarjeta" href="<?= buildUrl('/dashboards/inventario.php') ?>">
      <div class="tarjeta-overlay"></div>
      <div class="tarjeta-cabecera">
        <div class="icono">
          <span class="material-symbols-outlined">inventory_2</span>
        </div>
        <h2>Inventario</h2>
      </div>
      <p class="tarjeta-texto">Controla el stock de ingredientes, vinos y suministros esenciales.</p>
    </a>

    <a class="tarjeta" href="<?= buildUrl('/dashboards/configuracion.php') ?>">
      <div class="tarjeta-overlay"></div>
      <div class="tarjeta-cabecera">
        <div class="icono">
          <span class="material-symbols-outlined">settings</span>
        </div>
        <h2>Configuración</h2>
      </div>
      <p class="tarjeta-texto">Ajustes del sistema, gestión de usuarios y preferencias del restaurante.</p>
    </a>
  </div>

  <div class="area-accion">
    <button class="boton-accion" id="btnNuevaReserva">
      <span class="material-symbols-outlined">add</span>
      Nueva Reserva Rápida
    </button>
  </div>
</main>

<!-- ══════════════════════════════════════
     MODAL: Nueva reserva rápida (teléfono / mostrador)
════════════════════════════════════════ -->
<div class="modal-fondo" id="modalRapida" role="dialog" aria-modal="true" aria-labelledby="modalRapidaTitulo">
  <div class="modal-contenedor">
    <div class="modal-encabezado">
      <div class="modal-icono"><span class="material-symbols-outlined">phone_in_talk</span></div>
      <div>
        <h2 class="modal-titulo" id="modalRapidaTitulo">Nueva reserva rápida</h2>
        <p class="modal-subtitulo">Para reservas por teléfono o en el mostrador.</p>
      </div>
      <button class="modal-cerrar" id="btnCerrarRapida" aria-label="Cerrar">
        <span class="material-symbols-outlined">close</span>
      </button>
    </div>

    <form class="modal-form" id="formRapida" novalidate>

      <div class="inv-form-fila">
        <div class="campo-grupo">
          <label class="campo-etiqueta" for="qNombre">Nombre del cliente</label>
          <div class="campo-input-wrapper">
            <span class="campo-icono material-symbols-outlined">person</span>
            <input class="campo-input" type="text" id="qNombre" maxlength="255" required>
          </div>
          <span class="campo-error" id="qErrorNombre"></span>
        </div>

        <div class="campo-grupo">
          <label class="campo-etiqueta" for="qTelefono">Teléfono</label>
          <div class="campo-input-wrapper">
            <span class="campo-icono material-symbols-outlined">phone</span>
            <input class="campo-input" type="tel" id="qTelefono" maxlength="50" required>
          </div>
          <span class="campo-error" id="qErrorTelefono"></span>
        </div>
      </div>

      <div class="campo-grupo">
        <label class="campo-etiqueta" for="qEmail">Correo <span class="campo-opcional">(opcional)</span></label>
        <div class="campo-input-wrapper">
          <span class="campo-icono material-symbols-outlined">mail</span>
          <input class="campo-input" type="email" id="qEmail" maxlength="120" placeholder="Para enviarle la confirmación">
        </div>
        <span class="campo-ayuda">Si coincide con una cuenta registrada, la reserva le aparece en «Mis reservas».</span>
        <span class="campo-error" id="qErrorEmail"></span>
      </div>

      <div class="inv-form-fila">
        <div class="campo-grupo">
          <label class="campo-etiqueta" for="qFecha">Fecha</label>
          <div class="campo-input-wrapper">
            <span class="campo-icono material-symbols-outlined">calendar_month</span>
            <input class="campo-input" type="date" id="qFecha" required>
          </div>
          <span class="campo-error" id="qErrorFecha"></span>
        </div>

        <div class="campo-grupo">
          <label class="campo-etiqueta" for="qHora">Hora</label>
          <div class="campo-input-wrapper">
            <span class="campo-icono material-symbols-outlined">schedule</span>
            <select class="campo-input campo-select" id="qHora" required>
              <?php foreach ($franjasHorarias as $franja => $horas): ?>
                <optgroup label="<?= htmlspecialchars($franja, ENT_QUOTES) ?>">
                  <?php foreach ($horas as $h): ?>
                    <option value="<?= $h ?>"><?= $h ?></option>
                  <?php endforeach; ?>
                </optgroup>
              <?php endforeach; ?>
            </select>
          </div>
          <span class="campo-error" id="qErrorHora"></span>
        </div>
      </div>

      <div class="inv-form-fila">
        <div class="campo-grupo">
          <label class="campo-etiqueta" for="qPersonas">Comensales</label>
          <div class="campo-input-wrapper">
            <span class="campo-icono material-symbols-outlined">group</span>
            <input class="campo-input" type="number" id="qPersonas" min="1" max="<?= cfgInt('reservas.max_personas') ?>" value="2" required>
          </div>
          <span class="campo-error" id="qErrorPersonas"></span>
        </div>

        <div class="campo-grupo">
          <label class="campo-etiqueta" for="qMesa">Mesa <span class="campo-opcional">(opcional)</span></label>
          <div class="campo-input-wrapper">
            <span class="campo-icono material-symbols-outlined">table_restaurant</span>
            <select class="campo-input campo-select" id="qMesa" <?= $mesasPlano ? '' : 'disabled' ?>>
              <option value="0">Sin asignar</option>
              <?php foreach ($mesasPlano as $m): ?>
                <option value="<?= (int) $m['id'] ?>">Mesa <?= (int) $m['numero'] ?> · hasta <?= (int) $m['capacidad'] ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <span class="campo-error" id="qErrorMesa"></span>
        </div>
      </div>

      <div class="campo-grupo">
        <label class="campo-etiqueta" for="qComentario">Nota <span class="campo-opcional">(opcional)</span></label>
        <textarea class="campo-textarea" id="qComentario" rows="2" maxlength="500"
                  placeholder="Ej. alergias, cumpleaños, mesa junto a la ventana…"></textarea>
      </div>

      <label class="res-check" id="qBloqueAvisar">
        <input type="checkbox" id="qAvisar">
        <span>Enviar confirmación por correo</span>
      </label>

      <div class="modal-acciones">
        <button type="button" class="boton-secundario" id="btnCancelarRapida">Cancelar</button>
        <button type="submit" class="boton-accion boton-modal-submit" id="btnSubmitRapida">
          <span class="material-symbols-outlined">check</span>
          <span>Crear reserva</span>
        </button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  const BASE = '<?= BASE_URL ?>';
  const $ = id => document.getElementById(id);

  const modal  = $('modalRapida');
  const form   = $('formRapida');
  const btnSub = $('btnSubmitRapida');

  const CAMPOS = {
    nombre:   ['qNombre',   'qErrorNombre'],
    telefono: ['qTelefono', 'qErrorTelefono'],
    email:    ['qEmail',    'qErrorEmail'],
    fecha:    ['qFecha',    'qErrorFecha'],
    hora:     ['qHora',     'qErrorHora'],
    personas: ['qPersonas', 'qErrorPersonas'],
    mesa:     ['qMesa',     'qErrorMesa'],
  };

  function esc(s) {
    return String(s ?? '')
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function limpiarErrores() {
    Object.values(CAMPOS).forEach(p => $(p[1]).textContent = '');
    modal.querySelectorAll('.campo-wrapper-error').forEach(el => el.classList.remove('campo-wrapper-error'));
  }

  function marcarError(campo, texto) {
    const p = CAMPOS[campo];
    if (!p) return;
    const w = $(p[0]).closest('.campo-input-wrapper');
    if (w) w.classList.add('campo-wrapper-error');
    $(p[1]).textContent = texto;
  }

  function setLoading(on) {
    btnSub.disabled = on;
    const ic = btnSub.querySelector('.material-symbols-outlined');
    ic.textContent = on ? 'progress_activity' : 'check';
    ic.classList.toggle('icono-spin', on);
  }

  function abrir() {
    form.reset();
    limpiarErrores();
    setLoading(false);
    $('qFecha').value = new Date().toISOString().split('T')[0];
    $('qPersonas').value = 2;
    sincronizarAvisar();
    modal.classList.add('modal-visible');
    document.body.classList.add('modal-abierto');
    $('qNombre').focus();
  }

  function cerrar() {
    modal.classList.remove('modal-visible');
    document.body.classList.remove('modal-abierto');
    limpiarErrores();
    setLoading(false);
  }

  // El aviso por correo solo tiene sentido si hay un correo cargado.
  function sincronizarAvisar() {
    const hay = $('qEmail').value.trim() !== '';
    $('qAvisar').disabled = !hay;
    if (!hay) $('qAvisar').checked = false;
    $('qBloqueAvisar').style.opacity = hay ? '1' : '.5';
  }
  $('qEmail').addEventListener('input', sincronizarAvisar);

  $('btnNuevaReserva').addEventListener('click', abrir);
  $('btnCerrarRapida').addEventListener('click', cerrar);
  $('btnCancelarRapida').addEventListener('click', cerrar);
  modal.addEventListener('click', e => { if (e.target === modal) cerrar(); });
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && modal.classList.contains('modal-visible')) cerrar();
  });

  form.addEventListener('submit', async e => {
    e.preventDefault();
    limpiarErrores();

    const payload = {
      nombre:     $('qNombre').value.trim(),
      telefono:   $('qTelefono').value.trim(),
      email:      $('qEmail').value.trim(),
      fecha:      $('qFecha').value,
      hora:       $('qHora').value,
      personas:   parseInt($('qPersonas').value, 10) || 0,
      mesa_id:    parseInt($('qMesa').value, 10) || 0,
      comentario: $('qComentario').value.trim(),
      avisar:     $('qAvisar').checked,
    };

    if (!payload.nombre)   { marcarError('nombre', 'El nombre es obligatorio.'); return; }
    if (!payload.telefono) { marcarError('telefono', 'El teléfono es obligatorio.'); return; }
    if (!payload.fecha)    { marcarError('fecha', 'Elegí una fecha.'); return; }
    if (!payload.hora)     { marcarError('hora', 'Elegí un horario.'); return; }

    setLoading(true);
    try {
      const res  = await fetch(BASE + '/api/crear_reserva_admin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const data = await res.json();

      if (data.ok) {
        cerrar();
        mostrarToast(data.mensaje, 'exito');
      } else if (data.errores) {
        Object.keys(data.errores).forEach(c => marcarError(c, data.errores[c]));
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
    t.innerHTML = '<span class="material-symbols-outlined toast-icono">'
      + (tipo === 'exito' ? 'check_circle' : 'error') + '</span><span>' + esc(texto) + '</span>';
    document.body.appendChild(t);
    t.getBoundingClientRect();
    t.classList.add('toast-visible');
    setTimeout(() => {
      t.classList.remove('toast-visible');
      t.addEventListener('transitionend', () => t.remove(), { once: true });
    }, 4500);
  }
})();
</script>

<?php require_once '../includes/footer.php'; ?>
