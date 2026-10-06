<?php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/plano_db.php';

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
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Plano de mesas · El Corralín</title>
<style>
  :root {
    --verde: #2D5F3F;
    --verde-claro: #3d7d54;
    --ambar: #C9962E;
    --crema: #F5EFE0;
    --rojo: #a33;
  }
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body {
    font-family: Georgia, 'Times New Roman', serif;
    background: var(--crema);
    color: var(--verde);
    min-height: 100vh;
  }
  header {
    background: var(--verde);
    color: var(--crema);
    padding: 14px 28px;
    display: flex;
    align-items: baseline;
    gap: 14px;
  }
  header h1 { font-size: 1.3rem; font-weight: normal; letter-spacing: 1px; }
  header span { font-size: .8rem; opacity: .7; font-family: Arial, sans-serif; }
  .contenido {
    display: flex;
    gap: 20px;
    padding: 20px 28px;
    align-items: flex-start;
    flex-wrap: wrap;
  }
  #canvas-wrap {
    background: #fff;
    border: 2px solid var(--verde);
    border-radius: 6px;
    overflow: hidden;
    line-height: 0;
  }

  /* --- Lienzo del plano, ahora un div con grilla via CSS --- */
  #plano {
    position: relative;
    width: 900px;
    height: 560px;
    background-color: #fff;
    background-image:
      linear-gradient(#e9e2cc 1px, transparent 1px),
      linear-gradient(90deg, #e9e2cc 1px, transparent 1px);
    background-size: 40px 40px;
    touch-action: none;
    user-select: none;
  }

  aside {
    width: 230px;
    display: flex;
    flex-direction: column;
    gap: 10px;
  }
  aside h2 {
    font-size: .75rem;
    font-family: Arial, sans-serif;
    text-transform: uppercase;
    letter-spacing: 2px;
    color: var(--ambar);
    margin-top: 8px;
  }
  button {
    font-family: Arial, sans-serif;
    font-size: .85rem;
    padding: 9px 12px;
    border: 1.5px solid var(--verde);
    background: transparent;
    color: var(--verde);
    border-radius: 4px;
    cursor: pointer;
    text-align: left;
  }
  button:hover { background: var(--verde); color: var(--crema); }
  button.principal {
    background: var(--ambar);
    border-color: var(--ambar);
    color: #fff;
    font-weight: bold;
    text-align: center;
  }
  button.principal:hover { filter: brightness(.92); }
  button.peligro { border-color: var(--rojo); color: var(--rojo); }
  button.peligro:hover { background: var(--rojo); color: #fff; }
  .campo { display: flex; flex-direction: column; gap: 3px; }
  .campo label { font-size: .75rem; font-family: Arial, sans-serif; }
  .campo input {
    padding: 7px;
    border: 1.5px solid var(--verde);
    border-radius: 4px;
    background: #fff;
    font-size: .9rem;
    font-family: Arial, sans-serif;
  }
  #panel-mesa { display: none; }
  #panel-mesa.visible { display: flex; flex-direction: column; gap: 10px; }
  #aviso {
    font-size: .8rem;
    font-family: Arial, sans-serif;
    min-height: 1.2em;
  }
  #ayuda {
    font-size: .72rem;
    font-family: Arial, sans-serif;
    color: #7a7256;
    line-height: 1.4;
  }

  /* --- Mesas --- */
  .mesa {
    position: absolute;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--verde);
    border: 3px solid var(--ambar);
    cursor: grab;
    box-sizing: border-box;
    touch-action: none;
  }
  .mesa:active { cursor: grabbing; }
  .mesa.cuadrada { border-radius: 8px; }
  .mesa.redonda { border-radius: 50%; }
  .mesa.seleccionada {
    box-shadow: 0 0 0 2px #fff, 0 0 0 4px var(--ambar);
  }
  .mesa-numero {
    font-family: Georgia, serif;
    font-weight: bold;
    font-size: 20px;
    color: var(--crema);
    pointer-events: none;
    line-height: 1;
  }

  /* --- Controles (aparecen solo si la mesa está seleccionada) --- */
  .handle { position: absolute; display: none; z-index: 5; }
  .mesa.seleccionada .handle { display: block; }

  .handle.esquina {
    width: 13px; height: 13px;
    background: #fff;
    border: 2px solid var(--ambar);
    border-radius: 50%;
    transform: translate(-50%, -50%);
  }
  .handle.tl { top: 0;   left: 0;   cursor: nwse-resize; }
  .handle.tr { top: 0;   left: 100%; cursor: nesw-resize; }
  .handle.bl { top: 100%; left: 0;   cursor: nesw-resize; }
  .handle.br { top: 100%; left: 100%; cursor: nwse-resize; }

  .handle.rotar {
    width: 13px; height: 13px;
    background: #fff;
    border: 2px solid var(--verde);
    border-radius: 50%;
    top: -30px; left: 50%;
    transform: translate(-50%, -50%);
    cursor: grab;
  }
  .handle.rotar::after {
    content: '';
    position: absolute;
    width: 2px; height: 22px;
    background: var(--ambar);
    left: 50%; top: 100%;
    transform: translateX(-50%);
  }
</style>
</head>
<body>

<header>
  <h1>El Corralín de Campanal</h1>
  <span>Editor del plano de mesas</span>
</header>

<div class="contenido">
  <div id="canvas-wrap"><div id="plano"></div></div>

  <aside>
    <h2>Agregar</h2>
    <button onclick="agregarMesa('cuadrada')">＋ Mesa cuadrada</button>
    <button onclick="agregarMesa('redonda')">＋ Mesa redonda</button>

    <div id="panel-mesa">
      <h2>Mesa seleccionada</h2>
      <div class="campo">
        <label>Número</label>
        <input type="number" id="in-numero" min="1">
      </div>
      <div class="campo">
        <label>Capacidad (personas)</label>
        <input type="number" id="in-capacidad" min="1" max="20">
      </div>
      <button class="peligro" onclick="eliminarSeleccionada()">Eliminar mesa</button>
    </div>

    <h2>Plano</h2>
    <button class="principal" onclick="guardarPlano()">Guardar plano</button>
    <p id="aviso"></p>
    <p id="ayuda">Arrastrá el cuerpo para mover · esquinas para cambiar el tamaño · el círculo de arriba para girar · Supr para eliminar.</p>
  </aside>
</div>

<script src="<?= jsUrl('admin_plano.js') ?>"></script>
</body>
</html>