<?php
session_start();
include 'includes/conexionbd.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$usuario_id = $_SESSION['user_id'];
$es_admin = false; // Ajusta según tu lógica de roles (rolActual = 1 para admin)

// Verificar si se está viendo otro perfil (solo admin)
$perfil_id = $_GET['id'] ?? $usuario_id;
if ($perfil_id != $usuario_id && !$es_admin) {
    header('Location: perfil.php');
    exit();
}

// Obtener información completa del usuario
$sql_usuario = "SELECT 
    u.Id_Usuario,
    u.nombre,
    u.apellido,
    u.email,
    u.whatsapp,
    u.fechaRegistro,
    u.estatu,
    u.rolActual,
    u.Id_puesto,
    u.Id_departamento,
    u.Id_oficina,
    u.subarea,
    a.nombre as area_nombre,
    p.nombre as puesto_nombre,
    o.nombre as oficina_nombre
FROM usuarios u 
LEFT JOIN areas a ON u.Id_departamento = a.id 
LEFT JOIN puestos p ON u.Id_puesto = p.Id_puesto
LEFT JOIN oficinas o ON u.Id_oficina = o.id
WHERE u.Id_Usuario = :usuario_id";

$stmt_usuario = $pdo->prepare($sql_usuario);
$stmt_usuario->execute([':usuario_id' => $perfil_id]);
$usuario = $stmt_usuario->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    die("Usuario no encontrado.");
}

// Parámetros de filtro de período
$periodo = $_GET['periodo'] ?? 'mes';
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');

switch ($periodo) {
    case 'hoy':
        $fecha_inicio = date('Y-m-d');
        $fecha_fin = date('Y-m-d');
        break;
    case 'semana':
        $fecha_inicio = date('Y-m-d', strtotime('monday this week'));
        $fecha_fin = date('Y-m-d', strtotime('sunday this week'));
        break;
    case 'mes':
        $fecha_inicio = date('Y-m-01');
        $fecha_fin = date('Y-m-t');
        break;
    case 'trimestre':
        $trimestre = ceil(date('n') / 3);
        $fecha_inicio = date('Y-' . (($trimestre - 1) * 3 + 1) . '-01');
        $fecha_fin = date('Y-m-t', strtotime($fecha_inicio . ' +2 months'));
        break;
    case 'semestre':
        $semestre = ceil(date('n') / 6);
        $fecha_inicio = date('Y-' . (($semestre - 1) * 6 + 1) . '-01');
        $fecha_fin = date('Y-m-t', strtotime($fecha_inicio . ' +5 months'));
        break;
    case 'año':
        $fecha_inicio = date('Y-01-01');
        $fecha_fin = date('Y-12-31');
        break;
}

// ========== ESTADÍSTICAS DE TAREAS ÚNICAS ==========
$sql_tareas_unicas = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN estatus = 'completada' THEN 1 ELSE 0 END) as completadas,
    SUM(CASE WHEN estatus = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
    SUM(CASE WHEN estatus = 'en_proceso' THEN 1 ELSE 0 END) as en_proceso,
    SUM(CASE WHEN estatus = 'cancelada' THEN 1 ELSE 0 END) as canceladas,
    SUM(CASE WHEN prioridad = 'critica' THEN 1 ELSE 0 END) as criticas,
    SUM(CASE WHEN prioridad = 'urgente' THEN 1 ELSE 0 END) as urgentes,
    SUM(CASE WHEN prioridad = 'alta' THEN 1 ELSE 0 END) as altas,
    SUM(CASE WHEN prioridad = 'media' THEN 1 ELSE 0 END) as medias,
    SUM(CASE WHEN prioridad = 'baja' THEN 1 ELSE 0 END) as bajas,
    SUM(CASE WHEN fecha_limite < NOW() AND estatus NOT IN ('completada', 'cancelada') THEN 1 ELSE 0 END) as vencidas
FROM tareas 
WHERE id_responsable_ejecucion = :user_id 
AND activo = 1
AND DATE(fecha_creacion) BETWEEN :fecha_inicio AND :fecha_fin";

$stmt = $pdo->prepare($sql_tareas_unicas);
$stmt->execute([
    ':user_id' => $perfil_id,
    ':fecha_inicio' => $fecha_inicio,
    ':fecha_fin' => $fecha_fin
]);
$stats_tareas_unicas = $stmt->fetch(PDO::FETCH_ASSOC);

// Calcular eficiencia
$eficiencia_tareas = 0;
if ($stats_tareas_unicas['total'] > 0) {
    $eficiencia_tareas = round(($stats_tareas_unicas['completadas'] / $stats_tareas_unicas['total']) * 100, 2);
}

// ========== ESTADÍSTICAS DE TAREAS CÍCLICAS ==========
$sql_tareas_ciclicas = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN estado = 'Completada' THEN 1 ELSE 0 END) as completadas,
    SUM(CASE WHEN estado = 'Pendiente' THEN 1 ELSE 0 END) as pendientes,
    SUM(CASE WHEN estado = 'En Proceso' THEN 1 ELSE 0 END) as en_proceso,
    SUM(CASE WHEN estado = 'Cancelada' THEN 1 ELSE 0 END) as canceladas,
    SUM(CASE WHEN fecha < CURDATE() AND estado != 'Completada' THEN 1 ELSE 0 END) as vencidas
FROM tareas_instancias 
WHERE asignado_a = :user_id
AND fecha BETWEEN :fecha_inicio AND :fecha_fin";

$stmt = $pdo->prepare($sql_tareas_ciclicas);
$stmt->execute([
    ':user_id' => $perfil_id,
    ':fecha_inicio' => $fecha_inicio,
    ':fecha_fin' => $fecha_fin
]);
$stats_tareas_ciclicas = $stmt->fetch(PDO::FETCH_ASSOC);

// Calcular eficiencia
$eficiencia_ciclicas = 0;
if ($stats_tareas_ciclicas['total'] > 0) {
    $eficiencia_ciclicas = round(($stats_tareas_ciclicas['completadas'] / $stats_tareas_ciclicas['total']) * 100, 2);
}

// ========== ESTADÍSTICAS DE TICKETS ==========
$sql_tickets = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN estado = 'completado' THEN 1 ELSE 0 END) as completados,
    SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
    SUM(CASE WHEN estado = 'en_proceso' THEN 1 ELSE 0 END) as en_proceso,
    SUM(CASE WHEN estado = 'cancelado' THEN 1 ELSE 0 END) as cancelados,
    SUM(CASE WHEN prioridad = 'critica' THEN 1 ELSE 0 END) as criticos,
    SUM(CASE WHEN prioridad = 'alta' THEN 1 ELSE 0 END) as altos
FROM tickets 
WHERE id_ejecutante = :user_id
AND DATE(fecha_creacion) BETWEEN :fecha_inicio AND :fecha_fin";

$stmt = $pdo->prepare($sql_tickets);
$stmt->execute([
    ':user_id' => $perfil_id,
    ':fecha_inicio' => $fecha_inicio,
    ':fecha_fin' => $fecha_fin
]);
$stats_tickets = $stmt->fetch(PDO::FETCH_ASSOC);

// Calcular eficiencia
$eficiencia_tickets = 0;
if ($stats_tickets['total'] > 0) {
    $eficiencia_tickets = round(($stats_tickets['completados'] / $stats_tickets['total']) * 100, 2);
}

// Productividad global
$total_actividades = ($stats_tareas_unicas['total'] ?? 0) + ($stats_tareas_ciclicas['total'] ?? 0) + ($stats_tickets['total'] ?? 0);
$total_completadas = ($stats_tareas_unicas['completadas'] ?? 0) + ($stats_tareas_ciclicas['completadas'] ?? 0) + ($stats_tickets['completados'] ?? 0);
$eficiencia_global = $total_actividades > 0 ? round(($total_completadas / $total_actividades) * 100, 2) : 0;

// ========== DATOS PARA GRÁFICAS MENSUALES ==========
$sql_mensual = "SELECT 
    DATE_FORMAT(fecha, '%Y-%m') as mes,
    DATE_FORMAT(fecha, '%M %Y') as mes_nombre,
    SUM(CASE WHEN tipo = 'tarea' AND completada = 1 THEN 1 ELSE 0 END) as tareas_completadas,
    SUM(CASE WHEN tipo = 'tarea_ciclica' AND completada = 1 THEN 1 ELSE 0 END) as ciclicas_completadas,
    SUM(CASE WHEN tipo = 'ticket' AND completado = 1 THEN 1 ELSE 0 END) as tickets_completados
FROM (
    SELECT 
        fecha_creacion as fecha,
        'tarea' as tipo,
        CASE WHEN estatus = 'completada' THEN 1 ELSE 0 END as completada,
        NULL as completado
    FROM tareas 
    WHERE id_responsable_ejecucion = :user_id1
    AND fecha_creacion >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    
    UNION ALL
    
    SELECT 
        fecha as fecha,
        'tarea_ciclica' as tipo,
        CASE WHEN estado = 'Completada' THEN 1 ELSE 0 END as completada,
        NULL as completado
    FROM tareas_instancias 
    WHERE asignado_a = :user_id2
    AND fecha >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    
    UNION ALL
    
    SELECT 
        fecha_creacion as fecha,
        'ticket' as tipo,
        NULL as completada,
        CASE WHEN estado = 'completado' THEN 1 ELSE 0 END as completado
    FROM tickets 
    WHERE id_ejecutante = :user_id3
    AND fecha_creacion >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
) AS combined
GROUP BY DATE_FORMAT(fecha, '%Y-%m')
ORDER BY mes ASC";

$stmt = $pdo->prepare($sql_mensual);
$stmt->execute([
    ':user_id1' => $perfil_id,
    ':user_id2' => $perfil_id,
    ':user_id3' => $perfil_id
]);
$datos_mensuales = $stmt->fetchAll(PDO::FETCH_ASSOC);

//var_dump($datos_mensuales);

// ========== DATOS POR PRIORIDAD ==========
$prioridades = [
    'critica' => ($stats_tareas_unicas['criticas'] ?? 0) + ($stats_tickets['criticos'] ?? 0),
    'urgente' => $stats_tareas_unicas['urgentes'] ?? 0,
    'alta' => ($stats_tareas_unicas['altas'] ?? 0) + ($stats_tickets['altos'] ?? 0),
    'media' => $stats_tareas_unicas['medias'] ?? 0,
    'baja' => $stats_tareas_unicas['bajas'] ?? 0
];

// ========== ÚLTIMAS ACTIVIDADES ==========
$sql_actividades = "(SELECT 
    'tarea' as tipo,
    CAST(t.codigo_tarea AS CHAR) as referencia,
    CAST(t.titulo AS CHAR) as descripcion,
    CAST(t.estatus AS CHAR) as estado,
    t.fecha_creacion as fecha,
    CAST(t.prioridad AS CHAR) as prioridad,
    CAST('Ejecución' AS CHAR) as rol
FROM tareas t 
WHERE t.id_responsable_ejecucion = :user_id1
AND t.activo = 1
ORDER BY t.fecha_creacion DESC
LIMIT 10)
UNION ALL
(SELECT 
    'tarea_ciclica' as tipo,
    CAST(CONCAT('CIC-', ti.id) AS CHAR) as referencia,
    CAST(ti.actividad AS CHAR) as descripcion,
    CAST(ti.estado AS CHAR) as estado,
    CAST(CONCAT(ti.fecha, ' ', COALESCE(ti.hora, '00:00:00')) AS DATETIME) as fecha,
    CAST(COALESCE(ti.prioridad, 'media') AS CHAR) as prioridad,
    CAST('Ejecución' AS CHAR) as rol
FROM tareas_instancias ti 
WHERE ti.asignado_a = :user_id2
ORDER BY ti.fecha DESC
LIMIT 10)
UNION ALL
(SELECT 
    'ticket' as tipo,
    CAST(tk.folio AS CHAR) as referencia,
    CAST(tk.descripcion AS CHAR) as descripcion,
    CAST(tk.estado AS CHAR) as estado,
    tk.fecha_creacion as fecha,
    CAST(COALESCE(tk.prioridad, 'media') AS CHAR) as prioridad,
    CAST('Ejecutante' AS CHAR) as rol
FROM tickets tk 
WHERE tk.id_ejecutante = :user_id3
ORDER BY tk.fecha_creacion DESC
LIMIT 10)
ORDER BY fecha DESC
LIMIT 20";

$stmt = $pdo->prepare($sql_actividades);
$stmt->execute([
    ':user_id1' => $perfil_id,
    ':user_id2' => $perfil_id,
    ':user_id3' => $perfil_id
]);
$actividades = $stmt->fetchAll(PDO::FETCH_ASSOC);
//var_dump($actividades);


// ========== TAREAS ASIGNADAS ==========
$sql_tareas_asignadas = "SELECT 
    t.*,
    c.nombre_cat as categoria_nombre,
    a.nombre as area_nombre
FROM tareas t
LEFT JOIN categoria_servicio_ticket c ON t.id_categoria = c.id
LEFT JOIN areas a ON t.id_area = a.id
WHERE t.id_responsable_ejecucion = :user_id
AND t.activo = 1
AND t.estatus IN ('pendiente', 'en_proceso')
ORDER BY t.fecha_limite ASC, t.fecha_creacion DESC
LIMIT 10";

$stmt = $pdo->prepare($sql_tareas_asignadas);
$stmt->execute([':user_id' => $perfil_id]);
$tareas_asignadas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ========== TICKETS ASIGNADOS ==========
$sql_tickets_asignados = "SELECT 
    t.*,
    c.nombre_cat as categoria_nombre
FROM tickets t
LEFT JOIN categoria_servicio_ticket c ON t.categoria_id = c.id
WHERE t.id_ejecutante = :user_id
AND t.estado IN ('pendiente', 'en_proceso')
ORDER BY t.prioridad DESC, t.fecha_creacion ASC
LIMIT 10";

$stmt = $pdo->prepare($sql_tickets_asignados);
$stmt->execute([':user_id' => $perfil_id]);
$tickets_asignados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Iniciales para avatar
$iniciales = strtoupper(substr($usuario['nombre'] ?? 'U', 0, 1) . substr($usuario['apellido'] ?? 'S', 0, 1));
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flow | Perfil de <?= htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <style>
        .stat-card {
            transition: all 0.3s ease;
            border: 1px solid #e2e8f0;
            background: white;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .progress-bar {
            height: 8px;
            border-radius: 4px;
            background: #e2e8f0;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            transition: width 0.5s ease;
        }

        .badge-pendiente,
        .badge-Pendiente {
            background: #FEF3C7;
            color: #92400E;
        }

        .badge-en_proceso,
        .badge-En Proceso {
            background: #DBEAFE;
            color: #1E40AF;
        }

        .badge-completada,
        .badge-Completada {
            background: #D1FAE5;
            color: #065F46;
        }

        .badge-cancelada,
        .badge-Cancelada {
            background: #FEE2E2;
            color: #991B1B;
        }

        .badge-critica {
            background: #7F1D1D;
            color: #FEE2E2;
        }

        .badge-urgente {
            background: #DC2626;
            color: white;
        }

        .badge-alta {
            background: #F97316;
            color: white;
        }

        .badge-media {
            background: #3B82F6;
            color: white;
        }

        .badge-baja {
            background: #6B7280;
            color: white;
        }

        .tab-button.active {
            border-bottom: 3px solid #3B82F6;
            color: #3B82F6;
            font-weight: 600;
        }

        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .period-selector {
            transition: all 0.2s ease;
        }

        .period-selector:hover {
            background: #f1f5f9;
        }

        .period-selector.active {
            background: #3B82F6;
            color: white;
        }


        
    </style>
</head>

<body class="bg-gray-50 font-sans">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <?php include 'includes/menu.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 p-4 lg:p-8 transition-all duration-300">
            <!-- Header con información básica -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 mb-6 fade-in">
                <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
                    <div class="flex items-center gap-4">
                        <div class="w-20 h-20 bg-gradient-to-br from-blue-500 to-purple-600 rounded-2xl flex items-center justify-center text-white text-2xl font-bold shadow-lg">
                            <?= $iniciales ?>
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900"><?= htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']) ?></h1>
                            <div class="flex flex-wrap items-center gap-3 mt-2">
                                <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-medium">
                                    <i class="fas fa-briefcase mr-1"></i><?= htmlspecialchars($usuario['puesto_nombre'] ?? 'Sin puesto') ?>
                                </span>
                                <span class="bg-purple-100 text-purple-800 px-3 py-1 rounded-full text-sm font-medium">
                                    <i class="fas fa-building mr-1"></i><?= htmlspecialchars($usuario['area_nombre'] ?? 'Sin área') ?>
                                </span>
                                <?php if ($usuario['oficina_nombre']): ?>
                                    <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-medium">
                                        <i class="fas fa-map-marker-alt mr-1"></i><?= htmlspecialchars($usuario['oficina_nombre']) ?>
                                    </span>
                                <?php endif; ?>
                                <span class="bg-gray-100 text-gray-800 px-3 py-1 rounded-full text-sm font-medium">
                                    <i class="fas fa-circle mr-1 <?= $usuario['estatu'] == 'activo' ? 'text-green-500' : 'text-red-500' ?>"></i>
                                    <?= ucfirst($usuario['estatu'] ?? 'activo') ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <?php if ($perfil_id == $usuario_id || $es_admin): ?>
                            <button onclick="abrirModalEditar()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                <i class="fas fa-edit mr-2"></i>Editar Perfil
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Selector de período -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4 mb-6 fade-in">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-calendar-alt text-gray-400"></i>
                        <span class="text-sm font-medium text-gray-700">Período:</span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="?id=<?= $perfil_id ?>&periodo=hoy" class="period-selector px-4 py-2 rounded-lg text-sm font-medium <?= $periodo == 'hoy' ? 'active' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">Hoy</a>
                        <a href="?id=<?= $perfil_id ?>&periodo=semana" class="period-selector px-4 py-2 rounded-lg text-sm font-medium <?= $periodo == 'semana' ? 'active' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">Semana</a>
                        <a href="?id=<?= $perfil_id ?>&periodo=mes" class="period-selector px-4 py-2 rounded-lg text-sm font-medium <?= $periodo == 'mes' ? 'active' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">Mes</a>
                        <a href="?id=<?= $perfil_id ?>&periodo=trimestre" class="period-selector px-4 py-2 rounded-lg text-sm font-medium <?= $periodo == 'trimestre' ? 'active' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">Trimestre</a>
                        <a href="?id=<?= $perfil_id ?>&periodo=semestre" class="period-selector px-4 py-2 rounded-lg text-sm font-medium <?= $periodo == 'semestre' ? 'active' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">Semestre</a>
                        <a href="?id=<?= $perfil_id ?>&periodo=año" class="period-selector px-4 py-2 rounded-lg text-sm font-medium <?= $periodo == 'año' ? 'active' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">Año</a>
                    </div>
                    <div class="text-sm text-gray-500">
                        <i class="far fa-calendar mr-1"></i><?= date('d/m/Y', strtotime($fecha_inicio)) ?> - <?= date('d/m/Y', strtotime($fecha_fin)) ?>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                <!-- Productividad Global -->
                <div class="stat-card rounded-2xl p-6 fade-in">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-chart-line text-blue-600 text-xl"></i>
                        </div>
                        <span class="text-3xl font-bold text-gray-900"><?= $eficiencia_global ?>%</span>
                    </div>
                    <h3 class="text-sm font-medium text-gray-500 mb-1">Productividad Global</h3>
                    <div class="progress-bar">
                        <div class="progress-fill bg-blue-600" style="width: <?= $eficiencia_global ?>%"></div>
                    </div>
                    <p class="text-xs text-gray-400 mt-2">
                        <i class="far fa-clock mr-1"></i><?= $total_completadas ?> de <?= $total_actividades ?> actividades
                    </p>
                </div>

                <!-- Tareas Únicas -->
                <div class="stat-card rounded-2xl p-6 fade-in">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-tasks text-green-600 text-xl"></i>
                        </div>
                        <span class="text-3xl font-bold text-gray-900"><?= $stats_tareas_unicas['total'] ?? 0 ?></span>
                    </div>
                    <h3 class="text-sm font-medium text-gray-500 mb-1">Tareas Únicas</h3>
                    <div class="flex justify-between text-sm mb-2">
                        <span class="text-green-600"><?= $stats_tareas_unicas['completadas'] ?? 0 ?> completadas</span>
                        <span class="text-yellow-600"><?= $stats_tareas_unicas['pendientes'] ?? 0 ?> pendientes</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill bg-green-500" style="width: <?= $eficiencia_tareas ?>%"></div>
                    </div>
                    <p class="text-xs text-gray-400 mt-2">
                        <i class="fas fa-exclamation-triangle text-red-400 mr-1"></i><?= $stats_tareas_unicas['vencidas'] ?? 0 ?> vencidas
                    </p>
                </div>

                <!-- Tareas Cíclicas -->
                <div class="stat-card rounded-2xl p-6 fade-in">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-redo-alt text-purple-600 text-xl"></i>
                        </div>
                        <span class="text-3xl font-bold text-gray-900"><?= $stats_tareas_ciclicas['total'] ?? 0 ?></span>
                    </div>
                    <h3 class="text-sm font-medium text-gray-500 mb-1">Tareas Cíclicas</h3>
                    <div class="flex justify-between text-sm mb-2">
                        <span class="text-green-600"><?= $stats_tareas_ciclicas['completadas'] ?? 0 ?> completadas</span>
                        <span class="text-yellow-600"><?= $stats_tareas_ciclicas['pendientes'] ?? 0 ?> pendientes</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill bg-purple-500" style="width: <?= $eficiencia_ciclicas ?>%"></div>
                    </div>
                    <p class="text-xs text-gray-400 mt-2">
                        <i class="fas fa-exclamation-triangle text-red-400 mr-1"></i><?= $stats_tareas_ciclicas['vencidas'] ?? 0 ?> vencidas
                    </p>
                </div>

                <!-- Tickets -->
                <div class="stat-card rounded-2xl p-6 fade-in">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 bg-orange-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-ticket-alt text-orange-600 text-xl"></i>
                        </div>
                        <span class="text-3xl font-bold text-gray-900"><?= $stats_tickets['total'] ?? 0 ?></span>
                    </div>
                    <h3 class="text-sm font-medium text-gray-500 mb-1">Tickets</h3>
                    <div class="flex justify-between text-sm mb-2">
                        <span class="text-green-600"><?= $stats_tickets['completados'] ?? 0 ?> resueltos</span>
                        <span class="text-yellow-600"><?= $stats_tickets['pendientes'] ?? 0 ?> abiertos</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill bg-orange-500" style="width: <?= $eficiencia_tickets ?>%"></div>
                    </div>
                </div>
            </div>

            <!-- Gráficas -->
            <!-- Gráfica de Rendimiento Mensual -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 fade-in">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Rendimiento Mensual</h3>
                <div id="graficaMensual" style="width:100%; height:200px;"></div>
            </div>

            <!-- Gráfica de Distribución por Prioridad -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 fade-in">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Distribución por Prioridad</h3>
                <div id="graficaPrioridades" style="width:100%; height:200px;"></div>
            </div>

            <!-- Tabs de navegación -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden fade-in">
                <div class="border-b border-gray-200">
                    <nav class="flex overflow-x-auto px-6">
                        <button onclick="cambiarTab('resumen')" class="tab-button px-4 py-3 text-sm font-medium text-gray-600 hover:text-blue-600 border-b-2 border-transparent <?= !isset($_GET['tab']) || $_GET['tab'] == 'resumen' ? 'active' : '' ?>" data-tab="resumen">
                            <i class="fas fa-chart-pie mr-2"></i>Resumen
                        </button>
                        <button onclick="cambiarTab('tareas')" class="tab-button px-4 py-3 text-sm font-medium text-gray-600 hover:text-blue-600 border-b-2 border-transparent <?= ($_GET['tab'] ?? '') == 'tareas' ? 'active' : '' ?>" data-tab="tareas">
                            <i class="fas fa-tasks mr-2"></i>Tareas Asignadas
                        </button>
                        <button onclick="cambiarTab('tickets')" class="tab-button px-4 py-3 text-sm font-medium text-gray-600 hover:text-blue-600 border-b-2 border-transparent <?= ($_GET['tab'] ?? '') == 'tickets' ? 'active' : '' ?>" data-tab="tickets">
                            <i class="fas fa-ticket-alt mr-2"></i>Tickets
                        </button>
                        <button onclick="cambiarTab('actividades')" class="tab-button px-4 py-3 text-sm font-medium text-gray-600 hover:text-blue-600 border-b-2 border-transparent <?= ($_GET['tab'] ?? '') == 'actividades' ? 'active' : '' ?>" data-tab="actividades">
                            <i class="fas fa-history mr-2"></i>Actividades Recientes
                        </button>
                        <?php if ($perfil_id == $usuario_id || $es_admin): ?>
                            <button onclick="cambiarTab('configuracion')" class="tab-button px-4 py-3 text-sm font-medium text-gray-600 hover:text-blue-600 border-b-2 border-transparent <?= ($_GET['tab'] ?? '') == 'configuracion' ? 'active' : '' ?>" data-tab="configuracion">
                                <i class="fas fa-cog mr-2"></i>Configuración
                            </button>
                        <?php endif; ?>
                    </nav>
                </div>

                <div class="p-6">
                    <!-- Tab Resumen -->
                    <div id="tab-resumen" class="tab-content <?= !isset($_GET['tab']) || $_GET['tab'] == 'resumen' ? '' : 'hidden' ?>">
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            <!-- Información Personal -->
                            <div class="col-span-1">
                                <h4 class="font-semibold text-gray-900 mb-4">Información Personal</h4>
                                <dl class="space-y-3">
                                    <div class="flex justify-between py-2 border-b border-gray-100">
                                        <dt class="text-sm text-gray-500">Nombre completo</dt>
                                        <dd class="text-sm font-medium text-gray-900"><?= htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']) ?></dd>
                                    </div>
                                    <div class="flex justify-between py-2 border-b border-gray-100">
                                        <dt class="text-sm text-gray-500">Email</dt>
                                        <dd class="text-sm font-medium text-gray-900"><?= htmlspecialchars($usuario['email'] ?? 'No especificado') ?></dd>
                                    </div>
                                    <div class="flex justify-between py-2 border-b border-gray-100">
                                        <dt class="text-sm text-gray-500">WhatsApp</dt>
                                        <dd class="text-sm font-medium text-gray-900"><?= htmlspecialchars($usuario['whatsapp'] ?? 'No especificado') ?></dd>
                                    </div>
                                    <div class="flex justify-between py-2 border-b border-gray-100">
                                        <dt class="text-sm text-gray-500">Fecha registro</dt>
                                        <dd class="text-sm font-medium text-gray-900"><?= date('d/m/Y', strtotime($usuario['fechaRegistro'])) ?></dd>
                                    </div>
                                    <div class="flex justify-between py-2 border-b border-gray-100">
                                        <dt class="text-sm text-gray-500">Puesto</dt>
                                        <dd class="text-sm font-medium text-gray-900"><?= htmlspecialchars($usuario['puesto_nombre'] ?? 'N/A') ?></dd>
                                    </div>
                                    <div class="flex justify-between py-2 border-b border-gray-100">
                                        <dt class="text-sm text-gray-500">Departamento</dt>
                                        <dd class="text-sm font-medium text-gray-900"><?= htmlspecialchars($usuario['area_nombre'] ?? 'N/A') ?></dd>
                                    </div>
                                    <?php if ($usuario['subarea']): ?>
                                        <div class="flex justify-between py-2 border-b border-gray-100">
                                            <dt class="text-sm text-gray-500">Subárea</dt>
                                            <dd class="text-sm font-medium text-gray-900"><?= htmlspecialchars($usuario['subarea']) ?></dd>
                                        </div>
                                    <?php endif; ?>
                                </dl>
                            </div>

                            <!-- Estadísticas Rápidas -->
                            <div class="col-span-2">
                                <h4 class="font-semibold text-gray-900 mb-4">Estadísticas del Período</h4>
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="bg-gray-50 rounded-xl p-4">
                                        <div class="text-2xl font-bold text-blue-600"><?= $total_actividades ?></div>
                                        <div class="text-sm text-gray-600">Actividades totales</div>
                                    </div>
                                    <div class="bg-gray-50 rounded-xl p-4">
                                        <div class="text-2xl font-bold text-green-600"><?= $total_completadas ?></div>
                                        <div class="text-sm text-gray-600">Completadas</div>
                                    </div>
                                    <div class="bg-gray-50 rounded-xl p-4">
                                        <div class="text-2xl font-bold text-yellow-600"><?= ($stats_tareas_unicas['pendientes'] ?? 0) + ($stats_tareas_ciclicas['pendientes'] ?? 0) + ($stats_tickets['pendientes'] ?? 0) ?></div>
                                        <div class="text-sm text-gray-600">Pendientes</div>
                                    </div>
                                    <div class="bg-gray-50 rounded-xl p-4">
                                        <div class="text-2xl font-bold text-red-600"><?= ($stats_tareas_unicas['vencidas'] ?? 0) + ($stats_tareas_ciclicas['vencidas'] ?? 0) ?></div>
                                        <div class="text-sm text-gray-600">Vencidas</div>
                                    </div>
                                </div>
                                <div class="mt-4 grid grid-cols-3 gap-4">
                                    <div class="text-center">
                                        <div class="text-sm font-medium text-gray-500">Críticas</div>
                                        <div class="text-lg font-semibold text-red-600"><?= $prioridades['critica'] ?></div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-sm font-medium text-gray-500">Urgentes</div>
                                        <div class="text-lg font-semibold text-orange-600"><?= $prioridades['urgente'] ?></div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-sm font-medium text-gray-500">Alta</div>
                                        <div class="text-lg font-semibold text-yellow-600"><?= $prioridades['alta'] ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab Tareas -->
                    <div id="tab-tareas" class="tab-content <?= ($_GET['tab'] ?? '') == 'tareas' ? '' : 'hidden' ?>">
                        <h4 class="font-semibold text-gray-900 mb-4">Tareas Asignadas</h4>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Código</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Título</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Prioridad</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha Límite</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php if (!empty($tareas_asignadas)): ?>
                                        <?php foreach ($tareas_asignadas as $tarea): ?>
                                            <tr>
                                                <td class="px-4 py-3 text-sm font-mono text-blue-600"><?= htmlspecialchars($tarea['codigo_tarea']) ?></td>
                                                <td class="px-4 py-3 text-sm text-gray-900"><?= htmlspecialchars(substr($tarea['descripcion'], 0, 50)) ?>...</td>
                                                <td class="px-4 py-3">
                                                    <span class="badge-<?= $tarea['prioridad'] ?> px-2 py-1 rounded-full text-xs">
                                                        <?= ucfirst($tarea['prioridad'] ?? 'media') ?>
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <span class="badge-<?= $tarea['estatus'] ?> px-2 py-1 rounded-full text-xs">
                                                        <?= str_replace('_', ' ', ucfirst($tarea['estatus'] ?? '')) ?>
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3 text-sm <?= strtotime($tarea['fecha_limite']) < time() ? 'text-red-600' : 'text-gray-500' ?>">
                                                    <?= $tarea['fecha_limite'] ? date('d/m/Y', strtotime($tarea['fecha_limite'])) : '-' ?>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <button onclick="verTarea(<?= $tarea['id_tarea'] ?>)" class="text-blue-600 hover:text-blue-800">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                                No hay tareas asignadas en este período
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Tab Tickets -->
                    <div id="tab-tickets" class="tab-content <?= ($_GET['tab'] ?? '') == 'tickets' ? '' : 'hidden' ?>">
                        <h4 class="font-semibold text-gray-900 mb-4">Tickets Asignados</h4>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Folio</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Descripción</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Prioridad</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha Creación</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php if (!empty($tickets_asignados)): ?>
                                        <?php foreach ($tickets_asignados as $ticket): ?>
                                            <tr>
                                                <td class="px-4 py-3 text-sm font-mono text-blue-600"><?= htmlspecialchars($ticket['folio']) ?></td>
                                                <td class="px-4 py-3 text-sm text-gray-900"><?= htmlspecialchars(substr($ticket['descripcion'], 0, 50)) ?>...</td>
                                                <td class="px-4 py-3">
                                                    <span class="badge-<?= $ticket['prioridad'] ?> px-2 py-1 rounded-full text-xs">
                                                        <?= ucfirst($ticket['prioridad'] ?? 'media') ?>
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <span class="badge-<?= $ticket['estado'] ?> px-2 py-1 rounded-full text-xs">
                                                        <?= ucfirst(str_replace('_', ' ', $ticket['estado'] ?? '')) ?>
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-500"><?= date('d/m/Y', strtotime($ticket['fecha_creacion'])) ?></td>
                                                <td class="px-4 py-3">
                                                    <button onclick="verTicket(<?= $ticket['id_ticket'] ?>)" class="text-blue-600 hover:text-blue-800">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                                No hay tickets asignados en este período
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Tab Actividades -->
                    <div id="tab-actividades" class="tab-content <?= ($_GET['tab'] ?? '') == 'actividades' ? '' : 'hidden' ?>">
                        <h4 class="font-semibold text-gray-900 mb-4">Actividades Recientes</h4>
                        <div class="space-y-4">
                            <?php if (!empty($actividades)): ?>
                                <?php foreach ($actividades as $act): ?>
                                    <div class="flex items-start gap-4 p-4 bg-gray-50 rounded-xl">
                                        <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0">
                                            <i class="fas fa-<?= $act['tipo'] == 'tarea' ? 'tasks' : ($act['tipo'] == 'ticket' ? 'ticket-alt' : 'redo-alt') ?> text-blue-600"></i>
                                        </div>
                                        <div class="flex-1">
                                            <div class="flex items-center gap-2 mb-1">
                                                <span class="text-xs font-mono text-blue-600"><?= htmlspecialchars($act['referencia']) ?></span>
                                                <span class="text-xs bg-gray-200 text-gray-700 px-2 py-0.5 rounded-full"><?= $act['rol'] ?></span>
                                            </div>
                                            <p class="text-sm text-gray-900"><?= htmlspecialchars(substr($act['descripcion'], 0, 100)) ?>...</p>
                                            <div class="flex items-center gap-3 mt-2">
                                                <span class="text-xs text-gray-500">
                                                    <i class="far fa-clock mr-1"></i><?= date('d/m/Y H:i', strtotime($act['fecha'])) ?>
                                                </span>
                                                <span class="badge-<?= $act['estado'] ?> px-2 py-0.5 rounded-full text-xs">
                                                    <?= ucfirst(str_replace('_', ' ', $act['estado'] ?? '')) ?>
                                                </span>
                                                <?php if ($act['prioridad']): ?>
                                                    <span class="badge-<?= $act['prioridad'] ?> px-2 py-0.5 rounded-full text-xs">
                                                        <?= ucfirst($act['prioridad'] ?? '') ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-center text-gray-500 py-8">No hay actividades recientes</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Tab Configuración -->
                    <?php if ($perfil_id == $usuario_id || $es_admin): ?>
                        <div id="tab-configuracion" class="tab-content <?= ($_GET['tab'] ?? '') == 'configuracion' ? '' : 'hidden' ?>">
                            <h4 class="font-semibold text-gray-900 mb-4">Configuración de Perfil</h4>
                            <form id="formConfiguracion" class="space-y-6" onsubmit="guardarConfiguracion(event)">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Nombre</label>
                                        <input type="text" name="nombre" value="<?= htmlspecialchars($usuario['nombre']) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2" required>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Apellido</label>
                                        <input type="text" name="apellido" value="<?= htmlspecialchars($usuario['apellido']) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2" required>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                                        <input type="email" name="email" value="<?= htmlspecialchars($usuario['email'] ?? '') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2" required>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">WhatsApp</label>
                                        <input type="text" name="whatsapp" value="<?= htmlspecialchars($usuario['whatsapp'] ?? '') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Cambiar contraseña</label>
                                        <input type="password" name="password" placeholder="Nueva contraseña" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Confirmar contraseña</label>
                                        <input type="password" name="confirm_password" placeholder="Confirmar contraseña" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                                    </div>
                                </div>
                                <div class="flex justify-end gap-3">
                                    <button type="button" onclick="cancelarEdicion()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                                        Cancelar
                                    </button>
                                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                        Guardar Cambios
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="fixed top-4 right-4 z-50 hidden">
        <div class="bg-gray-800 text-white px-4 py-3 rounded-lg shadow-lg flex items-center">
            <span id="toast-message"></span>
            <button onclick="document.getElementById('toast').classList.add('hidden')" class="ml-4 text-gray-400 hover:text-white">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>


    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Inicializando gráficas con ApexCharts...');

            // Pequeño retraso para asegurar que ApexCharts esté listo
            setTimeout(function() {
                // Datos de tus var_dump
                const meses = ['March 2026'];
                const tareasData = [1];
                const ciclicasData = [0];
                const ticketsData = [0];
                const prioridadesData = [0, 0, 1, 0, 0];

                // GRÁFICA MENSUAL
                const mensualContainer = document.getElementById('graficaMensual');
                if (mensualContainer && typeof ApexCharts !== 'undefined') {
                    try {
                        // Asegurar dimensiones
                        mensualContainer.style.width = '100%';
                        mensualContainer.style.height = '200px';
                        mensualContainer.innerHTML = '';

                        const optionsMensual = {
                            chart: {
                                type: 'line',
                                height: 200,
                                width: '100%',
                                toolbar: {
                                    show: false
                                },
                                animations: {
                                    enabled: true,
                                    speed: 500
                                },
                                background: 'transparent'
                            },
                            series: [{
                                    name: 'Tareas Únicas',
                                    data: tareasData
                                },
                                {
                                    name: 'Tareas Cíclicas',
                                    data: ciclicasData
                                },
                                {
                                    name: 'Tickets',
                                    data: ticketsData
                                }
                            ],
                            xaxis: {
                                categories: meses,
                                labels: {
                                    style: {
                                        fontSize: '11px'
                                    },
                                    rotate: 0
                                }
                            },
                            yaxis: {
                                min: 0,
                                max: Math.max(1, ...tareasData, ...ciclicasData, ...ticketsData) + 0.5,
                                tickAmount: 3,
                                labels: {
                                    formatter: function(val) {
                                        return Math.round(val);
                                    }
                                }
                            },
                            colors: ['#10B981', '#8B5CF6', '#F97316'],
                            stroke: {
                                curve: 'smooth',
                                width: 2
                            },
                            markers: {
                                size: 4
                            },
                            grid: {
                                show: true,
                                borderColor: '#e2e8f0',
                                strokeDashArray: 4
                            },
                            legend: {
                                position: 'bottom',
                                horizontalAlign: 'center',
                                fontSize: '12px'
                            },
                            tooltip: {
                                enabled: true,
                                y: {
                                    formatter: function(val) {
                                        return val + ' actividades';
                                    }
                                }
                            }
                        };

                        const chartMensual = new ApexCharts(mensualContainer, optionsMensual);
                        chartMensual.render();
                        console.log('Gráfica mensual OK');
                    } catch (e) {
                        console.error('Error mensual:', e);
                    }
                }

                // GRÁFICA DE PRIORIDADES
                const prioridadesContainer = document.getElementById('graficaPrioridades');
                if (prioridadesContainer && typeof ApexCharts !== 'undefined') {
                    try {
                        // Asegurar dimensiones
                        prioridadesContainer.style.width = '100%';
                        prioridadesContainer.style.height = '200px';
                        prioridadesContainer.innerHTML = '';

                        const total = prioridadesData.reduce((a, b) => a + b, 0);

                        const optionsPrioridades = {
                            chart: {
                                type: 'donut',
                                height: 200,
                                width: '100%',
                                animations: {
                                    enabled: true,
                                    speed: 500
                                },
                                background: 'transparent'
                            },
                            series: prioridadesData,
                            labels: ['Crítica', 'Urgente', 'Alta', 'Media', 'Baja'],
                            colors: ['#7F1D1D', '#DC2626', '#F97316', '#3B82F6', '#6B7280'],
                            plotOptions: {
                                pie: {
                                    donut: {
                                        size: '60%',
                                        labels: {
                                            show: true,
                                            name: {
                                                show: true
                                            },
                                            value: {
                                                show: true
                                            },
                                            total: {
                                                show: true,
                                                label: 'Total',
                                                fontSize: '12px',
                                                formatter: function() {
                                                    return total + ' act';
                                                }
                                            }
                                        }
                                    }
                                }
                            },
                            legend: {
                                position: 'bottom',
                                horizontalAlign: 'center',
                                fontSize: '11px',
                                itemMargin: {
                                    horizontal: 5
                                }
                            },
                            dataLabels: {
                                enabled: false
                            },
                            tooltip: {
                                y: {
                                    formatter: function(val) {
                                        return val + ' actividades';
                                    }
                                }
                            },
                            responsive: [{
                                breakpoint: 480,
                                options: {
                                    chart: {
                                        height: 180
                                    },
                                    legend: {
                                        position: 'bottom'
                                    }
                                }
                            }]
                        };

                        const chartPrioridades = new ApexCharts(prioridadesContainer, optionsPrioridades);
                        chartPrioridades.render();
                        console.log('Gráfica prioridades OK');
                    } catch (e) {
                        console.error('Error prioridades:', e);
                    }
                }

                // Forzar fin de carga
                document.dispatchEvent(new Event('custom-loaded'));
            }, 200);
        });

        // Tus otras funciones...
        function cambiarTab(tab) {
            const params = new URLSearchParams(window.location.search);
            params.set('tab', tab);
            window.location.href = '?' + params.toString();
        }

        function verTarea(id) {
            window.location.href = 'ver_tarea_unica.php?id=' + id;
        }

        function verTicket(id) {
            window.location.href = 'ver_ticket.php?id=' + id;
        }

        function mostrarToast(mensaje, tipo = 'success') {
            const toast = document.getElementById('toast');
            if (!toast) return;
            const toastMessage = document.getElementById('toast-message');
            const bgColor = tipo === 'success' ? 'bg-green-600' : tipo === 'error' ? 'bg-red-600' : 'bg-blue-600';
            toast.className = `fixed top-4 right-4 z-50 ${bgColor} text-white px-4 py-3 rounded-lg shadow-lg flex items-center fade-in`;
            toastMessage.textContent = mensaje;
            toast.classList.remove('hidden');
            setTimeout(() => toast.classList.add('hidden'), 3000);
        }

        function abrirModalEditar() {
            cambiarTab('configuracion');
        }

        function cancelarEdicion() {
            cambiarTab('resumen');
        }

        function guardarConfiguracion(event) {
            event.preventDefault();
            const formData = new FormData(event.target);
            if (formData.get('password') && formData.get('password') !== formData.get('confirm_password')) {
                mostrarToast('Las contraseñas no coinciden', 'error');
                return;
            }
            fetch('api/actualizar_perfil.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        mostrarToast('Perfil actualizado correctamente', 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        mostrarToast('Error: ' + data.message, 'error');
                    }
                })
                .catch(error => mostrarToast('Error de conexión', 'error'));
        }
    </script>
</body>

</html>