<?php
session_start();
include '../includes/conexionbd.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$reunion_id = $data['reunion_id'] ?? null;
$campo = $data['campo'] ?? null;
$valor = $data['valor'] ?? null;

if (!$reunion_id || !$campo || !$valor) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit();
}

try {
    // Actualizar reunión
    $stmt = $pdo->prepare("UPDATE reuniones SET $campo = ? WHERE id_reunion = ?");
    $stmt->execute([$valor, $reunion_id]);
    
    echo json_encode(['success' => true, 'message' => 'Reunión actualizada']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>