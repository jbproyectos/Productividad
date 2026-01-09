<?php
session_start();
include '../../includes/conexionbd.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'Método no permitido']));
}

// Recibir datos
$id_ticket = isset($_POST['id_ticket']) ? trim($_POST['id_ticket']) : '';
$id_ejecutante = isset($_POST['id_ejecutante']) ? intval($_POST['id_ejecutante']) : 0;
$calificacion_atencion = isset($_POST['calificacion_atencion']) ? intval($_POST['calificacion_atencion']) : 0;
$calificacion_tiempo = isset($_POST['calificacion_tiempo']) ? intval($_POST['calificacion_tiempo']) : 0;
$calificacion_solucion = isset($_POST['calificacion_solucion']) ? intval($_POST['calificacion_solucion']) : 0;
$comentario = isset($_POST['comentario']) ? trim($_POST['comentario']) : '';

// Validaciones
if (empty($id_ticket) || $id_ejecutante <= 0) {
    die(json_encode(['success' => false, 'message' => 'Datos inválidos']));
}

if ($calificacion_atencion < 1 || $calificacion_atencion > 5 ||
    $calificacion_tiempo < 1 || $calificacion_tiempo > 5 ||
    $calificacion_solucion < 1 || $calificacion_solucion > 5) {
    die(json_encode(['success' => false, 'message' => 'Calificaciones inválidas']));
}

// Verificar que el ticket existe y esté cerrado
$stmt = $pdo->prepare("SELECT id_ticket, estado FROM tickets WHERE folio = ?");
$stmt->execute([$id_ticket]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket || $ticket['estado'] !== 'Cerrado') {
    die(json_encode(['success' => false, 'message' => 'Ticket no encontrado o no está cerrado']));
}

// ID del usuario que evalúa (desde sesión)
$id_evaluador = $_SESSION['user_id'] ?? 0;
if ($id_evaluador <= 0) {
    die(json_encode(['success' => false, 'message' => 'Usuario no autenticado']));
}

// Verificar si ya existe evaluación
$stmt = $pdo->prepare("SELECT id_evaluacion FROM evaluaciones_tickets WHERE id_ticket = ?");
$stmt->execute([$ticket['id_ticket']]);
$existe_evaluacion = $stmt->fetch();

try {
    if ($existe_evaluacion) {
        // Actualizar evaluación existente
        $stmt = $pdo->prepare("
            UPDATE evaluaciones_tickets 
            SET calificacion_atencion = ?, calificacion_tiempo = ?, calificacion_solucion = ?, comentario = ?
            WHERE id_ticket = ?
        ");
        $stmt->execute([
            $calificacion_atencion,
            $calificacion_tiempo,
            $calificacion_solucion,
            $comentario,
            $ticket['id_ticket']
        ]);
    } else {
        // Insertar nueva evaluación
        $stmt = $pdo->prepare("
            INSERT INTO evaluaciones_tickets 
            (id_ticket, id_ejecutante, id_evaluador, calificacion_atencion, calificacion_tiempo, calificacion_solucion, comentario)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $ticket['id_ticket'],
            $id_ejecutante,
            $id_evaluador,
            $calificacion_atencion,
            $calificacion_tiempo,
            $calificacion_solucion,
            $comentario
        ]);
    }

    echo json_encode(['success' => true, 'message' => 'Evaluación guardada exitosamente']);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error SQL: ' . $e->getMessage()
    ]);
}

?>
