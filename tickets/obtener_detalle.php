<?php
session_start();
include '../includes/conexionbd.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

if (!isset($_GET['folio'])) {
    echo json_encode(['success' => false, 'error' => 'Folio no proporcionado']);
    exit();
}

$folio = urldecode($_GET['folio']); // Decodificar URL
$usuario_id = $_SESSION['user_id'];

// Depuración - registrar el folio recibido
error_log("Buscando ticket con folio: " . $folio);

// Obtener detalles completos del ticket
$sql = "SELECT t.folio, t.descripcion, t.fecha_creacion, t.estado, t.prioridad,
               a.nombre AS area, 
               d.nombre AS donde, 
               dd.nombre AS detalle_donde,
               c.nombre_cat AS categoria, 
               s.nombre_sucat AS subcategoria, 
               u.nombre AS usuario_nombre,
               u.email AS usuario_email,
               rs.nombre AS supervisor_nombre,
               re.nombre AS ejecutante_nombre,
               t.id_supervisor, 
               t.id_ejecutante, 
               t.id_ticket,
               DATEDIFF(NOW(), t.fecha_creacion) as dias_transcurridos,
               DATE_FORMAT(t.fecha_creacion, '%d/%m/%Y %H:%i') as fecha_formateada
        FROM tickets t
        LEFT JOIN areas a ON t.area = a.id
        LEFT JOIN donde_ticket d ON t.donde = d.id
        LEFT JOIN detalle_donde_ticket dd ON t.detalle_donde = dd.id
        LEFT JOIN categoria_servicio_ticket c ON t.categoria_id = c.id
        LEFT JOIN subcategorias_ticket s ON t.subcategoria_id = s.id
        LEFT JOIN usuarios u ON t.usuario_id = u.id_Usuario
        LEFT JOIN responsable_sup rs ON t.id_supervisor = rs.id
        LEFT JOIN responsable_ejec re ON t.id_ejecutante = re.id
        WHERE t.folio = :folio 
        AND t.area = (SELECT Id_departamento FROM usuarios WHERE id_Usuario = :usuario_id)";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':folio' => $folio,
    ':usuario_id' => $usuario_id
]);

$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if ($ticket) {
    echo json_encode([
        'success' => true,
        'ticket' => $ticket
    ]);
} else {
    // Si no encuentra con #, intentar sin #
    $folio_sin_hash = ltrim($folio, '#');
    
    if ($folio_sin_hash !== $folio) {
        $stmt->execute([
            ':folio' => $folio_sin_hash,
            ':usuario_id' => $usuario_id
        ]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($ticket) {
            echo json_encode([
                'success' => true,
                'ticket' => $ticket
            ]);
            exit();
        }
    }
    
    // Obtener algunos folios para depuración
    $sql_muestras = "SELECT folio FROM tickets WHERE area = (SELECT Id_departamento FROM usuarios WHERE id_Usuario = ?) LIMIT 5";
    $stmt_muestras = $pdo->prepare($sql_muestras);
    $stmt_muestras->execute([$usuario_id]);
    $muestras = $stmt_muestras->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode([
        'success' => false, 
        'error' => 'Ticket no encontrado',
        'debug' => [
            'folio_buscado' => $folio,
            'folio_sin_hash' => $folio_sin_hash,
            'folios_disponibles' => $muestras
        ]
    ]);
}
?>