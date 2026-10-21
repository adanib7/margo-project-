// El servidor decide si la sesión sigue válida. Este script mantiene la pantalla
// sincronizada y avisa de actividad real sin contar los sondeos de reservas.
(function () {
  const script = document.currentScript;
  const api = script.dataset.api;
  const login = script.dataset.login;
  let actividad = false;
  let consultando = false;
  let saliendo = false;
  let proximaConsulta = 0;
  let vence = Infinity;

  function salir() {
    if (saliendo) return;
    saliendo = true;
    window.location.replace(login);
  }

  // Cualquier API del sitio que rechace la sesión debe llevar al login.
  const fetchOriginal = window.fetch.bind(window);
  window.fetch = async function (recurso, opciones) {
    const respuesta = await fetchOriginal(recurso, opciones);
    if (respuesta.status === 401) {
      const url = new URL(typeof recurso === 'string' ? recurso : (recurso.url || recurso.href), window.location.href);
      if (url.origin === window.location.origin) salir();
    }
    return respuesta;
  };

  async function comprobar() {
    if (consultando || saliendo) return;
    consultando = true;
    const renovar = actividad;
    actividad = false;
    try {
      const respuesta = await window.fetch(api, {
        method: renovar ? 'POST' : 'GET',
        cache: 'no-store',
        credentials: 'same-origin',
      });
      if (!respuesta.ok) return;
      const datos = await respuesta.json();
      if (!datos.ok) { salir(); return; }
      vence = Date.now() + datos.vence_en * 1000;
      proximaConsulta = Date.now() + 60000;
    } catch {
      // Una interrupción de red no renueva la sesión en el servidor.
      if (renovar) actividad = true;
      proximaConsulta = Date.now() + 15000;
    } finally {
      consultando = false;
    }
  }

  ['pointerdown', 'keydown', 'input', 'scroll'].forEach(evento => {
    document.addEventListener(evento, e => {
      if (e.isTrusted) {
        actividad = true;
        if (vence - Date.now() <= 60000) comprobar();
      }
    }, { passive: true });
  });
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) comprobar();
  });
  // Al volver desde el historial se consulta otra vez al servidor.
  window.addEventListener('pageshow', e => {
    if (e.persisted) window.location.reload();
  });
  setInterval(() => {
    if (Date.now() >= Math.min(proximaConsulta, vence)) comprobar();
  }, 15000);
  comprobar();
})();
