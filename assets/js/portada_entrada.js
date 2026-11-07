// Entrada del título y aparición de tarjetas y fotos al recorrer la portada.
(() => {
  const raiz = document.documentElement;
  const titulo = document.querySelector('.hero h1');
  if (!titulo) return;
  const movimientoReducido = window.matchMedia('(prefers-reduced-motion: reduce)');
  const animarAlEntrar = raiz.classList.contains('entrada-preparando');
  let fin = null, fotograma = null;
  const bloquesScroll = [...document.querySelectorAll('#cocina .dish, #galeria .gallery > .ph, #historia .split > .ph')];
  const finalesScroll = new Map();
  let observadorScroll = null;

  function mostrarBloque(bloque) {
    clearTimeout(finalesScroll.get(bloque));
    finalesScroll.delete(bloque);
    bloque.removeAttribute('data-entrada-scroll');
    bloque.classList.remove('entrada-scroll');
    bloque.style.removeProperty('--entrada-retraso');
    observadorScroll?.unobserve(bloque);
  }
  function mostrarRecorrido() {
    observadorScroll?.disconnect();
    observadorScroll = null;
    bloquesScroll.forEach(mostrarBloque);
  }
  function prepararRecorrido() {
    mostrarRecorrido();
    if (!('IntersectionObserver' in window)) return;
    observadorScroll = new IntersectionObserver((entradas, observador) => {
      if (observador !== observadorScroll) return;
      let orden = 0;
      for (const entrada of entradas) {
        if (!entrada.isIntersecting) continue;
        const bloque = entrada.target;
        const retraso = movimientoReducido.matches ? 0 : Math.min(orden++ * 60, 120);
        bloque.style.setProperty('--entrada-retraso', retraso + 'ms');
        bloque.dataset.entradaScroll = 'visible';
        observador.unobserve(bloque); // Una vez por bloque, aunque se vuelva a subir.
        const duracion = movimientoReducido.matches ? 180 : 360;
        finalesScroll.set(bloque, setTimeout(() => mostrarBloque(bloque), duracion + retraso + 50));
      }
    }, { threshold: .12, rootMargin: '0px 0px -24px 0px' });
    for (const bloque of bloquesScroll) {
      // Una recarga a mitad de página conserva visibles los bloques ya recorridos.
      if (bloque.getBoundingClientRect().bottom <= 0) continue;
      bloque.classList.add('entrada-scroll');
      bloque.dataset.entradaScroll = 'pendiente';
      observadorScroll.observe(bloque);
    }
  }

  function terminar() {
    clearTimeout(window.esperaEntradaPortada);
    clearTimeout(fin);
    cancelAnimationFrame(fotograma);
    raiz.classList.remove('entrada-preparando', 'entrada-en-curso');
  }
  function prepararPalabras() {
    const texto = titulo.textContent.trim();
    titulo.setAttribute('aria-label', texto);
    const palabras = texto.split(/\s+/);
    const contenido = document.createDocumentFragment();
    palabras.forEach((palabra, indice) => {
      if (indice) contenido.append(document.createTextNode(' '));
      const mascara = document.createElement('span');
      const interior = document.createElement('span');
      mascara.className = 'entrada-mascara';
      mascara.setAttribute('aria-hidden', 'true');
      interior.className = 'entrada-palabra';
      interior.textContent = palabra;
      // La última palabra llega un instante después, sin alargar las traducciones.
      interior.style.animationDelay = Math.min(indice * 65, 260) + (indice === palabras.length - 1 ? 55 : 0) + 'ms';
      mascara.append(interior); contenido.append(mascara);
    });
    titulo.replaceChildren(contenido);
  }
  function reproducir() {
    terminar();
    prepararPalabras();
    raiz.classList.add('entrada-preparando');
    // Separar los estados en dos fotogramas permite iniciar la entrada.
    fotograma = requestAnimationFrame(() => {
      fotograma = requestAnimationFrame(() => {
        raiz.classList.remove('entrada-preparando');
        raiz.classList.add('entrada-en-curso');
        fin = setTimeout(terminar, movimientoReducido.matches ? 210 : 1150);
      });
    });
  }
  document.addEventListener('portada:idioma', () => { terminar(); prepararPalabras(); });
  document.addEventListener('focusin', e => {
    terminar();
    const bloque = e.target.closest('[data-entrada-scroll]');
    if (bloque) mostrarBloque(bloque);
  });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') terminar(); });
  document.addEventListener('visibilitychange', () => { if (document.hidden) { terminar(); mostrarRecorrido(); } });
  movimientoReducido.addEventListener('change', () => { terminar(); mostrarRecorrido(); });
  window.addEventListener('pageshow', e => { if (e.persisted) { terminar(); mostrarRecorrido(); } });

  prepararRecorrido();

  if (animarAlEntrar) {
    const foto = document.querySelector('.hero .bg img');
    const recursos = [document.fonts.ready];
    if (foto?.decode) recursos.push(foto.decode());
    // Esperar solo a lo esencial, con límite: el resto de la página carga en paralelo.
    Promise.race([
      Promise.allSettled(recursos),
      new Promise(resolve => setTimeout(resolve, 400)),
    ]).then(() => {
      if (raiz.classList.contains('entrada-preparando')) reproducir();
    }).catch(terminar);
  }
})();
