<?php
require_once __DIR__ . '/../includes/sesion.php';
iniciarSesion();
require_once '../includes/config.php';
require_once '../includes/check_auth.php';
requireRole('mozo', 'admin', 'superadmin');

$pageTitle = 'Mis mesas';
$pageCSS = '../assets/css/dashboard.css';
$pageCSSExtra = ['caja.css'];
$esMozo = ($_SESSION['rol'] ?? '') === 'mozo';

require_once '../includes/header.php';
?>
<?php require_once '../includes/nav.php'; ?>

<main class="mz cj">
  <header class="mz-cab">
    <div>
      <?php if (!$esMozo): ?>
        <a href="<?= buildUrl('/dashboards/caja.php') ?>" class="enlace-volver"><span class="material-symbols-outlined" aria-hidden="true">arrow_back</span>Volver a la caja</a>
      <?php endif; ?>
      <p class="cj-eyebrow"><?= htmlspecialchars($_SESSION['usuario_logueado'] ?? '', ENT_QUOTES, 'UTF-8') ?> · salón</p>
      <h1>Mesas</h1>
    </div>
    <div style="text-align:right">
      <div class="cj-stat-label">Mis mesas abiertas</div>
      <div class="cj-mono" style="font-weight:700;font-size:1.1rem;color:#264220" id="mzAbiertas">—</div>
    </div>
  </header>

  <div class="mz-filtro" role="group" aria-label="Qué mesas mostrar">
    <button type="button" class="cj-tab activo" data-filtro="todas" aria-pressed="true">Todas</button>
    <button type="button" class="cj-tab" data-filtro="mias" aria-pressed="false">Mías</button>
    <button type="button" class="cj-tab" data-filtro="libres" aria-pressed="false">Libres</button>
  </div>

  <section class="mz-mesas" id="mzMesas" aria-label="Mesas del salón">
    <p class="cj-vacio-chico" style="grid-column:1/-1"><span class="material-symbols-outlined icono-spin" aria-hidden="true">progress_activity</span> Cargando mesas…</p>
  </section>
</main>

<div class="mz-fondo" id="mzFondo"></div>
<section class="mz-hoja cj" id="mzHoja" aria-label="Mesa elegida" aria-hidden="true"></section>

<?php require_once '../includes/caja_selector.php'; ?>

<script>
  const BASE = '<?= BASE_URL ?>';
</script>
<script src="<?= jsUrl('caja_comun.js') ?>"></script>
<script src="<?= jsUrl('mozo.js') ?>"></script>

<?php require_once '../includes/footer.php'; ?>
