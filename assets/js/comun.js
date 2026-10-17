// Se carga antes de los scripts de cada pantalla desde includes/header.php.
// Escapar datos antes de incluirlos en HTML evita que se interpreten como etiquetas.
function escaparHtml(texto) {
  return String(texto ?? '')
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

// Las pantallas comparten el mismo aviso y los mismos estilos de dashboard.css.
function mostrarToast(texto, tipo, duracion = 3500) {
  const aviso = document.createElement('div');
  aviso.className = 'toast toast-' + tipo;

  const icono = document.createElement('span');
  icono.className = 'material-symbols-outlined toast-icono';
  icono.textContent = tipo === 'exito' ? 'check_circle' : 'error';

  const mensaje = document.createElement('span');
  mensaje.textContent = texto;
  aviso.append(icono, mensaje);
  document.body.appendChild(aviso);

  aviso.getBoundingClientRect();
  aviso.classList.add('toast-visible');
  setTimeout(() => {
    aviso.classList.remove('toast-visible');
    // Esperar los 250 ms de la transicion CSS antes de quitar el elemento.
    setTimeout(() => aviso.remove(), 250);
  }, duracion);
}
