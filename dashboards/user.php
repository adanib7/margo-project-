<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/check_auth.php';
require_once '../includes/config_app.php';
requireLogin();
$pageTitle = 'Dashboard de Usuario';
$pageCSS = '../assets/css/dashboard.css';
$showDashboardBottomNav = true;
require_once '../includes/header.php';

$franjasHorarias = horarioFranjas();
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
        <div class="paso-linea" data-paso-linea="3"></div>
        <div class="paso-dot" data-paso-dot="4"><span>4</span></div>
      </div>
      <p class="paso-contador" id="pasoContador">Paso 1 de 4</p>
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

      <!-- Paso 2: Elegí tu mesa -->
      <div class="modal-paso" data-paso="2">
        <h3 class="paso-titulo">Elegí tu mesa</h3>

        <div class="campo-grupo">
          <input type="hidden" id="rMesa" name="mesa_id" required>
          <div class="plano-reserva-leyenda">
            <span class="pr-lg pr-libre">Libre</span>
            <span class="pr-lg pr-ocupada">Ocupada</span>
            <span class="pr-lg pr-elegida">Tu mesa</span>
          </div>
          <div class="plano-reserva-wrap" id="planoReservaWrap">
            <div class="plano-reserva-lienzo" id="planoReservaLienzo"></div>
            <div class="plano-reserva-estado" id="planoReservaEstado">Cargando plano…</div>
          </div>
          <p class="plano-reserva-hint" id="planoReservaHint">Tocá una mesa libre para elegirla.</p>
          <span class="campo-error" id="rErrorMesa"></span>
        </div>
      </div>

      <!-- Paso 3: Cuántos -->
      <div class="modal-paso" data-paso="3">
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
          <p class="campo-opcional" id="mesaCapacidadHint" style="margin-top:.5rem"></p>
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

      <!-- Paso 4: Confirmá -->
      <div class="modal-paso" data-paso="4">
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
          <div class="resumen-fila"><span>Mesa</span><strong id="resumenMesa">—</strong></div>
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
        <div class="dato"><span class="etiqueta">Mesa</span><span class="valor" id="exitoMesa"></span></div>
        <div class="dato"><span class="etiqueta">Personas</span><span class="valor" id="exitoPersonas"></span></div>
        <div class="dato"><span class="etiqueta">Teléfono</span><span class="valor" id="exitoTelefono"></span></div>
      </div>

      <div class="modal-exito-acciones">
        <a class="boton-accion" id="exitoPdf" href="#" target="_blank" rel="noopener">
          <span class="material-symbols-outlined">picture_as_pdf</span> Descargar comprobante (PDF)
        </a>
        <a class="boton-secundario" id="exitoCalendario" href="#" target="_blank" rel="noopener">
          <span class="material-symbols-outlined">event</span> Agregar al calendario
        </a>
        <a class="boton-secundario" id="exitoComprobante" href="#" target="_blank" rel="noopener">
          <span class="material-symbols-outlined">receipt_long</span> Ver comprobante
        </a>
      </div>

      <button type="button" class="boton-secundario modal-exito-cerrar" id="btnCerrarExito">Cerrar</button>
    </div>
  </div>
</div>

<script>
  const BASE = '<?= BASE_URL ?>';
  const PERSONAS_MAX = <?= cfgInt('reservas.max_personas') ?>;
  // Reglas del panel de Configuración
  const DIAS_CERRADOS   = <?= json_encode(array_map('intval', cfgArray('horario.dias_cerrados'))) ?>;
  const FECHAS_CERRADAS = <?= json_encode(array_values(cfgArray('horario.fechas_cerradas'))) ?>;
  const ANT_MIN_HORAS   = <?= cfgInt('reservas.antelacion_min_horas') ?>;
  const ANT_MAX_DIAS    = <?= cfgInt('reservas.antelacion_max_dias') ?>;
  const USUARIO_NOMBRE = <?= json_encode($_SESSION['usuario_logueado']) ?>;
</script>
<script src="<?= jsUrl('user.js') ?>"></script>

<?php require_once '../includes/footer.php'; ?>
