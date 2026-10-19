<?php
// Registro público: primero se comprueba el correo y después se crea la cuenta.
require_once __DIR__ . '/mailer.php';

const REGISTRO_MINUTOS = 15;
const REGISTRO_INTENTOS = 5;
const REGISTRO_ESPERA = 60;
const REGISTRO_ENVIOS_EMAIL = 5; // por hora
const REGISTRO_ENVIOS_IP = 10;   // solicitudes por hora, también si no se envía

function registroPreparar(mysqli $conn): void
{
    // El deploy excluye "base de datos"; el esquema también vive aquí.
    // El SQL manual permite prepararlo en un hosting sin permisos para crear tablas.
    $schema = <<<'SQL'
CREATE TABLE IF NOT EXISTS registro_pendientes (
    email VARCHAR(255) NOT NULL PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL DEFAULT '',
    password_hash VARCHAR(255) DEFAULT NULL,
    google_id VARCHAR(255) DEFAULT NULL,
    foto VARCHAR(500) DEFAULT NULL,
    codigo_hash VARCHAR(255) NOT NULL DEFAULT '',
    solicitud_hash CHAR(64) NOT NULL DEFAULT '',
    expira BIGINT NOT NULL DEFAULT 0,
    intentos INT NOT NULL DEFAULT 0,
    envios INT NOT NULL DEFAULT 0,
    ventana BIGINT NOT NULL DEFAULT 0,
    enviado_en BIGINT NOT NULL DEFAULT 0,
    creado_en BIGINT NOT NULL,
    INDEX idx_registro_creado (creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS registro_limites (
    clave CHAR(64) NOT NULL PRIMARY KEY,
    inicio BIGINT NOT NULL,
    cantidad INT NOT NULL,
    INDEX idx_registro_inicio (inicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL;
    $consultas = explode(';', $schema);
    foreach (['registro_pendientes', 'registro_limites'] as $i => $tabla) {
        $existe = $conn->query("SHOW TABLES LIKE '{$tabla}'");
        if ($existe->num_rows === 0) {
            $conn->query($consultas[$i]);
        }
    }
    $ayer = time() - 86400;
    $conn->query("DELETE FROM registro_pendientes WHERE creado_en < {$ayer}");
    $conn->query("DELETE FROM registro_limites WHERE inicio < {$ayer}");
}

function registroLimiteIp(mysqli $conn, string $accion, int $maximo): bool
{
    // No confiar en X-Forwarded-For enviado por el cliente. No se guarda la IP en claro.
    $clave = hash('sha256', $accion . ':' . ($_SERVER['REMOTE_ADDR'] ?? 'sin-ip'));
    $inicio = intdiv(time(), 3600) * 3600;
    $stmt = $conn->prepare(
        'INSERT INTO registro_limites (clave, inicio, cantidad) VALUES (?, ?, 1)
         ON DUPLICATE KEY UPDATE
           cantidad = IF(inicio < VALUES(inicio), 1, cantidad + 1), inicio = VALUES(inicio)'
    );
    $stmt->bind_param('si', $clave, $inicio);
    $stmt->execute();
    $stmt->close();
    $stmt = $conn->prepare('SELECT cantidad FROM registro_limites WHERE clave = ?');
    $stmt->bind_param('s', $clave);
    $stmt->execute();
    $cantidad = (int) $stmt->get_result()->fetch_assoc()['cantidad'];
    $stmt->close();
    return $cantidad <= $maximo;
}

// $datos solo se pasa al empezar un registro. Al reenviar se conserva lo guardado.
function registroSolicitar(mysqli $conn, string $email, ?array $datos = null): array
{
    if (!mailHabilitado()) {
        return [false, 'No podemos enviar códigos en este momento. Intentá registrarte más tarde.'];
    }
    if (!registroLimiteIp($conn, 'enviar', REGISTRO_ENVIOS_IP)) {
        return [false, 'Se alcanzó el límite de solicitudes desde esta conexión. Probá en una hora.'];
    }

    $ahora = time();
    $conn->begin_transaction();
    try {
        if ($datos !== null) {
            $stmt = $conn->prepare('INSERT IGNORE INTO registro_pendientes (email, creado_en) VALUES (?, ?)');
            $stmt->bind_param('si', $email, $ahora);
            $stmt->execute();
            $stmt->close();
        }
        // El bloqueo evita que dos reenvíos salteen la espera o los contadores.
        $stmt = $conn->prepare('SELECT * FROM registro_pendientes WHERE email = ? FOR UPDATE');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $pendiente = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$pendiente) {
            $conn->rollback();
            return [false, 'El registro pendiente ya no existe. Volvé a registrarte.'];
        }
        if ($datos === null && !hash_equals($pendiente['solicitud_hash'], hash('sha256', $_SESSION['registro_solicitud'] ?? ''))) {
            $conn->rollback();
            return [false, 'Este registro se inició en otro navegador. Volvé a registrarte para recibir tu propio código.'];
        }
        $espera = REGISTRO_ESPERA - ($ahora - (int) $pendiente['enviado_en']);
        if ($espera > 0) {
            $conn->rollback();
            return [false, "Esperá {$espera} segundos antes de pedir otro código."];
        }
        $ventana = (int) $pendiente['ventana'];
        $envios = (int) $pendiente['envios'];
        if ($ahora - $ventana >= 3600) {
            $ventana = $ahora;
            $envios = 0;
        }
        if ($envios >= REGISTRO_ENVIOS_EMAIL) {
            $conn->rollback();
            return [false, 'Se alcanzó el límite de códigos para este correo. Probá en una hora.'];
        }

        $nombre = $datos['nombre'] ?? $pendiente['nombre'];
        $password = $datos !== null
            ? (isset($datos['password']) ? password_hash($datos['password'], PASSWORD_BCRYPT) : null)
            : $pendiente['password_hash'];
        $googleId = $datos !== null ? ($datos['google_id'] ?? null) : $pendiente['google_id'];
        $foto = $datos !== null ? ($datos['foto'] ?? null) : $pendiente['foto'];
        $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $hash = password_hash($codigo, PASSWORD_BCRYPT);
        // Vincular el código al navegador que inició el registro. Otra solicitud
        // del mismo email no debe poder cambiar la contraseña que este usuario confirma.
        $solicitud = $datos !== null ? bin2hex(random_bytes(32)) : $_SESSION['registro_solicitud'];
        $solicitudHash = hash('sha256', $solicitud);
        $expira = $ahora + REGISTRO_MINUTOS * 60;
        $envios++;
        $stmt = $conn->prepare(
            'UPDATE registro_pendientes SET nombre = ?, password_hash = ?, google_id = ?, foto = ?,
             codigo_hash = ?, solicitud_hash = ?, expira = ?, intentos = 0, envios = ?, ventana = ?, enviado_en = ? WHERE email = ?'
        );
        $stmt->bind_param('ssssssiiiis', $nombre, $password, $googleId, $foto, $hash, $solicitudHash, $expira, $envios, $ventana, $ahora, $email);
        $stmt->execute();
        $stmt->close();
        $conn->commit();
        $_SESSION['registro_solicitud'] = $solicitud;
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }

    [$asunto, $html] = correoCodigoRegistro($nombre, $codigo, REGISTRO_MINUTOS);
    [$enviado] = enviarCorreoBrevo($email, $nombre, $asunto, $html, false);
    if (!$enviado) {
        // Un envío fallido no habilita la cuenta ni deja un código utilizable.
        $stmt = $conn->prepare("UPDATE registro_pendientes SET codigo_hash = '', expira = 0 WHERE email = ? AND codigo_hash = ?");
        $stmt->bind_param('ss', $email, $hash);
        $stmt->execute();
        $stmt->close();
        return [false, 'No pudimos enviar el correo. Esperá un minuto y usá Reenviar código.'];
    }
    return [true, 'Te enviamos un código. Revisá tu correo y la carpeta de spam. Vence en 15 minutos.'];
}

function registroConfirmar(mysqli $conn, string $email, string $codigo): array
{
    if (!registroLimiteIp($conn, 'confirmar', 30)) {
        return [false, 'Demasiados intentos desde esta conexión. Probá en una hora.'];
    }
    if (!preg_match('/^[0-9]{6}$/', $codigo)) {
        return [false, 'Ingresá el código de seis números.'];
    }
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare('SELECT * FROM registro_pendientes WHERE email = ? FOR UPDATE');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $pendiente = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$pendiente || (int) $pendiente['expira'] <= time()) {
            $conn->rollback();
            return [false, 'El código no es válido o venció. Pedí uno nuevo.'];
        }
        if (!hash_equals($pendiente['solicitud_hash'], hash('sha256', $_SESSION['registro_solicitud'] ?? ''))) {
            $conn->rollback();
            return [false, 'Este código corresponde a otro registro. Volvé a registrarte en este navegador.'];
        }
        if ((int) $pendiente['intentos'] >= REGISTRO_INTENTOS) {
            $conn->rollback();
            return [false, 'Se agotaron los cinco intentos. Pedí un código nuevo.'];
        }
        if (!password_verify($codigo, $pendiente['codigo_hash'])) {
            $stmt = $conn->prepare('UPDATE registro_pendientes SET intentos = intentos + 1 WHERE email = ?');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $stmt->close();
            $conn->commit();
            $restantes = REGISTRO_INTENTOS - (int) $pendiente['intentos'] - 1;
            return [false, $restantes > 0 ? "Código incorrecto. Te quedan {$restantes} intentos." : 'Se agotaron los cinco intentos. Pedí un código nuevo.'];
        }

        // Nunca reemplazar contraseña ni permisos de una cuenta existente.
        $stmt = $conn->prepare('SELECT id, nombre, google_id FROM usuarios WHERE email = ? FOR UPDATE');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $existente = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $googleId = $pendiente['google_id'];
        $nombre = $pendiente['nombre'];
        if ($existente) {
            if (!$googleId || (!empty($existente['google_id']) && $existente['google_id'] !== $googleId)) {
                $conn->rollback();
                return [false, 'Ese correo ya tiene una cuenta. Iniciá sesión o recuperá tu contraseña.'];
            }
            $stmt = $conn->prepare('UPDATE usuarios SET google_id = ?, foto = ? WHERE id = ?');
            $stmt->bind_param('ssi', $googleId, $pendiente['foto'], $existente['id']);
            $nombre = $existente['nombre'];
        } else {
            // Un nombre pudo ocuparse mientras llegaba el correo. No crear un duplicado.
            $stmt = $conn->prepare('SELECT id FROM usuarios WHERE nombre = ?');
            $stmt->bind_param('s', $nombre);
            $stmt->execute();
            $ocupado = $stmt->get_result()->num_rows > 0;
            $stmt->close();
            if ($ocupado) {
                $conn->rollback();
                return [false, 'Ese nombre de usuario ya se ocupó. Volvé a registrarte con otro.'];
            }
            $stmt = $conn->prepare("INSERT INTO usuarios (nombre, email, password, google_id, foto, rol) VALUES (?, ?, ?, ?, ?, 'usuario')");
            $stmt->bind_param('sssss', $nombre, $email, $pendiente['password_hash'], $googleId, $pendiente['foto']);
        }
        $stmt->execute();
        $stmt->close();
        // Consumir el código y crear/vincular la cuenta es una sola operación.
        $stmt = $conn->prepare('DELETE FROM registro_pendientes WHERE email = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->close();
        $conn->commit();
        return [true, $googleId ? 'Correo verificado. Ya podés continuar con Google.' : '¡Correo verificado! Ya podés iniciar sesión.', $nombre];
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}
