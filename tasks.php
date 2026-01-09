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

// Obtener información del usuario
$sql_usuario = "SELECT u.id_Usuario, u.nombre, u.apellido, u.Id_departamento, d.nombre as nombre_departamento 
                FROM usuarios u 
                LEFT JOIN areas d ON u.Id_departamento = d.id 
                WHERE u.id_Usuario = :usuario_id";
$stmt_usuario = $pdo->prepare($sql_usuario);
$stmt_usuario->execute([':usuario_id' => $usuario_id]);
$usuario = $stmt_usuario->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    die("Usuario no encontrado.");
}

$area_usuario = $usuario['Id_departamento'] ?? null;
$nombre_departamento = $usuario['nombre_departamento'] ?? 'N/A';
$nombre_usuario = $usuario['nombre'] . ' ' . $usuario['apellido'] ?? 'Usuario';
$iniciales = substr($usuario['nombre'] ?? 'U', 0, 1) . substr($usuario['apellido'] ?? 'S', 0, 1);

// Obtener puestos para jerarquía
$id_usuario_actual = $_SESSION['user_id'];
$sql_user = "SELECT Id_Usuario, Id_departamento, Id_puesto FROM usuarios WHERE Id_Usuario = :id";
$stmt_user = $pdo->prepare($sql_user);
$stmt_user->execute([':id' => $id_usuario_actual]);
$usuario_puesto = $stmt_user->fetch(PDO::FETCH_ASSOC);

$id_departamento = $usuario_puesto['Id_departamento'];
$id_puesto_usuario = $usuario_puesto['Id_puesto'];

// Función para obtener jerarquía de puestos (optimizada)
function obtenerJerarquiaPuestos($pdo, $id_puesto_usuario) {
    $sql_puestos = "SELECT Id_puesto, Id_superior FROM puestos";
    $stmt_puestos = $pdo->query($sql_puestos);
    $puestos = $stmt_puestos->fetchAll(PDO::FETCH_ASSOC);
    
    $mapa_superiores = [];
    foreach ($puestos as $p) {
        $mapa_superiores[$p['Id_puesto']] = $p['Id_superior'];
    }
    
    function obtenerSuperiores($puesto, $mapa_superiores) {
        $superiores = [];
        while (isset($mapa_superiores[$puesto]) && $mapa_superiores[$puesto] !== null) {
            $puesto = $mapa_superiores[$puesto];
            $superiores[] = $puesto;
        }
        return $superiores;
    }
    
    function obtenerSubordinados($puesto, $mapa_superiores) {
        $subordinados = [];
        foreach ($mapa_superiores as $hijo => $padre) {
            if ($padre == $puesto) {
                $subordinados[] = $hijo;
                $subordinados = array_merge($subordinados, obtenerSubordinados($hijo, $mapa_superiores));
            }
        }
        return $subordinados;
    }
    
    $puestos_superiores = obtenerSuperiores($id_puesto_usuario, $mapa_superiores);
    $puestos_inferiores = obtenerSubordinados($id_puesto_usuario, $mapa_superiores);
    
    $puestos_superiores[] = $id_puesto_usuario;
    $puestos_inferiores[] = $id_puesto_usuario;
    
    return [
        'superiores' => $puestos_superiores,
        'inferiores' => $puestos_inferiores
    ];
}

$jerarquia = obtenerJerarquiaPuestos($pdo, $id_puesto_usuario);

// Estados disponibles
$estados_disponibles = ['Pendiente', 'En Proceso', 'Completada', 'Cancelada'];
$prioridades_disponibles = [
    '0' => 'Mismo día',
    '2' => 'Alto - máx 2 días',
    '7' => 'Medio - máx 7 días',
    '15' => 'Bajo - máx 15 días',
    '30' => 'Act Mensual - máx 30 días',
    '60' => 'Act Bimestral - máx 60 días',
    '90' => 'Act Trimestral - máx 90 días',
    '180' => 'Semestral - máx 180 días',
    '365' => 'Anual - máx 365 días'
];

// Determinar qué pestaña está activa
$pestana_activa = $_GET['tab'] ?? 'ciclicas';

// Parámetros de filtro y paginación
$filtro_estado = $_GET['estado'] ?? '';
$filtro_categoria = $_GET['categoria'] ?? '';
$filtro_puesto = $_GET['puesto'] ?? '';
$filtro_busqueda = $_GET['busqueda'] ?? '';
$ordenar_por = $_GET['ordenar'] ?? 'fecha_asc';
$pagina = max(1, intval($_GET['pagina'] ?? 1));
$por_pagina = 10; // Número de tareas por página

// ================= FUNCIONES DE PAGINACIÓN Y FILTRO =================

function construirWhereSQL($filtro_estado, $filtro_categoria, $filtro_puesto, $filtro_busqueda, $pestana_activa, &$params) {
    $where_clauses = [];
    
    if ($pestana_activa === 'ciclicas') {
        $where_clauses[] = "sa.id = :area_usuario";
        $params[':area_usuario'] = $GLOBALS['area_usuario'];
        
        if (!empty($filtro_estado) && $filtro_estado !== 'todos') {
            $where_clauses[] = "ti.estado = :estado";
            $params[':estado'] = $filtro_estado;
        } else {
            $where_clauses[] = "ti.estado IN ('Pendiente', 'En Proceso')";
        }
    } else {
        $where_clauses[] = "ti.area = :area_usuario";
        $params[':area_usuario'] = $GLOBALS['area_usuario'];
        $where_clauses[] = "ti.tarea_id IS NULL";
        
        if (!empty($filtro_estado) && $filtro_estado !== 'todos') {
            $where_clauses[] = "ti.estado = :estado";
            $params[':estado'] = $filtro_estado;
        } else {
            $where_clauses[] = "ti.estado != 'Completada'";
        }
    }
    
    if (!empty($filtro_categoria)) {
        $where_clauses[] = "ti.categoria = :categoria";
        $params[':categoria'] = $filtro_categoria;
    }
    
    if (!empty($filtro_puesto)) {
        $where_clauses[] = "ti.puesto = :puesto";
        $params[':puesto'] = $filtro_puesto;
    }
    
    if (!empty($filtro_busqueda)) {
        $where_clauses[] = "(ti.actividad LIKE :busqueda OR ti.categoria LIKE :busqueda OR ti.subcategoria LIKE :busqueda)";
        $params[':busqueda'] = '%' . $filtro_busqueda . '%';
    }
    
    return !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
}

function construirOrderSQL($ordenar_por) {
    switch ($ordenar_por) {
        case 'fecha_desc':
            return 'ORDER BY ti.fecha DESC';
        case 'actividad_asc':
            return 'ORDER BY ti.actividad ASC';
        case 'actividad_desc':
            return 'ORDER BY ti.actividad DESC';
        case 'prioridad_asc':
            return 'ORDER BY FIELD(ti.prioridad, "0", "2", "7", "15", "30", "60", "90", "180", "365") ASC';
        case 'fecha_asc':
        default:
            return 'ORDER BY ti.fecha ASC, ti.estado ASC';
    }
}

// ================= TAREAS CÍCLICAS =================
if ($pestana_activa === 'ciclicas') {
    // Obtener categorías únicas para filtro
    $sql_categorias = "SELECT DISTINCT categoria FROM tareas_instancias WHERE area = :area_usuario ORDER BY categoria";
    $stmt_categorias = $pdo->prepare($sql_categorias);
$stmt_categorias->execute([':area_usuario' => $area_usuario]);
    $categorias = $stmt_categorias->fetchAll(PDO::FETCH_COLUMN, 0);
    
    // Obtener puestos únicos para filtro
    $sql_puestos = "SELECT DISTINCT p.Id_puesto, p.nombre 
                    FROM tareas_instancias ti
                    JOIN puestos p ON ti.puesto = p.Id_puesto
                    WHERE ti.area = :area_usuario
                    ORDER BY p.nombre";
    $stmt_puestos = $pdo->prepare($sql_puestos);
    $stmt_puestos->execute([':area_usuario' => $area_usuario]);
    $puestos_filtro = $stmt_puestos->fetchAll(PDO::FETCH_ASSOC);
    
    // Construir consulta con filtros
    $params = [];
    $where_sql = construirWhereSQL($filtro_estado, $filtro_categoria, $filtro_puesto, $filtro_busqueda, $pestana_activa, $params);
    $order_sql = construirOrderSQL($ordenar_por);
    
    // Consulta para conteo total
    $sql_count = "SELECT COUNT(*) as total 
                  FROM tareas_instancias ti
                  LEFT JOIN subareas sa ON ti.area = sa.nombre
                  $where_sql";
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute($params);
    $total_tareas = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Calcular paginación
    $total_paginas = ceil($total_tareas / $por_pagina);
    $offset = ($pagina - 1) * $por_pagina;
    
    // Consulta principal con paginación
    $sql_tareas = "SELECT 
        ti.id,
        ti.tarea_id,
        ti.fecha,
        ti.hora,
        ti.puesto,
        ti.actividad,
        ti.categoria,
        ti.subcategoria,
        ti.estado,
        ti.creado_en,
        ti.area,
        ti.fecha_ultima_actualizacion,
        p.nombre AS puesto_nombre,
        a.nombre AS area_nombre,
        u.nombre AS usuario_nombre,
        u.apellido AS usuario_apellido
    FROM tareas_instancias ti
    LEFT JOIN puestos p ON ti.puesto = p.Id_puesto
    LEFT JOIN subareas sa ON ti.area = sa.nombre
    LEFT JOIN areas a ON sa.id = a.id
    LEFT JOIN usuarios u ON u.Id_puesto = ti.puesto AND u.Id_departamento = a.id
    $where_sql
    $order_sql
    LIMIT :offset, :por_pagina";
    
    $stmt_tareas = $pdo->prepare($sql_tareas);
    $stmt_tareas->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt_tareas->bindValue(':por_pagina', $por_pagina, PDO::PARAM_INT);
    
    foreach ($params as $key => $value) {
        $stmt_tareas->bindValue($key, $value);
    }
    
    $stmt_tareas->execute();
    $tareas = $stmt_tareas->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener contadores para todos los estados
    $sql_contadores = "SELECT 
        estado,
        COUNT(*) as cantidad
    FROM tareas_instancias ti
    LEFT JOIN subareas sa ON ti.area = sa.nombre
    WHERE sa.id = :area_usuario
    GROUP BY estado";
    
    $stmt_contadores = $pdo->prepare($sql_contadores);
    $stmt_contadores->execute([':area_usuario' => $area_usuario]);
    $contadores_raw = $stmt_contadores->fetchAll(PDO::FETCH_ASSOC);
    
    $contadores_ciclicas = [
        'total' => $total_tareas,
        'pendientes' => 0,
        'en_proceso' => 0,
        'completadas' => 0,
        'canceladas' => 0
    ];
    
    foreach ($contadores_raw as $contador) {
        switch ($contador['estado']) {
            case 'Pendiente': $contadores_ciclicas['pendientes'] = $contador['cantidad']; break;
            case 'En Proceso': $contadores_ciclicas['en_proceso'] = $contador['cantidad']; break;
            case 'Completada': $contadores_ciclicas['completadas'] = $contador['cantidad']; break;
            case 'Cancelada': $contadores_ciclicas['canceladas'] = $contador['cantidad']; break;
        }
    }
}

// ================= TAREAS ÚNICAS =================
if ($pestana_activa === 'unicas') {
    // Obtener categorías y puestos para tareas únicas
    $sql_categorias = "SELECT DISTINCT categoria FROM tareas_instancias 
                       WHERE area = :area_usuario AND tarea_id IS NULL 
                       ORDER BY categoria";
    $stmt_categorias = $pdo->prepare($sql_categorias);
    $stmt_categorias->execute([':area_usuario' => $area_usuario]);
    $categorias = $stmt_categorias->fetchAll(PDO::FETCH_COLUMN, 0);
    
    $sql_puestos = "SELECT DISTINCT p.Id_puesto, p.nombre 
                    FROM tareas_instancias ti
                    JOIN puestos p ON ti.puesto = p.Id_puesto
                    WHERE ti.area = :area_usuario AND ti.tarea_id IS NULL
                    ORDER BY p.nombre";
    $stmt_puestos = $pdo->prepare($sql_puestos);
    $stmt_puestos->execute([':area_usuario' => $area_usuario]);
    $puestos_filtro = $stmt_puestos->fetchAll(PDO::FETCH_ASSOC);
    
    // Construir consulta con filtros
    $params = [];
    $where_sql = construirWhereSQL($filtro_estado, $filtro_categoria, $filtro_puesto, $filtro_busqueda, $pestana_activa, $params);
    $order_sql = construirOrderSQL($ordenar_por);
    
    // Consulta para conteo total
    $sql_count = "SELECT COUNT(*) as total 
                  FROM tareas_instancias ti
                  $where_sql";
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute($params);
    $total_tareas = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Calcular paginación
    $total_paginas = ceil($total_tareas / $por_pagina);
    $offset = ($pagina - 1) * $por_pagina;
    
    // Consulta principal con paginación
    $sql_unicas = "SELECT 
        ti.id,
        ti.tarea_id,
        ti.fecha,
        ti.hora,
        ti.puesto,
        ti.actividad,
        ti.categoria,
        ti.subcategoria,
        ti.estado,
        ti.creado_en,
        ti.area,
        ti.fecha_ultima_actualizacion,
        ti.prioridad,
        p.nombre as puesto_nombre,
        a.nombre as area_nombre,
        u.nombre as usuario_nombre,
        u.apellido as usuario_apellido
    FROM tareas_instancias ti
    LEFT JOIN puestos p ON ti.puesto = p.Id_puesto
    LEFT JOIN areas a ON ti.area = a.id
    LEFT JOIN usuarios u ON ti.puesto = u.Id_puesto AND u.Id_departamento = ti.area
    $where_sql
    $order_sql
    LIMIT :offset, :por_pagina";
    
    $stmt_unicas = $pdo->prepare($sql_unicas);
    $stmt_unicas->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt_unicas->bindValue(':por_pagina', $por_pagina, PDO::PARAM_INT);
    
    foreach ($params as $key => $value) {
        $stmt_unicas->bindValue($key, $value);
    }
    
    $stmt_unicas->execute();
    $tareas_unicas = $stmt_unicas->fetchAll(PDO::FETCH_ASSOC);
    
    // Contadores para tareas únicas
    $sql_contadores = "SELECT 
        estado,
        COUNT(*) as cantidad
    FROM tareas_instancias 
    WHERE area = :area_usuario AND tarea_id IS NULL
    GROUP BY estado";
    
    $stmt_contadores = $pdo->prepare($sql_contadores);
    $stmt_contadores->execute([':area_usuario' => $area_usuario]);
    $contadores_raw = $stmt_contadores->fetchAll(PDO::FETCH_ASSOC);
    
    $contadores_unicas = [
        'total' => $total_tareas,
        'pendientes' => 0,
        'en_proceso' => 0,
        'completadas' => 0,
        'canceladas' => 0
    ];
    
    foreach ($contadores_raw as $contador) {
        switch ($contador['estado']) {
            case 'Pendiente': $contadores_unicas['pendientes'] = $contador['cantidad']; break;
            case 'En Proceso': $contadores_unicas['en_proceso'] = $contador['cantidad']; break;
            case 'Completada': $contadores_unicas['completadas'] = $contador['cantidad']; break;
            case 'Cancelada': $contadores_unicas['canceladas'] = $contador['cantidad']; break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flow | Gestión de Tareas</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3B82F6',
                        secondary: '#64748B',
                        success: '#10B981',
                        warning: '#F59E0B',
                        error: '#EF4444',
                        purple: '#8B5CF6',
                        surface: '#F8FAFC',
                        border: '#E2E8F0'
                    }
                }
            }
        }
    </script>
    <style>
        .tarea-row:hover {
            background-color: #f8fafc;
            transition: background-color 0.2s ease;
        }
        
        .estado-pendiente { background-color: #FEF3C7; color: #92400E; }
        .estado-proceso { background-color: #DBEAFE; color: #1E40AF; }
        .estado-completada { background-color: #D1FAE5; color: #065F46; }
        .estado-cancelada { background-color: #FEE2E2; color: #991B1B; }
        
        .badge-prioridad-0 { background-color: #EF4444; color: white; }
        .badge-prioridad-2 { background-color: #F59E0B; color: white; }
        .badge-prioridad-7 { background-color: #10B981; color: white; }
        .badge-prioridad-15 { background-color: #3B82F6; color: white; }
        .badge-prioridad-30 { background-color: #8B5CF6; color: white; }
        .badge-prioridad-60 { background-color: #EC4899; color: white; }
        .badge-prioridad-90 { background-color: #F97316; color: white; }
        .badge-prioridad-180 { background-color: #6B7280; color: white; }
        .badge-prioridad-365 { background-color: #475569; color: white; }
        
        .tab-active {
            border-bottom: 3px solid #3B82F6;
            color: #3B82F6;
            font-weight: 600;
        }
        
        .badge {
            @apply px-2 py-1 rounded-full text-xs font-medium;
        }
        
        .fade-in {
            animation: fadeIn 0.3s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .modal-overlay {
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 font-sans">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <?php include 'includes/menu.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 p-8">
            <!-- Header -->
            <div class="flex justify-between items-center mb-8">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Gestión de Tareas</h1>
                    <p class="text-gray-500">Seguimiento y administración de tareas cíclicas y únicas</p>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-medium">
                        Departamento: <?= htmlspecialchars($nombre_departamento) ?>
                    </div>
                    <div class="flex items-center space-x-3 bg-white px-4 py-2 rounded-lg border border-gray-200">
                        <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center text-white font-medium">
                            <?= htmlspecialchars($iniciales) ?>
                        </div>
                        <div>
                            <span class="text-gray-700"><?= htmlspecialchars($nombre_usuario) ?></span>
                            <div class="text-xs text-gray-500">Usuario</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pestañas de navegación -->
            <div class="flex border-b border-gray-200 mb-6">
                <a href="?tab=ciclicas" 
                   class="px-6 py-3 text-gray-600 hover:text-blue-600 font-medium <?= $pestana_activa === 'ciclicas' ? 'tab-active' : '' ?>">
                    <i class="fas fa-redo-alt mr-2"></i>Tareas Cíclicas
                    <span class="ml-2 bg-blue-100 text-blue-600 text-xs px-2 py-1 rounded-full">
                        <?= $contadores_ciclicas['total'] ?? 0 ?>
                    </span>
                </a>
                <a href="?tab=unicas" 
                   class="px-6 py-3 text-gray-600 hover:text-blue-600 font-medium <?= $pestana_activa === 'unicas' ? 'tab-active' : '' ?>">
                    <i class="fas fa-tasks mr-2"></i>Tareas Únicas
                    <span class="ml-2 bg-blue-100 text-blue-600 text-xs px-2 py-1 rounded-full">
                        <?= $contadores_unicas['total'] ?? 0 ?>
                    </span>
                </a>
            </div>

            <!-- Stats con filtros -->
            <div class="bg-white p-6 rounded-xl border border-gray-200 mb-8 fade-in">
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
                    <?php if ($pestana_activa === 'ciclicas'): ?>
                    <a href="?tab=ciclicas&estado=todos" class="bg-white p-4 rounded-xl border border-gray-200 hover:border-purple-300 hover:bg-purple-50 transition-colors <?= $filtro_estado === 'todos' ? 'border-purple-500 bg-purple-50' : '' ?>">
                        <div class="text-xl font-bold text-purple-600"><?= $contadores_ciclicas['total'] ?? 0 ?></div>
                        <div class="text-gray-500 text-xs">Total</div>
                    </a>
                    <a href="?tab=ciclicas&estado=Pendiente" class="bg-white p-4 rounded-xl border border-gray-200 hover:border-yellow-300 hover:bg-yellow-50 transition-colors <?= $filtro_estado === 'Pendiente' ? 'border-yellow-500 bg-yellow-50' : '' ?>">
                        <div class="text-xl font-bold text-yellow-600"><?= $contadores_ciclicas['pendientes'] ?? 0 ?></div>
                        <div class="text-gray-500 text-xs">Pendientes</div>
                    </a>
                    <a href="?tab=ciclicas&estado=En%20Proceso" class="bg-white p-4 rounded-xl border border-gray-200 hover:border-blue-300 hover:bg-blue-50 transition-colors <?= $filtro_estado === 'En Proceso' ? 'border-blue-500 bg-blue-50' : '' ?>">
                        <div class="text-xl font-bold text-blue-500"><?= $contadores_ciclicas['en_proceso'] ?? 0 ?></div>
                        <div class="text-gray-500 text-xs">En Proceso</div>
                    </a>
                    <a href="?tab=ciclicas&estado=Completada" class="bg-white p-4 rounded-xl border border-gray-200 hover:border-green-300 hover:bg-green-50 transition-colors <?= $filtro_estado === 'Completada' ? 'border-green-500 bg-green-50' : '' ?>">
                        <div class="text-xl font-bold text-green-600"><?= $contadores_ciclicas['completadas'] ?? 0 ?></div>
                        <div class="text-gray-500 text-xs">Completadas</div>
                    </a>
                    <a href="?tab=ciclicas&estado=Cancelada" class="bg-white p-4 rounded-xl border border-gray-200 hover:border-red-300 hover:bg-red-50 transition-colors <?= $filtro_estado === 'Cancelada' ? 'border-red-500 bg-red-50' : '' ?>">
                        <div class="text-xl font-bold text-red-600"><?= $contadores_ciclicas['canceladas'] ?? 0 ?></div>
                        <div class="text-gray-500 text-xs">Canceladas</div>
                    </a>
                    
                    <?php elseif ($pestana_activa === 'unicas'): ?>
                    <a href="?tab=unicas&estado=todos" class="bg-white p-4 rounded-xl border border-gray-200 hover:border-blue-300 hover:bg-blue-50 transition-colors <?= $filtro_estado === 'todos' ? 'border-blue-500 bg-blue-50' : '' ?>">
                        <div class="text-xl font-bold text-blue-600"><?= $contadores_unicas['total'] ?? 0 ?></div>
                        <div class="text-gray-500 text-xs">Total</div>
                    </a>
                    <a href="?tab=unicas&estado=Pendiente" class="bg-white p-4 rounded-xl border border-gray-200 hover:border-yellow-300 hover:bg-yellow-50 transition-colors <?= $filtro_estado === 'Pendiente' ? 'border-yellow-500 bg-yellow-50' : '' ?>">
                        <div class="text-xl font-bold text-yellow-600"><?= $contadores_unicas['pendientes'] ?? 0 ?></div>
                        <div class="text-gray-500 text-xs">Pendientes</div>
                    </a>
                    <a href="?tab=unicas&estado=En%20Proceso" class="bg-white p-4 rounded-xl border border-gray-200 hover:border-blue-300 hover:bg-blue-50 transition-colors <?= $filtro_estado === 'En Proceso' ? 'border-blue-500 bg-blue-50' : '' ?>">
                        <div class="text-xl font-bold text-blue-500"><?= $contadores_unicas['en_proceso'] ?? 0 ?></div>
                        <div class="text-gray-500 text-xs">En Proceso</div>
                    </a>
                    <a href="?tab=unicas&estado=Completada" class="bg-white p-4 rounded-xl border border-gray-200 hover:border-green-300 hover:bg-green-50 transition-colors <?= $filtro_estado === 'Completada' ? 'border-green-500 bg-green-50' : '' ?>">
                        <div class="text-xl font-bold text-green-600"><?= $contadores_unicas['completadas'] ?? 0 ?></div>
                        <div class="text-gray-500 text-xs">Completadas</div>
                    </a>
                    <a href="?tab=unicas&estado=Cancelada" class="bg-white p-4 rounded-xl border border-gray-200 hover:border-red-300 hover:bg-red-50 transition-colors <?= $filtro_estado === 'Cancelada' ? 'border-red-500 bg-red-50' : '' ?>">
                        <div class="text-xl font-bold text-red-600"><?= $contadores_unicas['canceladas'] ?? 0 ?></div>
                        <div class="text-gray-500 text-xs">Canceladas</div>
                    </a>
                    <?php endif; ?>
                </div>
                
                <!-- Filtros Avanzados -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Categoría</label>
                        <select id="filtro-categoria" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Todas las categorías</option>
                            <?php foreach ($categorias as $categoria): ?>
                                <option value="<?= htmlspecialchars($categoria) ?>" <?= $filtro_categoria === $categoria ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($categoria) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Puesto</label>
                        <select id="filtro-puesto" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Todos los puestos</option>
                            <?php foreach ($puestos_filtro as $puesto): ?>
                                <option value="<?= $puesto['Id_puesto'] ?>" <?= $filtro_puesto == $puesto['Id_puesto'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($puesto['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Ordenar por</label>
                        <select id="filtro-ordenar" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="fecha_asc" <?= $ordenar_por === 'fecha_asc' ? 'selected' : '' ?>>Fecha (Ascendente)</option>
                            <option value="fecha_desc" <?= $ordenar_por === 'fecha_desc' ? 'selected' : '' ?>>Fecha (Descendente)</option>
                            <option value="actividad_asc" <?= $ordenar_por === 'actividad_asc' ? 'selected' : '' ?>>Actividad (A-Z)</option>
                            <option value="actividad_desc" <?= $ordenar_por === 'actividad_desc' ? 'selected' : '' ?>>Actividad (Z-A)</option>
                            <option value="prioridad_asc" <?= $ordenar_por === 'prioridad_asc' ? 'selected' : '' ?>>Prioridad</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Buscar</label>
                        <form method="GET" class="flex">
                            <input type="hidden" name="tab" value="<?= $pestana_activa ?>">
                            <input type="hidden" name="estado" value="<?= $filtro_estado ?>">
                            <input type="hidden" name="categoria" value="<?= $filtro_categoria ?>">
                            <input type="hidden" name="puesto" value="<?= $filtro_puesto ?>">
                            <input type="hidden" name="ordenar" value="<?= $ordenar_por ?>">
                            <input type="text" name="busqueda" value="<?= htmlspecialchars($filtro_busqueda) ?>" 
                                   placeholder="Buscar tareas..." 
                                   class="flex-1 border border-gray-300 rounded-l-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-r-lg hover:bg-blue-700">
                                <i class="fas fa-search"></i>
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Botones de acción filtros -->
                <div class="flex justify-between items-center mt-4">
                    <div class="text-sm text-gray-500">
                        Mostrando <?= min($por_pagina, ($pestana_activa === 'ciclicas' ? count($tareas) : count($tareas_unicas))) ?> de <?= $total_tareas ?> tareas
                        <?php if ($filtro_estado || $filtro_categoria || $filtro_puesto || $filtro_busqueda): ?>
                            <span class="ml-2 bg-gray-100 text-gray-800 px-2 py-1 rounded text-xs">
                                Filtros activos
                            </span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <a href="?tab=<?= $pestana_activa ?>" class="text-sm text-gray-600 hover:text-gray-800">
                            <i class="fas fa-times mr-1"></i>Limpiar filtros
                        </a>
                    </div>
                </div>
            </div>

            <!-- Action Bar -->
            <div class="flex justify-between items-center mb-6">
                <div class="flex space-x-2">
                    <?php if ($pestana_activa === 'ciclicas'): ?>
                    <button onclick="abrirModalCrearCiclica()" class="px-4 py-2 bg-purple-600 text-white rounded-lg font-medium flex items-center space-x-2 hover:bg-purple-700 transition-colors">
                        <i class="fas fa-plus"></i>
                        <span>Nueva Tarea Cíclica</span>
                    </button>
                    <button onclick="exportarCSV()" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg font-medium hover:bg-gray-50 transition-colors">
                        <i class="fas fa-download mr-2"></i>Exportar CSV
                    </button>
                    <?php else: ?>
                    <button onclick="abrirModalCrearUnica()" class="px-4 py-2 bg-blue-600 text-white rounded-lg font-medium flex items-center space-x-2 hover:bg-blue-700 transition-colors">
                        <i class="fas fa-plus"></i>
                        <span>Nueva Tarea Única</span>
                    </button>
                    <button onclick="exportarCSV()" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg font-medium hover:bg-gray-50 transition-colors">
                        <i class="fas fa-download mr-2"></i>Exportar CSV
                    </button>
                    <?php endif; ?>
                    <button onclick="mostrarOcultarCompletadas()" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg font-medium hover:bg-gray-50 transition-colors" id="toggle-completadas">
                        <i class="fas fa-eye mr-2"></i>Mostrar Completadas
                    </button>
                </div>
                <div class="text-sm text-gray-500">
                    <i class="fas fa-sync-alt mr-1"></i>Última actualización: <?= date('H:i:s') ?>
                </div>
            </div>

            <!-- Contenido de las pestañas -->
            <?php if ($pestana_activa === 'ciclicas'): ?>
            <!-- Tabla Tareas Cíclicas -->
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden fade-in">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actividad</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Puesto</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prioridad</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Última Actualización</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200" id="tareas-body">
                        <?php foreach ($tareas as $tarea): ?>
                            <?php 
                            $dias_restantes = floor((strtotime($tarea['fecha']) - time()) / (60 * 60 * 24));
                            $clase_prioridad = 'badge-prioridad-' . ($tarea['prioridad'] ?? '15');
                            ?>
                            
                            <tr class="tarea-row <?= $tarea['estado'] === 'Completada' ? 'opacity-75' : '' ?>" data-estado="<?= $tarea['estado'] ?>">
                                <!-- Actividad -->
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 rounded bg-purple-500 flex items-center justify-center text-white mr-3">
                                            <i class="fas fa-redo-alt text-sm"></i>
                                        </div>
                                        <div>
                                            <div class="font-medium text-gray-900"><?= htmlspecialchars($tarea['actividad']) ?></div>
                                            <div class="text-xs text-gray-500">
                                                <?php if ($tarea['subcategoria']): ?>
                                                    <?= htmlspecialchars($tarea['categoria']) ?> » <?= htmlspecialchars($tarea['subcategoria']) ?>
                                                <?php else: ?>
                                                    <?= htmlspecialchars($tarea['categoria']) ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                
                                <!-- Fecha -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?= date('d M Y', strtotime($tarea['fecha'])) ?>
                                    </div>
                                    <?php if ($tarea['hora']): ?>
                                        <div class="text-xs text-gray-500"><?= $tarea['hora'] ?></div>
                                    <?php endif; ?>
                                    <div class="text-xs font-medium mt-1 <?= $dias_restantes < 0 ? 'text-red-600' : ($dias_restantes == 0 ? 'text-yellow-600' : 'text-green-600') ?>">
                                        <?php 
                                        if ($dias_restantes > 0) {
                                            echo "$dias_restantes días restantes";
                                        } elseif ($dias_restantes == 0) {
                                            echo '<i class="fas fa-exclamation-circle mr-1"></i>Hoy vence';
                                        } else {
                                            echo '<i class="fas fa-clock mr-1"></i>Vencida hace ' . abs($dias_restantes) . ' días';
                                        }
                                        ?>
                                    </div>
                                </td>
                                
                                <!-- Puesto -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?= htmlspecialchars($tarea['puesto_nombre'] ?? $tarea['puesto']) ?>
                                    </div>
                                    <?php if ($tarea['usuario_nombre']): ?>
                                        <div class="text-xs text-gray-500">
                                            <i class="fas fa-user mr-1"></i><?= htmlspecialchars($tarea['usuario_nombre'] . ' ' . $tarea['usuario_apellido']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Prioridad -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="badge <?= $clase_prioridad ?>">
                                        <?= $prioridades_disponibles[$tarea['prioridad'] ?? '15'] ?? 'Bajo' ?>
                                    </span>
                                </td>
                                
                                <!-- Estado -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="relative">
                                        <select class="w-full border rounded-lg px-3 py-1 text-sm estado-select-ciclica estado-<?= strtolower($tarea['estado']) ?>" 
                                                data-tarea="<?= $tarea['id'] ?>"
                                                data-original="<?= $tarea['estado'] ?>">
                                            <?php foreach ($estados_disponibles as $estado): ?>
                                                <option value="<?= $estado ?>" 
                                                        <?= $tarea['estado'] === $estado ? 'selected' : '' ?>
                                                        class="estado-<?= strtolower($estado) ?>">
                                                    <?= $estado ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="absolute right-2 top-1/2 transform -translate-y-1/2 pointer-events-none">
                                            <i class="fas fa-chevron-down text-gray-400 text-xs"></i>
                                        </div>
                                    </div>
                                </td>
                                
                                <!-- Última Actualización -->
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php if ($tarea['fecha_ultima_actualizacion']): ?>
                                        <?= date('d M Y H:i', strtotime($tarea['fecha_ultima_actualizacion'])) ?>
                                        <div class="text-xs text-gray-400">
                                            <?php 
                                            $diff = time() - strtotime($tarea['fecha_ultima_actualizacion']);
                                            if ($diff < 3600) {
                                                echo floor($diff/60) . ' min';
                                            } elseif ($diff < 86400) {
                                                echo floor($diff/3600) . ' horas';
                                            } else {
                                                echo floor($diff/86400) . ' días';
                                            }
                                            ?> atrás
                                        </div>
                                    <?php else: ?>
                                        <span class="text-gray-400 italic">Sin actualizar</span>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Acciones -->
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <button onclick="verDetalleCiclica(<?= $tarea['id'] ?>)" 
                                                class="text-blue-600 hover:text-blue-800 p-1 hover:bg-blue-50 rounded" 
                                                title="Ver detalles">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button onclick="editarTareaCiclica(<?= $tarea['id'] ?>)" 
                                                class="text-green-600 hover:text-green-800 p-1 hover:bg-green-50 rounded" 
                                                title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="duplicarTarea(<?= $tarea['id'] ?>)" 
                                                class="text-purple-600 hover:text-purple-800 p-1 hover:bg-purple-50 rounded" 
                                                title="Duplicar">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                        <button onclick="eliminarTareaCiclica(<?= $tarea['id'] ?>)" 
                                                class="text-red-600 hover:text-red-800 p-1 hover:bg-red-50 rounded" 
                                                title="Eliminar">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Paginación -->
                <?php if ($total_paginas > 1): ?>
                <div class="px-6 py-4 border-t border-gray-200">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-500">
                            Página <?= $pagina ?> de <?= $total_paginas ?>
                        </div>
                        <div class="flex space-x-1">
                            <!-- Primera página -->
                            <a href="?tab=<?= $pestana_activa ?>&pagina=1&estado=<?= $filtro_estado ?>&categoria=<?= $filtro_categoria ?>&puesto=<?= $filtro_puesto ?>&busqueda=<?= $filtro_busqueda ?>&ordenar=<?= $ordenar_por ?>"
                               class="px-3 py-1 border border-gray-300 rounded-lg text-sm <?= $pagina == 1 ? 'bg-gray-100 text-gray-400' : 'text-gray-700 hover:bg-gray-50' ?>"
                               <?= $pagina == 1 ? 'disabled' : '' ?>>
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                            
                            <!-- Página anterior -->
                            <a href="?tab=<?= $pestana_activa ?>&pagina=<?= max(1, $pagina-1) ?>&estado=<?= $filtro_estado ?>&categoria=<?= $filtro_categoria ?>&puesto=<?= $filtro_puesto ?>&busqueda=<?= $filtro_busqueda ?>&ordenar=<?= $ordenar_por ?>"
                               class="px-3 py-1 border border-gray-300 rounded-lg text-sm <?= $pagina == 1 ? 'bg-gray-100 text-gray-400' : 'text-gray-700 hover:bg-gray-50' ?>"
                               <?= $pagina == 1 ? 'disabled' : '' ?>>
                                <i class="fas fa-angle-left"></i>
                            </a>
                            
                            <!-- Números de página -->
                            <?php 
                            $inicio = max(1, $pagina - 2);
                            $fin = min($total_paginas, $pagina + 2);
                            
                            if ($inicio > 1) {
                                echo '<span class="px-3 py-1 text-gray-500">...</span>';
                            }
                            
                            for ($i = $inicio; $i <= $fin; $i++): ?>
                                <a href="?tab=<?= $pestana_activa ?>&pagina=<?= $i ?>&estado=<?= $filtro_estado ?>&categoria=<?= $filtro_categoria ?>&puesto=<?= $filtro_puesto ?>&busqueda=<?= $filtro_busqueda ?>&ordenar=<?= $ordenar_por ?>"
                                   class="px-3 py-1 border rounded-lg text-sm <?= $pagina == $i ? 'bg-blue-600 text-white border-blue-600' : 'border-gray-300 text-gray-700 hover:bg-gray-50' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; 
                            
                            if ($fin < $total_paginas) {
                                echo '<span class="px-3 py-1 text-gray-500">...</span>';
                            }
                            ?>
                            
                            <!-- Página siguiente -->
                            <a href="?tab=<?= $pestana_activa ?>&pagina=<?= min($total_paginas, $pagina+1) ?>&estado=<?= $filtro_estado ?>&categoria=<?= $filtro_categoria ?>&puesto=<?= $filtro_puesto ?>&busqueda=<?= $filtro_busqueda ?>&ordenar=<?= $ordenar_por ?>"
                               class="px-3 py-1 border border-gray-300 rounded-lg text-sm <?= $pagina == $total_paginas ? 'bg-gray-100 text-gray-400' : 'text-gray-700 hover:bg-gray-50' ?>"
                               <?= $pagina == $total_paginas ? 'disabled' : '' ?>>
                                <i class="fas fa-angle-right"></i>
                            </a>
                            
                            <!-- Última página -->
                            <a href="?tab=<?= $pestana_activa ?>&pagina=<?= $total_paginas ?>&estado=<?= $filtro_estado ?>&categoria=<?= $filtro_categoria ?>&puesto=<?= $filtro_puesto ?>&busqueda=<?= $filtro_busqueda ?>&ordenar=<?= $ordenar_por ?>"
                               class="px-3 py-1 border border-gray-300 rounded-lg text-sm <?= $pagina == $total_paginas ? 'bg-gray-100 text-gray-400' : 'text-gray-700 hover:bg-gray-50' ?>"
                               <?= $pagina == $total_paginas ? 'disabled' : '' ?>>
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        </div>
                        
                        <!-- Selector de items por página -->
                        <div class="flex items-center space-x-2">
                            <span class="text-sm text-gray-500">Items por página:</span>
                            <select id="items-por-pagina" class="border border-gray-300 rounded-lg px-2 py-1 text-sm">
                                <option value="10" <?= $por_pagina == 10 ? 'selected' : '' ?>>10</option>
                                <option value="25" <?= $por_pagina == 25 ? 'selected' : '' ?>>25</option>
                                <option value="50" <?= $por_pagina == 50 ? 'selected' : '' ?>>50</option>
                                <option value="100" <?= $por_pagina == 100 ? 'selected' : '' ?>>100</option>
                            </select>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (empty($tareas)): ?>
                <div class="text-center py-12">
                    <i class="fas fa-redo-alt text-4xl text-gray-300 mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No hay tareas cíclicas</h3>
                    <p class="text-gray-500 mb-4"><?= $filtro_estado || $filtro_categoria || $filtro_puesto || $filtro_busqueda ? 'Intenta con otros filtros' : 'Crea tu primera tarea cíclica para comenzar' ?></p>
                    <button onclick="abrirModalCrearCiclica()" class="px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700">
                        <i class="fas fa-plus mr-2"></i>Crear Tarea Cíclica
                    </button>
                </div>
                <?php endif; ?>
            </div>
            
            <?php elseif ($pestana_activa === 'unicas'): ?>
            <!-- Tabla Tareas Únicas -->
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden fade-in">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actividad</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Puesto</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prioridad</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Última Actualización</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200" id="tareas-body">
                        <?php foreach ($tareas_unicas as $tarea): ?>
                            <?php 
                            $dias_restantes = floor((strtotime($tarea['fecha']) - time()) / (60 * 60 * 24));
                            $clase_prioridad = 'badge-prioridad-' . ($tarea['prioridad'] ?? '15');
                            ?>
                            
                            <tr class="tarea-row <?= $tarea['estado'] === 'Completada' ? 'opacity-75' : '' ?>" data-estado="<?= $tarea['estado'] ?>">
                                <!-- Actividad -->
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 rounded bg-blue-500 flex items-center justify-center text-white mr-3">
                                            <i class="fas fa-tasks text-sm"></i>
                                        </div>
                                        <div>
                                            <div class="font-medium text-gray-900"><?= htmlspecialchars($tarea['actividad']) ?></div>
                                            <div class="text-xs text-gray-500">
                                                <?php if ($tarea['subcategoria']): ?>
                                                    <?= htmlspecialchars($tarea['categoria']) ?> » <?= htmlspecialchars($tarea['subcategoria']) ?>
                                                <?php else: ?>
                                                    <?= htmlspecialchars($tarea['categoria']) ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                
                                <!-- Fecha -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?= date('d M Y', strtotime($tarea['fecha'])) ?>
                                    </div>
                                    <?php if ($tarea['hora']): ?>
                                        <div class="text-xs text-gray-500"><?= $tarea['hora'] ?></div>
                                    <?php endif; ?>
                                    <div class="text-xs font-medium mt-1 <?= $dias_restantes < 0 ? 'text-red-600' : ($dias_restantes == 0 ? 'text-yellow-600' : 'text-green-600') ?>">
                                        <?php 
                                        if ($dias_restantes > 0) {
                                            echo "$dias_restantes días restantes";
                                        } elseif ($dias_restantes == 0) {
                                            echo '<i class="fas fa-exclamation-circle mr-1"></i>Hoy vence';
                                        } else {
                                            echo '<i class="fas fa-clock mr-1"></i>Vencida hace ' . abs($dias_restantes) . ' días';
                                        }
                                        ?>
                                    </div>
                                </td>
                                
                                <!-- Puesto -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?= htmlspecialchars($tarea['puesto_nombre'] ?? $tarea['puesto']) ?>
                                    </div>
                                    <?php if ($tarea['usuario_nombre']): ?>
                                        <div class="text-xs text-gray-500">
                                            <i class="fas fa-user mr-1"></i><?= htmlspecialchars($tarea['usuario_nombre'] . ' ' . $tarea['usuario_apellido']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Prioridad -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="badge <?= $clase_prioridad ?>">
                                        <?= $prioridades_disponibles[$tarea['prioridad'] ?? '15'] ?? 'Bajo' ?>
                                    </span>
                                </td>
                                
                                <!-- Estado -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="relative">
                                        <select class="w-full border rounded-lg px-3 py-1 text-sm estado-select-unica estado-<?= strtolower($tarea['estado']) ?>" 
                                                data-tarea="<?= $tarea['id'] ?>"
                                                data-original="<?= $tarea['estado'] ?>">
                                            <?php foreach ($estados_disponibles as $estado): ?>
                                                <option value="<?= $estado ?>" 
                                                        <?= $tarea['estado'] === $estado ? 'selected' : '' ?>
                                                        class="estado-<?= strtolower($estado) ?>">
                                                    <?= $estado ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="absolute right-2 top-1/2 transform -translate-y-1/2 pointer-events-none">
                                            <i class="fas fa-chevron-down text-gray-400 text-xs"></i>
                                        </div>
                                    </div>
                                </td>
                                
                                <!-- Última Actualización -->
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php if ($tarea['fecha_ultima_actualizacion']): ?>
                                        <?= date('d M Y H:i', strtotime($tarea['fecha_ultima_actualizacion'])) ?>
                                        <div class="text-xs text-gray-400">
                                            <?php 
                                            $diff = time() - strtotime($tarea['fecha_ultima_actualizacion']);
                                            if ($diff < 3600) {
                                                echo floor($diff/60) . ' min';
                                            } elseif ($diff < 86400) {
                                                echo floor($diff/3600) . ' horas';
                                            } else {
                                                echo floor($diff/86400) . ' días';
                                            }
                                            ?> atrás
                                        </div>
                                    <?php else: ?>
                                        <span class="text-gray-400 italic">Sin actualizar</span>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Acciones -->
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <button onclick="verDetalleUnica(<?= $tarea['id'] ?>)" 
                                                class="text-blue-600 hover:text-blue-800 p-1 hover:bg-blue-50 rounded" 
                                                title="Ver detalles">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button onclick="editarTareaUnica(<?= $tarea['id'] ?>)" 
                                                class="text-green-600 hover:text-green-800 p-1 hover:bg-green-50 rounded" 
                                                title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="duplicarTarea(<?= $tarea['id'] ?>)" 
                                                class="text-blue-600 hover:text-blue-800 p-1 hover:bg-blue-50 rounded" 
                                                title="Duplicar">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                        <button onclick="eliminarTareaUnica(<?= $tarea['id'] ?>)" 
                                                class="text-red-600 hover:text-red-800 p-1 hover:bg-red-50 rounded" 
                                                title="Eliminar">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Paginación (similar a la de cíclicas) -->
                <?php if ($total_paginas > 1): ?>
                <div class="px-6 py-4 border-t border-gray-200">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-500">
                            Página <?= $pagina ?> de <?= $total_paginas ?>
                        </div>
                        <div class="flex space-x-1">
                            <!-- Similar paginación a la sección cíclica -->
                            <!-- ... (código de paginación similar) ... -->
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="text-sm text-gray-500">Items por página:</span>
                            <select id="items-por-pagina" class="border border-gray-300 rounded-lg px-2 py-1 text-sm">
                                <option value="10" <?= $por_pagina == 10 ? 'selected' : '' ?>>10</option>
                                <option value="25" <?= $por_pagina == 25 ? 'selected' : '' ?>>25</option>
                                <option value="50" <?= $por_pagina == 50 ? 'selected' : '' ?>>50</option>
                                <option value="100" <?= $por_pagina == 100 ? 'selected' : '' ?>>100</option>
                            </select>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (empty($tareas_unicas)): ?>
                <div class="text-center py-12">
                    <i class="fas fa-tasks text-4xl text-gray-300 mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No hay tareas únicas</h3>
                    <p class="text-gray-500 mb-4"><?= $filtro_estado || $filtro_categoria || $filtro_puesto || $filtro_busqueda ? 'Intenta con otros filtros' : 'Crea tu primera tarea única para comenzar' ?></p>
                    <button onclick="abrirModalCrearUnica()" class="px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700">
                        <i class="fas fa-plus mr-2"></i>Crear Tarea Única
                    </button>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
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

    <!-- Modal Filtros Avanzados -->
    <div id="modal-filtros" class="fixed inset-0 z-50 hidden">
        <div class="modal-overlay absolute inset-0"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-auto">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xl font-bold text-gray-900">Filtros Avanzados</h3>
                        <button onclick="cerrarModalFiltros()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                    <!-- Contenido del modal de filtros -->
                    <!-- ... -->
                </div>
            </div>
        </div>
    </div>

    <script>
        // Función para mostrar notificaciones toast
        function mostrarToast(mensaje, tipo = 'success') {
            const toast = document.getElementById('toast');
            const toastMessage = document.getElementById('toast-message');
            
            let bgColor = 'bg-gray-800';
            if (tipo === 'success') bgColor = 'bg-green-600';
            if (tipo === 'error') bgColor = 'bg-red-600';
            if (tipo === 'warning') bgColor = 'bg-yellow-600';
            if (tipo === 'info') bgColor = 'bg-blue-600';
            
            toast.className = `fixed top-4 right-4 z-50 ${bgColor} text-white px-4 py-3 rounded-lg shadow-lg flex items-center fade-in`;
            toastMessage.textContent = mensaje;
            toast.classList.remove('hidden');
            
            setTimeout(() => {
                toast.classList.add('hidden');
            }, 3000);
        }

        // Actualizar estado de tareas
        function actualizarEstado(tareaId, nuevoEstado, tipo) {
            const endpoint = tipo === 'ciclica' ? 'task/actualizar_ciclica.php' : 'task/actualizar_unica.php';
            
            fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    id: tareaId,
                    estado: nuevoEstado
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarToast(`Estado actualizado a: ${nuevoEstado}`, 'success');
                    // Actualizar la fila sin recargar toda la página
                    const fila = document.querySelector(`[data-tarea="${tareaId}"]`).closest('tr');
                    fila.setAttribute('data-estado', nuevoEstado);
                    if (nuevoEstado === 'Completada') {
                        fila.classList.add('opacity-75');
                    } else {
                        fila.classList.remove('opacity-75');
                    }
                } else {
                    mostrarToast(`Error: ${data.message}`, 'error');
                }
            })
            .catch(error => {
                mostrarToast('Error de conexión', 'error');
            });
        }

        // Duplicar tarea
        function duplicarTarea(tareaId) {
            if (confirm('¿Duplicar esta tarea?')) {
                fetch('task/duplicar.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        id: tareaId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        mostrarToast('Tarea duplicada correctamente', 'success');
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        mostrarToast(`Error: ${data.message}`, 'error');
                    }
                })
                .catch(error => {
                    mostrarToast('Error de conexión', 'error');
                });
            }
        }

        // Exportar a CSV
        function exportarCSV() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'csv');
            window.location.href = 'exportar.php?' + params.toString();
        }

        // Mostrar/ocultar tareas completadas
        function mostrarOcultarCompletadas() {
            const boton = document.getElementById('toggle-completadas');
            const tareas = document.querySelectorAll('#tareas-body tr');
            let mostrar = boton.textContent.includes('Mostrar');
            
            tareas.forEach(fila => {
                if (fila.dataset.estado === 'Completada') {
                    fila.style.display = mostrar ? 'table-row' : 'none';
                }
            });
            
            boton.innerHTML = mostrar ? 
                '<i class="fas fa-eye-slash mr-2"></i>Ocultar Completadas' : 
                '<i class="fas fa-eye mr-2"></i>Mostrar Completadas';
        }

        // Funciones de filtro
        document.getElementById('filtro-categoria').addEventListener('change', function() {
            aplicarFiltros();
        });

        document.getElementById('filtro-puesto').addEventListener('change', function() {
            aplicarFiltros();
        });

        document.getElementById('filtro-ordenar').addEventListener('change', function() {
            aplicarFiltros();
        });

        document.getElementById('items-por-pagina').addEventListener('change', function() {
            const params = new URLSearchParams(window.location.search);
            params.set('por_pagina', this.value);
            params.set('pagina', '1'); // Volver a primera página
            window.location.href = '?' + params.toString();
        });

        function aplicarFiltros() {
            const params = new URLSearchParams(window.location.search);
            params.set('categoria', document.getElementById('filtro-categoria').value);
            params.set('puesto', document.getElementById('filtro-puesto').value);
            params.set('ordenar', document.getElementById('filtro-ordenar').value);
            params.set('pagina', '1'); // Reset a página 1
            window.location.href = '?' + params.toString();
        }

        // Event listeners cuando se carga el DOM
        document.addEventListener('DOMContentLoaded', function() {
            // Evento para estados de tareas cíclicas
            document.querySelectorAll('.estado-select-ciclica').forEach(select => {
                select.addEventListener('change', function() {
                    const tareaId = this.getAttribute('data-tarea');
                    const nuevoEstado = this.value;
                    const original = this.dataset.original;
                    
                    if (tareaId && nuevoEstado) {
                        if (confirm(`¿Cambiar estado de "${original}" a "${nuevoEstado}"?`)) {
                            actualizarEstado(tareaId, nuevoEstado, 'ciclica');
                            this.dataset.original = nuevoEstado;
                        } else {
                            this.value = original;
                        }
                    }
                });
            });

            // Evento para estados de tareas únicas
            document.querySelectorAll('.estado-select-unica').forEach(select => {
                select.addEventListener('change', function() {
                    const tareaId = this.getAttribute('data-tarea');
                    const nuevoEstado = this.value;
                    const original = this.dataset.original;
                    
                    if (tareaId && nuevoEstado) {
                        if (confirm(`¿Cambiar estado de "${original}" a "${nuevoEstado}"?`)) {
                            actualizarEstado(tareaId, nuevoEstado, 'unica');
                            this.dataset.original = nuevoEstado;
                        } else {
                            this.value = original;
                        }
                    }
                });
            });

            // Auto-refresh cada 5 minutos
            setInterval(() => {
                // Solo recargar si no hay cambios pendientes
                if (!document.querySelector('.estado-select-ciclica:focus, .estado-select-unica:focus')) {
                    window.location.reload();
                }
            }, 300000); // 5 minutos
        });

        // Placeholder functions
        function abrirModalCrearCiclica() {
            mostrarToast('Funcionalidad en desarrollo', 'info');
        }

        function abrirModalCrearUnica() {
            mostrarToast('Funcionalidad en desarrollo', 'info');
        }

        function verDetalleCiclica(id) {
            mostrarToast('Viendo detalles de tarea cíclica ID: ' + id, 'info');
        }

        function verDetalleUnica(id) {
            mostrarToast('Viendo detalles de tarea única ID: ' + id, 'info');
        }

        function editarTareaCiclica(id) {
            mostrarToast('Editando tarea cíclica ID: ' + id, 'info');
        }

        function editarTareaUnica(id) {
            mostrarToast('Editando tarea única ID: ' + id, 'info');
        }

        function eliminarTareaCiclica(id) {
            if (confirm('¿Estás seguro de eliminar esta tarea cíclica?')) {
                mostrarToast('Eliminando tarea cíclica ID: ' + id, 'warning');
            }
        }

        function eliminarTareaUnica(id) {
            if (confirm('¿Estás seguro de eliminar esta tarea única?')) {
                mostrarToast('Eliminando tarea única ID: ' + id, 'warning');
            }
        }
    </script>
</body>
</html>