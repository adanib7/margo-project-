<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/check_auth.php';
require_once 'includes/auth.php';
require_once 'includes/header.php';
?>
<link rel="stylesheet" href="assets/css/login.css" />

<div class="card">
  <div class="tabs">
    <a href="?modo=login"    class="<?= $modo === 'login'    ? 'activo' : '' ?>">Iniciar sesión</a>
    <a href="?modo=registro" class="<?= $modo === 'registro' ? 'activo' : '' ?>">Registrarse</a>
  </div>

  <?php if ($modo === 'login'): ?>
    <h2>Iniciar sesión</h2>
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
    <h2>Crear cuenta</h2>
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

  <?php if ($mensaje !== ''): ?>
    <div class="mensaje <?= $tipo === 'success' ? 'success' : '' ?>">
      <?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
