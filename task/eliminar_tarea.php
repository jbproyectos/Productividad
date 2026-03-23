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

if (!$id_tarea) {
    echo json_encode(['success' => false, 'message' => 'ID no válido']);
    exit;
}

try {
    // Soft delete - marcar como inactivo
    $sql = "UPDATE tareas SET activo = 0 WHERE id_tarea = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_tarea]);

    echo json_encode(['success' => true, 'message' => 'Tarea eliminada']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>