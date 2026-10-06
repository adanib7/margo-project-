(function () {
  const modal       = document.getElementById('modalCrearAdmin');
  const btnAbrir    = document.getElementById('btnAbrirModal');
  const btnCerrar   = document.getElementById('btnCerrarModal');
  const btnCancelar = document.getElementById('btnCancelarModal');
  const form        = document.getElementById('formCrearAdmin');
  const togglePwd   = document.getElementById('togglePassword');
  const inputPwd    = document.getElementById('adminPassword');

  // ── Modal open / close ───────────────────────────────────────────────────
  function abrirModal() {
    modal.classList.add('modal-visible');
    document.body.classList.add('modal-abierto');
    document.getElementById('adminNombre').focus();
  }

  function cerrarModal() {
    modal.classList.remove('modal-visible');
    document.body.classList.remove('modal-abierto');
    form.reset();
    limpiarErrores();
    restaurarBoton();
  }

  function limpiarErrores() {
    document.querySelectorAll('.campo-error').forEach(el => el.textContent = '');
    document.querySelectorAll('.campo-wrapper-error').forEach(el => el.classList.remove('campo-wrapper-error'));
  }

  function marcarError(campo, mensajeId, texto) {
    campo.closest('.campo-input-wrapper').classList.add('campo-wrapper-error');
    document.getElementById(mensajeId).textContent = texto;
  }

  btnAbrir.addEventListener('click', abrirModal);
  btnCerrar.addEventListener('click', cerrarModal);
  btnCancelar.addEventListener('click', cerrarModal);
  modal.addEventListener('click', e => { if (e.target === modal) cerrarModal(); });
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && modal.classList.contains('modal-visible')) cerrarModal();
  });

  // ── Toggle password ──────────────────────────────────────────────────────
  togglePwd.addEventListener('click', function () {
    const oculto = inputPwd.type === 'password';
    inputPwd.type = oculto ? 'text' : 'password';
    togglePwd.querySelector('span').textContent = oculto ? 'visibility_off' : 'visibility';
  });

  // ── Botón submit ─────────────────────────────────────────────────────────
  const btnSubmit = form.querySelector('.boton-modal-submit');

  function setLoading(on) {
    btnSubmit.disabled = on;
    btnSubmit.innerHTML = on
      ? '<span class="material-symbols-outlined icono-spin">progress_activity</span> Creando...'
      : '<span class="material-symbols-outlined">check</span> Crear Admin';
  }

  function restaurarBoton() { setLoading(false); }

  // ── Validación cliente ───────────────────────────────────────────────────
  function validarFrontend() {
    const nombre   = document.getElementById('adminNombre');
    const email    = document.getElementById('adminEmail');
    const password = inputPwd;
    let valido = true;

    if (!nombre.value.trim()) {
      marcarError(nombre, 'errorNombre', 'El nombre es obligatorio.');
      valido = false;
    }

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email.value.trim())) {
      marcarError(email, 'errorEmail', 'Ingresá un correo válido.');
      valido = false;
    }

    if (password.value.length < 8) {
      marcarError(password, 'errorPassword', 'Mínimo 8 caracteres.');
      valido = false;
    } else if (!/[A-Z]/.test(password.value)) {
      marcarError(password, 'errorPassword', 'Debe tener al menos una mayúscula.');
      valido = false;
    } else if (!/[0-9]/.test(password.value)) {
      marcarError(password, 'errorPassword', 'Debe tener al menos un número.');
      valido = false;
    }

    return valido;
  }

  // ── Submit ────────────────────────────────────────────────────────────────
  form.addEventListener('submit', async function (e) {
    e.preventDefault();
    limpiarErrores();
    if (!validarFrontend()) return;

    setLoading(true);

    try {
      const res  = await fetch(BASE_URL + '/api/crear_admin.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({
          nombre:   document.getElementById('adminNombre').value.trim(),
          email:    document.getElementById('adminEmail').value.trim(),
          password: inputPwd.value,
        }),
      });

      const data = await res.json();

      if (data.ok) {
        cerrarModal();
        mostrarToast(data.mensaje, 'exito');
      } else if (data.errores) {
        // Errores de campo devueltos por el servidor
        const map = {
          nombre:   ['adminNombre',   'errorNombre'],
          email:    ['adminEmail',    'errorEmail'],
          password: ['adminPassword', 'errorPassword'],
        };
        Object.entries(data.errores).forEach(([campo, msg]) => {
          if (map[campo]) {
            const [inputId, errorId] = map[campo];
            const el = document.getElementById(inputId);
            marcarError(el, errorId, msg);
          }
        });
        restaurarBoton();
      } else {
        mostrarToast(data.mensaje || 'Error inesperado.', 'error');
        restaurarBoton();
      }
    } catch (err) {
      mostrarToast('No se pudo conectar con el servidor.', 'error');
      restaurarBoton();
    }
  });

  // ── Toast ─────────────────────────────────────────────────────────────────
  function mostrarToast(texto, tipo) {
    const toast = document.createElement('div');
    toast.className = 'toast toast-' + tipo;
    toast.innerHTML =
      '<span class="material-symbols-outlined toast-icono">' +
        (tipo === 'exito' ? 'check_circle' : 'error') +
      '</span>' +
      '<span>' + texto + '</span>';
    document.body.appendChild(toast);

    // Forzar reflow para que la animación arranque
    toast.getBoundingClientRect();
    toast.classList.add('toast-visible');

    setTimeout(() => {
      toast.classList.remove('toast-visible');
      toast.addEventListener('transitionend', () => toast.remove(), { once: true });
    }, 3500);
  }
})();

/* ── Aceternity Card Hover Effect (vanilla JS) ── */
(function () {
  const grid      = document.getElementById('grillaTarjetas');
  const highlight = document.getElementById('tarjetaHighlight');
  const cards     = grid.querySelectorAll('.tarjeta');
  const PAD       = 10; // breathing room around each card (px)
  let leaveTimer  = null;
  let visible     = false;

  function snap(card) {
    const gRect = grid.getBoundingClientRect();
    const cRect = card.getBoundingClientRect();
    highlight.style.left   = (cRect.left - gRect.left - PAD) + 'px';
    highlight.style.top    = (cRect.top  - gRect.top  - PAD) + 'px';
    highlight.style.width  = (cRect.width  + PAD * 2) + 'px';
    highlight.style.height = (cRect.height + PAD * 2) + 'px';
  }

  cards.forEach(card => {
    card.addEventListener('mouseenter', () => {
      clearTimeout(leaveTimer);

      if (!visible) {
        // Primera vez: posiciona sin transición, luego aparece con fade
        highlight.classList.remove('hl-slide');
        snap(card);
        // Forzar reflow para que la posición se aplique antes del fade
        highlight.getBoundingClientRect();
        highlight.style.opacity = '1';
        visible = true;
        // Habilitar deslizamiento para movimientos posteriores
        requestAnimationFrame(() => highlight.classList.add('hl-slide'));
      } else {
        // Ya visible → desliza al nuevo card
        snap(card);
      }
    });
  });

  grid.addEventListener('mouseleave', () => {
    leaveTimer = setTimeout(() => {
      highlight.style.opacity = '0';
      visible = false;
      // Al desaparecer desactiva el slide para la próxima entrada
      highlight.addEventListener('transitionend', () => {
        if (!visible) highlight.classList.remove('hl-slide');
      }, { once: true });
    }, 150);
  });
})();
