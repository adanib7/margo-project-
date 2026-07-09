<?php
// Devuelve todas las mesas del plano en JSON.
// Si le pasás ?fecha=2026-07-15&hora=21:00 también te dice si cada mesa está ocupada.
require "conexion.php";
header("Content-Type: application/json");

$mesas = $pdo->query("SELECT id, numero, capacidad, pos_x, pos_y, forma, ancho, alto, rotacion FROM mesa")->fetchAll();

// Estado de ocupación (solo si mandan fecha y hora)
$ocupadas = [];
if (!empty($_GET['fecha']) && !empty($_GET['hora'])) {
    // OJO: ajustá los nombres de columnas a tu tabla reserva real.
    // Acá se asume: reserva(id, id_mesa, fecha DATE, hora TIME, estado)
    $stmt = $pdo->prepare(
        "SELECT id_mesa FROM reserva
         WHERE fecha = ? AND hora = ? AND estado != 'cancelada'"
    );
    $stmt->execute([$_GET['fecha'], $_GET['hora']]);
    $ocupadas = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

foreach ($mesas as &$m) {
    // MySQL devuelve los números como texto y eso rompe a Konva,
    // así que los casteamos antes de mandar el JSON.
    $m['id']        = (int)$m['id'];
    $m['numero']    = (int)$m['numero'];
    $m['capacidad'] = (int)$m['capacidad'];
    $m['pos_x']     = (float)$m['pos_x'];
    $m['pos_y']     = (float)$m['pos_y'];
    $m['ancho']     = (float)$m['ancho'];
    $m['alto']      = (float)$m['alto'];
    $m['rotacion']  = (float)$m['rotacion'];
    $m['ocupada']   = in_array($m['id'], $ocupadas);
}

echo json_encode($mesas);