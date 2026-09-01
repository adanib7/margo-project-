<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/check_auth.php';
require_once __DIR__ . '/plano_db.php';
requireLogin();

$codigo  = trim($_GET['codigo'] ?? '');
$reserva = null;

if ($codigo !== '' && $conn !== null) {
    // SELECT * : no depende de qué columnas tenga `reservas` en cada servidor.
    $stmt = $conn->prepare("SELECT * FROM reservas WHERE codigo = ? AND usuario_id = ? LIMIT 1");
    if ($stmt !== false) {
        $stmt->bind_param('si', $codigo, $_SESSION['usuario_id']);
        $stmt->execute();
        $reserva = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    if ($reserva) {
        $reserva['mesa_numero'] = null;
        $reserva['telefono']    = $reserva['telefono']   ?? '';
        $reserva['comentario']  = $reserva['comentario'] ?? '';

        if (!empty($reserva['mesa_id']) && planoLigadoAReservas($conn)) {
            $qm = $conn->prepare("SELECT numero FROM mesas WHERE id = ?");
            if ($qm !== false) {
                $qm->bind_param('i', $reserva['mesa_id']);
                $qm->execute();
                $rowm = $qm->get_result()->fetch_assoc();
                $qm->close();
                if ($rowm) {
                    $reserva['mesa_numero'] = (int) $rowm['numero'];
                }
            }
        }
    }
}

$labelEstado = ['pendiente' => 'Pendiente', 'confirmada' => 'Confirmada', 'cancelada' => 'Cancelada'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Comprobante de reserva · El Corralín de Campanal</title>
<style>
  :root { --verde: #264220; --verde-claro: #3d5a35; --dorado: #C9962E; --crema: #fafaf4; }
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body {
    font-family: 'Montserrat', Arial, sans-serif;
    background: var(--crema);
    color: #1a1c19;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 32px 16px;
  }
  .pagina-acciones {
    position: fixed;
    top: 16px;
    left: 16px;
    right: 16px;
    display: flex;
    justify-content: space-between;
    max-width: 480px;
    margin: 0 auto;
  }
  .enlace-volver {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: var(--verde);
    text-decoration: none;
    font-weight: 600;
    font-size: 0.9rem;
  }
  .boton-imprimir {
    display: inline-block;
    background: var(--verde);
    color: #fff;
    border: none;
    border-radius: 0.5rem;
    padding: 0.6rem 1.1rem;
    font-weight: 600;
    font-size: 0.85rem;
    text-decoration: none;
    cursor: pointer;
  }
  .boton-imprimir:hover { background: var(--verde-claro); }

  .ticket {
    background: #fff;
    width: 100%;
    max-width: 420px;
    border-radius: 1rem;
    box-shadow: 0 12px 40px rgba(0,0,0,0.12);
    overflow: hidden;
    border: 1px solid rgba(0,0,0,0.06);
  }
  .ticket-header {
    background: linear-gradient(135deg, var(--verde) 0%, var(--verde-claro) 100%);
    padding: 28px 24px 22px;
    text-align: center;
  }
  .ticket-header img { height: 44px; width: auto; filter: brightness(0) invert(1); margin: 0 auto 10px; }
  .ticket-header p { color: rgba(255,255,255,0.85); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.08em; }

  .ticket-codigo {
    text-align: center;
    padding: 20px 24px 4px;
  }
  .ticket-codigo span {
    display: inline-block;
    font-family: 'Roboto Mono', monospace;
    font-size: 1.5rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    color: var(--verde);
    background: #eef3ea;
    padding: 8px 18px;
    border-radius: 0.6rem;
  }

  .ticket-estado {
    text-align: center;
    padding-top: 8px;
  }
  .badge {
    display: inline-block;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    padding: 4px 12px;
    border-radius: 9999px;
  }
  .badge-pendiente  { background: #ffdea5; color: #785a1a; }
  .badge-confirmada { background: #caecbc; color: #264220; }
  .badge-cancelada  { background: #ffdad6; color: #b91c1c; }

  .ticket-separador {
    border: none;
    border-top: 1.5px dashed rgba(0,0,0,0.15);
    margin: 20px 24px 0;
  }

  .ticket-datos {
    padding: 18px 24px 26px;
    display: flex;
    flex-direction: column;
    gap: 14px;
  }
  .dato { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; }
  .dato .etiqueta { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.06em; color: #73796f; }
  .dato .valor { font-weight: 600; color: #1a1c19; text-align: right; }

  .ticket-footer {
    background: #f4f4ee;
    padding: 14px 24px;
    text-align: center;
    font-size: 0.75rem;
    color: #73796f;
  }

  .no-encontrada {
    background: #fff;
    max-width: 420px;
    width: 100%;
    border-radius: 1rem;
    padding: 40px 28px;
    text-align: center;
    box-shadow: 0 12px 40px rgba(0,0,0,0.12);
  }
  .no-encontrada p { margin-top: 8px; color: #434840; }

  @media print {
    .pagina-acciones { display: none; }
    body { padding: 0; background: #fff; }
    .ticket { box-shadow: none; border: none; max-width: 100%; }
  }
</style>
</head>
<body>

<div class="pagina-acciones">
  <a href="<?= buildUrl('/dashboards/mis-reservas.php') ?>" class="enlace-volver">← Mis reservas</a>
  <?php if ($reserva): ?>
    <a class="boton-imprimir" href="<?= buildUrl('/includes/comprobante_pdf.php?codigo=' . urlencode($reserva['codigo'])) ?>">Descargar PDF</a>
  <?php endif; ?>
</div>

<?php if (!$reserva): ?>
  <div class="no-encontrada">
    <p style="font-size:1.1rem;font-weight:700;color:#264220">Comprobante no encontrado</p>
    <p>No existe una reserva con ese código a tu nombre.</p>
  </div>
<?php else: ?>
  <div class="ticket">
    <div class="ticket-header">
      <img src="<?= buildUrl('/assets/img/logo-horizontal-verde.png') ?>" alt="El Corralín de Campanal">
      <p>Comprobante de reserva</p>
    </div>

    <div class="ticket-codigo">
      <span><?= htmlspecialchars($reserva['codigo'], ENT_QUOTES, 'UTF-8') ?></span>
    </div>
    <div class="ticket-estado">
      <span class="badge badge-<?= htmlspecialchars($reserva['estado'], ENT_QUOTES, 'UTF-8') ?>">
        <?= htmlspecialchars($labelEstado[$reserva['estado']] ?? $reserva['estado'], ENT_QUOTES, 'UTF-8') ?>
      </span>
    </div>

    <hr class="ticket-separador">

    <div class="ticket-datos">
      <div class="dato">
        <span class="etiqueta">Nombre</span>
        <span class="valor"><?= htmlspecialchars($reserva['nombre'], ENT_QUOTES, 'UTF-8') ?></span>
      </div>
      <div class="dato">
        <span class="etiqueta">Fecha</span>
        <span class="valor"><?= htmlspecialchars(date('d/m/Y', strtotime($reserva['fecha'])), ENT_QUOTES, 'UTF-8') ?></span>
      </div>
      <div class="dato">
        <span class="etiqueta">Hora</span>
        <span class="valor"><?= htmlspecialchars(substr($reserva['hora'], 0, 5), ENT_QUOTES, 'UTF-8') ?>hs</span>
      </div>
      <?php if (!empty($reserva['mesa_numero'])): ?>
      <div class="dato">
        <span class="etiqueta">Mesa</span>
        <span class="valor">Mesa <?= (int) $reserva['mesa_numero'] ?></span>
      </div>
      <?php endif; ?>
      <div class="dato">
        <span class="etiqueta">Personas</span>
        <span class="valor"><?= (int) $reserva['personas'] ?></span>
      </div>
      <?php if (!isset($reserva['telefono']) || $reserva['telefono'] !== ''): ?>
      <div class="dato">
        <span class="etiqueta">Teléfono</span>
        <span class="valor"><?= htmlspecialchars($reserva['telefono'] ?? '—', ENT_QUOTES, 'UTF-8') ?></span>
      </div>
      <?php endif; ?>
      <?php if (!empty($reserva['comentario'])): ?>
      <div class="dato">
        <span class="etiqueta">Pedido especial</span>
        <span class="valor"><?= htmlspecialchars($reserva['comentario'], ENT_QUOTES, 'UTF-8') ?></span>
      </div>
      <?php endif; ?>
    </div>

    <div class="ticket-footer">
      Plaza Manuel Uría, 4 · 33520 Nava, Asturias · 985 71 60 42
    </div>
  </div>
<?php endif; ?>

</body>
</html>
