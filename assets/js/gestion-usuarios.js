(function () {

  // ── Estado ───────────────────────────────────────────────────────────────
  let filtroRol   = '';
  let busqueda    = '';
  let debounceT   = null;
  let eliminarId  = null;

  // ── Refs ─────────────────────────────────────────────────────────────────
  const guTablaWrap = document.getElementById('guTablaWrap');
  const guEstado    = document.getElementById('guEstado');

  // ── Cargar usuarios ───────────────────────────────────────────────────────
  async function cargarUsuarios() {
    mostrarEstado('<span class="material-symbols-outlined icono-spin" style="font-size:2rem;color:#264220">progress_activity</span><p>Cargando…</p>');

    const params = new URLSearchParams();
    if (busqueda)  params.set('q',   busqueda);
    if (filtroRol) params.set('rol', filtroRol);

    try {
      const res  = await fetch(BASE + '/api/listar_usuarios.php?' + params);
      const data = await res.json();

      if (!data.ok) throw new Error(data.mensaje || 'Error al cargar.');

      actualizarStats(data.totales);
      renderTabla(data.usuarios);
    } catch (e) {
      mostrarEstado(`<span class="material-symbols-outlined" style="font-size:2rem;color:#b91c1c">error</span><p>${e.message}</p>`);
    }
  }

  function mostrarEstado(html) {
    guTablaWrap.innerHTML = `<div class="gu-estado">${html}</div>`;
  }

  // ── Stats ─────────────────────────────────────────────────────────────────
  function actualizarStats(totales) {
    const total = (totales.usuario || 0) + (totales.admin || 0) + (totales.superadmin || 0);
    document.getElementById('statUsuarios').textContent    = total;
    document.getElementById('statAdmins').textContent      = totales.admin     || 0;
    document.getElementById('statSuperadmins').textContent = totales.superadmin || 0;
  }

  // ── Render tabla ──────────────────────────────────────────────────────────
  function renderTabla(usuarios) {
    if (usuarios.length === 0) {
      mostrarEstado('<span class="material-symbols-outlined" style="font-size:2.5rem;color:#73796f">person_search</span><p>No se encontraron usuarios.</p>');
      return;
    }

    const filas = usuarios.map(u => `
      <tr>
        <td>
          <div class="gu-usuario-celda">
            <div class="gu-avatar">${iniciales(u.nombre)}</div>
            <span class="gu-nombre">${esc(u.nombre)}</span>
          </div>
        </td>
        <td class="gu-email">${esc(u.email)}</td>
        <td><span class="badge-rol badge-${u.rol}">${labelRol(u.rol)}</span></td>
        <td class="gu-fecha">${formatFecha(u.fecha_registro)}</td>
        <td>
          <div class="gu-acciones">
            <button class="btn-tabla btn-editar" title="Editar"
              data-id="${u.id}" data-nombre="${esc(u.nombre)}"
              data-email="${esc(u.email)}" data-rol="${u.rol}">
              <span class="material-symbols-outlined">edit</span>
            </button>
            <button class="btn-tabla btn-eliminar" title="Eliminar"
              data-id="${u.id}" data-nombre="${esc(u.nombre)}">
              <span class="material-symbols-outlined">delete</span>
            </button>
          </div>
        </td>
      </tr>
    `).join('');

    guTablaWrap.innerHTML = `
      <table class="gu-tabla">
        <thead>
          <tr>
            <th>Usuario</th>
            <th>Email</th>
            <th>Rol</th>
            <th>Registro</th>
            <th class="col-acciones">Acciones</th>
          </tr>
        </thead>
        <tbody>${filas}</tbody>
      </table>
    `;

    // Eventos de editar/eliminar
    guTablaWrap.querySelectorAll('.btn-editar').forEach(btn => {
      btn.addEventListener('click', () => abrirModalEditar(btn.dataset));
    });
    guTablaWrap.querySelectorAll('.btn-eliminar').forEach(btn => {
      btn.addEventListener('click', () => abrirModalEliminar(btn.dataset.id, btn.dataset.nombre));
    });
  }

  // ── Helpers ───────────────────────────────────────────────────────────────
  function esc(str) {
    return String(str)
      .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
      .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
  }

  function iniciales(nombre) {
    return nombre.trim().split(/\s+/).slice(0,2).map(w => w[0].toUpperCase()).join('');
  }

  function labelRol(rol) {
    return { usuario: 'Usuario', admin: 'Admin', superadmin: 'Superadmin' }[rol] || rol;
  }

  function formatFecha(str) {
    const d = new Date(str);
    return d.toLocaleDateString('es-AR', { day: '2-digit', month: 'short', year: 'numeric' });
  }

  // ── Búsqueda y filtros ────────────────────────────────────────────────────
  document.getElementById('inputBusqueda').addEventListener('input', e => {
    clearTimeout(debounceT);
    busqueda = e.target.value.trim();
    debounceT = setTimeout(cargarUsuarios, 320);
  });

  document.querySelectorAll('.filtro-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.filtro-btn').forEach(b => b.classList.remove('filtro-activo'));
      btn.classList.add('filtro-activo');
      filtroRol = btn.dataset.rol;
      cargarUsuarios();
    });
  });

  // ══════════════════════════════════════════════════════════════════════════
  // MODAL: Crear / Editar usuario
  // ══════════════════════════════════════════════════════════════════════════
  const modalUsuario  = document.getElementById('modalUsuario');
  const formUsuario   = document.getElementById('formUsuario');
  const togglePwd     = document.getElementById('uTogglePassword');
  const inputPwd      = document.getElementById('uPassword');
  let modoEditar      = false;

  function abrirModalCrear() {
    modoEditar = false;
    document.getElementById('usuarioId').value  = '';
    document.getElementById('modalUsuarioIcono').innerHTML = '<span class="material-symbols-outlined">person_add</span>';
    document.getElementById('modalUsuarioTitulo').textContent   = 'Nuevo usuario';
    document.getElementById('modalUsuarioSubtitulo').textContent = 'Completá los datos para registrar un nuevo usuario.';
    document.getElementById('btnSubmitTexto').textContent = 'Crear usuario';
    document.getElementById('uPassOptional').style.display = 'none';
    document.getElementById('uPassword').required = true;
    formUsuario.reset();
    limpiarErroresUsuario();
    abrirModal(modalUsuario);
    document.getElementById('uNombre').focus();
  }

  function abrirModalEditar(data) {
    modoEditar = true;
    document.getElementById('usuarioId').value  = data.id;
    document.getElementById('uNombre').value    = data.nombre;
    document.getElementById('uEmail').value     = data.email;
    document.getElementById('uRol').value       = data.rol;
    document.getElementById('uPassword').value  = '';
    document.getElementById('modalUsuarioIcono').innerHTML = '<span class="material-symbols-outlined">edit</span>';
    document.getElementById('modalUsuarioTitulo').textContent    = 'Editar usuario';
    document.getElementById('modalUsuarioSubtitulo').textContent = `Editando la cuenta de ${data.nombre}.`;
    document.getElementById('btnSubmitTexto').textContent = 'Guardar cambios';
    document.getElementById('uPassOptional').style.display = 'inline';
    document.getElementById('uPassword').required = false;
    limpiarErroresUsuario();
    abrirModal(modalUsuario);
    document.getElementById('uNombre').focus();
  }

  function cerrarModalUsuario() {
    cerrarModal(modalUsuario);
    formUsuario.reset();
    limpiarErroresUsuario();
    restaurarBtnUsuario();
  }

  document.getElementById('btnNuevoUsuario').addEventListener('click', abrirModalCrear);
  document.getElementById('btnCerrarModalUsuario').addEventListener('click', cerrarModalUsuario);
  document.getElementById('btnCancelarModalUsuario').addEventListener('click', cerrarModalUsuario);
  modalUsuario.addEventListener('click', e => { if (e.target === modalUsuario) cerrarModalUsuario(); });

  togglePwd.addEventListener('click', () => {
    const oculto = inputPwd.type === 'password';
    inputPwd.type = oculto ? 'text' : 'password';
    togglePwd.querySelector('span').textContent = oculto ? 'visibility_off' : 'visibility';
  });

  function limpiarErroresUsuario() {
    ['uErrorNombre','uErrorEmail','uErrorRol','uErrorPassword'].forEach(id => {
      document.getElementById(id).textContent = '';
    });
    modalUsuario.querySelectorAll('.campo-wrapper-error').forEach(el => el.classList.remove('campo-wrapper-error'));
  }

  function marcarErrorUsuario(inputId, errorId, texto) {
    const el = document.getElementById(inputId);
    el.closest('.campo-input-wrapper').classList.add('campo-wrapper-error');
    document.getElementById(errorId).textContent = texto;
  }

  const btnSubmitUsuario = document.getElementById('btnSubmitUsuario');

  function setLoadingUsuario(on) {
    btnSubmitUsuario.disabled = on;
    btnSubmitUsuario.querySelector('.material-symbols-outlined').textContent =
      on ? 'progress_activity' : 'check';
    if (on) btnSubmitUsuario.querySelector('.material-symbols-outlined').classList.add('icono-spin');
    else    btnSubmitUsuario.querySelector('.material-symbols-outlined').classList.remove('icono-spin');
  }

  function restaurarBtnUsuario() { setLoadingUsuario(false); }

  // Validación cliente
  function validarFormUsuario() {
    let ok = true;
    const nombre = document.getElementById('uNombre').value.trim();
    const email  = document.getElementById('uEmail').value.trim();
    const pass   = inputPwd.value;

    if (!nombre) {
      marcarErrorUsuario('uNombre', 'uErrorNombre', 'El nombre es obligatorio.');
      ok = false;
    }
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      marcarErrorUsuario('uEmail', 'uErrorEmail', 'Ingresá un correo válido.');
      ok = false;
    }
    if (!modoEditar && !pass) {
      marcarErrorUsuario('uPassword', 'uErrorPassword', 'La contraseña es obligatoria.');
      ok = false;
    } else if (pass) {
      if (pass.length < 8) {
        marcarErrorUsuario('uPassword', 'uErrorPassword', 'Mínimo 8 caracteres.');
        ok = false;
      } else if (!/[A-Z]/.test(pass)) {
        marcarErrorUsuario('uPassword', 'uErrorPassword', 'Debe tener al menos una mayúscula.');
        ok = false;
      } else if (!/[0-9]/.test(pass)) {
        marcarErrorUsuario('uPassword', 'uErrorPassword', 'Debe tener al menos un número.');
        ok = false;
      }
    }
    return ok;
  }

  formUsuario.addEventListener('submit', async e => {
    e.preventDefault();
    limpiarErroresUsuario();
    if (!validarFormUsuario()) return;

    setLoadingUsuario(true);

    const payload = {
      nombre:   document.getElementById('uNombre').value.trim(),
      email:    document.getElementById('uEmail').value.trim(),
      rol:      document.getElementById('uRol').value,
      password: inputPwd.value,
    };

    const endpoint = modoEditar
      ? BASE + '/api/editar_usuario.php'
      : BASE + '/api/crear_usuario.php';

    if (modoEditar) payload.id = parseInt(document.getElementById('usuarioId').value, 10);

    try {
      const res  = await fetch(endpoint, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify(payload),
      });
      const data = await res.json();

      if (data.ok) {
        cerrarModalUsuario();
        mostrarToast(data.mensaje, 'exito');
        cargarUsuarios();
      } else if (data.errores) {
        const map = {
          nombre:   ['uNombre',   'uErrorNombre'],
          email:    ['uEmail',    'uErrorEmail'],
          rol:      ['uRol',      'uErrorRol'],
          password: ['uPassword', 'uErrorPassword'],
        };
        Object.entries(data.errores).forEach(([campo, msg]) => {
          if (map[campo]) marcarErrorUsuario(map[campo][0], map[campo][1], msg);
        });
        restaurarBtnUsuario();
      } else {
        mostrarToast(data.mensaje || 'Error inesperado.', 'error');
        restaurarBtnUsuario();
      }
    } catch {
      mostrarToast('No se pudo conectar con el servidor.', 'error');
      restaurarBtnUsuario();
    }
  });

  // ══════════════════════════════════════════════════════════════════════════
  // MODAL: Eliminar
  // ══════════════════════════════════════════════════════════════════════════
  const modalEliminar = document.getElementById('modalEliminar');

  function abrirModalEliminar(id, nombre) {
    eliminarId = parseInt(id, 10);
    document.getElementById('eliminarNombreTarget').textContent = nombre;
    abrirModal(modalEliminar);
  }

  function cerrarModalEliminar() {
    cerrarModal(modalEliminar);
    eliminarId = null;
  }

  document.getElementById('btnCerrarModalEliminar').addEventListener('click', cerrarModalEliminar);
  document.getElementById('btnCancelarEliminar').addEventListener('click', cerrarModalEliminar);
  modalEliminar.addEventListener('click', e => { if (e.target === modalEliminar) cerrarModalEliminar(); });

  document.getElementById('btnConfirmarEliminar').addEventListener('click', async () => {
    if (!eliminarId) return;
    const btn = document.getElementById('btnConfirmarEliminar');
    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-outlined icono-spin">progress_activity</span> Eliminando…';

    try {
      const res  = await fetch(BASE + '/api/eliminar_usuario.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ id: eliminarId }),
      });
      const data = await res.json();

      if (data.ok) {
        cerrarModalEliminar();
        mostrarToast(data.mensaje, 'exito');
        cargarUsuarios();
      } else {
        mostrarToast(data.mensaje || 'Error al eliminar.', 'error');
      }
    } catch {
      mostrarToast('No se pudo conectar con el servidor.', 'error');
    } finally {
      btn.disabled = false;
      btn.innerHTML = '<span class="material-symbols-outlined">delete</span> Eliminar';
    }
  });

  // ══════════════════════════════════════════════════════════════════════════
  // Utilidades modales
  // ══════════════════════════════════════════════════════════════════════════
  function abrirModal(modal) {
    modal.classList.add('modal-visible');
    document.body.classList.add('modal-abierto');
  }

  function cerrarModal(modal) {
    modal.classList.remove('modal-visible');
    document.body.classList.remove('modal-abierto');
  }

  document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    if (modalUsuario.classList.contains('modal-visible'))  cerrarModalUsuario();
    if (modalEliminar.classList.contains('modal-visible')) cerrarModalEliminar();
  });

  // ══════════════════════════════════════════════════════════════════════════
  // Toast
  // ══════════════════════════════════════════════════════════════════════════
  function mostrarToast(texto, tipo) {
    const t = document.createElement('div');
    t.className = 'toast toast-' + tipo;
    t.innerHTML = `<span class="material-symbols-outlined toast-icono">${tipo === 'exito' ? 'check_circle' : 'error'}</span><span>${texto}</span>`;
    document.body.appendChild(t);
    t.getBoundingClientRect();
    t.classList.add('toast-visible');
    setTimeout(() => {
      t.classList.remove('toast-visible');
      t.addEventListener('transitionend', () => t.remove(), { once: true });
    }, 3500);
  }

  // ── Arranque ──────────────────────────────────────────────────────────────
  cargarUsuarios();
})();
