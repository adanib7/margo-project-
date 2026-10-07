// Avisos en vivo para el admin: cada 10 segundos le pregunta al servidor si
// entraron reservas nuevas (api/reservas_nuevas.php). Si hay, muestra una
// tarjeta abajo a la derecha, suena un "ding" y la pestaña muestra (N).
// Se carga desde includes/footer.php solo para admin y superadmin.
(function () {
  const script      = document.currentScript;
  const API         = script.dataset.api;
  const URL_RESERVAS = script.dataset.reservas;
  const INTERVALO   = 10000; // milisegundos entre consultas

  let ultimoId  = null;      // la última reserva que ya conocemos
  let sinLeer   = 0;         // contador que se muestra en el título de la pestaña
  const tituloOriginal = document.title;

  const contenedor = document.createElement('div');
  contenedor.className = 'aviso-pila';
  contenedor.setAttribute('aria-live', 'polite');
  document.body.appendChild(contenedor);

  function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
  }

  function fechaCorta(iso) {
    const [a, m, d] = iso.split('-').map(Number);
    return new Date(a, m - 1, d).toLocaleDateString('es-ES', { weekday: 'short', day: 'numeric', month: 'short' });
  }

  // "Ding" de dos notas generado con el navegador (no hace falta un archivo de audio).
  // Los navegadores solo dejan sonar después de que el usuario tocó la página una vez.
  let audio = null;
  document.addEventListener('pointerdown', () => {
    if (!audio) audio = new (window.AudioContext || window.webkitAudioContext)();
  }, { once: true });

  function sonar() {
    if (!audio) return;
    [[880, 0], [1320, 0.15]].forEach(([frecuencia, inicio]) => {
      const osc = audio.createOscillator();
      const vol = audio.createGain();
      osc.frequency.value = frecuencia;
      vol.gain.setValueAtTime(0.0001, audio.currentTime + inicio);
      vol.gain.exponentialRampToValueAtTime(0.25, audio.currentTime + inicio + 0.02);
      vol.gain.exponentialRampToValueAtTime(0.0001, audio.currentTime + inicio + 0.6);
      osc.connect(vol).connect(audio.destination);
      osc.start(audio.currentTime + inicio);
      osc.stop(audio.currentTime + inicio + 0.65);
    });
  }

  function actualizarTitulo() {
    document.title = sinLeer > 0 ? '(' + sinLeer + ') ' + tituloOriginal : tituloOriginal;
  }

  function mostrarAviso(r) {
    const tarjeta = document.createElement('a');
    tarjeta.className = 'aviso-tarjeta';
    tarjeta.href = URL_RESERVAS;
    tarjeta.innerHTML =
        '<span class="aviso-icono material-symbols-outlined">notifications_active</span>'
      + '<span class="aviso-texto">'
      +   '<strong>Nueva reserva · ' + esc(r.codigo) + '</strong>'
      +   '<span>' + esc(r.nombre) + ' · ' + r.personas + (r.personas === 1 ? ' persona' : ' personas') + '</span>'
      +   '<span>' + esc(fechaCorta(r.fecha)) + ' · ' + esc(r.hora) + ' h' + (r.mesa ? ' · Mesa ' + r.mesa : '') + '</span>'
      + '</span>'
      + '<button type="button" class="aviso-cerrar" aria-label="Cerrar"><span class="material-symbols-outlined">close</span></button>';

    tarjeta.querySelector('.aviso-cerrar').addEventListener('click', e => {
      e.preventDefault();
      cerrar(tarjeta);
    });
    contenedor.prepend(tarjeta);
    requestAnimationFrame(() => tarjeta.classList.add('visible'));
    setTimeout(() => cerrar(tarjeta), 20000); // se va sola a los 20 segundos
  }

  function cerrar(tarjeta) {
    tarjeta.classList.remove('visible');
    setTimeout(() => tarjeta.remove(), 300);
  }

  async function consultar() {
    try {
      const url = API + (ultimoId === null ? '' : '?desde_id=' + ultimoId);
      const data = await (await fetch(url, { cache: 'no-store' })).json();
      if (!data.ok) return;

      if (ultimoId !== null && data.reservas.length) {
        data.reservas.forEach(mostrarAviso);
        if (document.hidden) { // el (N) solo sirve si está mirando otra pestaña
          sinLeer += data.reservas.length;
          actualizarTitulo();
        }
        sonar();
        // Avisamos a la página por si quiere refrescar algo (ej. la tabla de Reservas).
        document.dispatchEvent(new CustomEvent('reservas:nuevas', { detail: data.reservas }));
      }
      ultimoId = data.ultimo_id;
    } catch {
      /* sin conexión: se reintenta en la próxima vuelta */
    }
  }

  // Al volver a mirar la pestaña, el contador se limpia.
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) { sinLeer = 0; actualizarTitulo(); }
  });

  consultar();
  setInterval(consultar, INTERVALO);
})();
