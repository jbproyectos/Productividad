<?php
session_start();
header('Content-Type: application/json');
include '../includes/conexionbd.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id_tarea = $data['id'] ?? 0;
$nuevo_estado = $data['estado'] ?? '';

if (!$id_tarea || !$nuevo_estado) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Obtener estado actual y seguimiento
    $stmt = $pdo->prepare("SELECT estatus, seguimiento, codigo_tarea FROM tareas WHERE id_tarea = ?");
    $stmt->execute([$id_tarea]);
    $tarea = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tarea) {
        throw new Exception('Tarea no encontrada');
    }

    // Obtener nombre del usuario
    $stmt = $pdo->prepare("SELECT CONCAT(nombre, ' ', apellido) as nombre FROM usuarios WHERE Id_Usuario = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $usuario_nombre = $stmt->fetchColumn();

    // Actualizar seguimiento
    $seguimiento = json_decode($tarea['seguimiento'], true) ?: [];
    $seguimiento[] = [
        'fecha' => date('Y-m-d H:i:s'),
        'usuario' => $usuario_nombre,
        'accion' => 'cambio_estado',
        'estado_anterior' => $tarea['estatus'],
        'estado_nuevo' => $nuevo_estado,
        'comentario' => "Estado cambiado de {$tarea['estatus']} a {$nuevo_estado}"
    ];

    // Si se completa, registrar fecha
    $fecha_completado = ($nuevo_estado === 'completada') ? date('Y-m-d H:i:s') : null;

    // Actualizar tarea
    $sql = "UPDATE tareas SET 
            estatus = ?, 
            fecha_completado = ?,
            seguimiento = ?
            WHERE id_tarea = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$nuevo_estado, $fecha_completado, json_encode($seguimiento), $id_tarea]);

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Estado actualizado']);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>