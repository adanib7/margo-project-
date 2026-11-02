// Konva se ocupa del lienzo, el arrastre y las manijas de giro y tama?o.
// Nuestro c?digo conserva los datos de las mesas y los env?a al PHP existente.
const ANCHO = 900, ALTO = 560, GRILLA = 10;
const MIN_TAM = 30, MAX_TAM = 400;
const VERDE = '#2D5F3F', AMBAR = '#C9962E', CREMA = '#F5EFE0';
const panelMesa = document.getElementById('panel-mesa');
const inNumero = document.getElementById('in-numero');
const inCapacidad = document.getElementById('in-capacidad');
const aviso = document.getElementById('aviso');
const escenario = new Konva.Stage({ container: 'plano', width: ANCHO, height: ALTO });
const capa = new Konva.Layer();
escenario.add(capa);
const controles = new Konva.Transformer({
  enabledAnchors: ['top-left', 'top-right', 'bottom-left', 'bottom-right'],
  rotationSnaps: [0, 45, 90, 135, 180, 225, 270, 315],
  rotationSnapTolerance: 6,
  centeredScaling: true,
  flipEnabled: false,
  ignoreStroke: true,
  anchorStroke: AMBAR, anchorFill: '#fff', borderStroke: AMBAR,
});
capa.add(controles);
let seleccionada = null;
const eliminadas = [];

function limitar(valor, minimo, maximo) {
  return Math.min(Math.max(valor, minimo), maximo);
}

function actualizarTamano(grupo, ancho, alto) {
  grupo.setAttrs({ ancho, alto });
  const figura = grupo.findOne('.figura');
  if (grupo.getAttr('forma') === 'redonda') {
    figura.radiusX(ancho / 2);
    figura.radiusY(alto / 2);
  } else {
    figura.size({ width: ancho, height: alto });
    figura.offset({ x: ancho / 2, y: alto / 2 });
  }
  grupo.findOne('.numero').setAttrs({ x: -ancho / 2, y: -alto / 2, width: ancho, height: alto });
}

function limitarPosicion(grupo, ajustarGrilla = false) {
  let x = grupo.x(), y = grupo.y();
  if (ajustarGrilla) {
    x = Math.round(x / GRILLA) * GRILLA;
    y = Math.round(y / GRILLA) * GRILLA;
  }
  grupo.position({
    x: limitar(x, grupo.getAttr('ancho') / 2, ANCHO - grupo.getAttr('ancho') / 2),
    y: limitar(y, grupo.getAttr('alto') / 2, ALTO - grupo.getAttr('alto') / 2),
  });
}

function crearMesa(datos) {
  const ancho = parseFloat(datos.ancho) || 70;
  const alto = parseFloat(datos.alto) || 70;
  const forma = datos.forma === 'redonda' ? 'redonda' : 'cuadrada';
  const grupo = new Konva.Group({
    name: 'mesa', draggable: true,
    x: parseFloat(datos.pos_x) || 100,
    y: parseFloat(datos.pos_y) || 100,
    rotation: parseFloat(datos.rotacion) || 0,
    mesaId: datos.id || null,
    numero: parseInt(datos.numero, 10) || 1,
    capacidad: parseInt(datos.capacidad, 10) || 4,
    forma, ancho, alto,
  });
  const estilo = { name: 'figura', fill: VERDE, stroke: AMBAR, strokeWidth: 3 };
  const figura = forma === 'redonda' ? new Konva.Ellipse(estilo) : new Konva.Rect({ ...estilo, cornerRadius: 8 });
  const numero = new Konva.Text({
    name: 'numero', text: String(grupo.getAttr('numero')), fontFamily: 'Montserrat, sans-serif',
    fontSize: 20, fontStyle: 'bold', fill: CREMA,
    align: 'center', verticalAlign: 'middle', listening: false,
  });
  grupo.add(figura, numero);
  actualizarTamano(grupo, ancho, alto);
  grupo.on('click tap dragstart', () => seleccionar(grupo));
  grupo.on('dragmove', () => limitarPosicion(grupo));
  grupo.on('dragend', () => limitarPosicion(grupo, true));
  grupo.on('transformend', () => {
    // Transformer cambia la escala. Guardamos el tama?o real y restablecemos
    // la escala para no multiplicarla otra vez en la siguiente edici?n.
    const nuevoAncho = limitar(anchoActual(grupo), MIN_TAM, MAX_TAM);
    const nuevoAlto = limitar(altoActual(grupo), MIN_TAM, MAX_TAM);
    grupo.scale({ x: 1, y: 1 });
    actualizarTamano(grupo, nuevoAncho, nuevoAlto);
    limitarPosicion(grupo);
    controles.forceUpdate();
  });
  grupo.on('mouseenter', () => { escenario.container().style.cursor = 'grab'; });
  grupo.on('mouseleave', () => { escenario.container().style.cursor = ''; });
  capa.add(grupo);
  controles.moveToTop();
  return grupo;
}

function anchoActual(grupo) { return grupo.getAttr('ancho') * grupo.scaleX(); }
function altoActual(grupo) { return grupo.getAttr('alto') * grupo.scaleY(); }

function seleccionar(grupo) {
  seleccionada = grupo;
  controles.nodes(grupo ? [grupo] : []);
  panelMesa.classList.toggle('visible', !!grupo);
  if (grupo) {
    grupo.moveToTop();
    controles.moveToTop();
    controles.keepRatio(grupo.getAttr('forma') === 'redonda');
    inNumero.value = grupo.getAttr('numero');
    inCapacidad.value = grupo.getAttr('capacidad');
  }
}

escenario.on('mousedown touchstart', () => escenario.container().focus({ preventScroll: true }));
escenario.on('click tap', e => {
  if (e.target === escenario) seleccionar(null);
});
document.addEventListener('keydown', e => {
  if (['INPUT', 'TEXTAREA', 'BUTTON'].includes(document.activeElement.tagName)) return;
  if ((e.key === 'Delete' || e.key === 'Backspace') && seleccionada) {
    e.preventDefault();
    eliminarSeleccionada();
  } else if (e.key === 'Escape') seleccionar(null);
});
inNumero.addEventListener('input', e => {
  if (!seleccionada) return;
  const numero = parseInt(e.target.value, 10) || 1;
  seleccionada.setAttr('numero', numero);
  seleccionada.findOne('.numero').text(String(numero));
});
inCapacidad.addEventListener('input', e => {
  if (seleccionada) seleccionada.setAttr('capacidad', parseInt(e.target.value, 10) || 1);
});

function agregarMesa(forma) {
  let numero = 1;
  for (const mesa of capa.find('.mesa')) {
    numero = Math.max(numero, mesa.getAttr('numero') + 1);
  }
  seleccionar(crearMesa({ numero, capacidad: 4, forma, pos_x: ANCHO / 2, pos_y: ALTO / 2, ancho: 70, alto: 70 }));
}

function eliminarSeleccionada() {
  if (!seleccionada) return;
  if (seleccionada.getAttr('mesaId')) eliminadas.push(seleccionada.getAttr('mesaId'));
  controles.nodes([]);
  seleccionada.destroy();
  seleccionar(null);
}

async function cargarPlano() {
  try {
    const respuesta = await fetch(window.location.pathname + '?action=cargar');
    const mesas = await respuesta.json();
    if (!respuesta.ok || !Array.isArray(mesas)) throw new Error(mesas.error || mesas.mensaje || 'No se pudo cargar el plano.');
    seleccionar(null);
    for (const mesa of capa.find('.mesa')) mesa.destroy();
    for (const datos of mesas) crearMesa(datos);
  } catch (error) {
    aviso.style.color = 'var(--rojo)';
    aviso.textContent = error.message;
  }
}

async function guardarPlano() {
  const mesas = [];
  for (const grupo of capa.find('.mesa')) {
    mesas.push({
      id: grupo.getAttr('mesaId'), numero: grupo.getAttr('numero'),
      capacidad: grupo.getAttr('capacidad'), forma: grupo.getAttr('forma'),
      pos_x: grupo.x(), pos_y: grupo.y(),
      ancho: anchoActual(grupo), alto: altoActual(grupo), rotacion: grupo.rotation(),
    });
  }
  try {
    const respuesta = await fetch(window.location.pathname, {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'guardar', mesas, eliminadas }),
    });
    const datos = await respuesta.json();
    if (!respuesta.ok || !datos.ok) throw new Error(datos.error || datos.mensaje || 'No se pudo guardar el plano.');
    aviso.style.color = 'var(--verde)';
    aviso.textContent = datos.no_eliminadas?.length
      ? 'Guardado. Algunas mesas no se borraron porque tienen reservas.' : 'Plano guardado.';
    eliminadas.length = 0;
    await cargarPlano();
  } catch (error) {
    aviso.style.color = 'var(--rojo)';
    aviso.textContent = error.message;
  }
}

cargarPlano();
