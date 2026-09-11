<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/check_auth.php';
require_once '../includes/config_app.php';
requireRole('admin', 'superadmin');

$pageTitle = 'Configuración';
$pageCSS = '../assets/css/dashboard.css';
$showDashboardBottomNav = true;

$esSuperadmin = ($_SESSION['rol'] ?? '') === 'superadmin';
$volverUrl = $esSuperadmin
    ? buildUrl('/dashboards/superadmin.php')
    : buildUrl('/dashboards/admin.php');

// La tabla se crea al entrar; si el hosting no deja, se sigue con los defaults.
$sinBase = ($conn === null);
if (!$sinBase) {
    ensureConfiguracionTable($conn);
}

$diasCerrados   = array_map('intval', cfgArray('horario.dias_cerrados'));
$fechasCerradas = cfgArray('horario.fechas_cerradas');

require_once '../includes/header.php';
?>
<?php require_once '../includes/nav.php'; ?>

<main class="contenido-principal">

  <header class="seccion-encabezado">
    <div>
      <a href="<?= $volverUrl ?>" class="enlace-volver">
        <span class="material-symbols-outlined">arrow_back</span>
        Panel principal
      </a>
      <h1 class="titulo-pagina" style="margin-top:.5rem">Configuración</h1>
      <p class="subtitulo-pagina">Horarios de servicio y reglas de reserva del local.</p>
    </div>
  </header>

  <?php if ($sinBase): ?>
    <div class="gu-estado">
      <span class="material-symbols-outlined" style="font-size:2.5rem;color:#b91c1c">error</span>
      <p>No se pudo conectar con la base de datos.</p>
    </div>
  <?php else: ?>

  <form id="formConfig">

    <!-- ══════════ Datos del local ══════════ -->
    <section class="cfg-bloque">
      <div class="cfg-bloque-cab">
        <div class="icono"><span class="material-symbols-outlined">storefront</span></div>
        <div>
          <h2 class="cfg-bloque-titulo">Datos del local</h2>
          <p class="cfg-bloque-texto">Se usan en la web, los comprobantes y los correos.</p>
        </div>
      </div>

      <div class="cfg-grid">
        <div class="campo-grupo">
          <label class="campo-etiqueta" for="locNombre">Nombre</label>
          <div class="campo-input-wrapper">
            <span class="campo-icono material-symbols-outlined">restaurant</span>
            <input class="campo-input" type="text" id="locNombre" maxlength="80"
                   value="<?= htmlspecialchars((string) cfg('local.nombre'), ENT_QUOTES) ?>">
          </div>
          <span class="campo-error" id="err_local.nombre"></span>
        </div>

        <div class="campo-grupo">
          <label class="campo-etiqueta" for="locEslogan">Bajada <span class="campo-opcional">(opcional)</span></label>
          <div class="campo-input-wrapper">
            <span class="campo-icono material-symbols-outlined">notes</span>
            <input class="campo-input" type="text" id="locEslogan" maxlength="120"
                   value="<?= htmlspecialchars((string) cfg('local.eslogan'), ENT_QUOTES) ?>">
          </div>
          <span class="campo-ayuda">Aparece bajo el nombre en el comprobante.</span>
        </div>

        <div class="campo-grupo cfg-col-entera">
          <label class="campo-etiqueta" for="locDireccion">Calle y número</label>
          <div class="campo-input-wrapper">
            <span class="campo-icono material-symbols-outlined">location_on</span>
            <input class="campo-input" type="text" id="locDireccion" maxlength="120"
                   value="<?= htmlspecialchars((string) cfg('local.direccion'), ENT_QUOTES) ?>">
          </div>
          <span class="campo-error" id="err_local.direccion"></span>
        </div>

        <div class="campo-grupo">
          <label class="campo-etiqueta" for="locCp">Código postal</label>
          <div class="campo-input-wrapper">
            <span class="campo-icono material-symbols-outlined">markunread_mailbox</span>
            <input class="campo-input" type="text" id="locCp" maxlength="10"
                   value="<?= htmlspecialchars((string) cfg('local.cp'), ENT_QUOTES) ?>">
          </div>
          <span class="campo-error" id="err_local.cp"></span>
        </div>

        <div class="campo-grupo">
          <label class="campo-etiqueta" for="locCiudad">Localidad</label>
          <div class="campo-input-wrapper">
            <span class="campo-icono material-symbols-outlined">apartment</span>
            <input class="campo-input" type="text" id="locCiudad" maxlength="80"
                   value="<?= htmlspecialchars((string) cfg('local.ciudad'), ENT_QUOTES) ?>">
          </div>
          <span class="campo-error" id="err_local.ciudad"></span>
        </div>

        <div class="campo-grupo">
          <label class="campo-etiqueta" for="locProvincia">Provincia <span class="campo-opcional">(opcional)</span></label>
          <div class="campo-input-wrapper">
            <span class="campo-icono material-symbols-outlined">map</span>
            <input class="campo-input" type="text" id="locProvincia" maxlength="80"
                   value="<?= htmlspecialchars((string) cfg('local.provincia'), ENT_QUOTES) ?>">
          </div>
        </div>

        <div class="campo-grupo">
          <label class="campo-etiqueta" for="locTelefono">Teléfono</label>
          <div class="campo-input-wrapper">
            <span class="campo-icono material-symbols-outlined">phone</span>
            <input class="campo-input" type="tel" id="locTelefono" maxlength="30"
                   value="<?= htmlspecialchars((string) cfg('local.telefono'), ENT_QUOTES) ?>">
          </div>
          <span class="campo-error" id="err_local.telefono"></span>
        </div>

        <div class="campo-grupo">
          <label class="campo-etiqueta" for="locEmail">Email de contacto</label>
          <div class="campo-input-wrapper">
            <span class="campo-icono material-symbols-outlined">mail</span>
            <input class="campo-input" type="email" id="locEmail" maxlength="120"
                   value="<?= htmlspecialchars((string) cfg('local.email'), ENT_QUOTES) ?>">
          </div>
          <span class="campo-error" id="err_local.email"></span>
        </div>

        <div class="campo-grupo cfg-col-entera">
          <label class="campo-etiqueta" for="locSitio">Dirección web</label>
          <div class="campo-input-wrapper">
            <span class="campo-icono material-symbols-outlined">language</span>
            <input class="campo-input" type="url" id="locSitio" maxlength="160"
                   value="<?= htmlspecialchars((string) cfg('local.sitio_url'), ENT_QUOTES) ?>">
          </div>
          <span class="campo-ayuda">Con https:// adelante. Se usa para el logo y los enlaces de los correos.</span>
          <span class="campo-error" id="err_local.sitio_url"></span>
        </div>
      </div>

      <div class="cfg-preview">
        <span class="cfg-preview-label">Así se ve la dirección</span>
        <p class="cfg-dir-preview" id="previewDireccion"></p>
      </div>
    </section>

    <!-- ══════════ Horarios de servicio ══════════ -->
    <section class="cfg-bloque">
      <div class="cfg-bloque-cab">
        <div class="icono icono-primary"><span class="material-symbols-outlined">schedule</span></div>
        <div>
          <h2 class="cfg-bloque-titulo">Horarios de servicio</h2>
          <p class="cfg-bloque-texto">Los turnos que puede elegir el cliente al reservar.</p>
        </div>
      </div>

      <?php foreach (['almuerzo' => 'Almuerzo', 'cena' => 'Cena'] as $turno => $label): ?>
        <div class="cfg-turno">
          <label class="res-check cfg-turno-check">
            <input type="checkbox" id="<?= $turno ?>Activo" <?= cfgBool("horario.{$turno}_activo") ? 'checked' : '' ?>>
            <span><?= $label ?></span>
          </label>
          <div class="cfg-turno-horas">
            <div class="campo-grupo">
              <label class="campo-etiqueta" for="<?= $turno ?>Inicio">Desde</label>
              <div class="campo-input-wrapper">
                <input class="campo-input" type="time" id="<?= $turno ?>Inicio" step="900"
                       value="<?= htmlspecialchars((string) cfg("horario.{$turno}_inicio"), ENT_QUOTES) ?>">
              </div>
            </div>
            <div class="campo-grupo">
              <label class="campo-etiqueta" for="<?= $turno ?>Fin">Hasta</label>
              <div class="campo-input-wrapper">
                <input class="campo-input" type="time" id="<?= $turno ?>Fin" step="900"
                       value="<?= htmlspecialchars((string) cfg("horario.{$turno}_fin"), ENT_QUOTES) ?>">
              </div>
            </div>
          </div>
          <span class="campo-error" id="err_horario.<?= $turno ?>_inicio"></span>
          <span class="campo-error" id="err_horario.<?= $turno ?>_fin"></span>
          <span class="campo-error" id="err_horario.<?= $turno ?>_activo"></span>
        </div>
      <?php endforeach; ?>

      <div class="campo-grupo cfg-campo-corto">
        <label class="campo-etiqueta" for="intervalo">Cada cuánto se puede reservar</label>
        <div class="campo-input-wrapper">
          <span class="campo-icono material-symbols-outlined">timer</span>
          <select class="campo-input campo-select" id="intervalo">
            <?php foreach ([15 => 'Cada 15 minutos', 30 => 'Cada 30 minutos', 60 => 'Cada hora'] as $v => $t): ?>
              <option value="<?= $v ?>" <?= cfgInt('horario.intervalo_min') === $v ? 'selected' : '' ?>><?= $t ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <span class="campo-error" id="err_horario.intervalo_min"></span>
      </div>

      <div class="cfg-preview">
        <span class="cfg-preview-label">Vista previa de los horarios</span>
        <div id="previewHorarios"></div>
      </div>
    </section>

    <!-- ══════════ Días de cierre ══════════ -->
    <section class="cfg-bloque">
      <div class="cfg-bloque-cab">
        <div class="icono icono-gold"><span class="material-symbols-outlined">event_busy</span></div>
        <div>
          <h2 class="cfg-bloque-titulo">Días de cierre</h2>
          <p class="cfg-bloque-texto">Los días marcados no se pueden reservar desde la web.</p>
        </div>
      </div>

      <div class="campo-grupo">
        <label class="campo-etiqueta">Cierra todas las semanas</label>
        <div class="cfg-dias">
          <?php foreach (DIAS_SEMANA as $n => $nombre): ?>
            <label class="cfg-dia <?= in_array($n, $diasCerrados, true) ? 'cfg-dia-on' : '' ?>">
              <input type="checkbox" class="chkDia" value="<?= $n ?>" <?= in_array($n, $diasCerrados, true) ? 'checked' : '' ?>>
              <span><?= substr($nombre, 0, 3) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
        <span class="campo-error" id="err_horario.dias_cerrados"></span>
      </div>

      <div class="campo-grupo">
        <label class="campo-etiqueta" for="nuevaFecha">Cierres puntuales <span class="campo-opcional">(festivos, vacaciones)</span></label>
        <div class="cfg-fecha-alta">
          <div class="campo-input-wrapper">
            <span class="campo-icono material-symbols-outlined">calendar_month</span>
            <input class="campo-input" type="date" id="nuevaFecha">
          </div>
          <button type="button" class="boton-secundario" id="btnAddFecha">
            <span class="material-symbols-outlined">add</span> Agregar
          </button>
        </div>
        <div class="cfg-fechas" id="listaFechas"></div>
      </div>
    </section>

    <!-- ══════════ Reglas de reserva ══════════ -->
    <section class="cfg-bloque">
      <div class="cfg-bloque-cab">
        <div class="icono"><span class="material-symbols-outlined">rule</span></div>
        <div>
          <h2 class="cfg-bloque-titulo">Reglas de reserva</h2>
          <p class="cfg-bloque-texto">Qué se le permite al cliente al reservar.</p>
        </div>
      </div>

      <div class="cfg-grid">
        <div class="campo-grupo">
          <label class="campo-etiqueta" for="maxPersonas">Máximo de comensales por reserva</label>
          <div class="campo-input-wrapper">
            <span class="campo-icono material-symbols-outlined">group</span>
            <input class="campo-input" type="number" id="maxPersonas" min="1" max="50"
                   value="<?= cfgInt('reservas.max_personas') ?>">
          </div>
          <span class="campo-ayuda">Para grupos más grandes, que llamen por teléfono.</span>
          <span class="campo-error" id="err_reservas.max_personas"></span>
        </div>

        <div class="campo-grupo">
          <label class="campo-etiqueta" for="antMin">Antelación mínima (horas)</label>
          <div class="campo-input-wrapper">
            <span class="campo-icono material-symbols-outlined">hourglass_top</span>
            <input class="campo-input" type="number" id="antMin" min="0" max="168"
                   value="<?= cfgInt('reservas.antelacion_min_horas') ?>">
          </div>
          <span class="campo-ayuda">Evita que reserven para dentro de 10 minutos. 0 = sin límite.</span>
          <span class="campo-error" id="err_reservas.antelacion_min_horas"></span>
        </div>

        <div class="campo-grupo">
          <label class="campo-etiqueta" for="antMax">Antelación máxima (días)</label>
          <div class="campo-input-wrapper">
            <span class="campo-icono material-symbols-outlined">event_upcoming</span>
            <input class="campo-input" type="number" id="antMax" min="1" max="365"
                   value="<?= cfgInt('reservas.antelacion_max_dias') ?>">
          </div>
          <span class="campo-ayuda">Hasta cuándo se abre el calendario.</span>
          <span class="campo-error" id="err_reservas.antelacion_max_dias"></span>
        </div>

        <div class="campo-grupo">
          <label class="campo-etiqueta" for="duracion">Duración de la mesa (horas)</label>
          <div class="campo-input-wrapper">
            <span class="campo-icono material-symbols-outlined">timelapse</span>
            <input class="campo-input" type="number" id="duracion" min="1" max="8"
                   value="<?= cfgInt('reservas.duracion_horas') ?>">
          </div>
          <span class="campo-ayuda">Se usa en el evento de calendario que descarga el cliente.</span>
          <span class="campo-error" id="err_reservas.duracion_horas"></span>
        </div>

        <div class="campo-grupo">
          <label class="campo-etiqueta" for="cortesia">Minutos de cortesía</label>
          <div class="campo-input-wrapper">
            <span class="campo-icono material-symbols-outlined">timer_3</span>
            <input class="campo-input" type="number" id="cortesia" min="0" max="120"
                   value="<?= cfgInt('reservas.cortesia_min') ?>">
          </div>
          <span class="campo-ayuda">Cuánto se guarda la mesa pasada la hora. Sale en el comprobante y el correo.</span>
          <span class="campo-error" id="err_reservas.cortesia_min"></span>
        </div>
      </div>

      <label class="res-check cfg-check-suelto">
        <input type="checkbox" id="autoConfirmar" <?= cfgBool('reservas.auto_confirmar') ? 'checked' : '' ?>>
        <span>Confirmar las reservas automáticamente</span>
      </label>
      <span class="campo-ayuda cfg-ayuda-check">
        Si lo destildás, las reservas entran como <strong>pendientes</strong> y hay que confirmarlas a mano desde el panel de Reservas.
      </span>
    </section>

    <div class="cfg-acciones">
      <button type="submit" class="boton-accion" id="btnGuardar">
        <span class="material-symbols-outlined">save</span>
        <span>Guardar configuración</span>
      </button>
    </div>

  </form>

  <?php endif; ?>
</main>

<?php if (!$sinBase): ?>
<script>
(function () {
  const BASE = '<?= BASE_URL ?>';
  let fechasCerradas = <?= json_encode(array_values($fechasCerradas)) ?>;

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
</script>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
