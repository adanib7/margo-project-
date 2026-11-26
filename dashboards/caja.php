<?php
require_once __DIR__ . '/../includes/sesion.php';
iniciarSesion();
require_once '../includes/config.php';
require_once '../includes/check_auth.php';
requireRole('admin', 'superadmin');

$pageTitle = 'Caja y facturación';
$pageCSS = '../assets/css/dashboard.css';
$pageCSSExtra = ['caja.css'];
$showDashboardBottomNav = true;

$volverUrl = ($_SESSION['rol'] ?? '') === 'superadmin'
    ? buildUrl('/dashboards/superadmin.php')
    : buildUrl('/dashboards/admin.php');

require_once '../includes/header.php';
?>
<?php require_once '../includes/nav.php'; ?>

<main class="contenido-principal cj" id="caja">

  <header class="cj-encabezado">
    <div>
      <a href="<?= $volverUrl ?>" class="enlace-volver">
        <span class="material-symbols-outlined" aria-hidden="true">arrow_back</span>
        Panel principal
      </a>
      <p class="cj-eyebrow">Caja y facturación · e-Ticket y e-Factura</p>
      <h1 class="titulo-pagina">La caja es el salón</h1>
      <p class="subtitulo-pagina">Tocá una mesa para ver su cuenta, cargar pedidos, dividirla y cobrarla.</p>
    </div>
    <div class="cj-enlaces">
      <span class="cj-chip-turno cerrada" id="cjChipTurno">Caja cerrada</span>
      <a class="cj-enlace" href="<?= buildUrl('/dashboards/ventas.php') ?>"><span class="material-symbols-outlined" aria-hidden="true">receipt_long</span>Ventas</a>
      <a class="cj-enlace" href="<?= buildUrl('/dashboards/carta.php') ?>"><span class="material-symbols-outlined" aria-hidden="true">restaurant_menu</span>Carta</a>
      <a class="cj-enlace" href="<?= buildUrl('/dashboards/mozo.php') ?>"><span class="material-symbols-outlined" aria-hidden="true">smartphone</span>Vista mozo</a>
      <a class="cj-enlace" href="<?= buildUrl('/dashboards/cierre_caja.php') ?>"><span class="material-symbols-outlined" aria-hidden="true">lock</span>Cierre de caja</a>
    </div>
  </header>

  <div class="cj-stats">
    <div class="cj-stat"><div class="cj-stat-label">Mesas abiertas</div><div class="cj-stat-valor" id="stAbiertas">—</div></div>
    <div class="cj-stat dorado"><div class="cj-stat-label">Piden la cuenta</div><div class="cj-stat-valor" id="stPiden">—</div></div>
    <div class="cj-stat"><div class="cj-stat-label">Cobrado en el turno</div><div class="cj-stat-valor" id="stCobrado">—</div></div>
    <div class="cj-stat"><div class="cj-stat-label">Comprobantes</div><div class="cj-stat-valor" id="stCfe">—</div></div>
  </div>

  <div class="cj-aviso cerrada" id="cjAvisoTurno" hidden>
    <span class="material-symbols-outlined" aria-hidden="true">point_of_sale</span>
    <div class="cj-aviso-texto">
      <strong>La caja está cerrada</strong>
      Los mozos pueden abrir mesas y cargar pedidos, pero para cobrar hay que abrir la caja con el efectivo del cajón.
    </div>
    <form id="formAbrirTurno">
      <label for="fondoInicial">Fondo inicial</label>
      <input class="cj-input-plata" type="number" id="fondoInicial" placeholder="Ej. 3000" min="0" step="1" inputmode="numeric" value="<?= (float) cfg('facturacion.fondo_sugerido') ?>">
      <button type="submit" class="cj-boton-dorado"><span class="material-symbols-outlined" aria-hidden="true">lock_open</span>Abrir caja</button>
    </form>
  </div>

  <div class="cj-aviso comandas" id="cjAvisoComandas" hidden>
    <span class="material-symbols-outlined" aria-hidden="true">receipt_long</span>
    <div class="cj-aviso-texto"><strong id="cjComandasTexto">Pedidos nuevos para cocina</strong>Imprimí las comandas para que cocina y barra las preparen.</div>
    <button type="button" class="cj-boton-dorado" id="btnComandas"><span class="material-symbols-outlined" aria-hidden="true">print</span>Imprimir comandas</button>
  </div>

  <div class="cj-layout">
    <div class="cj-salon">
      <section class="cj-panel" aria-labelledby="tituloPlano">
        <div class="cj-panel-cab">
          <h2 class="cj-panel-titulo" id="tituloPlano">Salón</h2>
          <div class="cj-leyenda" aria-hidden="true">
            <span><i style="border:2px dashed #a9afa2"></i>Libre</span>
            <span><i style="background:#3d5a35"></i>Comiendo</span>
            <span><i style="background:#c9962e"></i>Pide la cuenta</span>
            <span><i style="background:#eaf1f8;border:2px solid #2d5f8a"></i>Reservada</span>
          </div>
        </div>
        <div class="cj-plano" id="cjPlano" role="group" aria-label="Plano del salón">
          <div class="cj-plano-vacio"><span class="material-symbols-outlined icono-spin" aria-hidden="true">progress_activity</span>Cargando el salón…</div>
        </div>
      </section>

      <section class="cj-panel" aria-labelledby="tituloMostrador">
        <div class="cj-panel-cab">
          <h2 class="cj-panel-titulo" id="tituloMostrador">Mostrador y para llevar</h2>
          <button type="button" class="cj-enlace" id="btnMostrador"><span class="material-symbols-outlined" aria-hidden="true">add_shopping_cart</span>Nueva venta</button>
        </div>
        <div class="cj-mostrador" id="cjMostrador"><span class="cj-vacio-chico">Sin ventas de mostrador abiertas.</span></div>
      </section>

      <section class="cj-panel" aria-labelledby="tituloReservas">
        <div class="cj-panel-cab">
          <h2 class="cj-panel-titulo" id="tituloReservas">Reservas de hoy</h2>
          <a class="cj-enlace" href="<?= buildUrl('/dashboards/reservas.php') ?>"><span class="material-symbols-outlined" aria-hidden="true">event</span>Ver todas</a>
        </div>
        <ul class="cj-reservas" id="cjReservas"><li class="cj-vacio-chico">Cargando…</li></ul>
      </section>
    </div>

    <aside class="cj-cuenta" aria-label="Cuenta de la mesa" id="cjCuenta" aria-live="polite"></aside>
  </div>
</main>

<?php require_once '../includes/caja_selector.php'; ?>

<!-- Mesa reservada / elegir mesa para una reserva que llegó -->
<div class="modal-fondo" id="modalReservada" role="dialog" aria-modal="true" aria-labelledby="resTitulo">
  <div class="modal-contenedor">
    <div class="modal-encabezado">
      <div class="modal-icono" style="background:#2d5f8a;color:#dbe7f3"><span class="material-symbols-outlined">event_available</span></div>
      <div>
        <h2 class="modal-titulo" id="resTitulo">Mesa reservada</h2>
        <p class="modal-subtitulo" id="resSub"></p>
      </div>
      <button type="button" class="modal-cerrar" data-cerrar="modalReservada" aria-label="Cerrar"><span class="material-symbols-outlined">close</span></button>
    </div>
    <div id="resCuerpo"></div>
  </div>
</div>

<!-- Abrir mesa -->
<div class="modal-fondo" id="modalAbrir" role="dialog" aria-modal="true" aria-labelledby="abrirTitulo">
  <div class="modal-contenedor modal-contenedor-sm">
    <div class="modal-encabezado">
      <div class="modal-icono"><span class="material-symbols-outlined">table_restaurant</span></div>
      <div>
        <h2 class="modal-titulo" id="abrirTitulo">Abrir mesa</h2>
        <p class="modal-subtitulo">¿Cuántos se sientan?</p>
      </div>
      <button type="button" class="modal-cerrar" data-cerrar="modalAbrir" aria-label="Cerrar"><span class="material-symbols-outlined">close</span></button>
    </div>
    <form class="modal-form" id="formAbrir">
      <div class="campo-grupo">
        <span class="campo-etiqueta" id="abrirComensalesEtq">Comensales</span>
        <div class="cj-stepper" role="group" aria-labelledby="abrirComensalesEtq">
          <button type="button" data-paso="-1" aria-label="Uno menos"><span class="material-symbols-outlined">remove</span></button>
          <output id="abrirComensales">2</output>
          <button type="button" data-paso="1" aria-label="Uno más"><span class="material-symbols-outlined">add</span></button>
        </div>
      </div>
      <div class="campo-grupo" id="abrirMozoGrupo">
        <label class="campo-etiqueta" for="abrirMozo">Mozo</label>
        <div class="campo-input-wrapper">
          <span class="campo-icono material-symbols-outlined">person</span>
          <select class="campo-input campo-select" id="abrirMozo"><option value="">Sin asignar</option></select>
        </div>
      </div>
      <div class="modal-acciones">
        <button type="button" class="boton-secundario" data-cerrar="modalAbrir">Cancelar</button>
        <button type="submit" class="boton-accion boton-modal-submit"><span class="material-symbols-outlined">check</span>Abrir</button>
      </div>
    </form>
  </div>
</div>

<!-- Anular producto o cuenta -->
<div class="modal-fondo" id="modalAnular" role="dialog" aria-modal="true" aria-labelledby="anularTitulo">
  <div class="modal-contenedor modal-contenedor-sm">
    <div class="modal-encabezado">
      <div class="modal-icono modal-icono-danger"><span class="material-symbols-outlined">block</span></div>
      <div>
        <h2 class="modal-titulo" id="anularTitulo">Anular</h2>
        <p class="modal-subtitulo" id="anularSub">Queda registrado quién lo anuló y por qué.</p>
      </div>
      <button type="button" class="modal-cerrar" data-cerrar="modalAnular" aria-label="Cerrar"><span class="material-symbols-outlined">close</span></button>
    </div>
    <form class="modal-form" id="formAnular">
      <div class="campo-grupo">
        <label class="campo-etiqueta" for="anularMotivo">Motivo</label>
        <textarea class="campo-textarea" id="anularMotivo" rows="2" maxlength="160" placeholder="Ej. se cargó por error, el cliente lo canceló…" required></textarea>
      </div>
      <div class="modal-acciones">
        <button type="button" class="boton-secundario" data-cerrar="modalAnular">Volver</button>
        <button type="submit" class="boton-peligro"><span class="material-symbols-outlined">block</span>Anular</button>
      </div>
    </form>
  </div>
</div>

<!-- Mover de mesa / comensales -->
<div class="modal-fondo" id="modalMover" role="dialog" aria-modal="true" aria-labelledby="moverTitulo">
  <div class="modal-contenedor modal-contenedor-sm">
    <div class="modal-encabezado">
      <div class="modal-icono"><span class="material-symbols-outlined">tune</span></div>
      <div>
        <h2 class="modal-titulo" id="moverTitulo">Ajustar la mesa</h2>
        <p class="modal-subtitulo">Cambiá los comensales o pasá la cuenta a otra mesa.</p>
      </div>
      <button type="button" class="modal-cerrar" data-cerrar="modalMover" aria-label="Cerrar"><span class="material-symbols-outlined">close</span></button>
    </div>
    <div class="modal-form">
      <div class="campo-grupo">
        <span class="campo-etiqueta" id="moverComEtq">Comensales</span>
        <div class="cj-stepper" role="group" aria-labelledby="moverComEtq">
          <button type="button" data-paso="-1" aria-label="Uno menos"><span class="material-symbols-outlined">remove</span></button>
          <output id="moverComensales">2</output>
          <button type="button" data-paso="1" aria-label="Uno más"><span class="material-symbols-outlined">add</span></button>
          <button type="button" class="cj-enlace" id="btnGuardarComensales" style="width:auto;height:auto">Guardar</button>
        </div>
      </div>
      <div class="campo-grupo">
        <label class="campo-etiqueta" for="moverMesa">Pasar a la mesa</label>
        <div class="campo-input-wrapper">
          <span class="campo-icono material-symbols-outlined">move_down</span>
          <select class="campo-input campo-select" id="moverMesa"></select>
        </div>
      </div>
      <div class="modal-acciones">
        <button type="button" class="boton-peligro" id="btnAnularCuenta" style="margin-right:auto"><span class="material-symbols-outlined">delete</span>Anular cuenta</button>
        <button type="button" class="boton-accion boton-modal-submit" id="btnMover"><span class="material-symbols-outlined">move_down</span>Mover</button>
      </div>
    </div>
  </div>
</div>

<!-- Cobro registrado -->
<div class="modal-fondo" id="modalCobrado" role="dialog" aria-modal="true" aria-labelledby="cobradoTitulo">
  <div class="modal-contenedor modal-contenedor-sm">
    <div class="modal-encabezado">
      <div class="modal-icono"><span class="material-symbols-outlined">check_circle</span></div>
      <div>
        <h2 class="modal-titulo" id="cobradoTitulo">Cobro registrado</h2>
        <p class="modal-subtitulo" id="cobradoSub"></p>
      </div>
      <button type="button" class="modal-cerrar" data-cerrar="modalCobrado" aria-label="Cerrar"><span class="material-symbols-outlined">close</span></button>
    </div>
    <div class="cj-exito">
      <div class="cj-sello" id="cobradoSello">Cobrada</div>
      <div id="cobradoDetalle"></div>
    </div>
    <div class="modal-acciones" style="margin-top:1.25rem">
      <button type="button" class="boton-secundario" data-cerrar="modalCobrado">Listo</button>
      <button type="button" class="boton-accion boton-modal-submit" id="btnImprimirComprobante"><span class="material-symbols-outlined">print</span>Imprimir ticket</button>
    </div>
  </div>
</div>

<script>
  const BASE = '<?= BASE_URL ?>';
</script>
<script src="<?= jsUrl('caja_comun.js') ?>"></script>
<script src="<?= jsUrl('caja.js') ?>"></script>

<?php require_once '../includes/footer.php'; ?>
