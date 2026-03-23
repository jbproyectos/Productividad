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
function obtenerJerarquiaPuestos($pdo, $id_puesto_usuario)
{
    $sql_puestos = "SELECT Id_puesto, Id_superior FROM puestos";
    $stmt_puestos = $pdo->query($sql_puestos);
    $puestos = $stmt_puestos->fetchAll(PDO::FETCH_ASSOC);

    $mapa_superiores = [];
    foreach ($puestos as $p) {
        $mapa_superiores[$p['Id_puesto']] = $p['Id_superior'];
    }

    function obtenerSuperiores($puesto, $mapa_superiores)
    {
        $superiores = [];
        while (isset($mapa_superiores[$puesto]) && $mapa_superiores[$puesto] !== null) {
            $puesto = $mapa_superiores[$puesto];
            $superiores[] = $puesto;
        }
        return $superiores;
    }

    function obtenerSubordinados($puesto, $mapa_superiores)
    {
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
$estados_db = [
    'Pendiente' => 'Pendiente',
    'En Proceso' => 'En Proceso',
    'Completada' => 'completada',
    'Cancelada' => 'cancelada'
];

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

// Prioridades para tareas únicas (ENUM de la tabla)
$prioridades_unicas = [
    'baja' => 'Baja',
    'media' => 'Media',
    'alta' => 'Alta',
    'urgente' => 'Urgente',
    'critica' => 'Crítica'
];

// Determinar qué pestaña está activa
$pestana_activa = $_GET['tab'] ?? 'ciclicas';

// Parámetros de filtro y paginación
$filtro_estado = $_GET['estado'] ?? '';
$filtro_categoria = $_GET['categoria'] ?? '';
$filtro_puesto = $_GET['puesto'] ?? '';
$filtro_busqueda = $_GET['busqueda'] ?? '';
$filtro_fecha_inicio = $_GET['fecha_inicio'] ?? '';
$filtro_fecha_fin = $_GET['fecha_fin'] ?? '';
$filtro_prioridad = $_GET['prioridad'] ?? '';
$filtro_actualizacion = $_GET['actualizacion'] ?? '';
$ordenar_por = $_GET['ordenar'] ?? 'fecha_asc';
$pagina = max(1, intval($_GET['pagina'] ?? 1));
$por_pagina = $_GET['por_pagina'] ?? 10;

// ================= FUNCIONES DE PAGINACIÓN Y FILTRO =================

function construirWhereSQL(
    $filtro_estado,
    $filtro_categoria,
    $filtro_puesto,
    $filtro_busqueda,
    $filtro_fecha_inicio,
    $filtro_fecha_fin,
    $filtro_prioridad,
    $filtro_actualizacion,
    $pestana_activa,
    &$params
) {
    $where_clauses = [];

    if ($pestana_activa === 'ciclicas') {
        $where_clauses[] = "sa.id = :area_usuario";
        $params[':area_usuario'] = $GLOBALS['area_usuario'];
    } else {
        $where_clauses[] = "ti.area = :area_usuario";
        $params[':area_usuario'] = $GLOBALS['area_usuario'];
        $where_clauses[] = "ti.tarea_id IS NULL";
    }

    // Filtro por estado
    if (!empty($filtro_estado) && $filtro_estado !== 'todos') {
        $where_clauses[] = "ti.estado = :estado";
        $params[':estado'] = $filtro_estado;
    } else if ($pestana_activa === 'ciclicas') {
        $where_clauses[] = "ti.estado IN ('Pendiente', 'En Proceso')";
    } else {
        $where_clauses[] = "ti.estado != 'Completada'";
    }

    // Filtro por categoría
    if (!empty($filtro_categoria)) {
        $where_clauses[] = "ti.categoria = :categoria";
        $params[':categoria'] = $filtro_categoria;
    }

    // Filtro por puesto
    if (!empty($filtro_puesto)) {
        $where_clauses[] = "ti.puesto = :puesto";
        $params[':puesto'] = $filtro_puesto;
    }

    // Filtro por búsqueda
    if (!empty($filtro_busqueda)) {
        $where_clauses[] = "(ti.actividad LIKE :busqueda OR ti.categoria LIKE :busqueda OR ti.subcategoria LIKE :busqueda)";
        $params[':busqueda'] = '%' . $filtro_busqueda . '%';
    }

    // Filtro por rango de fechas
    if (!empty($filtro_fecha_inicio)) {
        $where_clauses[] = "ti.fecha >= :fecha_inicio";
        $params[':fecha_inicio'] = $filtro_fecha_inicio;
    }

    if (!empty($filtro_fecha_fin)) {
        $where_clauses[] = "ti.fecha <= :fecha_fin";
        $params[':fecha_fin'] = $filtro_fecha_fin;
    }

    // Filtro por prioridad
    if (!empty($filtro_prioridad)) {
        $where_clauses[] = "ti.prioridad = :prioridad";
        $params[':prioridad'] = $filtro_prioridad;
    }

    // Filtro por última actualización
    if (!empty($filtro_actualizacion)) {
        switch ($filtro_actualizacion) {
            case 'hoy':
                $where_clauses[] = "DATE(ti.fecha_ultima_actualizacion) = CURDATE()";
                break;
            case 'ayer':
                $where_clauses[] = "DATE(ti.fecha_ultima_actualizacion) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
                break;
            case 'semana':
                $where_clauses[] = "ti.fecha_ultima_actualizacion >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                break;
            case 'mes':
                $where_clauses[] = "ti.fecha_ultima_actualizacion >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                break;
            case 'rango':
                if (!empty($filtro_fecha_inicio) && !empty($filtro_fecha_fin)) {
                    $where_clauses[] = "ti.fecha_ultima_actualizacion BETWEEN :actualizacion_inicio AND :actualizacion_fin";
                    $params[':actualizacion_inicio'] = $filtro_fecha_inicio . ' 00:00:00';
                    $params[':actualizacion_fin'] = $filtro_fecha_fin . ' 23:59:59';
                }
                break;
        }
    }

    return !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
}

function construirOrderSQL($ordenar_por)
{
    switch ($ordenar_por) {
        case 'fecha_desc':
            return 'ORDER BY ti.fecha DESC';
        case 'actividad_asc':
            return 'ORDER BY ti.actividad ASC';
        case 'actividad_desc':
            return 'ORDER BY ti.actividad DESC';
        case 'prioridad_asc':
            return 'ORDER BY FIELD(ti.prioridad, "0", "2", "7", "15", "30", "60", "90", "180", "365") ASC';
        case 'prioridad_desc':
            return 'ORDER BY FIELD(ti.prioridad, "365", "180", "90", "60", "30", "15", "7", "2", "0") ASC';
        case 'actualizacion_desc':
            return 'ORDER BY ti.fecha_ultima_actualizacion DESC';
        case 'actualizacion_asc':
            return 'ORDER BY ti.fecha_ultima_actualizacion ASC';
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
    $sql_puestos = "
    SELECT DISTINCT ti.puesto
    FROM tareas_instancias ti
    JOIN subareas sa 
        ON UPPER(TRIM(ti.area)) = UPPER(TRIM(sa.nombre))
    WHERE sa.depa = :id_departamento
    LIMIT 20
        ";

    $stmt = $pdo->prepare($sql_puestos);
    $stmt->execute([
        ':id_departamento' => $id_departamento
    ]);

    $puestos_filtro = $stmt->fetchAll(PDO::FETCH_ASSOC);
    //aqui filtro por puestos


    // Construir consulta con filtros
    $params = [];
    $where_sql = construirWhereSQL(
        $filtro_estado,
        $filtro_categoria,
        $filtro_puesto,
        $filtro_busqueda,
        $filtro_fecha_inicio,
        $filtro_fecha_fin,
        $filtro_prioridad,
        $filtro_actualizacion,
        $pestana_activa,
        $params
    );
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
        ti.prioridad,
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
        'En Proceso' => 0,
        'completadas' => 0,
        'canceladas' => 0
    ];

    foreach ($contadores_raw as $contador) {
        switch ($contador['estado']) {
            case 'Pendiente':
                $contadores_ciclicas['pendientes'] = $contador['cantidad'];
                break;
            case 'En Proceso':
                $contadores_ciclicas['En Proceso'] = $contador['cantidad'];
                break;
            case 'Completada':
                $contadores_ciclicas['completadas'] = $contador['cantidad'];
                break;
            case 'Cancelada':
                $contadores_ciclicas['canceladas'] = $contador['cantidad'];
                break;
        }
    }
}

// ================= TAREAS ÚNICAS =================
// ================= TAREAS ÚNICAS =================
if ($pestana_activa === 'unicas') {
    // Obtener categorías para filtro
    $sql_categorias = "SELECT DISTINCT c.id, c.nombre_cat 
                       FROM tareas t
                       JOIN categoria_servicio_ticket c ON t.id_categoria = c.id
                       WHERE t.activo = 1
                       ORDER BY c.nombre_cat";
    $stmt_categorias = $pdo->prepare($sql_categorias);
    $stmt_categorias->execute();
    $categorias = $stmt_categorias->fetchAll(PDO::FETCH_ASSOC);

    // Obtener responsables para filtro
    $sql_responsables = "SELECT DISTINCT u.Id_Usuario, u.nombre, u.apellido
                        FROM tareas t
                        JOIN usuarios u ON t.id_responsable_ejecucion = u.Id_Usuario
                        WHERE t.activo = 1
                        ORDER BY u.nombre";
    $stmt_responsables = $pdo->prepare($sql_responsables);
    $stmt_responsables->execute();
    $responsables_filtro = $stmt_responsables->fetchAll(PDO::FETCH_ASSOC);

    // Construir consulta con filtros para tareas únicas
    $params = [];
    $where_clauses = [];

    // ELIMINADO: Filtro por área

    // Activo
    $where_clauses[] = "t.activo = 1";

    // Filtro por estado
    if (!empty($filtro_estado) && $filtro_estado !== 'todos') {
        $where_clauses[] = "t.estatus = :estado";
        $params[':estado'] = $filtro_estado;
    }

    // Filtro por categoría
    if (!empty($filtro_categoria)) {
        $where_clauses[] = "t.id_categoria = :categoria";
        $params[':categoria'] = $filtro_categoria;
    }

    // Filtro por responsable
    if (!empty($filtro_puesto)) {
        $where_clauses[] = "t.id_responsable_ejecucion = :responsable";
        $params[':responsable'] = $filtro_puesto;
    }

    // Filtro por tipo de trabajo
    if (!empty($_GET['tipo_trabajo'])) {
        $where_clauses[] = "t.tipo_trabajo = :tipo_trabajo";
        $params[':tipo_trabajo'] = $_GET['tipo_trabajo'];
    }

    // Filtro por rubro
    if (!empty($_GET['rubro'])) {
        $where_clauses[] = "t.rubro = :rubro";
        $params[':rubro'] = $_GET['rubro'];
    }

    // Filtro por búsqueda
    if (!empty($filtro_busqueda)) {
        $where_clauses[] = "(t.titulo LIKE :busqueda OR t.descripcion LIKE :busqueda OR t.codigo_tarea LIKE :busqueda)";
        $params[':busqueda'] = '%' . $filtro_busqueda . '%';
    }

    // Filtro por rango de fechas
    if (!empty($filtro_fecha_inicio)) {
        $where_clauses[] = "DATE(t.fecha_creacion) >= :fecha_inicio";
        $params[':fecha_inicio'] = $filtro_fecha_inicio;
    }

    if (!empty($filtro_fecha_fin)) {
        $where_clauses[] = "DATE(t.fecha_creacion) <= :fecha_fin";
        $params[':fecha_fin'] = $filtro_fecha_fin;
    }

    // Filtro por prioridad
    if (!empty($filtro_prioridad)) {
        $where_clauses[] = "t.prioridad = :prioridad";
        $params[':prioridad'] = $filtro_prioridad;
    }

    $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

    // Ordenamiento
    switch ($ordenar_por) {
        case 'fecha_desc':
            $order_sql = 'ORDER BY t.fecha_creacion DESC';
            break;
        case 'fecha_asc':
            $order_sql = 'ORDER BY t.fecha_creacion ASC';
            break;
        case 'prioridad_asc':
            $order_sql = "ORDER BY FIELD(t.prioridad, 'critica', 'urgente', 'alta', 'media', 'baja') ASC";
            break;
        case 'prioridad_desc':
            $order_sql = "ORDER BY FIELD(t.prioridad, 'baja', 'media', 'alta', 'urgente', 'critica') ASC";
            break;
        default:
            $order_sql = 'ORDER BY t.fecha_creacion DESC';
    }

    // Consulta para conteo total
    $sql_count = "SELECT COUNT(*) as total FROM tareas t $where_sql";
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute($params);
    $total_tareas = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];

    // Calcular paginación
    $total_paginas = ceil($total_tareas / $por_pagina);
    $offset = ($pagina - 1) * $por_pagina;

    // Consulta principal con paginación
    $sql_unicas = "SELECT 
        t.id_tarea,
        t.codigo_tarea,
        t.titulo,
        t.descripcion,
        t.tipo_trabajo,
        t.rubro,
        t.prioridad,
        t.estatus,
        t.fecha_creacion,
        t.fecha_limite,
        t.fecha_completado,
        t.seguimiento,
        -- Información de categoría
        c.nombre_cat as categoria_nombre,
        sc.nombre_sucat as subcategoria_nombre,
        -- Información de ubicación
        d.nombre as donde_nombre,
        dd.nombre as detalle_nombre,
        -- Información de responsables
        u_creador.nombre as creador_nombre,
        u_creador.apellido as creador_apellido,
        u_ejec.nombre as ejecutor_nombre,
        u_ejec.apellido as ejecutor_apellido,
        u_sup.nombre as supervisor_nombre,
        u_sup.apellido as supervisor_apellido,
        -- Área
        a.nombre as area_nombre
    FROM tareas t
    LEFT JOIN categoria_servicio_ticket c ON t.id_categoria = c.id
    LEFT JOIN subcategorias_ticket sc ON t.id_subcategoria = sc.id
    LEFT JOIN donde_ticket d ON t.id_donde = d.id
    LEFT JOIN detalle_donde_ticket dd ON t.id_detalle_donde = dd.id
    LEFT JOIN usuarios u_creador ON t.id_usuario_creador = u_creador.Id_Usuario
    LEFT JOIN usuarios u_ejec ON t.id_responsable_ejecucion = u_ejec.Id_Usuario
    LEFT JOIN usuarios u_sup ON t.id_responsable_supervision = u_sup.Id_Usuario
    LEFT JOIN areas a ON t.id_area = a.id
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

    // Contadores para tareas únicas (también sin filtro de área)
    $sql_contadores = "SELECT 
        estatus,
        COUNT(*) as cantidad
    FROM tareas 
    WHERE activo = 1
    GROUP BY estatus";

    $stmt_contadores = $pdo->prepare($sql_contadores);
    $stmt_contadores->execute();
    $contadores_raw = $stmt_contadores->fetchAll(PDO::FETCH_ASSOC);

    $contadores_unicas = [
        'total' => $total_tareas,
        'pendientes' => 0,
        'En Proceso' => 0,
        'completadas' => 0,
        'canceladas' => 0
    ];

    foreach ($contadores_raw as $contador) {
        switch ($contador['estatus']) {
            case 'Pendiente':
                $contadores_unicas['pendientes'] += $contador['cantidad'];
                break;
            case 'En Proceso':
                $contadores_unicas['En Proceso'] += $contador['cantidad'];
                break;
            case 'completada':
                $contadores_unicas['completadas'] += $contador['cantidad'];
                break;
            case 'cancelada':
                $contadores_unicas['canceladas'] += $contador['cantidad'];
                break;
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

        .estado-pendiente {
            background-color: #FEF3C7;
            color: #92400E;
        }

        .estado-proceso {
            background-color: #DBEAFE;
            color: #1E40AF;
        }

        .estado-completada {
            background-color: #D1FAE5;
            color: #065F46;
        }

        .estado-cancelada {
            background-color: #FEE2E2;
            color: #991B1B;
        }

        .badge-prioridad-0 {
            background-color: #EF4444;
            color: white;
        }

        .badge-prioridad-2 {
            background-color: #F59E0B;
            color: white;
        }

        .badge-prioridad-7 {
            background-color: #10B981;
            color: white;
        }

        .badge-prioridad-15 {
            background-color: #3B82F6;
            color: white;
        }

        .badge-prioridad-30 {
            background-color: #8B5CF6;
            color: white;
        }

        .badge-prioridad-60 {
            background-color: #EC4899;
            color: white;
        }

        .badge-prioridad-90 {
            background-color: #F97316;
            color: white;
        }

        .badge-prioridad-180 {
            background-color: #6B7280;
            color: white;
        }

        .badge-prioridad-365 {
            background-color: #475569;
            color: white;
        }

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
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-overlay {
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
        }

        /* Estilos para filtros responsivos */
        @media (max-width: 768px) {
            .filtro-grid {
                grid-template-columns: 1fr !important;
            }
        }
    </style>
</head>

<body class="bg-gray-50 text-gray-900 font-sans">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <?php include 'includes/menu.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 p-4 md:p-8">
            <!-- Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 md:mb-8 gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Gestión de Tareas</h1>
                    <p class="text-gray-500">Seguimiento y administración de tareas cíclicas y únicas</p>
                </div>
                <div class="flex flex-col md:flex-row items-start md:items-center space-y-3 md:space-y-0 md:space-x-4">
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
            <div class="flex border-b border-gray-200 mb-6 overflow-x-auto">
                <a href="?tab=ciclicas"
                    class="px-4 md:px-6 py-3 text-gray-600 hover:text-blue-600 font-medium whitespace-nowrap <?= $pestana_activa === 'ciclicas' ? 'tab-active' : '' ?>">
                    <i class="fas fa-redo-alt mr-2"></i>Tareas Cíclicas
                    <span class="ml-2 bg-blue-100 text-blue-600 text-xs px-2 py-1 rounded-full">
                        <?= $contadores_ciclicas['total'] ?? 0 ?>
                    </span>
                </a>
                <a href="?tab=unicas"
                    class="px-4 md:px-6 py-3 text-gray-600 hover:text-blue-600 font-medium whitespace-nowrap <?= $pestana_activa === 'unicas' ? 'tab-active' : '' ?>">
                    <i class="fas fa-tasks mr-2"></i>Tareas Únicas
                    <span class="ml-2 bg-blue-100 text-blue-600 text-xs px-2 py-1 rounded-full">
                        <?= $contadores_unicas['total'] ?? 0 ?>
                    </span>
                </a>
            </div>

            <!-- Stats con filtros -->
            <div class="bg-white p-4 md:p-6 rounded-xl border border-gray-200 mb-6 md:mb-8 fade-in">
                <div class="grid grid-cols-2 md:grid-cols-5 gap-3 md:gap-4 mb-6">
                    <?php if ($pestana_activa === 'ciclicas'): ?>
                        <!-- Total - Icono de gráfico circular -->
                        <a href="?tab=ciclicas&estado=todos"
                            class="group bg-white p-3 md:p-4 rounded-xl border border-gray-200 hover:border-purple-300 hover:bg-purple-50/50 transition-all duration-300 <?= $filtro_estado === 'todos' ? 'ring-2 ring-purple-500 ring-offset-2 border-purple-500 bg-purple-50' : '' ?>">
                            <div class="flex items-center justify-between mb-2">
                                <div class="text-lg md:text-xl font-bold text-purple-600"><?= $contadores_ciclicas['total'] ?? 0 ?></div>
                                <div class="w-8 h-8 rounded-lg bg-purple-100 flex items-center justify-center group-hover:scale-110 group-hover:bg-purple-200 transition-all duration-300">
                                    <i class="fas fa-chart-pie text-purple-600 text-sm md:text-base"></i>
                                </div>
                            </div>
                            <div class="text-gray-500 text-xs flex items-center">
                                <i class="fas fa-circle text-[6px] text-purple-500 mr-1"></i>
                                Total
                            </div>
                            <div class="mt-2 h-1 w-12 bg-purple-200 rounded-full group-hover:w-16 transition-all duration-300"></div>
                        </a>

                        <!-- Pendientes - Icono de reloj -->
                        <a href="?tab=ciclicas&estado=Pendiente"
                            class="group bg-white p-3 md:p-4 rounded-xl border border-gray-200 hover:border-yellow-300 hover:bg-yellow-50/50 transition-all duration-300 <?= $filtro_estado === 'Pendiente' ? 'ring-2 ring-yellow-500 ring-offset-2 border-yellow-500 bg-yellow-50' : '' ?>">
                            <div class="flex items-center justify-between mb-2">
                                <div class="text-lg md:text-xl font-bold text-yellow-600"><?= $contadores_ciclicas['pendientes'] ?? 0 ?></div>
                                <div class="w-8 h-8 rounded-lg bg-yellow-100 flex items-center justify-center group-hover:scale-110 group-hover:bg-yellow-200 transition-all duration-300">
                                    <i class="fas fa-hourglass-half text-yellow-600 text-sm md:text-base"></i>
                                </div>
                            </div>
                            <div class="text-gray-500 text-xs flex items-center">
                                <i class="fas fa-circle text-[6px] text-yellow-500 mr-1"></i>
                                Pendientes
                            </div>
                            <div class="mt-2 h-1 w-12 bg-yellow-200 rounded-full group-hover:w-16 transition-all duration-300"></div>
                        </a>

                        <!-- En Proceso - Icono de carga/progresos -->
                        <a href="?tab=ciclicas&estado=En%20Proceso"
                            class="group bg-white p-3 md:p-4 rounded-xl border border-gray-200 hover:border-blue-300 hover:bg-blue-50/50 transition-all duration-300 <?= $filtro_estado === 'En Proceso' ? 'ring-2 ring-blue-500 ring-offset-2 border-blue-500 bg-blue-50' : '' ?>">
                            <div class="flex items-center justify-between mb-2">
                                <div class="text-lg md:text-xl font-bold text-blue-600"><?= $contadores_ciclicas['En Proceso'] ?? 0 ?></div>
                                <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center group-hover:scale-110 group-hover:bg-blue-200 transition-all duration-300">
                                    <i class="fas fa-spinner text-blue-600 text-sm md:text-base"></i>
                                </div>
                            </div>
                            <div class="text-gray-500 text-xs flex items-center">
                                <i class="fas fa-circle text-[6px] text-blue-500 mr-1"></i>
                                En Proceso
                            </div>
                            <div class="mt-2 h-1 w-12 bg-blue-200 rounded-full group-hover:w-16 transition-all duration-300"></div>
                        </a>

                        <!-- Completadas - Icono de check -->
                        <a href="?tab=ciclicas&estado=Completada"
                            class="group bg-white p-3 md:p-4 rounded-xl border border-gray-200 hover:border-green-300 hover:bg-green-50/50 transition-all duration-300 <?= $filtro_estado === 'Completada' ? 'ring-2 ring-green-500 ring-offset-2 border-green-500 bg-green-50' : '' ?>">
                            <div class="flex items-center justify-between mb-2">
                                <div class="text-lg md:text-xl font-bold text-green-600"><?= $contadores_ciclicas['completadas'] ?? 0 ?></div>
                                <div class="w-8 h-8 rounded-lg bg-green-100 flex items-center justify-center group-hover:scale-110 group-hover:bg-green-200 transition-all duration-300">
                                    <i class="fas fa-check-circle text-green-600 text-sm md:text-base"></i>
                                </div>
                            </div>
                            <div class="text-gray-500 text-xs flex items-center">
                                <i class="fas fa-circle text-[6px] text-green-500 mr-1"></i>
                                Completadas
                            </div>
                            <div class="mt-2 h-1 w-12 bg-green-200 rounded-full group-hover:w-16 transition-all duration-300"></div>
                        </a>

                        <!-- Canceladas - Icono de X -->
                        <a href="?tab=ciclicas&estado=Cancelada"
                            class="group bg-white p-3 md:p-4 rounded-xl border border-gray-200 hover:border-red-300 hover:bg-red-50/50 transition-all duration-300 <?= $filtro_estado === 'Cancelada' ? 'ring-2 ring-red-500 ring-offset-2 border-red-500 bg-red-50' : '' ?>">
                            <div class="flex items-center justify-between mb-2">
                                <div class="text-lg md:text-xl font-bold text-red-600"><?= $contadores_ciclicas['canceladas'] ?? 0 ?></div>
                                <div class="w-8 h-8 rounded-lg bg-red-100 flex items-center justify-center group-hover:scale-110 group-hover:bg-red-200 transition-all duration-300">
                                    <i class="fas fa-times-circle text-red-600 text-sm md:text-base"></i>
                                </div>
                            </div>
                            <div class="text-gray-500 text-xs flex items-center">
                                <i class="fas fa-circle text-[6px] text-red-500 mr-1"></i>
                                Canceladas
                            </div>
                            <div class="mt-2 h-1 w-12 bg-red-200 rounded-full group-hover:w-16 transition-all duration-300"></div>
                        </a>

                    <?php elseif ($pestana_activa === 'unicas'): ?>
                        <!-- Total - Icono de dashboard -->
                        <a href="?tab=unicas&estado=todos"
                            class="group bg-white p-3 md:p-4 rounded-xl border border-gray-200 hover:border-blue-300 hover:bg-blue-50/50 transition-all duration-300 <?= $filtro_estado === 'todos' ? 'ring-2 ring-blue-500 ring-offset-2 border-blue-500 bg-blue-50' : '' ?>">
                            <div class="flex items-center justify-between mb-2">
                                <div class="text-lg md:text-xl font-bold text-blue-600"><?= $contadores_unicas['total'] ?? 0 ?></div>
                                <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center group-hover:scale-110 group-hover:bg-blue-200 transition-all duration-300">
                                    <i class="fas fa-th-large text-blue-600 text-sm md:text-base"></i>
                                </div>
                            </div>
                            <div class="text-gray-500 text-xs flex items-center">
                                <i class="fas fa-circle text-[6px] text-blue-500 mr-1"></i>
                                Total
                            </div>
                            <div class="mt-2 h-1 w-12 bg-blue-200 rounded-full group-hover:w-16 transition-all duration-300"></div>
                        </a>

                        <!-- Pendientes - Icono de reloj de arena -->
                        <a href="?tab=unicas&estado=pendiente"
                            class="group bg-white p-3 md:p-4 rounded-xl border border-gray-200 hover:border-yellow-300 hover:bg-yellow-50/50 transition-all duration-300 <?= $filtro_estado === 'pendiente' ? 'ring-2 ring-yellow-500 ring-offset-2 border-yellow-500 bg-yellow-50' : '' ?>">
                            <div class="flex items-center justify-between mb-2">
                                <div class="text-lg md:text-xl font-bold text-yellow-600"><?= $contadores_unicas['pendientes'] ?? 0 ?></div>
                                <div class="w-8 h-8 rounded-lg bg-yellow-100 flex items-center justify-center group-hover:scale-110 group-hover:bg-yellow-200 transition-all duration-300">
                                    <i class="fas fa-clock text-yellow-600 text-sm md:text-base"></i>
                                </div>
                            </div>
                            <div class="text-gray-500 text-xs flex items-center">
                                <i class="fas fa-circle text-[6px] text-yellow-500 mr-1"></i>
                                Pendientes
                            </div>
                            <div class="mt-2 h-1 w-12 bg-yellow-200 rounded-full group-hover:w-16 transition-all duration-300"></div>
                        </a>

                        <!-- En Proceso - Icono de engranaje -->
                        <a href="?tab=unicas&estado=En%20Proceso"
                            class="group bg-white p-3 md:p-4 rounded-xl border border-gray-200 hover:border-blue-300 hover:bg-blue-50/50 transition-all duration-300 <?= $filtro_estado === 'En Proceso' ? 'ring-2 ring-blue-500 ring-offset-2 border-blue-500 bg-blue-50' : '' ?>">
                            <div class="flex items-center justify-between mb-2">
                                <div class="text-lg md:text-xl font-bold text-blue-600"><?= $contadores_unicas['En Proceso'] ?? 0 ?></div>
                                <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center group-hover:scale-110 group-hover:bg-blue-200 transition-all duration-300">
                                    <i class="fas fa-cog text-blue-600 text-sm md:text-base"></i>
                                </div>
                            </div>
                            <div class="text-gray-500 text-xs flex items-center">
                                <i class="fas fa-circle text-[6px] text-blue-500 mr-1"></i>
                                En Proceso
                            </div>
                            <div class="mt-2 h-1 w-12 bg-blue-200 rounded-full group-hover:w-16 transition-all duration-300"></div>
                        </a>

                        <!-- Completadas - Icono de verificación -->
                        <a href="?tab=unicas&estado=completada"
                            class="group bg-white p-3 md:p-4 rounded-xl border border-gray-200 hover:border-green-300 hover:bg-green-50/50 transition-all duration-300 <?= $filtro_estado === 'completada' ? 'ring-2 ring-green-500 ring-offset-2 border-green-500 bg-green-50' : '' ?>">
                            <div class="flex items-center justify-between mb-2">
                                <div class="text-lg md:text-xl font-bold text-green-600"><?= $contadores_unicas['completadas'] ?? 0 ?></div>
                                <div class="w-8 h-8 rounded-lg bg-green-100 flex items-center justify-center group-hover:scale-110 group-hover:bg-green-200 transition-all duration-300">
                                    <i class="fas fa-check-double text-green-600 text-sm md:text-base"></i>
                                </div>
                            </div>
                            <div class="text-gray-500 text-xs flex items-center">
                                <i class="fas fa-circle text-[6px] text-green-500 mr-1"></i>
                                Completadas
                            </div>
                            <div class="mt-2 h-1 w-12 bg-green-200 rounded-full group-hover:w-16 transition-all duration-300"></div>
                        </a>

                        <!-- Canceladas - Icono de prohibido -->
                        <a href="?tab=unicas&estado=cancelada"
                            class="group bg-white p-3 md:p-4 rounded-xl border border-gray-200 hover:border-red-300 hover:bg-red-50/50 transition-all duration-300 <?= $filtro_estado === 'cancelada' ? 'ring-2 ring-red-500 ring-offset-2 border-red-500 bg-red-50' : '' ?>">
                            <div class="flex items-center justify-between mb-2">
                                <div class="text-lg md:text-xl font-bold text-red-600"><?= $contadores_unicas['canceladas'] ?? 0 ?></div>
                                <div class="w-8 h-8 rounded-lg bg-red-100 flex items-center justify-center group-hover:scale-110 group-hover:bg-red-200 transition-all duration-300">
                                    <i class="fas fa-ban text-red-600 text-sm md:text-base"></i>
                                </div>
                            </div>
                            <div class="text-gray-500 text-xs flex items-center">
                                <i class="fas fa-circle text-[6px] text-red-500 mr-1"></i>
                                Canceladas
                            </div>
                            <div class="mt-2 h-1 w-12 bg-red-200 rounded-full group-hover:w-16 transition-all duration-300"></div>
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Filtros Avanzados -->
                <div class="space-y-4">
                    <!-- Fila 1: Filtros principales -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 filtro-grid">
                        <!-- Filtro por Rango de Fechas -->
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Fecha Inicio</label>
                            <input type="date" id="filtro-fecha-inicio"
                                value="<?= htmlspecialchars($filtro_fecha_inicio) ?>"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Fecha Fin</label>
                            <input type="date" id="filtro-fecha-fin"
                                value="<?= htmlspecialchars($filtro_fecha_fin) ?>"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <!-- Filtro por Puesto/Responsable -->
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1"><?= $pestana_activa === 'ciclicas' ? 'Puesto' : 'Responsable' ?></label>
                            <select id="filtro-puesto" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">Todos</option>
                                <?php if ($pestana_activa === 'ciclicas'): ?>
                                    <?php foreach ($puestos_filtro as $puesto): ?>
                                        <option value="<?= $puesto['puesto'] ?>" <?= $filtro_puesto == $puesto['puesto'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($puesto['puesto']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <?php foreach ($responsables_filtro as $resp): ?>
                                        <option value="<?= $resp['Id_Usuario'] ?>" <?= $filtro_puesto == $resp['Id_Usuario'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($resp['nombre'] . ' ' . $resp['apellido']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <!-- Filtro por Prioridad -->
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Prioridad</label>
                            <select id="filtro-prioridad" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">Todas las prioridades</option>
                                <?php if ($pestana_activa === 'ciclicas'): ?>
                                    <?php foreach ($prioridades_disponibles as $key => $prioridad): ?>
                                        <option value="<?= $key ?>" <?= ($filtro_prioridad ?? '') == $key ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($prioridad) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <?php foreach ($prioridades_unicas as $key => $prioridad): ?>
                                        <option value="<?= $key ?>" <?= ($filtro_prioridad ?? '') == $key ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($prioridad) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Fila 2: Filtros secundarios -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 filtro-grid">
                        <!-- Filtro por Estado -->
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Estado</label>
                            <select id="filtro-estado" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">Todos los estados</option>
                                <?php if ($pestana_activa === 'ciclicas'): ?>
                                    <?php foreach ($estados_disponibles as $estado): ?>
                                        <option value="<?= $estado ?>" <?= $filtro_estado === $estado ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($estado) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="pendiente" <?= $filtro_estado === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                                    <option value="En Proceso" <?= $filtro_estado === 'En Proceso' ? 'selected' : '' ?>>En Proceso</option>
                                    <option value="completada" <?= $filtro_estado === 'completada' ? 'selected' : '' ?>>Completada</option>
                                    <option value="cancelada" <?= $filtro_estado === 'cancelada' ? 'selected' : '' ?>>Cancelada</option>
                                <?php endif; ?>
                            </select>
                        </div>

                        <!-- Filtro por Última Actualización -->
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Actualización</label>
                            <select id="filtro-actualizacion" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">Cualquier fecha</option>
                                <option value="hoy" <?= ($filtro_actualizacion ?? '') == 'hoy' ? 'selected' : '' ?>>Hoy</option>
                                <option value="ayer" <?= ($filtro_actualizacion ?? '') == 'ayer' ? 'selected' : '' ?>>Ayer</option>
                                <option value="semana" <?= ($filtro_actualizacion ?? '') == 'semana' ? 'selected' : '' ?>>Esta semana</option>
                                <option value="mes" <?= ($filtro_actualizacion ?? '') == 'mes' ? 'selected' : '' ?>>Este mes</option>
                            </select>
                        </div>

                        <!-- Filtro por Categoría -->
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Categoría</label>
                            <select id="filtro-categoria" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">Todas las categorías</option>
                                <?php if ($pestana_activa === 'ciclicas'): ?>
                                    <?php foreach ($categorias as $categoria): ?>
                                        <option value="<?= htmlspecialchars($categoria) ?>" <?= $filtro_categoria === $categoria ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($categoria) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <?php foreach ($categorias as $categoria): ?>
                                        <option value="<?= $categoria['id'] ?>" <?= $filtro_categoria == $categoria['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($categoria['nombre_cat']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <!-- Filtro por Orden -->
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Ordenar por</label>
                            <select id="filtro-ordenar" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="fecha_asc" <?= $ordenar_por === 'fecha_asc' ? 'selected' : '' ?>>Fecha (Ascendente)</option>
                                <option value="fecha_desc" <?= $ordenar_por === 'fecha_desc' ? 'selected' : '' ?>>Fecha (Descendente)</option>
                                <?php if ($pestana_activa === 'ciclicas'): ?>
                                    <option value="actividad_asc" <?= $ordenar_por === 'actividad_asc' ? 'selected' : '' ?>>Actividad (A-Z)</option>
                                    <option value="actividad_desc" <?= $ordenar_por === 'actividad_desc' ? 'selected' : '' ?>>Actividad (Z-A)</option>
                                <?php endif; ?>
                                <option value="prioridad_asc" <?= $ordenar_por === 'prioridad_asc' ? 'selected' : '' ?>>Prioridad (Alta a Baja)</option>
                                <option value="prioridad_desc" <?= $ordenar_por === 'prioridad_desc' ? 'selected' : '' ?>>Prioridad (Baja a Alta)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Fila para filtros específicos de tareas únicas -->
                    <?php if ($pestana_activa === 'unicas'): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Tipo de Trabajo</label>
                                <select id="filtro-tipo-trabajo" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="">Todos</option>
                                    <option value="interno" <?= ($_GET['tipo_trabajo'] ?? '') == 'interno' ? 'selected' : '' ?>>Interno</option>
                                    <option value="colaboracion" <?= ($_GET['tipo_trabajo'] ?? '') == 'colaboracion' ? 'selected' : '' ?>>Colaboración</option>
                                    <option value="externo" <?= ($_GET['tipo_trabajo'] ?? '') == 'externo' ? 'selected' : '' ?>>Externo</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Rubro</label>
                                <select id="filtro-rubro" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="">Todos</option>
                                    <option value="trabajo_diario" <?= ($_GET['rubro'] ?? '') == 'trabajo_diario' ? 'selected' : '' ?>>Trabajo Diario</option>
                                    <option value="minuta" <?= ($_GET['rubro'] ?? '') == 'minuta' ? 'selected' : '' ?>>Minuta</option>
                                    <option value="planeacion" <?= ($_GET['rubro'] ?? '') == 'planeacion' ? 'selected' : '' ?>>Planeación</option>
                                    <option value="incidente" <?= ($_GET['rubro'] ?? '') == 'incidente' ? 'selected' : '' ?>>Incidente</option>
                                    <option value="proyecto" <?= ($_GET['rubro'] ?? '') == 'proyecto' ? 'selected' : '' ?>>Proyecto</option>
                                    <option value="requerimiento" <?= ($_GET['rubro'] ?? '') == 'requerimiento' ? 'selected' : '' ?>>Requerimiento</option>
                                </select>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Fila 3: Búsqueda -->
                    <div class="grid grid-cols-1 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Buscar</label>
                            <div class="flex">
                                <input type="text" id="filtro-busqueda"
                                    value="<?= htmlspecialchars($filtro_busqueda) ?>"
                                    placeholder="<?= $pestana_activa === 'ciclicas' ? 'Buscar por actividad, categoría o subcategoría...' : 'Buscar por título, descripción o código...' ?>"
                                    class="flex-1 border border-gray-300 rounded-l-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <button onclick="aplicarFiltros()" class="bg-blue-600 text-white px-4 py-2 rounded-r-lg hover:bg-blue-700 transition-colors">
                                    <i class="fas fa-search"></i>
                                </button>
                                <button onclick="limpiarFiltros()" class="ml-2 border border-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-50 transition-colors">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Botones de acción filtros -->
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mt-4 gap-3">
                    <div class="text-sm text-gray-500">
                        <?php if (isset($total_tareas)): ?>
                            Mostrando <?= min($por_pagina, $total_tareas) ?> de <?= $total_tareas ?> tareas
                            <?php if ($pagina > 1): ?>
                                (Página <?= $pagina ?> de <?= $total_paginas ?>)
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if (
                            $filtro_estado || $filtro_categoria || $filtro_puesto || $filtro_busqueda ||
                            $filtro_fecha_inicio || $filtro_fecha_fin || $filtro_prioridad || $filtro_actualizacion
                        ): ?>
                            <span class="ml-2 bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs">
                                <i class="fas fa-filter mr-1"></i>Filtros activos
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="flex space-x-2">
                        <button onclick="aplicarFiltros()" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
                            <i class="fas fa-filter mr-2"></i>Aplicar Filtros
                        </button>
                        <button onclick="limpiarFiltros()" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors">
                            <i class="fas fa-times mr-2"></i>Limpiar todo
                        </button>
                    </div>
                </div>
            </div>

            <!-- Action Bar -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                <div class="flex flex-wrap gap-2">
                    <?php if ($pestana_activa === 'ciclicas'): ?>
                        <button onclick="abrirModalCrearCiclica()" class="px-4 py-2 bg-purple-600 text-white rounded-lg font-medium flex items-center space-x-2 hover:bg-purple-700 transition-colors">
                            <i class="fas fa-plus"></i>
                            <span>Nueva Tarea Cíclica</span>
                        </button>
                        <button onclick="exportarConFiltros()" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg font-medium hover:bg-gray-50 transition-colors">
                            <i class="fas fa-download mr-2"></i>Exportar CSV
                        </button>
                    <?php else: ?>
                        <button onclick="window.location.href='crear_tarea_unica.php'" class="px-4 py-2 bg-blue-600 text-white rounded-lg font-medium flex items-center space-x-2 hover:bg-blue-700 transition-colors">
                            <i class="fas fa-plus"></i>
                            <span>Nueva Tarea Única</span>
                        </button>
                        <button onclick="exportarConFiltros()" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg font-medium hover:bg-gray-50 transition-colors">
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
                                    <th scope="col" class="px-4 md:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actividad</th>
                                    <th scope="col" class="px-4 md:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                                    <th scope="col" class="px-4 md:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Puesto</th>
                                    <th scope="col" class="px-4 md:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prioridad</th>
                                    <th scope="col" class="px-4 md:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                    <th scope="col" class="px-4 md:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Última Actualización</th>
                                    <th scope="col" class="px-4 md:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
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
                                        <td class="px-4 md:px-6 py-4">
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
                                        <td class="px-4 md:px-6 py-4 whitespace-nowrap">
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
                                        <td class="px-4 md:px-6 py-4 whitespace-nowrap">
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
                                        <td class="px-4 md:px-6 py-4 whitespace-nowrap">
                                            <span class="badge <?= $clase_prioridad ?>">
                                                <?= $prioridades_disponibles[$tarea['prioridad'] ?? '15'] ?? 'Bajo' ?>
                                            </span>
                                        </td>

                                        <!-- Estado -->
                                        <td class="px-4 md:px-6 py-4 whitespace-nowrap">
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
                                        <td class="px-4 md:px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php if ($tarea['fecha_ultima_actualizacion']): ?>
                                                <?= date('d M Y H:i', strtotime($tarea['fecha_ultima_actualizacion'])) ?>
                                                <div class="text-xs text-gray-400">
                                                    <?php
                                                    $diff = time() - strtotime($tarea['fecha_ultima_actualizacion']);
                                                    if ($diff < 3600) {
                                                        echo floor($diff / 60) . ' min';
                                                    } elseif ($diff < 86400) {
                                                        echo floor($diff / 3600) . ' horas';
                                                    } else {
                                                        echo floor($diff / 86400) . ' días';
                                                    }
                                                    ?> atrás
                                                </div>
                                            <?php else: ?>
                                                <span class="text-gray-400 italic">Sin actualizar</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Acciones -->
                                        <td class="px-4 md:px-6 py-4 whitespace-nowrap text-sm font-medium">
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
                        <div class="px-4 md:px-6 py-4 border-t border-gray-200">
                            <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                                <div class="text-sm text-gray-500">
                                    Página <?= $pagina ?> de <?= $total_paginas ?>
                                </div>
                                <div class="flex space-x-1">
                                    <!-- Primera página -->
                                    <a href="<?php
                                                $params = [
                                                    'tab' => $pestana_activa,
                                                    'pagina' => 1,
                                                    'estado' => $filtro_estado,
                                                    'categoria' => $filtro_categoria,
                                                    'puesto' => $filtro_puesto,
                                                    'busqueda' => $filtro_busqueda,
                                                    'fecha_inicio' => $filtro_fecha_inicio,
                                                    'fecha_fin' => $filtro_fecha_fin,
                                                    'prioridad' => $filtro_prioridad,
                                                    'actualizacion' => $filtro_actualizacion,
                                                    'ordenar' => $ordenar_por,
                                                    'por_pagina' => $por_pagina
                                                ];
                                                echo '?' . http_build_query(array_filter($params));
                                                ?>" class="px-3 py-1 border border-gray-300 rounded-lg text-sm <?= $pagina == 1 ? 'bg-gray-100 text-gray-400' : 'text-gray-700 hover:bg-gray-50' ?>">
                                        <i class="fas fa-angle-double-left"></i>
                                    </a>

                                    <!-- Página anterior -->
                                    <a href="<?php
                                                $params['pagina'] = max(1, $pagina - 1);
                                                echo '?' . http_build_query(array_filter($params));
                                                ?>" class="px-3 py-1 border border-gray-300 rounded-lg text-sm <?= $pagina == 1 ? 'bg-gray-100 text-gray-400' : 'text-gray-700 hover:bg-gray-50' ?>">
                                        <i class="fas fa-angle-left"></i>
                                    </a>

                                    <!-- Números de página -->
                                    <?php
                                    $inicio = max(1, $pagina - 2);
                                    $fin = min($total_paginas, $pagina + 2);

                                    if ($inicio > 1) {
                                        echo '<span class="px-3 py-1 text-gray-500">...</span>';
                                    }

                                    for ($i = $inicio; $i <= $fin; $i++):
                                        $params['pagina'] = $i;
                                    ?>
                                        <a href="<?= '?' . http_build_query(array_filter($params)) ?>"
                                            class="px-3 py-1 border rounded-lg text-sm <?= $pagina == $i ? 'bg-blue-600 text-white border-blue-600' : 'border-gray-300 text-gray-700 hover:bg-gray-50' ?>">
                                            <?= $i ?>
                                        </a>
                                    <?php endfor;

                                    if ($fin < $total_paginas) {
                                        echo '<span class="px-3 py-1 text-gray-500">...</span>';
                                    }
                                    ?>

                                    <!-- Página siguiente -->
                                    <a href="<?php
                                                $params['pagina'] = min($total_paginas, $pagina + 1);
                                                echo '?' . http_build_query(array_filter($params));
                                                ?>" class="px-3 py-1 border border-gray-300 rounded-lg text-sm <?= $pagina == $total_paginas ? 'bg-gray-100 text-gray-400' : 'text-gray-700 hover:bg-gray-50' ?>">
                                        <i class="fas fa-angle-right"></i>
                                    </a>

                                    <!-- Última página -->
                                    <a href="<?php
                                                $params['pagina'] = $total_paginas;
                                                echo '?' . http_build_query(array_filter($params));
                                                ?>" class="px-3 py-1 border border-gray-300 rounded-lg text-sm <?= $pagina == $total_paginas ? 'bg-gray-100 text-gray-400' : 'text-gray-700 hover:bg-gray-50' ?>">
                                        <i class="fas fa-angle-double-right"></i>
                                    </a>
                                </div>

                                <!-- Selector de items por página -->
                                <div class="flex items-center space-x-2">
                                    <span class="text-sm text-gray-500">Items por página:</span>
                                    <select id="items-por-pagina" class="border border-gray-300 rounded-lg px-2 py-1 text-sm" onchange="cambiarItemsPorPagina(this.value)">
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
                                    <th scope="col" class="px-4 md:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Código / Título</th>
                                    <th scope="col" class="px-4 md:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo / Rubro</th>
                                    <th scope="col" class="px-4 md:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Responsable</th>
                                    <th scope="col" class="px-4 md:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prioridad</th>
                                    <th scope="col" class="px-4 md:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                    <th scope="col" class="px-4 md:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fechas</th>
                                    <th scope="col" class="px-4 md:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200" id="tareas-body">
                                <?php if (!empty($tareas_unicas)): ?>
                                    <?php foreach ($tareas_unicas as $tarea): ?>
                                        <?php
                                        $dias_restantes = $tarea['fecha_limite'] ? floor((strtotime($tarea['fecha_limite']) - time()) / (60 * 60 * 24)) : null;
                                        $clase_prioridad = 'badge-prioridad-' . ($tarea['prioridad'] ?? 'media');
                                        ?>
                                        <tr class="tarea-row <?= $tarea['estatus'] === 'completada' ? 'opacity-75' : '' ?>" data-id="<?= $tarea['id_tarea'] ?>" data-estado="<?= $tarea['estatus'] ?>">
                                            <td class="px-4 md:px-6 py-4">
                                                <div class="flex items-center">
                                                    <div class="w-8 h-8 rounded bg-blue-500 flex items-center justify-center text-white mr-3">
                                                        <i class="fas fa-tasks text-sm"></i>
                                                    </div>
                                                    <div>
                                                        <div class="text-xs font-mono text-blue-600"><?= htmlspecialchars($tarea['codigo_tarea']) ?></div>
                                                        <div class="font-medium text-gray-900"><?= htmlspecialchars(substr($tarea['descripcion'], 0, 50)) ?><?= strlen($tarea['descripcion']) > 50 ? '...' : '' ?></div>
                                                        <div class="text-xs text-gray-500"><?= htmlspecialchars($tarea['categoria_nombre'] ?? 'Sin categoría') ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 md:px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900"><?= ucfirst($tarea['tipo_trabajo'] ?? 'N/A') ?></div>
                                                <div class="text-xs text-gray-500"><?= ucfirst(str_replace('_', ' ', $tarea['rubro'] ?? '')) ?></div>
                                            </td>
                                            <td class="px-4 md:px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900"><?= htmlspecialchars($tarea['ejecutor_nombre'] . ' ' . ($tarea['ejecutor_apellido'] ?? '')) ?></div>
                                                <?php if (!empty($tarea['supervisor_nombre'])): ?>
                                                    <div class="text-xs text-gray-500">Sup: <?= htmlspecialchars($tarea['supervisor_nombre']) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 md:px-6 py-4 whitespace-nowrap">
                                                <span class="badge <?= $clase_prioridad ?>"><?= ucfirst($tarea['prioridad'] ?? 'media') ?></span>
                                            </td>
                                            <td class="px-4 md:px-6 py-4 whitespace-nowrap">
                                                <select class="estado-select w-full border rounded-lg px-3 py-1 text-sm estado-<?= $tarea['estatus'] ?>"
                                                    data-id="<?= $tarea['id_tarea'] ?>"
                                                    data-original="<?= $tarea['estatus'] ?>"
                                                    onchange="cambiarEstado(this)">
                                                    <option value="pendiente" <?= $tarea['estatus'] == 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                                                    <option value="En Proceso" <?= $tarea['estatus'] == 'En Proceso' ? 'selected' : '' ?>>En Proceso</option>
                                                    <option value="completada" <?= $tarea['estatus'] == 'completada' ? 'selected' : '' ?>>Completada</option>
                                                    <option value="cancelada" <?= $tarea['estatus'] == 'cancelada' ? 'selected' : '' ?>>Cancelada</option>
                                                </select>
                                            </td>
                                            <td class="px-4 md:px-6 py-4 whitespace-nowrap text-sm">
                                                <div><?= date('d/m/Y', strtotime($tarea['fecha_creacion'])) ?></div>
                                                <?php if ($tarea['fecha_limite']): ?>
                                                    <div class="text-xs <?= $dias_restantes < 0 ? 'text-red-600' : ($dias_restantes == 0 ? 'text-yellow-600' : 'text-gray-500') ?>">
                                                        <?= $dias_restantes < 0 ? 'Vencida' : ($dias_restantes == 0 ? 'Vence hoy' : "Vence en $dias_restantes días") ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 md:px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <div class="flex space-x-2">
                                                    <button onclick="verDetalleUnica(<?= $tarea['id_tarea'] ?>)"
                                                        class="text-blue-600 hover:text-blue-800 p-1 hover:bg-blue-50 rounded"
                                                        title="Ver detalles">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button onclick="editarTareaUnica(<?= $tarea['id_tarea'] ?>)"
                                                        class="text-green-600 hover:text-green-800 p-1 hover:bg-green-50 rounded"
                                                        title="Editar">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button onclick="eliminarTareaUnica(<?= $tarea['id_tarea'] ?>)"
                                                        class="text-red-600 hover:text-red-800 p-1 hover:bg-red-50 rounded"
                                                        title="Eliminar">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-12">
                                            <i class="fas fa-tasks text-4xl text-gray-300 mb-4"></i>
                                            <h3 class="text-lg font-medium text-gray-900 mb-2">No hay tareas únicas</h3>
                                            <p class="text-gray-500 mb-4"><?= $filtro_estado || $filtro_categoria || $filtro_puesto || $filtro_busqueda ? 'Intenta con otros filtros' : 'Crea tu primera tarea única para comenzar' ?></p>
                                            <button onclick="window.location.href='crear_tarea_unica.php'" class="px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700">
                                                <i class="fas fa-plus mr-2"></i>Crear Tarea Única
                                            </button>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación para tareas únicas -->
                    <?php if ($total_paginas > 1): ?>
                        <div class="px-4 md:px-6 py-4 border-t border-gray-200">
                            <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                                <div class="text-sm text-gray-500">
                                    Página <?= $pagina ?> de <?= $total_paginas ?>
                                </div>
                                <div class="flex space-x-1">
                                    <?php
                                    $params = [
                                        'tab' => $pestana_activa,
                                        'estado' => $filtro_estado,
                                        'categoria' => $filtro_categoria,
                                        'puesto' => $filtro_puesto,
                                        'busqueda' => $filtro_busqueda,
                                        'fecha_inicio' => $filtro_fecha_inicio,
                                        'fecha_fin' => $filtro_fecha_fin,
                                        'prioridad' => $filtro_prioridad,
                                        'actualizacion' => $filtro_actualizacion,
                                        'ordenar' => $ordenar_por,
                                        'por_pagina' => $por_pagina,
                                        'tipo_trabajo' => $_GET['tipo_trabajo'] ?? '',
                                        'rubro' => $_GET['rubro'] ?? ''
                                    ];
                                    ?>
                                    <a href="?<?= http_build_query(array_merge($params, ['pagina' => 1])) ?>" class="px-3 py-1 border border-gray-300 rounded-lg text-sm <?= $pagina == 1 ? 'bg-gray-100 text-gray-400' : 'text-gray-700 hover:bg-gray-50' ?>">
                                        <i class="fas fa-angle-double-left"></i>
                                    </a>
                                    <a href="?<?= http_build_query(array_merge($params, ['pagina' => max(1, $pagina - 1)])) ?>" class="px-3 py-1 border border-gray-300 rounded-lg text-sm <?= $pagina == 1 ? 'bg-gray-100 text-gray-400' : 'text-gray-700 hover:bg-gray-50' ?>">
                                        <i class="fas fa-angle-left"></i>
                                    </a>
                                    <?php for ($i = max(1, $pagina - 2); $i <= min($total_paginas, $pagina + 2); $i++): ?>
                                        <a href="?<?= http_build_query(array_merge($params, ['pagina' => $i])) ?>" class="px-3 py-1 border rounded-lg text-sm <?= $pagina == $i ? 'bg-blue-600 text-white border-blue-600' : 'border-gray-300 text-gray-700 hover:bg-gray-50' ?>">
                                            <?= $i ?>
                                        </a>
                                    <?php endfor; ?>
                                    <a href="?<?= http_build_query(array_merge($params, ['pagina' => min($total_paginas, $pagina + 1)])) ?>" class="px-3 py-1 border border-gray-300 rounded-lg text-sm <?= $pagina == $total_paginas ? 'bg-gray-100 text-gray-400' : 'text-gray-700 hover:bg-gray-50' ?>">
                                        <i class="fas fa-angle-right"></i>
                                    </a>
                                    <a href="?<?= http_build_query(array_merge($params, ['pagina' => $total_paginas])) ?>" class="px-3 py-1 border border-gray-300 rounded-lg text-sm <?= $pagina == $total_paginas ? 'bg-gray-100 text-gray-400' : 'text-gray-700 hover:bg-gray-50' ?>">
                                        <i class="fas fa-angle-double-right"></i>
                                    </a>
                                </div>
                            </div>
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

        // Exportar a CSV con filtros
        function exportarConFiltros() {
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
                if (fila.dataset.estado === 'Completada' || fila.dataset.estado === 'completada') {
                    fila.style.display = mostrar ? 'table-row' : 'none';
                }
            });

            boton.innerHTML = mostrar ?
                '<i class="fas fa-eye-slash mr-2"></i>Ocultar Completadas' :
                '<i class="fas fa-eye mr-2"></i>Mostrar Completadas';
        }

        // Función para aplicar filtros
        function aplicarFiltros() {
            const params = new URLSearchParams();
            params.set('tab', '<?= $pestana_activa ?>');

            const fechaInicio = document.getElementById('filtro-fecha-inicio').value;
            const fechaFin = document.getElementById('filtro-fecha-fin').value;
            const puesto = document.getElementById('filtro-puesto').value;
            const prioridad = document.getElementById('filtro-prioridad').value;
            const estado = document.getElementById('filtro-estado').value;
            const actualizacion = document.getElementById('filtro-actualizacion').value;
            const categoria = document.getElementById('filtro-categoria').value;
            const ordenar = document.getElementById('filtro-ordenar').value;
            const busqueda = document.getElementById('filtro-busqueda').value;

            if (fechaInicio) params.set('fecha_inicio', fechaInicio);
            if (fechaFin) params.set('fecha_fin', fechaFin);
            if (puesto) params.set('puesto', puesto);
            if (prioridad) params.set('prioridad', prioridad);
            if (estado) params.set('estado', estado);
            if (actualizacion) params.set('actualizacion', actualizacion);
            if (categoria) params.set('categoria', categoria);
            if (ordenar) params.set('ordenar', ordenar);
            if (busqueda) params.set('busqueda', busqueda);

            <?php if ($pestana_activa === 'unicas'): ?>
                const tipoTrabajo = document.getElementById('filtro-tipo-trabajo')?.value;
                const rubro = document.getElementById('filtro-rubro')?.value;
                if (tipoTrabajo) params.set('tipo_trabajo', tipoTrabajo);
                if (rubro) params.set('rubro', rubro);
            <?php endif; ?>

            params.set('pagina', '1');
            window.location.href = '?' + params.toString();
        }

        // Función para limpiar todos los filtros
        function limpiarFiltros() {
            window.location.href = '?tab=<?= $pestana_activa ?>';
        }

        // Función para cambiar items por página
        function cambiarItemsPorPagina(valor) {
            const params = new URLSearchParams(window.location.search);
            params.set('por_pagina', valor);
            params.set('pagina', '1');
            window.location.href = '?' + params.toString();
        }

        // Event listeners cuando se carga el DOM
        document.addEventListener('DOMContentLoaded', function() {
            // Configurar fechas por defecto si están vacías
            const hoy = new Date().toISOString().split('T')[0];
            const inicioMes = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0];

            if (!document.getElementById('filtro-fecha-inicio').value) {
                document.getElementById('filtro-fecha-inicio').value = inicioMes;
            }
            if (!document.getElementById('filtro-fecha-fin').value) {
                document.getElementById('filtro-fecha-fin').value = hoy;
            }

            // Permitir buscar con Enter
            document.getElementById('filtro-busqueda').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    aplicarFiltros();
                }
            });
        });

        // Funciones para tareas cíclicas (placeholder)
        function abrirModalCrearCiclica() {
            mostrarToast('Funcionalidad en desarrollo', 'info');
        }

        function verDetalleCiclica(id) {
            mostrarToast('Viendo detalles de tarea cíclica ID: ' + id, 'info');
        }

        function editarTareaCiclica(id) {
            mostrarToast('Editando tarea cíclica ID: ' + id, 'info');
        }

        function eliminarTareaCiclica(id) {
            if (confirm('¿Estás seguro de eliminar esta tarea cíclica?')) {
                mostrarToast('Eliminando tarea cíclica ID: ' + id, 'warning');
            }
        }

        function duplicarTarea(id) {
            if (confirm('¿Duplicar esta tarea?')) {
                mostrarToast('Duplicando tarea ID: ' + id, 'info');
            }
        }

        // Funciones para Tareas Únicas
        function verDetalleUnica(id) {
            window.location.href = 'task/ver_tarea_unica.php?id=' + id;
        }

        function editarTareaUnica(id) {
            window.location.href = 'task/editar_tarea_unica.php?id=' + id;
        }

        function eliminarTareaUnica(id) {
            if (confirm('¿Estás seguro de eliminar esta tarea?\nEsta acción no se puede deshacer.')) {
                fetch('task/eliminar_tarea.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            id: id
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            mostrarToast('Tarea eliminada correctamente', 'success');
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            mostrarToast('Error: ' + data.message, 'error');
                        }
                    })
                    .catch(error => {
                        mostrarToast('Error de conexión', 'error');
                    });
            }
        }
    </script>



    <script>
        // Función para cambiar estado (AJAX)
        function cambiarEstado(selectElement) {
            const tareaId = selectElement.dataset.id;
            const nuevoEstado = selectElement.value;
            const estadoOriginal = selectElement.dataset.original;
            const estadoTexto = selectElement.options[selectElement.selectedIndex].text;

            if (!confirm(`¿Cambiar estado a "${estadoTexto}"?`)) {
                selectElement.value = estadoOriginal;
                return;
            }

            // Deshabilitar select mientras se procesa
            selectElement.disabled = true;

            // Mostrar indicador de carga
            const originalBg = selectElement.style.backgroundColor;
            selectElement.style.backgroundColor = '#f3f4f6';

            fetch('task/actualizar_estado.php', {
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
                        // Actualizar dataset y clases
                        selectElement.dataset.original = nuevoEstado;

                        // Actualizar clase CSS del select
                        selectElement.className = selectElement.className.replace(/estado-\w+/, `estado-${nuevoEstado}`);

                        // Actualizar data-estado de la fila
                        const fila = selectElement.closest('tr');
                        fila.dataset.estado = nuevoEstado;

                        // Si es completada, añadir opacidad
                        if (nuevoEstado === 'completada') {
                            fila.classList.add('opacity-75');
                        } else {
                            fila.classList.remove('opacity-75');
                        }

                        mostrarNotificacion('Estado actualizado correctamente', 'success');
                    } else {
                        mostrarNotificacion('Error: ' + data.message, 'error');
                        selectElement.value = estadoOriginal;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    mostrarNotificacion('Error de conexión', 'error');
                    selectElement.value = estadoOriginal;
                })
                .finally(() => {
                    // Rehabilitar select y restaurar estilo
                    selectElement.disabled = false;
                    selectElement.style.backgroundColor = originalBg;
                });
        }

        // Función para mostrar notificaciones
        function mostrarNotificacion(mensaje, tipo = 'success') {
            // Crear o obtener contenedor de notificaciones
            let notificacion = document.getElementById('notificacion');
            if (!notificacion) {
                notificacion = document.createElement('div');
                notificacion.id = 'notificacion';
                notificacion.className = 'fixed top-4 right-4 z-50 transition-all duration-300 transform translate-x-full';
                document.body.appendChild(notificacion);
            }

            // Configurar colores según tipo
            const colores = {
                success: 'bg-green-500',
                error: 'bg-red-500',
                warning: 'bg-yellow-500',
                info: 'bg-blue-500'
            };

            const iconos = {
                success: 'fa-check-circle',
                error: 'fa-exclamation-circle',
                warning: 'fa-exclamation-triangle',
                info: 'fa-info-circle'
            };

            notificacion.className = `fixed top-4 right-4 z-50 ${colores[tipo]} text-white px-4 py-3 rounded-lg shadow-lg transition-all duration-300`;
            notificacion.innerHTML = `
        <div class="flex items-center">
            <i class="fas ${iconos[tipo]} mr-2"></i>
            <span>${mensaje}</span>
            <button onclick="cerrarNotificacion(this)" class="ml-4 text-white hover:text-gray-200">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;

            // Mostrar con animación
            setTimeout(() => {
                notificacion.classList.remove('translate-x-full');
            }, 100);

            // Auto-cerrar después de 3 segundos
            const timeoutId = setTimeout(() => {
                cerrarNotificacion(notificacion.querySelector('button'));
            }, 3000);

            // Guardar timeout para poder cancelarlo si es necesario
            notificacion.dataset.timeoutId = timeoutId;
        }

        // Función para cerrar notificación
        function cerrarNotificacion(boton) {
            const notificacion = boton.closest('#notificacion');
            if (notificacion) {
                // Cancelar auto-cierre pendiente
                if (notificacion.dataset.timeoutId) {
                    clearTimeout(parseInt(notificacion.dataset.timeoutId));
                }

                notificacion.classList.add('translate-x-full');
                setTimeout(() => {
                    if (notificacion.parentNode) {
                        notificacion.remove();
                    }
                }, 300);
            }
        }

        // Función para aplicar filtros (si los tienes)
        function aplicarFiltros() {
            const params = new URLSearchParams();
            params.set('tab', 'unicas');

            // Recoger valores de los filtros
            const fechaInicio = document.getElementById('filtro-fecha-inicio')?.value;
            const fechaFin = document.getElementById('filtro-fecha-fin')?.value;
            const responsable = document.getElementById('filtro-responsable')?.value;
            const prioridad = document.getElementById('filtro-prioridad')?.value;
            const estado = document.getElementById('filtro-estado')?.value;
            const categoria = document.getElementById('filtro-categoria')?.value;
            const tipoTrabajo = document.getElementById('filtro-tipo-trabajo')?.value;
            const rubro = document.getElementById('filtro-rubro')?.value;
            const busqueda = document.getElementById('filtro-busqueda')?.value;
            const ordenar = document.getElementById('filtro-ordenar')?.value;

            if (fechaInicio) params.set('fecha_inicio', fechaInicio);
            if (fechaFin) params.set('fecha_fin', fechaFin);
            if (responsable) params.set('responsable', responsable);
            if (prioridad) params.set('prioridad', prioridad);
            if (estado) params.set('estado', estado);
            if (categoria) params.set('categoria', categoria);
            if (tipoTrabajo) params.set('tipo_trabajo', tipoTrabajo);
            if (rubro) params.set('rubro', rubro);
            if (busqueda) params.set('busqueda', busqueda);
            if (ordenar) params.set('ordenar', ordenar);

            window.location.href = '?' + params.toString();
        }

        // Función para limpiar filtros
        function limpiarFiltros() {
            window.location.href = '?tab=unicas';
        }



        // Función para eliminar
        function eliminarTareaUnica(id) {
            if (confirm('¿Estás seguro de eliminar esta tarea?')) {
                fetch('task/eliminar_tarea.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            id: id
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            mostrarNotificacion('Tarea eliminada correctamente', 'success');
                            // Eliminar la fila de la tabla
                            const fila = document.querySelector(`tr[data-id="${id}"]`);
                            if (fila) {
                                fila.remove();
                            }
                            // Si no quedan filas, recargar
                            if (document.querySelectorAll('#tareas-body tr').length === 0) {
                                setTimeout(() => location.reload(), 1000);
                            }
                        } else {
                            mostrarNotificacion('Error: ' + data.message, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        mostrarNotificacion('Error de conexión', 'error');
                    });
            }
        }

        // Permitir buscar con Enter
        document.addEventListener('DOMContentLoaded', function() {
            const busquedaInput = document.getElementById('filtro-busqueda');
            if (busquedaInput) {
                busquedaInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        aplicarFiltros();
                    }
                });
            }
        });
    </script>
</body>

</html>
