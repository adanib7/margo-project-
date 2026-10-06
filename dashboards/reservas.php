<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/check_auth.php';
require_once '../includes/config_app.php';
requireRole('admin', 'superadmin');

$pageTitle = 'Reservas';
$pageCSS = '../assets/css/dashboard.css';
$showDashboardBottomNav = true;

$esSuperadmin = ($_SESSION['rol'] ?? '') === 'superadmin';
$volverUrl = $esSuperadmin
    ? buildUrl('/dashboards/superadmin.php')
    : buildUrl('/dashboards/admin.php');

$franjasHorarias = horarioFranjas();

require_once '../includes/header.php';
?>
<?php require_once '../includes/nav.php'; ?>

<main class="contenido-principal">

  <!-- ── Encabezado ── -->
  <header class="seccion-encabezado">
    <div>
      <a href="<?= $volverUrl ?>" class="enlace-volver">
        <span class="material-symbols-outlined">arrow_back</span>
        Panel principal
      </a>
      <h1 class="titulo-pagina" style="margin-top:.5rem">Reservas</h1>
      <p class="subtitulo-pagina">Consultá, confirmá, editá o cancelá las reservas del salón.</p>
    </div>
    <button class="boton-secundario" id="btnRefrescar">
      <span class="material-symbols-outlined">refresh</span>
      Actualizar
    </button>
  </header>

  <!-- ── Stats ── -->
  <div class="gu-stats res-stats">
    <div class="gu-stat">
      <div class="icono icono-primary"><span class="material-symbols-outlined">today</span></div>
      <div>
        <p class="texto-etiqueta">Reservas de hoy</p>
        <p class="numero-resumen" id="statHoy">—</p>
      </div>
    </div>
    <div class="gu-stat">
      <div class="icono icono-gold"><span class="material-symbols-outlined">schedule</span></div>
      <div>
        <p class="texto-etiqueta">Pendientes</p>
        <p class="numero-resumen" id="statPendientes">—</p>
      </div>
    </div>
    <div class="gu-stat">
      <div class="icono"><span class="material-symbols-outlined">event_available</span></div>
      <div>
        <p class="texto-etiqueta">Confirmadas</p>
        <p class="numero-resumen" id="statConfirmadas">—</p>
      </div>
    </div>
    <div class="gu-stat">
      <div class="icono"><span class="material-symbols-outlined">group</span></div>
      <div>
        <p class="texto-etiqueta">Comensales hoy</p>
        <p class="numero-resumen" id="statComensales">—</p>
      </div>
    </div>
  </div>

  <!-- ── Búsqueda + filtros ── -->
  <div class="gu-barra">
    <div class="campo-input-wrapper gu-busqueda">
      <span class="campo-icono material-symbols-outlined">search</span>
      <input class="campo-input" type="text" id="inputBusqueda" placeholder="Buscar por nombre, código o teléfono…">
    </div>
    <div class="filtros-rol" role="group" aria-label="Filtrar por estado">
      <button class="filtro-btn filtro-activo" data-estado="">Todos</button>
      <button class="filtro-btn" data-estado="pendiente">Pendientes</button>
      <button class="filtro-btn" data-estado="confirmada">Confirmadas</button>
      <button class="filtro-btn" data-estado="cancelada">Canceladas</button>
    </div>
  </div>

  <div class="res-barra-rango">
    <div class="filtros-rol" role="group" aria-label="Filtrar por fecha">
      <button class="filtro-btn filtro-activo" data-rango="proximas">Próximas</button>
      <button class="filtro-btn" data-rango="hoy">Hoy</button>
      <button class="filtro-btn" data-rango="manana">Mañana</button>
      <button class="filtro-btn" data-rango="semana">Esta semana</button>
      <button class="filtro-btn" data-rango="pasadas">Pasadas</button>
      <button class="filtro-btn" data-rango="">Todas</button>
    </div>
  </div>

  <!-- ── Tabla ── -->
  <div class="gu-tabla-wrap" id="resTablaWrap">
    <div class="gu-estado">
      <span class="material-symbols-outlined icono-spin" style="font-size:2rem;color:#264220">progress_activity</span>
      <p>Cargando reservas…</p>
    </div>
  </div>

</main>

<!-- ══════════════════════════════════════
     MODAL: Editar reserva
════════════════════════════════════════ -->
<div class="modal-fondo" id="modalReserva" role="dialog" aria-modal="true" aria-labelledby="modalReservaTitulo">
  <div class="modal-contenedor">
    <div class="modal-encabezado">
      <div class="modal-icono"><span class="material-symbols-outlined">edit_calendar</span></div>
      <div>
        <h2 class="modal-titulo" id="modalReservaTitulo">Editar reserva</h2>
        <p class="modal-subtitulo" id="modalReservaSubtitulo">Ajustá los datos de la reserva.</p>
      </div>
      <button class="modal-cerrar" id="btnCerrarModalReserva" aria-label="Cerrar">
        <span class="material-symbols-outlined">close</span>
      </button>
    </div>

    <form class="modal-form" id="formReserva" novalidate>
      <input type="hidden" id="rId" value="">

      <div class="campo-grupo">
        <label class="campo-etiqueta" for="rNombre">Nombre del cliente</label>
        <div class="campo-input-wrapper">
          <span class="campo-icono material-symbols-outlined">person</span>
          <input class="campo-input" type="text" id="rNombre" maxlength="255" required>
        </div>
        <span class="campo-error" id="rErrorNombre"></span>
      </div>

      <div class="inv-form-fila">
        <div class="campo-grupo">
          <label class="campo-etiqueta" for="rFecha">Fecha</label>
          <div class="campo-input-wrapper">
            <span class="campo-icono material-symbols-outlined">calendar_month</span>
            <input class="campo-input" type="date" id="rFecha" required>
          </div>
          <span class="campo-error" id="rErrorFecha"></span>
        </div>

        <div class="campo-grupo">
          <label class="campo-etiqueta" for="rHora">Hora</label>
          <div class="campo-input-wrapper">
            <span class="campo-icono material-symbols-outlined">schedule</span>
            <select class="campo-input campo-select" id="rHora" required>
              <?php foreach ($franjasHorarias as $franja => $horas): ?>
                <optgroup label="<?= htmlspecialchars($franja, ENT_QUOTES) ?>">
                  <?php foreach ($horas as $h): ?>
                    <option value="<?= $h ?>"><?= $h ?></option>
                  <?php endforeach; ?>
                </optgroup>
              <?php endforeach; ?>
            </select>
          </div>
          <span class="campo-error" id="rErrorHora"></span>
        </div>
      </div>

      <div class="inv-form-fila">
        <div class="campo-grupo">
          <label class="campo-etiqueta" for="rPersonas">Comensales</label>
          <div class="campo-input-wrapper">
            <span class="campo-icono material-symbols-outlined">group</span>
            <input class="campo-input" type="number" id="rPersonas" min="1" max="20" required>
          </div>
          <span class="campo-error" id="rErrorPersonas"></span>
        </div>

        <div class="campo-grupo">
          <label class="campo-etiqueta" for="rMesa">Mesa</label>
          <div class="campo-input-wrapper">
            <span class="campo-icono material-symbols-outlined">table_restaurant</span>
            <select class="campo-input campo-select" id="rMesa">
              <option value="0">Sin asignar</option>
            </select>
          </div>
          <span class="campo-error" id="rErrorMesa"></span>
        </div>
      </div>

      <div class="inv-form-fila">
        <div class="campo-grupo">
          <label class="campo-etiqueta" for="rTelefono">Teléfono <span class="campo-opcional">(opcional)</span></label>
          <div class="campo-input-wrapper">
            <span class="campo-icono material-symbols-outlined">phone</span>
            <input class="campo-input" type="tel" id="rTelefono" maxlength="50">
          </div>
          <span class="campo-error" id="rErrorTelefono"></span>
        </div>

        <div class="campo-grupo">
          <label class="campo-etiqueta" for="rEstado">Estado</label>
          <div class="campo-input-wrapper">
            <span class="campo-icono material-symbols-outlined">flag</span>
            <select class="campo-input campo-select" id="rEstado" required>
              <option value="pendiente">Pendiente</option>
              <option value="confirmada">Confirmada</option>
              <option value="cancelada">Cancelada</option>
            </select>
          </div>
          <span class="campo-error" id="rErrorEstado"></span>
        </div>
      </div>

      <div class="campo-grupo">
        <label class="campo-etiqueta" for="rComentario">Pedido especial <span class="campo-opcional">(opcional)</span></label>
        <textarea class="campo-textarea" id="rComentario" rows="2" maxlength="500"></textarea>
      </div>

      <div class="modal-acciones">
        <button type="button" class="boton-secundario" id="btnCancelarModalReserva">Cancelar</button>
        <button type="submit" class="boton-accion boton-modal-submit" id="btnSubmitReserva">
          <span class="material-symbols-outlined">check</span>
          <span>Guardar cambios</span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ══════════════════════════════════════
     MODAL: Confirmar acción (cancelar / eliminar)
════════════════════════════════════════ -->
<div class="modal-fondo" id="modalConfirmar" role="dialog" aria-modal="true" aria-labelledby="modalConfirmarTitulo">
  <div class="modal-contenedor">
    <div class="modal-encabezado">
      <div class="modal-icono modal-icono-danger" id="modalConfirmarIcono">
        <span class="material-symbols-outlined">event_busy</span>
      </div>
      <div>
        <h2 class="modal-titulo" id="modalConfirmarTitulo">Cancelar reserva</h2>
        <p class="modal-subtitulo" id="modalConfirmarSubtitulo">La mesa queda libre otra vez.</p>
      </div>
      <button class="modal-cerrar" id="btnCerrarModalConfirmar" aria-label="Cerrar">
        <span class="material-symbols-outlined">close</span>
      </button>
    </div>

    <p class="eliminar-descripcion" id="modalConfirmarTexto"></p>

    <div class="res-aviso-bloque" id="bloqueAviso">
      <label class="res-check">
        <input type="checkbox" id="chkAvisar" checked>
        <span>Avisar al cliente por correo</span>
      </label>
      <div class="campo-grupo" id="grupoMotivo">
        <label class="campo-etiqueta" for="txtMotivo">Motivo <span class="campo-opcional">(opcional, se incluye en el correo)</span></label>
        <textarea class="campo-textarea" id="txtMotivo" rows="2" maxlength="300" placeholder="Ej. cerramos por reforma ese día"></textarea>
      </div>
    </div>

    <div class="modal-acciones">
      <button type="button" class="boton-secundario" id="btnCancelarConfirmar">Volver</button>
      <button type="button" class="boton-peligro" id="btnAceptarConfirmar">
        <span class="material-symbols-outlined">check</span>
        <span id="btnAceptarConfirmarTexto">Cancelar reserva</span>
      </button>
    </div>
  </div>
</div>

<script>
  const BASE = '<?= BASE_URL ?>';
  const ES_SUPERADMIN = <?= $esSuperadmin ? 'true' : 'false' ?>;
</script>
<script src="<?= jsUrl('reservas.js') ?>"></script>

<?php require_once '../includes/footer.php'; ?>
