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

    <a class="tarjeta" href="<?= buildUrl('/dashboards/reportes.php') ?>">
      <div class="tarjeta-overlay"></div>
      <div class="tarjeta-cabecera">
        <div class="icono">
          <span class="material-symbols-outlined">insights</span>
        </div>
        <h2>Reportes</h2>
      </div>
      <p class="tarjeta-texto">Reseña de reservas, horarios más pedidos y uso de las mesas.</p>
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
  const BASE = '<?= BASE_URL ?>';
</script>
<script src="<?= jsUrl('admin.js') ?>"></script>

<?php require_once '../includes/footer.php'; ?>
