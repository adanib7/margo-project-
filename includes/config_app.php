<?php
/**
 * Configuración editable del restaurante (tabla `configuracion`, clave/valor).
 *
 * Todo tiene un valor por defecto en código: si la tabla no existe o está
 * vacía, la app se comporta exactamente igual que antes. Así el sitio sigue
 * funcionando aunque el hosting no deje crear tablas desde PHP.
 *
 *   cfg('reservas.max_personas')   -> lee (con caché en memoria)
 *   cfgGuardar($conn, [...])       -> escribe
 */

/**
 * Valores por defecto. La clave usa prefijo por bloque.
 */
function cfgDefaults(): array
{
    return [
        // ── Datos del local ──
        'local.nombre'    => 'El Corralín de Campanal',
        'local.eslogan'   => 'Cocina asturiana y sidra de llagar',
        'local.direccion' => 'Plaza Manuel Uría, 4',
        'local.cp'        => '33520',
        'local.ciudad'    => 'Nava',
        'local.provincia' => 'Asturias',
        'local.telefono'  => '985 71 60 42',
        'local.email'     => 'reservas@elcorralindelcampanal.com',
        'local.sitio_url' => 'https://corralin.kesug.com',

        // ── Horarios de servicio ──
        'horario.almuerzo_activo' => '1',
        'horario.almuerzo_inicio' => '12:00',
        'horario.almuerzo_fin'    => '15:00',
        'horario.cena_activo'     => '1',
        'horario.cena_inicio'     => '20:00',
        'horario.cena_fin'        => '23:00',
        'horario.intervalo_min'   => '30',

        // Días de la semana cerrados: 1 = lunes … 7 = domingo (formato ISO "N").
        'horario.dias_cerrados'   => '[1]',
        // Fechas sueltas cerradas (festivos, vacaciones): ["2026-12-25", …]
        'horario.fechas_cerradas' => '[]',

        // ── Reglas de reserva ──
        'reservas.max_personas'         => '20',
        'reservas.antelacion_min_horas' => '2',
        'reservas.antelacion_max_dias'  => '60',
        'reservas.duracion_horas'       => '2',
        'reservas.cortesia_min'         => '15',
        'reservas.auto_confirmar'       => '1',
    ];
}

/**
 * Crea la tabla si hace falta. Silencioso si el hosting no deja.
 */
function ensureConfiguracionTable(mysqli $conn): void
{
    $conn->query("
        CREATE TABLE IF NOT EXISTS configuracion (
            clave VARCHAR(60) NOT NULL,
            valor TEXT NOT NULL,
            actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (clave)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}

/**
 * Carga toda la configuración una sola vez por request (1 query).
 */
function cfgTodo(): array
{
    static $cache = null;

    if (!empty($GLOBALS['__cfg_dirty'])) {
        $cache = null;
        $GLOBALS['__cfg_dirty'] = false;
    }
    if ($cache !== null) {
        return $cache;
    }

    $cache = cfgDefaults();

    $conn = $GLOBALS['conn'] ?? null;
    if (!($conn instanceof mysqli)) {
        return $cache;
    }

    $t = $conn->query("SHOW TABLES LIKE 'configuracion'");
    if (!$t || $t->num_rows === 0) {
        return $cache;
    }

    $res = $conn->query("SELECT clave, valor FROM configuracion");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            // Solo claves conocidas: así una fila vieja no rompe nada.
            if (array_key_exists($row['clave'], $cache)) {
                $cache[$row['clave']] = $row['valor'];
            }
        }
    }
    return $cache;
}

/**
 * Lee un valor de configuración.
 */
function cfg(string $clave, $default = null)
{
    $todo = cfgTodo();
    return $todo[$clave] ?? ($default ?? (cfgDefaults()[$clave] ?? null));
}

function cfgInt(string $clave): int
{
    return (int) cfg($clave);
}

function cfgBool(string $clave): bool
{
    return (string) cfg($clave) === '1';
}

function cfgArray(string $clave): array
{
    $v = json_decode((string) cfg($clave), true);
    return is_array($v) ? $v : [];
}

/**
 * Guarda pares clave/valor. Ignora claves desconocidas.
 *
 * @return array [ok(bool), guardadas(int)]
 */
function cfgGuardar(mysqli $conn, array $pares): array
{
    ensureConfiguracionTable($conn);
    $validas = cfgDefaults();

    $stmt = $conn->prepare(
        "INSERT INTO configuracion (clave, valor) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE valor = VALUES(valor)"
    );
    if ($stmt === false) {
        return [false, 0];
    }

    $n = 0;
    foreach ($pares as $clave => $valor) {
        if (!array_key_exists($clave, $validas)) {
            continue;
        }
        $v = (string) $valor;
        $stmt->bind_param('ss', $clave, $v);
        if ($stmt->execute()) {
            $n++;
        }
    }
    $stmt->close();

    // Invalida el caché del request en curso.
    cfgResetCache();

    return [true, $n];
}

/**
 * Fuerza a releer la configuración (tras guardar).
 */
function cfgResetCache(): void
{
    // cfgTodo() mira este flag y descarta su static en la próxima llamada.
    $GLOBALS['__cfg_dirty'] = true;
}

/* ══════════════════════ Datos del local ══════════════════════ */

/**
 * "Plaza Manuel Uría, 4 · 33520 Nava (Asturias)"
 *
 * @param string $sep         separador entre calle y localidad
 * @param bool   $provParen   provincia entre paréntesis (si no, tras una coma)
 */
function localDireccion(string $sep = ' · ', bool $provParen = true): string
{
    $calle     = trim((string) cfg('local.direccion'));
    $cp        = trim((string) cfg('local.cp'));
    $ciudad    = trim((string) cfg('local.ciudad'));
    $provincia = trim((string) cfg('local.provincia'));

    $localidad = trim($cp . ' ' . $ciudad);
    if ($provincia !== '') {
        $localidad .= $provParen ? " ({$provincia})" : ", {$provincia}";
    }

    return trim($calle . ($calle !== '' && $localidad !== '' ? $sep : '') . $localidad);
}

/**
 * Teléfono en formato `tel:` (sin espacios, con prefijo internacional).
 */
function localTelefonoLink(string $prefijo = '+34'): string
{
    $t = preg_replace('/[^0-9]/', '', (string) cfg('local.telefono'));
    return $t === '' ? '' : $prefijo . $t;
}

/**
 * Dominio del sitio, sin protocolo (para mostrar en textos).
 */
function localDominio(): string
{
    return preg_replace('~^https?://~', '', rtrim((string) cfg('local.sitio_url'), '/'));
}

/* ══════════════════════ Derivados de horarios ══════════════════════ */

/**
 * Genera los horarios de un turno: "12:00","12:30",… hasta el fin (incluido).
 *
 * @return list<string>
 */
function horarioSlots(string $inicio, string $fin, int $intervalo): array
{
    if ($intervalo < 5) {
        $intervalo = 30;
    }
    $ini = strtotime('1970-01-01 ' . $inicio . ':00 UTC');
    $end = strtotime('1970-01-01 ' . $fin . ':00 UTC');
    if ($ini === false || $end === false || $end < $ini) {
        return [];
    }

    $slots = [];
    for ($t = $ini; $t <= $end; $t += $intervalo * 60) {
        $slots[] = gmdate('H:i', $t);
        if (count($slots) > 60) {
            break; // guardia por si alguien pone un intervalo absurdo
        }
    }
    return $slots;
}

/**
 * Franjas horarias activas, listas para pintar en el formulario.
 *
 * @return array<string, list<string>>
 */
function horarioFranjas(): array
{
    $franjas   = [];
    $intervalo = cfgInt('horario.intervalo_min');

    if (cfgBool('horario.almuerzo_activo')) {
        $s = horarioSlots((string) cfg('horario.almuerzo_inicio'), (string) cfg('horario.almuerzo_fin'), $intervalo);
        if ($s) {
            $franjas['Almuerzo'] = $s;
        }
    }
    if (cfgBool('horario.cena_activo')) {
        $s = horarioSlots((string) cfg('horario.cena_inicio'), (string) cfg('horario.cena_fin'), $intervalo);
        if ($s) {
            $franjas['Cena'] = $s;
        }
    }
    return $franjas;
}

/**
 * Todos los horarios válidos en una sola lista.
 *
 * @return list<string>
 */
function horarioTodosLosSlots(): array
{
    $todos = [];
    foreach (horarioFranjas() as $slots) {
        foreach ($slots as $s) {
            $todos[] = $s;
        }
    }
    return $todos;
}

/**
 * ¿El restaurante cierra ese día? (por día de la semana o fecha puntual)
 */
function fechaCerrada(string $fecha): bool
{
    if (in_array($fecha, cfgArray('horario.fechas_cerradas'), true)) {
        return true;
    }
    $dia = (int) (new DateTime($fecha))->format('N'); // 1 = lunes … 7 = domingo
    return in_array($dia, array_map('intval', cfgArray('horario.dias_cerrados')), true);
}

const DIAS_SEMANA = [
    1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves',
    5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo',
];

/* ══════════════════════ Reglas de reserva ══════════════════════ */

/**
 * Valida fecha + hora contra los días de cierre y la antelación permitida.
 *
 * @return array<string,string>  errores por campo (vacío si está todo bien)
 */
function validarMomentoReserva(string $fecha, string $hora, ?DateTime $ahora = null): array
{
    $errores = [];
    $ahora   = $ahora ?? new DateTime('now');

    if (fechaCerrada($fecha)) {
        // Distinguimos el cierre semanal del puntual: el mensaje es más claro.
        if (in_array($fecha, cfgArray('horario.fechas_cerradas'), true)) {
            $errores['fecha'] = 'Ese día el restaurante está cerrado. Elegí otra fecha.';
        } else {
            $dia = DIAS_SEMANA[(int) (new DateTime($fecha))->format('N')] ?? '';
            $errores['fecha'] = $dia !== ''
                ? "Los {$dia} el restaurante está cerrado."
                : 'Ese día el restaurante está cerrado.';
        }
        return $errores;
    }

    $maxDias = cfgInt('reservas.antelacion_max_dias');
    if ($maxDias > 0) {
        $limite = (clone $ahora)->modify("+{$maxDias} days");
        if ($fecha > $limite->format('Y-m-d')) {
            $errores['fecha'] = "Solo se puede reservar con hasta {$maxDias} días de antelación.";
            return $errores;
        }
    }

    $momento = DateTime::createFromFormat('Y-m-d H:i', $fecha . ' ' . $hora);
    if (!$momento) {
        $errores['hora'] = 'Seleccioná un horario válido.';
        return $errores;
    }

    $minHoras = cfgInt('reservas.antelacion_min_horas');
    $minimo   = (clone $ahora)->modify("+{$minHoras} hours");

    if ($momento < $minimo) {
        $errores['hora'] = $minHoras > 0
            ? "Hay que reservar con al menos {$minHoras} " . ($minHoras === 1 ? 'hora' : 'horas') . ' de antelación.'
            : 'Ese horario ya pasó.';
    }

    return $errores;
}
