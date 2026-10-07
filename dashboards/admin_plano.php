<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/check_auth.php';
require_once __DIR__ . '/../includes/plano_db.php';
requireRole('admin', 'superadmin');

function sendJson(array $data): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($conn) || $conn === null) {
    http_response_code(500);
    sendJson(['error' => 'No se pudo conectar a la base de datos del servidor.']);
}

ensureMesaTable($conn);
ensureReservaMesaColumn($conn);

$action = $_GET['action'] ?? '';

if ($action === 'cargar') {
    $result = $conn->query("SELECT id, numero, capacidad, forma, pos_x, pos_y, ancho, alto, rotacion FROM mesas ORDER BY id ASC");
    if (!$result) {
        sendJson(['error' => 'Error al cargar las mesas.']);
    }

    $mesas = [];
    while ($row = $result->fetch_assoc()) {
        $mesas[] = $row;
    }

    sendJson($mesas);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true) ?: [];
    $action = $payload['action'] ?? ($_POST['action'] ?? '');

    if ($action === 'guardar') {
        $mesas = $payload['mesas'] ?? [];
        $eliminadas = $payload['eliminadas'] ?? [];

        foreach ($mesas as $m) {
            $id = isset($m['id']) && $m['id'] !== '' ? (int) $m['id'] : null;
            $numero = (int) ($m['numero'] ?? 1);
            $capacidad = (int) ($m['capacidad'] ?? 4);
            $forma = $m['forma'] ?? 'cuadrada';
            $posX = (float) ($m['pos_x'] ?? 100);
            $posY = (float) ($m['pos_y'] ?? 100);
            $ancho = (float) ($m['ancho'] ?? 70);
            $alto = (float) ($m['alto'] ?? 70);
            $rotacion = (float) ($m['rotacion'] ?? 0);

            if ($id) {
                $stmt = $conn->prepare("
                    UPDATE mesas
                    SET numero = ?, capacidad = ?, forma = ?, pos_x = ?, pos_y = ?, ancho = ?, alto = ?, rotacion = ?
                    WHERE id = ?
                ");
                $stmt->bind_param('iisdddddi', $numero, $capacidad, $forma, $posX, $posY, $ancho, $alto, $rotacion, $id);
                $stmt->execute();
                $stmt->close();
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO mesas (numero, capacidad, forma, pos_x, pos_y, ancho, alto, rotacion)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param('iisddddd', $numero, $capacidad, $forma, $posX, $posY, $ancho, $alto, $rotacion);
                $stmt->execute();
                $stmt->close();
            }
        }

        $noEliminadas = [];
        foreach ($eliminadas as $mesaId) {
            $id = (int) $mesaId;
            if ($id > 0 && mesaTieneReservas($conn, $id)) {
                $noEliminadas[] = $id;
            } else {
                $stmt = $conn->prepare("DELETE FROM mesas WHERE id = ?");
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $stmt->close();
            }
        }

        sendJson([
            'ok' => true,
            'no_eliminadas' => $noEliminadas,
        ]);
    }
}
$pageTitle = 'Plano de mesas';
$pageCSS = '../assets/css/dashboard.css';
$volverUrl = ($_SESSION['rol'] ?? '') === 'superadmin'
    ? buildUrl('/dashboards/superadmin.php')
    : buildUrl('/dashboards/admin.php');
require_once '../includes/header.php';
?>
<?php require_once '../includes/nav.php'; ?>
<main class="contenido-principal">
  <header class="seccion-encabezado">
    <div>
      <a href="<?= $volverUrl ?>" class="enlace-volver">
        <span class="material-symbols-outlined">arrow_back</span>
        Panel principal
      </a>
      <h1 class="titulo-pagina" style="margin-top:.5rem">Plano de mesas</h1>
      <p class="subtitulo-pagina">Armá la distribución del salón: agregá, mové, girá y cambiá el tamaño de las mesas.</p>
    </div>
  </header>

  <div class="plano-editor">
    <div id="canvas-wrap"><div id="plano"></div></div>

    <aside class="plano-panel">
      <section class="plano-bloque">
        <h2 class="campo-etiqueta">Agregar</h2>
        <button class="boton-secundario plano-boton" onclick="agregarMesa('cuadrada')">
          <span class="material-symbols-outlined">crop_square</span> Mesa cuadrada
        </button>
        <button class="boton-secundario plano-boton" onclick="agregarMesa('redonda')">
          <span class="material-symbols-outlined">circle</span> Mesa redonda
        </button>
      </section>

      <section id="panel-mesa" class="plano-bloque">
        <h2 class="campo-etiqueta">Mesa seleccionada</h2>
        <div class="campo-grupo">
          <label class="campo-etiqueta" for="in-numero">Número</label>
          <div class="campo-input-wrapper">
            <span class="campo-icono material-symbols-outlined">tag</span>
            <input class="campo-input" type="number" id="in-numero" min="1">
          </div>
        </div>
        <div class="campo-grupo">
          <label class="campo-etiqueta" for="in-capacidad">Capacidad (personas)</label>
          <div class="campo-input-wrapper">
            <span class="campo-icono material-symbols-outlined">group</span>
            <input class="campo-input" type="number" id="in-capacidad" min="1" max="20">
          </div>
        </div>
        <button class="boton-peligro plano-boton" onclick="eliminarSeleccionada()">
          <span class="material-symbols-outlined">delete</span> Eliminar mesa
        </button>
      </section>

      <section class="plano-bloque">
        <button class="boton-accion plano-boton" onclick="guardarPlano()">
          <span class="material-symbols-outlined">save</span> Guardar plano
        </button>
        <p id="aviso"></p>
        <p class="campo-ayuda">Arrastrá el cuerpo para mover · esquinas para cambiar el tamaño · el círculo de arriba para girar · Supr para eliminar.</p>
      </section>
    </aside>
  </div>
</main>

<script src="<?= jsUrl('admin_plano.js') ?>"></script>
<?php require_once '../includes/footer.php'; ?>
