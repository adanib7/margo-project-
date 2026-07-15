<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/check_auth.php';
requireLogin();

function validarPassword(string $p): string {
    if (strlen($p) < 6)                       return "La contraseña debe tener mínimo 6 caracteres.";
    if (!preg_match('/[A-Z]/', $p))           return "La contraseña debe tener al menos una mayúscula.";
    if (!preg_match('/[0-9]/', $p))           return "La contraseña debe tener al menos un número.";
    if (!preg_match('/^[a-zA-Z0-9]+$/', $p)) return "Solo se permiten letras y números.";
    return "";
}

$usuarioId = (int)($_SESSION['usuario_id'] ?? 0);
if ($usuarioId <= 0) {
    redirectToDashboard();
}

$mensaje = '';
$tipo = 'success';
$errores = [];

$stmt = $conn->prepare("SELECT nombre, email, rol FROM usuarios WHERE id = ?");
$stmt->bind_param('i', $usuarioId);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$usuario) {
    redirectToDashboard();
}

$nombre = $usuario['nombre'];
$email  = $usuario['email'];
$rol    = $usuario['rol'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre   = trim($_POST['nombre'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($nombre === '') {
        $errores['nombre'] = 'El nombre es obligatorio.';
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errores['email'] = 'Ingresá un correo válido.';
    }

    if ($password !== '') {
        $passwordError = validarPassword($password);
        if ($passwordError !== '') {
            $errores['password'] = $passwordError;
        }
    }

    if (empty($errores)) {
        $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
        $stmt->bind_param('si', $email, $usuarioId);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $errores['email'] = 'Ese correo ya está registrado.';
        }

        $stmt->close();
    }

    if (empty($errores)) {
        if ($password !== '') {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("UPDATE usuarios SET nombre = ?, email = ?, password = ? WHERE id = ?");
            $stmt->bind_param('sssi', $nombre, $email, $hash, $usuarioId);
        } else {
            $stmt = $conn->prepare("UPDATE usuarios SET nombre = ?, email = ? WHERE id = ?");
            $stmt->bind_param('ssi', $nombre, $email, $usuarioId);
        }

        if ($stmt->execute()) {
            $_SESSION['usuario_logueado'] = $nombre;
            $mensaje = 'Tus datos se actualizaron correctamente.';
            $tipo = 'success';
        } else {
            $mensaje = 'No se pudo actualizar tu perfil. Intentá de nuevo más tarde.';
            $tipo = 'error';
        }

        $stmt->close();
    }
}

$pageTitle = 'Mi Perfil';
$pageCSS   = '../assets/css/dashboard.css';
$showDashboardBottomNav = false;
require_once '../includes/header.php';
require_once '../includes/nav.php';
?>

<main class="contenido-principal">
  <header class="seccion-encabezado">
    <div>
      <h1 class="titulo-pagina">Mi perfil</h1>
      <p class="subtitulo-pagina">Revisá y actualizá tus datos de usuario.</p>
    </div>
  </header>

  <?php if ($mensaje !== ''): ?>
    <div class="mensaje <?= $tipo === 'success' ? 'mensaje-exito' : 'mensaje-error' ?>">
      <?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php endif; ?>

  <section class="seccion-perfil">
    <form method="post" class="formulario-perfil">
      <div class="campo-grupo">
        <label class="campo-etiqueta" for="nombre">Nombre completo</label>
        <div class="campo-input-wrapper">
          <span class="campo-icono material-symbols-outlined">person</span>
          <input class="campo-input" type="text" id="nombre" name="nombre" value="<?= htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') ?>" required>
        </div>
        <span class="campo-error"><?= htmlspecialchars($errores['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
      </div>

      <div class="campo-grupo">
        <label class="campo-etiqueta" for="email">Email</label>
        <div class="campo-input-wrapper">
          <span class="campo-icono material-symbols-outlined">mail</span>
          <input class="campo-input" type="email" id="email" name="email" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" required>
        </div>
        <span class="campo-error"><?= htmlspecialchars($errores['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
      </div>

      

      <div class="campo-grupo">
        <label class="campo-etiqueta" for="password">Contraseña nueva <span class="campo-opcional">(opcional)</span></label>
        <div class="campo-input-wrapper">
          <span class="campo-icono material-symbols-outlined">lock</span>
          <input class="campo-input" type="password" id="password" name="password" placeholder="Dejar en blanco para no cambiarla">
        </div>
        <span class="campo-error"><?= htmlspecialchars($errores['password'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
      </div>

      <div class="modal-acciones formulario-acciones">
        <button type="submit" class="boton-accion">Guardar cambios</button>
      </div>
    </form>
  </section>
</main>

<?php require_once '../includes/footer.php';
