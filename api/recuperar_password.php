<?php
/**
 * Recuperar la contraseña con un código por correo. Lo usa la vista
 * "Recuperar contraseña" de public/login.php (assets/js/login.js), en dos pasos:
 *
 *   accion = pedir    { email }                                -> manda un código de 6 dígitos
 *   accion = cambiar  { email, codigo, password, confirmar }   -> valida el código y cambia la contraseña
 *
 * El código se guarda encriptado (como las contraseñas), vence a los 15 minutos,
 * sirve una sola vez y se anula después de 5 intentos fallidos.
 */
session_start();
require_once '../includes/config.php';
require_once '../includes/check_auth.php';
require_once '../includes/mailer.php';

header('Content-Type: application/json; charset=utf-8');

const CODIGO_MINUTOS  = 15; // cuánto dura el código
const CODIGO_INTENTOS = 5;  // intentos fallidos antes de anularlo
const CODIGO_ESPERA   = 60; // segundos mínimos entre un pedido y otro

function responder(int $status, array $datos): void
{
    http_response_code($status);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(405, ['ok' => false, 'mensaje' => 'Método no permitido.']);
}
if ($conn === null) {
    responder(500, ['ok' => false, 'mensaje' => 'No se pudo conectar con la base de datos.']);
}

// Tabla de códigos pendientes: uno por usuario (pedir otro reemplaza al anterior).
$conn->query("
    CREATE TABLE IF NOT EXISTS password_resets (
        usuario_id  INT NOT NULL,
        codigo_hash VARCHAR(255) NOT NULL,
        expira      DATETIME NOT NULL,
        intentos    INT NOT NULL DEFAULT 0,
        creado_en   DATETIME NOT NULL,
        PRIMARY KEY (usuario_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

$body   = json_decode(file_get_contents('php://input'), true) ?: [];
$accion = $body['accion'] ?? '';
$email  = trim((string) ($body['email'] ?? ''));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    responder(422, ['ok' => false, 'errores' => ['email' => 'Ingresá un email válido.']]);
}

$stmt = $conn->prepare("SELECT id, nombre FROM usuarios WHERE email = ? LIMIT 1");
$stmt->bind_param('s', $email);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stmt = $conn->prepare("SELECT * FROM password_resets WHERE usuario_id = ?");
$idUsuario = (int) ($usuario['id'] ?? 0);
$stmt->bind_param('i', $idUsuario);
$stmt->execute();
$pendiente = $stmt->get_result()->fetch_assoc();
$stmt->close();

/* ── Paso 1: pedir el código ── */
if ($accion === 'pedir') {
    // Siempre la misma respuesta, exista o no el email: así nadie puede usar
    // este formulario para averiguar qué emails tienen cuenta.
    $respuesta = ['ok' => true, 'mensaje' => 'Si el email está registrado, te enviamos un código. Revisá tu correo (y la carpeta de spam).'];

    if (!$usuario) {
        responder(200, $respuesta);
    }
    // Un pedido por minuto como máximo, para que no se use para mandar spam.
    if ($pendiente && time() - strtotime($pendiente['creado_en']) < CODIGO_ESPERA) {
        responder(200, $respuesta);
    }

    $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $hash   = password_hash($codigo, PASSWORD_BCRYPT);
    $ahora  = date('Y-m-d H:i:s');
    $expira = date('Y-m-d H:i:s', time() + CODIGO_MINUTOS * 60);

    $stmt = $conn->prepare("REPLACE INTO password_resets (usuario_id, codigo_hash, expira, intentos, creado_en) VALUES (?, ?, ?, 0, ?)");
    $stmt->bind_param('isss', $idUsuario, $hash, $expira, $ahora);
    $stmt->execute();
    $stmt->close();

    [$asunto, $html] = correoCodigoRecuperacion($usuario['nombre'], $codigo, CODIGO_MINUTOS);
    [$enviado] = enviarCorreoBrevo($email, $usuario['nombre'], $asunto, $html);

    if (!$enviado) {
        responder(500, ['ok' => false, 'mensaje' => 'No pudimos enviar el correo en este momento. Probá de nuevo en unos minutos.']);
    }
    responder(200, $respuesta);
}

/* ── Paso 2: validar el código y cambiar la contraseña ── */
if ($accion === 'cambiar') {
    $codigo    = trim((string) ($body['codigo'] ?? ''));
    $password  = (string) ($body['password'] ?? '');
    $confirmar = (string) ($body['confirmar'] ?? '');

    $errores = [];
    if (!preg_match('/^\d{6}$/', $codigo)) {
        $errores['codigo'] = 'El código tiene 6 números.';
    }
    if (($err = validarPassword($password)) !== '') {
        $errores['password'] = $err;
    } elseif ($password !== $confirmar) {
        $errores['confirmar'] = 'Las contraseñas no coinciden.';
    }
    if ($errores) {
        responder(422, ['ok' => false, 'errores' => $errores]);
    }

    $invalido = ['ok' => false, 'errores' => ['codigo' => 'El código no es válido o ya venció. Pedí uno nuevo.']];

    if (!$usuario || !$pendiente || strtotime($pendiente['expira']) < time()) {
        responder(422, $invalido);
    }

    if (!password_verify($codigo, $pendiente['codigo_hash'])) {
        $intentos = (int) $pendiente['intentos'] + 1;
        if ($intentos >= CODIGO_INTENTOS) {
            // Demasiados intentos: se anula el código y hay que pedir otro.
            $stmt = $conn->prepare("DELETE FROM password_resets WHERE usuario_id = ?");
            $stmt->bind_param('i', $idUsuario);
            $stmt->execute();
            $stmt->close();
            responder(422, ['ok' => false, 'errores' => ['codigo' => 'Demasiados intentos. Pedí un código nuevo.']]);
        }
        $stmt = $conn->prepare("UPDATE password_resets SET intentos = ? WHERE usuario_id = ?");
        $stmt->bind_param('ii', $intentos, $idUsuario);
        $stmt->execute();
        $stmt->close();
        $quedan = CODIGO_INTENTOS - $intentos;
        responder(422, ['ok' => false, 'errores' => ['codigo' => "Código incorrecto. Te quedan {$quedan} intentos."]]);
    }

    // Código correcto: nueva contraseña encriptada y el código se borra (sirve una sola vez).
    $hashPassword = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $conn->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
    $stmt->bind_param('si', $hashPassword, $idUsuario);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM password_resets WHERE usuario_id = ?");
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $stmt->close();

    responder(200, [
        'ok'      => true,
        'mensaje' => 'Contraseña actualizada. Ya podés iniciar sesión.',
        'usuario' => $usuario['nombre'], // el login es por nombre de usuario: se lo completamos
    ]);
}

responder(400, ['ok' => false, 'mensaje' => 'Acción no válida.']);
