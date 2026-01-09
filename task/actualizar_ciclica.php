<?php
session_start();
include '../includes/conexionbd.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

// ID del usuario que modifica
$usuario_id = (int) $_SESSION['user_id'];

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['id']) || !isset($data['estado'])) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit();
}

$rawId = $data['id'];
$estado = trim($data['estado']);

preg_match('/\d+$/', $rawId, $matches);
$tarea_id = isset($matches[0]) ? (int)$matches[0] : null;

if (!$tarea_id || $tarea_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'ID de tarea no válido',
        'id_recibido' => $rawId,
        'id_extraido' => $tarea_id
    ]);
    exit();
}

$estadosPermitidos = ['Pendiente', 'En Proceso', 'Completada', 'Cancelada'];
if (!in_array($estado, $estadosPermitidos, true)) {
    echo json_encode([
        'success' => false,
        'message' => 'Estado no permitido',
        'estado_recibido' => $estado,
        'estados_permitidos' => $estadosPermitidos
    ]);
    exit();
}

try {
    $stmtVerificar = $pdo->prepare("SELECT estado FROM tareas_instancias WHERE id = ?");
    $stmtVerificar->execute([$tarea_id]);

    if ($stmtVerificar->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Tarea no encontrada']);
        exit();
    }

    $estadoActual = $stmtVerificar->fetchColumn();

    if ($estadoActual === $estado) {
        echo json_encode([
            'success' => true,
            'message' => 'El estado ya estaba actualizado',
            'no_changes' => true
        ]);
        exit();
    }

    // Se actualiza asignado_a con el usuario logueado
    $sql = "UPDATE tareas_instancias 
            SET estado = ?, 
                asignado_a = ?, 
                fecha_ultima_actualizacion = NOW() 
            WHERE id = ?";

    $stmt = $pdo->prepare($sql);
    $resultado = $stmt->execute([$estado, $usuario_id, $tarea_id]);

    if ($resultado && $stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Estado actualizado correctamente',
            'id' => $tarea_id,
            'estado_anterior' => $estadoActual,
            'estado_nuevo' => $estado,
            'asignado_a' => $usuario_id
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No se pudo actualizar el estado'
        ]);
    }

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos',
        'error' => $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error general',
        'error' => $e->getMessage()
    ]);
}
?>
