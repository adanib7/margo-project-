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

// ── Vistas: Iniciar sesión / Registrarse / Recuperar contraseña ─────────────
// Cambian sin recargar la página. El CSS anima según la clase "activa" y el
// atributo data-modo de la caja.
const caja = document.querySelector('.login-caja');
const titulos = { login: 'Iniciar sesión', registro: 'Crear cuenta', recuperar: 'Recuperar contraseña' };
const nombreLocal = document.title.split(' · ').slice(1).join(' · ');

function cambiarModo(modo) {
  caja.dataset.modo = modo;
  document.querySelectorAll('.login-vista').forEach(vista => {
    const activa = vista.dataset.vista === modo;
    vista.classList.toggle('activa', activa);
    vista.inert = !activa; // el formulario oculto no recibe foco
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

// ── Recuperar contraseña (api/recuperar_password.php) ───────────────────────
// Paso 1: el usuario escribe su email y le mandamos un código.
// Paso 2: escribe el código y su nueva contraseña.
const formRec   = document.getElementById('formRecuperar');
const recBoton  = document.getElementById('recBoton');
const recPaso2  = document.getElementById('recPaso2');
const recMsj    = document.getElementById('recMensaje');
const recEmail  = document.getElementById('rec-email');
const reenviar  = document.getElementById('recReenviar');
const API_REC   = '../api/recuperar_password.php';

function mostrarMensaje(contenedor, texto, exito) {
  contenedor.className = 'login-mensaje' + (exito ? ' exito' : '');
  contenedor.innerHTML = '<span class="material-symbols-outlined">' + (exito ? 'check_circle' : 'error') + '</span>';
  contenedor.append(texto);
  contenedor.hidden = false;
}

function limpiarErrores() {
  formRec.querySelectorAll('.login-error').forEach(el => { el.textContent = ''; });
  formRec.querySelectorAll('.login-campo').forEach(el => el.classList.remove('con-error'));
  recMsj.hidden = true;
}

function marcarErrores(errores) {
  Object.entries(errores).forEach(([campo, texto]) => {
    const span = formRec.querySelector('[data-error="' + campo + '"]');
    if (!span) return;
    span.textContent = texto;
    span.previousElementSibling.classList.add('con-error');
  });
}

async function llamarApi(datos) {
  const res = await fetch(API_REC, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(datos),
  });
  return res.json();
}

async function pedirCodigo() {
  const data = await llamarApi({ accion: 'pedir', email: recEmail.value.trim() });
  if (data.errores) { marcarErrores(data.errores); return; }
  if (!data.ok) { mostrarMensaje(recMsj, data.mensaje, false); return; }

  mostrarMensaje(recMsj, data.mensaje, true);
  recPaso2.hidden = false;
  reenviar.hidden = false;
  document.getElementById('recSeparador').hidden = false;
  document.getElementById('recIntro').hidden = true;
  recEmail.readOnly = true;
  recBoton.textContent = 'Cambiar contraseña';
  document.getElementById('rec-codigo').focus();
}

async function cambiarPassword() {
  const data = await llamarApi({
    accion:    'cambiar',
    email:     recEmail.value.trim(),
    codigo:    document.getElementById('rec-codigo').value.trim(),
    password:  document.getElementById('rec-pass').value,
    confirmar: document.getElementById('rec-confirmar').value,
  });
  if (data.errores) { marcarErrores(data.errores); return; }
  if (!data.ok) { mostrarMensaje(recMsj, data.mensaje, false); return; }

  // Listo: volvemos al login con el usuario ya escrito y un mensaje verde.
  const vistaLogin = document.querySelector('.login-vista[data-vista="login"]');
  let msjLogin = vistaLogin.querySelector('.login-mensaje');
  if (!msjLogin) {
    msjLogin = document.createElement('div');
    vistaLogin.querySelector('.login-form-titulo').after(msjLogin);
  }
  mostrarMensaje(msjLogin, data.mensaje, true);
  document.getElementById('usuario').value = data.usuario || '';
  cambiarModo('login');
  document.getElementById('pass').focus();
}

formRec.addEventListener('submit', async e => {
  e.preventDefault();
  limpiarErrores();
  const textoOriginal = recBoton.textContent;
  recBoton.disabled = true;
  recBoton.textContent = 'Un momento…';
  try {
    if (recPaso2.hidden) {
      await pedirCodigo();
    } else {
      await cambiarPassword();
    }
  } catch {
    mostrarMensaje(recMsj, 'No se pudo conectar con el servidor.', false);
  } finally {
    recBoton.disabled = false;
    if (recBoton.textContent === 'Un momento…') recBoton.textContent = textoOriginal;
  }
});

reenviar.addEventListener('click', async e => {
  e.preventDefault();
  limpiarErrores();
  try {
    await pedirCodigo();
  } catch {
    mostrarMensaje(recMsj, 'No se pudo conectar con el servidor.', false);
  }
});
