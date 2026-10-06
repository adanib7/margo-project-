<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/check_auth.php';
require_once '../includes/inventario_db.php';
requireRole('admin', 'superadmin');

$pageTitle = 'Inventario';
$pageCSS = '../assets/css/dashboard.css';
$showDashboardBottomNav = true;

$volverUrl = ($_SESSION['rol'] ?? '') === 'superadmin'
    ? buildUrl('/dashboards/superadmin.php')
    : buildUrl('/dashboards/admin.php');

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
      <h1 class="titulo-pagina" style="margin-top:.5rem">Inventario</h1>
      <p class="subtitulo-pagina">Controlá el stock de ingredientes, bebidas y suministros del local.</p>
    </div>
    <button class="boton-accion" id="btnNuevoItem">
      <span class="material-symbols-outlined">add</span>
      Nuevo artículo
    </button>
  </header>

  <!-- ── Stats ── -->
  <div class="gu-stats">
    <div class="gu-stat">
      <div class="icono"><span class="material-symbols-outlined">inventory_2</span></div>
      <div>
        <p class="texto-etiqueta">Artículos</p>
        <p class="numero-resumen" id="statArticulos">—</p>
      </div>
    </div>
    <div class="gu-stat">
      <div class="icono icono-gold"><span class="material-symbols-outlined">warning</span></div>
      <div>
        <p class="texto-etiqueta">Bajo stock</p>
        <p class="numero-resumen" id="statBajoStock">—</p>
      </div>
    </div>
    <div class="gu-stat">
      <div class="icono icono-primary"><span class="material-symbols-outlined">payments</span></div>
      <div>
        <p class="texto-etiqueta">Valor del inventario</p>
        <p class="numero-resumen" id="statValor">—</p>
      </div>
    </div>
  </div>

  <!-- ── Barra búsqueda + filtros ── -->
  <div class="gu-barra">
    <div class="campo-input-wrapper gu-busqueda">
      <span class="campo-icono material-symbols-outlined">search</span>
      <input class="campo-input" type="text" id="inputBusqueda" placeholder="Buscar por nombre o proveedor…">
    </div>
    <div class="filtros-rol" role="group" aria-label="Filtrar por categoría">
      <button class="filtro-btn filtro-activo" data-cat="">Todas</button>
      <?php foreach (INV_CATEGORIAS as $slug => $label): ?>
        <button class="filtro-btn" data-cat="<?= htmlspecialchars($slug, ENT_QUOTES) ?>"><?= htmlspecialchars($label, ENT_QUOTES) ?></button>
      <?php endforeach; ?>
      <button class="filtro-btn filtro-bajo" data-cat="__bajo__">
        <span class="material-symbols-outlined" style="font-size:1rem;vertical-align:-2px">warning</span>
        Bajo stock
      </button>
    </div>
  </div>

  <!-- ── Tabla ── -->
  <div class="gu-tabla-wrap" id="invTablaWrap">
    <div class="gu-estado">
      <span class="material-symbols-outlined icono-spin" style="font-size:2rem;color:#264220">progress_activity</span>
      <p>Cargando inventario…</p>
    </div>
  </div>

</main>

<!-- ══════════════════════════════════════
     MODAL: Crear / Editar artículo
════════════════════════════════════════ -->
<div class="modal-fondo" id="modalItem" role="dialog" aria-modal="true" aria-labelledby="modalItemTitulo">
  <div class="modal-contenedor">
    <div class="modal-encabezado">
      <div class="modal-icono" id="modalItemIcono">
        <span class="material-symbols-outlined">add</span>
      </div>
      <div>
        <h2 class="modal-titulo" id="modalItemTitulo">Nuevo artículo</h2>
        <p class="modal-subtitulo" id="modalItemSubtitulo">Completá los datos del artículo a inventariar.</p>
      </div>
      <button class="modal-cerrar" id="btnCerrarModalItem" aria-label="Cerrar">
        <span class="material-symbols-outlined">close</span>
      </button>
    </div>

    <form class="modal-form" id="formItem" novalidate>
      <input type="hidden" id="itemId" value="">

      <div class="campo-grupo">
        <label class="campo-etiqueta" for="iNombre">Nombre</label>
        <div class="campo-input-wrapper">
          <span class="campo-icono material-symbols-outlined">label</span>
          <input class="campo-input" type="text" id="iNombre" name="nombre" placeholder="Ej. Faba de la Granja" maxlength="120" required>
        </div>
        <span class="campo-error" id="iErrorNombre"></span>
      </div>

      <div class="inv-form-fila">
        <div class="campo-grupo">
          <label class="campo-etiqueta" for="iCategoria">Categoría</label>
          <div class="campo-input-wrapper">
            <span class="campo-icono material-symbols-outlined">category</span>
            <select class="campo-input campo-select" id="iCategoria" name="categoria" required>
              <?php foreach (INV_CATEGORIAS as $slug => $label): ?>
                <option value="<?= htmlspecialchars($slug, ENT_QUOTES) ?>"><?= htmlspecialchars($label, ENT_QUOTES) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <span class="campo-error" id="iErrorCategoria"></span>
        </div>

        <div class="campo-grupo">
          <label class="campo-etiqueta" for="iUnidad">Unidad</label>
          <div class="campo-input-wrapper">
            <span class="campo-icono material-symbols-outlined">straighten</span>
            <select class="campo-input campo-select" id="iUnidad" name="unidad" required>
              <?php foreach (INV_UNIDADES as $slug => $label): ?>
                <option value="<?= htmlspecialchars($slug, ENT_QUOTES) ?>"><?= htmlspecialchars($label, ENT_QUOTES) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <span class="campo-error" id="iErrorUnidad"></span>
        </div>
      </div>

      <div class="inv-form-fila">
        <div class="campo-grupo">
          <label class="campo-etiqueta" for="iStock">Stock actual</label>
          <div class="campo-input-wrapper">
            <span class="campo-icono material-symbols-outlined">inventory</span>
            <input class="campo-input" type="number" id="iStock" name="stock" min="0" step="0.01" placeholder="0" required>
          </div>
          <span class="campo-error" id="iErrorStock"></span>
        </div>

        <div class="campo-grupo">
          <label class="campo-etiqueta" for="iStockMinimo">Stock mínimo</label>
          <div class="campo-input-wrapper">
            <span class="campo-icono material-symbols-outlined">notification_important</span>
            <input class="campo-input" type="number" id="iStockMinimo" name="stock_minimo" min="0" step="0.01" placeholder="0" required>
          </div>
          <span class="campo-error" id="iErrorStockMinimo"></span>
        </div>
      </div>

      <div class="inv-form-fila">
        <div class="campo-grupo">
          <label class="campo-etiqueta" for="iPrecio">Precio unitario (€)</label>
          <div class="campo-input-wrapper">
            <span class="campo-icono material-symbols-outlined">euro</span>
            <input class="campo-input" type="number" id="iPrecio" name="precio_unitario" min="0" step="0.01" placeholder="0.00" required>
          </div>
          <span class="campo-error" id="iErrorPrecio"></span>
        </div>

        <div class="campo-grupo">
          <label class="campo-etiqueta" for="iProveedor">Proveedor <span class="campo-opcional">(opcional)</span></label>
          <div class="campo-input-wrapper">
            <span class="campo-icono material-symbols-outlined">local_shipping</span>
            <input class="campo-input" type="text" id="iProveedor" name="proveedor" placeholder="Ej. Llagar Trabanco" maxlength="120">
          </div>
          <span class="campo-error" id="iErrorProveedor"></span>
        </div>
      </div>

      <div class="modal-acciones">
        <button type="button" class="boton-secundario" id="btnCancelarModalItem">Cancelar</button>
        <button type="submit" class="boton-accion boton-modal-submit" id="btnSubmitItem">
          <span class="material-symbols-outlined">check</span>
          <span id="btnSubmitItemTexto">Crear artículo</span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ══════════════════════════════════════
     MODAL: Confirmar eliminación
════════════════════════════════════════ -->
<div class="modal-fondo" id="modalEliminarItem" role="dialog" aria-modal="true" aria-labelledby="modalEliminarItemTitulo">
  <div class="modal-contenedor modal-contenedor-sm">
    <div class="modal-encabezado">
      <div class="modal-icono modal-icono-danger">
        <span class="material-symbols-outlined">delete</span>
      </div>
      <div>
        <h2 class="modal-titulo" id="modalEliminarItemTitulo">Eliminar artículo</h2>
        <p class="modal-subtitulo">Esta acción no se puede deshacer.</p>
      </div>
      <button class="modal-cerrar" id="btnCerrarModalEliminarItem" aria-label="Cerrar">
        <span class="material-symbols-outlined">close</span>
      </button>
    </div>

    <p class="eliminar-descripcion">
      ¿Estás seguro de que querés eliminar <strong id="eliminarItemTarget"></strong> del inventario?
    </p>

    <div class="modal-acciones">
      <button type="button" class="boton-secundario" id="btnCancelarEliminarItem">Cancelar</button>
      <button type="button" class="boton-peligro" id="btnConfirmarEliminarItem">
        <span class="material-symbols-outlined">delete</span>
        Eliminar
      </button>
    </div>
  </div>
</div>

<script>
  const BASE = '<?= BASE_URL ?>';
</script>
<script src="<?= jsUrl('inventario.js') ?>"></script>

<?php require_once '../includes/footer.php'; ?>
