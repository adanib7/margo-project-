<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/plano_db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'mensaje' => 'Debés iniciar sesión para ver horarios.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido.']);
    exit;
}

$fecha = trim($_GET['fecha'] ?? '');

if ($fecha === '' || DateTime::createFromFormat('Y-m-d', $fecha)->format('Y-m-d') !== $fecha) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'Fecha inválida.']);
    exit;
}

if ($conn === null) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'No se pudo conectar con la base de datos.']);
    exit;
}

// Total de mesas del plano: una franja horaria solo se considera "completa"
// cuando ya no queda ninguna mesa libre.
$totalMesas = 0;
$resMesas = $conn->query("SHOW TABLES LIKE 'mesas'");
if ($resMesas && $resMesas->num_rows > 0) {
    $row = $conn->query("SELECT COUNT(*) AS total FROM mesas")->fetch_assoc();
    $totalMesas = (int) ($row['total'] ?? 0);
}

$horarios = [];

if ($totalMesas > 0) {
    // Una mesa sigue ocupada durante toda su duración, así que hay que contar
    // por solapamiento y no por hora exacta: se revisa cada horario del turno.
    $stmt = $conn->prepare(
        "SELECT COUNT(DISTINCT mesa_id) AS reservadas
         FROM reservas
         WHERE fecha = ? AND estado != 'cancelada' AND mesa_id IS NOT NULL
           AND hora < ? AND ADDTIME(hora, ?) > ?"
    );

    if ($stmt !== false) {
        foreach (horarioTodosLosSlots() as $slot) {
            $f = franjaReserva($slot);
            $stmt->bind_param('ssss', $fecha, $f['fin'], $f['duracion'], $f['inicio']);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();

            if ((int) ($row['reservadas'] ?? 0) >= $totalMesas) {
                $horarios[] = $slot;
            }
        }
        $stmt->close();
    }
}

echo json_encode(['ok' => true, 'horarios' => array_values(array_unique($horarios))]);
