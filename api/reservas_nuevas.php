<?php
/**
 * Avisos en vivo del panel de admin (assets/js/avisos.js).
 *
 * El navegador del admin pregunta cada pocos segundos: "¿hay reservas con id
 * mayor a la última que vi?". La primera vez (sin desde_id) solo devuelve el
 * último id, para no avisar de reservas viejas.
 *
 *   GET ?desde_id=123  ->  { ok, ultimo_id, reservas: [ ... ] }
 */
session_start();
require_once '../includes/config.php';
require_once '../includes/plano_db.php';

header('Content-Type: application/json; charset=utf-8');

if (!in_array($_SESSION['rol'] ?? '', ['admin', 'superadmin'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'mensaje' => 'Acceso denegado.']);
    exit;
}

if ($conn === null) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Sin conexión a la base de datos.']);
    exit;
}

$res = $conn->query("SELECT COALESCE(MAX(id), 0) AS ultimo FROM reservas");
$ultimoId = $res ? (int) $res->fetch_assoc()['ultimo'] : 0;

$reservas = [];
if (isset($_GET['desde_id'])) {
    $desdeId = (int) $_GET['desde_id'];

    // Con el plano montado también traemos el número de mesa.
    $sql = planoLigadoAReservas($conn)
        ? "SELECT r.id, r.codigo, r.nombre, r.fecha, r.hora, r.personas, r.estado, m.numero AS mesa
           FROM reservas r LEFT JOIN mesas m ON m.id = r.mesa_id
           WHERE r.id > ? ORDER BY r.id ASC LIMIT 10"
        : "SELECT r.id, r.codigo, r.nombre, r.fecha, r.hora, r.personas, r.estado, NULL AS mesa
           FROM reservas r
           WHERE r.id > ? ORDER BY r.id ASC LIMIT 10";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $desdeId);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
        $reservas[] = [
            'id'       => (int) $r['id'],
            'codigo'   => $r['codigo'],
            'nombre'   => $r['nombre'],
            'fecha'    => $r['fecha'],
            'hora'     => substr((string) $r['hora'], 0, 5),
            'personas' => (int) $r['personas'],
            'estado'   => $r['estado'],
            'mesa'     => $r['mesa'] !== null ? (int) $r['mesa'] : null,
        ];
    }
    $stmt->close();
}

echo json_encode(['ok' => true, 'ultimo_id' => $ultimoId, 'reservas' => $reservas], JSON_UNESCAPED_UNICODE);
