// Konva dibuja las mesas. Este archivo maneja las acciones, vistas previas y guardado.
if (window.lucide) lucide.createIcons();
const ANCHO = 900, ALTO = 560, GRILLA = 10;
const VERDE = '#2d5f3f', AMBAR = '#c9962e', CREMA = '#fafaf4';
const editor = document.querySelector('.salon-editor');
const panelMesa = document.getElementById('panel-mesa');
const listaMesas = document.getElementById('listaMesas');
const aviso = document.getElementById('aviso');
const escenario = new Konva.Stage({ container: 'plano', width: ANCHO, height: ALTO });
const capa = new Konva.Layer();
escenario.add(capa);
// El borde muestra los límites reales del salón, incluso al alejar o desplazar la vista.
capa.add(new Konva.Rect({ width: ANCHO, height: ALTO, stroke: '#b8b097', strokeWidth: 2, listening: false }));
let seleccionada = null, modo = 'seleccionar', contadorMesas = 0;
let cargado = false, guardando = false, firmaGuardada = '';
let eliminadas = [], historial = [];
let fantasma = null, destinoFijado = false, destinoValido = false;
let propuesta = null;
let antesArrastre = null;

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
    reservas_pendientes: mesa.getAttr('reservasPendientes'), tiene_historial: mesa.getAttr('tieneHistorial'),
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
function crearFigura(forma) {
  const estilo = { name: 'figura', fill: VERDE, stroke: AMBAR, strokeWidth: 3, hitStrokeWidth: 4, preventDefault: false };
  if (forma === 'redonda') return new Konva.Ellipse(estilo);
  if (forma === 'triangular') return new Konva.Line({ ...estilo, closed: true, lineJoin: 'round' });
  return new Konva.Rect({ ...estilo, cornerRadius: 8 });
}
function crearMesa(datos) {
  const forma = ['cuadrada', 'rectangular', 'redonda', 'triangular'].includes(datos.forma) ? datos.forma : 'cuadrada';
  const mesa = new Konva.Group({
    name: 'mesa', draggable: false, dragDistance: 5,
    x: Number(datos.pos_x), y: Number(datos.pos_y), rotation: Number(datos.rotacion) || 0,
    clave: datos.clave || (datos.id ? 'db-' + datos.id : 'nueva-' + (++contadorMesas)),
    mesaId: datos.id || null, numero: Number(datos.numero), capacidad: Number(datos.capacidad), forma,
    reservasPendientes: Number(datos.reservas_pendientes) || 0, tieneHistorial: !!datos.tiene_historial,
  });
  mesa.add(crearFigura(forma), new Konva.Text({ name: 'numero', text: String(datos.numero), fontFamily: 'Montserrat, sans-serif', fontStyle: 'bold', fill: CREMA, align: 'center', verticalAlign: 'middle', listening: false }));
  actualizarTamano(mesa, Number(datos.ancho), Number(datos.alto));
  capa.add(mesa);
  return mesa;
}
function aplicarDatos(mesa, dato) {
  if (dato.forma !== mesa.getAttr('forma')) {
    mesa.findOne('.figura').destroy();
    mesa.setAttr('forma', dato.forma);
    const figura = crearFigura(dato.forma);
    mesa.add(figura); figura.moveToBottom();
  }
  mesa.findOne('.figura').fill(mesa === seleccionada ? '#46774e' : VERDE);
  mesa.position({ x: dato.pos_x, y: dato.pos_y }); mesa.rotation(dato.rotacion); mesa.scale({ x: 1, y: 1 });
  mesa.setAttrs({ numero: dato.numero, capacidad: dato.capacidad });
  mesa.findOne('.numero').text(String(dato.numero)); actualizarTamano(mesa, dato.ancho, dato.alto);
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
  return problemaPosicion(mesa, posicion) === '';
}
function problemaPosicion(mesa, posicion) {
  const caja = cajaEn(mesa, posicion);
  if (caja.x < 6 || caja.y < 6 || caja.x + caja.width > ANCHO - 6 || caja.y + caja.height > ALTO - 6) return 'Se sale del salón.';
  const otra = mesasPlano().find(otra => otra !== mesa && seCruzan(caja, otra.getClientRect({ relativeTo: capa })));
  return otra ? 'Se cruza con la mesa ' + otra.getAttr('numero') + '.' : '';
}
function ajustarDentro(mesa) {
  const caja = mesa.getClientRect({ relativeTo: capa });
  const dx = Math.max(6 - caja.x, 0) + Math.min(ANCHO - 6 - caja.x - caja.width, 0);
  const dy = Math.max(6 - caja.y, 0) + Math.min(ALTO - 6 - caja.y - caja.height, 0);
  mesa.position({ x: mesa.x() + dx, y: mesa.y() + dy });
}
function seleccionar(mesa) {
  if (modo !== 'seleccionar' || guardando) return;
  if (seleccionada) seleccionada.findOne('.figura').fill(VERDE);
  seleccionada = mesa;
  if (mesa) mesa.findOne('.figura').fill('#46774e');
  actualizarListaMesas(); actualizarInterfaz();
}
function actualizarListaMesas() {
  const foco = listaMesas.contains(document.activeElement) ? document.activeElement.dataset.clave : null;
  listaMesas.replaceChildren();
  for (const mesa of [...mesasPlano()].sort((a, b) => a.getAttr('numero') - b.getAttr('numero'))) {
    const boton = document.createElement('button');
    boton.type = 'button'; boton.className = 'salon-mesa-numero';
    boton.dataset.clave = mesa.getAttr('clave');
    boton.textContent = 'Mesa ' + mesa.getAttr('numero');
    boton.setAttribute('aria-pressed', String(mesa === seleccionada));
    boton.disabled = modo !== 'seleccionar' || guardando;
    boton.addEventListener('click', () => seleccionar(mesa));
    listaMesas.append(boton);
  }
  if (foco) [...listaMesas.children].find(boton => boton.dataset.clave === foco)?.focus({ preventScroll: true });
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
  for (const boton of listaMesas.querySelectorAll('button')) boton.disabled = !cargado || guardando || !normal;
  for (const boton of panelMesa.querySelectorAll('button')) boton.disabled = guardando || (!normal && boton.id !== 'cerrarFicha');
  document.getElementById('accionPlano').hidden = normal;
  if (seleccionada) {
    document.getElementById('mesaActiva').textContent = 'Mesa ' + seleccionada.getAttr('numero');
    const formas = { cuadrada: 'Cuadrada', rectangular: 'Rectangular', redonda: 'Redonda', triangular: 'Triangular' };
    const ancho = seleccionada.getAttr('ancho'), alto = seleccionada.getAttr('alto');
    const forma = seleccionada.getAttr('forma') === 'cuadrada' && ancho !== alto ? 'rectangular' : seleccionada.getAttr('forma');
    document.getElementById('mesaDetalle').textContent = seleccionada.getAttr('capacidad') + ' personas · ' + formas[forma];
    document.getElementById('mesaMedidas').textContent = 'Tamaño de la mesa: ' + ancho + ' × ' + alto;
    document.getElementById('btnAchicarMesa').disabled = guardando || !normal || Math.min(ancho, alto) <= 30;
    document.getElementById('btnAgrandarMesa').disabled = guardando || !normal || Math.max(ancho, alto) >= 400;
    const pendientes = seleccionada.getAttr('reservasPendientes');
    document.getElementById('mesaReservas').textContent = pendientes
      ? pendientes + ' reservas por atender. Para retirar esta mesa, reasignalas primero en Reservas.'
      : seleccionada.getAttr('tieneHistorial') ? 'Podés retirarla del plano conservando sus reservas anteriores.' : 'Sin reservas asociadas.';
  }
  const instrucciones = {
    seleccionar: seleccionada ? 'Agrandá o achicá la mesa con los botones. En Forma y tamaño podés hacerla rectangular.' : 'Elegí una mesa en el plano o por su número. Usá la rueda o dos dedos para acercar la vista.',
    mover: 'Desplazá el plano debajo de la mira y tocá Colocar aquí.',
    distribuir: 'Esta es una vista previa. Las mesas originales no cambiaron.',
    arrastrar: 'Soltá para colocar. Si se cruza con otra mesa, conserva su lugar anterior.',
  };
  document.getElementById('planoInstruccion').textContent = instrucciones[modo];
}

function salirAccion() {
  antesArrastre = null;
  if (propuesta) {
    for (const mesa of mesasPlano()) mesa.opacity(1);
    for (const copia of capa.find('.pre-distribucion')) copia.destroy();
    propuesta = null;
  }
  if (fantasma) { fantasma.destroy(); fantasma = null; }
  for (const mesa of mesasPlano()) mesa.opacity(1);
  document.getElementById('planoMira').hidden = true;
  for (const punto of capa.find('.lugar-libre')) punto.destroy();
  modo = 'seleccionar'; destinoFijado = false; destinoValido = false;
  actualizarListaMesas();
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
  capa.add(fantasma); dibujarLugares(); seleccionada.opacity(0.25);
  document.getElementById('planoMira').hidden = false;
  document.getElementById('btnAplicarAccion').textContent = 'Colocar aquí';
  document.getElementById('btnAplicarAccion').hidden = false;
  document.getElementById('btnAplicarAccion').disabled = true;
  document.getElementById('btnCancelarAccion').textContent = 'Cancelar';
  actualizarInterfaz(); vista.centrar(seleccionada.position());
  escenario.container().scrollIntoView({ block: 'center' });
}
function mostrarDestino(posicion) {
  if (!fantasma || !seleccionada) return;
  const destino = { x: Math.round(posicion.x / GRILLA) * GRILLA, y: Math.round(posicion.y / GRILLA) * GRILLA };
  fantasma.position(destino);
  const problema = problemaPosicion(seleccionada, destino);
  destinoValido = problema === '';
  fantasma.findOne('.figura').setAttrs({ fill: destinoValido ? '#5b8c61' : '#b87276', stroke: destinoValido ? VERDE : '#9a3540' });
  destinoFijado = true;
  document.getElementById('accionTexto').textContent = 'Moviendo mesa ' + seleccionada.getAttr('numero') + ' · ' + (problema || 'La mesa entra aquí.');
  document.getElementById('btnAplicarAccion').disabled = !destinoValido;
  document.getElementById('planoMira').classList.toggle('invalida', !destinoValido);
  capa.batchDraw();
}
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
function empezarArrastre(mesa) {
  seleccionar(mesa); antesArrastre = estadoActual(); modo = 'arrastrar';
  fantasma = mesa.clone({ name: 'vista-previa', listening: false, opacity: 0.8, draggable: false });
  capa.add(fantasma); mesa.opacity(0.25);
  document.getElementById('btnAplicarAccion').hidden = true;
  document.getElementById('btnCancelarAccion').textContent = 'Cancelar';
  actualizarInterfaz(); mostrarDestino(mesa.position());
}
function terminarArrastre(confirmar) {
  if (!antesArrastre) return;
  const antes = antesArrastre; antesArrastre = null;
  const valido = destinoValido;
  const problema = fantasma && seleccionada ? problemaPosicion(seleccionada, fantasma.position()) : '';
  if (confirmar && valido && fantasma) seleccionada.position(fantasma.position());
  salirAccion();
  if (confirmar && valido && antes) recordarCambio(antes);
  else if (confirmar) mostrarAviso(problema + ' La mesa conserva su lugar anterior.', true);
}
function girarMesa() {
  if (!seleccionada) return;
  const antes = estadoActual(), dato = datosMesa(seleccionada);
  seleccionada.rotation(angulo(seleccionada.rotation() + 45)); ajustarDentro(seleccionada);
  if (!posicionValida(seleccionada, seleccionada.position())) {
    aplicarDatos(seleccionada, dato); mostrarAviso('No hay espacio para ese giro. Mové la mesa un poco y volvé a probar.', true); return;
  }
  recordarCambio(antes);
}
function moverUnPaso(dx, dy, paso) {
  if (!seleccionada || modo !== 'seleccionar') return;
  const posicion = { x: seleccionada.x() + dx * paso, y: seleccionada.y() + dy * paso };
  if (!posicionValida(seleccionada, posicion)) { mostrarAviso('Ese ajuste deja poco espacio o se sale del salón.', true); return; }
  const antes = estadoActual(); seleccionada.position(posicion); recordarCambio(antes);
}
function cambiarTamanoMesa(factor) {
  if (!seleccionada || guardando || modo !== 'seleccionar') return;
  const antes = estadoActual(), original = datosMesa(seleccionada);
  // Se conserva la proporción, incluso al alcanzar el mínimo o el máximo.
  const escala = Math.max(30 / original.ancho, 30 / original.alto,
    Math.min(factor, 400 / original.ancho, 400 / original.alto));
  const nuevo = { ...original, ancho: Math.round(original.ancho * escala * 10) / 10, alto: Math.round(original.alto * escala * 10) / 10 };
  aplicarDatos(seleccionada, nuevo); ajustarDentro(seleccionada);
  const problema = problemaPosicion(seleccionada, seleccionada.position());
  if (problema) {
    aplicarDatos(seleccionada, original);
    mostrarAviso(problema + ' Mové la mesa o elegí otras medidas en Forma y tamaño.', true); return;
  }
  recordarCambio(antes);
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
  const ancho = forma === 'rectangular' ? Math.round(tamano * 1.6) : tamano, alto = tamano;
  const posicion = buscarEspacio(ancho + 3, alto + 3);
  if (!posicion) { document.getElementById('errorAgregar').textContent = 'No encontramos un espacio libre. Probá distribuir el salón o usar una mesa más pequeña.'; return; }
  const antes = estadoActual();
  const numero = Math.max(window.planoSiguienteNumero || 1, Math.max(0, ...mesasPlano().map(m => m.getAttr('numero'))) + 1);
  const mesa = crearMesa({ numero, capacidad, forma, pos_x: posicion.x, pos_y: posicion.y, ancho, alto, rotacion: 0 });
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
  vista.verTodo(); actualizarInterfaz();
});
document.getElementById('btnEditarMesa').addEventListener('click', () => {
  const dato = datosMesa(seleccionada);
  for (const [id, campo] of Object.entries({ 'in-numero': 'numero', 'in-capacidad': 'capacidad', 'in-ancho': 'ancho', 'in-alto': 'alto', 'in-rotacion': 'rotacion' })) {
    document.getElementById(id).value = campo === 'rotacion' ? angulo(dato[campo]) : dato[campo];
  }
  const forma = dato.forma === 'cuadrada' && dato.ancho !== dato.alto ? 'rectangular' : dato.forma;
  document.querySelector('input[name="editarForma"][value="' + forma + '"]').checked = true;
  document.getElementById('tituloEditar').textContent = 'Forma y tamaño · Mesa ' + dato.numero;
  const pendientes = dato.reservas_pendientes;
  document.getElementById('btnEliminarMesa').disabled = pendientes > 0;
  document.getElementById('btnEliminarMesa').textContent = dato.tiene_historial ? 'Retirar del plano' : 'Eliminar mesa';
  document.getElementById('ayudaRetirar').textContent = pendientes
    ? 'Tiene ' + pendientes + ' reservas por atender. Reasignalas en Reservas antes de retirarla.'
    : dato.tiene_historial ? 'Se retirará al guardar. Su historial de reservas se conserva.' : 'Se eliminará al guardar el plano. Podés deshacer antes de guardar.';
  document.getElementById('errorEditar').textContent = ''; actualizarPreviewMesa(); abrirDialogo('dialogoEditar');
});
function actualizarPreviewMesa() {
  const forma = document.querySelector('input[name="editarForma"]:checked').value;
  const numero = document.getElementById('in-numero').value;
  const ancho = Math.min(400, Math.max(30, Number(document.getElementById('in-ancho').value) || 30));
  const alto = Math.min(400, Math.max(30, Number(document.getElementById('in-alto').value) || 30));
  const giro = Number(document.getElementById('in-rotacion').value) || 0;
  const svg = document.getElementById('previewMesa'), lado = Math.hypot(ancho, alto) + 24;
  svg.setAttribute('viewBox', `${-lado / 2} ${-lado / 2} ${lado} ${lado}`);
  const crear = (etiqueta, atributos) => {
    const nodo = document.createElementNS('http://www.w3.org/2000/svg', etiqueta);
    for (const [clave, valor] of Object.entries(atributos)) nodo.setAttribute(clave, valor);
    return nodo;
  };
  const grupo = crear('g', { transform: 'rotate(' + giro + ')' });
  const figura = forma === 'redonda' ? crear('ellipse', { rx: ancho / 2, ry: alto / 2 })
    : forma === 'triangular' ? crear('polygon', { points: `0,${-alto / 2} ${ancho / 2},${alto / 2} ${-ancho / 2},${alto / 2}` })
      : crear('rect', { x: -ancho / 2, y: -alto / 2, width: ancho, height: alto, rx: 8 });
  figura.setAttribute('fill', VERDE); figura.setAttribute('stroke', AMBAR); figura.setAttribute('stroke-width', 3);
  const texto = crear('text', { x: 0, y: forma === 'triangular' ? alto / 6 : 0, 'text-anchor': 'middle', 'dominant-baseline': 'central', fill: CREMA, 'font-size': 20, 'font-family': 'Montserrat, sans-serif', 'font-weight': 700 });
  texto.textContent = numero; grupo.append(figura, texto); svg.replaceChildren(grupo);
  document.getElementById('previewMedidas').textContent = ancho + ' × ' + alto + ' · Giro ' + giro + '°';
}
for (const radio of document.querySelectorAll('input[name="editarForma"]')) radio.addEventListener('change', () => {
  const ancho = document.getElementById('in-ancho'), alto = document.getElementById('in-alto');
  const base = Math.min(Number(ancho.value) || 80, Number(alto.value) || 80);
  if (radio.value === 'rectangular' && ancho.value === alto.value) ancho.value = Math.min(400, Math.round(base * 1.6));
  else if (radio.value === 'cuadrada' || radio.value === 'redonda') { ancho.value = base; alto.value = base; }
  actualizarPreviewMesa();
});
document.getElementById('formEditarMesa').addEventListener('input', e => {
  if (['in-ancho', 'in-alto'].includes(e.target.id)
      && document.querySelector('input[name="editarForma"]:checked').value === 'cuadrada'
      && Number(document.getElementById('in-ancho').value) !== Number(document.getElementById('in-alto').value)) {
    document.querySelector('input[name="editarForma"][value="rectangular"]').checked = true;
  }
  document.getElementById('errorEditar').textContent = '';
  actualizarPreviewMesa();
});
document.getElementById('formEditarMesa').addEventListener('submit', e => {
  e.preventDefault();
  const antes = estadoActual(), original = datosMesa(seleccionada);
  const nuevo = { ...original, forma: document.querySelector('input[name="editarForma"]:checked').value, numero: Number(document.getElementById('in-numero').value), capacidad: Number(document.getElementById('in-capacidad').value), ancho: Number(document.getElementById('in-ancho').value), alto: Number(document.getElementById('in-alto').value), rotacion: Number(document.getElementById('in-rotacion').value) };
  if (mesasPlano().some(m => m !== seleccionada && m.getAttr('numero') === nuevo.numero)) { document.getElementById('errorEditar').textContent = 'Ya hay otra mesa con ese número.'; return; }
  aplicarDatos(seleccionada, nuevo);
  const cambiaForma = nuevo.forma !== original.forma || nuevo.ancho !== original.ancho || nuevo.alto !== original.alto || nuevo.rotacion !== original.rotacion;
  if (cambiaForma) ajustarDentro(seleccionada);
  if (cambiaForma && !posicionValida(seleccionada, seleccionada.position())) {
    aplicarDatos(seleccionada, original); document.getElementById('errorEditar').textContent = 'Con esas medidas la mesa se cruza con otra. Movela primero o elegí otras medidas.'; return;
  }
  document.getElementById('dialogoEditar').close(); actualizarListaMesas(); recordarCambio(antes); actualizarInterfaz();
});
function eliminarSeleccionada() {
  if (!seleccionada || modo !== 'seleccionar') return;
  if (seleccionada.getAttr('reservasPendientes') > 0) {
    mostrarAviso('Esta mesa tiene reservas por atender. Reasignalas en Reservas antes de retirarla.', true); return;
  }
  const antes = estadoActual(), id = seleccionada.getAttr('mesaId');
  if (id) eliminadas.push(id);
  const mesa = seleccionada; seleccionar(null); mesa.destroy(); actualizarListaMesas(); recordarCambio(antes);
}
document.getElementById('btnEliminarMesa').addEventListener('click', () => { document.getElementById('dialogoEditar').close(); eliminarSeleccionada(); });

// Primero se prueba la figura real. La tolerancia pequeña solo se usa en el fondo;
// así no hay áreas invisibles que le roben el click a una mesa vecina.
function mesaEnPantalla(punto) {
  const figura = escenario.getIntersection(punto);
  const mesa = figura?.findAncestor('.mesa');
  if (mesa) return mesa;
  const mundo = vista.aPlano(punto), margen = 7 / escenario.scaleX();
  let cercana = null, distancia = Infinity;
  for (const candidata of mesasPlano()) {
    const caja = candidata.getClientRect({ relativeTo: capa });
    const dx = Math.max(caja.x - mundo.x, 0, mundo.x - caja.x - caja.width);
    const dy = Math.max(caja.y - mundo.y, 0, mundo.y - caja.y - caja.height);
    const d = Math.hypot(dx, dy);
    if (d <= margen && d < distancia) { cercana = candidata; distancia = d; }
  }
  return cercana;
}
const vista = crearVistaPlano(escenario, ANCHO, ALTO, {
  bloqueada: () => guardando || !cargado || modo === 'distribuir',
  colocando: () => modo === 'mover',
  mesaEn: mesaEnPantalla,
  seleccionar,
  inicioArrastre: empezarArrastre,
  arrastrar: mostrarDestino,
  finArrastre: terminarArrastre,
  camara: centro => { if (modo === 'mover') mostrarDestino(centro); },
});
document.getElementById('btnMoverMesa').addEventListener('click', iniciarMovimiento);
document.getElementById('btnGirarMesa').addEventListener('click', girarMesa);
document.getElementById('btnAchicarMesa').addEventListener('click', () => cambiarTamanoMesa(0.9));
document.getElementById('btnAgrandarMesa').addEventListener('click', () => cambiarTamanoMesa(1.1));
document.getElementById('btnAplicarAccion').addEventListener('click', aplicarAccion);
document.getElementById('btnCancelarAccion').addEventListener('click', salirAccion);
document.getElementById('btnDeshacer').addEventListener('click', deshacer);
document.getElementById('cerrarFicha').addEventListener('click', () => { salirAccion(); seleccionar(null); });
document.getElementById('btnAcercarMesa').addEventListener('click', () => {
  if (!seleccionada) return;
  vista.acercar(seleccionada);
  escenario.container().scrollIntoView({ block: 'center' });
  if (getComputedStyle(panelMesa).position === 'fixed') {
    const caja = escenario.container().getBoundingClientRect();
    const zonaVisible = panelMesa.getBoundingClientRect().top;
    window.scrollBy(0, caja.top + caja.height / 2 - zonaVisible / 2);
  }
});
for (const boton of document.querySelectorAll('[data-mover]')) boton.addEventListener('click', () => { const [dx, dy] = boton.dataset.mover.split(',').map(Number); moverUnPaso(dx, dy, Number(document.getElementById('pasoMesa').value)); });
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
    window.planoSiguienteNumero = datos.siguiente_numero || window.planoSiguienteNumero;
    instalarMesas(datos.mesas); eliminadas = []; historial = []; firmaGuardada = firma(estadoActual());
    mostrarAviso(datos.retiradas?.length ? 'Plano guardado. Las mesas retiradas conservan su historial de reservas.' : 'Plano guardado. Tu salón ya está actualizado.');
  } catch (error) {
    mostrarAviso(['TypeError', 'SyntaxError'].includes(error.name) ? 'No se pudo confirmar el guardado. Comprobá la conexión y volvé a intentarlo.' : error.message, true);
  }
  finally { guardando = false; editor.inert = false; actualizarInterfaz(); }
}
document.getElementById('btnGuardarPlano').addEventListener('click', guardarPlano);
document.getElementById('btnReintentar').addEventListener('click', cargarPlano);
actualizarInterfaz(); cargarPlano();
