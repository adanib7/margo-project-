<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/check_auth.php';
requireLogin();
$pageTitle = 'Mis Reservas';
$pageCSS = '../assets/css/dashboard.css';
$showDashboardBottomNav = true;
require_once '../includes/header.php';

$reservas   = [];
$errorCarga = ($conn === null);

if ($conn !== null) {
    $stmt = $conn->prepare(
        "SELECT codigo, fecha, hora, personas, comentario, estado FROM reservas WHERE usuario_id = ? ORDER BY fecha ASC, hora ASC"
    );
    $stmt->bind_param('i', $_SESSION['usuario_id']);
    $stmt->execute();
    $reservas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$labelEstado = ['pendiente' => 'Pendiente', 'confirmada' => 'Confirmada', 'cancelada' => 'Cancelada'];
?>
<?php require_once '../includes/nav.php'; ?>

<main class="contenido-principal">
  <header class="seccion-encabezado">
    <div>
      <a href="<?= buildUrl('/dashboards/user.php') ?>" class="enlace-volver">
        <span class="material-symbols-outlined">arrow_back</span>
        Panel principal
      </a>
      <h1 class="titulo-pagina" style="margin-top:.5rem">Mis Reservas</h1>
      <p class="subtitulo-pagina">Revisá el estado de tus reservas de mesa.</p>
    </div>
  </header>

  <?php if ($errorCarga): ?>
    <div class="gu-estado">
      <span class="material-symbols-outlined" style="font-size:2.5rem;color:#b91c1c">error</span>
      <p>No se pudo conectar con la base de datos.</p>
    </div>
  <?php elseif (empty($reservas)): ?>
    <div class="gu-estado">
      <span class="material-symbols-outlined" style="font-size:2.5rem;color:#73796f">event_busy</span>
      <p>Todavía no tenés reservas. ¡Reservá tu mesa desde el panel principal!</p>
    </div>
  <?php else: ?>
    <div class="gu-tabla-wrap">
      <table class="gu-tabla">
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Hora</th>
            <th>Personas</th>
            <th>Pedido especial</th>
            <th>Estado</th>
            <th class="col-acciones">Comprobante</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($reservas as $r): ?>
            <tr>
              <td><?= htmlspecialchars(date('d/m/Y', strtotime($r['fecha'])), ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars(substr($r['hora'], 0, 5), ENT_QUOTES, 'UTF-8') ?>hs</td>
              <td><?= (int) $r['personas'] ?></td>
              <td><?= $r['comentario'] !== null && $r['comentario'] !== '' ? htmlspecialchars($r['comentario'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
              <td><span class="badge-rol badge-estado-<?= htmlspecialchars($r['estado'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($labelEstado[$r['estado']] ?? $r['estado'], ENT_QUOTES, 'UTF-8') ?></span></td>
              <td>
                <?php if (!empty($r['codigo'])): ?>
                  <a class="btn-tabla" title="Ver comprobante" href="<?= buildUrl('/comprobante.php?codigo=' . urlencode($r['codigo'])) ?>" target="_blank" rel="noopener">
                    <span class="material-symbols-outlined">receipt_long</span>
                  </a>
                <?php else: ?>
                  —
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</main>

<?php require_once '../includes/footer.php'; ?>
