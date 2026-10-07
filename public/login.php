<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/check_auth.php';
require_once '../includes/auth.php';
$pageCSS   = '../assets/css/login.css';
$pageTitle = ($modo === 'registro' ? 'Crear cuenta' : 'Iniciar sesión') . ' · ' . cfg('local.nombre');
require_once '../includes/header.php';

// Mostrar errores que vengan de google_auth.php
$erroresGoogle = [
    'google'   => 'No se pudo iniciar sesión con Google.',
    'token'    => 'Token de Google inválido.',
    'database' => 'No se pudo conectar con la base de datos.',
];
if (isset($erroresGoogle[$_GET['error'] ?? ''])) {
    $mensaje = $erroresGoogle[$_GET['error']];
    $tipo    = 'error';
}

$h = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<script src="https://accounts.google.com/gsi/client" async defer></script>

<div class="login">
  <!-- ── Mitad izquierda: foto y marca (igual que la portada) ── -->
  <aside class="login-marca">
    <img class="login-foto" src="../assets/img/hero.jpg" alt="">
    <div class="login-marca-contenido">
      <a href="../index.php" class="login-volver">
        <span class="material-symbols-outlined">arrow_back</span> Volver al inicio
      </a>
      <img src="../assets/img/logo-horizontal-verde.png" alt="<?= $h(cfg('local.nombre')) ?>" class="login-logo">
      <span class="login-eyebrow"><?= $h(implode(' · ', array_filter([cfg('local.ciudad'), cfg('local.provincia')]))) ?></span>
      <h1 class="login-titulo">Tu mesa te espera</h1>
      <ul class="login-ventajas">
        <li><span class="material-symbols-outlined">event_available</span> Reservá en un minuto, eligiendo tu mesa en el plano</li>
        <li><span class="material-symbols-outlined">mail</span> Recibí la confirmación por correo</li>
        <li><span class="material-symbols-outlined">receipt_long</span> Descargá tu comprobante cuando quieras</li>
      </ul>
    </div>
  </aside>

  <!-- ── Mitad derecha: formulario ── -->
  <main class="login-panel">
    <form class="login-form" method="post" action="?modo=<?= $h($modo) ?>">
      <div class="login-tabs">
        <a href="?modo=login"    class="<?= $modo === 'login'    ? 'activo' : '' ?>">Iniciar sesión</a>
        <a href="?modo=registro" class="<?= $modo === 'registro' ? 'activo' : '' ?>">Registrarse</a>
      </div>

      <h2 class="login-form-titulo"><?= $modo === 'registro' ? 'Creá tu cuenta' : 'Bienvenido de nuevo' ?></h2>

      <?php if ($mensaje !== ''): ?>
        <div class="login-mensaje <?= $tipo === 'success' ? 'exito' : '' ?>">
          <span class="material-symbols-outlined"><?= $tipo === 'success' ? 'check_circle' : 'error' ?></span>
          <?= $h($mensaje) ?>
        </div>
      <?php endif; ?>

      <?php if ($modo === 'login'): ?>
        <input type="hidden" name="accion" value="login">

        <label class="login-label" for="usuario">Usuario</label>
        <div class="login-campo">
          <span class="material-symbols-outlined">person</span>
          <input type="text" id="usuario" name="usuario" required autocomplete="username"
                 value="<?= $h($_POST['usuario'] ?? '') ?>" placeholder="Tu usuario">
        </div>

        <label class="login-label" for="pass">Contraseña</label>
        <div class="login-campo">
          <span class="material-symbols-outlined">lock</span>
          <input type="password" id="pass" name="contraseña" required autocomplete="current-password" placeholder="Tu contraseña">
          <button type="button" class="login-ojo" aria-label="Mostrar contraseña"><span class="material-symbols-outlined">visibility</span></button>
        </div>

        <button type="submit" class="login-boton">Entrar</button>
        <p class="login-alternativa">¿No tenés cuenta? <a href="?modo=registro">Registrate</a></p>
      <?php else: ?>
        <input type="hidden" name="accion" value="registro">

        <label class="login-label" for="usuario">Usuario</label>
        <div class="login-campo">
          <span class="material-symbols-outlined">person</span>
          <input type="text" id="usuario" name="usuario" required autocomplete="username"
                 value="<?= $h($_POST['usuario'] ?? '') ?>" placeholder="Ej: juan99">
        </div>

        <label class="login-label" for="email">Email</label>
        <div class="login-campo">
          <span class="material-symbols-outlined">alternate_email</span>
          <input type="email" id="email" name="email" required autocomplete="email"
                 value="<?= $h($_POST['email'] ?? '') ?>" placeholder="ejemplo@correo.com">
        </div>

        <label class="login-label" for="pass">Contraseña</label>
        <div class="login-campo">
          <span class="material-symbols-outlined">lock</span>
          <input type="password" id="pass" name="contraseña" required autocomplete="new-password" placeholder="Mínimo 6 caracteres">
          <button type="button" class="login-ojo" aria-label="Mostrar contraseña"><span class="material-symbols-outlined">visibility</span></button>
        </div>
        <p class="login-ayuda">Solo letras y números. Al menos 6 caracteres, una mayúscula y un número.</p>

        <label class="login-label" for="confirmar">Confirmar contraseña</label>
        <div class="login-campo">
          <span class="material-symbols-outlined">lock</span>
          <input type="password" id="confirmar" name="confirmar" required autocomplete="new-password" placeholder="Repetí la contraseña">
          <button type="button" class="login-ojo" aria-label="Mostrar contraseña"><span class="material-symbols-outlined">visibility</span></button>
        </div>

        <button type="submit" class="login-boton">Crear cuenta</button>
        <p class="login-alternativa">¿Ya tenés cuenta? <a href="?modo=login">Iniciá sesión</a></p>
      <?php endif; ?>

      <p class="login-separador"><span>o continuá con</span></p>

      <div id="g_id_onload"
           data-client_id="<?= GOOGLE_CLIENT_ID ?>"
           data-login_uri="<?= buildUrl('/includes/google_auth.php', true) ?>"
           data-auto_prompt="false">
      </div>
      <div class="login-google g_id_signin"
           data-type="standard"
           data-size="large"
           data-theme="outline"
           data-text="continue_with"
           data-shape="rectangular"
           data-logo_alignment="center"
           data-width="300">
      </div>
    </form>
  </main>
</div>

<script src="<?= jsUrl('login.js') ?>"></script>
</body>
</html>
