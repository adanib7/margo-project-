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

  <!-- ── Mitad derecha: los dos formularios, uno encima del otro ── -->
  <main class="login-panel">
    <div class="login-caja" data-modo="<?= $h($modo) ?>">
      <div class="login-tabs" role="tablist">
        <span class="login-tabs-indicador" aria-hidden="true"></span>
        <a href="?modo=login"    data-modo="login"    role="tab">Iniciar sesión</a>
        <a href="?modo=registro" data-modo="registro" role="tab">Registrarse</a>
      </div>

      <div class="login-vistas">
        <!-- Iniciar sesión -->
        <form class="login-vista" data-vista="login" method="post" action="?modo=login">
          <h2 class="login-form-titulo">Bienvenido de nuevo</h2>
          <?php if ($mensaje !== '' && $modo === 'login'): ?>
            <div class="login-mensaje <?= $tipo === 'success' ? 'exito' : '' ?>">
              <span class="material-symbols-outlined"><?= $tipo === 'success' ? 'check_circle' : 'error' ?></span>
              <?= $h($mensaje) ?>
            </div>
          <?php endif; ?>
          <input type="hidden" name="accion" value="login">

          <label class="login-label" for="usuario">Usuario</label>
          <div class="login-campo">
            <span class="material-symbols-outlined">person</span>
            <input type="text" id="usuario" name="usuario" required autocomplete="username"
                   value="<?= $h($modo === 'login' ? ($_POST['usuario'] ?? '') : '') ?>" placeholder="Tu usuario">
          </div>

          <label class="login-label" for="pass">Contraseña</label>
          <div class="login-campo">
            <span class="material-symbols-outlined">lock</span>
            <input type="password" id="pass" name="contraseña" required autocomplete="current-password" placeholder="Tu contraseña">
            <button type="button" class="login-ojo" aria-label="Mostrar contraseña"><span class="material-symbols-outlined">visibility</span></button>
          </div>

          <button type="submit" class="login-boton">Entrar</button>
          <p class="login-alternativa">¿No tenés cuenta? <a href="?modo=registro" data-modo="registro">Registrate</a></p>

          <p class="login-separador"><span>o continuá con</span></p>
          <div class="login-google g_id_signin" data-type="standard" data-size="large" data-theme="outline"
               data-text="continue_with" data-shape="rectangular" data-logo_alignment="center" data-width="300"></div>
        </form>

        <!-- Registrarse -->
        <form class="login-vista" data-vista="registro" method="post" action="?modo=registro">
          <h2 class="login-form-titulo">Creá tu cuenta</h2>
          <?php if ($mensaje !== '' && $modo === 'registro'): ?>
            <div class="login-mensaje <?= $tipo === 'success' ? 'exito' : '' ?>">
              <span class="material-symbols-outlined"><?= $tipo === 'success' ? 'check_circle' : 'error' ?></span>
              <?= $h($mensaje) ?>
            </div>
          <?php endif; ?>
          <input type="hidden" name="accion" value="registro">

          <label class="login-label" for="r-usuario">Usuario</label>
          <div class="login-campo">
            <span class="material-symbols-outlined">person</span>
            <input type="text" id="r-usuario" name="usuario" required autocomplete="username"
                   value="<?= $h($modo === 'registro' ? ($_POST['usuario'] ?? '') : '') ?>" placeholder="Ej: juan99">
          </div>

          <label class="login-label" for="r-email">Email</label>
          <div class="login-campo">
            <span class="material-symbols-outlined">alternate_email</span>
            <input type="email" id="r-email" name="email" required autocomplete="email"
                   value="<?= $h($_POST['email'] ?? '') ?>" placeholder="ejemplo@correo.com">
          </div>

          <label class="login-label" for="r-pass">Contraseña</label>
          <div class="login-campo">
            <span class="material-symbols-outlined">lock</span>
            <input type="password" id="r-pass" name="contraseña" required autocomplete="new-password" placeholder="Mínimo 6 caracteres">
            <button type="button" class="login-ojo" aria-label="Mostrar contraseña"><span class="material-symbols-outlined">visibility</span></button>
          </div>
          <p class="login-ayuda">Solo letras y números. Al menos 6 caracteres, una mayúscula y un número.</p>

          <label class="login-label" for="r-confirmar">Confirmar contraseña</label>
          <div class="login-campo">
            <span class="material-symbols-outlined">lock</span>
            <input type="password" id="r-confirmar" name="confirmar" required autocomplete="new-password" placeholder="Repetí la contraseña">
            <button type="button" class="login-ojo" aria-label="Mostrar contraseña"><span class="material-symbols-outlined">visibility</span></button>
          </div>

          <button type="submit" class="login-boton">Crear cuenta</button>
          <p class="login-alternativa">¿Ya tenés cuenta? <a href="?modo=login" data-modo="login">Iniciá sesión</a></p>

          <p class="login-separador"><span>o continuá con</span></p>
          <div class="login-google g_id_signin" data-type="standard" data-size="large" data-theme="outline"
               data-text="continue_with" data-shape="rectangular" data-logo_alignment="center" data-width="300"></div>
        </form>
      </div>

      <div id="g_id_onload"
           data-client_id="<?= GOOGLE_CLIENT_ID ?>"
           data-login_uri="<?= buildUrl('/includes/google_auth.php', true) ?>"
           data-auto_prompt="false">
      </div>
    </div>
  </main>
</div>

<script src="<?= jsUrl('login.js') ?>"></script>
</body>
</html>
