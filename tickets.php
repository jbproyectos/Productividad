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
echo $usuario_id;

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

// Id del usuario logueado (puedes cambiarlo si lo tomas de sesión)
$id_usuario_actual =  $_SESSION['user_id'] ?? 1;

// Obtener información del usuario logueado
$sql_user = "SELECT Id_Usuario, Id_departamento, Id_puesto FROM usuarios WHERE Id_Usuario = :id";
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
function obtenerSuperiores($puesto, $mapa_superiores) {
    $superiores = [];
    while (isset($mapa_superiores[$puesto]) && $mapa_superiores[$puesto] !== null) {
        $puesto = $mapa_superiores[$puesto];
        $superiores[] = $puesto;
    }
    return $superiores;
}

// Función para obtener todos los subordinados de un puesto
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

// 🔹 Obtener IDs de puestos superiores e inferiores
$puestos_superiores = obtenerSuperiores($id_puesto_usuario, $mapa_superiores);
$puestos_inferiores = obtenerSubordinados($id_puesto_usuario, $mapa_superiores);

// Asegurar que el usuario se incluya a sí mismo
$puestos_superiores[] = $id_puesto_usuario;
$puestos_inferiores[] = $id_puesto_usuario;

// 🔹 Supervisores → mismo departamento, puestos iguales o superiores
if (!empty($puestos_superiores)) {
    $placeholders_sup = implode(',', array_fill(0, count($puestos_superiores), '?'));
    $sql_supervisores = "
        SELECT Id_Usuario, nombre 
        FROM usuarios 
        WHERE Id_departamento = ? 
        AND Id_puesto IN ($placeholders_sup)
    ";
    $params_sup = array_merge([$id_departamento], $puestos_superiores);
    $stmt_supervisores = $pdo->prepare($sql_supervisores);
    $stmt_supervisores->execute($params_sup);
    $supervisores = $stmt_supervisores->fetchAll(PDO::FETCH_ASSOC);
} else {
    $supervisores = [];
}

// 🔹 Ejecutantes → mismo departamento, puestos iguales o inferiores
if (!empty($puestos_inferiores)) {
    $placeholders_ejec = implode(',', array_fill(0, count($puestos_inferiores), '?'));
    $sql_ejecutantes = "
        SELECT Id_Usuario, nombre 
        FROM usuarios 
        WHERE Id_departamento = ? 
        AND Id_puesto IN ($placeholders_ejec)
    ";
    $params_ejec = array_merge([$id_departamento], $puestos_inferiores);
    $stmt_ejecutantes = $pdo->prepare($sql_ejecutantes);
    $stmt_ejecutantes->execute($params_ejec);
    $ejecutantes = $stmt_ejecutantes->fetchAll(PDO::FETCH_ASSOC);
} else {
    $ejecutantes = [];
}

// Estados disponibles para los tickets
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


// Obtener todos los tickets con sus relaciones para mostrar nombres en lugar de IDs
$sql = "SELECT t.folio, t.descripcion, t.fecha_creacion, t.estado,
               a.nombre AS area, d.nombre AS donde, dd.nombre AS detalle_donde,
               c.nombre_cat AS categoria, s.nombre_sucat AS subcategoria, 
               u.nombre AS usuario_nombre,
               rs.nombre AS supervisor_nombre,
               re.nombre AS ejecutante_nombre,
               t.prioridad, t.id_supervisor, t.id_ejecutante, t.id_ticket
        FROM tickets t
        LEFT JOIN areas a ON t.area = a.id
        LEFT JOIN donde_ticket d ON t.donde = d.id
        LEFT JOIN detalle_donde_ticket dd ON t.detalle_donde = dd.id
        LEFT JOIN categoria_servicio_ticket c ON t.categoria_id = c.id
        LEFT JOIN subcategorias_ticket s ON t.subcategoria_id = s.id
        LEFT JOIN usuarios u ON t.usuario_id = u.id_Usuario
        LEFT JOIN responsable_sup rs ON t.id_supervisor = rs.id
        LEFT JOIN responsable_ejec re ON t.id_ejecutante = re.id
        WHERE t.area = (
            SELECT Id_departamento FROM usuarios WHERE id_Usuario = ?
        )
        AND t.usuario_id != ?
        ORDER BY t.fecha_creacion DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$usuario_id, $usuario_id]);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        .priority-high { border-left: 4px solid #EF4444; }
        .priority-medium { border-left: 4px solid #F59E0B; }
        .priority-low { border-left: 4px solid #10B981; }
        
        /* Estilos para los estados */
        .estado-pendiente { background-color: #FEF3C7; color: #92400E; }
        .estado-proceso { background-color: #DBEAFE; color: #1E40AF; }
        .estado-resuelto { background-color: #D1FAE5; color: #065F46; }
        .estado-cerrado { background-color: #E5E7EB; color: #374151; }
        .estado-cancelado { background-color: #FEE2E2; color: #991B1B; }
        
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
            <div class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-8">
                <div class="bg-white p-4 rounded-xl border border-gray-200">
                    <div class="text-xl font-bold text-blue-600"><?= $contadores['total'] ?? 0 ?></div>
                    <div class="text-gray-500 text-xs">Total</div>
                </div>
                <div class="bg-white p-4 rounded-xl border border-gray-200">
                    <div class="text-xl font-bold text-yellow-600"><?= $contadores['pendientes'] ?? 0 ?></div>
                    <div class="text-gray-500 text-xs">Pendientes</div>
                </div>
                <div class="bg-white p-4 rounded-xl border border-gray-200">
                    <div class="text-xl font-bold text-blue-500"><?= $contadores['en_proceso'] ?? 0 ?></div>
                    <div class="text-gray-500 text-xs">En Proceso</div>
                </div>
                <div class="bg-white p-4 rounded-xl border border-gray-200">
                    <div class="text-xl font-bold text-green-600"><?= $contadores['resueltos'] ?? 0 ?></div>
                    <div class="text-gray-500 text-xs">Resueltos</div>
                </div>
                <div class="bg-white p-4 rounded-xl border border-gray-200">
                    <div class="text-xl font-bold text-gray-600"><?= $contadores['cerrados'] ?? 0 ?></div>
                    <div class="text-gray-500 text-xs">Cerrados</div>
                </div>
                <div class="bg-white p-4 rounded-xl border border-gray-200">
                    <div class="text-xl font-bold text-red-600"><?= $contadores['cancelados'] ?? 0 ?></div>
                    <div class="text-gray-500 text-xs">Cancelados</div>
                </div>
            </div>

            <!-- Action Bar -->
            <div class="flex justify-between items-center mb-6">
                <div class="flex space-x-2">
                    <button class="px-4 py-2 bg-blue-600 text-white rounded-lg font-medium flex items-center space-x-2 hover:bg-blue-700">
                        <i class="fas fa-plus"></i>
                        <span>Nuevo Ticket</span>
                    </button>
                    <button class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg font-medium hover:bg-gray-50">
                        <i class="fas fa-filter mr-2"></i>Filtrar
                    </button>
                    <button class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg font-medium hover:bg-gray-50">
                        <i class="fas fa-download mr-2"></i>Exportar
                    </button>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <input type="text" placeholder="Buscar tickets..." class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                    </div>
                </div>
            </div>

            <!-- Tickets Table -->
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Título</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Solicitante</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supervisa</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Asigna a</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prioridad</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Evalucion</th>
                                
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($tickets as $ticket): ?>
                        <tr class="ticket-row <?php 
                            echo $ticket['prioridad'] === 'Alta' ? 'priority-high' :
                                 ($ticket['prioridad'] === 'Media' ? 'priority-medium' : 'priority-low');
                        ?>">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($ticket['folio']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($ticket['descripcion']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($ticket['usuario_nombre']) ?></td>
                            
                            <!-- Columna Supervisor -->
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <select class="border border-gray-300 rounded-lg px-2 py-1 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent supervisor-select" 
                                        data-ticket="<?= htmlspecialchars($ticket['folio']) ?>">
                                    <option value="">Seleccionar supervisor</option>
                                    <?php foreach ($supervisores as $supervisor): ?>
                                        <option value="<?= $supervisor['Id_Usuario'] ?>" 
                                                <?= $ticket['id_supervisor'] == $supervisor['Id_Usuario'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($supervisor['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            
                            <!-- Columna Ejecutante -->
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <select class="border border-gray-300 rounded-lg px-2 py-1 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent ejecutante-select" 
                                        data-ticket="<?= htmlspecialchars($ticket['folio']) ?>">
                                    <option value="">Seleccionar ejecutante</option>
                                    <?php foreach ($ejecutantes as $ejecutante): ?>
                                        <option value="<?= $ejecutante['Id_Usuario'] ?>" 
                                                <?= $ticket['id_ejecutante'] == $ejecutante['Id_Usuario'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($ejecutante['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            
                            <!-- Columna Prioridad -->
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <select class="border border-gray-300 rounded-lg px-2 py-1 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent prioridad-select" 
                                        data-ticket="<?= htmlspecialchars($ticket['folio']) ?>">
                                    <option value="2" <?= $ticket['prioridad'] == '2' ? 'selected' : '' ?>>Alto - máx 2 días</option>
                                    <option value="7" <?= $ticket['prioridad'] == '7' ? 'selected' : '' ?>>Medio - máx 7 días</option>
                                    <option value="15" <?= $ticket['prioridad'] == '15' ? 'selected' : '' ?>>Bajo - máx 15 días</option>
                                    <option value="30" <?= $ticket['prioridad'] == '30' ? 'selected' : '' ?>>Act Mensual - máx 30 días</option>
                                    <option value="60" <?= $ticket['prioridad'] == '60' ? 'selected' : '' ?>>Act Bimestral - máx 60 días</option>
                                    <option value="90" <?= $ticket['prioridad'] == '90' ? 'selected' : '' ?>>Act Trimestral - máx 90 días</option>
                                    <option value="180" <?= $ticket['prioridad'] == '180' ? 'selected' : '' ?>>Semestral - máx 180 días</option>
                                    <option value="365" <?= $ticket['prioridad'] == '365' ? 'selected' : '' ?>>Anual - máx 365 días</option>
                                    <option value="0" <?= $ticket['prioridad'] == '0' ? 'selected' : '' ?>>Mismo día</option>
                                </select>
                            </td>



                            <!-- Columna Estado -->
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <select class="border border-gray-300 rounded-lg px-2 py-1 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent estado-select" 
                                        data-ticket="<?= htmlspecialchars($ticket['folio']) ?>">
                                    <?php foreach ($estados_disponibles as $estado): ?>
                                        <option value="<?= $estado ?>" 
                                                <?= $ticket['estado'] === $estado ? 'selected' : '' ?>
                                                class="estado-<?= strtolower(str_replace(' ', '-', $estado)) ?>">
                                            <?= $estado ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            
                            <!-- Columna Estado - modificar para incluir ícono de evaluación -->
                           <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <?php 
                                // Verificar si tiene evaluación
                                $stmt_eval = $pdo->prepare("SELECT id_evaluacion FROM evaluaciones_tickets WHERE id_ticket = ?");
                                $stmt_eval->execute([$ticket['id_ticket']]);
                                $tiene_evaluacion = $stmt_eval->fetch();
                                ?>
                            
                                <?php if ($ticket['estado'] === 'Cerrado'): ?>
                                    <?php if ($tiene_evaluacion): ?>
                                        <!-- Ya evaluado - Ver resultados -->
                                        <button onclick="verResultadosEvaluacion('<?= ltrim($ticket['folio'], '#') ?>')" 
                                                class="inline-flex items-center px-3 py-1.5 bg-green-50 text-green-700 rounded-lg border border-green-200 hover:bg-green-100 transition-colors duration-200"
                                                title="Ver evaluación">
                                            <i class="fas fa-star text-yellow-400 mr-2"></i>
                                            <span class="text-xs font-medium">Ver Evaluación</span>
                                        </button>
                                    <?php elseif ($ticket['id_ejecutante'] != $usuario_id): ?>
                            <span class="inline-flex items-center px-3 py-1.5 bg-gray-50 text-gray-500 rounded-lg border border-gray-200 text-xs">
                                            No puedes evaluarte
                                        </span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <!-- Ticket no cerrado -->
                                    <span class="inline-flex items-center px-3 py-1.5 bg-gray-50 text-gray-500 rounded-lg border border-gray-200 text-xs">
                                        <i class="fas fa-clock mr-2"></i>
                                        Pendiente
                                    </span>
                                <?php endif; ?>
                            </td>



                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= date('d M Y', strtotime($ticket['fecha_creacion'])) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <button class="text-green-600 hover:text-green-900 mr-3"><i class="fas fa-eye"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                    <div class="flex-1 flex justify-between items-center">
                        <div>
                            <p class="text-sm text-gray-700">
                                Mostrando
                                <span class="font-medium">1</span>
                                a
                                <span class="font-medium"><?= count($tickets) ?></span>
                                de
                                <span class="font-medium"><?= $contadores['total'] ?? 0 ?></span>
                                resultados
                            </p>
                        </div>
                        <div>
                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                <a href="#" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    <span class="sr-only">Previous</span>
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                                <a href="#" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">1</a>
                                <a href="#" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">2</a>
                                <a href="#" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">3</a>
                                <a href="#" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    <span class="sr-only">Next</span>
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="toast hidden"></div>

    <!-- Modal for Ticket Details -->
    <div id="ticket-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 p-4">
        <div class="bg-white rounded-xl w-full max-w-4xl mx-auto max-h-[90vh] overflow-hidden">
            <!-- Modal content would go here -->
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
            }, 3000);
        }

        // Función para obtener el texto descriptivo del cambio
        function obtenerTextoCambio(campo, valor) {
            const textos = {
                'id_supervisor': 'supervisor asignado',
                'id_ejecutante': 'ejecutante asignado', 
                'prioridad': `prioridad cambiada a ${valor}`,
                'estado': `estado cambiado a ${valor}`
            };
            return textos[campo] || 'cambio realizado';
        }

        // Función para obtener el tipo de notificación según el campo
        function obtenerTipoNotificacion(campo) {
            const tipos = {
                'id_supervisor': 'info',
                'id_ejecutante': 'info',
                'prioridad': 'warning', 
                'estado': 'success'
            };
            return tipos[campo] || 'info';
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Función para actualizar datos via AJAX
            function actualizarTicket(ticketId, campo, valor) {
                const textoCambio = obtenerTextoCambio(campo, valor);
                const tipoNotificacion = obtenerTipoNotificacion(campo);
                
                fetch('tickets/actualizar_ticket.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        ticket_id: ticketId,
                        campo: campo,
                        valor: valor
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        mostrarToast(`Ticket ${ticketId}: ${textoCambio} correctamente`, tipoNotificacion);
                        
                        // Actualizar contadores si es un cambio de estado
                        if (campo === 'estado') {
                            // Aquí podrías recargar los contadores via AJAX si quieres
                            console.log('Estado cambiado, podrías actualizar contadores aquí');
                        }
                    } else {
                        mostrarToast(`Error al actualizar ticket: ${data.error}`, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    mostrarToast('Error de conexión al actualizar ticket', 'error');
                });
            }

            // Event listeners para los selects
            document.querySelectorAll('.supervisor-select').forEach(select => {
                select.addEventListener('change', function() {
                    const ticketId = this.getAttribute('data-ticket');
                    const supervisorId = this.value;
                    const supervisorNombre = this.options[this.selectedIndex].text;
                    actualizarTicket(ticketId, 'id_supervisor', supervisorId);
                });
            });

            document.querySelectorAll('.ejecutante-select').forEach(select => {
                select.addEventListener('change', function() {
                    const ticketId = this.getAttribute('data-ticket');
                    const ejecutanteId = this.value;
                    const ejecutanteNombre = this.options[this.selectedIndex].text;
                    actualizarTicket(ticketId, 'id_ejecutante', ejecutanteId);
                });
            });

            document.querySelectorAll('.prioridad-select').forEach(select => {
                select.addEventListener('change', function() {
                    const ticketId = this.getAttribute('data-ticket');
                    const prioridad = this.value;
                    
                    // Actualizar clase de prioridad en la fila
                    const fila = this.closest('tr');
                    fila.className = fila.className.replace(/priority-(high|medium|low)/, '');
                    fila.classList.add(`priority-${prioridad.toLowerCase()}`);
                    
                    actualizarTicket(ticketId, 'prioridad', prioridad);
                });
            });

            document.querySelectorAll('.estado-select').forEach(select => {
                select.addEventListener('change', function() {
                    const ticketId = this.getAttribute('data-ticket');
                    const estado = this.value;
                    
                    // Aplicar clase de estado al select
                    this.className = this.className.replace(/estado-(pendiente|proceso|resuelto|cerrado|cancelado)/, '');
                    this.classList.add(`estado-${estado.toLowerCase().replace(' ', '-')}`);
                    
                    actualizarTicket(ticketId, 'estado', estado);
                });
            });

            // Aplicar clases de estado a los selects al cargar la página
            document.querySelectorAll('.estado-select').forEach(select => {
                const estado = select.value;
                select.classList.add(`estado-${estado.toLowerCase().replace(' ', '-')}`);
            });

            // Add click event to view buttons
            document.querySelectorAll('button .fa-eye').forEach(button => {
                button.closest('button').addEventListener('click', function() {
                    document.getElementById('ticket-modal').classList.remove('hidden');
                });
            });

            // Close modal when clicking outside
            document.getElementById('ticket-modal').addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.add('hidden');
                }
            });
        });
    </script>
    <script>
      let calificaciones = { 
    atencion: 0, 
    tiempo: 0, 
    solucion: 0, 
    comunicacion: 0 
};

const textosCalificacion = {
    1: 'Muy malo',
    2: 'Malo', 
    3: 'Regular',
    4: 'Bueno',
    5: 'Excelente'
};

function calificar(tipo, puntuacion) {
    calificaciones[tipo] = puntuacion;
    actualizarEstrellas(tipo, puntuacion);
    document.getElementById('calificacion_' + tipo).value = puntuacion;
    document.getElementById(tipo + '-text').textContent = textosCalificacion[puntuacion];
    document.getElementById(tipo + '-text').className = `text-xs font-medium ${puntuacion >= 4 ? 'text-green-600' : puntuacion >= 3 ? 'text-yellow-600' : 'text-red-600'}`;
    verificarCalificaciones();
}

function actualizarEstrellas(tipo, puntuacion) {
    const elementos = document.querySelectorAll('.star-' + tipo);
    
    elementos.forEach((star, index) => {
        const starIndex = index + 1;
        
        if (starIndex <= puntuacion) {
            star.classList.add('active');
            star.style.color = '#f59e0b'; // yellow-500
        } else {
            star.classList.remove('active');
            star.style.color = '#d1d5db'; // gray-300
        }
    });
}

function verificarCalificaciones() {
    const todasCalificadas = Object.values(calificaciones).every(val => val > 0);
    document.getElementById('btnEnviarEvaluacion').disabled = !todasCalificadas;
}

// Detectar clics en el botón Enviar dentro del modal, incluso si se genera dinámicamente
document.addEventListener('click', function(e) {
    if (e.target && e.target.id === 'btnEnviarEvaluacion') {
        console.log('Click detectado en botón evaluación');
        
        const form = document.getElementById('formEvaluacion');
        const btn = e.target;
        const originalText = btn.innerHTML;

        btn.innerHTML = 'Enviando...';
        btn.disabled = true;

        const formData = new FormData(form);

        fetch('tickets/evaluacion/guardar_evaluacion.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (typeof mostrarToast === 'function') {
                    mostrarToast('✅ Evaluación guardada', 'success');
                }
                cerrarModalEvaluacion();
                setTimeout(() => window.location.reload(), 1000);
            } else {
                if (typeof mostrarToast === 'function') {
                    mostrarToast('❌ ' + data.message, 'error');
                }
                btn.innerHTML = originalText;
                verificarCalificaciones();
            }
        })
        .catch(err => {
            console.error('Error:', err);
            if (typeof mostrarToast === 'function') {
                mostrarToast('❌ Error de conexión', 'error');
            }
            btn.innerHTML = originalText;
            verificarCalificaciones();
        });
    }
});


// Inicializar estrellas en gris
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.star-btn').forEach(star => {
        star.style.color = '#d1d5db';
    });
});


    </script>
        <script src="tickets/evaluacion/evaluaciones.js"></script>

</body>
</html>