<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/check_auth.php';
requireRole('superadmin');
$pageTitle = 'Gestión de Usuarios';
$pageCSS = '../assets/css/dashboard.css';
$showDashboardBottomNav = true;
require_once '../includes/header.php';
?>
<?php require_once '../includes/nav.php'; ?>

<main class="contenido-principal">

  <!-- ── Encabezado ── -->
  <header class="seccion-encabezado">
    <div>
      <a href="<?= buildUrl('/dashboards/superadmin.php') ?>" class="enlace-volver">
        <span class="material-symbols-outlined">arrow_back</span>
        Panel principal
      </a>
      <h1 class="titulo-pagina" style="margin-top:.5rem">Gestión de Usuarios</h1>
      <p class="subtitulo-pagina">Creá, editá y revisá cuentas de usuarios, admins y superadmins.</p>
    </div>
    <button class="boton-accion" id="btnNuevoUsuario">
      <span class="material-symbols-outlined">person_add</span>
      Nuevo usuario
    </button>
  </header>

  <!-- ── Stats ── -->
  <div class="gu-stats" id="guStats">
    <div class="gu-stat">
      <div class="icono"><span class="material-symbols-outlined">group</span></div>
      <div>
        <p class="texto-etiqueta">Total usuarios</p>
        <p class="numero-resumen" id="statUsuarios">—</p>
      </div>
    </div>
    <div class="gu-stat">
      <div class="icono icono-primary"><span class="material-symbols-outlined">manage_accounts</span></div>
      <div>
        <p class="texto-etiqueta">Admins</p>
        <p class="numero-resumen" id="statAdmins">—</p>
      </div>
    </div>
    <div class="gu-stat">
      <div class="icono icono-gold"><span class="material-symbols-outlined">verified_user</span></div>
      <div>
        <p class="texto-etiqueta">Superadmins</p>
        <p class="numero-resumen" id="statSuperadmins">—</p>
      </div>
    </div>
  </div>

  <!-- ── Barra búsqueda + filtros ── -->
  <div class="gu-barra">
    <div class="campo-input-wrapper gu-busqueda">
      <span class="campo-icono material-symbols-outlined">search</span>
      <input class="campo-input" type="text" id="inputBusqueda" placeholder="Buscar por nombre o email…">
    </div>
    <div class="filtros-rol" role="group" aria-label="Filtrar por rol">
      <button class="filtro-btn filtro-activo" data-rol="">Todos</button>
      <button class="filtro-btn" data-rol="usuario">Usuario</button>
      <button class="filtro-btn" data-rol="admin">Admin</button>
      <button class="filtro-btn" data-rol="superadmin">Superadmin</button>
    </div>
  </div>

  <!-- ── Tabla ── -->
  <div class="gu-tabla-wrap" id="guTablaWrap">
    <div class="gu-estado" id="guEstado">
      <span class="material-symbols-outlined icono-spin" style="font-size:2rem;color:#264220">progress_activity</span>
      <p>Cargando usuarios…</p>
    </div>
  </div>

</main>

<!-- ══════════════════════════════════════
     MODAL: Crear / Editar usuario
════════════════════════════════════════ -->
<div class="modal-fondo" id="modalUsuario" role="dialog" aria-modal="true" aria-labelledby="modalUsuarioTitulo">
  <div class="modal-contenedor">
    <div class="modal-encabezado">
      <div class="modal-icono" id="modalUsuarioIcono">
        <span class="material-symbols-outlined">person_add</span>
      </div>
      <div>
        <h2 class="modal-titulo" id="modalUsuarioTitulo">Nuevo usuario</h2>
        <p class="modal-subtitulo" id="modalUsuarioSubtitulo">Completá los datos para registrar un nuevo usuario.</p>
      </div>
      <button class="modal-cerrar" id="btnCerrarModalUsuario" aria-label="Cerrar">
        <span class="material-symbols-outlined">close</span>
      </button>
    </div>

    <form class="modal-form" id="formUsuario" novalidate>
      <input type="hidden" id="usuarioId" value="">

      <div class="campo-grupo">
        <label class="campo-etiqueta" for="uNombre">Nombre completo</label>
        <div class="campo-input-wrapper">
          <span class="campo-icono material-symbols-outlined">person</span>
          <input class="campo-input" type="text" id="uNombre" name="nombre" placeholder="Ej. María González" required>
        </div>
        <span class="campo-error" id="uErrorNombre"></span>
      </div>

      <div class="campo-grupo">
        <label class="campo-etiqueta" for="uEmail">Correo electrónico</label>
        <div class="campo-input-wrapper">
          <span class="campo-icono material-symbols-outlined">mail</span>
          <input class="campo-input" type="email" id="uEmail" name="email" placeholder="usuario@ejemplo.com" required>
        </div>
        <span class="campo-error" id="uErrorEmail"></span>
      </div>

      <div class="campo-grupo">
        <label class="campo-etiqueta" for="uRol">Rol</label>
        <div class="campo-input-wrapper">
          <span class="campo-icono material-symbols-outlined">shield_person</span>
          <select class="campo-input campo-select" id="uRol" name="rol" required>
            <option value="usuario">Usuario</option>
            <option value="admin">Admin</option>
            <option value="superadmin">Superadmin</option>
          </select>
        </div>
        <span class="campo-error" id="uErrorRol"></span>
      </div>

      <div class="campo-grupo">
        <label class="campo-etiqueta" for="uPassword">
          Contraseña <span id="uPassOptional" class="campo-opcional">(opcional al editar)</span>
        </label>
        <div class="campo-input-wrapper">
          <span class="campo-icono material-symbols-outlined">lock</span>
          <input class="campo-input" type="password" id="uPassword" name="password" placeholder="Mínimo 8 caracteres, 1 mayúscula y 1 número">
          <button type="button" class="campo-ojo" id="uTogglePassword" aria-label="Mostrar contraseña">
            <span class="material-symbols-outlined">visibility</span>
          </button>
        </div>
        <span class="campo-error" id="uErrorPassword"></span>
      </div>

      <div class="modal-acciones">
        <button type="button" class="boton-secundario" id="btnCancelarModalUsuario">Cancelar</button>
        <button type="submit" class="boton-accion boton-modal-submit" id="btnSubmitUsuario">
          <span class="material-symbols-outlined">check</span>
          <span id="btnSubmitTexto">Crear usuario</span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ══════════════════════════════════════
     MODAL: Confirmar eliminación
════════════════════════════════════════ -->
<div class="modal-fondo" id="modalEliminar" role="dialog" aria-modal="true" aria-labelledby="modalEliminarTitulo">
  <div class="modal-contenedor modal-contenedor-sm">
    <div class="modal-encabezado">
      <div class="modal-icono modal-icono-danger">
        <span class="material-symbols-outlined">delete</span>
      </div>
      <div>
        <h2 class="modal-titulo" id="modalEliminarTitulo">Eliminar usuario</h2>
        <p class="modal-subtitulo">Esta acción no se puede deshacer.</p>
      </div>
      <button class="modal-cerrar" id="btnCerrarModalEliminar" aria-label="Cerrar">
        <span class="material-symbols-outlined">close</span>
      </button>
    </div>

    <p class="eliminar-descripcion">
      ¿Estás seguro de que querés eliminar a <strong id="eliminarNombreTarget"></strong>?
    </p>

    <div class="modal-acciones">
      <button type="button" class="boton-secundario" id="btnCancelarEliminar">Cancelar</button>
      <button type="button" class="boton-peligro" id="btnConfirmarEliminar">
        <span class="material-symbols-outlined">delete</span>
        Eliminar
      </button>
    </div>
  </div>
</div>

<script>
  const BASE = '<?= BASE_URL ?>';
</script>
<script src="<?= jsUrl('gestion-usuarios.js') ?>"></script>

<?php require_once '../includes/footer.php'; ?>
