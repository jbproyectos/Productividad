<?php
session_start();
include '../includes/conexionbd.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$usuario_id = $_SESSION['user_id'];

// Obtener información completa del usuario
$sql_usuario = "SELECT u.id_Usuario, u.nombre, u.apellido, u.Id_departamento, d.nombre as nombre_departamento 
                FROM usuarios u 
                LEFT JOIN areas d ON u.Id_departamento = d.id 
                WHERE u.id_Usuario = :usuario_id";
$stmt_usuario = $pdo->prepare($sql_usuario);
$stmt_usuario->execute([':usuario_id' => $usuario_id]);
$usuario = $stmt_usuario->fetch(PDO::FETCH_ASSOC);
$area_usuario = $usuario['Id_departamento'] ?? null;
$nombre_departamento = $usuario['nombre_departamento'] ?? 'N/A';
$nombre_usuario = $usuario['nombre'] . ' ' . $usuario['apellido'] ?? 'Usuario';
$iniciales = substr($usuario['nombre'] ?? 'U', 0, 1) . substr($usuario['apellido'] ?? 'S', 0, 1);

// ============ CRUD PARA SUBCATEGORÍAS ============
$mensaje = '';
$accion = $_GET['accion'] ?? '';
$id_editar = $_GET['id'] ?? '';

// Procesar formulario de subcategorías
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_subcategorias'])) {
    $nombre_sucat = trim($_POST['nombre_sucat']);
    $id_area = $_POST['id_area'];
    $id_catServ = $_POST['id_catServ'];
    
    try {
        if ($_POST['accion_subcategorias'] === 'crear') {
            // Crear nueva subcategoría
            $sql = "INSERT INTO subcategorias_ticket (nombre_sucat, id_area, id_catServ, fecha_creacion) VALUES (?, ?, ?, NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nombre_sucat, $id_area, $id_catServ]);
            $mensaje = "success:Subcategoría creada correctamente";
            
        } elseif ($_POST['accion_subcategorias'] === 'editar') {
            // Editar subcategoría existente
            $id = $_POST['id_subcategoria'];
            $sql = "UPDATE subcategorias_ticket SET nombre_sucat = ?, id_area = ?, id_catServ = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nombre_sucat, $id_area, $id_catServ, $id]);
            $mensaje = "success:Subcategoría actualizada correctamente";
            
        } elseif ($_POST['accion_subcategorias'] === 'eliminar') {
            // Eliminar subcategoría
            $id = $_POST['id_subcategoria'];
            
            // Verificar si hay tickets asociados a esta subcategoría
            $sql_check = "SELECT COUNT(*) as total FROM tickets WHERE subcategoria_id = ?";
            $stmt_check = $pdo->prepare($sql_check);
            $stmt_check->execute([$id]);
            $resultado = $stmt_check->fetch(PDO::FETCH_ASSOC);
            
            if ($resultado['total'] > 0) {
                $mensaje = "error:No se puede eliminar la subcategoría porque tiene tickets asociados";
            } else {
                $sql = "DELETE FROM subcategorias_ticket WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$id]);
                $mensaje = "success:Subcategoría eliminada correctamente";
            }
        }
        
        // Redireccionar para evitar reenvío del formulario
        header("Location: " . str_replace("&accion=editar&id=$id_editar", "", $_SERVER['PHP_SELF']));
        exit();
        
    } catch (PDOException $e) {
        $mensaje = "error:Error en la operación: " . $e->getMessage();
    }
}

// Obtener subcategoría para editar
$subcategoria_editar = null;
if ($accion === 'editar' && $id_editar) {
    $sql = "SELECT s.*, a.nombre as area_nombre, c.nombre_cat as categoria_nombre
            FROM subcategorias_ticket s 
            LEFT JOIN areas a ON s.id_area = a.id 
            LEFT JOIN categoria_servicio_ticket c ON s.id_catServ = c.id 
            WHERE s.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_editar]);
    $subcategoria_editar = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Obtener todas las áreas para los selects
$sql_areas = "SELECT id, nombre FROM areas ORDER BY nombre";
$stmt_areas = $pdo->query($sql_areas);
$areas = $stmt_areas->fetchAll(PDO::FETCH_ASSOC);

// Obtener todas las categorías para los selects
$sql_categorias = "SELECT id, nombre_cat, id_area FROM categoria_servicio_ticket ORDER BY nombre_cat";
$stmt_categorias = $pdo->query($sql_categorias);
$categorias = $stmt_categorias->fetchAll(PDO::FETCH_ASSOC);

// Configuración de paginación y búsqueda
$pagina = $_GET['pagina'] ?? 1;
$busqueda = $_GET['busqueda'] ?? '';
$filtro_area = $_GET['filtro_area'] ?? '';
$filtro_categoria = $_GET['filtro_categoria'] ?? '';
$registros_por_pagina = 10;
$offset = ($pagina - 1) * $registros_por_pagina;

// Construir consulta con búsqueda y filtros
$where = "WHERE 1=1";
$params = [];

if (!empty($busqueda)) {
    $where .= " AND (s.nombre_sucat LIKE ? OR c.nombre_cat LIKE ? OR a.nombre LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
}

if (!empty($filtro_area)) {
    $where .= " AND s.id_area = ?";
    $params[] = $filtro_area;
}

if (!empty($filtro_categoria)) {
    $where .= " AND s.id_catServ = ?";
    $params[] = $filtro_categoria;
}

// Obtener total de registros
$sql_total = "SELECT COUNT(*) as total 
              FROM subcategorias_ticket s 
              LEFT JOIN areas a ON s.id_area = a.id 
              LEFT JOIN categoria_servicio_ticket c ON s.id_catServ = c.id 
              $where";
$stmt_total = $pdo->prepare($sql_total);
$stmt_total->execute($params);
$total_registros = $stmt_total->fetch(PDO::FETCH_ASSOC)['total'];
$total_paginas = ceil($total_registros / $registros_por_pagina);

// Obtener subcategorías con paginación
$sql_subcategorias = "SELECT s.*, a.nombre as area_nombre, c.nombre_cat as categoria_nombre
                      FROM subcategorias_ticket s 
                      LEFT JOIN areas a ON s.id_area = a.id 
                      LEFT JOIN categoria_servicio_ticket c ON s.id_catServ = c.id 
                      $where 
                      ORDER BY a.nombre, c.nombre_cat, s.nombre_sucat 
                      LIMIT $offset, $registros_por_pagina";
$stmt_subcategorias = $pdo->prepare($sql_subcategorias);
$stmt_subcategorias->execute($params);
$subcategorias = $stmt_subcategorias->fetchAll(PDO::FETCH_ASSOC);

// Contadores
$sql_contador_total = "SELECT COUNT(*) as total FROM subcategorias_ticket";
$stmt_contador_total = $pdo->query($sql_contador_total);
$total_subcategorias = $stmt_contador_total->fetch(PDO::FETCH_ASSOC)['total'];

// Contador por área actual del usuario
$sql_contador_area = "SELECT COUNT(*) as total FROM subcategorias_ticket WHERE id_area = ?";
$stmt_contador_area = $pdo->prepare($sql_contador_area);
$stmt_contador_area->execute([$area_usuario]);
$subcategorias_area_actual = $stmt_contador_area->fetch(PDO::FETCH_ASSOC)['total'];

// Subcategorías por categoría (para estadísticas)
$sql_stats_categorias = "SELECT c.nombre_cat, COUNT(s.id) as total_subcategorias
                         FROM categoria_servicio_ticket c 
                         LEFT JOIN subcategorias_ticket s ON c.id = s.id_catServ 
                         GROUP BY c.id, c.nombre_cat 
                         ORDER BY total_subcategorias DESC 
                         LIMIT 5";
$stmt_stats_categorias = $pdo->query($sql_stats_categorias);
$stats_categorias = $stmt_stats_categorias->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flow | Gestión de Subcategorías</title>
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
        .subcategoria-row:hover {
            background-color: #f8fafc;
            transition: background-color 0.2s ease;
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
        .toast.success { background-color: #10B981; }
        .toast.error { background-color: #EF4444; }
        .toast.warning { background-color: #F59E0B; }
        .toast.info { background-color: #3B82F6; }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 font-sans">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <?php include '../includes/menu.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 p-8">
            <!-- Header -->
            <div class="flex justify-between items-center mb-8">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Gestión de Subcategorías</h1>
                    <p class="text-gray-500">Administración de subcategorías para tickets</p>
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

            <!-- Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                <div class="bg-white p-6 rounded-xl border border-gray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-2xl font-bold text-blue-600"><?= $total_subcategorias ?></div>
                            <div class="text-gray-500 text-sm">Total Subcategorías</div>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-tags text-blue-600 text-xl"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-xl border border-gray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-2xl font-bold text-green-600"><?= count($categorias) ?></div>
                            <div class="text-gray-500 text-sm">Categorías Disponibles</div>
                        </div>
                        <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-list-alt text-green-600 text-xl"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-xl border border-gray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-2xl font-bold text-purple-600"><?= $subcategorias_area_actual ?></div>
                            <div class="text-gray-500 text-sm">En Mi Área</div>
                        </div>
                        <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-layer-group text-purple-600 text-xl"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-xl border border-gray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-2xl font-bold text-orange-600"><?= count($stats_categorias) ?></div>
                            <div class="text-gray-500 text-sm">Categorías Activas</div>
                        </div>
                        <div class="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-chart-pie text-orange-600 text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Estadísticas por Categoría -->
            <?php if (!empty($stats_categorias)): ?>
            <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Subcategorías por Categoría</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-3">
                    <?php foreach ($stats_categorias as $stat): ?>
                        <div class="bg-gradient-to-r from-green-50 to-emerald-50 rounded-lg p-3 border border-green-200">
                            <div class="font-medium text-sm text-gray-900 truncate"><?= htmlspecialchars($stat['nombre_cat']) ?></div>
                            <div class="flex justify-between items-center mt-2">
                                <span class="text-xs text-green-600 font-medium"><?= $stat['total_subcategorias'] ?> subcat.</span>
                                <i class="fas fa-sitemap text-green-400 text-xs"></i>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Action Bar -->
            <div class="flex justify-between items-center mb-6">
                <div class="flex space-x-2">
                    <button onclick="abrirModalCrear()" class="px-4 py-2 bg-blue-600 text-white rounded-lg font-medium flex items-center space-x-2 hover:bg-blue-700">
                        <i class="fas fa-plus"></i>
                        <span>Nueva Subcategoría</span>
                    </button>
                </div>
                <div class="flex items-center space-x-4">
                    <form method="GET" class="flex space-x-3">
                        <div class="relative">
                            <select name="filtro_area" onchange="this.form.submit()" 
                                    class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">Todas las áreas</option>
                                <?php foreach ($areas as $area): ?>
                                    <option value="<?= $area['id'] ?>" <?= $filtro_area == $area['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($area['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <i class="fas fa-building absolute left-3 top-3 text-gray-400"></i>
                        </div>
                        <div class="relative">
                            <select name="filtro_categoria" onchange="this.form.submit()" 
                                    class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">Todas las categorías</option>
                                <?php foreach ($categorias as $categoria): ?>
                                    <option value="<?= $categoria['id'] ?>" <?= $filtro_categoria == $categoria['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($categoria['nombre_cat']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <i class="fas fa-list-alt absolute left-3 top-3 text-gray-400"></i>
                        </div>
                        <div class="relative">
                            <input type="text" name="busqueda" value="<?= htmlspecialchars($busqueda) ?>" 
                                   placeholder="Buscar subcategorías..." 
                                   class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                            <input type="hidden" name="pagina" value="1">
                        </div>
                    </form>
                </div>
            </div>

            <!-- Subcategorías Table -->
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden mb-6">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subcategoría</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Categoría Padre</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Área</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha Creación</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($subcategorias)): ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                        No se encontraron subcategorías
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($subcategorias as $subcategoria): ?>
                                    <tr class="subcategoria-row">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($subcategoria['id']) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <div class="font-medium"><?= htmlspecialchars($subcategoria['nombre_sucat']) ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                <?= htmlspecialchars($subcategoria['categoria_nombre']) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                <?= htmlspecialchars($subcategoria['area_nombre']) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= date('d M Y', strtotime($subcategoria['fecha_creacion'])) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <button onclick="editarSubcategoria(<?= $subcategoria['id'] ?>)" 
                                                    class="text-blue-600 hover:text-blue-900 mr-3"
                                                    title="Editar subcategoría">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button onclick="eliminarSubcategoria(<?= $subcategoria['id'] ?>, '<?= htmlspecialchars($subcategoria['nombre_sucat']) ?>')" 
                                                    class="text-red-600 hover:text-red-900"
                                                    title="Eliminar subcategoría">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_paginas > 1): ?>
                <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                    <div class="flex-1 flex justify-between items-center">
                        <div>
                            <p class="text-sm text-gray-700">
                                Mostrando
                                <span class="font-medium"><?= $offset + 1 ?></span>
                                a
                                <span class="font-medium"><?= min($offset + $registros_por_pagina, $total_registros) ?></span>
                                de
                                <span class="font-medium"><?= $total_registros ?></span>
                                resultados
                            </p>
                        </div>
                        <div>
                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                <?php if ($pagina > 1): ?>
                                    <a href="?pagina=<?= $pagina - 1 ?>&busqueda=<?= urlencode($busqueda) ?>&filtro_area=<?= urlencode($filtro_area) ?>&filtro_categoria=<?= urlencode($filtro_categoria) ?>" 
                                       class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                        <span class="sr-only">Previous</span>
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                <?php endif; ?>

                                <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                                    <a href="?pagina=<?= $i ?>&busqueda=<?= urlencode($busqueda) ?>&filtro_area=<?= urlencode($filtro_area) ?>&filtro_categoria=<?= urlencode($filtro_categoria) ?>" 
                                       class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium <?= $i == $pagina ? 'text-blue-600 bg-blue-50' : 'text-gray-700 hover:bg-gray-50' ?>">
                                        <?= $i ?>
                                    </a>
                                <?php endfor; ?>

                                <?php if ($pagina < $total_paginas): ?>
                                    <a href="?pagina=<?= $pagina + 1 ?>&busqueda=<?= urlencode($busqueda) ?>&filtro_area=<?= urlencode($filtro_area) ?>&filtro_categoria=<?= urlencode($filtro_categoria) ?>" 
                                       class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                        <span class="sr-only">Next</span>
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </nav>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="toast hidden"></div>

    <!-- Modal para Crear/Editar Subcategoría -->
    <div id="modal-subcategoria" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 p-4">
        <div class="bg-white rounded-xl w-full max-w-md mx-auto">
            <div class="p-6">
                <h3 id="modal-titulo" class="text-lg font-bold text-gray-900 mb-4">Nueva Subcategoría</h3>
                
                <form id="form-subcategoria" method="POST">
                    <input type="hidden" name="accion_subcategorias" id="accion_subcategorias" value="crear">
                    <input type="hidden" name="id_subcategoria" id="id_subcategoria" value="">
                    
                    <div class="mb-4">
                        <label for="nombre_sucat" class="block text-sm font-medium text-gray-700 mb-2">
                            Nombre de la Subcategoría *
                        </label>
                        <input type="text" id="nombre_sucat" name="nombre_sucat" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Ej: Error en login, Solicitud de acceso, etc.">
                    </div>
                    
                    <div class="mb-4">
                        <label for="id_area" class="block text-sm font-medium text-gray-700 mb-2">Área *</label>
                        <select id="id_area" name="id_area" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Seleccione un área</option>
                            <?php foreach ($areas as $area): ?>
                                <option value="<?= $area['id'] ?>"><?= htmlspecialchars($area['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-6">
                        <label for="id_catServ" class="block text-sm font-medium text-gray-700 mb-2">Categoría Padre *</label>
                        <select id="id_catServ" name="id_catServ" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Seleccione una categoría</option>
                            <?php foreach ($categorias as $categoria): ?>
                                <option value="<?= $categoria['id'] ?>" data-area="<?= $categoria['id_area'] ?>">
                                    <?= htmlspecialchars($categoria['nombre_cat']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">La categoría debe pertenecer al área seleccionada</p>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="cerrarModal()" 
                                class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg font-medium hover:bg-gray-50">
                            Cancelar
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700">
                            <span id="btn-text">Crear Subcategoría</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal de Confirmación para Eliminar -->
    <div id="modal-confirmar" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 p-4">
        <div class="bg-white rounded-xl w-full max-w-md mx-auto">
            <div class="p-6">
                <div class="flex items-center justify-center w-12 h-12 bg-red-100 rounded-full mx-auto mb-4">
                    <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-900 text-center mb-2">Confirmar Eliminación</h3>
                <p id="confirmar-texto" class="text-gray-600 text-center mb-6">
                    ¿Está seguro de que desea eliminar esta subcategoría?
                </p>
                
                <div class="flex justify-center space-x-3">
                    <button onclick="cerrarModalConfirmar()" 
                            class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg font-medium hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button id="btn-confirmar-eliminar" 
                            class="px-4 py-2 bg-red-600 text-white rounded-lg font-medium hover:bg-red-700">
                        Eliminar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Función para mostrar notificaciones toast
        function mostrarToast(mensaje, tipo = 'success') {
            const toast = document.getElementById('toast');
            toast.textContent = mensaje;
            toast.className = `toast ${tipo} show`;
            
            setTimeout(() => {
                toast.classList.remove('show');
            }, 4000);
        }

        // Mostrar mensaje del servidor si existe
        <?php if ($mensaje): ?>
            <?php 
            list($tipo, $texto) = explode(':', $mensaje, 2); 
            ?>
            mostrarToast('<?= addslashes($texto) ?>', '<?= $tipo ?>');
        <?php endif; ?>

        // Filtrar categorías según el área seleccionada
        function filtrarCategorias() {
            const areaId = document.getElementById('id_area').value;
            const categoriaSelect = document.getElementById('id_catServ');
            const opciones = categoriaSelect.querySelectorAll('option');
            
            opciones.forEach(opcion => {
                if (opcion.value === '') {
                    opcion.style.display = 'block';
                } else {
                    const opcionArea = opcion.getAttribute('data-area');
                    if (areaId === '' || opcionArea === areaId) {
                        opcion.style.display = 'block';
                    } else {
                        opcion.style.display = 'none';
                        if (opcion.selected) {
                            opcion.selected = false;
                        }
                    }
                }
            });
        }

        // Funciones para el modal
        function abrirModalCrear() {
            document.getElementById('modal-titulo').textContent = 'Nueva Subcategoría';
            document.getElementById('accion_subcategorias').value = 'crear';
            document.getElementById('id_subcategoria').value = '';
            document.getElementById('nombre_sucat').value = '';
            document.getElementById('id_area').value = '';
            document.getElementById('id_catServ').value = '';
            document.getElementById('btn-text').textContent = 'Crear Subcategoría';
            document.getElementById('modal-subcategoria').classList.remove('hidden');
            
            // Resetear filtro de categorías
            filtrarCategorias();
        }

        function editarSubcategoria(id) {
            // Redirigir a la misma página con parámetros de edición
            window.location.href = `?accion=editar&id=${id}`;
        }

        function eliminarSubcategoria(id, nombre) {
            document.getElementById('confirmar-texto').textContent = 
                `¿Está seguro de que desea eliminar la subcategoría "${nombre}"?`;
            
            document.getElementById('btn-confirmar-eliminar').onclick = function() {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="accion_subcategorias" value="eliminar">
                    <input type="hidden" name="id_subcategoria" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            };
            
            document.getElementById('modal-confirmar').classList.remove('hidden');
        }

        function cerrarModal() {
            document.getElementById('modal-subcategoria').classList.add('hidden');
            // Limpiar parámetros de edición en la URL
            if (window.location.href.includes('accion=editar')) {
                window.location.href = window.location.pathname;
            }
        }

        function cerrarModalConfirmar() {
            document.getElementById('modal-confirmar').classList.add('hidden');
        }

        // Cerrar modales al hacer clic fuera
        document.getElementById('modal-subcategoria').addEventListener('click', function(e) {
            if (e.target === this) {
                cerrarModal();
            }
        });

        document.getElementById('modal-confirmar').addEventListener('click', function(e) {
            if (e.target === this) {
                cerrarModalConfirmar();
            }
        });

        // Event listener para filtrar categorías cuando cambia el área
        document.getElementById('id_area').addEventListener('change', filtrarCategorias);

        // Auto-abrir modal de edición si hay una subcategoría para editar
        <?php if ($accion === 'editar' && $subcategoria_editar): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('modal-titulo').textContent = 'Editar Subcategoría';
            document.getElementById('accion_subcategorias').value = 'editar';
            document.getElementById('id_subcategoria').value = '<?= $subcategoria_editar['id'] ?>';
            document.getElementById('nombre_sucat').value = '<?= addslashes($subcategoria_editar['nombre_sucat']) ?>';
            document.getElementById('id_area').value = '<?= $subcategoria_editar['id_area'] ?>';
            
            // Primero establecer el área, luego la categoría
            setTimeout(() => {
                document.getElementById('id_catServ').value = '<?= $subcategoria_editar['id_catServ'] ?>';
            }, 100);
            
            document.getElementById('btn-text').textContent = 'Actualizar Subcategoría';
            document.getElementById('modal-subcategoria').classList.remove('hidden');
        });
        <?php endif; ?>

        // Buscar en tiempo real
        let timeoutBusqueda;
        document.querySelector('input[name="busqueda"]').addEventListener('input', function(e) {
            clearTimeout(timeoutBusqueda);
            timeoutBusqueda = setTimeout(() => {
                this.form.submit();
            }, 500);
        });
    </script>
</body>
</html>