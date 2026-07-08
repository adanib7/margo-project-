<?php
function validarPassword(string $p): string {
    if (strlen($p) < 6)                       return "La contraseña debe tener mínimo 6 caracteres.";
    if (!preg_match('/[A-Z]/', $p))           return "La contraseña debe tener al menos una mayúscula.";
    if (!preg_match('/[0-9]/', $p))           return "La contraseña debe tener al menos un número.";
    if (!preg_match('/^[a-zA-Z0-9]+$/', $p)) return "Solo se permiten letras y números.";
    return "";
}

$modo    = $_GET['modo'] ?? 'login';
$mensaje = "";
$tipo    = "error";

if (isset($_GET['logout'])) {
    unset($_SESSION['usuario_logueado'], $_SESSION['rol']);
    header('Location: ' . BASE_URL . '/index.php?logout=1');
    exit;
}

if (isset($_SESSION['usuario_logueado'])) {
    redirectToDashboard();
}

if ($conn === null) {
    $mensaje = $dbErrorMessage ?? 'No se pudo conectar con la base de datos. Verifica que MySQL esté activo.';
} else {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'registro') {
        $usuario = trim($_POST['usuario']  ?? '');
        $email   = trim($_POST['email']    ?? '');
        $pass1   = $_POST['contraseña']    ?? '';
        $pass2   = $_POST['confirmar']     ?? '';
        $modo    = 'registro';

        if ($usuario === '' || $email === '' || $pass1 === '') {
            $mensaje = "Todos los campos son obligatorios.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $mensaje = "El email no es válido.";
        } elseif ($pass1 !== $pass2) {
            $mensaje = "Las contraseñas no coinciden.";
        } elseif (($err = validarPassword($pass1)) !== '') {
            $mensaje = $err;
        } else {
            $stmt = $conn->prepare("SELECT id FROM usuarios WHERE nombre = ? OR email = ?");
            $stmt->bind_param("ss", $usuario, $email);
            $stmt->execute();
            $existe = $stmt->get_result()->num_rows > 0;
            $stmt->close();

            if ($existe) {
                $mensaje = "Ese usuario o email ya está registrado.";
            } else {
                $hash = password_hash($pass1, PASSWORD_BCRYPT);
                $stmt = $conn->prepare("INSERT INTO usuarios (nombre, email, password, rol) VALUES (?, ?, ?, 'usuario')");
                $stmt->bind_param("sss", $usuario, $email, $hash);
                $stmt->execute();
                $stmt->close();
                $mensaje = "¡Registro exitoso! Ya podés iniciar sesión.";
                $tipo    = "success";
                $modo    = 'login';
            }
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'login') {
        $usuario = trim($_POST['usuario'] ?? '');
        $pass1   = $_POST['contraseña']   ?? '';
        $modo    = 'login';

        if ($usuario === '' || $pass1 === '') {
            $mensaje = "Completá todos los campos.";
        } elseif (($err = validarPassword($pass1)) !== '') {
            $mensaje = $err;
        } else {
            $stmt = $conn->prepare("SELECT id, password, rol FROM usuarios WHERE nombre = ?");
            $stmt->bind_param("s", $usuario);
            $stmt->execute();
            $row  = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($row && password_verify($pass1, $row['password'])) {
                $_SESSION['usuario_logueado'] = $usuario;
                $_SESSION['rol']              = $row['rol'] ?? 'usuario';
                $_SESSION['usuario_id']       = $row['id'];
                redirectToDashboard();
            } else {
                $mensaje = "Usuario o contraseña incorrectos.";
            }
        }
    }
}
