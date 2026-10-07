// Botón del ojito: muestra u oculta la contraseña del campo en el que está.
document.querySelectorAll('.login-ojo').forEach(boton => {
  boton.addEventListener('click', () => {
    const input = boton.parentElement.querySelector('input');
    const mostrar = input.type === 'password';
    input.type = mostrar ? 'text' : 'password';
    boton.querySelector('.material-symbols-outlined').textContent = mostrar ? 'visibility_off' : 'visibility';
    boton.setAttribute('aria-label', mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña');
  });
});

// Pestañas Iniciar sesión / Registrarse: cambian sin recargar la página.
// El CSS hace la animación según el atributo data-modo de la caja.
const caja = document.querySelector('.login-caja');
const titulos = { login: 'Iniciar sesión', registro: 'Crear cuenta' };
const nombreLocal = document.title.split(' · ').slice(1).join(' · ');

function cambiarModo(modo) {
  caja.dataset.modo = modo;
  document.querySelectorAll('.login-vista').forEach(vista => {
    vista.inert = vista.dataset.vista !== modo; // el formulario oculto no recibe foco
  });
  document.querySelectorAll('.login-tabs a').forEach(tab => {
    tab.setAttribute('aria-selected', tab.dataset.modo === modo);
  });
  document.title = titulos[modo] + ' · ' + nombreLocal;
  history.replaceState(null, '', '?modo=' + modo);
}

document.querySelectorAll('a[data-modo]').forEach(enlace => {
  enlace.addEventListener('click', e => {
    e.preventDefault();
    cambiarModo(enlace.dataset.modo);
  });
});

cambiarModo(caja.dataset.modo);
