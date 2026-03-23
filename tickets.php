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

// Obtener información completa del usuario
$sql_usuario = "SELECT u.id_Usuario, u.nombre, u.apellido, u.Id_departamento, u.whatsapp, d.nombre as nombre_departamento 
                FROM usuarios u 
                LEFT JOIN departamentos d ON u.Id_departamento = d.id 
                WHERE u.id_Usuario = :usuario_id";
$stmt_usuario = $pdo->prepare($sql_usuario);
$stmt_usuario->execute([':usuario_id' => $usuario_id]);
$usuario = $stmt_usuario->fetch(PDO::FETCH_ASSOC);
$area_usuario = $usuario['Id_departamento'] ?? null;
$nombre_departamento = $usuario['nombre_departamento'] ?? 'N/A';
$nombre_usuario = $usuario['nombre'] . ' ' . $usuario['apellido'] ?? 'Usuario';
$iniciales = substr($usuario['nombre'] ?? 'U', 0, 1) . substr($usuario['apellido'] ?? 'S', 0, 1);

// Id del usuario logueado
$id_usuario_actual = $_SESSION['user_id'] ?? 1;

// Obtener información del usuario logueado
$sql_user = "SELECT Id_Usuario, Id_departamento, Id_puesto, whatsapp FROM usuarios WHERE Id_Usuario = :id";
$stmt_user = $pdo->prepare($sql_user);
$stmt_user->execute([':id' => $id_usuario_actual]);
$usuario = $stmt_user->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    die("Usuario no encontrado.");
}

$id_departamento = $usuario['Id_departamento'];
$id_puesto_usuario = $usuario['Id_puesto'];

// Obtener jerarquía de puestos
$sql_puestos = "SELECT Id_puesto, Id_superior FROM puestos";
$stmt_puestos = $pdo->query($sql_puestos);
$puestos = $stmt_puestos->fetchAll(PDO::FETCH_ASSOC);

// Crear mapa de jerarquía
$mapa_superiores = [];
foreach ($puestos as $p) {
    $mapa_superiores[$p['Id_puesto']] = $p['Id_superior'];
}

// Función para obtener todos los superiores de un puesto
function obtenerSuperiores($puesto, $mapa_superiores)
{
    $superiores = [];
    while (isset($mapa_superiores[$puesto]) && $mapa_superiores[$puesto] !== null) {
        $puesto = $mapa_superiores[$puesto];
        $superiores[] = $puesto;
    }
    return $superiores;
}

// Función para obtener todos los subordinados de un puesto
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

// Obtener IDs de puestos superiores e inferiores
$puestos_superiores = obtenerSuperiores($id_puesto_usuario, $mapa_superiores);
$puestos_inferiores = obtenerSubordinados($id_puesto_usuario, $mapa_superiores);

// Asegurar que el usuario se incluya a sí mismo
$puestos_superiores[] = $id_puesto_usuario;
$puestos_inferiores[] = $id_puesto_usuario;

// Supervisores
if (!empty($puestos_superiores)) {
    $sql_supervisores = "
    SELECT id, nombre 
    FROM responsable_sup 
    WHERE id_area = ?
    ORDER BY nombre ASC
    ";
    $stmt_supervisores = $pdo->prepare($sql_supervisores);
    $stmt_supervisores->execute([$id_departamento]);
    $supervisores = $stmt_supervisores->fetchAll(PDO::FETCH_ASSOC);
} else {
    $supervisores = [];
}

// Ejecutantes
if (!empty($puestos_inferiores)) {
    $sql_ejecutantes = "
    SELECT id, nombre 
    FROM responsable_ejec 
    WHERE id_area = ?
    ORDER BY nombre ASC
    ";
    $stmt_ejecutantes = $pdo->prepare($sql_ejecutantes);
    $stmt_ejecutantes->execute([$id_departamento]);
    $ejecutantes = $stmt_ejecutantes->fetchAll(PDO::FETCH_ASSOC);
} else {
    $ejecutantes = [];
}

// Estados disponibles
$estados_disponibles = ['Pendiente', 'En Proceso', 'Resuelto', 'Cerrado', 'Cancelado'];

// Obtener contadores reales
$sql_contadores = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN estado = 'Pendiente' THEN 1 ELSE 0 END) as pendientes,
    SUM(CASE WHEN estado = 'Resuelto' THEN 1 ELSE 0 END) as resueltos,
    SUM(CASE WHEN estado = 'En Proceso' THEN 1 ELSE 0 END) as en_proceso,
    SUM(CASE WHEN estado = 'Cerrado' THEN 1 ELSE 0 END) as cerrados,
    SUM(CASE WHEN estado = 'Cancelado' THEN 1 ELSE 0 END) as cancelados
FROM tickets
WHERE area = (SELECT Id_departamento FROM usuarios WHERE id_Usuario = ?)
  AND usuario_id != ?";

$stmt_contadores = $pdo->prepare($sql_contadores);
$stmt_contadores->execute([$usuario_id, $usuario_id]);
$contadores = $stmt_contadores->fetch(PDO::FETCH_ASSOC);

// Obtener todas las áreas y categorías para filtros
$sql_areas = "SELECT id, nombre FROM areas ORDER BY nombre";
$stmt_areas = $pdo->query($sql_areas);
$areas = $stmt_areas->fetchAll(PDO::FETCH_ASSOC);

$sql_categorias = "SELECT id, nombre_cat FROM categoria_servicio_ticket ORDER BY nombre_cat";
$stmt_categorias = $pdo->query($sql_categorias);
$categorias = $stmt_categorias->fetchAll(PDO::FETCH_ASSOC);

// Construir consulta con filtros
$where_conditions = ["t.area = (SELECT Id_departamento FROM usuarios WHERE id_Usuario = ?)", "t.usuario_id != ?"];
$params = [$usuario_id, $usuario_id];

// Aplicar filtros si existen
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
        $where_conditions[] = "(t.folio LIKE ? OR t.descripcion LIKE ? OR u.nombre LIKE ? OR u.apellido LIKE ?)";
        $busqueda = "%" . $_GET['busqueda'] . "%";
        $params[] = $busqueda;
        $params[] = $busqueda;
        $params[] = $busqueda;
        $params[] = $busqueda;
    }
}

$where_clause = implode(" AND ", $where_conditions);

// Obtener todos los tickets con filtros - INCLUIR TELÉFONO DEL SOLICITANTE
$sql = "SELECT t.folio, t.descripcion, t.fecha_creacion, t.estado,
               a.nombre AS area, d.nombre AS donde, dd.nombre AS detalle_donde,
               c.nombre_cat AS categoria, s.nombre_sucat AS subcategoria, 
               u.nombre AS usuario_nombre, u.apellido AS usuario_apellido, u.whatsapp AS usuario_telefono,
               rs.nombre AS supervisor_nombre,
               re.nombre AS ejecutante_nombre,
               t.prioridad, t.id_supervisor, t.id_ejecutante, t.id_ticket,
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

// Función para obtener color según días transcurridos
function getDiasColor($dias, $prioridad)
{
    if ($prioridad == 2 && $dias > 2) return 'text-red-600 font-bold';
    if ($prioridad == 7 && $dias > 7) return 'text-red-600 font-bold';
    if ($prioridad == 15 && $dias > 15) return 'text-red-600 font-bold';
    if ($dias > $prioridad && $prioridad > 0) return 'text-red-600 font-bold';
    if ($dias > $prioridad / 2) return 'text-yellow-600';
    return 'text-green-600';
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flow | Gestión de Tickets</title>
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
                        surface: '#F8FAFC',
                        border: '#E2E8F0'
                    }
                }
            }
        }
    </script>
    <style>
        .ticket-row:hover {
            background-color: #f8fafc;
            transition: background-color 0.2s ease;
        }

        .priority-high {
            border-left: 4px solid #EF4444;
        }

        .priority-medium {
            border-left: 4px solid #F59E0B;
        }

        .priority-low {
            border-left: 4px solid #10B981;
        }

        /* Estilos para los estados */
        .estado-pendiente {
            background-color: #FEF3C7;
            color: #92400E;
        }

        .estado-proceso {
            background-color: #DBEAFE;
            color: #1E40AF;
        }

        .estado-resuelto {
            background-color: #D1FAE5;
            color: #065F46;
        }

        .estado-cerrado {
            background-color: #E5E7EB;
            color: #374151;
        }

        .estado-cancelado {
            background-color: #FEE2E2;
            color: #991B1B;
        }

        /* Notificación toast */
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            z-index: 1000;
            transform: translateX(400px);
            transition: transform 0.3s ease;
        }

        .toast.show {
            transform: translateX(0);
        }

        .toast.success {
            background-color: #10B981;
        }

        .toast.error {
            background-color: #EF4444;
        }

        .toast.warning {
            background-color: #F59E0B;
        }

        .toast.info {
            background-color: #3B82F6;
        }

        /* Filtros animados */
        .filters-panel {
            transition: all 0.3s ease;
        }

        .filters-panel.hidden {
            opacity: 0;
            max-height: 0;
            overflow: hidden;
        }

        /* Tooltips */
        .tooltip {
            position: relative;
            display: inline-block;
        }

        .tooltip .tooltip-text {
            visibility: hidden;
            width: 120px;
            background-color: #1e293b;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 5px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -60px;
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 12px;
        }

        .tooltip:hover .tooltip-text {
            visibility: visible;
            opacity: 1;
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
                    <h1 class="text-2xl font-bold text-gray-900">Gestión de Tickets</h1>
                    <p class="text-gray-500">Seguimiento y administración de solicitudes</p>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-medium">
                        <i class="fas fa-building mr-1"></i> <?= htmlspecialchars($nombre_departamento) ?>
                    </div>
                    <div class="flex items-center space-x-3 bg-white px-4 py-2 rounded-lg border border-gray-200 shadow-sm">
                        <div class="w-8 h-8 bg-gradient-to-r from-blue-600 to-blue-400 rounded-full flex items-center justify-center text-white font-medium">
                            <?= htmlspecialchars($iniciales) ?>
                        </div>
                        <div>
                            <span class="text-gray-700 font-medium"><?= htmlspecialchars($nombre_usuario) ?></span>
                            <div class="text-xs text-gray-500">Usuario</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-8">
                <div class="bg-white p-4 rounded-xl border border-gray-200 hover:shadow-lg transition-all duration-300">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-2xl font-bold text-blue-600"><?= $contadores['total'] ?? 0 ?></div>
                            <div class="text-gray-500 text-xs">Total Tickets</div>
                        </div>
                        <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-ticket-alt text-blue-600"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white p-4 rounded-xl border border-gray-200 hover:shadow-lg transition-all duration-300">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-2xl font-bold text-yellow-600"><?= $contadores['pendientes'] ?? 0 ?></div>
                            <div class="text-gray-500 text-xs">Pendientes</div>
                        </div>
                        <div class="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-clock text-yellow-600"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white p-4 rounded-xl border border-gray-200 hover:shadow-lg transition-all duration-300">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-2xl font-bold text-blue-500"><?= $contadores['en_proceso'] ?? 0 ?></div>
                            <div class="text-gray-500 text-xs">En Proceso</div>
                        </div>
                        <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-sync-alt fa-spin text-blue-500"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white p-4 rounded-xl border border-gray-200 hover:shadow-lg transition-all duration-300">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-2xl font-bold text-green-600"><?= $contadores['resueltos'] ?? 0 ?></div>
                            <div class="text-gray-500 text-xs">Resueltos</div>
                        </div>
                        <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-check-circle text-green-600"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white p-4 rounded-xl border border-gray-200 hover:shadow-lg transition-all duration-300">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-2xl font-bold text-gray-600"><?= $contadores['cerrados'] ?? 0 ?></div>
                            <div class="text-gray-500 text-xs">Cerrados</div>
                        </div>
                        <div class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-lock text-gray-600"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white p-4 rounded-xl border border-gray-200 hover:shadow-lg transition-all duration-300">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-2xl font-bold text-red-600"><?= $contadores['cancelados'] ?? 0 ?></div>
                            <div class="text-gray-500 text-xs">Cancelados</div>
                        </div>
                        <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-times-circle text-red-600"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Panel de Filtros Avanzados -->
            <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6 shadow-sm">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">
                        <i class="fas fa-filter text-blue-500 mr-2"></i>Filtros
                    </h3>
                    <button onclick="toggleFilters()" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                        <i class="fas fa-chevron-up" id="filterToggleIcon"></i>
                    </button>
                </div>

                <form method="GET" action="" id="filterForm">
                    <input type="hidden" name="filtrar" value="1">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <!-- Búsqueda -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Buscar</label>
                            <div class="relative">
                                <input type="text" name="busqueda" value="<?= htmlspecialchars($_GET['busqueda'] ?? '') ?>"
                                    placeholder="Folio, descripción o solicitante..."
                                    class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                            </div>
                        </div>

                        <!-- Estado -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                            <select name="estado" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Todos</option>
                                <?php foreach ($estados_disponibles as $estado): ?>
                                    <option value="<?= $estado ?>" <?= ($_GET['estado'] ?? '') == $estado ? 'selected' : '' ?>>
                                        <?= $estado ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Prioridad -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Prioridad</label>
                            <select name="prioridad" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Todas</option>
                                <option value="2" <?= ($_GET['prioridad'] ?? '') == '2' ? 'selected' : '' ?>>Alta</option>
                                <option value="7" <?= ($_GET['prioridad'] ?? '') == '7' ? 'selected' : '' ?>>Media</option>
                                <option value="15" <?= ($_GET['prioridad'] ?? '') == '15' ? 'selected' : '' ?>>Baja</option>
                            </select>
                        </div>

                        <!-- Área -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Área</label>
                            <select name="area" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Todas</option>
                                <?php foreach ($areas as $area): ?>
                                    <option value="<?= $area['id'] ?>" <?= ($_GET['area'] ?? '') == $area['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($area['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Categoría -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Categoría</label>
                            <select name="categoria" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Todas</option>
                                <?php foreach ($categorias as $categoria): ?>
                                    <option value="<?= $categoria['id'] ?>" <?= ($_GET['categoria'] ?? '') == $categoria['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($categoria['nombre_cat']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Fecha Inicio -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Fecha Inicio</label>
                            <input type="date" name="fecha_inicio" value="<?= htmlspecialchars($_GET['fecha_inicio'] ?? '') ?>"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>

                        <!-- Fecha Fin -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Fecha Fin</label>
                            <input type="date" name="fecha_fin" value="<?= htmlspecialchars($_GET['fecha_fin'] ?? '') ?>"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>

                        <!-- Botones -->
                        <div class="flex items-end space-x-2">
                            <button type="submit" class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                                <i class="fas fa-search mr-2"></i>Aplicar
                            </button>
                            <a href="?" class="flex-1 bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 transition-colors text-center">
                                <i class="fas fa-eraser mr-2"></i>Limpiar
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Action Bar -->
            <div class="flex justify-between items-center mb-6">
                <div class="flex space-x-2">
                    <button onclick="exportToExcel()" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg font-medium hover:bg-gray-50 transition-all">
                        <i class="fas fa-file-excel text-green-600 mr-2"></i>Exportar
                    </button>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-500">
                        <i class="fas fa-ticket-alt mr-1"></i> <?= count($tickets) ?> tickets
                    </span>
                </div>
            </div>

            <!-- Tickets Table -->
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Folio</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descripción</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Solicitante</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supervisor</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ejecutante</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prioridad</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Días</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Evaluación</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contacto</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($tickets)): ?>
                                <tr>
                                    <td colspan="12" class="px-6 py-12 text-center text-gray-500">
                                        <i class="fas fa-inbox text-4xl mb-3 text-gray-300"></i>
                                        <p class="text-lg">No hay tickets para mostrar</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($tickets as $ticket): ?>
                                    <tr class="ticket-row <?= $ticket['prioridad'] == '2' ? 'priority-high' : ($ticket['prioridad'] == '7' ? 'priority-medium' : 'priority-low') ?>">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <span class="bg-gray-100 px-2 py-1 rounded-lg text-gray-700"><?= htmlspecialchars($ticket['folio']) ?></span>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900 max-w-xs">
                                            <div class="truncate" title="<?= htmlspecialchars($ticket['descripcion']) ?>">
                                                <?= htmlspecialchars($ticket['descripcion']) ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= htmlspecialchars($ticket['usuario_nombre'] . ' ' . $ticket['usuario_apellido']) ?>
                                        </td>

                                        <!-- Supervisor -->
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <select class="border border-gray-300 rounded-lg px-2 py-1 text-xs focus:outline-none focus:ring-2 focus:ring-blue-500 supervisor-select"
                                                data-ticket="<?= $ticket['folio'] ?>">
                                                <option value="">Sin supervisor</option>
                                                <?php foreach ($supervisores as $supervisor): ?>
                                                    <option value="<?= $supervisor['id'] ?>"
                                                        <?= $ticket['id_supervisor'] == $supervisor['id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($supervisor['nombre']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>

                                        <!-- Ejecutante -->
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <select class="border border-gray-300 rounded-lg px-2 py-1 text-xs focus:outline-none focus:ring-2 focus:ring-blue-500 ejecutante-select"
                                                data-ticket="<?= $ticket['folio'] ?>">
                                                <option value="">Sin ejecutante</option>
                                                <?php foreach ($ejecutantes as $ejecutante): ?>
                                                    <option value="<?= $ejecutante['id'] ?>"
                                                        <?= $ticket['id_ejecutante'] == $ejecutante['id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($ejecutante['nombre']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>

                                        <!-- Prioridad -->
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <select class="border border-gray-300 rounded-lg px-2 py-1 text-xs focus:outline-none focus:ring-2 focus:ring-blue-500 prioridad-select"
                                                data-ticket="<?= htmlspecialchars($ticket['folio']) ?>">
                                                <option value="2" <?= $ticket['prioridad'] == '2' ? 'selected' : '' ?>>Alta</option>
                                                <option value="7" <?= $ticket['prioridad'] == '7' ? 'selected' : '' ?>>Media</option>
                                                <option value="15" <?= $ticket['prioridad'] == '15' ? 'selected' : '' ?>>Baja</option>
                                            </select>
                                        </td>

                                        <!-- Días transcurridos -->
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium <?= getDiasColor($ticket['dias_transcurridos'], $ticket['prioridad']) ?>">
                                            <?= $ticket['dias_transcurridos'] ?> días
                                        </td>

                                        <!-- Estado -->
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <select class="border border-gray-300 rounded-lg px-2 py-1 text-xs focus:outline-none focus:ring-2 focus:ring-blue-500 estado-select"
                                                data-ticket="<?= htmlspecialchars($ticket['folio']) ?>">
                                                <?php foreach ($estados_disponibles as $estado): ?>
                                                    <option value="<?= $estado ?>"
                                                        <?= $ticket['estado'] === $estado ? 'selected' : '' ?>>
                                                        <?= $estado ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>

                                        <!-- Evaluación -->
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <?php
                                            $stmt_eval = $pdo->prepare("SELECT id_evaluacion FROM evaluaciones_tickets WHERE id_ticket = ?");
                                            $stmt_eval->execute([$ticket['id_ticket']]);
                                            $tiene_evaluacion = $stmt_eval->fetch();
                                            ?>

                                            <?php if ($ticket['estado'] === 'Cerrado'): ?>
                                                <?php if ($tiene_evaluacion): ?>
                                                    <button onclick="verResultadosEvaluacion('<?= ltrim($ticket['folio'], '#') ?>')"
                                                        class="inline-flex items-center px-2 py-1 bg-green-50 text-green-700 rounded-lg border border-green-200 hover:bg-green-100 transition-colors text-xs">
                                                        <i class="fas fa-star text-yellow-400 mr-1"></i>
                                                        Ver
                                                    </button>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center px-2 py-1 bg-gray-50 text-gray-500 rounded-lg border border-gray-200 text-xs">
                                                        Pendiente
                                                    </span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2 py-1 bg-gray-50 text-gray-500 rounded-lg border border-gray-200 text-xs">
                                                    <i class="fas fa-clock mr-1"></i>
                                                    Pendiente
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= date('d/m/Y', strtotime($ticket['fecha_creacion'])) ?>
                                        </td>

                                        <!-- Contacto WhatsApp -->
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <?php if (!empty($ticket['usuario_telefono'])): ?>
                                                <button onclick="enviarWhatsApp('<?= $ticket['usuario_telefono'] ?>', '<?= $ticket['folio'] ?>', '<?= htmlspecialchars($ticket['usuario_nombre'] . ' ' . $ticket['usuario_apellido']) ?>')"
                                                    class="inline-flex items-center px-3 py-1.5 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors text-xs font-medium shadow-sm"
                                                    title="Contactar vía WhatsApp">
                                                    <i class="fab fa-whatsapp mr-1"></i>
                                                    WhatsApp
                                                </button>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-3 py-1.5 bg-gray-100 text-gray-400 rounded-lg text-xs cursor-not-allowed"
                                                    title="No hay número registrado">
                                                    <i class="fas fa-phone-slash mr-1"></i>
                                                    Sin contacto
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Acciones -->
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <button onclick="verDetalleTicket('<?= $ticket['folio'] ?>')"
                                                class="text-blue-600 hover:text-blue-900 tooltip">
                                                <i class="fas fa-eye"></i>
                                                <span class="tooltip-text">Ver detalles</span>
                                            </button>
                                            <!-- <button onclick="verHistorial('<?= $ticket['id_ticket'] ?>')"
                                                class="text-purple-600 hover:text-purple-900 ml-2 tooltip">
                                                <i class="fas fa-history"></i>
                                                <span class="tooltip-text">Ver historial</span>
                                            </button>-->
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="toast hidden"></div>

    <!-- Modal para detalles del ticket -->
    <div id="ticket-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 p-4">
        <div class="bg-white rounded-xl w-full max-w-4xl mx-auto max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold text-gray-900">Detalles del Ticket</h2>
                    <button onclick="cerrarModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div id="modal-content" class="space-y-4">
                    <!-- Contenido cargado dinámicamente -->
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para historial -->
    <div id="historial-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 p-4">
        <div class="bg-white rounded-xl w-full max-w-2xl mx-auto max-h-[80vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold text-gray-900">
                        <i class="fas fa-history text-purple-600 mr-2"></i>
                        Historial del Ticket
                    </h2>
                    <button onclick="cerrarModalHistorial()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div id="historial-content" class="space-y-4">
                    <!-- Contenido cargado dinámicamente -->
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para evaluación -->
    <div id="evaluacion-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 p-4">
        <div class="bg-white rounded-xl w-full max-w-2xl mx-auto">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold text-gray-900">
                        <i class="fas fa-star text-yellow-400 mr-2"></i>
                        Evaluación del Servicio
                    </h2>
                    <button onclick="cerrarModalEvaluacion()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div id="evaluacion-content">
                    <!-- Contenido cargado dinámicamente -->
                </div>
            </div>
        </div>
    </div>

    <script>
        // Función para toggle de filtros
        function toggleFilters() {
            const panel = document.querySelector('.filters-panel');
            const icon = document.getElementById('filterToggleIcon');

            if (panel) {
                panel.classList.toggle('hidden');
                icon.classList.toggle('fa-chevron-up');
                icon.classList.toggle('fa-chevron-down');
            }
        }

        // Función para mostrar notificaciones toast
        function mostrarToast(mensaje, tipo = 'success') {
            const toast = document.getElementById('toast');
            toast.textContent = mensaje;
            toast.className = `toast ${tipo} show`;

            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }

        // Función para enviar WhatsApp
        function enviarWhatsApp(telefono, folio, nombreSolicitante) {
            // Limpiar el número (quitar espacios, guiones, etc.)
            let numeroLimpio = telefono.replace(/\D/g, '');

            // Asegurar que tenga código de país (México 52)
            if (!numeroLimpio.startsWith('52')) {
                numeroLimpio = '52' + numeroLimpio;
            }

            // Mensaje con el formato exacto que mostraste
            const mensaje =
                `👋 *Hola ${nombreSolicitante}*,\n\n` +

                `✨ Soy del *equipo de soporte* y estoy dando seguimiento a tu ticket *${folio}*.\n\n` +

                `📋 *Para poder ayudarte mejor, necesito que me proporciones más información:*\n\n` +

                `🔍 *1. Descripción detallada*\n` +
                `   ¿Podrías explicar con más detalle el problema?\n` +
                `   > ¿Qué estabas haciendo cuando ocurrió?\n` +
                `   > ¿Qué mensajes de error aparecen?\n\n` +

                `⏰ *2. Tiempo de ocurrencia*\n` +
                `   ¿Desde cuándo está pasando esto?\n` +
                `   > ¿Ocurre todo el tiempo o es intermitente?\n\n` +

                `📸 *3. Evidencia (opcional)*\n` +
                `   ¿Tienes capturas de pantalla o videos?\n` +
                `   > Puedes enviarlas en este chat\n\n` +

                `🛠️ *4. Pasos realizados*\n` +
                `   ¿Has intentado algo para resolverlo?\n` +
                `   > Cuéntame qué has probado\n\n` +

                `📍 *5. Información adicional*\n` +
                `   ¿Hay algo más que debamos saber?\n` +
                `   > Detalles de tu equipo, ubicación, etc.\n\n` +

                `━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n` +

                `💡 *Con esta información podré:*\n` +
                `✅ Agilizar la solución de tu ticket\n` +
                `✅ Asignarlo al área correcta\n` +
                `✅ Darte una respuesta más precisa\n\n` +

                `🙏 *¡Gracias por tu ayuda!*\n` +
                `Quedo atento a tu respuesta. 🚀\n\n` +

                `Saludos cordiales,\n` +
                `✨ *Equipo de Soporte* ✨`;

            // Usar api.whatsapp.com que funciona mejor con emojis
            const mensajeCodificado = encodeURIComponent(mensaje);
            const urlWhatsApp = `https://api.whatsapp.com/send?phone=${numeroLimpio}&text=${mensajeCodificado}`;

            // Abrir en nueva ventana
            window.open(urlWhatsApp, '_blank');
        }

        // Función para actualizar ticket
        function actualizarTicket(ticketId, campo, valor) {
            fetch('tickets/actualizar_ticket.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        id: ticketId,
                        campo: campo,
                        valor: valor
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        mostrarToast('Actualizado correctamente', 'success');
                        if (campo === 'estado') {
                            setTimeout(() => location.reload(), 1000);
                        }
                    } else {
                        mostrarToast('Error al actualizar', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    mostrarToast('Error de conexión', 'error');
                });
        }

        // Event listeners para los selects
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.supervisor-select, .ejecutante-select, .prioridad-select, .estado-select').forEach(select => {
                select.addEventListener('change', function() {
                    const ticketId = this.getAttribute('data-ticket');
                    const campo = this.classList.contains('supervisor-select') ? 'id_supervisor' :
                        this.classList.contains('ejecutante-select') ? 'id_ejecutante' :
                        this.classList.contains('prioridad-select') ? 'prioridad' : 'estado';
                    actualizarTicket(ticketId, campo, this.value);
                });
            });
        });

        // Función para ver detalles del ticket
        function verDetalleTicket(folio) {
            const folioEncoded = encodeURIComponent(folio);

            fetch(`tickets/obtener_detalle.php?folio=${folioEncoded}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const modal = document.getElementById('ticket-modal');
                        const content = document.getElementById('modal-content');

                        // Determinar texto de prioridad
                        let prioridadText = 'Baja';
                        if (data.ticket.prioridad == '2') prioridadText = 'Alta';
                        else if (data.ticket.prioridad == '7') prioridadText = 'Media';

                        let html = `
                            <div class="grid grid-cols-2 gap-4">
                                <div class="col-span-2 bg-gradient-to-r from-blue-50 to-indigo-50 p-4 rounded-lg">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <h3 class="font-semibold text-lg">${data.ticket.descripcion || 'Sin descripción'}</h3>
                                            <p class="text-sm text-gray-600 mt-1">
                                                <span class="font-bold">Folio:</span> ${data.ticket.folio}
                                            </p>
                                        </div>
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold 
                                            ${data.ticket.estado === 'Pendiente' ? 'bg-yellow-100 text-yellow-800' : 
                                              data.ticket.estado === 'En Proceso' ? 'bg-blue-100 text-blue-800' : 
                                              data.ticket.estado === 'Resuelto' ? 'bg-green-100 text-green-800' : 
                                              data.ticket.estado === 'Cerrado' ? 'bg-gray-100 text-gray-800' : 
                                              'bg-red-100 text-red-800'}">
                                            ${data.ticket.estado}
                                        </span>
                                    </div>
                                </div>
                                <div class="border rounded-lg p-3">
                                    <p class="text-xs text-gray-500">Solicitante</p>
                                    <p class="font-medium">${data.ticket.usuario_nombre || 'N/A'} ${data.ticket.usuario_apellido || ''}</p>
                                    ${data.ticket.usuario_telefono ? 
                                        `<p class="text-xs text-green-600 mt-1">
                                            <i class="fas fa-phone-alt mr-1"></i>${data.ticket.usuario_telefono}
                                        </p>` : ''}
                                </div>
                                <div class="border rounded-lg p-3">
                                    <p class="text-xs text-gray-500">Área</p>
                                    <p class="font-medium">${data.ticket.area || 'N/A'}</p>
                                </div>
                                <div class="border rounded-lg p-3">
                                    <p class="text-xs text-gray-500">Categoría</p>
                                    <p class="font-medium">${data.ticket.categoria || 'N/A'}</p>
                                </div>
                                <div class="border rounded-lg p-3">
                                    <p class="text-xs text-gray-500">Subcategoría</p>
                                    <p class="font-medium">${data.ticket.subcategoria || 'N/A'}</p>
                                </div>
                                <div class="border rounded-lg p-3">
                                    <p class="text-xs text-gray-500">Ubicación</p>
                                    <p class="font-medium">${data.ticket.donde || 'N/A'} ${data.ticket.detalle_donde ? '- ' + data.ticket.detalle_donde : ''}</p>
                                </div>
                                <div class="border rounded-lg p-3">
                                    <p class="text-xs text-gray-500">Supervisor</p>
                                    <p class="font-medium">${data.ticket.supervisor_nombre || 'No asignado'}</p>
                                </div>
                                <div class="border rounded-lg p-3">
                                    <p class="text-xs text-gray-500">Ejecutante</p>
                                    <p class="font-medium">${data.ticket.ejecutante_nombre || 'No asignado'}</p>
                                </div>
                                <div class="border rounded-lg p-3">
                                    <p class="text-xs text-gray-500">Prioridad</p>
                                    <p class="font-medium">
                                        <span class="px-2 py-1 rounded-full text-xs 
                                            ${data.ticket.prioridad == '2' ? 'bg-red-100 text-red-800' : 
                                              data.ticket.prioridad == '7' ? 'bg-yellow-100 text-yellow-800' : 
                                              'bg-green-100 text-green-800'}">
                                            ${prioridadText}
                                        </span>
                                    </p>
                                </div>
                                <div class="border rounded-lg p-3">
                                    <p class="text-xs text-gray-500">Fecha creación</p>
                                    <p class="font-medium">${new Date(data.ticket.fecha_creacion).toLocaleString() || 'N/A'}</p>
                                </div>
                                <div class="border rounded-lg p-3">
                                    <p class="text-xs text-gray-500">Días transcurridos</p>
                                    <p class="font-medium ${data.ticket.dias_transcurridos > data.ticket.prioridad ? 'text-red-600' : ''}">
                                        ${data.ticket.dias_transcurridos || 0} días
                                    </p>
                                </div>
                            </div>
                        `;

                        content.innerHTML = html;
                        modal.classList.remove('hidden');
                    } else {
                        mostrarToast('Error: ' + (data.error || 'No se pudo cargar el ticket'), 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    mostrarToast('Error de conexión al cargar detalles', 'error');
                });
        }

        // Función para ver historial
        function verHistorial(idTicket) {
            fetch(`tickets/obtener_historial.php?id=${idTicket}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const modal = document.getElementById('historial-modal');
                        const content = document.getElementById('historial-content');

                        let html = '';
                        if (data.historial.length === 0) {
                            html = '<p class="text-center text-gray-500 py-4">No hay historial disponible</p>';
                        } else {
                            html = '<div class="space-y-3">';
                            data.historial.forEach(item => {
                                html += `
                                    <div class="border-l-4 border-blue-500 bg-gray-50 p-3 rounded-r-lg">
                                        <div class="flex justify-between items-start">
                                            <p class="text-sm">${item.descripcion}</p>
                                            <span class="text-xs text-gray-500">${new Date(item.fecha_cambio).toLocaleString()}</span>
                                        </div>
                                        <p class="text-xs text-gray-500 mt-1">Por: ${item.usuario_nombre || 'Sistema'}</p>
                                    </div>
                                `;
                            });
                            html += '</div>';
                        }

                        content.innerHTML = html;
                        modal.classList.remove('hidden');
                    } else {
                        mostrarToast('Error al cargar historial', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    mostrarToast('Error de conexión', 'error');
                });
        }

        // Función para ver resultados de evaluación
        function verResultadosEvaluacion(folio) {
            fetch(`tickets/evaluacion/obtener_evaluacion.php?folio=${folio}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const modal = document.getElementById('evaluacion-modal');
                        const content = document.getElementById('evaluacion-content');

                        const promedio = (data.evaluacion.calificacion_atencion +
                            data.evaluacion.calificacion_tiempo +
                            data.evaluacion.calificacion_solucion) / 3;

                        const estrellas = (promedio) => {
                            let stars = '';
                            for (let i = 1; i <= 5; i++) {
                                if (i <= Math.round(promedio)) {
                                    stars += '<i class="fas fa-star text-yellow-400"></i>';
                                } else {
                                    stars += '<i class="far fa-star text-gray-300"></i>';
                                }
                            }
                            return stars;
                        };

                        const html = `
                            <div class="space-y-4">
                                <div class="text-center">
                                    <div class="text-3xl mb-2">
                                        ${estrellas(promedio)}
                                    </div>
                                    <p class="text-sm text-gray-600">Promedio: ${promedio.toFixed(1)} / 5.0</p>
                                </div>
                                
                                <div class="grid grid-cols-3 gap-3">
                                    <div class="text-center p-3 bg-gray-50 rounded-lg">
                                        <p class="text-xs text-gray-500">Atención</p>
                                        <p class="text-lg font-bold">${data.evaluacion.calificacion_atencion}</p>
                                        <div class="text-yellow-400 text-xs">
                                            ${'★'.repeat(data.evaluacion.calificacion_atencion)}${'☆'.repeat(5 - data.evaluacion.calificacion_atencion)}
                                        </div>
                                    </div>
                                    <div class="text-center p-3 bg-gray-50 rounded-lg">
                                        <p class="text-xs text-gray-500">Tiempo</p>
                                        <p class="text-lg font-bold">${data.evaluacion.calificacion_tiempo}</p>
                                        <div class="text-yellow-400 text-xs">
                                            ${'★'.repeat(data.evaluacion.calificacion_tiempo)}${'☆'.repeat(5 - data.evaluacion.calificacion_tiempo)}
                                        </div>
                                    </div>
                                    <div class="text-center p-3 bg-gray-50 rounded-lg">
                                        <p class="text-xs text-gray-500">Solución</p>
                                        <p class="text-lg font-bold">${data.evaluacion.calificacion_solucion}</p>
                                        <div class="text-yellow-400 text-xs">
                                            ${'★'.repeat(data.evaluacion.calificacion_solucion)}${'☆'.repeat(5 - data.evaluacion.calificacion_solucion)}
                                        </div>
                                    </div>
                                </div>
                                
                                ${data.evaluacion.comentario ? `
                                    <div class="mt-4">
                                        <p class="text-xs text-gray-500 mb-1">Comentario:</p>
                                        <p class="text-sm bg-gray-50 p-3 rounded-lg">${data.evaluacion.comentario}</p>
                                    </div>
                                ` : ''}
                                
                                <p class="text-xs text-gray-400 text-right">
                                    Evaluado el: ${new Date(data.evaluacion.fecha_evaluacion).toLocaleString()}
                                </p>
                            </div>
                        `;

                        content.innerHTML = html;
                        modal.classList.remove('hidden');
                    } else {
                        mostrarToast('Error al cargar evaluación', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    mostrarToast('Error de conexión', 'error');
                });
        }

        // Funciones para cerrar modales
        function cerrarModal() {
            document.getElementById('ticket-modal').classList.add('hidden');
        }

        function cerrarModalHistorial() {
            document.getElementById('historial-modal').classList.add('hidden');
        }

        function cerrarModalEvaluacion() {
            document.getElementById('evaluacion-modal').classList.add('hidden');
        }

        // Función para exportar a Excel
        function exportToExcel() {
            const params = new URLSearchParams(new FormData(document.getElementById('filterForm'))).toString();
            window.location.href = 'tickets/exportar_excel.php?' + params;
        }

        // Cerrar modales con Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                cerrarModal();
                cerrarModalHistorial();
                cerrarModalEvaluacion();
            }
        });
    </script>
</body>

</html>