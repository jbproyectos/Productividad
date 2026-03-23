<?php
session_start();
include '../includes/conexionbd.php';

if (!isset($_SESSION['user_id'])) {
    die('No autorizado');
}

$usuario_id = $_SESSION['user_id'];

// Obtener parámetros de filtro igual que en tickets.php
$where_conditions = ["t.area = (SELECT Id_departamento FROM usuarios WHERE id_Usuario = ?)", "t.usuario_id != ?"];
$params = [$usuario_id, $usuario_id];

if (isset($_GET['filtrar']) && $_GET['filtrar'] == '1') {
    if (!empty($_GET['estado'])) {
        $where_conditions[] = "t.estado = ?";
        $params[] = $_GET['estado'];
    }
    if (!empty($_GET['prioridad'])) {
        $where_conditions[] = "t.prioridad = ?";
        $params[] = $_GET['prioridad'];
    }
    if (!empty($_GET['area'])) {
        $where_conditions[] = "t.area = ?";
        $params[] = $_GET['area'];
    }
    if (!empty($_GET['categoria'])) {
        $where_conditions[] = "t.categoria_id = ?";
        $params[] = $_GET['categoria'];
    }
    if (!empty($_GET['fecha_inicio'])) {
        $where_conditions[] = "DATE(t.fecha_creacion) >= ?";
        $params[] = $_GET['fecha_inicio'];
    }
    if (!empty($_GET['fecha_fin'])) {
        $where_conditions[] = "DATE(t.fecha_creacion) <= ?";
        $params[] = $_GET['fecha_fin'];
    }
    if (!empty($_GET['busqueda'])) {
        $where_conditions[] = "(t.folio LIKE ? OR t.descripcion LIKE ?)";
        $busqueda = "%" . $_GET['busqueda'] . "%";
        $params[] = $busqueda;
        $params[] = $busqueda;
    }
}

$where_clause = implode(" AND ", $where_conditions);

// Obtener datos para exportar
$sql = "SELECT t.folio, t.descripcion, t.fecha_creacion, t.estado, t.prioridad,
               a.nombre AS area, 
               d.nombre AS donde, 
               dd.nombre AS detalle_donde,
               c.nombre_cat AS categoria, 
               s.nombre_sucat AS subcategoria, 
               u.nombre AS solicitante,
               rs.nombre AS supervisor,
               re.nombre AS ejecutante,
               DATEDIFF(NOW(), t.fecha_creacion) as dias_transcurridos
        FROM tickets t
        LEFT JOIN areas a ON t.area = a.id
        LEFT JOIN donde_ticket d ON t.donde = d.id
        LEFT JOIN detalle_donde_ticket dd ON t.detalle_donde = dd.id
        LEFT JOIN categoria_servicio_ticket c ON t.categoria_id = c.id
        LEFT JOIN subcategorias_ticket s ON t.subcategoria_id = s.id
        LEFT JOIN usuarios u ON t.usuario_id = u.id_Usuario
        LEFT JOIN responsable_sup rs ON t.id_supervisor = rs.id
        LEFT JOIN responsable_ejec re ON t.id_ejecutante = re.id
        WHERE $where_clause
        ORDER BY t.fecha_creacion DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Configurar headers para descarga de Excel
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="tickets_' . date('Y-m-d') . '.xls"');

// Escribir archivo Excel (formato HTML simple)
echo '<html>';
echo '<head><meta charset="UTF-8"></head>';
echo '<body>';
echo '<table border="1">';
echo '<thead>';
echo '<tr>';
echo '<th>Folio</th>';
echo '<th>Descripción</th>';
echo '<th>Solicitante</th>';
echo '<th>Área</th>';
echo '<th>Categoría</th>';
echo '<th>Subcategoría</th>';
echo '<th>Ubicación</th>';
echo '<th>Detalle</th>';
echo '<th>Supervisor</th>';
echo '<th>Ejecutante</th>';
echo '<th>Prioridad</th>';
echo '<th>Estado</th>';
echo '<th>Días</th>';
echo '<th>Fecha Creación</th>';
echo '</tr>';
echo '</thead>';
echo '<tbody>';

foreach ($tickets as $ticket) {
    $prioridad_text = '';
    if ($ticket['prioridad'] == '2') $prioridad_text = 'Alta';
    elseif ($ticket['prioridad'] == '7') $prioridad_text = 'Media';
    else $prioridad_text = 'Baja';
    
    echo '<tr>';
    echo '<td>' . htmlspecialchars($ticket['folio']) . '</td>';
    echo '<td>' . htmlspecialchars($ticket['descripcion']) . '</td>';
    echo '<td>' . htmlspecialchars($ticket['solicitante']) . '</td>';
    echo '<td>' . htmlspecialchars($ticket['area']) . '</td>';
    echo '<td>' . htmlspecialchars($ticket['categoria']) . '</td>';
    echo '<td>' . htmlspecialchars($ticket['subcategoria']) . '</td>';
    echo '<td>' . htmlspecialchars($ticket['donde']) . '</td>';
    echo '<td>' . htmlspecialchars($ticket['detalle_donde']) . '</td>';
    echo '<td>' . htmlspecialchars($ticket['supervisor'] ?? 'No asignado') . '</td>';
    echo '<td>' . htmlspecialchars($ticket['ejecutante'] ?? 'No asignado') . '</td>';
    echo '<td>' . $prioridad_text . '</td>';
    echo '<td>' . htmlspecialchars($ticket['estado']) . '</td>';
    echo '<td>' . $ticket['dias_transcurridos'] . '</td>';
    echo '<td>' . date('d/m/Y H:i', strtotime($ticket['fecha_creacion'])) . '</td>';
    echo '</tr>';
}

echo '</tbody>';
echo '</table>';
echo '</body>';
echo '</html>';
?>