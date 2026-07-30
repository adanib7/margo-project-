<?php
<?php

function getPdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = getenv('DB_HOST') ?: 'localhost';
    $db   = getenv('DB_NAME') ?: 'margo';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '';

    $pdo = new PDO("mysql:host={$host};dbname={$db};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function ensureMesaTable(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS mesas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            numero INT NOT NULL DEFAULT 1,
            capacidad INT NOT NULL DEFAULT 4,
            forma VARCHAR(20) NOT NULL DEFAULT 'cuadrada',
            pos_x DOUBLE NOT NULL DEFAULT 100,
            pos_y DOUBLE NOT NULL DEFAULT 100,
            ancho DOUBLE NOT NULL DEFAULT 70,
            alto DOUBLE NOT NULL DEFAULT 70,
            rotacion DOUBLE NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $columns = [
        ['numero', 'INT NOT NULL DEFAULT 1'],
        ['capacidad', 'INT NOT NULL DEFAULT 4'],
        ['forma', "VARCHAR(20) NOT NULL DEFAULT 'cuadrada'"],
        ['pos_x', 'DOUBLE NOT NULL DEFAULT 100'],
        ['pos_y', 'DOUBLE NOT NULL DEFAULT 100'],
        ['ancho', 'DOUBLE NOT NULL DEFAULT 70'],
        ['alto', 'DOUBLE NOT NULL DEFAULT 70'],
        ['rotacion', 'DOUBLE NOT NULL DEFAULT 0'],
    ];

    foreach ($columns as [$col, $definition]) {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM mesas LIKE ?");
        $stmt->execute([$col]);
        if ($stmt->rowCount() === 0) {
            $pdo->exec("ALTER TABLE mesas ADD COLUMN {$col} {$definition}");
        }
    }
}

function hasReservation(PDO $pdo, int $mesaId): bool
{
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM reservas WHERE mesa_id = ? LIMIT 1");
        $stmt->execute([$mesaId]);
        return (bool) $stmt->fetch();
    } catch (PDOException $e) {
        return false;
    }
}

function sendJson(array $data): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = getPdo();
ensureMesaTable($pdo);

$action = '';
if (isset($_GET['action'])) {
    $action = $_GET['action'];
}

if ($action === 'cargar') {
    $stmt = $pdo->query("SELECT id, numero, capacidad, forma, pos_x, pos_y, ancho, alto, rotacion FROM mesas ORDER BY id ASC");
    sendJson($stmt->fetchAll());
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
                $stmt = $pdo->prepare("
                    UPDATE mesas
                    SET numero = ?, capacidad = ?, forma = ?, pos_x = ?, pos_y = ?, ancho = ?, alto = ?, rotacion = ?
                    WHERE id = ?
                ");
                $stmt->execute([$numero, $capacidad, $forma, $posX, $posY, $ancho, $alto, $rotacion, $id]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO mesas (numero, capacidad, forma, pos_x, pos_y, ancho, alto, rotacion)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$numero, $capacidad, $forma, $posX, $posY, $ancho, $alto, $rotacion]);
            }
        }

        $noEliminadas = [];
        foreach ($eliminadas as $mesaId) {
            $id = (int) $mesaId;
            if ($id > 0 && hasReservation($pdo, $id)) {
                $noEliminadas[] = $id;
            } else {
                $stmt = $pdo->prepare("DELETE FROM mesas WHERE id = ?");
                $stmt->execute([$id]);
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
<script src="https://unpkg.com/konva@9/konva.min.js"></script>
<style>
  :root {
    --verde: #2D5F3F;
    --ambar: #C9962E;
    --crema: #F5EFE0;
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
  button.peligro { border-color: #a33; color: #a33; }
  button.peligro:hover { background: #a33; color: #fff; }
  .campo { display: flex; flex-direction: column; gap: 3px; }
  .campo label { font-size: .75rem; font-family: Arial, sans-serif; }
  .campo input {
    padding: 7px;
    border: 1.5px solid var(--verde);
    border-radius: 4px;
    background: #fff;
    font-size: .9rem;
  }
  #panel-mesa { display: none; }
  #panel-mesa.visible { display: flex; flex-direction: column; gap: 10px; }
  #aviso {
    font-size: .8rem;
    font-family: Arial, sans-serif;
    min-height: 1.2em;
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
  </aside>
</div>

<script>
const ANCHO = 900, ALTO = 560, GRILLA = 10;
const VERDE = '#2D5F3F', AMBAR = '#C9962E', CREMA = '#F5EFE0';

const stage = new Konva.Stage({ container: 'plano', width: ANCHO, height: ALTO });
const capa = new Konva.Layer();
stage.add(capa);

const fondo = new Konva.Rect({ width: ANCHO, height: ALTO, fill: CREMA, listening: true });
capa.add(fondo);

for (let x = GRILLA * 4; x < ANCHO; x += GRILLA * 4) {
  capa.add(new Konva.Line({ points: [x, 0, x, ALTO], stroke: '#e3dcc8', strokeWidth: 1, listening: false }));
}
for (let y = GRILLA * 4; y < ALTO; y += GRILLA * 4) {
  capa.add(new Konva.Line({ points: [0, y, ANCHO, y], stroke: '#e3dcc8', strokeWidth: 1, listening: false }));
}

const trans = new Konva.Transformer({
  rotationSnaps: [0, 45, 90, 135, 180, 225, 270, 315],
  anchorStroke: AMBAR, anchorFill: '#fff', borderStroke: AMBAR,
  enabledAnchors: ['top-left', 'top-right', 'bottom-left', 'bottom-right']
});
capa.add(trans);

let seleccionada = null;
const eliminadas = [];

function crearMesa(datos) {
  const ancho = parseFloat(datos.ancho) || 70;
  const alto = parseFloat(datos.alto) || 70;

  const grupo = new Konva.Group({
    x: parseFloat(datos.pos_x) || 100,
    y: parseFloat(datos.pos_y) || 100,
    rotation: parseFloat(datos.rotacion) || 0,
    draggable: true
  });

  grupo.setAttrs({
    mesaId: datos.id || null,
    numero: parseInt(datos.numero) || 1,
    capacidad: parseInt(datos.capacidad) || 4,
    forma: datos.forma || 'cuadrada'
  });

  let figura;
  if (grupo.getAttr('forma') === 'redonda') {
    figura = new Konva.Circle({
      radius: ancho / 2,
      fill: VERDE, stroke: AMBAR, strokeWidth: 3
    });
  } else {
    figura = new Konva.Rect({
      width: ancho, height: alto,
      offsetX: ancho / 2, offsetY: alto / 2,
      cornerRadius: 8,
      fill: VERDE, stroke: AMBAR, strokeWidth: 3
    });
  }

  const texto = new Konva.Text({
    text: String(grupo.getAttr('numero')),
    fontSize: 20, fontStyle: 'bold',
    fontFamily: 'Georgia', fill: CREMA,
    listening: false
  });
  texto.offsetX(texto.width() / 2);
  texto.offsetY(texto.height() / 2);

  grupo.add(figura, texto);
  grupo.on('click tap', () => seleccionar(grupo));

  grupo.on('dragend', () => {
    grupo.x(Math.round(Math.min(Math.max(grupo.x(), 30), ANCHO - 30) / GRILLA) * GRILLA);
    grupo.y(Math.round(Math.min(Math.max(grupo.y(), 30), ALTO - 30) / GRILLA) * GRILLA);
  });

  grupo.on('transformend', () => {
    const sx = grupo.scaleX();
    const sy = grupo.scaleY();
    if (figura.className === 'Circle') {
      figura.radius(figura.radius() * sx);
    } else {
      figura.width(figura.width() * sx);
      figura.height(figura.height() * sy);
      figura.offsetX(figura.width() / 2);
      figura.offsetY(figura.height() / 2);
    }
    grupo.scale({ x: 1, y: 1 });
    grupo.scaleY(sy);
  });

  capa.add(grupo);
  return grupo;
}

function agregarMesa(forma) {
  const numeros = capa.find('Group').map(g => g.getAttr('numero') || 0);
  const grupo = crearMesa({
    numero: Math.max(0, ...numeros) + 1,
    capacidad: 4,
    forma,
    pos_x: ANCHO / 2,
    pos_y: ALTO / 2,
    ancho: 70,
    alto: 70
  });
  seleccionar(grupo);
}

function seleccionar(grupo) {
  seleccionada = grupo;
  trans.nodes(grupo ? [grupo] : []);
  const panel = document.getElementById('panel-mesa');
  panel.classList.toggle('visible', !!grupo);
  if (grupo) {
    document.getElementById('in-numero').value = grupo.getAttr('numero');
    document.getElementById('in-capacidad').value = grupo.getAttr('capacidad');
  }
}

fondo.on('click tap', () => seleccionar(null));

document.getElementById('in-numero').addEventListener('input', e => {
  if (!seleccionada) return;
  seleccionada.setAttr('numero', parseInt(e.target.value) || 1);
  const t = seleccionada.findOne('Text');
  t.text(String(seleccionada.getAttr('numero')));
  t.offsetX(t.width() / 2);
});

document.getElementById('in-capacidad').addEventListener('input', e => {
  if (!seleccionada) return;
  seleccionada.setAttr('capacidad', parseInt(e.target.value) || 1);
});

function eliminarSeleccionada() {
  if (!seleccionada) return;
  if (seleccionada.getAttr('mesaId')) eliminadas.push(seleccionada.getAttr('mesaId'));
  seleccionada.destroy();
  seleccionar(null);
}

async function cargarPlano() {
  const res = await fetch('admin_plano.php?action=cargar');
  const mesas = await res.json();
  mesas.forEach(crearMesa);
}

async function guardarPlano() {
  const aviso = document.getElementById('aviso');
  const mesas = capa.find('Group').map(g => {
    const fig = g.findOne('Circle') || g.findOne('Rect');
    const esCirculo = fig.className === 'Circle';
    return {
      id: g.getAttr('mesaId'),
      numero: g.getAttr('numero'),
      capacidad: g.getAttr('capacidad'),
      forma: g.getAttr('forma'),
      pos_x: g.x(),
      pos_y: g.y(),
      ancho: esCirculo ? fig.radius() * 2 : fig.width(),
      alto: esCirculo ? fig.radius() * 2 : fig.height(),
      rotacion: g.rotation()
    };
  });

  const res = await fetch('admin_plano.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'guardar', mesas, eliminadas })
  });

  const datos = await res.json();

  if (datos.ok) {
    aviso.style.color = VERDE;
    aviso.textContent = datos.no_eliminadas?.length
      ? 'Guardado. Algunas mesas no se borraron porque tienen reservas.'
      : 'Plano guardado ✓';

    capa.find('Group').forEach(g => g.destroy());
    eliminadas.length = 0;
    seleccionar(null);
    cargarPlano();
  } else {
    aviso.style.color = '#a33';
    aviso.textContent = datos.error || 'Error al guardar';
  }
}

cargarPlano();
</script>
</body>
</html>