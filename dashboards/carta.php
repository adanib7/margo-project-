<?php
require_once __DIR__ . '/../includes/sesion.php';
iniciarSesion();
require_once '../includes/config.php';
require_once '../includes/check_auth.php';
require_once '../includes/facturacion_db.php';
requireRole('admin', 'superadmin');

$pageTitle = 'Carta y precios';
$pageCSS = '../assets/css/dashboard.css';
$pageCSSExtra = ['caja.css'];
$showDashboardBottomNav = true;
$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

require_once '../includes/header.php';
?>
<?php require_once '../includes/nav.php'; ?>

<main class="contenido-principal cj">
  <header class="cj-encabezado">
    <div>
      <a href="<?= buildUrl('/dashboards/caja.php') ?>" class="enlace-volver"><span class="material-symbols-outlined" aria-hidden="true">arrow_back</span>Volver a la caja</a>
      <p class="cj-eyebrow">Caja y facturación</p>
      <h1 class="titulo-pagina">Carta y precios</h1>
      <p class="subtitulo-pagina">Lo que el mozo y la caja pueden cargar. Los precios van con IVA incluido.</p>
    </div>
    <button type="button" class="boton-accion" id="btnNuevoProducto"><span class="material-symbols-outlined" aria-hidden="true">add</span>Nuevo producto</button>
  </header>

  <div class="gu-barra">
    <div class="campo-input-wrapper gu-busqueda">
      <span class="campo-icono material-symbols-outlined">search</span>
      <input class="campo-input" type="search" id="ctBuscar" placeholder="Buscar producto…" aria-label="Buscar producto">
    </div>
    <div class="filtros-rol" role="group" aria-label="Filtrar por categoría" id="ctFiltros">
      <button type="button" class="filtro-btn filtro-activo" data-cat="">Todas</button>
      <?php foreach (FAC_CATEGORIAS as $k => $label): ?>
        <button type="button" class="filtro-btn" data-cat="<?= $h($k) ?>"><?= $h($label) ?></button>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="gu-tabla-wrap" id="ctTabla">
    <div class="gu-estado"><span class="material-symbols-outlined icono-spin" style="font-size:2rem;color:#264220" aria-hidden="true">progress_activity</span><p>Cargando la carta…</p></div>
  </div>

  <section class="cj-panel" style="margin-top:1.75rem" aria-labelledby="ctAjTit">
    <div class="cj-panel-cab">
      <div>
        <h2 class="cj-panel-titulo" id="ctAjTit">Datos de facturación</h2>
        <p style="margin:.25rem 0 0;font-size:.85rem;color:#434840">Salen en el encabezado de los tickets. Cuando se conecte DGI se usan para emitir los CFE.</p>
      </div>
    </div>
    <form id="formAjustes" class="ct-ajustes" novalidate>
      <div class="campo-grupo">
        <label class="campo-etiqueta" for="ajRazon">Razón social</label>
        <div class="campo-input-wrapper"><span class="campo-icono material-symbols-outlined">apartment</span>
          <input class="campo-input" type="text" id="ajRazon" maxlength="150" placeholder="Ej. Corralín S.R.L."></div>
      </div>
      <div class="campo-grupo">
        <label class="campo-etiqueta" for="ajRut">RUT</label>
        <div class="campo-input-wrapper"><span class="campo-icono material-symbols-outlined">badge</span>
          <input class="campo-input" type="text" id="ajRut" inputmode="numeric" maxlength="12" placeholder="12 dígitos"></div>
        <span class="campo-error" id="ajErrorRut"></span>
      </div>
      <div class="campo-grupo">
        <label class="campo-etiqueta" for="ajPropina">Propina sugerida (%)</label>
        <div class="campo-input-wrapper"><span class="campo-icono material-symbols-outlined">volunteer_activism</span>
          <input class="campo-input" type="number" id="ajPropina" min="0" max="30" step="1"></div>
        <span class="campo-error" id="ajErrorPropina"></span>
      </div>
      <div class="campo-grupo">
        <label class="campo-etiqueta" for="ajFondo">Fondo de caja habitual ($)</label>
        <div class="campo-input-wrapper"><span class="campo-icono material-symbols-outlined">savings</span>
          <input class="campo-input" type="number" id="ajFondo" min="0" step="1"></div>
      </div>
      <div><button type="submit" class="boton-accion"><span class="material-symbols-outlined" aria-hidden="true">save</span>Guardar</button></div>
    </form>
  </section>
</main>

<div class="modal-fondo" id="modalProducto" role="dialog" aria-modal="true" aria-labelledby="prTitulo">
  <div class="modal-contenedor">
    <div class="modal-encabezado">
      <div class="modal-icono"><span class="material-symbols-outlined">restaurant_menu</span></div>
      <div>
        <h2 class="modal-titulo" id="prTitulo">Nuevo producto</h2>
        <p class="modal-subtitulo">Cambiar el precio no modifica los pedidos ya cargados.</p>
      </div>
      <button type="button" class="modal-cerrar" data-cerrar="modalProducto" aria-label="Cerrar"><span class="material-symbols-outlined">close</span></button>
    </div>
    <form class="modal-form" id="formProducto" novalidate>
      <input type="hidden" id="prId">
      <div class="campo-grupo">
        <label class="campo-etiqueta" for="prNombre">Nombre</label>
        <div class="campo-input-wrapper"><span class="campo-icono material-symbols-outlined">label</span>
          <input class="campo-input" type="text" id="prNombre" maxlength="120" required></div>
        <span class="campo-error" id="prErrorNombre"></span>
      </div>
      <div class="inv-form-fila">
        <div class="campo-grupo">
          <label class="campo-etiqueta" for="prCategoria">Categoría</label>
          <div class="campo-input-wrapper"><span class="campo-icono material-symbols-outlined">category</span>
            <select class="campo-input campo-select" id="prCategoria">
              <?php foreach (FAC_CATEGORIAS as $k => $label): ?><option value="<?= $h($k) ?>"><?= $h($label) ?></option><?php endforeach; ?>
            </select></div>
        </div>
        <div class="campo-grupo">
          <label class="campo-etiqueta" for="prPrecio">Precio con IVA ($)</label>
          <div class="campo-input-wrapper"><span class="campo-icono material-symbols-outlined">sell</span>
            <input class="campo-input" type="number" id="prPrecio" min="0" step="0.01" inputmode="decimal" required></div>
          <span class="campo-error" id="prErrorPrecio"></span>
        </div>
      </div>
      <div class="inv-form-fila">
        <div class="campo-grupo">
          <label class="campo-etiqueta" for="prIva">IVA</label>
          <div class="campo-input-wrapper"><span class="campo-icono material-symbols-outlined">percent</span>
            <select class="campo-input campo-select" id="prIva">
              <?php foreach (FAC_IVA as $k => $t): ?><option value="<?= $h($k) ?>"><?= $h($t['label']) ?></option><?php endforeach; ?>
            </select></div>
          <span class="campo-ayuda">Restaurantes: casi todo va a tasa básica. Confirmalo con tu contador.</span>
        </div>
        <div class="campo-grupo">
          <label class="campo-etiqueta" for="prDestino">Se prepara en</label>
          <div class="campo-input-wrapper"><span class="campo-icono material-symbols-outlined">soup_kitchen</span>
            <select class="campo-input campo-select" id="prDestino">
              <?php foreach (FAC_DESTINOS as $k => $label): ?><option value="<?= $h($k) ?>"><?= $h($label) ?></option><?php endforeach; ?>
            </select></div>
        </div>
      </div>
      <label class="res-check"><input type="checkbox" id="prActivo" checked><span>Disponible para pedir</span></label>
      <div class="modal-acciones">
        <button type="button" class="boton-peligro" id="btnEliminarProducto" style="margin-right:auto" hidden><span class="material-symbols-outlined">delete</span>Eliminar</button>
        <button type="button" class="boton-secundario" data-cerrar="modalProducto">Cancelar</button>
        <button type="submit" class="boton-accion boton-modal-submit"><span class="material-symbols-outlined">check</span>Guardar</button>
      </div>
    </form>
  </div>
</div>

<script>
  const BASE = '<?= BASE_URL ?>';
</script>
<script src="<?= jsUrl('caja_comun.js') ?>"></script>
<script src="<?= jsUrl('carta.js') ?>"></script>

<?php require_once '../includes/footer.php'; ?>
