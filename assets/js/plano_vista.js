// La cámara transforma coordenadas del plano a píxeles. Nunca cambia las mesas.
function crearVistaPlano(escenario, ancho, alto, acciones) {
  const superficie = escenario.container();
  const marco = superficie.parentElement;
  const punteros = new Map();
  let escalaBase = 1, zoom = 1, gesto = null;
  const limitar = (n, min, max) => Math.min(max, Math.max(min, n));
  const distancia = (a, b) => Math.hypot(a.x - b.x, a.y - b.y);
  const medio = (a, b) => ({ x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 });
  const centro = () => ({ x: escenario.width() / 2, y: escenario.height() / 2 });
  function posicionEvento(e) {
    const caja = superficie.getBoundingClientRect();
    return { x: e.clientX - caja.left, y: e.clientY - caja.top };
  }
  function aPlano(punto) {
    return { x: (punto.x - escenario.x()) / escenario.scaleX(), y: (punto.y - escenario.y()) / escenario.scaleY() };
  }
  function actualizar() {
    const escala = escenario.scaleX();
    // La mira puede llegar a los bordes, pero no perderse fuera del salón.
    const c = centro();
    escenario.position({ x: limitar(escenario.x(), c.x - ancho * escala, c.x), y: limitar(escenario.y(), c.y - alto * escala, c.y) });
    superficie.style.backgroundSize = `${40 * escala}px ${40 * escala}px`;
    superficie.style.backgroundPosition = `${escenario.x()}px ${escenario.y()}px`;
    document.getElementById('zoomValor').textContent = Math.round(zoom * 100) + '%';
    document.getElementById('zoomMenos').disabled = zoom <= 1.001;
    document.getElementById('zoomMas').disabled = zoom >= 8;
    acciones.camara(aPlano(centro()));
    escenario.batchDraw();
  }
  function ampliar(valor, punto = centro(), destino = punto) {
    const mundo = aPlano(punto);
    zoom = limitar(valor, 1, 8);
    const escala = escalaBase * zoom;
    escenario.scale({ x: escala, y: escala });
    escenario.position({ x: destino.x - mundo.x * escala, y: destino.y - mundo.y * escala });
    actualizar();
  }
  function encuadrar(punto, factor) {
    zoom = limitar(factor, 1, 8);
    const escala = escalaBase * zoom, c = centro();
    escenario.scale({ x: escala, y: escala });
    escenario.position({ x: c.x - punto.x * escala, y: c.y - punto.y * escala });
    actualizar();
  }
  function ajustar() {
    const anterior = escalaBase ? aPlano(centro()) : { x: ancho / 2, y: alto / 2 };
    escenario.size({ width: marco.clientWidth, height: marco.clientHeight });
    escalaBase = Math.min((escenario.width() - 24) / ancho, (escenario.height() - 24) / alto);
    encuadrar(zoom === 1 ? { x: ancho / 2, y: alto / 2 } : anterior, zoom);
  }
  superficie.addEventListener('wheel', e => {
    e.preventDefault();
    if (!acciones.bloqueada()) ampliar(zoom * Math.exp(-e.deltaY * (e.deltaMode === 1 ? 0.04 : 0.002)), posicionEvento(e));
  }, { passive: false });
  superficie.addEventListener('pointerdown', e => {
    if (acciones.bloqueada() || (e.pointerType === 'mouse' && e.button !== 0)) return;
    const punto = posicionEvento(e);
    superficie.setPointerCapture(e.pointerId);
    superficie.focus({ preventScroll: true });
    punteros.set(e.pointerId, punto);
    if (punteros.size === 2) {
      if (gesto?.arrastrando) acciones.finArrastre(false);
      const [a, b] = [...punteros.values()];
      gesto = { tipo: 'pinza', centro: medio(a, b), distancia: distancia(a, b) };
    } else if (punteros.size === 1) {
      const mesa = acciones.mesaEn(punto);
      gesto = { tipo: 'inicio', inicio: punto, ultimo: punto, mesa, mouse: e.pointerType === 'mouse', movido: false };
      if (gesto.mouse && mesa && !acciones.colocando()) acciones.seleccionar(mesa);
    }
  });
  superficie.addEventListener('pointermove', e => {
    const punto = posicionEvento(e);
    if (!punteros.has(e.pointerId)) {
      if (e.pointerType === 'mouse') superficie.style.cursor = 'grab';
      return;
    }
    punteros.set(e.pointerId, punto);
    if (punteros.size === 2 && gesto?.tipo === 'pinza') {
      const [a, b] = [...punteros.values()], c = medio(a, b), d = distancia(a, b);
      if (gesto.distancia > 0) ampliar(zoom * d / gesto.distancia, gesto.centro, c);
      gesto.centro = c; gesto.distancia = d;
      return;
    }
    if (punteros.size !== 1 || !gesto || gesto.tipo === 'esperar') return;
    if (!gesto.movido && distancia(gesto.inicio, punto) < 6) return;
    if (!gesto.movido) {
      gesto.movido = true;
      gesto.arrastrando = gesto.mouse && gesto.mesa && !acciones.colocando();
      if (gesto.arrastrando) {
        const inicio = aPlano(gesto.inicio);
        gesto.desfase = { x: inicio.x - gesto.mesa.x(), y: inicio.y - gesto.mesa.y() };
        acciones.inicioArrastre(gesto.mesa);
      }
    }
    if (gesto.arrastrando) {
      const mundo = aPlano(punto);
      acciones.arrastrar({ x: mundo.x - gesto.desfase.x, y: mundo.y - gesto.desfase.y });
    } else {
      escenario.position({ x: escenario.x() + punto.x - gesto.ultimo.x, y: escenario.y() + punto.y - gesto.ultimo.y });
      actualizar();
    }
    gesto.ultimo = punto;
    superficie.style.cursor = 'grabbing';
  });
  function soltar(e, cancelar = false) {
    if (!punteros.has(e.pointerId)) return;
    punteros.delete(e.pointerId);
    if (gesto?.arrastrando) acciones.finArrastre(!cancelar);
    else if (!cancelar && gesto?.tipo === 'inicio' && !gesto.movido && !acciones.colocando()) acciones.seleccionar(gesto.mesa);
    gesto = punteros.size ? { tipo: 'esperar' } : null;
    superficie.style.cursor = 'grab';
  }
  superficie.addEventListener('pointerup', e => soltar(e));
  superficie.addEventListener('pointercancel', e => soltar(e, true));
  superficie.addEventListener('lostpointercapture', e => soltar(e, true));
  window.addEventListener('blur', () => {
    if (gesto?.arrastrando) acciones.finArrastre(false);
    punteros.clear(); gesto = null;
  });
  document.getElementById('zoomMas').addEventListener('click', () => ampliar(zoom * 1.3));
  document.getElementById('zoomMenos').addEventListener('click', () => ampliar(zoom / 1.3));
  document.getElementById('zoomAjustar').addEventListener('click', () => encuadrar({ x: ancho / 2, y: alto / 2 }, 1));
  new ResizeObserver(ajustar).observe(marco);
  ajustar();
  return {
    aPlano,
    verTodo: () => encuadrar({ x: ancho / 2, y: alto / 2 }, 1),
    centrar: punto => encuadrar(punto, zoom),
    acercar: mesa => {
      const caja = mesa.getClientRect({ relativeTo: mesa.getLayer() });
      const escala = Math.min(escenario.width() / Math.max(300, caja.width + 100), escenario.height() / Math.max(240, caja.height + 100));
      encuadrar(mesa.position(), Math.max(1, escala / escalaBase));
    },
  };
}
