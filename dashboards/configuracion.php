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

        <div class="campo-grupo">
          <label class="campo-etiqueta" for="maxActivas">Reservas activas por cliente</label>
          <div class="campo-input-wrapper">
            <span class="campo-icono material-symbols-outlined">event_repeat</span>
            <input class="campo-input" type="number" id="maxActivas" min="0" max="20"
                   value="<?= cfgInt('reservas.max_activas_usuario') ?>">
          </div>
          <span class="campo-ayuda">Cuántas reservas futuras puede tener a la vez una cuenta. Evita el spam. 0 = sin límite.</span>
          <span class="campo-error" id="err_reservas.max_activas_usuario"></span>
        </div>

        <div class="campo-grupo">
          <label class="campo-etiqueta" for="maxPorDia">Reservas por cliente en un mismo día</label>
          <div class="campo-input-wrapper">
            <span class="campo-icono material-symbols-outlined">today</span>
            <input class="campo-input" type="number" id="maxPorDia" min="0" max="10"
                   value="<?= cfgInt('reservas.max_por_dia_usuario') ?>">
          </div>
          <span class="campo-ayuda">Evita que una cuenta tome varias mesas el mismo día para revenderlas. 0 = sin límite.</span>
          <span class="campo-error" id="err_reservas.max_por_dia_usuario"></span>
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
  const BASE = '<?= BASE_URL ?>';
  let fechasCerradas = <?= json_encode(array_values($fechasCerradas)) ?>;
</script>
<script src="<?= jsUrl('configuracion.js') ?>"></script>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
