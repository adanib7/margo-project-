<?php
/**
 * Turnos de caja.
 *   GET                        → turno abierto con su resumen, y los últimos cierres
 *   GET ?id=N                  → resumen de un turno (para reimprimir el Z)
 *   POST {accion:"abrir", fondo}
 *   POST {accion:"cerrar", arqueo:{"2000":3,...}, nota}
 */
require_once __DIR__ . '/../includes/sesion.php';
iniciarSesion();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/check_auth.php';
require_once __DIR__ . '/../includes/facturacion_db.php';

$conn = facIniciarApi(FAC_ROLES_CAJA);

facEjecutar($conn, function () use ($conn) {
    $ahora = date('Y-m-d H:i:s');
    $usuario = facUsuarioNombre();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("SELECT * FROM turnos_caja WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $turno = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        } else {
            $turno = facTurnoAbierto($conn);
        }

        $abiertas = (int) $conn->query("SELECT COUNT(*) n FROM cuentas WHERE estado IN ('abierta', 'pide_cuenta')")->fetch_assoc()['n'];
        $historial = [];
        $res = $conn->query("SELECT id, abierto_en, cerrado_en, cerrado_por, diferencia,
                (SELECT COALESCE(SUM(monto), 0) FROM pagos p WHERE p.turno_id = t.id) total
            FROM turnos_caja t WHERE cerrado_en IS NOT NULL ORDER BY id DESC LIMIT 10");
        while ($r = $res->fetch_assoc()) {
            $historial[] = [
                'id' => (int) $r['id'], 'abierto_en' => $r['abierto_en'], 'cerrado_en' => $r['cerrado_en'],
                'cerrado_por' => $r['cerrado_por'], 'diferencia' => (float) $r['diferencia'], 'total' => (float) $r['total'],
            ];
        }

        facJson([
            'ok' => true,
            'turno' => $turno ? [
                'id' => (int) $turno['id'],
                'abierto_en' => $turno['abierto_en'],
                'abierto_por' => $turno['abierto_por'],
                'cerrado_en' => $turno['cerrado_en'],
                'fondo' => (float) $turno['fondo_inicial'],
                'contado' => $turno['efectivo_contado'] !== null ? (float) $turno['efectivo_contado'] : null,
                'diferencia' => $turno['diferencia'] !== null ? (float) $turno['diferencia'] : null,
                'arqueo' => $turno['arqueo'] ? json_decode($turno['arqueo'], true) : null,
            ] : null,
            'resumen' => $turno ? facResumenTurno($conn, (int) $turno['id']) : null,
            'cuentas_abiertas' => $abiertas,
            'denominaciones' => FAC_DENOMINACIONES,
            'fondo_sugerido' => (float) cfg('facturacion.fondo_sugerido'),
            'historial' => $historial,
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        facJson(['ok' => false, 'mensaje' => 'Método no permitido.'], 405);
    }

    $body = facCuerpo();
    $accion = (string) ($body['accion'] ?? '');

    if ($accion === 'abrir') {
        $fondo = round((float) ($body['fondo'] ?? 0), 2);
        if ($fondo < 0 || $fondo > 1000000) facJson(['ok' => false, 'mensaje' => 'El fondo inicial no es válido.'], 422);
        $conn->begin_transaction();
        $conn->query("SELECT id FROM turnos_caja WHERE cerrado_en IS NULL FOR UPDATE");
        if (facTurnoAbierto($conn)) { $conn->rollback(); facJson(['ok' => false, 'mensaje' => 'Ya hay una caja abierta.'], 409); }
        $stmt = $conn->prepare("INSERT INTO turnos_caja (abierto_por, abierto_en, fondo_inicial) VALUES (?, ?, ?)");
        $stmt->bind_param('ssd', $usuario, $ahora, $fondo);
        $stmt->execute();
        $stmt->close();
        $conn->commit();
        facJson(['ok' => true, 'mensaje' => 'Caja abierta. ¡Buen turno!']);
    }

    if ($accion === 'cerrar') {
        $arqueoIn = is_array($body['arqueo'] ?? null) ? $body['arqueo'] : [];
        $arqueo = [];
        $contado = 0.0;
        foreach (FAC_DENOMINACIONES as $den) {
            $n = max(0, min(100000, (int) ($arqueoIn[(string) $den] ?? 0)));
            $arqueo[(string) $den] = $n;
            $contado += $den * $n;
        }
        $nota = mb_substr(trim((string) ($body['nota'] ?? '')), 0, 255);

        $conn->begin_transaction();
        $turno = facTurnoAbierto($conn);
        if (!$turno) { $conn->rollback(); facJson(['ok' => false, 'mensaje' => 'No hay una caja abierta.'], 409); }
        $abiertas = (int) $conn->query("SELECT COUNT(*) n FROM cuentas WHERE estado IN ('abierta', 'pide_cuenta')")->fetch_assoc()['n'];
        if ($abiertas > 0) {
            $conn->rollback();
            facJson(['ok' => false, 'mensaje' => $abiertas === 1 ? 'Queda 1 mesa abierta. Cobrala o anulala antes de cerrar.' : "Quedan {$abiertas} mesas abiertas. Cobralas o anulalas antes de cerrar."], 409);
        }
        $resumen = facResumenTurno($conn, (int) $turno['id']);
        $esperado = round((float) $turno['fondo_inicial'] + $resumen['efectivo_cobrado'], 2);
        $diferencia = round($contado - $esperado, 2);
        $arqueoJson = json_encode($arqueo);
        $notaParam = $nota !== '' ? $nota : null;
        $id = (int) $turno['id'];
        $stmt = $conn->prepare("UPDATE turnos_caja SET cerrado_por = ?, cerrado_en = ?, efectivo_esperado = ?, efectivo_contado = ?, diferencia = ?, arqueo = ?, nota = ? WHERE id = ?");
        $stmt->bind_param('ssdddssi', $usuario, $ahora, $esperado, $contado, $diferencia, $arqueoJson, $notaParam, $id);
        $stmt->execute();
        $stmt->close();
        $conn->commit();
        facJson(['ok' => true, 'mensaje' => 'Caja cerrada. Reporte Z guardado.', 'turno_id' => $id, 'diferencia' => $diferencia]);
    }

    facJson(['ok' => false, 'mensaje' => 'Acción desconocida.'], 400);
});
