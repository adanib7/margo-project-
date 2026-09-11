<?php
/**
 * Esquema y consultas compartidas del plano de mesas.
 *
 * Lo usan tanto el editor del admin (dashboards/admin_plano.php) como el
 * selector de mesa del cliente (paso "Elegí tu mesa" en el modal de reserva),
 * así ambos leen y escriben SIEMPRE sobre la misma tabla `mesas`.
 */

const PLANO_ANCHO = 900;
const PLANO_ALTO  = 560;

/**
 * Crea la tabla `mesas` si no existe y agrega columnas que falten.
 */
function ensureMesaTable(mysqli $conn): void
{
    $conn->query("
        CREATE TABLE IF NOT EXISTS mesas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            numero INT NOT NULL DEFAULT 1,
            capacidad INT NOT NULL DEFAULT 4,
            forma VARCHAR(20) NOT NULL DEFAULT 'cuadrada',
            pos_x DOUBLE NOT NULL DEFAULT 100,
            pos_y DOUBLE NOT NULL DEFAULT 100,
            ancho DOUBLE NOT NULL DEFAULT 70,
            alto DOUBLE NOT NULL DEFAULT 70,
            rotacion DOUBLE NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $columns = [
        ['numero', 'INT NOT NULL DEFAULT 1'],
        ['capacidad', 'INT NOT NULL DEFAULT 4'],
        ['forma', "VARCHAR(20) NOT NULL DEFAULT 'cuadrada'"],
        ['pos_x', 'DOUBLE NOT NULL DEFAULT 100'],
        ['pos_y', 'DOUBLE NOT NULL DEFAULT 100'],
        ['ancho', 'DOUBLE NOT NULL DEFAULT 70'],
        ['alto', 'DOUBLE NOT NULL DEFAULT 70'],
        ['rotacion', 'DOUBLE NOT NULL DEFAULT 0'],
    ];

    // MariaDB no acepta `SHOW COLUMNS ... LIKE ?` preparado; $col es literal del
    // código (no entrada de usuario), así que se consulta directo.
    foreach ($columns as [$col, $definition]) {
        $result = $conn->query("SHOW COLUMNS FROM mesas LIKE '{$col}'");
        if ($result && $result->num_rows === 0) {
            $conn->query("ALTER TABLE mesas ADD COLUMN {$col} {$definition}");
        }
    }
}

/**
 * Agrega la columna `mesa_id` a `reservas` para ligar cada reserva a una mesa
 * concreta del plano. Se salta silenciosamente si la tabla `reservas` todavía
 * no existe (en local se importa aparte).
 */
function ensureReservaMesaColumn(mysqli $conn): void
{
    $tabla = $conn->query("SHOW TABLES LIKE 'reservas'");
    if (!$tabla || $tabla->num_rows === 0) {
        return;
    }

    $col = $conn->query("SHOW COLUMNS FROM reservas LIKE 'mesa_id'");
    if ($col && $col->num_rows === 0) {
        $conn->query("ALTER TABLE reservas ADD COLUMN mesa_id INT DEFAULT NULL AFTER usuario_id");
        $conn->query("ALTER TABLE reservas ADD INDEX idx_mesa_franja (mesa_id, fecha, hora)");
    }
}

/**
 * Permite que `reservas.usuario_id` quede en NULL.
 *
 * Hace falta para las reservas que carga el admin por teléfono o en el mostrador:
 * el cliente no tiene cuenta, así que la reserva no cuelga de ningún usuario.
 * La clave foránea sigue valiendo (un NULL no la viola).
 */
function ensureReservaUsuarioOpcional(mysqli $conn): void
{
    $tabla = $conn->query("SHOW TABLES LIKE 'reservas'");
    if (!$tabla || $tabla->num_rows === 0) {
        return;
    }

    $res = $conn->query("SHOW COLUMNS FROM reservas LIKE 'usuario_id'");
    $col = $res ? $res->fetch_assoc() : null;

    if ($col && strtoupper((string) ($col['Null'] ?? '')) === 'NO') {
        $conn->query("ALTER TABLE reservas MODIFY `usuario_id` INT(11) NULL");
    }
}

/**
 * ¿Se puede consultar la mesa de una reserva? Es decir: existe la tabla `mesas`
 * y la columna `reservas.mesa_id`.
 *
 * Sirve para que el comprobante y "Mis reservas" sigan funcionando aunque el
 * plano todavía no se haya montado en ese servidor (p. ej. si el hosting no
 * dejó crear las tablas desde PHP).
 */
function planoLigadoAReservas(mysqli $conn): bool
{
    $t = $conn->query("SHOW TABLES LIKE 'mesas'");
    if (!$t || $t->num_rows === 0) {
        return false;
    }
    $c = $conn->query("SHOW COLUMNS FROM reservas LIKE 'mesa_id'");
    return (bool) ($c && $c->num_rows > 0);
}

/**
 * ¿La mesa tiene al menos una reserva asociada? (para no borrarla en el editor)
 */
function mesaTieneReservas(mysqli $conn, int $mesaId): bool
{
    $stmt = $conn->prepare("SELECT 1 FROM reservas WHERE mesa_id = ? LIMIT 1");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $mesaId);
    $stmt->execute();
    $stmt->store_result();
    $tiene = $stmt->num_rows > 0;
    $stmt->close();
    return $tiene;
}

/**
 * Ventana que ocupa una reserva: desde su hora hasta hora + duración de mesa
 * (Configuración → Reglas de reserva).
 *
 * El fin puede pasar de las 24:00 (ej. "24:30:00"): el tipo TIME de MySQL lo
 * admite y así una reserva de las 23:00 no "vuelve" al principio del día.
 *
 * @return array{inicio:string, fin:string, duracion:string}
 */
function franjaReserva(string $hora): array
{
    $horas = function_exists('cfgInt') ? max(1, cfgInt('reservas.duracion_horas')) : 2;

    [$h, $m] = array_map('intval', array_pad(explode(':', substr($hora, 0, 5)), 2, 0));
    $iniMin = $h * 60 + $m;

    $fmt = static function (int $min): string {
        return sprintf('%02d:%02d:00', intdiv($min, 60), $min % 60);
    };

    return [
        'inicio'   => $fmt($iniMin),
        'fin'      => $fmt($iniMin + $horas * 60),
        'duracion' => $fmt($horas * 60),
    ];
}

/**
 * ¿Hay otra reserva activa que se solape con esa mesa en esa fecha/hora?
 *
 * Dos reservas chocan si sus intervalos se pisan, no solo si empiezan a la
 * misma hora: con mesas de 2 h, las 21:00 y las 21:30 son la misma mesa.
 *
 * @return array{codigo:string,hora:string}|null  la reserva en conflicto
 */
function reservaSolapada(mysqli $conn, int $mesaId, string $fecha, string $hora, ?int $excluirId = null): ?array
{
    $f = franjaReserva($hora);

    $sql = "SELECT codigo, hora FROM reservas
            WHERE mesa_id = ? AND fecha = ? AND estado != 'cancelada'
              AND hora < ? AND ADDTIME(hora, ?) > ?";
    $tipos  = 'issss';
    $params = [$mesaId, $fecha, $f['fin'], $f['duracion'], $f['inicio']];

    if ($excluirId !== null) {
        $sql   .= ' AND id <> ?';
        $tipos .= 'i';
        $params[] = $excluirId;
    }
    $sql .= ' LIMIT 1';

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        return null;
    }
    $stmt->bind_param($tipos, ...$params);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

/**
 * Devuelve todas las mesas del plano. Si se pasa fecha + hora, marca `ocupada`
 * según las reservas activas que se solapen con esa franja.
 *
 * @return list<array<string,mixed>>
 */
function planoMesasConOcupacion(mysqli $conn, string $fecha = '', string $hora = ''): array
{
    $ocupadas = [];

    if ($fecha !== '' && $hora !== '') {
        $tabla = $conn->query("SHOW TABLES LIKE 'reservas'");
        if ($tabla && $tabla->num_rows > 0) {
            // Ocupada = su intervalo se pisa con el de la franja consultada.
            $f = franjaReserva($hora);
            $stmt = $conn->prepare(
                "SELECT mesa_id FROM reservas
                 WHERE fecha = ? AND estado != 'cancelada' AND mesa_id IS NOT NULL
                   AND hora < ? AND ADDTIME(hora, ?) > ?"
            );
            $stmt->bind_param('ssss', $fecha, $f['fin'], $f['duracion'], $f['inicio']);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $ocupadas[(int) $row['mesa_id']] = true;
            }
            $stmt->close();
        }
    }

    $mesas  = [];
    $result = $conn->query(
        "SELECT id, numero, capacidad, forma, pos_x, pos_y, ancho, alto, rotacion
         FROM mesas ORDER BY numero ASC, id ASC"
    );
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $id = (int) $row['id'];
            $mesas[] = [
                'id'        => $id,
                'numero'    => (int) $row['numero'],
                'capacidad' => (int) $row['capacidad'],
                'forma'     => $row['forma'] === 'redonda' ? 'redonda' : 'cuadrada',
                'pos_x'     => (float) $row['pos_x'],
                'pos_y'     => (float) $row['pos_y'],
                'ancho'     => (float) $row['ancho'],
                'alto'      => (float) $row['alto'],
                'rotacion'  => (float) $row['rotacion'],
                'ocupada'   => isset($ocupadas[$id]),
            ];
        }
    }

    return $mesas;
}
