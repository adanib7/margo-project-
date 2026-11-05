// Konva dibuja las mesas. Este archivo maneja las acciones, vistas previas y guardado.
if (window.lucide) lucide.createIcons();
const ANCHO = 900, ALTO = 560, GRILLA = 10;
const VERDE = '#2d5f3f', AMBAR = '#c9962e', CREMA = '#fafaf4';
const editor = document.querySelector('.salon-editor');
const contenedorPlano = document.getElementById('canvas-wrap');
const panelMesa = document.getElementById('panel-mesa');
const selectorMesa = document.getElementById('selector-mesa');
const aviso = document.getElementById('aviso');
const escenario = new Konva.Stage({ container: 'plano', width: ANCHO, height: ALTO });
const capa = new Konva.Layer();
escenario.add(capa);
const controles = new Konva.Transformer({
  enabledAnchors: ['top-left', 'top-right', 'bottom-left', 'bottom-right'],
  rotationSnaps: [0, 45, 90, 135, 180, 225, 270, 315],
  rotationSnapTolerance: 3, flipEnabled: false, ignoreStroke: true,
  resizeEnabled: false, rotateEnabled: false, listening: false,
  anchorSize: 16, anchorCornerRadius: 8, padding: 10, rotateAnchorOffset: 44,
  anchorStroke: AMBAR, anchorFill: '#fff', borderStroke: AMBAR, borderStrokeWidth: 2,
});
capa.add(controles);
let seleccionada = null, modo = 'seleccionar', zoom = 1, contadorMesas = 0;
let cargado = false, guardando = false, firmaGuardada = '';
let eliminadas = [], historial = [];
let fantasma = null, destinoFijado = false, destinoValido = false;
let propuesta = null, antesDelGesto = null;
let inicioPuntero = null, gestoMovido = false;

function limitar(n, min, max) { return Math.min(Math.max(n, min), max); }
function angulo(n) { return ((n % 360) + 360) % 360; }
function mesasPlano() { return capa.find('.mesa'); }
function mostrarAviso(texto, error = false) {
  aviso.textContent = texto;
  aviso.style.color = error ? '#a5303d' : '#264220';
}

// Los estados contienen datos simples, no objetos internos de Konva.
function datosMesa(mesa) {
  return {
    clave: mesa.getAttr('clave'), id: mesa.getAttr('mesaId') || null,
    numero: mesa.getAttr('numero'), capacidad: mesa.getAttr('capacidad'), forma: mesa.getAttr('forma'),
    pos_x: mesa.x(), pos_y: mesa.y(), ancho: mesa.getAttr('ancho'), alto: mesa.getAttr('alto'), rotacion: mesa.rotation(),
  };
}
function estadoActual() {
  return { mesas: mesasPlano().map(datosMesa), eliminadas: [...eliminadas], seleccion: seleccionada?.getAttr('clave') || null };
}
function firma(estado) { return JSON.stringify({ mesas: estado.mesas, eliminadas: estado.eliminadas }); }
function hayCambios() { return cargado && firma(estadoActual()) !== firmaGuardada; }
function recordarCambio(antes) {
  if (firma(antes) === firma(estadoActual())) return;
  historial.push(antes);
  if (historial.length > 30) historial.shift();
  mostrarAviso('');
  actualizarInterfaz();
}
function instalarMesas(datos, claveSeleccionada = null) {
  seleccionada = null;
  controles.nodes([]);
  for (const mesa of mesasPlano()) mesa.destroy();
  for (const dato of datos) crearMesa(dato);
  seleccionar(mesasPlano().find(m => m.getAttr('clave') === claveSeleccionada) || null);
  actualizarListaMesas();
}
function deshacer() {
  if (guardando || modo !== 'seleccionar' || !historial.length) return;
  const anterior = historial.pop();
  eliminadas = [...anterior.eliminadas];
  instalarMesas(anterior.mesas, anterior.seleccion);
  mostrarAviso('Cambio deshecho.');
  actualizarInterfaz();
}

function actualizarTamano(mesa, ancho, alto) {
  mesa.setAttrs({ ancho, alto });
  const figura = mesa.findOne('.figura'), forma = mesa.getAttr('forma');
  if (forma === 'redonda') {
    figura.radiusX(ancho / 2); figura.radiusY(alto / 2);
  } else if (forma === 'triangular') {
    figura.points([0, -alto / 2, ancho / 2, alto / 2, -ancho / 2, alto / 2]);
  } else {
    figura.size({ width: ancho, height: alto }); figura.offset({ x: ancho / 2, y: alto / 2 });
  }
  mesa.findOne('.numero').setAttrs({
    x: -ancho / 2, y: forma === 'triangular' ? -alto / 6 : -alto / 2,
    width: ancho, height: forma === 'triangular' ? alto * 2 / 3 : alto,
    fontSize: forma === 'triangular' ? Math.min(20, ancho / 3, alto / 3) : 20,
  });
}
function crearMesa(datos) {
  const forma = ['cuadrada', 'redonda', 'triangular'].includes(datos.forma) ? datos.forma : 'cuadrada';
  const mesa = new Konva.Group({
    name: 'mesa', draggable: false, dragDistance: 5,
    x: Number(datos.pos_x), y: Number(datos.pos_y), rotation: Number(datos.rotacion) || 0,
    clave: datos.clave || (datos.id ? 'db-' + datos.id : 'nueva-' + (++contadorMesas)),
    mesaId: datos.id || null, numero: Number(datos.numero), capacidad: Number(datos.capacidad), forma,
  });
  const estilo = { name: 'figura', fill: VERDE, stroke: AMBAR, strokeWidth: 3, hitStrokeWidth: 4, preventDefault: false };
  let figura;
  if (forma === 'redonda') figura = new Konva.Ellipse(estilo);
  else if (forma === 'triangular') figura = new Konva.Line({ ...estilo, closed: true, lineJoin: 'round' });
  else figura = new Konva.Rect({ ...estilo, cornerRadius: 8 });
  mesa.add(figura, new Konva.Text({ name: 'numero', text: String(datos.numero), fontFamily: 'Montserrat, sans-serif', fontStyle: 'bold', fill: CREMA, align: 'center', verticalAlign: 'middle', listening: false }));
  actualizarTamano(mesa, Number(datos.ancho), Number(datos.alto));
  mesa.on('click tap', e => {
    e.cancelBubble = true;
    if (gestoMovido || guardando || propuesta) return;
    if (modo === 'mover') elegirDestino();
    else if (modo === 'seleccionar') seleccionar(mesa);
  });
  mesa.on('dragstart transformstart', () => { antesDelGesto = estadoActual(); gestoMovido = true; });
  mesa.on('dragend', e => {
    if (e.evt?.shiftKey) mesa.position({ x: Math.round(mesa.x() / GRILLA) * GRILLA, y: Math.round(mesa.y() / GRILLA) * GRILLA });
    terminarGesto(mesa);
  });
  mesa.on('transformend', () => {
    const ancho = limitar(mesa.getAttr('ancho') * mesa.scaleX(), 30, 400);
    const alto = limitar(mesa.getAttr('alto') * mesa.scaleY(), 30, 400);
    mesa.scale({ x: 1, y: 1 }); actualizarTamano(mesa, ancho, alto); terminarGesto(mesa);
  });
  mesa.on('mouseenter', () => { escenario.container().style.cursor = modo === 'arrastrar' ? 'grab' : 'pointer'; });
  mesa.on('mouseleave', () => { escenario.container().style.cursor = ''; });
  capa.add(mesa); controles.moveToTop();
  return mesa;
}
function aplicarDatos(mesa, dato) {
  mesa.position({ x: dato.pos_x, y: dato.pos_y }); mesa.rotation(dato.rotacion); mesa.scale({ x: 1, y: 1 });
  mesa.setAttrs({ numero: dato.numero, capacidad: dato.capacidad });
  mesa.findOne('.numero').text(String(dato.numero)); actualizarTamano(mesa, dato.ancho, dato.alto);
  controles.forceUpdate();
}

// Se comparan las cajas que ocupa cada figura, incluyendo su giro y borde.
function cajaEn(mesa, posicion) {
  const caja = mesa.getClientRect({ relativeTo: capa });
  return { x: caja.x + posicion.x - mesa.x(), y: caja.y + posicion.y - mesa.y(), width: caja.width, height: caja.height };
}
function seCruzan(a, b, separacion = 12) {
  return a.x < b.x + b.width + separacion && a.x + a.width + separacion > b.x
    && a.y < b.y + b.height + separacion && a.y + a.height + separacion > b.y;
}
function posicionValida(mesa, posicion) {
  const caja = cajaEn(mesa, posicion);
  if (caja.x < 6 || caja.y < 6 || caja.x + caja.width > ANCHO - 6 || caja.y + caja.height > ALTO - 6) return false;
  return !mesasPlano().some(otra => otra !== mesa && seCruzan(caja, otra.getClientRect({ relativeTo: capa })));
}
function ajustarDentro(mesa) {
  const caja = mesa.getClientRect({ relativeTo: capa });
  const dx = Math.max(6 - caja.x, 0) + Math.min(ANCHO - 6 - caja.x - caja.width, 0);
  const dy = Math.max(6 - caja.y, 0) + Math.min(ALTO - 6 - caja.y - caja.height, 0);
  mesa.position({ x: mesa.x() + dx, y: mesa.y() + dy });
}
function terminarGesto(mesa) {
  if (!antesDelGesto) return;
  const anterior = antesDelGesto; antesDelGesto = null;
  ajustarDentro(mesa);
  if (!posicionValida(mesa, mesa.position())) {
    aplicarDatos(mesa, anterior.mesas.find(m => m.clave === mesa.getAttr('clave')));
    mostrarAviso('Ese ajuste se cruza con otra mesa. Probá otro lugar o tamaño.', true);
  } else recordarCambio(anterior);
  controles.forceUpdate(); actualizarInterfaz();
}

function seleccionar(mesa) {
  if (seleccionada) seleccionada.findOne('.figura').fill(VERDE);
  seleccionada = mesa;
  if (mesa) mesa.findOne('.figura').fill('#46774e');
  controles.nodes(mesa ? [mesa] : []); controles.moveToTop();
  actualizarListaMesas(); actualizarInterfaz();
}
function actualizarListaMesas() {
  selectorMesa.replaceChildren(new Option('Elegí una mesa', ''));
  for (const mesa of mesasPlano()) {
    const opcion = new Option('Mesa ' + mesa.getAttr('numero'), mesa.getAttr('clave'));
    opcion.selected = mesa === seleccionada; selectorMesa.add(opcion);
  }
}
function actualizarInterfaz() {
  const mesas = mesasPlano(), normal = modo === 'seleccionar';
  editor.dataset.accion = modo;
  panelMesa.hidden = !seleccionada;
  document.getElementById('salonBienvenida').hidden = !!seleccionada;
  document.body.classList.toggle('salon-con-ficha', !!seleccionada || !normal);
  document.getElementById('resumenSalon').textContent = mesas.length + ' mesas · ' + mesas.reduce((n, m) => n + m.getAttr('capacidad'), 0) + ' personas';
  const cambios = hayCambios(), estado = document.getElementById('estadoGuardado');
  estado.textContent = guardando ? 'Guardando…' : !cargado ? 'Cargando salón…' : cambios ? 'Cambios sin guardar' : 'Todo guardado';
  estado.classList.toggle('sin-guardar', cambios);
  document.getElementById('btnAgregarMesa').disabled = !cargado || guardando || !normal;
  document.getElementById('btnDistribuir').disabled = !cargado || guardando || !normal || !mesas.length;
  document.getElementById('btnGuardarPlano').disabled = !cambios || guardando || !normal;
  document.getElementById('btnDeshacer').disabled = !historial.length || guardando || !normal;
  selectorMesa.disabled = !cargado || guardando || !normal;
  for (const boton of panelMesa.querySelectorAll('button')) boton.disabled = guardando || (!normal && boton.id !== 'cerrarFicha');
  document.getElementById('accionPlano').hidden = normal;
  if (seleccionada) {
    document.getElementById('mesaActiva').textContent = 'Mesa ' + seleccionada.getAttr('numero');
    const formas = { cuadrada: 'Cuadrada', redonda: 'Redonda', triangular: 'Triangular' };
    document.getElementById('mesaDetalle').textContent = seleccionada.getAttr('capacidad') + ' personas · ' + formas[seleccionada.getAttr('forma')];
  }
  const instrucciones = {
    seleccionar: seleccionada ? 'Elegí qué querés hacer con esta mesa.' : 'Tocá una mesa para moverla, girarla o editarla.',
    mover: 'Tocá un espacio vacío. Los puntos verdes sugieren lugares disponibles.',
    distribuir: 'Esta es una vista previa. Las mesas originales no cambiaron.',
    arrastrar: 'Arrastrá la mesa seleccionada. Si no entra, vuelve a su lugar.',
    transformar: 'Usá las esquinas para cambiar el tamaño y la manija superior para girar.',
  };
  document.getElementById('planoInstruccion').textContent = instrucciones[modo];
}

function salirAccion() {
  if (propuesta) {
    for (const mesa of mesasPlano()) mesa.opacity(1);
    for (const copia of capa.find('.pre-distribucion')) copia.destroy();
    propuesta = null;
  }
  if (fantasma) { fantasma.destroy(); fantasma = null; }
  for (const punto of capa.find('.lugar-libre')) punto.destroy();
  modo = 'seleccionar'; destinoFijado = false; destinoValido = false;
  for (const mesa of mesasPlano()) { mesa.draggable(false); mesa.findOne('.figura').preventDefault(false); }
  escenario.container().classList.remove('captura-gestos');
  controles.resizeEnabled(false); controles.rotateEnabled(false); controles.listening(false);
  actualizarInterfaz();
}
function dibujarLugares() {
  for (let y = 40; y < ALTO; y += 50) {
    for (let x = 40; x < ANCHO; x += 50) {
      if (posicionValida(seleccionada, { x, y })) {
        const punto = new Konva.Circle({ name: 'lugar-libre', x, y, radius: 3, fill: VERDE, opacity: 0.35, listening: false });
        capa.add(punto); punto.moveToBottom();
      }
    }
  }
}
function iniciarMovimiento() {
  if (!seleccionada) return;
  salirAccion(); modo = 'mover';
  fantasma = seleccionada.clone({ name: 'vista-previa', listening: false, opacity: 0.6, draggable: false });
  capa.add(fantasma); dibujarLugares();
  document.getElementById('accionTexto').textContent = 'Elegí el nuevo lugar para la mesa ' + seleccionada.getAttr('numero') + '.';
  document.getElementById('btnAplicarAccion').textContent = 'Colocar aquí';
  document.getElementById('btnAplicarAccion').hidden = false;
  document.getElementById('btnAplicarAccion').disabled = true;
  document.getElementById('btnCancelarAccion').textContent = 'Cancelar';
  actualizarInterfaz();
}
function mostrarDestino(posicion, fijar = false) {
  if (!fantasma || !seleccionada) return;
  const destino = { x: Math.round(posicion.x / GRILLA) * GRILLA, y: Math.round(posicion.y / GRILLA) * GRILLA };
  fantasma.position(destino);
  destinoValido = posicionValida(seleccionada, destino);
  fantasma.findOne('.figura').setAttrs({ fill: destinoValido ? '#5b8c61' : '#b87276', stroke: destinoValido ? VERDE : '#9a3540' });
  if (fijar) {
    destinoFijado = true;
    document.getElementById('accionTexto').textContent = destinoValido ? 'La mesa entra aquí. ¿La colocamos?' : 'Este lugar no tiene espacio. Tocá otro punto del salón.';
    document.getElementById('btnAplicarAccion').disabled = !destinoValido;
  }
}
function elegirDestino() { mostrarDestino(escenario.getRelativePointerPosition(), true); }
function aplicarAccion() {
  if (modo === 'mover' && seleccionada && destinoFijado && destinoValido) {
    const antes = estadoActual(); seleccionada.position(fantasma.position()); salirAccion(); recordarCambio(antes);
  } else if (modo === 'distribuir' && propuesta) {
    const antes = propuesta.antes, posiciones = propuesta.posiciones;
    salirAccion();
    for (const posicion of posiciones) mesasPlano().find(m => m.getAttr('clave') === posicion.clave).position({ x: posicion.x, y: posicion.y });
    recordarCambio(antes); mostrarAviso('Distribución aplicada. Podés deshacerla o guardar el plano.');
  }
}
function iniciarAjuste(tipo) {
  if (!seleccionada) return;
  salirAccion(); modo = tipo;
  seleccionada.draggable(tipo === 'arrastrar'); seleccionada.findOne('.figura').preventDefault(true);
  escenario.container().classList.add('captura-gestos');
  controles.resizeEnabled(tipo === 'transformar'); controles.rotateEnabled(tipo === 'transformar'); controles.listening(tipo === 'transformar');
  controles.keepRatio(seleccionada.getAttr('forma') === 'redonda');
  document.getElementById('btnAplicarAccion').hidden = true;
  document.getElementById('btnCancelarAccion').textContent = 'Listo';
  document.getElementById('accionTexto').textContent = tipo === 'arrastrar' ? 'Mové la mesa y tocá Listo al terminar.' : 'Ajustá la mesa y tocá Listo al terminar.';
  actualizarInterfaz();
}
function girarMesa() {
  if (!seleccionada) return;
  const antes = estadoActual(), dato = datosMesa(seleccionada);
  seleccionada.rotation(angulo(seleccionada.rotation() + 45)); ajustarDentro(seleccionada);
  if (!posicionValida(seleccionada, seleccionada.position())) {
    aplicarDatos(seleccionada, dato); mostrarAviso('No hay espacio para ese giro. Mové la mesa un poco y volvé a probar.', true); return;
  }
  controles.forceUpdate(); recordarCambio(antes);
}
function moverUnPaso(dx, dy, paso) {
  if (!seleccionada || modo !== 'seleccionar') return;
  const posicion = { x: seleccionada.x() + dx * paso, y: seleccionada.y() + dy * paso };
  if (!posicionValida(seleccionada, posicion)) { mostrarAviso('Ese ajuste deja poco espacio o se sale del salón.', true); return; }
  const antes = estadoActual(); seleccionada.position(posicion); recordarCambio(antes);
}

// Los diálogos nativos conservan el foco y permiten cerrar con Escape.
function abrirDialogo(id) { document.getElementById(id).showModal(); }
for (const boton of document.querySelectorAll('[data-cerrar]')) boton.addEventListener('click', () => document.getElementById(boton.dataset.cerrar).close());
document.getElementById('btnAgregarMesa').addEventListener('click', () => {
  document.getElementById('formAgregarMesa').reset(); document.getElementById('errorAgregar').textContent = ''; abrirDialogo('dialogoAgregar');
});
function buscarEspacio(ancho, alto) {
  const cajas = mesasPlano().map(m => m.getClientRect({ relativeTo: capa }));
  for (let y = 24 + alto / 2; y <= ALTO - 24 - alto / 2; y += 20) {
    for (let x = 24 + ancho / 2; x <= ANCHO - 24 - ancho / 2; x += 20) {
      const caja = { x: x - ancho / 2, y: y - alto / 2, width: ancho, height: alto };
      if (!cajas.some(otra => seCruzan(caja, otra))) return { x, y };
    }
  }
  return null;
}
document.getElementById('formAgregarMesa').addEventListener('submit', e => {
  e.preventDefault();
  const forma = document.querySelector('input[name="nuevaForma"]:checked').value;
  const capacidad = Number(document.getElementById('nuevaCapacidad').value), tamano = 40 + capacidad * 10;
  const posicion = buscarEspacio(tamano + 3, tamano + 3);
  if (!posicion) { document.getElementById('errorAgregar').textContent = 'No encontramos un espacio libre. Probá distribuir el salón o usar una mesa más pequeña.'; return; }
  const antes = estadoActual();
  const numero = Math.max(0, ...mesasPlano().map(m => m.getAttr('numero'))) + 1;
  const mesa = crearMesa({ numero, capacidad, forma, pos_x: posicion.x, pos_y: posicion.y, ancho: tamano, alto: tamano, rotacion: 0 });
  document.getElementById('dialogoAgregar').close(); seleccionar(mesa); recordarCambio(antes);
});
document.getElementById('btnDistribuir').addEventListener('click', () => { document.getElementById('errorDistribuir').textContent = ''; abrirDialogo('dialogoDistribuir'); });
document.getElementById('formDistribuir').addEventListener('submit', e => {
  e.preventDefault();
  const tipo = document.querySelector('input[name="distribucion"]:checked').value;
  const medidas = mesasPlano().map(mesa => {
    const caja = mesa.getClientRect({ relativeTo: capa });
    return { clave: mesa.getAttr('clave'), ancho: caja.width, alto: caja.height, desfaseX: caja.x + caja.width / 2 - mesa.x(), desfaseY: caja.y + caja.height / 2 - mesa.y() };
  });
  const posiciones = proponerDistribucion(medidas, tipo);
  if (!posiciones) { document.getElementById('errorDistribuir').textContent = 'Las mesas no entran con esa distribución. Probá otra opción o ajustá sus tamaños.'; return; }
  propuesta = { posiciones, antes: estadoActual() }; modo = 'distribuir';
  for (const mesa of mesasPlano()) mesa.opacity(0.15);
  for (const posicion of posiciones) {
    const mesa = mesasPlano().find(m => m.getAttr('clave') === posicion.clave);
    const copia = mesa.clone({ name: 'pre-distribucion', x: posicion.x, y: posicion.y, opacity: 0.9, listening: false, draggable: false });
    copia.findOne('.figura').fill(VERDE); capa.add(copia);
  }
  document.getElementById('dialogoDistribuir').close();
  document.getElementById('accionTexto').textContent = tipo === 'centro' ? 'Propuesta: centro libre.' : 'Propuesta: mesas en filas.';
  document.getElementById('btnAplicarAccion').hidden = false; document.getElementById('btnAplicarAccion').disabled = false;
  document.getElementById('btnAplicarAccion').textContent = 'Aplicar distribución';
  document.getElementById('btnCancelarAccion').textContent = 'Volver';
  cambiarZoom(1); actualizarInterfaz();
});
document.getElementById('btnEditarMesa').addEventListener('click', () => {
  const dato = datosMesa(seleccionada);
  for (const [id, campo] of Object.entries({ 'in-numero': 'numero', 'in-capacidad': 'capacidad', 'in-ancho': 'ancho', 'in-alto': 'alto', 'in-rotacion': 'rotacion' })) {
    document.getElementById(id).value = campo === 'rotacion' ? angulo(dato[campo]) : dato[campo];
  }
  document.getElementById('tituloEditar').textContent = 'Editar mesa ' + dato.numero;
  document.getElementById('errorEditar').textContent = ''; abrirDialogo('dialogoEditar');
});
document.getElementById('formEditarMesa').addEventListener('invalid', e => {
  const detalles = e.target.closest('details');
  if (detalles) detalles.open = true;
}, true);
document.getElementById('formEditarMesa').addEventListener('submit', e => {
  e.preventDefault();
  const antes = estadoActual(), original = datosMesa(seleccionada);
  const nuevo = { ...original, numero: Number(document.getElementById('in-numero').value), capacidad: Number(document.getElementById('in-capacidad').value), ancho: Number(document.getElementById('in-ancho').value), alto: Number(document.getElementById('in-alto').value), rotacion: Number(document.getElementById('in-rotacion').value) };
  if (mesasPlano().some(m => m !== seleccionada && m.getAttr('numero') === nuevo.numero)) { document.getElementById('errorEditar').textContent = 'Ya hay otra mesa con ese número.'; return; }
  aplicarDatos(seleccionada, nuevo);
  const cambiaForma = nuevo.ancho !== original.ancho || nuevo.alto !== original.alto || nuevo.rotacion !== original.rotacion;
  if (cambiaForma) ajustarDentro(seleccionada);
  if (cambiaForma && !posicionValida(seleccionada, seleccionada.position())) {
    aplicarDatos(seleccionada, original); document.getElementById('errorEditar').textContent = 'Con esas medidas la mesa se cruza con otra. Movela primero o elegí otras medidas.'; return;
  }
  document.getElementById('dialogoEditar').close(); actualizarListaMesas(); recordarCambio(antes); actualizarInterfaz();
});
function eliminarSeleccionada() {
  if (!seleccionada) return;
  const antes = estadoActual(), id = seleccionada.getAttr('mesaId');
  if (id) eliminadas.push(id);
  const mesa = seleccionada; seleccionar(null); mesa.destroy(); actualizarListaMesas(); recordarCambio(antes);
}
document.getElementById('btnEliminarMesa').addEventListener('click', () => { document.getElementById('dialogoEditar').close(); eliminarSeleccionada(); });

// La vista se escala; las posiciones guardadas siempre son de 900 x 560 unidades.
function ajustarVista() {
  const escala = Math.min(1, contenedorPlano.clientWidth / ANCHO, window.innerHeight * 0.65 / ALTO) * zoom;
  if (!escala) return;
  escenario.size({ width: ANCHO * escala, height: ALTO * escala }); escenario.scale({ x: escala, y: escala });
  escenario.container().style.width = escenario.width() + 'px';
  escenario.container().style.backgroundSize = 40 * escala + 'px ' + 40 * escala + 'px';
  const tactil = window.matchMedia('(pointer: coarse)').matches;
  controles.anchorSize(tactil ? 24 : 16); controles.anchorStyleFunc(ancla => ancla.hitStrokeWidth(tactil ? 20 : 6));
  document.getElementById('zoomValor').textContent = Math.round(escala * 100) + '%';
  document.getElementById('zoomMenos').disabled = zoom <= 1;
  document.getElementById('zoomMas').disabled = zoom >= 4;
}
function cambiarZoom(valor) {
  const escala = escenario.scaleX();
  const centro = seleccionada ? seleccionada.position() : { x: (contenedorPlano.scrollLeft + contenedorPlano.clientWidth / 2) / escala, y: (contenedorPlano.scrollTop + contenedorPlano.clientHeight / 2) / escala };
  zoom = limitar(valor, 1, 4); ajustarVista();
  contenedorPlano.scrollLeft = centro.x * escenario.scaleX() - contenedorPlano.clientWidth / 2;
  contenedorPlano.scrollTop = centro.y * escenario.scaleY() - contenedorPlano.clientHeight / 2;
}
let anchoObservado = 0;
new ResizeObserver(entradas => { const ancho = entradas[0].contentRect.width; if (Math.abs(ancho - anchoObservado) > 0.5) { anchoObservado = ancho; ajustarVista(); } }).observe(document.querySelector('.plano-area'));
window.addEventListener('resize', ajustarVista);
escenario.container().addEventListener('pointerdown', e => { if (!e.isPrimary) { gestoMovido = true; return; } inicioPuntero = { x: e.clientX, y: e.clientY }; gestoMovido = false; });
escenario.container().addEventListener('pointermove', e => { if (inicioPuntero && Math.hypot(e.clientX - inicioPuntero.x, e.clientY - inicioPuntero.y) > 8) gestoMovido = true; }, { passive: true });
escenario.container().addEventListener('pointercancel', () => { gestoMovido = true; });
escenario.on('mousemove', () => { if (modo === 'mover' && !destinoFijado && !guardando) mostrarDestino(escenario.getRelativePointerPosition()); });
escenario.on('mousedown', () => escenario.container().focus({ preventScroll: true }));
escenario.on('click tap', e => {
  if (e.target !== escenario || gestoMovido || guardando) return;
  if (modo === 'mover') elegirDestino();
  else if (modo === 'seleccionar') seleccionar(null);
});
document.getElementById('zoomMas').addEventListener('click', () => cambiarZoom(zoom + 0.5));
document.getElementById('zoomMenos').addEventListener('click', () => cambiarZoom(zoom - 0.5));
document.getElementById('zoomAjustar').addEventListener('click', () => cambiarZoom(1));
document.getElementById('btnMoverMesa').addEventListener('click', iniciarMovimiento);
document.getElementById('btnGirarMesa').addEventListener('click', girarMesa);
document.getElementById('btnAplicarAccion').addEventListener('click', aplicarAccion);
document.getElementById('btnCancelarAccion').addEventListener('click', salirAccion);
document.getElementById('btnDeshacer').addEventListener('click', deshacer);
document.getElementById('cerrarFicha').addEventListener('click', () => { salirAccion(); seleccionar(null); });
document.getElementById('btnArrastrar').addEventListener('click', () => iniciarAjuste('arrastrar'));
document.getElementById('btnManijas').addEventListener('click', () => iniciarAjuste('transformar'));
for (const boton of document.querySelectorAll('[data-mover]')) boton.addEventListener('click', () => { const [dx, dy] = boton.dataset.mover.split(',').map(Number); moverUnPaso(dx, dy, Number(document.getElementById('pasoMesa').value)); });
selectorMesa.addEventListener('change', () => { seleccionar(mesasPlano().find(m => m.getAttr('clave') === selectorMesa.value) || null); escenario.container().focus({ preventScroll: true }); });
document.addEventListener('keydown', e => {
  if (document.querySelector('dialog[open]') || guardando) return;
  if (e.key === 'Escape') { if (modo !== 'seleccionar') salirAccion(); else seleccionar(null); return; }
  if (['INPUT', 'SELECT', 'TEXTAREA'].includes(document.activeElement.tagName) || document.activeElement.isContentEditable) return;
  if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z') { e.preventDefault(); deshacer(); return; }
  if (document.activeElement.tagName === 'BUTTON' || modo !== 'seleccionar') return;
  const direcciones = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] };
  if (seleccionada && direcciones[e.key]) { e.preventDefault(); const [dx, dy] = direcciones[e.key]; moverUnPaso(dx, dy, e.shiftKey ? 10 : 1); }
  else if (seleccionada && ['Delete', 'Backspace'].includes(e.key)) { e.preventDefault(); eliminarSeleccionada(); }
});
window.addEventListener('beforeunload', e => { if (hayCambios()) { e.preventDefault(); e.returnValue = ''; } });

async function cargarPlano() {
  try {
    const respuesta = await fetch(window.location.pathname + '?action=cargar');
    const datos = await respuesta.json();
    if (!respuesta.ok || !Array.isArray(datos)) throw new Error(datos.error || datos.mensaje || 'No se pudo cargar el salón.');
    instalarMesas(datos); cargado = true; eliminadas = []; historial = []; firmaGuardada = firma(estadoActual());
    document.getElementById('btnReintentar').hidden = true; mostrarAviso(''); actualizarInterfaz();
  } catch (error) {
    const mensaje = ['TypeError', 'SyntaxError'].includes(error.name) ? 'No se pudo cargar el salón. Comprobá la conexión y volvé a intentarlo.' : error.message;
    mostrarAviso(mensaje, true); document.getElementById('estadoGuardado').textContent = 'Plano no disponible'; document.getElementById('btnReintentar').hidden = false;
  }
}
async function guardarPlano() {
  if (guardando || modo !== 'seleccionar' || !hayCambios()) return;
  guardando = true; editor.inert = true; actualizarInterfaz();
  try {
    const mesas = estadoActual().mesas.map(({ clave, ...datos }) => datos);
    const respuesta = await fetch(window.location.pathname, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'guardar', mesas, eliminadas }) });
    const datos = await respuesta.json();
    if (!respuesta.ok || !datos.ok || !Array.isArray(datos.mesas)) throw new Error(datos.error || datos.mensaje || 'No se pudo guardar el plano. Volvé a intentarlo.');
    instalarMesas(datos.mesas); eliminadas = []; historial = []; firmaGuardada = firma(estadoActual());
    mostrarAviso(datos.no_eliminadas?.length ? 'Plano guardado. Se conservaron las mesas que tienen reservas.' : 'Plano guardado. Tu salón ya está actualizado.');
  } catch (error) {
    mostrarAviso(['TypeError', 'SyntaxError'].includes(error.name) ? 'No se pudo confirmar el guardado. Comprobá la conexión y volvé a intentarlo.' : error.message, true);
  }
  finally { guardando = false; editor.inert = false; actualizarInterfaz(); }
}
document.getElementById('btnGuardarPlano').addEventListener('click', guardarPlano);
document.getElementById('btnReintentar').addEventListener('click', cargarPlano);
actualizarInterfaz(); ajustarVista(); cargarPlano();
