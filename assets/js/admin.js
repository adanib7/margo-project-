(function () {
  const $ = id => document.getElementById(id);

  const modal  = $('modalRapida');
  const form   = $('formRapida');
  const btnSub = $('btnSubmitRapida');

  const CAMPOS = {
    nombre:   ['qNombre',   'qErrorNombre'],
    telefono: ['qTelefono', 'qErrorTelefono'],
    email:    ['qEmail',    'qErrorEmail'],
    fecha:    ['qFecha',    'qErrorFecha'],
    hora:     ['qHora',     'qErrorHora'],
    personas: ['qPersonas', 'qErrorPersonas'],
    mesa:     ['qMesa',     'qErrorMesa'],
  };

  function esc(s) {
    return String(s ?? '')
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function limpiarErrores() {
    Object.values(CAMPOS).forEach(p => $(p[1]).textContent = '');
    modal.querySelectorAll('.campo-wrapper-error').forEach(el => el.classList.remove('campo-wrapper-error'));
  }

  function marcarError(campo, texto) {
    const p = CAMPOS[campo];
    if (!p) return;
    const w = $(p[0]).closest('.campo-input-wrapper');
    if (w) w.classList.add('campo-wrapper-error');
    $(p[1]).textContent = texto;
  }

  function setLoading(on) {
    btnSub.disabled = on;
    const ic = btnSub.querySelector('.material-symbols-outlined');
    ic.textContent = on ? 'progress_activity' : 'check';
    ic.classList.toggle('icono-spin', on);
  }

  function abrir() {
    form.reset();
    limpiarErrores();
    setLoading(false);
    $('qFecha').value = new Date().toISOString().split('T')[0];
    $('qPersonas').value = 2;
    sincronizarAvisar();
    modal.classList.add('modal-visible');
    document.body.classList.add('modal-abierto');
    $('qNombre').focus();
  }

  function cerrar() {
    modal.classList.remove('modal-visible');
    document.body.classList.remove('modal-abierto');
    limpiarErrores();
    setLoading(false);
  }

  // El aviso por correo solo tiene sentido si hay un correo cargado.
  function sincronizarAvisar() {
    const hay = $('qEmail').value.trim() !== '';
    $('qAvisar').disabled = !hay;
    if (!hay) $('qAvisar').checked = false;
    $('qBloqueAvisar').style.opacity = hay ? '1' : '.5';
  }
  $('qEmail').addEventListener('input', sincronizarAvisar);

  $('btnNuevaReserva').addEventListener('click', abrir);
  $('btnCerrarRapida').addEventListener('click', cerrar);
  $('btnCancelarRapida').addEventListener('click', cerrar);
  modal.addEventListener('click', e => { if (e.target === modal) cerrar(); });
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && modal.classList.contains('modal-visible')) cerrar();
  });

  form.addEventListener('submit', async e => {
    e.preventDefault();
    limpiarErrores();

    const payload = {
      nombre:     $('qNombre').value.trim(),
      telefono:   $('qTelefono').value.trim(),
      email:      $('qEmail').value.trim(),
      fecha:      $('qFecha').value,
      hora:       $('qHora').value,
      personas:   parseInt($('qPersonas').value, 10) || 0,
      mesa_id:    parseInt($('qMesa').value, 10) || 0,
      comentario: $('qComentario').value.trim(),
      avisar:     $('qAvisar').checked,
    };

    if (!payload.nombre)   { marcarError('nombre', 'El nombre es obligatorio.'); return; }
    if (!payload.telefono) { marcarError('telefono', 'El teléfono es obligatorio.'); return; }
    if (!payload.fecha)    { marcarError('fecha', 'Elegí una fecha.'); return; }
    if (!payload.hora)     { marcarError('hora', 'Elegí un horario.'); return; }

    setLoading(true);
    try {
      const res  = await fetch(BASE + '/api/crear_reserva_admin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const data = await res.json();

      if (data.ok) {
        cerrar();
        mostrarToast(data.mensaje, 'exito');
      } else if (data.errores) {
        Object.keys(data.errores).forEach(c => marcarError(c, data.errores[c]));
        setLoading(false);
      } else {
        mostrarToast(data.mensaje || 'Error inesperado.', 'error');
        setLoading(false);
      }
    } catch {
      mostrarToast('No se pudo conectar con el servidor.', 'error');
      setLoading(false);
    }
  });

  function mostrarToast(texto, tipo) {
    const t = document.createElement('div');
    t.className = 'toast toast-' + tipo;
    t.innerHTML = '<span class="material-symbols-outlined toast-icono">'
      + (tipo === 'exito' ? 'check_circle' : 'error') + '</span><span>' + esc(texto) + '</span>';
    document.body.appendChild(t);
    t.getBoundingClientRect();
    t.classList.add('toast-visible');
    setTimeout(() => {
      t.classList.remove('toast-visible');
      t.addEventListener('transitionend', () => t.remove(), { once: true });
    }, 4500);
  }
})();
