<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reservar mesa · El Corralín</title>
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
  }
  header {
    background: var(--verde);
    color: var(--crema);
    padding: 14px 28px;
  }
  header h1 { font-size: 1.3rem; font-weight: normal; letter-spacing: 1px; }
  .contenido { padding: 20px 28px; max-width: 980px; margin: 0 auto; }
  .filtros {
    display: flex;
    gap: 14px;
    align-items: flex-end;
    flex-wrap: wrap;
    margin-bottom: 16px;
    font-family: Arial, sans-serif;
  }
  .filtros label { display: block; font-size: .75rem; margin-bottom: 3px; }
  .filtros input, .filtros select {
    padding: 8px;
    border: 1.5px solid var(--verde);
    border-radius: 4px;
    background: #fff;
    font-size: .9rem;
  }
  .leyenda {
    display: flex;
    gap: 18px;
    font-size: .8rem;
    font-family: Arial, sans-serif;
    margin-left: auto;
  }
  .leyenda span::before {
    content: '';
    display: inline-block;
    width: 12px; height: 12px;
    border-radius: 3px;
    margin-right: 5px;
    vertical-align: -1px;
  }
  .libre::before { background: var(--verde); }
  .ocupada::before { background: #b9b3a4; }
  .elegida::before { background: var(--ambar); }
  #canvas-wrap {
    background: #fff;
    border: 2px solid var(--verde);
    border-radius: 6px;
    overflow: auto;
  }
  #confirmar {
    display: none;
    margin-top: 16px;
    padding: 14px 18px;
    background: #fff;
    border: 2px solid var(--ambar);
    border-radius: 6px;
    align-items: center;
    gap: 16px;
    font-family: Arial, sans-serif;
  }
  #confirmar.visible { display: flex; flex-wrap: wrap; }
  #confirmar button {
    padding: 10px 22px;
    background: var(--ambar);
    color: #fff;
    border: none;
    border-radius: 4px;
    font-weight: bold;
    cursor: pointer;
  }
  #confirmar button:hover { filter: brightness(.92); }
</style>
</head>
<body>

<header><h1>El Corralín de Campanal — Reservá tu mesa</h1></header>

<div class="contenido">
  <div class="filtros">
    <div>
      <label for="f-fecha">Fecha</label>
      <input type="date" id="f-fecha">
    </div>
    <div>
      <label for="f-hora">Hora</label>
      <select id="f-hora">
        <option>13:00</option><option>13:30</option><option>14:00</option>
        <option>20:00</option><option>20:30</option><option>21:00</option>
        <option>21:30</option><option>22:00</option>
      </select>
    </div>
    <div class="leyenda">
      <span class="libre">Libre</span>
      <span class="ocupada">Ocupada</span>
      <span class="elegida">Tu elección</span>
    </div>
  </div>

  <div id="canvas-wrap"><div id="plano"></div></div>

  <div id="confirmar">
    <span id="txt-mesa"></span>
    <button onclick="confirmarReserva()">Reservar esta mesa</button>
  </div>
</div>

<script>
const ANCHO = 900, ALTO = 560;
const VERDE = '#2D5F3F', AMBAR = '#C9962E', CREMA = '#F5EFE0', GRIS = '#b9b3a4';

const stage = new Konva.Stage({ container: 'plano', width: ANCHO, height: ALTO });
const capa = new Konva.Layer();
stage.add(capa);

let mesaElegida = null;

function dibujarPlano(mesas) {
  capa.destroyChildren();
  capa.add(new Konva.Rect({ width: ANCHO, height: ALTO, fill: CREMA, listening: false }));

  mesas.forEach(m => {
    const grupo = new Konva.Group({
      x: parseFloat(m.pos_x), y: parseFloat(m.pos_y),
      rotation: parseFloat(m.rotacion)
    });

    const color = m.ocupada ? GRIS : VERDE;
    let figura;
    if (m.forma === 'redonda') {
      figura = new Konva.Circle({
        radius: m.ancho / 2,
        fill: color, stroke: AMBAR, strokeWidth: 3
      });
    } else {
      figura = new Konva.Rect({
        width: parseFloat(m.ancho), height: parseFloat(m.alto),
        offsetX: m.ancho / 2, offsetY: m.alto / 2,
        cornerRadius: 8,
        fill: color, stroke: AMBAR, strokeWidth: 3
      });
    }

    const texto = new Konva.Text({
      text: String(m.numero),
      fontSize: 20, fontStyle: 'bold',
      fontFamily: 'Georgia', fill: CREMA,
      listening: false
    });
    texto.offsetX(texto.width() / 2);
    texto.offsetY(texto.height() / 2);
    grupo.add(figura, texto);

    if (!m.ocupada) {
      grupo.on('mouseenter', () => { stage.container().style.cursor = 'pointer'; });
      grupo.on('mouseleave', () => { stage.container().style.cursor = 'default'; });
      grupo.on('click tap', () => elegirMesa(m, figura));
    }

    capa.add(grupo);
  });
}

function elegirMesa(mesa, figura) {
  // despintamos la anterior
  if (mesaElegida) mesaElegida.figura.fill(VERDE);
  mesaElegida = { datos: mesa, figura };
  figura.fill(AMBAR);

  document.getElementById('confirmar').classList.add('visible');
  document.getElementById('txt-mesa').textContent =
    `Mesa ${mesa.numero} · hasta ${mesa.capacidad} personas · ` +
    `${document.getElementById('f-fecha').value} ${document.getElementById('f-hora').value} hs`;
}

async function cargar() {
  const fecha = document.getElementById('f-fecha').value;
  const hora = document.getElementById('f-hora').value;
  if (!fecha) return;

  mesaElegida = null;
  document.getElementById('confirmar').classList.remove('visible');

  const res = await fetch(`cargar_plano.php?fecha=${fecha}&hora=${encodeURIComponent(hora)}`);
  dibujarPlano(await res.json());
}

function confirmarReserva() {
  if (!mesaElegida) return;
  const fecha = document.getElementById('f-fecha').value;
  const hora = document.getElementById('f-hora').value;
  // Acá conectás con tu flujo de reserva real. Lo más simple es
  // mandar al form de reserva con los datos ya elegidos:
  window.location.href =
    `reservar.php?id_mesa=${mesaElegida.datos.id}&fecha=${fecha}&hora=${encodeURIComponent(hora)}`;
}

// fecha de hoy por defecto y carga inicial
const hoy = new Date().toISOString().split('T')[0];
document.getElementById('f-fecha').value = hoy;
document.getElementById('f-fecha').min = hoy;
document.getElementById('f-fecha').addEventListener('change', cargar);
document.getElementById('f-hora').addEventListener('change', cargar);
cargar();
</script>
</body>
</html>
