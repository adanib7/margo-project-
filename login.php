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

<div class="card">
  <div class="tabs">
    <a href="?modo=login"    class="<?= $modo === 'login'    ? 'activo' : '' ?>" id="tab-login">Iniciar sesión</a>
    <a href="?modo=registro" class="<?= $modo === 'registro' ? 'activo' : '' ?>" id="tab-registro">Registrarse</a>
  </div>

  <?php if ($modo === 'login'): ?>
    <form method="post" action="?modo=login">
      <input type="hidden" name="accion" value="login" />

      <label for="usuario">Usuario</label>
      <input type="text" id="usuario" name="usuario" required
             value="<?= htmlspecialchars($_POST['usuario'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
             placeholder="Tu usuario" />

      <label for="contraseña">Contraseña</label>
      <input type="password" id="contraseña" name="contraseña" required
             placeholder="Tu contraseña" />

      <button type="submit">Entrar</button>
    </form>

  <?php else: ?>
    <form method="post" action="?modo=registro">
      <input type="hidden" name="accion" value="registro" />

      <label for="reg-usuario">Usuario</label>
      <input type="text" id="reg-usuario" name="usuario" required
             value="<?= htmlspecialchars($_POST['usuario'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
             placeholder="Ej: juan99" />

      <label for="reg-email">Email</label>
      <input type="email" id="reg-email" name="email" required
             value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
             placeholder="ejemplo@correo.com" />

      <label for="reg-pass">Contraseña</label>
      <input type="password" id="reg-pass" name="contraseña" required
             placeholder="Mínimo 6 chars, 1 mayúscula, 1 número" />
      <p class="hint">Solo letras y números. Al menos 6 caracteres, una mayúscula y un número.</p>

      <label for="reg-pass2">Confirmar contraseña</label>
      <input type="password" id="reg-pass2" name="confirmar" required
             placeholder="Repetí la contraseña" />

      <button type="submit">Registrarse</button>
    </form>
  <?php endif; ?>

  <!-- Separador y botón de Google -->
  <div class="separador">
    <span>o</span> 
  </div>

  <div id="g_id_onload"
       data-client_id="<?= GOOGLE_CLIENT_ID ?>"
       data-login_uri="<?= (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\') ?>/includes/google_auth.php"
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
</div>
