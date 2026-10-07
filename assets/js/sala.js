// Sala en vivo (dashboards/sala.php).
// Trae las mesas y las reservas del día (api/sala_estado.php) y pinta cada mesa
// según la hora elegida en la barra de tiempo:
//   ocupada  -> tiene una reserva en curso a esa hora
//   próxima  -> le llega una reserva en menos de 1 hora
//   libre    -> nada de lo anterior
(function () {
  const PLANO_W = 900, PLANO_H = 560;
  const AVISO_PROXIMA = 60; // minutos antes de una reserva en que la mesa pasa a "próxima"

  const inputFecha = document.getElementById('salaFecha');
  const slider     = document.getElementById('salaSlider');
  const horaTexto  = document.getElementById('salaHora');
  const btnAhora   = document.getElementById('salaAhora');
  const wrap       = document.getElementById('salaWrap');
  const lienzo     = document.getElementById('salaLienzo');
  const estado     = document.getElementById('salaEstado');
  const lista      = document.getElementById('salaLista');

  let datos = null;           // respuesta de la API
  let siguiendoAhora = true;  // la barra avanza sola con el reloj
  let mesaElegida = null;     // mesa resaltada (clic en el plano o en la lista)
  let relojServidor = null;   // { minutos, tomado } para saber la hora del restaurante

  // ── Utilidades ───────────────────────────────────────────────────────────
  const aMin  = h => parseInt(h.slice(0, 2), 10) * 60 + parseInt(h.slice(3, 5), 10);
  const aHora = m => { // 1500 -> "01:00" (las reservas pueden terminar pasada la medianoche)
    m = ((m % 1440) + 1440) % 1440;
    return String(Math.floor(m / 60)).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0');
  };
  const esc   = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

  // Hora del restaurante (la del servidor, Europe/Madrid), no la de esta compu.
  function minutoAhora() {
    const pasados = Math.floor((Date.now() - relojServidor.tomado) / 60000);
    return relojServidor.minutos + pasados;
  }

  function estadoDeMesa(mesaId, t) {
    const suyas = datos.reservas.filter(r => r.mesa_id === mesaId);
    const enCurso = suyas.find(r => aMin(r.hora) <= t && t < aMin(r.hora) + datos.duracion_min);
    if (enCurso) return { tipo: 'ocupada', reserva: enCurso };
    const proxima = suyas.find(r => aMin(r.hora) > t && aMin(r.hora) - t <= AVISO_PROXIMA);
    if (proxima) return { tipo: 'proxima', reserva: proxima };
    return { tipo: 'libre', reserva: null };
  }

  // ── Cargar datos ─────────────────────────────────────────────────────────
  async function cargar() {
    const fecha = inputFecha.value;
    try {
      const res = await fetch(BASE + '/api/sala_estado.php' + (fecha ? '?fecha=' + fecha : ''), { cache: 'no-store' });
      const data = await res.json();
      if (!data.ok) throw new Error(data.mensaje || 'No se pudo cargar la sala.');

      const primeraVez = datos === null;
      datos = data;
      relojServidor = { minutos: aMin(data.ahora), tomado: Date.now() };

      slider.min = data.desde;
      slider.max = data.hasta;
      if (primeraVez) inputFecha.value = data.fecha;
      if (siguiendoAhora && data.fecha !== data.hoy) siguiendoAhora = false;
      if (siguiendoAhora) slider.value = minutoAhora();
      else if (primeraVez) slider.value = data.desde;

      dibujar();
    } catch (e) {
      estado.hidden = false;
      estado.textContent = e.message;
    }
  }

  // ── Dibujar todo para la hora del slider ─────────────────────────────────
  function dibujar() {
    const t = parseInt(slider.value, 10);
    horaTexto.textContent = aHora(t);
    btnAhora.classList.toggle('activo', siguiendoAhora);

    dibujarPlano(t);
    dibujarLista(t);

    // Resumen
    const ocupadas = datos.mesas.filter(m => estadoDeMesa(m.id, t).tipo === 'ocupada').length;
    const enSala = datos.reservas.filter(r => aMin(r.hora) <= t && t < aMin(r.hora) + datos.duracion_min);
    document.getElementById('statOcupadas').textContent = ocupadas + ' / ' + datos.mesas.length;
    document.getElementById('statComensales').textContent = enSala.reduce((s, r) => s + r.personas, 0);
    document.getElementById('statReservas').textContent = datos.reservas.length;
  }

  function dibujarPlano(t) {
    lienzo.innerHTML = '';
    if (!datos.mesas.length) {
      estado.hidden = false;
      estado.textContent = 'Todavía no hay mesas en el plano. Armalo desde "Plano de mesas".';
      return;
    }
    estado.hidden = true;

    datos.mesas.forEach(m => {
      const { tipo, reserva } = estadoDeMesa(m.id, t);
      const el = document.createElement('button');
      el.type = 'button';
      el.className = 'pr-mesa sala-mesa ' + m.forma + ' sala-' + tipo + (mesaElegida === m.id ? ' elegida' : '');
      el.style.width  = m.ancho + 'px';
      el.style.height = m.alto + 'px';
      el.style.left   = (m.pos_x - m.ancho / 2) + 'px';
      el.style.top    = (m.pos_y - m.alto / 2) + 'px';
      el.style.transform = 'rotate(' + m.rotacion + 'deg)';

      let detalle = '';
      if (tipo === 'ocupada') detalle = 'hasta ' + aHora(aMin(reserva.hora) + datos.duracion_min);
      if (tipo === 'proxima') detalle = reserva.hora;
      el.innerHTML = '<span>' + m.numero + '</span>' + (detalle ? '<small>' + detalle + '</small>' : '');
      el.title = 'Mesa ' + m.numero + ' · ' + m.capacidad + ' pers.'
        + (reserva ? ' · ' + reserva.nombre + ' (' + reserva.personas + ') ' + reserva.hora : ' · libre');

      el.addEventListener('click', () => {
        mesaElegida = mesaElegida === m.id ? null : m.id;
        dibujar();
      });
      lienzo.appendChild(el);
    });
    requestAnimationFrame(escalar);
  }

  function dibujarLista(t) {
    if (!datos.reservas.length) {
      lista.innerHTML = '<p class="sala-vacio">No hay reservas para este día.</p>';
      return;
    }
    const numeroMesa = id => (datos.mesas.find(m => m.id === id) || {}).numero;

    lista.innerHTML = datos.reservas.map(r => {
      const inicio = aMin(r.hora), fin = inicio + datos.duracion_min;
      let clase = 'futura', etiqueta = '';
      if (t >= fin)                               { clase = 'pasada'; }
      else if (t >= inicio)                       { clase = 'en-curso'; etiqueta = 'En sala'; }
      else if (inicio - t <= AVISO_PROXIMA)       { clase = 'proxima'; etiqueta = 'En ' + (inicio - t) + ' min'; }
      if (r.mesa_id !== null && r.mesa_id === mesaElegida) clase += ' resaltada';

      const mesa = numeroMesa(r.mesa_id);
      return '<button type="button" class="sala-item ' + clase + '" data-mesa="' + (r.mesa_id ?? '') + '">'
        + '<span class="sala-item-hora">' + esc(r.hora) + '</span>'
        + '<span class="sala-item-info"><strong>' + esc(r.nombre) + '</strong>'
        + '<span>' + r.personas + ' pers. · ' + (mesa ? 'Mesa ' + mesa : 'sin mesa')
        + (r.estado === 'pendiente' ? ' · pendiente' : '') + '</span></span>'
        + (etiqueta ? '<span class="sala-item-etiqueta">' + etiqueta + '</span>' : '')
        + '</button>';
    }).join('');

    lista.querySelectorAll('.sala-item').forEach(item => item.addEventListener('click', () => {
      const id = item.dataset.mesa ? parseInt(item.dataset.mesa, 10) : null;
      mesaElegida = mesaElegida === id ? null : id;
      dibujar();
    }));
  }

  // El plano mide 900x560: lo achicamos para que entre en el ancho disponible.
  function escalar() {
    const escala = Math.min(1, wrap.clientWidth / PLANO_W);
    lienzo.style.transform = 'scale(' + escala + ')';
    wrap.style.height = (PLANO_H * escala) + 'px';
  }
  window.addEventListener('resize', escalar);

  // ── Controles ────────────────────────────────────────────────────────────
  slider.addEventListener('input', () => {
    siguiendoAhora = false;
    dibujar();
  });

  inputFecha.addEventListener('change', () => {
    siguiendoAhora = false;
    mesaElegida = null;
    cargar();
  });

  btnAhora.addEventListener('click', () => {
    siguiendoAhora = true;
    if (inputFecha.value !== datos.hoy) {
      inputFecha.value = datos.hoy;
      cargar();
    } else {
      slider.value = minutoAhora();
      dibujar();
    }
  });

  // En modo "Ahora", la barra avanza sola cada minuto.
  setInterval(() => {
    if (datos && siguiendoAhora) {
      slider.value = minutoAhora();
      dibujar();
    }
  }, 60000);

  // Cada 30 segundos se vuelven a pedir las reservas, y al instante si avisos.js
  // detecta una reserva nueva.
  setInterval(cargar, 30000);
  document.addEventListener('reservas:nuevas', cargar);

  cargar();
})();
