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
