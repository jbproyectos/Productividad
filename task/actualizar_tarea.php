<?php
include '../includes/conexionbd.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// Obtener datos del request
$input = json_decode(file_get_contents('php://input'), true);
$idConFormato = $input['id'] ?? null; // Viene como "tarea_3"
$campo = $input['campo'] ?? null;
$valor = $input['valor'] ?? null;

if (!$idConFormato || !$campo || !$valor) {
    echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
    exit;
}

// Extraer el número del ID (quitar "tarea_" y dejar solo el número)
$tareaId = str_replace('tarea_', '', $idConFormato);

// Verificar que sea un número válido
if (!is_numeric($tareaId)) {
    echo json_encode(['success' => false, 'error' => 'ID de tarea no válido']);
    exit;
}

// Validar campos permitidos
$camposPermitidos = ['id_supervisor', 'id_ejecutante', 'estatus'];
if (!in_array($campo, $camposPermitidos)) {
    echo json_encode(['success' => false, 'error' => 'Campo no permitido']);
    exit;
}

// Validar valores para estatus
if ($campo === 'estatus') {
    $estadosPermitidos = ['Pendiente', 'En Proceso', 'Completada', 'Cancelada'];
    if (!in_array($valor, $estadosPermitidos)) {
        echo json_encode(['success' => false, 'error' => 'Estado no válido']);
        exit;
    }
}

try {
    if ($campo === 'estatus' && $valor === 'En Proceso') {
        // Actualizar estatus y fecha de inicio
        $fechaInicio = date('Y-m-d H:i:s');
        
        $sql = "UPDATE tareas 
                SET estatus = :valor, 
                    fecha_inicio_act = :fecha_inicio
                WHERE id_tarea = :tarea_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':valor' => $valor,
            ':fecha_inicio' => $fechaInicio,
            ':tarea_id' => $tareaId
        ]);
    } elseif ($campo === 'estatus' && $valor === 'Completada') {
        // Registrar fecha de término
        $fechaTermino = date('Y-m-d H:i:s');
        
        $sql = "UPDATE tareas 
                SET estatus = :valor, 
                    fecha_completado = :fecha_termino
                WHERE id_tarea = :tarea_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':valor' => $valor,
            ':fecha_termino' => $fechaTermino,
            ':tarea_id' => $tareaId
        ]);
    } elseif ($campo === 'estatus' && $valor === 'Cancelada') {
        // Registrar fecha de cancelación
        $fechaCancelacion = date('Y-m-d H:i:s');
        
        $sql = "UPDATE tareas 
                SET estatus = :valor, 
                    fecha_cancelacion = :fecha_cancelacion
                WHERE id_tarea = :tarea_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':valor' => $valor,
            ':fecha_cancelacion' => $fechaCancelacion,
            ':tarea_id' => $tareaId
        ]);
    } else {
        // Para cambio a Pendiente o cambios en otros campos
        $sql = "UPDATE tareas SET $campo = :valor WHERE id_tarea = :tarea_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':valor' => $valor,
            ':tarea_id' => $tareaId
        ]);
    }

    // Verificar si se actualizó alguna fila
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No se encontró la tarea o no hubo cambios']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>