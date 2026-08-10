<?php

require_once __DIR__ . '/../includes/config.php';

function ensureMesaTable($conn): void
{
    $conn->query("
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
        $stmt = $conn->prepare("SHOW COLUMNS FROM mesas LIKE ?");
        if (!$stmt) {
            continue;
        }
        $stmt->bind_param('s', $col);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows === 0) {
            $conn->query("ALTER TABLE mesas ADD COLUMN {$col} {$definition}");
        }
        $stmt->close();
    }
}

function hasReservation($conn, int $mesaId): bool
{
    $stmt = $conn->prepare("SELECT 1 FROM reservas WHERE mesa_id = ? LIMIT 1");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $mesaId);
    $stmt->execute();
    $stmt->store_result();
    $has = $stmt->num_rows > 0;
    $stmt->close();
    return $has;
}

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
            if ($id > 0 && hasReservation($conn, $id)) {
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

<script>
const ANCHO = 900, ALTO = 560, GRILLA = 10;
const MIN_TAM = 30, MAX_TAM = 400;

const plano = document.getElementById('plano');
const panelMesa = document.getElementById('panel-mesa');
const inNumero = document.getElementById('in-numero');
const inCapacidad = document.getElementById('in-capacidad');
const aviso = document.getElementById('aviso');

let seleccionada = null;
const eliminadas = [];

function clamp(v, min, max) { return Math.min(Math.max(v, min), max); }
function normalizarAngulo(a) { a = a % 360; return a < 0 ? a + 360 : a; }

/* Aplica x,y (centro), ancho, alto y rotación al estilo del div */
function renderMesa(el) {
  el.style.width = el._w + 'px';
  el.style.height = el._h + 'px';
  el.style.left = (el._x - el._w / 2) + 'px';
  el.style.top = (el._y - el._h / 2) + 'px';
  el.style.transform = `rotate(${el._rot}deg)`;
}

function setGeom(el, cambios) {
  Object.assign(el, {
    _x: cambios.x ?? el._x,
    _y: cambios.y ?? el._y,
    _w: cambios.w ?? el._w,
    _h: cambios.h ?? el._h,
    _rot: cambios.rot ?? el._rot,
  });
  renderMesa(el);
}

function crearMesa(datos) {
  const el = document.createElement('div');
  const forma = datos.forma === 'redonda' ? 'redonda' : 'cuadrada';
  el.className = 'mesa ' + forma;
  el.dataset.mesaId = datos.id || '';
  el.dataset.numero = datos.numero ?? 1;
  el.dataset.capacidad = datos.capacidad ?? 4;
  el.dataset.forma = forma;

  const numero = document.createElement('span');
  numero.className = 'mesa-numero';
  numero.textContent = datos.numero ?? 1;
  el.appendChild(numero);

  ['tl', 'tr', 'bl', 'br'].forEach(pos => {
    const h = document.createElement('div');
    h.className = `handle esquina ${pos}`;
    h.dataset.handle = pos;
    el.appendChild(h);
    activarResize(el, h);
  });

  const rotHandle = document.createElement('div');
  rotHandle.className = 'handle rotar';
  el.appendChild(rotHandle);
  activarRotacion(el, rotHandle);

  el._x = parseFloat(datos.pos_x) || 100;
  el._y = parseFloat(datos.pos_y) || 100;
  el._w = parseFloat(datos.ancho) || 70;
  el._h = parseFloat(datos.alto) || 70;
  el._rot = parseFloat(datos.rotacion) || 0;
  renderMesa(el);

  plano.appendChild(el);
  activarArrastre(el);
  el.addEventListener('pointerdown', () => seleccionar(el));

  return el;
}

function agregarMesa(forma) {
  const numeros = Array.from(plano.querySelectorAll('.mesa')).map(el => parseInt(el.dataset.numero) || 0);
  const el = crearMesa({
    numero: (numeros.length ? Math.max(...numeros) : 0) + 1,
    capacidad: 4,
    forma,
    pos_x: ANCHO / 2,
    pos_y: ALTO / 2,
    ancho: 70,
    alto: 70,
  });
  seleccionar(el);
}

function seleccionar(el) {
  if (seleccionada === el) return;
  if (seleccionada) seleccionada.classList.remove('seleccionada');
  seleccionada = el;
  if (el) {
    el.classList.add('seleccionada');
    plano.appendChild(el); // trae al frente
  }
  panelMesa.classList.toggle('visible', !!el);
  if (el) {
    inNumero.value = el.dataset.numero;
    inCapacidad.value = el.dataset.capacidad;
  }
}

/* Clic en el fondo del plano deselecciona */
plano.addEventListener('pointerdown', e => {
  if (e.target === plano) seleccionar(null);
});

document.addEventListener('keydown', e => {
  if (document.activeElement.tagName === 'INPUT') return;
  if ((e.key === 'Delete' || e.key === 'Backspace') && seleccionada) {
    eliminarSeleccionada();
  } else if (e.key === 'Escape') {
    seleccionar(null);
  }
});

inNumero.addEventListener('input', e => {
  if (!seleccionada) return;
  const v = parseInt(e.target.value) || 1;
  seleccionada.dataset.numero = v;
  seleccionada.querySelector('.mesa-numero').textContent = v;
});

inCapacidad.addEventListener('input', e => {
  if (!seleccionada) return;
  seleccionada.dataset.capacidad = parseInt(e.target.value) || 1;
});

function eliminarSeleccionada() {
  if (!seleccionada) return;
  if (seleccionada.dataset.mesaId) eliminadas.push(seleccionada.dataset.mesaId);
  seleccionada.remove();
  seleccionar(null);
}

/* --- Arrastrar para mover --- */
function activarArrastre(el) {
  el.addEventListener('pointerdown', e => {
    if (e.target.classList.contains('handle')) return;
    e.stopPropagation();
    el.setPointerCapture(e.pointerId);

    const startMouseX = e.clientX, startMouseY = e.clientY;
    const startX = el._x, startY = el._y;

    function mover(ev) {
      const nx = clamp(startX + (ev.clientX - startMouseX), el._w / 2, ANCHO - el._w / 2);
      const ny = clamp(startY + (ev.clientY - startMouseY), el._h / 2, ALTO - el._h / 2);
      setGeom(el, { x: nx, y: ny });
    }
    function soltar() {
      setGeom(el, {
        x: clamp(Math.round(el._x / GRILLA) * GRILLA, el._w / 2, ANCHO - el._w / 2),
        y: clamp(Math.round(el._y / GRILLA) * GRILLA, el._h / 2, ALTO - el._h / 2),
      });
      el.removeEventListener('pointermove', mover);
      el.removeEventListener('pointerup', soltar);
    }
    el.addEventListener('pointermove', mover);
    el.addEventListener('pointerup', soltar);
  });
}

/* --- Manijas de esquina: redimensionar (desde el centro, respeta la rotación) --- */
function activarResize(el, handle) {
  handle.addEventListener('pointerdown', e => {
    e.stopPropagation();
    seleccionar(el);
    handle.setPointerCapture(e.pointerId);
    const rectContenedor = plano.getBoundingClientRect();
    const esCirculo = el.dataset.forma === 'redonda';

    function coordLocal(ev) {
      const mx = ev.clientX - rectContenedor.left;
      const my = ev.clientY - rectContenedor.top;
      const dx = mx - el._x, dy = my - el._y;
      const rad = -el._rot * Math.PI / 180;
      return {
        lx: dx * Math.cos(rad) - dy * Math.sin(rad),
        ly: dx * Math.sin(rad) + dy * Math.cos(rad),
      };
    }

    function mover(ev) {
      const { lx, ly } = coordLocal(ev);
      if (esCirculo) {
        const d = clamp(Math.hypot(lx, ly) * 2, MIN_TAM, MAX_TAM);
        setGeom(el, { w: d, h: d });
      } else {
        setGeom(el, {
          w: clamp(Math.abs(lx) * 2, MIN_TAM, MAX_TAM),
          h: clamp(Math.abs(ly) * 2, MIN_TAM, MAX_TAM),
        });
      }
    }
    function soltar() {
      handle.removeEventListener('pointermove', mover);
      handle.removeEventListener('pointerup', soltar);
    }
    handle.addEventListener('pointermove', mover);
    handle.addEventListener('pointerup', soltar);
  });
}

/* --- Manija superior: rotar (con imán cada 45°) --- */
function activarRotacion(el, handle) {
  handle.addEventListener('pointerdown', e => {
    e.stopPropagation();
    seleccionar(el);
    handle.setPointerCapture(e.pointerId);
    const rectContenedor = plano.getBoundingClientRect();

    function mover(ev) {
      const mx = ev.clientX - rectContenedor.left;
      const my = ev.clientY - rectContenedor.top;
      let ang = normalizarAngulo(Math.atan2(my - el._y, mx - el._x) * 180 / Math.PI + 90);

      const cercano45 = Math.round(ang / 45) * 45 % 360;
      const dif = Math.min(Math.abs(ang - cercano45), 360 - Math.abs(ang - cercano45));
      if (dif < 6) ang = cercano45;

      setGeom(el, { rot: ang });
    }
    function soltar() {
      handle.removeEventListener('pointermove', mover);
      handle.removeEventListener('pointerup', soltar);
    }
    handle.addEventListener('pointermove', mover);
    handle.addEventListener('pointerup', soltar);
  });
}

/* --- Cargar / Guardar --- */
async function cargarPlano() {
  const res = await fetch('admin_plano.php?action=cargar');
  const mesas = await res.json();
  mesas.forEach(crearMesa);
}

async function guardarPlano() {
  const mesas = Array.from(plano.querySelectorAll('.mesa')).map(el => ({
    id: el.dataset.mesaId || null,
    numero: el.dataset.numero,
    capacidad: el.dataset.capacidad,
    forma: el.dataset.forma,
    pos_x: el._x,
    pos_y: el._y,
    ancho: el._w,
    alto: el._h,
    rotacion: el._rot,
  }));

  const res = await fetch('admin_plano.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'guardar', mesas, eliminadas }),
  });

  const datos = await res.json();

  if (datos.ok) {
    aviso.style.color = 'var(--verde)';
    aviso.textContent = datos.no_eliminadas?.length
      ? 'Guardado. Algunas mesas no se borraron porque tienen reservas.'
      : 'Plano guardado ✓';

    plano.querySelectorAll('.mesa').forEach(el => el.remove());
    eliminadas.length = 0;
    seleccionar(null);
    cargarPlano();
  } else {
    aviso.style.color = 'var(--rojo)';
    aviso.textContent = datos.error || 'Error al guardar';
  }
}

cargarPlano();
</script>
</body>
</html>