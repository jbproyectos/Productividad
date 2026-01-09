<?php
include '../includes/conexionbd.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// Obtener datos del request
$input = json_decode(file_get_contents('php://input'), true);
$ticketId = $input['id'] ?? null;
$campo = $input['campo'] ?? null;
$valor = $input['valor'] ?? null;

if (!$ticketId || !$campo) {
    echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
    exit;
}

// Validar campos permitidos
$camposPermitidos = ['id_supervisor', 'id_ejecutante', 'prioridad', 'estado'];
if (!in_array($campo, $camposPermitidos)) {
    echo json_encode(['success' => false, 'error' => 'Campo no permitido']);
    exit;
}

// Validar valores para estado
if ($campo === 'estado') {
    $estadosPermitidos = ['Pendiente', 'En Proceso', 'Resuelto', 'Cerrado', 'Cancelado'];
    if (!in_array($valor, $estadosPermitidos)) {
        echo json_encode(['success' => false, 'error' => 'Estado no válido']);
        exit;
    }
}

// Función para sumar días hábiles
function sumarDiasHabiles($fechaInicio, $dias)
{
    $fecha = new DateTime($fechaInicio);
    $sumados = 0;

    while ($sumados < $dias) {
        $fecha->modify('+1 day');
        $diaSemana = $fecha->format('N'); // 1 = Lunes, 7 = Domingo
        if ($diaSemana < 6) { // solo cuenta lunes a viernes
            $sumados++;
        }
    }

    return $fecha->format('Y-m-d H:i:s');
}

try {
    if ($campo === 'estado' && $valor === 'En Proceso') {
        // 1️⃣ Obtener la prioridad (número de días)
        $stmtPrioridad = $pdo->prepare("SELECT prioridad FROM tickets WHERE folio = :folio");
        $stmtPrioridad->execute([':folio' => $ticketId]);
        $ticket = $stmtPrioridad->fetch(PDO::FETCH_ASSOC);
        $diasPrioridad = intval($ticket['prioridad'] ?? 0);

        // 2️⃣ Calcular fecha compromiso considerando solo días hábiles
        $fechaInicio = date('Y-m-d H:i:s');
        $fechaCompromiso = ($diasPrioridad > 0) ? sumarDiasHabiles($fechaInicio, $diasPrioridad) : $fechaInicio;

        // 3️⃣ Actualizar el ticket con ambas fechas
        $sql = "UPDATE tickets 
                SET estado = :valor, 
                    fecha_inicio_act = :fecha_inicio, 
                    fecha_compromiso = :fecha_compromiso
                WHERE folio = :ticket_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':valor' => $valor,
            ':fecha_inicio' => $fechaInicio,
            ':fecha_compromiso' => $fechaCompromiso,
            ':ticket_id' => $ticketId
        ]);
    } else {
        // Si solo cambia otro campo (como prioridad o supervisor)
        $sql = "UPDATE tickets SET $campo = :valor WHERE folio = :ticket_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':valor' => $valor,
            ':ticket_id' => $ticketId
        ]);
    }

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
