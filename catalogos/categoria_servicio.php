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

// ============ CRUD PARA CATEGORÍAS DE SERVICIO ============
$mensaje = '';
$accion = $_GET['accion'] ?? '';
$id_editar = $_GET['id'] ?? '';

// Procesar formulario de categorías
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_categorias'])) {
    $nombre_cat = trim($_POST['nombre_cat']);
    $id_area = $_POST['id_area'];
    
    try {
        if ($_POST['accion_categorias'] === 'crear') {
            // Crear nueva categoría
            $sql = "INSERT INTO categoria_servicio_ticket (nombre_cat, id_area) VALUES (?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nombre_cat, $id_area]);
            $mensaje = "success:Categoría creada correctamente";
            
        } elseif ($_POST['accion_categorias'] === 'editar') {
            // Editar categoría existente
            $id = $_POST['id_categoria'];
            $sql = "UPDATE categoria_servicio_ticket SET nombre_cat = ?, id_area = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nombre_cat, $id_area, $id]);
            $mensaje = "success:Categoría actualizada correctamente";
            
        } elseif ($_POST['accion_categorias'] === 'eliminar') {
            // Eliminar categoría
            $id = $_POST['id_categoria'];
            
            // Verificar si hay tickets asociados a esta categoría
            $sql_check = "SELECT COUNT(*) as total FROM tickets WHERE categoria_id = ?";
            $stmt_check = $pdo->prepare($sql_check);
            $stmt_check->execute([$id]);
            $resultado = $stmt_check->fetch(PDO::FETCH_ASSOC);
            
            if ($resultado['total'] > 0) {
                $mensaje = "error:No se puede eliminar la categoría porque tiene tickets asociados";
            } else {
                // Verificar si hay subcategorías asociadas
                $sql_check_sub = "SELECT COUNT(*) as total FROM subcategorias_ticket WHERE id_catServ = ?";
                $stmt_check_sub = $pdo->prepare($sql_check_sub);
                $stmt_check_sub->execute([$id]);
                $resultado_sub = $stmt_check_sub->fetch(PDO::FETCH_ASSOC);
                
                if ($resultado_sub['total'] > 0) {
                    $mensaje = "error:No se puede eliminar la categoría porque tiene subcategorías asociadas";
                } else {
                    $sql = "DELETE FROM categoria_servicio_ticket WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$id]);
                    $mensaje = "success:Categoría eliminada correctamente";
                }
            }
        }
        
        // Redireccionar para evitar reenvío del formulario
        header("Location: " . str_replace("&accion=editar&id=$id_editar", "", $_SERVER['PHP_SELF']));
        exit();
        
    } catch (PDOException $e) {
        $mensaje = "error:Error en la operación: " . $e->getMessage();
    }
}

// Obtener categoría para editar
$categoria_editar = null;
if ($accion === 'editar' && $id_editar) {
    $sql = "SELECT c.*, a.nombre as area_nombre 
            FROM categoria_servicio_ticket c 
            LEFT JOIN areas a ON c.id_area = a.id 
            WHERE c.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_editar]);
    $categoria_editar = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Obtener todas las áreas para los selects
$sql_areas = "SELECT id, nombre FROM areas ORDER BY nombre";
$stmt_areas = $pdo->query($sql_areas);
$areas = $stmt_areas->fetchAll(PDO::FETCH_ASSOC);

// Configuración de paginación y búsqueda
$pagina = $_GET['pagina'] ?? 1;
$busqueda = $_GET['busqueda'] ?? '';
$filtro_area = $_GET['filtro_area'] ?? '';
$registros_por_pagina = 10;
$offset = ($pagina - 1) * $registros_por_pagina;

// Construir consulta con búsqueda y filtros
$where = "WHERE 1=1";
$params = [];

if (!empty($busqueda)) {
    $where .= " AND (c.nombre_cat LIKE ? OR a.nombre LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
}

if (!empty($filtro_area)) {
    $where .= " AND c.id_area = ?";
    $params[] = $filtro_area;
}

// Obtener total de registros
$sql_total = "SELECT COUNT(*) as total 
              FROM categoria_servicio_ticket c 
              LEFT JOIN areas a ON c.id_area = a.id 
              $where";
$stmt_total = $pdo->prepare($sql_total);
$stmt_total->execute($params);
$total_registros = $stmt_total->fetch(PDO::FETCH_ASSOC)['total'];
$total_paginas = ceil($total_registros / $registros_por_pagina);

// Obtener categorías con paginación
$sql_categorias = "SELECT c.*, a.nombre as area_nombre 
                   FROM categoria_servicio_ticket c 
                   LEFT JOIN areas a ON c.id_area = a.id 
                   $where 
                   ORDER BY a.nombre, c.nombre_cat 
                   LIMIT $offset, $registros_por_pagina";
$stmt_categorias = $pdo->prepare($sql_categorias);
$stmt_categorias->execute($params);
$categorias = $stmt_categorias->fetchAll(PDO::FETCH_ASSOC);

// Contadores
$sql_contador_total = "SELECT COUNT(*) as total FROM categoria_servicio_ticket";
$stmt_contador_total = $pdo->query($sql_contador_total);
$total_categorias = $stmt_contador_total->fetch(PDO::FETCH_ASSOC)['total'];

// Contador por área actual del usuario
$sql_contador_area = "SELECT COUNT(*) as total FROM categoria_servicio_ticket WHERE id_area = ?";
$stmt_contador_area = $pdo->prepare($sql_contador_area);
$stmt_contador_area->execute([$area_usuario]);
$categorias_area_actual = $stmt_contador_area->fetch(PDO::FETCH_ASSOC)['total'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flow | Gestión de Categorías de Servicio</title>
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
        .categoria-row:hover {
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
                    <h1 class="text-2xl font-bold text-gray-900">Categorías de Servicio</h1>
                    <p class="text-gray-500">Administración de categorías para tickets</p>
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
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
                <div class="bg-white p-6 rounded-xl border border-gray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-2xl font-bold text-blue-600"><?= $total_categorias ?></div>
                            <div class="text-gray-500 text-sm">Total Categorías</div>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-list-alt text-blue-600 text-xl"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-xl border border-gray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-2xl font-bold text-green-600"><?= count($areas) ?></div>
                            <div class="text-gray-500 text-sm">Áreas Disponibles</div>
                        </div>
                        <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-building text-green-600 text-xl"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-xl border border-gray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-2xl font-bold text-purple-600"><?= $categorias_area_actual ?></div>
                            <div class="text-gray-500 text-sm">Categorías en Mi Área</div>
                        </div>
                        <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-tags text-purple-600 text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Bar -->
            <div class="flex justify-between items-center mb-6">
                <div class="flex space-x-2">
                    <button onclick="abrirModalCrear()" class="px-4 py-2 bg-blue-600 text-white rounded-lg font-medium flex items-center space-x-2 hover:bg-blue-700">
                        <i class="fas fa-plus"></i>
                        <span>Nueva Categoría</span>
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
                            <input type="text" name="busqueda" value="<?= htmlspecialchars($busqueda) ?>" 
                                   placeholder="Buscar categorías..." 
                                   class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                            <input type="hidden" name="pagina" value="1">
                        </div>
                    </form>
                </div>
            </div>

            <!-- Categorías Table -->
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden mb-6">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nombre Categoría</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Área</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($categorias)): ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-4 text-center text-gray-500">
                                        No se encontraron categorías
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($categorias as $categoria): ?>
                                    <tr class="categoria-row">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($categoria['id']) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <div class="font-medium"><?= htmlspecialchars($categoria['nombre_cat']) ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                <?= htmlspecialchars($categoria['area_nombre']) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <button onclick="editarCategoria(<?= $categoria['id'] ?>)" 
                                                    class="text-blue-600 hover:text-blue-900 mr-3"
                                                    title="Editar categoría">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button onclick="eliminarCategoria(<?= $categoria['id'] ?>, '<?= htmlspecialchars($categoria['nombre_cat']) ?>')" 
                                                    class="text-red-600 hover:text-red-900"
                                                    title="Eliminar categoría">
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
                                    <a href="?pagina=<?= $pagina - 1 ?>&busqueda=<?= urlencode($busqueda) ?>&filtro_area=<?= urlencode($filtro_area) ?>" 
                                       class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                        <span class="sr-only">Previous</span>
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                <?php endif; ?>

                                <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                                    <a href="?pagina=<?= $i ?>&busqueda=<?= urlencode($busqueda) ?>&filtro_area=<?= urlencode($filtro_area) ?>" 
                                       class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium <?= $i == $pagina ? 'text-blue-600 bg-blue-50' : 'text-gray-700 hover:bg-gray-50' ?>">
                                        <?= $i ?>
                                    </a>
                                <?php endfor; ?>

                                <?php if ($pagina < $total_paginas): ?>
                                    <a href="?pagina=<?= $pagina + 1 ?>&busqueda=<?= urlencode($busqueda) ?>&filtro_area=<?= urlencode($filtro_area) ?>" 
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

    <!-- Modal para Crear/Editar Categoría -->
    <div id="modal-categoria" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 p-4">
        <div class="bg-white rounded-xl w-full max-w-md mx-auto">
            <div class="p-6">
                <h3 id="modal-titulo" class="text-lg font-bold text-gray-900 mb-4">Nueva Categoría</h3>
                
                <form id="form-categoria" method="POST">
                    <input type="hidden" name="accion_categorias" id="accion_categorias" value="crear">
                    <input type="hidden" name="id_categoria" id="id_categoria" value="">
                    
                    <div class="mb-4">
                        <label for="nombre_cat" class="block text-sm font-medium text-gray-700 mb-2">Nombre de la Categoría *</label>
                        <input type="text" id="nombre_cat" name="nombre_cat" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Ingrese el nombre de la categoría">
                    </div>
                    
                    <div class="mb-6">
                        <label for="id_area" class="block text-sm font-medium text-gray-700 mb-2">Área *</label>
                        <select id="id_area" name="id_area" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Seleccione un área</option>
                            <?php foreach ($areas as $area): ?>
                                <option value="<?= $area['id'] ?>"><?= htmlspecialchars($area['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="cerrarModal()" 
                                class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg font-medium hover:bg-gray-50">
                            Cancelar
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700">
                            <span id="btn-text">Crear Categoría</span>
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
                    ¿Está seguro de que desea eliminar esta categoría?
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

        // Funciones para el modal de categorías
        function abrirModalCrear() {
            document.getElementById('modal-titulo').textContent = 'Nueva Categoría';
            document.getElementById('accion_categorias').value = 'crear';
            document.getElementById('id_categoria').value = '';
            document.getElementById('nombre_cat').value = '';
            document.getElementById('id_area').value = '';
            document.getElementById('btn-text').textContent = 'Crear Categoría';
            document.getElementById('modal-categoria').classList.remove('hidden');
        }

        function editarCategoria(id) {
            // Redirigir a la misma página con parámetros de edición
            window.location.href = `?accion=editar&id=${id}`;
        }

        function eliminarCategoria(id, nombre) {
            document.getElementById('confirmar-texto').textContent = 
                `¿Está seguro de que desea eliminar la categoría "${nombre}"?`;
            
            document.getElementById('btn-confirmar-eliminar').onclick = function() {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="accion_categorias" value="eliminar">
                    <input type="hidden" name="id_categoria" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            };
            
            document.getElementById('modal-confirmar').classList.remove('hidden');
        }

        function cerrarModal() {
            document.getElementById('modal-categoria').classList.add('hidden');
            // Limpiar parámetros de edición en la URL
            if (window.location.href.includes('accion=editar')) {
                window.location.href = window.location.pathname;
            }
        }

        function cerrarModalConfirmar() {
            document.getElementById('modal-confirmar').classList.add('hidden');
        }

        // Cerrar modales al hacer clic fuera
        document.getElementById('modal-categoria').addEventListener('click', function(e) {
            if (e.target === this) {
                cerrarModal();
            }
        });

        document.getElementById('modal-confirmar').addEventListener('click', function(e) {
            if (e.target === this) {
                cerrarModalConfirmar();
            }
        });

        // Auto-abrir modal de edición si hay una categoría para editar
        <?php if ($accion === 'editar' && $categoria_editar): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('modal-titulo').textContent = 'Editar Categoría';
            document.getElementById('accion_categorias').value = 'editar';
            document.getElementById('id_categoria').value = '<?= $categoria_editar['id'] ?>';
            document.getElementById('nombre_cat').value = '<?= addslashes($categoria_editar['nombre_cat']) ?>';
            document.getElementById('id_area').value = '<?= $categoria_editar['id_area'] ?>';
            document.getElementById('btn-text').textContent = 'Actualizar Categoría';
            document.getElementById('modal-categoria').classList.remove('hidden');
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