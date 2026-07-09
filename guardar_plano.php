<?php
// Guarda el plano que manda el editor del admin.
// Recibe por POST un JSON: { "mesas": [...], "eliminadas": [ids] }
// IMPORTANTE: acá arriba tendría que ir tu control de sesión de admin, ej:
// session_start();
// if (($_SESSION['rol'] ?? '') !== 'admin') { http_response_code(403); die(); }

require "conexion.php";
header("Content-Type: application/json");

$datos = json_decode(file_get_contents("php://input"), true);
if (!$datos || !isset($datos['mesas'])) {
    http_response_code(400);
    die(json_encode(["error" => "Datos inválidos"]));
}

try {
    $pdo->beginTransaction();

    // 1) Eliminar mesas que el admin borró del plano
    // (si una mesa tiene reservas asociadas va a fallar por la FK, y está bien:
    //  no queremos borrar mesas con historial. En ese caso avisamos y seguimos.)
    $noEliminadas = [];
    if (!empty($datos['eliminadas'])) {
        $del = $pdo->prepare("DELETE FROM mesa WHERE id = ?");
        foreach ($datos['eliminadas'] as $id) {
            try {
                $del->execute([(int)$id]);
            } catch (PDOException $e) {
                $noEliminadas[] = (int)$id;
            }
        }
    }

    // 2) Actualizar las existentes e insertar las nuevas
    $upd = $pdo->prepare(
        "UPDATE mesa SET numero=?, capacidad=?, pos_x=?, pos_y=?, forma=?, ancho=?, alto=?, rotacion=?
         WHERE id=?"
    );
    $ins = $pdo->prepare(
        "INSERT INTO mesa (numero, capacidad, pos_x, pos_y, forma, ancho, alto, rotacion)
         VALUES (?,?,?,?,?,?,?,?)"
    );

    foreach ($datos['mesas'] as $m) {
        $fila = [
            (int)$m['numero'], (int)$m['capacidad'],
            (float)$m['pos_x'], (float)$m['pos_y'],
            $m['forma'] === 'redonda' ? 'redonda' : 'cuadrada',
            (float)$m['ancho'], (float)$m['alto'], (float)$m['rotacion']
        ];
        if (!empty($m['id'])) {
            $upd->execute([...$fila, (int)$m['id']]);
        } else {
            $ins->execute($fila);
        }
    }

    $pdo->commit();
    echo json_encode(["ok" => true, "no_eliminadas" => $noEliminadas]);
} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(["error" => "No se pudo guardar el plano"]);
}
