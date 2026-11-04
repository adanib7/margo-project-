
const ANCHO = 900, ALTO = 560, GRILLA = 10;
const MIN_TAM = 30, MAX_TAM = 400;

const plano = document.getElementById('plano');
const panelMesa = document.getElementById('panel-mesa');
const inNumero = document.getElementById('in-numero');
const inCapacidad = document.getElementById('in-capacidad');
const aviso = document.getElementById('aviso');

let seleccionada = null;
const eliminadas = [];

function clamp(v, min, max) {
  return Math.min(Math.max(v, min), max);
}

function normalizarAngulo(a) {
  a = a % 360;
  return a < 0 ? a + 360 : a;
}

/* Aplica posición, tamaño y rotación */
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

/* NUEVO: dibujar mesa triangular con SVG */
function dibujarTriangulo(el) {
  el.style.background = 'transparent';
  el.style.border = 'none';
  el.style.borderRadius = '0';

  const ns = 'http://www.w3.org/2000/svg';

  const svg = document.createElementNS(ns, 'svg');
  svg.setAttribute('viewBox', '0 0 100 100');
  svg.setAttribute('preserveAspectRatio', 'none');
  svg.setAttribute('aria-hidden', 'true');

  Object.assign(svg.style, {
    position: 'absolute',
    inset: '0',
    width: '100%',
    height: '100%',
    overflow: 'visible',
    pointerEvents: 'none',
    zIndex: '0',
  });

  const poligono = document.createElementNS(ns, 'polygon');
  poligono.setAttribute('points', '50,0 100,100 0,100');
  poligono.setAttribute('fill', '#9DB9ED');
  poligono.setAttribute('stroke', '#3164BC');
  poligono.setAttribute('stroke-width', '3');
  poligono.setAttribute('stroke-linejoin', 'round');

  svg.appendChild(poligono);
  el.appendChild(svg);
}

/* Crear mesa */
function crearMesa(datos) {
  const el = document.createElement('div');

  const formasValidas = [
    'redonda',
    'cuadrada',
    'triangular'
  ];

  const forma = formasValidas.includes(datos.forma)
    ? datos.forma
    : 'cuadrada';

  el.className = 'mesa ' + forma;
  el.dataset.mesaId = datos.id || '';
  el.dataset.numero = datos.numero ?? 1;
  el.dataset.capacidad = datos.capacidad ?? 4;
  el.dataset.forma = forma;

  // NUEVO: dibujar triángulo
  if (forma === 'triangular') {
    dibujarTriangulo(el);
  }

  const numero = document.createElement('span');
  numero.className = 'mesa-numero';
  numero.textContent = datos.numero ?? 1;

  if (forma === 'triangular') {
    numero.style.zIndex = '1';
  }

  el.appendChild(numero);

  // Manijas para cambiar el tamaño
  ['tl', 'tr', 'bl', 'br'].forEach(pos => {
    const h = document.createElement('div');
    h.className = `handle esquina ${pos}`;
    h.dataset.handle = pos;

    if (forma === 'triangular') {
      h.style.zIndex = '2';
    }

    el.appendChild(h);
    activarResize(el, h);
  });

  // Manija para rotar
  const rotHandle = document.createElement('div');
  rotHandle.className = 'handle rotar';

  if (forma === 'triangular') {
    rotHandle.style.zIndex = '2';
  }

  el.appendChild(rotHandle);
  activarRotacion(el, rotHandle);

  el._x = parseFloat(datos.pos_x) || 100;
  el._y = parseFloat(datos.pos_y) || 100;
  el._w = parseFloat(datos.ancho) || 70;
  el._h = parseFloat(datos.alto) || 70;
  el._rot = parseFloat(datos.rotacion) || 0;

  renderMesa(el);

  plano.appendChild(el);

  if (
    forma === 'triangular' &&
    getComputedStyle(numero).position === 'static'
  ) {
    numero.style.position = 'relative';
  }

  activarArrastre(el);
  el.addEventListener('pointerdown', () => seleccionar(el));

  return el;
}

/* Agregar nueva mesa */
function agregarMesa(forma) {
  const numeros = Array.from(
    plano.querySelectorAll('.mesa')
  ).map(el => parseInt(el.dataset.numero) || 0);

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

/* Seleccionar una mesa */
function seleccionar(el) {
  if (seleccionada === el) return;

  if (seleccionada) {
    seleccionada.classList.remove('seleccionada');
  }

  seleccionada = el;

  if (el) {
    el.classList.add('seleccionada');
    plano.appendChild(el);
  }

  panelMesa.classList.toggle('visible', !!el);

  if (el) {
    inNumero.value = el.dataset.numero;
    inCapacidad.value = el.dataset.capacidad;
  }
}

/* Deseleccionar al hacer clic en el fondo */
plano.addEventListener('pointerdown', e => {
  if (e.target === plano) seleccionar(null);
});

/* Atajos de teclado */
document.addEventListener('keydown', e => {
  if (document.activeElement.tagName === 'INPUT') return;

  if (
    (e.key === 'Delete' || e.key === 'Backspace') &&
    seleccionada
  ) {
    eliminarSeleccionada();
  } else if (e.key === 'Escape') {
    seleccionar(null);
  }
});

/* Cambiar número de mesa */
inNumero.addEventListener('input', e => {
  if (!seleccionada) return;

  const v = parseInt(e.target.value) || 1;
  seleccionada.dataset.numero = v;
  seleccionada.querySelector('.mesa-numero').textContent = v;
});

/* Cambiar capacidad */
inCapacidad.addEventListener('input', e => {
  if (!seleccionada) return;

  seleccionada.dataset.capacidad =
    parseInt(e.target.value) || 1;
});

/* Eliminar mesa */
function eliminarSeleccionada() {
  if (!seleccionada) return;

  if (seleccionada.dataset.mesaId) {
    eliminadas.push(seleccionada.dataset.mesaId);
  }

  seleccionada.remove();
  seleccionar(null);
}

/* Arrastrar mesas */
function activarArrastre(el) {
  el.addEventListener('pointerdown', e => {
    if (e.target.classList.contains('handle')) return;

    e.stopPropagation();
    el.setPointerCapture(e.pointerId);

    const startMouseX = e.clientX;
    const startMouseY = e.clientY;
    const startX = el._x;
    const startY = el._y;

    function mover(ev) {
      const nx = clamp(
        startX + (ev.clientX - startMouseX),
        el._w / 2,
        ANCHO - el._w / 2
      );

      const ny = clamp(
        startY + (ev.clientY - startMouseY),
        el._h / 2,
        ALTO - el._h / 2
      );

      setGeom(el, { x: nx, y: ny });
    }

    function soltar() {
      setGeom(el, {
        x: clamp(
          Math.round(el._x / GRILLA) * GRILLA,
          el._w / 2,
          ANCHO - el._w / 2
        ),
        y: clamp(
          Math.round(el._y / GRILLA) * GRILLA,
          el._h / 2,
          ALTO - el._h / 2
        ),
      });

      el.removeEventListener('pointermove', mover);
      el.removeEventListener('pointerup', soltar);
    }

    el.addEventListener('pointermove', mover);
    el.addEventListener('pointerup', soltar);
  });
}

/* Redimensionar mesas */
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

      const dx = mx - el._x;
      const dy = my - el._y;
      const rad = -el._rot * Math.PI / 180;

      return {
        lx: dx * Math.cos(rad) - dy * Math.sin(rad),
        ly: dx * Math.sin(rad) + dy * Math.cos(rad),
      };
    }

    function mover(ev) {
      const { lx, ly } = coordLocal(ev);

      if (esCirculo) {
        const d = clamp(
          Math.hypot(lx, ly) * 2,
          MIN_TAM,
          MAX_TAM
        );

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

/* Rotar mesas con imán cada 45 grados */
function activarRotacion(el, handle) {
  handle.addEventListener('pointerdown', e => {
    e.stopPropagation();
    seleccionar(el);
    handle.setPointerCapture(e.pointerId);

    const rectContenedor = plano.getBoundingClientRect();

    function mover(ev) {
      const mx = ev.clientX - rectContenedor.left;
      const my = ev.clientY - rectContenedor.top;

      let ang = normalizarAngulo(
        Math.atan2(my - el._y, mx - el._x) *
        180 / Math.PI + 90
      );

      const cercano45 =
        Math.round(ang / 45) * 45 % 360;

      const dif = Math.min(
        Math.abs(ang - cercano45),
        360 - Math.abs(ang - cercano45)
      );

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

/* Cargar mesas desde PHP */
async function cargarPlano() {
  const res = await fetch('admin_plano.php?action=cargar');
  const mesas = await res.json();

  mesas.forEach(crearMesa);
}

/* Guardar mesas en PHP */
async function guardarPlano() {
  const mesas = Array.from(
    plano.querySelectorAll('.mesa')
  ).map(el => ({
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
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      action: 'guardar',
      mesas,
      eliminadas
    }),
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
    aviso.textContent =
      datos.error || 'Error al guardar';
  }
}

/* Inicializar */
cargarPlano();
