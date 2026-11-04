<?php
$modo    = is_string($_GET['modo'] ?? null) ? $_GET['modo'] : 'login';
$mensaje = !empty($_SESSION['sesion_vencida']) || ($_GET['sesion'] ?? '') === 'expirada'
    ? 'Tu sesión terminó por inactividad o por alcanzar su duración máxima. Volvé a iniciar sesión.' : '';
$tipo    = "error";
$registroEmail = $_SESSION['registro_email'] ?? '';
$_SESSION['registro_csrf'] ??= bin2hex(random_bytes(32));

if (isset($_GET['logout'])) {
    cerrarSesion();
    header('Location: ' . buildUrl('/public/login.php', true));
    exit;
}

if (isset($_SESSION['usuario_logueado'])) {
    redirectToDashboard();
}

if ($conn === null) {
    $mensaje = $dbErrorMessage ?? 'No se pudo conectar con la base de datos. Verifica que MySQL esté activo.';
} else {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'registro') {
        $usuario = is_string($_POST['usuario'] ?? null) ? trim($_POST['usuario']) : '';
        $email   = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
        $pass1   = is_string($_POST['contraseña'] ?? null) ? $_POST['contraseña'] : '';
        $pass2   = is_string($_POST['confirmar'] ?? null) ? $_POST['confirmar'] : '';
        $modo    = 'registro';

        if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['registro_csrf'], $_POST['csrf'])) {
            $mensaje = 'La sesión del formulario venció. Recargá la página e intentá de nuevo.';
        } elseif (strlen($usuario) > 100 || strlen($email) > 255 || strlen($pass1) > 72) {
            $mensaje = 'El usuario, correo o contraseña es demasiado largo.';
        } elseif ($usuario === '' || $email === '' || $pass1 === '') {
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
                require_once __DIR__ . '/registro.php';
                $reportAnterior = (new mysqli_driver())->report_mode;
                mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
                try {
                    registroPreparar($conn);
                    [$ok, $mensaje] = registroSolicitar($conn, $email, ['nombre' => $usuario, 'password' => $pass1]);
                    // Incluso si falla el correo se puede reenviar desde esta pantalla.
                    $_SESSION['registro_email'] = $registroEmail = $email;
                    $modo = 'verificar';
                    $tipo = $ok ? 'success' : 'error';
                } catch (Throwable $e) {
                    error_log('Registro: no se pudo preparar o guardar la verificación.');
                    $mensaje = 'No pudimos iniciar la verificación. Intentá de nuevo más tarde.';
                } finally {
                    mysqli_report($reportAnterior);
                }
            }
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'login') {
        $usuario = is_string($_POST['usuario'] ?? null) ? trim($_POST['usuario']) : '';
        $pass1   = is_string($_POST['contraseña'] ?? null) ? $_POST['contraseña'] : '';
        $modo    = 'login';

        if ($usuario === '' || $pass1 === '') {
            $mensaje = "Completá todos los campos.";
        } else {
            // En el acceso se verifica el hash existente; las reglas se aplican
            // al crear o cambiar la contraseña, no al volver a iniciar sesión.
            $stmt = $conn->prepare("SELECT id, nombre, password, rol FROM usuarios WHERE nombre = ? OR email = ? LIMIT 2");
            $stmt->bind_param("ss", $usuario, $usuario);
            $stmt->execute();
            $resultado = $stmt->get_result();
            // Si un nombre coincide con el correo de otra cuenta, no elegir una al azar.
            $row = $resultado->num_rows === 1 ? $resultado->fetch_assoc() : null;
            $stmt->close();

            if ($row && is_string($row['password']) && password_verify($pass1, $row['password'])) {
                autenticarSesion($row);
                redirectToDashboard();
            } else {
                $mensaje = "Usuario, correo o contraseña incorrectos.";
            }
        }
    }
}

// La cuenta todavía no existe en usuarios: solo el código permite completarla.
if ($conn !== null && $_SERVER['REQUEST_METHOD'] === 'POST'
    && in_array($_POST['accion'] ?? '', ['verificar_registro', 'reenviar_registro'], true)) {
    $modo = 'verificar';
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['registro_csrf'], $_POST['csrf'])) {
        $mensaje = 'La sesión del formulario venció. Recargá la página e intentá de nuevo.';
    } elseif (!filter_var($registroEmail, FILTER_VALIDATE_EMAIL)) {
        $mensaje = 'Primero completá el formulario de registro.';
        $modo = 'registro';
    } else {
        require_once __DIR__ . '/registro.php';
        $reportAnterior = (new mysqli_driver())->report_mode;
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        try {
            registroPreparar($conn);
            if ($_POST['accion'] === 'reenviar_registro') {
                [$ok, $mensaje] = registroSolicitar($conn, $registroEmail);
            } else {
                [$ok, $mensaje, $nombreVerificado] = array_pad(
                    registroConfirmar($conn, $registroEmail, is_string($_POST['codigo'] ?? null) ? trim($_POST['codigo']) : ''), 3, null
                );
                if ($ok) {
                    unset($_SESSION['registro_email'], $_SESSION['registro_solicitud']);
                    $_POST['usuario'] = $nombreVerificado;
                    $_SESSION['registro_csrf'] = bin2hex(random_bytes(32));
                    $modo = 'login';
                }
            }
            $tipo = $ok ? 'success' : 'error';
        } catch (Throwable $e) {
            error_log('Registro: no se pudo procesar la verificación.');
            $mensaje = 'No pudimos procesar la verificación. Intentá de nuevo más tarde.';
        } finally {
            mysqli_report($reportAnterior);
        }
    }
}
