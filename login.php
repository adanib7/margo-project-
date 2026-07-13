<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/check_auth.php';
require_once 'includes/auth.php';
$pageCSS = 'assets/css/login.css';
require_once 'includes/header.php';

// Mostrar errores que vengan de google_auth.php
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'google') {
        $mensaje = 'No se pudo iniciar sesión con Google.';
        $tipo = 'error';
    }
    if ($_GET['error'] === 'token') {
        $mensaje = 'Token de Google inválido.';
        $tipo = 'error';
    }
}
?>
<script src="https://accounts.google.com/gsi/client" async defer></script>

<form class="form" method="post" action="?modo=<?= $modo ?>">
  <img src="assets/img/logo-horizontal-verde.png" alt="El Corralín de Campanal" class="login-logo">

  <div class="tabs">
    <a href="?modo=login"    class="<?= $modo === 'login'    ? 'activo' : '' ?>" id="tab-login">Iniciar sesión</a>
    <a href="?modo=registro" class="<?= $modo === 'registro' ? 'activo' : '' ?>" id="tab-registro">Registrarse</a>
  </div>

  <?php if ($modo === 'login'): ?>
    <input type="hidden" name="accion" value="login" />

    <div class="flex-column">
      <label>Usuario </label>
    </div>
    <div class="inputForm">
      <svg height="20" viewBox="0 0 32 32" width="20" xmlns="http://www.w3.org/2000/svg">
        <path d="M16 16a7 7 0 1 0-7-7 7 7 0 0 0 7 7zm0-12a5 5 0 1 1-5 5 5 5 0 0 1 5-5zM28 30v-2a8 8 0 0 0-8-8h-8a8 8 0 0 0-8 8v2h2v-2a6 6 0 0 1 6-6h8a6 6 0 0 1 6 6v2z"/>
      </svg>
      <input type="text" class="input" name="usuario" required
             value="<?= htmlspecialchars($_POST['usuario'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
             placeholder="Tu usuario" />
    </div>

    <div class="flex-column">
      <label>Contraseña </label>
    </div>
    <div class="inputForm">
      <svg height="20" viewBox="-64 0 512 512" width="20" xmlns="http://www.w3.org/2000/svg">
        <path d="m336 512h-288c-26.453125 0-48-21.523438-48-48v-224c0-26.476562 21.546875-48 48-48h288c26.453125 0 48 21.523438 48 48v224c0 26.476562-21.546875 48-48 48zm-288-288c-8.8125 0-16 7.167969-16 16v224c0 8.832031 7.1875 16 16 16h288c8.8125 0 16-7.167969 16-16v-224c0-8.832031-7.1875-16-16-16zm0 0"/>
        <path d="m304 224c-8.832031 0-16-7.167969-16-16v-80c0-52.929688-43.070312-96-96-96s-96 43.070312-96 96v80c0 8.832031-7.167969 16-16 16s-16-7.167969-16-16v-80c0-70.59375 57.40625-128 128-128s128 57.40625 128 128v80c0 8.832031-7.167969 16-16 16zm0 0"/>
      </svg>
      <input type="password" class="input" name="contraseña" required
             placeholder="Tu contraseña" />
    </div>

    <div class="flex-row">
      <div>
        <input type="checkbox" id="remember" />
        <label for="remember">Recordarme </label>
      </div>
      <span class="span">Recuperar contrasena</span>
    </div>

    <button type="submit" class="button-submit">Entrar</button>

    <p class="p">¿No tenés cuenta? <a href="?modo=registro" class="span">Registrate</a></p>

  <?php else: ?>
    <input type="hidden" name="accion" value="registro" />

    <div class="flex-column">
      <label>Usuario </label>
    </div>
    <div class="inputForm">
      <svg height="20" viewBox="0 0 32 32" width="20" xmlns="http://www.w3.org/2000/svg">
        <path d="M16 16a7 7 0 1 0-7-7 7 7 0 0 0 7 7zm0-12a5 5 0 1 1-5 5 5 5 0 0 1 5-5zM28 30v-2a8 8 0 0 0-8-8h-8a8 8 0 0 0-8 8v2h2v-2a6 6 0 0 1 6-6h8a6 6 0 0 1 6 6v2z"/>
      </svg>
      <input type="text" class="input" name="usuario" required
             value="<?= htmlspecialchars($_POST['usuario'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
             placeholder="Ej: juan99" />
    </div>

    <div class="flex-column">
      <label>Email </label>
    </div>
    <div class="inputForm">
      <svg height="20" viewBox="0 0 32 32" width="20" xmlns="http://www.w3.org/2000/svg">
        <g id="Layer_3" data-name="Layer 3">
          <path d="m30.853 13.87a15 15 0 0 0 -29.729 4.082 15.1 15.1 0 0 0 12.876 12.918 15.6 15.6 0 0 0 2.016.13 14.85 14.85 0 0 0 7.715-2.145 1 1 0 1 0 -1.031-1.711 13.007 13.007 0 1 1 5.458-6.529 2.149 2.149 0 0 1 -4.158-.759v-10.856a1 1 0 0 0 -2 0v1.726a8 8 0 1 0 .2 10.325 4.135 4.135 0 0 0 7.83.274 15.2 15.2 0 0 0 .823-7.455zm-14.853 8.13a6 6 0 1 1 6-6 6.006 6.006 0 0 1 -6 6z"/>
        </g>
      </svg>
      <input type="email" class="input" name="email" required
             value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
             placeholder="ejemplo@correo.com" />
    </div>

    <div class="flex-column">
      <label>Contraseña </label>
    </div>
    <div class="inputForm">
      <svg height="20" viewBox="-64 0 512 512" width="20" xmlns="http://www.w3.org/2000/svg">
        <path d="m336 512h-288c-26.453125 0-48-21.523438-48-48v-224c0-26.476562 21.546875-48 48-48h288c26.453125 0 48 21.523438 48 48v224c0 26.476562-21.546875 48-48 48zm-288-288c-8.8125 0-16 7.167969-16 16v224c0 8.832031 7.1875 16 16 16h288c8.8125 0 16-7.167969 16-16v-224c0-8.832031-7.1875-16-16-16zm0 0"/>
        <path d="m304 224c-8.832031 0-16-7.167969-16-16v-80c0-52.929688-43.070312-96-96-96s-96 43.070312-96 96v80c0 8.832031-7.167969 16-16 16s-16-7.167969-16-16v-80c0-70.59375 57.40625-128 128-128s128 57.40625 128 128v80c0 8.832031-7.167969 16-16 16zm0 0"/>
      </svg>
      <input type="password" class="input" name="contraseña" required
             placeholder="Mín. 6 chars, 1 mayúscula, 1 número" />
    </div>
    <p class="hint">Solo letras y números. Al menos 6 caracteres, una mayúscula y un número.</p>

    <div class="flex-column">
      <label>Confirmar contraseña </label>
    </div>
    <div class="inputForm">
      <svg height="20" viewBox="-64 0 512 512" width="20" xmlns="http://www.w3.org/2000/svg">
        <path d="m336 512h-288c-26.453125 0-48-21.523438-48-48v-224c0-26.476562 21.546875-48 48-48h288c26.453125 0 48 21.523438 48 48v224c0 26.476562-21.546875 48-48 48zm-288-288c-8.8125 0-16 7.167969-16 16v224c0 8.832031 7.1875 16 16 16h288c8.8125 0 16-7.167969 16-16v-224c0-8.832031-7.1875-16-16-16zm0 0"/>
        <path d="m304 224c-8.832031 0-16-7.167969-16-16v-80c0-52.929688-43.070312-96-96-96s-96 43.070312-96 96v80c0 8.832031-7.167969 16-16 16s-16-7.167969-16-16v-80c0-70.59375 57.40625-128 128-128s128 57.40625 128 128v80c0 8.832031-7.167969 16-16 16zm0 0"/>
      </svg>
      <input type="password" class="input" name="confirmar" required
             placeholder="Repetí la contraseña" />
    </div>

    <button type="submit" class="button-submit">Registrarse</button>

    <p class="p">¿Ya tenés cuenta? <a href="?modo=login" class="span">Iniciá sesión</a></p>

  <?php endif; ?>

  <p class="p line">O con</p>

  <div id="g_id_onload"
       data-client_id="<?= GOOGLE_CLIENT_ID ?>"
       data-login_uri="<?= buildUrl('/includes/google_auth.php', true) ?>"
       data-auto_prompt="false">
  </div>
  <div class="g_id_signin"
       data-type="standard"
       data-size="large"
       data-theme="outline"
       data-text="continue_with"
       data-shape="rectangular"
       data-logo_alignment="center"
       data-width="300">
  </div>

  <?php if ($mensaje !== ''): ?>
    <div class="mensaje <?= $tipo === 'success' ? 'success' : '' ?>">
      <?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php endif; ?>
</form>