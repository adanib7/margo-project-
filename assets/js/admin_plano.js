// Konva se ocupa del lienzo, el arrastre y las manijas de giro y tamaño.
// Nuestro código conserva los datos de las mesas y los envía al PHP existente.
const ANCHO = 900, ALTO = 560, GRILLA = 10;
const MIN_TAM = 30, MAX_TAM = 400;
const VERDE = '#2D5F3F', AMBAR = '#C9962E', CREMA = '#F5EFE0';
const panelMesa = document.getElementById('panel-mesa');
const inNumero = document.getElementById('in-numero');
const inCapacidad = document.getElementById('in-capacidad');
const inRotacion = document.getElementById('in-rotacion');
const inAncho = document.getElementById('in-ancho');
const inAlto = document.getElementById('in-alto');
const aviso = document.getElementById('aviso');
const selectorMesa = document.getElementById('selector-mesa');
const contenedorPlano = document.getElementById('canvas-wrap');
const instruccion = document.getElementById('planoInstruccion');
const ajusteMesa = document.getElementById('planoAjuste');
const escenario = new Konva.Stage({ container: 'plano', width: ANCHO, height: ALTO });
const capa = new Konva.Layer();
escenario.add(capa);
const controles = new Konva.Transformer({
  resizeEnabled: false, rotateEnabled: false, listening: false,
  enabledAnchors: ['top-left', 'top-right', 'bottom-left', 'bottom-right'],
  rotationSnaps: [0, 45, 90, 135, 180, 225, 270, 315],
  rotationSnapTolerance: 3,
  centeredScaling: false,
  flipEnabled: false,
  ignoreStroke: true,
  padding: 8, anchorSize: 16, anchorCornerRadius: 8,
  rotateAnchorOffset: 48,
  anchorStrokeWidth: 2, borderStrokeWidth: 2,
  anchorStroke: AMBAR, anchorFill: '#fff', borderStroke: AMBAR,
});
// El área que responde al toque es mayor que el dibujo de la manija.
controles.anchorStyleFunc(ancla => ancla.hitStrokeWidth(14));
capa.add(controles);
let seleccionada = null;
const eliminadas = [];
let modoMovimiento = window.matchMedia('(pointer: coarse)').matches ? 'colocar' : 'mover';
let modoActual = modoMovimiento;
let zoom = 1;
let inicioPuntero = null;
let gestoMovido = false;

// Ajustamos la vista, pero las mesas siguen usando las 900 x 560 unidades originales.
function ajustarVista() {
  const escala = Math.min(1, contenedorPlano.clientWidth / ANCHO, window.innerHeight * 0.7 / ALTO) * zoom;
  if (!escala) return;
  escenario.size({ width: ANCHO * escala, height: ALTO * escala });
  escenario.scale({ x: escala, y: escala });
  escenario.container().style.width = escenario.width() + 'px';
  escenario.container().style.backgroundSize = (40 * escala) + 'px ' + (40 * escala) + 'px';
  // Transformer ya compensa la escala: estos tamaños son píxeles de pantalla.
  const tactil = window.matchMedia('(pointer: coarse)').matches;
  controles.anchorSize(tactil ? 24 : 16);
  controles.padding(10);
  controles.rotateAnchorOffset(44);
  controles.anchorStyleFunc(ancla => ancla.hitStrokeWidth(tactil ? 20 : 6));
  document.getElementById('zoomValor').textContent = Math.round(escala * 100) + '%';
  document.getElementById('zoomMenos').disabled = zoom <= 1;
  document.getElementById('zoomMas').disabled = zoom >= 4;
}

function cambiarZoom(valor) {
  // Acercar la mesa elegida; si no hay selección, conservar el centro de la vista.
  const escalaAnterior = escenario.scaleX();
  const centro = seleccionada ? seleccionada.position() : {
    x: (contenedorPlano.scrollLeft + contenedorPlano.clientWidth / 2) / escalaAnterior,
    y: (contenedorPlano.scrollTop + contenedorPlano.clientHeight / 2) / escalaAnterior,
  };
  zoom = limitar(valor, 1, 4);
  ajustarVista();
  contenedorPlano.scrollLeft = centro.x * escenario.scaleX() - contenedorPlano.clientWidth / 2;
  contenedorPlano.scrollTop = centro.y * escenario.scaleY() - contenedorPlano.clientHeight / 2;
}

document.getElementById('zoomMas').addEventListener('click', () => cambiarZoom(zoom + 0.5));
document.getElementById('zoomMenos').addEventListener('click', () => cambiarZoom(zoom - 0.5));
document.getElementById('zoomAjustar').addEventListener('click', () => cambiarZoom(1));
let anchoObservado = 0;
new ResizeObserver(entradas => {
  const ancho = entradas[0].contentRect.width;
  if (Math.abs(ancho - anchoObservado) > 0.5) {
    anchoObservado = ancho;
    ajustarVista();
  }
}).observe(document.querySelector('.plano-area'));
window.addEventListener('resize', ajustarVista);

// Un deslizamiento o gesto cancelado por el navegador no debe terminar en un clic.
escenario.container().addEventListener('pointerdown', e => {
  if (!e.isPrimary) { gestoMovido = true; return; }
  inicioPuntero = { x: e.clientX, y: e.clientY };
  gestoMovido = false;
});
escenario.container().addEventListener('pointermove', e => {
  if (inicioPuntero && Math.hypot(e.clientX - inicioPuntero.x, e.clientY - inicioPuntero.y) > 8) gestoMovido = true;
}, { passive: true });
escenario.container().addEventListener('pointercancel', () => { gestoMovido = true; });

function limitar(valor, minimo, maximo) {
  return Math.min(Math.max(valor, minimo), maximo);
}

function actualizarTamano(grupo, ancho, alto) {
  grupo.setAttrs({ ancho, alto });
  const figura = grupo.findOne('.figura');
  if (grupo.getAttr('forma') === 'redonda') {
    figura.radiusX(ancho / 2);
    figura.radiusY(alto / 2);
  } else if (grupo.getAttr('forma') === 'triangular') {
    // Tres vértices: punta superior y dos extremos de la base.
    figura.points([0, -alto / 2, ancho / 2, alto / 2, -ancho / 2, alto / 2]);
  } else {
    figura.size({ width: ancho, height: alto });
    figura.offset({ x: ancho / 2, y: alto / 2 });
  }
  grupo.findOne('.numero').setAttrs({ x: -ancho / 2, y: -alto / 2, width: ancho, height: alto });
  if (grupo.getAttr('forma') === 'triangular') {
    // El centro visual del triángulo está más cerca de su base.
    grupo.findOne('.numero').setAttrs({ y: -alto / 6, height: alto * 2 / 3, fontSize: Math.min(20, ancho / 3, alto / 3) });
  }
}

function limitarPosicion(grupo, ajustarGrilla = false) {
  let x = grupo.x(), y = grupo.y();
  if (ajustarGrilla) {
    x = Math.round(x / GRILLA) * GRILLA;
    y = Math.round(y / GRILLA) * GRILLA;
  }
  // Medir también el giro para que una esquina no se salga del salón.
  const caja = grupo.getClientRect({ relativeTo: capa });
  const margenX = Math.min(ANCHO / 2, Math.max(grupo.x() - caja.x, caja.x + caja.width - grupo.x()));
  const margenY = Math.min(ALTO / 2, Math.max(grupo.y() - caja.y, caja.y + caja.height - grupo.y()));
  grupo.position({ x: limitar(x, margenX, ANCHO - margenX), y: limitar(y, margenY, ALTO - margenY) });
}

function crearMesa(datos) {
  const ancho = parseFloat(datos.ancho) || 70;
  const alto = parseFloat(datos.alto) || 70;
  const forma = ['cuadrada', 'redonda', 'triangular'].includes(datos.forma) ? datos.forma : 'cuadrada';
  const grupo = new Konva.Group({
    name: 'mesa', draggable: modoActual !== 'colocar', dragDistance: 5,
    x: parseFloat(datos.pos_x) || 100,
    y: parseFloat(datos.pos_y) || 100,
    rotation: parseFloat(datos.rotacion) || 0,
    mesaId: datos.id || null,
    numero: parseInt(datos.numero, 10) || 1,
    capacidad: parseInt(datos.capacidad, 10) || 4,
    forma, ancho, alto,
  });
  const estilo = { name: 'figura', fill: VERDE, stroke: AMBAR, strokeWidth: 3,
    hitStrokeWidth: 4, preventDefault: modoActual !== 'colocar' };
  let figura;
  if (forma === 'redonda') figura = new Konva.Ellipse(estilo);
  else if (forma === 'triangular') figura = new Konva.Line({ ...estilo, closed: true, lineJoin: 'round' });
  else figura = new Konva.Rect({ ...estilo, cornerRadius: 8 });
  const numero = new Konva.Text({
    name: 'numero', text: String(grupo.getAttr('numero')), fontFamily: 'Montserrat, sans-serif',
    fontSize: 20, fontStyle: 'bold', fill: CREMA,
    align: 'center', verticalAlign: 'middle', listening: false,
  });
  grupo.add(figura, numero);
  actualizarTamano(grupo, ancho, alto);
  // En arrastre se selecciona al apoyar; en colocar, solo después de un toque.
  grupo.on('mousedown touchstart', () => {
    if (modoActual !== 'colocar') seleccionar(grupo);
  });
  grupo.on('click tap', e => {
    e.cancelBubble = true;
    if (!gestoMovido) seleccionar(grupo);
  });
  grupo.on('dragstart', () => { gestoMovido = true; escenario.container().style.cursor = 'grabbing'; });
  grupo.on('dragmove', () => limitarPosicion(grupo));
  grupo.on('dragend', e => {
    limitarPosicion(grupo, !!e.evt?.shiftKey);
    escenario.container().style.cursor = 'grab';
  });
  grupo.on('transformend', () => {
    // Transformer cambia la escala. Guardamos el tamaño real y restablecemos
    // la escala para no multiplicarla otra vez en la siguiente edición.
    const nuevoAncho = limitar(anchoActual(grupo), MIN_TAM, MAX_TAM);
    const nuevoAlto = limitar(altoActual(grupo), MIN_TAM, MAX_TAM);
    grupo.scale({ x: 1, y: 1 });
    actualizarTamano(grupo, nuevoAncho, nuevoAlto);
    limitarPosicion(grupo);
    controles.forceUpdate();
    if (seleccionada === grupo) actualizarMedidas();
  });
  grupo.on('mouseenter', () => { escenario.container().style.cursor = modoActual === 'colocar' ? 'pointer' : 'grab'; });
  grupo.on('mouseleave', () => { escenario.container().style.cursor = ''; });
  capa.add(grupo);
  controles.moveToTop();
  return grupo;
}

function anchoActual(grupo) { return grupo.getAttr('ancho') * grupo.scaleX(); }
function altoActual(grupo) { return grupo.getAttr('alto') * grupo.scaleY(); }
function anguloNormalizado(angulo) { return Math.round(((angulo % 360) + 360) % 360) % 360; }

function seleccionar(grupo) {
  if (seleccionada === grupo) return;
  if (seleccionada) seleccionada.findOne('.figura').fill(VERDE);
  seleccionada = grupo;
  controles.nodes(grupo ? [grupo] : []);
  panelMesa.classList.toggle('visible', !!grupo);
  ajusteMesa.hidden = !grupo;
  // Volver al modo de movimiento elegido, sin encender manijas al seleccionar.
  if (modoActual === 'transformar') cambiarModo(modoMovimiento);
  if (grupo) {
    controles.moveToTop();
    controles.keepRatio(grupo.getAttr('forma') === 'redonda');
    inNumero.value = grupo.getAttr('numero');
    inCapacidad.value = grupo.getAttr('capacidad');
    grupo.findOne('.figura').fill('#3d7a52');
    actualizarMedidas();
    document.getElementById('mesaActiva').textContent = 'Mesa ' + grupo.getAttr('numero');
  }
  actualizarInstruccion();
  actualizarListaMesas();
}

function cambiarModo(modo) {
  modoActual = modo;
  if (modo !== 'transformar') modoMovimiento = modo;
  document.querySelector('input[name="modo-plano"][value="' + modo + '"]').checked = true;
  const transformar = modo === 'transformar';
  controles.resizeEnabled(transformar);
  controles.rotateEnabled(transformar);
  // El marco de selección no recibe clics cuando solo queremos mover mesas.
  controles.listening(transformar);
  escenario.container().classList.toggle('arrastrando', modo !== 'colocar');
  for (const mesa of capa.find('.mesa')) {
    mesa.draggable(modo !== 'colocar');
    mesa.findOne('.figura').preventDefault(modo !== 'colocar');
  }
  actualizarInstruccion();
}

function actualizarInstruccion() {
  const nombre = seleccionada ? 'Mesa ' + seleccionada.getAttr('numero') + ': ' : '';
  if (modoActual === 'colocar') instruccion.textContent = nombre + (seleccionada
    ? 'tocá un espacio vacío para colocarla. Deslizá el plano para recorrerlo.'
    : 'elegí una mesa y después tocá su destino. Podés elegirla por número.');
  else if (modoActual === 'transformar') instruccion.textContent = nombre + 'usá las manijas o escribí las medidas en el panel.';
  else instruccion.textContent = nombre + 'arrastrá una mesa para moverla. También podés usar Tocar destino.';
}

function moverSeleccionada(dx, dy, paso) {
  if (!seleccionada) return;
  seleccionada.position({ x: seleccionada.x() + dx * paso, y: seleccionada.y() + dy * paso });
  limitarPosicion(seleccionada);
}
for (const boton of document.querySelectorAll('[data-mover]')) {
  boton.addEventListener('click', () => {
    const [dx, dy] = boton.dataset.mover.split(',').map(Number);
    moverSeleccionada(dx, dy, Number(document.getElementById('pasoMesa').value));
  });
}
document.getElementById('deseleccionarMesa').addEventListener('click', () => seleccionar(null));

function actualizarMedidas() {
  inRotacion.value = anguloNormalizado(seleccionada.rotation());
  inAncho.value = Math.round(seleccionada.getAttr('ancho'));
  inAlto.value = Math.round(seleccionada.getAttr('alto'));
}
for (const campo of [inAncho, inAlto]) {
  campo.addEventListener('change', () => {
    if (!seleccionada) return;
    const valor = Number(campo.value);
    if (campo.value === '' || !Number.isFinite(valor)) { actualizarMedidas(); return; }
    let ancho = limitar(Number(inAncho.value), MIN_TAM, MAX_TAM);
    let alto = limitar(Number(inAlto.value), MIN_TAM, MAX_TAM);
    if (seleccionada.getAttr('forma') === 'redonda') ancho = alto = limitar(valor, MIN_TAM, MAX_TAM);
    actualizarTamano(seleccionada, ancho, alto);
    limitarPosicion(seleccionada);
    controles.forceUpdate();
    actualizarMedidas();
  });
}

for (const opcion of document.querySelectorAll('input[name="modo-plano"]')) {
  opcion.addEventListener('change', () => cambiarModo(opcion.value));
}

function actualizarListaMesas() {
  selectorMesa.replaceChildren(new Option('Elegí una mesa', ''));
  let indice = 0;
  for (const mesa of capa.find('.mesa')) {
    const opcion = new Option('Mesa ' + mesa.getAttr('numero'), String(indice));
    opcion.selected = mesa === seleccionada;
    selectorMesa.add(opcion);
    indice++;
  }
}

selectorMesa.addEventListener('change', () => {
  const mesas = capa.find('.mesa');
  seleccionar(selectorMesa.value === '' ? null : mesas[Number(selectorMesa.value)]);
  escenario.container().focus({ preventScroll: true });
});

escenario.on('mousedown', () => escenario.container().focus({ preventScroll: true }));
escenario.on('click tap', e => {
  if (e.target !== escenario || gestoMovido) return;
  if (modoActual === 'colocar' && seleccionada) {
    const destino = escenario.getRelativePointerPosition();
    seleccionada.position(destino);
    limitarPosicion(seleccionada);
  } else seleccionar(null);
});
document.addEventListener('keydown', e => {
  if (['INPUT', 'TEXTAREA', 'BUTTON', 'SELECT'].includes(document.activeElement.tagName) || document.activeElement.isContentEditable) return;
  const direcciones = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] };
  if (seleccionada && direcciones[e.key]) {
    e.preventDefault();
    const paso = e.shiftKey ? GRILLA : 1;
    const [dx, dy] = direcciones[e.key];
    moverSeleccionada(dx, dy, paso);
    return;
  }
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
  document.getElementById('mesaActiva').textContent = 'Mesa ' + numero;
  actualizarInstruccion();
  actualizarListaMesas();
});
inCapacidad.addEventListener('input', e => {
  if (seleccionada) seleccionada.setAttr('capacidad', parseInt(e.target.value, 10) || 1);
});
inRotacion.addEventListener('input', e => {
  if (!seleccionada || e.target.value === '') return;
  const angulo = Number(e.target.value);
  if (!Number.isFinite(angulo)) return;
  seleccionada.rotation(anguloNormalizado(angulo));
  limitarPosicion(seleccionada);
  controles.forceUpdate();
});
inRotacion.addEventListener('change', () => {
  if (seleccionada) inRotacion.value = anguloNormalizado(seleccionada.rotation());
});

function posicionNuevaMesa() {
  // Buscar un espacio libre para no apilar todas las mesas nuevas en el centro.
  for (let y = 120; y <= ALTO - 70; y += 110) {
    for (let x = 90; x <= ANCHO - 70; x += 110) {
      let libre = true;
      for (const mesa of capa.find('.mesa')) {
        const caja = mesa.getClientRect({ relativeTo: capa });
        if (x - 47 < caja.x + caja.width && x + 47 > caja.x
            && y - 47 < caja.y + caja.height && y + 47 > caja.y) {
          libre = false;
          break;
        }
      }
      if (libre) return { x, y };
    }
  }
  return { x: ANCHO / 2, y: ALTO / 2 };
}

function agregarMesa(forma) {
  let numero = 1;
  for (const mesa of capa.find('.mesa')) {
    numero = Math.max(numero, mesa.getAttr('numero') + 1);
  }
  const posicion = posicionNuevaMesa();
  seleccionar(crearMesa({ numero, capacidad: 4, forma, pos_x: posicion.x, pos_y: posicion.y, ancho: 70, alto: 70 }));
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
    actualizarListaMesas();
  } catch (error) {
    aviso.style.color = 'var(--rojo)';
    aviso.textContent = error.message;
  }
}

async function guardarPlano() {
  const mesas = [];
  for (const grupo of capa.find('.mesa')) {
    mesas.push({
      id: grupo.getAttr('mesaId') || null, numero: grupo.getAttr('numero'),
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

cambiarModo(modoActual);
ajustarVista();
cargarPlano();
