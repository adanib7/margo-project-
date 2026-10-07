<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/check_auth.php';
requireRole('superadmin');
$pageTitle = 'Panel Super Administrador';
$pageCSS = '../assets/css/dashboard.css';
$showDashboardBottomNav = true;
require_once '../includes/header.php';
?>
<?php require_once '../includes/nav.php'; ?>

<main class="contenido-principal">
  <header class="seccion-encabezado">
    <div>
      <h1 class="titulo-pagina">Panel Super Administrador</h1>
    </div>
  </header>

  <div class="grilla-tarjetas" id="grillaTarjetas">
    <!-- El highlight es un único elemento que se desliza entre tarjetas (efecto Aceternity) -->
    <span class="tarjeta-highlight" id="tarjetaHighlight" aria-hidden="true"></span>

    <a class="tarjeta" href="<?= buildUrl('/dashboards/gestion-usuarios.php') ?>">
      <div class="tarjeta-cabecera">
        <div class="icono icono-primary">
          <span class="material-symbols-outlined">people</span>
        </div>
        <h2>Gestión de Usuarios</h2>
      </div>
      <p class="tarjeta-texto">Crear, editar y revisar cuentas de usuarios, admins y superadmins.</p>
    </a>

    <a class="tarjeta" href="<?= buildUrl('/dashboards/admin_plano.php') ?>">
      <div class="tarjeta-cabecera">
        <div class="icono">
          <span class="material-symbols-outlined">table_restaurant</span>
        </div>
        <h2>Plano de Mesas</h2>
      </div>
      <p class="tarjeta-texto">Editar la distribución de mesas que ven los clientes al reservar.</p>
    </a>

    <a class="tarjeta" href="<?= buildUrl('/dashboards/reservas.php') ?>">
      <div class="tarjeta-cabecera">
        <div class="icono icono-primary">
          <span class="material-symbols-outlined">event_available</span>
        </div>
        <h2>Reservas</h2>
      </div>
      <p class="tarjeta-texto">Ver, confirmar, editar y cancelar las reservas del salón.</p>
    </a>

    <a class="tarjeta" href="<?= buildUrl('/dashboards/inventario.php') ?>">
      <div class="tarjeta-cabecera">
        <div class="icono">
          <span class="material-symbols-outlined">inventory_2</span>
        </div>
        <h2>Inventario</h2>
      </div>
      <p class="tarjeta-texto">Controlar el stock de ingredientes, bebidas y suministros del local.</p>
    </a>

    <a class="tarjeta" href="<?= buildUrl('/dashboards/configuracion.php') ?>">
      <div class="tarjeta-cabecera">
        <div class="icono">
          <span class="material-symbols-outlined">settings</span>
        </div>
        <h2>Configuración</h2>
      </div>
      <p class="tarjeta-texto">Ajustes globales del sistema y mantenimiento de la plataforma.</p>
    </a>

    <a class="tarjeta" href="<?= buildUrl('/dashboards/reportes.php') ?>">
      <div class="tarjeta-cabecera">
        <div class="icono">
          <span class="material-symbols-outlined">insights</span>
        </div>
        <h2>Reportes</h2>
      </div>
      <p class="tarjeta-texto">Analiza el desempeño global y consulta datos de uso.</p>
    </a>
  </div>

  <div class="area-accion">
    <button class="boton-accion" id="btnAbrirModal">
      <span class="material-symbols-outlined">add</span>
      Crear Admin
    </button>
  </div>
</main>

<!-- ===== MODAL CREAR ADMIN ===== -->
<div class="modal-fondo" id="modalCrearAdmin" role="dialog" aria-modal="true" aria-labelledby="modalTitulo">
  <div class="modal-contenedor">
    <div class="modal-encabezado">
      <div class="modal-icono">
        <span class="material-symbols-outlined">admin_panel_settings</span>
      </div>
      <div>
        <h2 class="modal-titulo" id="modalTitulo">Crear Administrador</h2>
        <p class="modal-subtitulo">Completa los datos para registrar un nuevo admin.</p>
      </div>
      <button class="modal-cerrar" id="btnCerrarModal" aria-label="Cerrar">
        <span class="material-symbols-outlined">close</span>
      </button>
    </div>

    <form class="modal-form" id="formCrearAdmin" novalidate>
      <div class="campo-grupo">
        <label class="campo-etiqueta" for="adminNombre">Nombre completo</label>
        <div class="campo-input-wrapper">
          <span class="campo-icono material-symbols-outlined">person</span>
          <input class="campo-input" type="text" id="adminNombre" name="nombre" placeholder="Ej. María González" required>
        </div>
        <span class="campo-error" id="errorNombre"></span>
      </div>

      <div class="campo-grupo">
        <label class="campo-etiqueta" for="adminEmail">Correo electrónico</label>
        <div class="campo-input-wrapper">
          <span class="campo-icono material-symbols-outlined">mail</span>
          <input class="campo-input" type="email" id="adminEmail" name="email" placeholder="admin@ejemplo.com" required>
        </div>
        <span class="campo-error" id="errorEmail"></span>
      </div>

      <div class="campo-grupo">
        <label class="campo-etiqueta" for="adminPassword">Contraseña</label>
        <div class="campo-input-wrapper">
          <span class="campo-icono material-symbols-outlined">lock</span>
          <input class="campo-input" type="password" id="adminPassword" name="password" placeholder="Mínimo 8 caracteres" required>
          <button type="button" class="campo-ojo" id="togglePassword" aria-label="Mostrar contraseña">
            <span class="material-symbols-outlined">visibility</span>
          </button>
        </div>
        <span class="campo-error" id="errorPassword"></span>
      </div>

      <div class="modal-acciones">
        <button type="button" class="boton-secundario" id="btnCancelarModal">Cancelar</button>
        <button type="submit" class="boton-accion boton-modal-submit">
          <span class="material-symbols-outlined">check</span>
          Crear Admin
        </button>
      </div>
    </form>
  </div>
</div>

<script>
  const BASE_URL    = '<?= BASE_URL ?>';
</script>
<script src="<?= jsUrl('superadmin.js') ?>"></script>

<?php require_once '../includes/footer.php'; ?>