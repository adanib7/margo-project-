<?php

require "conexion.php";
header("Content-Type: application/json");

$mesas = $pdo->query("SELECT id, numero, capacidad, pos_x, pos_y, forma, ancho, alto, rotacion FROM mesa")->fetchAll();

// Estado de ocupación (solo si mandan fecha y hora)
$ocupadas = [];
if (!empty($_GET['fecha']) && !empty($_GET['hora'])) {

    $stmt = $pdo->prepare(
        "SELECT id_mesa FROM reserva
         WHERE fecha = ? AND hora = ? AND estado != 'cancelada'"
    );
    $stmt->execute([$_GET['fecha'], $_GET['hora']]);
    $ocupadas = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

foreach ($mesas as &$m) {
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