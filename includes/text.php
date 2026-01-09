<?php
session_start();
include 'includes/conexionbd.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
try {
    $stmt = $conexion->prepare("
        SELECT u.*, p.nombre as puesto_nombre, d.nombre as departamento_nombre, o.nombre as oficina_nombre
        FROM usuarios u 
        LEFT JOIN puestos p ON u.Id_puesto = p.Id_puesto 
        LEFT JOIN departamentos d ON u.Id_departamento = d.Id_departamento 
        LEFT JOIN oficinas o ON u.Id_oficina = o.Id_oficina 
        WHERE u.Id_Usuario = :user_id
    ");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        throw new Exception("Usuario no encontrado");
    }
    
} catch (Exception $e) {
    die("Error al cargar datos del usuario: " . $e->getMessage());
}

try {
    $stmt_tickets = $conexion->prepare("
        SELECT t.*, a.nombre as area_nombre, cat.nombre_cat as categoria_nombre,
               d.nombre as donde_nombre, dd.nombre as detalle_donde_nombre,
               sc.nombre as subcategoria_nombre
        FROM tickets t
        LEFT JOIN areas a ON t.id_area = a.id
        LEFT JOIN categoria_servicio_ticket cat ON t.id_categoria = cat.id
        LEFT JOIN donde_ticket d ON t.id_donde = d.id
        LEFT JOIN detalle_donde_ticket dd ON t.id_detalle_donde = dd.id
        LEFT JOIN subcategoria_servicio_ticket sc ON t.id_subcategoria = sc.id
        WHERE (t.id_usuario_asignado = :user_id OR t.id_usuario_creador = :user_id)
        AND t.estatus != 'cancelado'
        ORDER BY t.fecha_creacion DESC
    ");
    $stmt_tickets->bindParam(':user_id', $user_id);
    $stmt_tickets->execute();
    $tickets = $stmt_tickets->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $tickets = [];
    error_log("Error al cargar tickets: " . $e->getMessage());
}

try {
    $stmt_tareas = $conexion->prepare("
        SELECT * FROM tareas 
        WHERE id_usuario_asignado = :user_id 
        AND estatus != 'completado'
        ORDER BY fecha_creacion DESC
    ");
    $stmt_tareas->bindParam(':user_id', $user_id);
    $stmt_tareas->execute();
    $tareas = $stmt_tareas->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $tareas = [];
    error_log("Error al cargar tareas: " . $e->getMessage());
}

try {
    $stmt_reuniones = $conexion->prepare("
        SELECT * FROM reuniones 
        WHERE FIND_IN_SET(:user_id, participantes) > 0
        AND fecha >= CURDATE()
        ORDER BY fecha, hora
    ");
    $stmt_reuniones->bindParam(':user_id', $user_id);
    $stmt_reuniones->execute();
    $reuniones = $stmt_reuniones->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $reuniones = [];
    error_log("Error al cargar reuniones: " . $e->getMessage());
}

$areas = $conexion->query("SELECT id, nombre FROM areas WHERE estatu = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$donde_tickets = $conexion->query("SELECT id, nombre FROM donde_ticket WHERE estatu = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$detalle_donde_tickets = $conexion->query("SELECT id, nombre FROM detalle_donde_ticket WHERE estatu = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$categorias = $conexion->query("SELECT id, nombre_cat FROM categoria_servicio_ticket WHERE estatu = 1 ORDER BY nombre_cat")->fetchAll(PDO::FETCH_ASSOC);

$events = [];
foreach ($tickets as $ticket) {
    $fecha_evento = isset($ticket['fecha_compromiso']) && !empty($ticket['fecha_compromiso']) ? 
        $ticket['fecha_compromiso'] : $ticket['fecha_creacion'];
    
    $events[] = [
        'id' => 'ticket_' . $ticket['id_ticket'],
        'type' => 'ticket',
        'title' => 'Ticket #' . $ticket['id_ticket'] . ' - ' . ($ticket['descripcion'] ? substr($ticket['descripcion'], 0, 30) . '...' : 'Sin descripción'),
        'date' => new DateTime($fecha_evento),
        'time' => isset($ticket['hora_compromiso']) ? $ticket['hora_compromiso'] : '09:00',
        'department' => strtolower($usuario['departamento_nombre'] ?? 'ti'),
        'priority' => $ticket['prioridad'] ?? 'media',
        'description' => $ticket['descripcion'] ?? 'Sin descripción',
        'status' => $ticket['estatus'] ?? 'activo',
        'area' => $ticket['area_nombre'] ?? 'No especificado',
        'categoria' => $ticket['categoria_nombre'] ?? 'No especificado',
        'donde' => $ticket['donde_nombre'] ?? 'No especificado',
        'detalle_donde' => $ticket['detalle_donde_nombre'] ?? 'No especificado',
        'subcategoria' => $ticket['subcategoria_nombre'] ?? 'No especificado',
        'db_data' => $ticket
    ];
}

foreach ($tareas as $tarea) {
    $fecha_evento = isset($tarea['fecha_compromiso']) && !empty($tarea['fecha_compromiso']) ? 
        $tarea['fecha_compromiso'] : $tarea['fecha_creacion'];
    
    $events[] = [
        'id' => 'tarea_' . $tarea['id_tarea'],
        'type' => 'task',
        'title' => $tarea['titulo'] ?? 'Tarea sin título',
        'date' => new DateTime($fecha_evento),
        'time' => isset($tarea['hora_compromiso']) ? $tarea['hora_compromiso'] : '09:00',
        'department' => strtolower($usuario['departamento_nombre'] ?? 'ti'),
        'priority' => $tarea['prioridad'] ?? 'media',
        'description' => $tarea['descripcion'] ?? 'Sin descripción',
        'status' => $tarea['estatus'] ?? 'pendiente',
        'db_data' => $tarea
    ];
}

foreach ($reuniones as $reunion) {
    $events[] = [
        'id' => 'reunion_' . $reunion['id_reunion'],
        'type' => 'meeting',
        'title' => $reunion['titulo'] ?? 'Reunión sin título',
        'date' => new DateTime($reunion['fecha']),
        'time' => $reunion['hora'] ?? '09:00',
        'department' => strtolower($usuario['departamento_nombre'] ?? 'ti'),
        'description' => $reunion['descripcion'] ?? 'Sin descripción',
        'status' => 'activa',
        'db_data' => $reunion
    ];
}

$events_json = json_encode($events);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flow | Calendario</title>
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
        .kanban-column {
            min-height: 500px;
        }
        .kanban-card {
            transition: all 0.2s ease;
        }
        .kanban-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .dragging {
            opacity: 0.5;
        }
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            .sidebar.active {
                transform: translateX(0);
            }
        }
        .event-indicator {
            cursor: pointer;
        }
        .event-indicator:hover {
            background-color: rgba(59, 130, 246, 0.1);
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 font-sans">
    <div class="lg:hidden fixed top-4 left-4 z-50">
        <button id="mobile-menu-btn" class="w-10 h-10 bg-white rounded-lg shadow flex items-center justify-center">
            <i class="fas fa-bars text-gray-700"></i>
        </button>
    </div>

    <div class="flex min-h-screen">
        <div class="sidebar w-64 bg-white border-r border-gray-200 p-6 fixed lg:static h-full z-40">
            <div class="mb-8 flex justify-between items-center">
                <div>
                    <h1 class="text-xl font-bold text-gray-900">Flow</h1>
                    <p class="text-sm text-gray-500">Gestión de productividad</p>
                </div>
                <button id="close-sidebar" class="lg:hidden w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center">
                    <i class="fas fa-times text-gray-500"></i>
                </button>
            </div>
            
            <nav class="space-y-2">
                <a href="calendar.php" class="flex items-center space-x-3 px-3 py-2 bg-blue-50 text-blue-600 rounded-lg border-l-4 border-blue-600">
                    <i class="fas fa-calendar w-5 text-center"></i>
                    <span>Calendario</span>
                </a>
                <a href="kanban.php" class="flex items-center space-x-3 px-3 py-2 text-gray-600 hover:bg-gray-50 rounded-lg">
                    <i class="fas fa-columns w-5 text-center"></i>
                    <span>Kanban</span>
                </a>
                <a href="tasks.php" class="flex items-center space-x-3 px-3 py-2 text-gray-600 hover:bg-gray-50 rounded-lg">
                    <i class="fas fa-tasks w-5 text-center"></i>
                    <span>Tareas</span>
                </a>
                <a href="tickets.php" class="flex items-center space-x-3 px-3 py-2 text-gray-600 hover:bg-gray-50 rounded-lg">
                    <i class="fas fa-ticket-alt w-5 text-center"></i>
                    <span>Tickets</span>
                </a>
                <a href="meetings.php" class="flex items-center space-x-3 px-3 py-2 text-gray-600 hover:bg-gray-50 rounded-lg">
                    <i class="fas fa-users w-5 text-center"></i>
                    <span>Reuniones</span>
                </a>
                <a href="notices.php" class="flex items-center space-x-3 px-3 py-2 text-gray-600 hover:bg-gray-50 rounded-lg">
                    <i class="fas fa-bell w-5 text-center"></i>
                    <span>Avisos</span>
                </a>
                <a href="metrics.php" class="flex items-center space-x-3 px-3 py-2 text-gray-600 hover:bg-gray-50 rounded-lg">
                    <i class="fas fa-chart-bar w-5 text-center"></i>
                    <span>Reportes</span>
                </a>
                <a href="evaluations.php" class="flex items-center space-x-3 px-3 py-2 text-gray-600 hover:bg-gray-50 rounded-lg">
                    <i class="fas fa-star w-5 text-center"></i>
                    <span>Evaluaciones</span>
                </a>
                <a href="ticket-evaluations.php" class="flex items-center space-x-3 px-3 py-2 text-gray-600 hover:bg-gray-50 rounded-lg">
                    <i class="fas fa-user-chart w-5 text-center"></i>
                    <span>Análisis Personal</span>
                </a>
                <a href="logout.php" class="flex items-center space-x-3 px-3 py-2 text-red-600 hover:bg-red-50 rounded-lg mt-8">
                    <i class="fas fa-sign-out-alt w-5 text-center"></i>
                    <span>Cerrar Sesión</span>
                </a>
            </nav>
        </div>

        <div class="flex-1 p-4 lg:p-8 ml-0 lg:ml-0">
            <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-6 lg:mb-8 gap-4">
                <div>
                    <h1 class="text-xl lg:text-2xl font-bold text-gray-900" id="view-title">Calendario Mensual</h1>
                    <p class="text-gray-500" id="current-period"><?php echo date('F Y'); ?></p>
                </div>
                <div class="flex flex-col lg:flex-row items-start lg:items-center space-y-2 lg:space-y-0 lg:space-x-4 w-full lg:w-auto">
                    <div class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-medium">
                        Departamento: <?php echo htmlspecialchars($usuario['departamento_nombre'] ?? 'No asignado'); ?>
                    </div>
                    <div class="flex items-center space-x-3 bg-white px-4 py-2 rounded-lg border border-gray-200 w-full lg:w-auto justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center text-white font-medium">
                                <?php 
                                $iniciales = substr($usuario['nombre'] ?? 'U', 0, 1) . substr($usuario['apellido'] ?? 'S', 0, 1);
                                echo strtoupper($iniciales);
                                ?>
                            </div>
                            <div>
                                <span class="text-gray-700"><?php echo htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']); ?></span>
                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($usuario['puesto_nombre'] ?? 'Usuario'); ?></div>
                            </div>
                        </div>
                        <button class="lg:hidden w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center">
                            <i class="fas fa-chevron-down text-gray-500"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-4 mb-6 lg:mb-8">
                <div class="bg-white p-4 lg:p-6 rounded-xl border border-gray-200">
                    <div class="text-xl lg:text-2xl font-bold text-blue-600"><?php echo count($tickets); ?></div>
                    <div class="text-gray-500 text-sm">Tickets activos</div>
                </div>
                <div class="bg-white p-4 lg:p-6 rounded-xl border border-gray-200">
                    <div class="text-xl lg:text-2xl font-bold text-warning"><?php echo count($tareas); ?></div>
                    <div class="text-gray-500 text-sm">Tareas pendientes</div>
                </div>
                <div class="bg-white p-4 lg:p-6 rounded-xl border border-gray-200">
                    <div class="text-xl lg:text-2xl font-bold text-success"><?php echo count($reuniones); ?></div>
                    <div class="text-gray-500 text-sm">Reuniones</div>
                </div>
                <div class="bg-white p-4 lg:p-6 rounded-xl border border-gray-200">
                    <div class="text-xl lg:text-2xl font-bold text-purple-600" id="recurrent-tasks-count">0</div>
                    <div class="text-gray-500 text-sm">Tareas cíclicas</div>
                </div>
            </div>

            <div id="calendar-view" class="view-container">
                <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-6 gap-4">
                    <div class="flex flex-wrap gap-2">
                        <button class="view-toggle px-3 lg:px-4 py-2 bg-blue-600 text-white rounded-lg font-medium text-sm lg:text-base" data-view="month">Mes</button>
                        <button class="view-toggle px-3 lg:px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg font-medium text-sm lg:text-base" data-view="week">Semana</button>
                        <button class="view-toggle px-3 lg:px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg font-medium text-sm lg:text-base" data-view="day">Día</button>
                    </div>
                    <div class="flex space-x-2">
                        <button class="nav-btn px-3 lg:px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50" data-direction="-1">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <button class="nav-btn px-3 lg:px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50" data-direction="1">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                        <button id="add-item-btn" class="px-3 lg:px-4 py-2 bg-blue-600 text-white rounded-lg font-medium flex items-center space-x-2 hover:bg-blue-700 text-sm lg:text-base">
                            <i class="fas fa-plus"></i>
                            <span class="hidden lg:inline">Nuevo elemento</span>
                            <span class="lg:hidden">Nuevo</span>
                        </button>
                    </div>
                </div>

                <div id="month-view" class="calendar-view">
                    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                        <div class="grid grid-cols-7 gap-1 lg:gap-2 p-2 lg:p-4 bg-gray-50 border-b border-gray-200">
                            <div class="text-center text-xs lg:text-sm font-medium text-gray-500 py-2">Lun</div>
                            <div class="text-center text-xs lg:text-sm font-medium text-gray-500 py-2">Mar</div>
                            <div class="text-center text-xs lg:text-sm font-medium text-gray-500 py-2">Mié</div>
                            <div class="text-center text-xs lg:text-sm font-medium text-gray-500 py-2">Jue</div>
                            <div class="text-center text-xs lg:text-sm font-medium text-gray-500 py-2">Vie</div>
                            <div class="text-center text-xs lg:text-sm font-medium text-gray-500 py-2">Sáb</div>
                            <div class="text-center text-xs lg:text-sm font-medium text-gray-500 py-2">Dom</div>
                        </div>
                        <div class="grid grid-cols-7 gap-1 lg:gap-2 p-2 lg:p-4" id="month-days"></div>
                    </div>
                </div>

                <div id="week-view" class="calendar-view hidden">
                    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                        <div class="grid grid-cols-8 gap-1 lg:gap-2 p-2 lg:p-4 bg-gray-50 border-b border-gray-200 overflow-x-auto">
                            <div class="text-xs lg:text-sm font-medium text-gray-500 py-2 min-w-12">Hora</div>
                            <div class="text-center text-xs lg:text-sm font-medium text-gray-500 py-2 min-w-20" id="week-day-0">Lun</div>
                            <div class="text-center text-xs lg:text-sm font-medium text-gray-500 py-2 min-w-20" id="week-day-1">Mar</div>
                            <div class="text-center text-xs lg:text-sm font-medium text-gray-500 py-2 min-w-20" id="week-day-2">Mié</div>
                            <div class="text-center text-xs lg:text-sm font-medium text-gray-500 py-2 min-w-20" id="week-day-3">Jue</div>
                            <div class="text-center text-xs lg:text-sm font-medium text-gray-500 py-2 min-w-20" id="week-day-4">Vie</div>
                            <div class="text-center text-xs lg:text-sm font-medium text-gray-500 py-2 min-w-20" id="week-day-5">Sáb</div>
                            <div class="text-center text-xs lg:text-sm font-medium text-gray-500 py-2 min-w-20" id="week-day-6">Dom</div>
                        </div>
                        <div class="p-2 lg:p-4 overflow-x-auto" id="week-hours"></div>
                    </div>
                </div>

                <div id="day-view" class="calendar-view hidden">
                    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                        <div class="p-4 lg:p-6 border-b border-gray-200">
                            <h3 class="text-base lg:text-lg font-semibold" id="day-title"></h3>
                        </div>
                        <div class="p-4 lg:p-6" id="day-events"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="item-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 p-4">
        <div class="bg-white rounded-xl w-full max-w-4xl mx-auto max-h-[90vh] overflow-hidden">
            <div class="flex justify-between items-center p-4 lg:p-6 border-b border-gray-200">
                <h3 class="text-base lg:text-lg font-semibold" id="modal-title">Nuevo elemento</h3>
                <button id="close-modal" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center">
                    <i class="fas fa-times text-gray-500"></i>
                </button>
            </div>

            <div class="p-4 lg:p-6 space-y-6 max-h-[60vh] overflow-y-auto">
                <div id="type-selection">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de elemento</label>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 lg:gap-4">
                        <button type="button" class="item-type-btn p-3 lg:p-4 border-2 border-gray-200 rounded-lg text-center hover:border-blue-500 transition-colors" data-type="ticket">
                            <i class="fas fa-ticket-alt text-xl lg:text-2xl text-blue-500 mb-2"></i>
                            <div class="font-medium text-sm lg:text-base">Ticket</div>
                            <div class="text-xs lg:text-sm text-gray-500">Problema o solicitud</div>
                        </button>
                        <button type="button" class="item-type-btn p-3 lg:p-4 border-2 border-gray-200 rounded-lg text-center hover:border-green-500 transition-colors" data-type="task">
                            <i class="fas fa-tasks text-xl lg:text-2xl text-green-500 mb-2"></i>
                            <div class="font-medium text-sm lg:text-base">Tarea</div>
                            <div class="text-xs lg:text-sm text-gray-500">Actividad productiva</div>
                        </button>
                        <button type="button" class="item-type-btn p-3 lg:p-4 border-2 border-gray-200 rounded-lg text-center hover:border-purple-500 transition-colors" data-type="meeting">
                            <i class="fas fa-users text-xl lg:text-2xl text-purple-500 mb-2"></i>
                            <div class="font-medium text-sm lg:text-base">Reunión</div>
                            <div class="text-xs lg:text-sm text-gray-500">Encuentro programado</div>
                        </button>
                    </div>
                </div>

                <div id="ticket-form" class="item-form hidden space-y-4">
                    <form id="ticketForm" method="POST" action="procesar_ticket.php">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Área *</label>
                                <select id="area" name="area" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="">Selecciona un área</option>
                                    <?php foreach ($areas as $area): ?>
                                        <option value="<?= $area['id'] ?>"><?= htmlspecialchars($area['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Dónde *</label>
                                <select id="donde" name="donde" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="">Selecciona una ubicación</option>
                                    <?php foreach ($donde_tickets as $donde): ?>
                                        <option value="<?= $donde['id'] ?>"><?= htmlspecialchars($donde['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Detalle de dónde</label>
                                <select id="detalle_donde" name="detalle_donde" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="">Selecciona un detalle</option>
                                    <?php foreach ($detalle_donde_tickets as $detalle): ?>
                                        <option value="<?= $detalle['id'] ?>"><?= htmlspecialchars($detalle['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Categoría de servicios *</label>
                                <select id="categoria" name="categoria" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="">Selecciona una categoría</option>
                                    <?php foreach ($categorias as $categoria): ?>
                                        <option value="<?= $categoria['id'] ?>"><?= htmlspecialchars($categoria['nombre_cat']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Subcategoría</label>
                            <select id="subcategoria" name="subcategoria" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">Selecciona una subcategoría</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Fecha compromiso *</label>
                                <input type="date" name="fecha_compromiso" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Hora compromiso</label>
                                <input type="time" name="hora_compromiso" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Descripción *</label>
                            <textarea name="descripcion" id="descripcion" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" rows="3" placeholder="Describe el trabajo o solicitud"></textarea>
                        </div>
                    </form>
                </div>

                <div id="task-form" class="item-form hidden space-y-4">
                    <form id="taskForm" method="POST" action="procesar_tarea.php">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Título *</label>
                                <input type="text" name="titulo" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Título de la tarea">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Prioridad *</label>
                                <select name="prioridad" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="baja">Baja</option>
                                    <option value="media">Media</option>
                                    <option value="alta">Alta</option>
                                    <option value="urgente">Urgente</option>
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Fecha compromiso *</label>
                                <input type="date" name="fecha_compromiso" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Hora compromiso</label>
                                <input type="time" name="hora_compromiso" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Descripción *</label>
                            <textarea name="descripcion" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" rows="3" placeholder="Describe la tarea a realizar"></textarea>
                        </div>
                    </form>
                </div>

                <div id="meeting-form" class="item-form hidden space-y-4">
                    <form id="meetingForm" method="POST" action="procesar_reunion.php">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Título *</label>
                                <input type="text" name="titulo" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Título de la reunión">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Fecha *</label>
                                <input type="date" name="fecha" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Hora *</label>
                                <input type="time" name="hora" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Duración (minutos)</label>
                                <input type="number" name="duracion" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="60" value="60">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Descripción *</label>
                            <textarea name="descripcion" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" rows="3" placeholder="Describe el objetivo de la reunión"></textarea>
                        </div>
                    </form>
                </div>
            </div>

            <div class="flex justify-end space-x-3 p-4 lg:p-6 border-t border-gray-200">
                <button id="cancel-btn" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg font-medium hover:bg-gray-50">
                    Cancelar
                </button>
                <button id="save-btn" class="px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700">
                    Guardar
                </button>
            </div>
        </div>
    </div>

    <div id="detail-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 p-4">
        <div class="bg-white rounded-xl w-full max-w-2xl mx-auto max-h-[90vh] overflow-hidden">
            <div class="flex justify-between items-center p-4 lg:p-6 border-b border-gray-200">
                <h3 class="text-base lg:text-lg font-semibold" id="detail-title">Detalles del elemento</h3>
                <button id="close-detail-modal" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center">
                    <i class="fas fa-times text-gray-500"></i>
                </button>
            </div>

            <div class="p-4 lg:p-6 space-y-6 max-h-[60vh] overflow-y-auto" id="detail-content"></div>

            <div class="flex justify-between p-4 lg:p-6 border-t border-gray-200">
                <div>
                    <button id="cancel-item-btn" class="px-4 py-2 bg-red-600 text-white rounded-lg font-medium hover:bg-red-700 mr-2">
                        Cancelar
                    </button>
                    <button id="delete-item-btn" class="px-4 py-2 bg-gray-600 text-white rounded-lg font-medium hover:bg-gray-700">
                        Eliminar
                    </button>
                </div>
                <div>
                    <button id="edit-item-btn" class="px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700">
                        Editar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const usuario = {
            id: <?php echo $user_id; ?>,
            nombre: "<?php echo $usuario['nombre']; ?>",
            apellido: "<?php echo $usuario['apellido']; ?>",
            departamento: "<?php echo strtolower($usuario['departamento_nombre'] ?? 'ti'); ?>",
            puesto: "<?php echo $usuario['puesto_nombre'] ?? 'Usuario'; ?>"
        };

        let events = <?php echo $events_json; ?>;

        events = events.map(event => {
            return {
                ...event,
                date: new Date(event.date.date)
            };
        });

        const departments = {
            'ti': { name: 'TI', positions: ['Desarrollador', 'Analista de Sistemas', 'Administrador de Red', 'Soporte Técnico', 'Arquitecto de Software'] },
            'ventas': { name: 'Ventas', positions: ['Ejecutivo de Ventas', 'Gerente de Ventas', 'Asesor Comercial', 'Coordinador de Ventas'] },
            'marketing': { name: 'Marketing', positions: ['Especialista en Marketing', 'Community Manager', 'Analista de Mercado', 'Diseñador Gráfico'] },
            'rh': { name: 'Recursos Humanos', positions: ['Reclutador', 'Especialista en Nómina', 'Coordinador de Capacitación', 'Analista de RH'] },
            'contabilidad': { name: 'Contabilidad', positions: ['Contador', 'Auxiliar Contable', 'Analista Financiero', 'Auditor'] }
        };

        const holidays = [
            '2023-01-01', '2023-02-05', '2023-03-21', '2023-05-01', '2023-09-16', '2023-12-25'
        ];

        const currentDate = new Date();
        let currentMonth = currentDate.getMonth();
        let currentYear = currentDate.getFullYear();
        let currentWeek = 0;
        let currentDay = currentDate.getDate();
        let currentView = 'month';
        let currentMainView = 'calendar';
        let editingItemId = null;

        const months = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
        const days = ["Domingo", "Lunes", "Martes", "Miércoles", "Jueves", "Viernes", "Sábado"];

        function initApp() {
            initCalendar();
            setupEventListeners();
        }

        function initCalendar() {
            updateCalendarHeader();
            renderMonthView();
        }

        function setupEventListeners() {
            document.getElementById('mobile-menu-btn').addEventListener('click', function() {
                document.querySelector('.sidebar').classList.add('active');
            });
            
            document.getElementById('close-sidebar').addEventListener('click', function() {
                document.querySelector('.sidebar').classList.remove('active');
            });

            document.querySelectorAll('.view-toggle').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('.view-toggle').forEach(b => {
                        b.classList.remove('bg-blue-600', 'text-white');
                        b.classList.add('text-gray-600', 'hover:bg-gray-100');
                    });
                    this.classList.remove('text-gray-600', 'hover:bg-gray-100');
                    this.classList.add('bg-blue-600', 'text-white');
                    
                    currentView = this.dataset.view;
                    switchView(currentView);
                });
            });

            document.querySelectorAll('.nav-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const direction = parseInt(this.dataset.direction);
                    navigatePeriod(direction);
                });
            });

            document.getElementById('add-item-btn').addEventListener('click', openModal);
            document.getElementById('close-modal').addEventListener('click', closeModal);
            document.getElementById('cancel-btn').addEventListener('click', closeModal);
            document.getElementById('save-btn').addEventListener('click', saveItem);
            
            document.getElementById('close-detail-modal').addEventListener('click', closeDetailModal);
            document.getElementById('cancel-item-btn').addEventListener('click', cancelItem);
            document.getElementById('delete-item-btn').addEventListener('click', deleteItem);
            document.getElementById('edit-item-btn').addEventListener('click', editItem);
            
            document.querySelectorAll('.item-type-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const type = this.dataset.type;
                    showForm(type);
                });
            });

            document.getElementById('categoria').addEventListener('change', function() {
                loadSubcategorias(this.value);
            });
        }

        function loadSubcategorias(categoriaId) {
            const subcategoriaSelect = document.getElementById('subcategoria');
            subcategoriaSelect.innerHTML = '<option value="">Selecciona una subcategoría</option>';
            
            if (!categoriaId) return;
            
            fetch('get_subcategorias.php?categoria_id=' + categoriaId)
                .then(response => response.json())
                .then(data => {
                    data.forEach(subcat => {
                        const option = document.createElement('option');
                        option.value = subcat.id;
                        option.textContent = subcat.nombre;
                        subcategoriaSelect.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error('Error al cargar subcategorías:', error);
                });
        }

        function switchView(view) {
            document.querySelectorAll('.calendar-view').forEach(v => v.classList.add('hidden'));
            document.getElementById(`${view}-view`).classList.remove('hidden');
            
            const titles = {
                'month': 'Calendario Mensual',
                'week': 'Calendario Semanal', 
                'day': 'Vista Diaria'
            };
            document.getElementById('view-title').textContent = titles[view];
            
            if (view === 'month') {
                renderMonthView();
            } else if (view === 'week') {
                renderWeekView();
            } else if (view === 'day') {
                renderDayView();
            }
        }

        function navigatePeriod(direction) {
            if (currentView === 'month') {
                currentMonth += direction;
                if (currentMonth < 0) {
                    currentMonth = 11;
                    currentYear--;
                } else if (currentMonth > 11) {
                    currentMonth = 0;
                    currentYear++;
                }
                renderMonthView();
            } else if (currentView === 'week') {
                currentWeek += direction;
                renderWeekView();
            } else if (currentView === 'day') {
                currentDay += direction;
                const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
                if (currentDay < 1) {
                    currentMonth--;
                    if (currentMonth < 0) {
                        currentMonth = 11;
                        currentYear--;
                    }
                    currentDay = new Date(currentYear, currentMonth + 1, 0).getDate();
                } else if (currentDay > daysInMonth) {
                    currentDay = 1;
                    currentMonth++;
                    if (currentMonth > 11) {
                        currentMonth = 0;
                        currentYear++;
                    }
                }
                renderDayView();
            }
            updateCalendarHeader();
        }

        function updateCalendarHeader() {
            if (currentView === 'month') {
                document.getElementById('current-period').textContent = `${months[currentMonth]} ${currentYear}`;
            } else if (currentView === 'week') {
                const weekStart = new Date(currentYear, currentMonth, currentWeek * 7 + 1);
                const weekEnd = new Date(currentYear, currentMonth, currentWeek * 7 + 7);
                document.getElementById('current-period').textContent = 
                    `Semana ${currentWeek + 1} - ${weekStart.getDate()} al ${weekEnd.getDate()} de ${months[currentMonth]}`;
            } else if (currentView === 'day') {
                const date = new Date(currentYear, currentMonth, currentDay);
                document.getElementById('current-period').textContent = 
                    `${days[date.getDay()]}, ${currentDay} de ${months[currentMonth]} ${currentYear}`;
            }
        }

        function renderMonthView() {
            const monthDays = document.getElementById('month-days');
            monthDays.innerHTML = '';

            const firstDay = new Date(currentYear, currentMonth, 1).getDay();
            const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
            const startDay = firstDay === 0 ? 6 : firstDay - 1;

            for (let i = 0; i < startDay; i++) {
                const emptyDay = document.createElement('div');
                emptyDay.classList.add('min-h-20', 'lg:min-h-24', 'p-1', 'lg:p-2', 'border', 'border-gray-200', 'rounded-lg', 'bg-gray-50');
                monthDays.appendChild(emptyDay);
            }

            for (let day = 1; day <= daysInMonth; day++) {
                const dayElement = document.createElement('div');
                const dayDate = new Date(currentYear, currentMonth, day);
                
                dayElement.classList.add('min-h-20', 'lg:min-h-24', 'p-1', 'lg:p-2', 'border', 'border-gray-200', 'rounded-lg', 'hover:border-blue-300', 'transition-colors');
                
                if (day === currentDate.getDate() && currentMonth === currentDate.getMonth() && currentYear === currentDate.getFullYear()) {
                    dayElement.classList.add('bg-blue-50', 'border-blue-300');
                }
                
                if (isHoliday(dayDate)) {
                    dayElement.classList.add('bg-red-50', 'border-red-200');
                }

                const dayNumber = document.createElement('div');
                dayNumber.classList.add('font-medium', 'text-gray-900', 'mb-1', 'flex', 'justify-between', 'items-center', 'text-sm', 'lg:text-base');
                
                if (day === currentDate.getDate() && currentMonth === currentDate.getMonth() && currentYear === currentDate.getFullYear()) {
                    dayNumber.classList.add('text-blue-600');
                }
                
                if (isHoliday(dayDate)) {
                    dayNumber.classList.add('text-red-600');
                }
                
                dayNumber.innerHTML = `
                    <span>${day}</span>
                    ${isHoliday(dayDate) ? '<i class="fas fa-star text-red-500 text-xs"></i>' : ''}
                `;
                dayElement.appendChild(dayNumber);

                const dayEvents = getEventsForDate(dayDate);
                if (dayEvents.length > 0) {
                    const eventIndicators = document.createElement('div');
                    eventIndicators.classList.add('space-y-1');
                    
                    dayEvents.slice(0, 2).forEach(event => {
                        const indicator = document.createElement('div');
                        indicator.classList.add('text-xs', 'flex', 'items-center', 'space-x-1', 'event-indicator', 'p-1', 'rounded');
                        indicator.addEventListener('click', () => showEventDetails(event));
                        
                        const dot = document.createElement('div');
                        dot.classList.add('w-2', 'h-2', 'rounded-full', 'flex-shrink-0');
                        
                        if (event.type === 'ticket') dot.classList.add('bg-blue-500');
                        else if (event.type === 'task') dot.classList.add('bg-warning');
                        else if (event.type === 'meeting') dot.classList.add('bg-success');
                        
                        const text = document.createElement('span');
                        text.classList.add('truncate', 'text-gray-600', 'flex-1');
                        text.textContent = event.title.substring(0, 10) + '...';
                        
                        indicator.appendChild(dot);
                        indicator.appendChild(text);
                        eventIndicators.appendChild(indicator);
                    });
                    
                    if (dayEvents.length > 2) {
                        const moreIndicator = document.createElement('div');
                        moreIndicator.classList.add('text-xs', 'text-gray-500', 'p-1');
                        moreIndicator.textContent = `+${dayEvents.length - 2} más`;
                        eventIndicators.appendChild(moreIndicator);
                    }
                    
                    dayElement.appendChild(eventIndicators);
                }

                monthDays.appendChild(dayElement);
            }
        }

        function renderWeekView() {
            for (let i = 0; i < 7; i++) {
                const day = new Date(currentYear, currentMonth, currentWeek * 7 + i + 1);
                const dayElement = document.getElementById(`week-day-${i}`);
                const dayName = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'][day.getDay()];
                dayElement.textContent = `${dayName} ${day.getDate()}`;
                
                if (isHoliday(day)) {
                    dayElement.classList.add('text-red-600');
                } else {
                    dayElement.classList.remove('text-red-600');
                }
            }

            const weekHours = document.getElementById('week-hours');
            weekHours.innerHTML = '';

            for (let hour = 8; hour <= 18; hour++) {
                const timeRow = document.createElement('div');
                timeRow.classList.add('grid', 'grid-cols-8', 'gap-1', 'lg:gap-2', 'mb-2');
                
                const timeLabel = document.createElement('div');
                timeLabel.classList.add('text-xs', 'lg:text-sm', 'text-gray-500', 'py-2');
                timeLabel.textContent = `${hour}:00`;
                timeRow.appendChild(timeLabel);

                for (let day = 0; day < 7; day++) {
                    const dayCell = document.createElement('div');
                    dayCell.classList.add('min-h-10', 'lg:min-h-12', 'border', 'border-gray-200', 'rounded', 'p-1');
                    
                    const dayDate = new Date(currentYear, currentMonth, currentWeek * 7 + day + 1);
                    dayDate.setHours(hour);
                    
                    if (isHoliday(dayDate)) {
                        dayCell.classList.add('bg-red-50', 'border-red-200');
                    }
                    
                    const hourEvents = getEventsForDateAndHour(dayDate, hour);
                    hourEvents.forEach(event => {
                        const eventElement = document.createElement('div');
                        eventElement.classList.add('text-xs', 'p-1', 'rounded', 'mb-1', 'text-white', 'truncate', 'cursor-pointer');
                        eventElement.addEventListener('click', () => showEventDetails(event));
                        
                        if (event.type === 'ticket') eventElement.classList.add('bg-blue-500');
                        else if (event.type === 'task') eventElement.classList.add('bg-warning');
                        else if (event.type === 'meeting') eventElement.classList.add('bg-success');
                        
                        eventElement.textContent = event.title.substring(0, 15) + '...';
                        dayCell.appendChild(eventElement);
                    });
                    
                    timeRow.appendChild(dayCell);
                }
                
                weekHours.appendChild(timeRow);
            }
        }

        function renderDayView() {
            const dayTitle = document.getElementById('day-title');
            const dayDate = new Date(currentYear, currentMonth, currentDay);
            dayTitle.textContent = `${days[dayDate.getDay()]}, ${currentDay} de ${months[currentMonth]} ${currentYear}`;

            const dayEventsContainer = document.getElementById('day-events');
            dayEventsContainer.innerHTML = '';

            if (isHoliday(dayDate)) {
                const holidayNotice = document.createElement('div');
                holidayNotice.classList.add('bg-red-50', 'border', 'border-red-200', 'rounded-lg', 'p-4', 'mb-4');
                holidayNotice.innerHTML = `
                    <div class="flex items-center space-x-2 text-red-800">
                        <i class="fas fa-star"></i>
                        <span class="font-medium">Día festivo</span>
                    </div>
                    <p class="text-sm text-red-600 mt-1">Este día es considerado festivo. Las tareas programadas pueden requerir aprobación especial.</p>
                `;
                dayEventsContainer.appendChild(holidayNotice);
            }

            const dayEvents = getEventsForDate(dayDate);
            
            if (dayEvents.length === 0) {
                dayEventsContainer.innerHTML = `
                    <div class="text-center py-8 lg:py-12 text-gray-500">
                        <i class="fas fa-calendar-day text-2xl lg:text-4xl mb-4"></i>
                        <p class="text-base lg:text-lg">No hay eventos programados para hoy</p>
                        <p class="text-xs lg:text-sm">Haz clic en "Nuevo elemento" para agregar uno</p>
                    </div>
                `;
                return;
            }

            dayEvents.sort((a, b) => (a.time || '00:00').localeCompare(b.time || '00:00'));

            dayEvents.forEach(event => {
                const eventElement = document.createElement('div');
                eventElement.classList.add('flex', 'items-start', 'space-x-3', 'lg:space-x-4', 'p-3', 'lg:p-4', 'border', 'border-gray-200', 'rounded-lg', 'mb-3', 'cursor-pointer');
                eventElement.addEventListener('click', () => showEventDetails(event));
                
                const icon = document.createElement('div');
                icon.classList.add('w-8', 'h-8', 'lg:w-10', 'lg:h-10', 'rounded-full', 'flex', 'items-center', 'justify-center', 'text-white', 'flex-shrink-0');
                
                if (event.type === 'ticket') icon.classList.add('bg-blue-500');
                else if (event.type === 'task') icon.classList.add('bg-warning');
                else if (event.type === 'meeting') icon.classList.add('bg-success');
                
                icon.innerHTML = `<i class="fas fa-${event.type === 'ticket' ? 'ticket-alt' : event.type === 'task' ? 'tasks' : 'users'} text-sm lg:text-base"></i>`;
                
                const content = document.createElement('div');
                content.classList.add('flex-1');
                
                const header = document.createElement('div');
                header.classList.add('flex', 'flex-col', 'lg:flex-row', 'lg:justify-between', 'lg:items-start', 'mb-2');
                
                const title = document.createElement('h4');
                title.classList.add('font-medium', 'text-gray-900', 'text-sm', 'lg:text-base', 'mb-1', 'lg:mb-0');
                title.textContent = event.title;
                
                const time = document.createElement('span');
                time.classList.add('text-xs', 'lg:text-sm', 'text-gray-500');
                time.textContent = event.time || 'Todo el día';
                
                header.appendChild(title);
                header.appendChild(time);
                
                const details = document.createElement('div');
                details.classList.add('text-xs', 'lg:text-sm', 'text-gray-600', 'space-y-1');
                
                if (event.type === 'ticket') {
                    const ticketInfo = document.createElement('div');
                    ticketInfo.classList.add('flex', 'items-center', 'space-x-2');
                    ticketInfo.innerHTML = `
                        <span class="font-medium">Área:</span>
                        <span class="bg-gray-100 px-2 py-1 rounded text-xs">${event.area}</span>
                    `;
                    details.appendChild(ticketInfo);
                }
                
                if (event.priority) {
                    const priorityInfo = document.createElement('div');
                    priorityInfo.classList.add('flex', 'items-center', 'space-x-2');
                    priorityInfo.innerHTML = `
                        <span class="font-medium">Prioridad:</span>
                        <span class="bg-${event.priority === 'alta' || event.priority === 'urgente' ? 'red' : event.priority === 'media' ? 'yellow' : 'green'}-100 text-${event.priority === 'alta' || event.priority === 'urgente' ? 'red' : event.priority === 'media' ? 'yellow' : 'green'}-800 px-2 py-1 rounded text-xs">${event.priority}</span>
                    `;
                    details.appendChild(priorityInfo);
                }
                
                content.appendChild(header);
                content.appendChild(details);
                
                eventElement.appendChild(icon);
                eventElement.appendChild(content);
                dayEventsContainer.appendChild(eventElement);
            });
        }

        function getEventsForDate(date) {
            const dayEvents = [];
            
            events.forEach(event => {
                if (event.date.getDate() === date.getDate() && 
                    event.date.getMonth() === date.getMonth() && 
                    event.date.getFullYear() === date.getFullYear()) {
                    dayEvents.push(event);
                }
            });
            
            return dayEvents;
        }

        function getEventsForDateAndHour(date, hour) {
            return getEventsForDate(date).filter(event => {
                if (!event.time) return false;
                const eventHour = parseInt(event.time.split(':')[0]);
                return eventHour === hour;
            });
        }

        function isHoliday(date) {
            const dateString = date.toISOString().split('T')[0];
            return holidays.includes(dateString);
        }

        function showEventDetails(event) {
            document.getElementById('detail-title').textContent = event.title;
            
            let content = '';
            if (event.type === 'ticket') {
                content = `
                    <div class="space-y-4">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 rounded-full bg-blue-500 flex items-center justify-center text-white">
                                <i class="fas fa-ticket-alt"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold">Ticket #${event.db_data.id_ticket}</h4>
                                <p class="text-sm text-gray-500">${event.date.toLocaleDateString()} ${event.time ? ' - ' + event.time : ''}</p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Prioridad</label>
                                <div class="bg-${event.priority === 'alta' ? 'red' : event.priority === 'media' ? 'yellow' : 'green'}-100 text-${event.priority === 'alta' ? 'red' : event.priority === 'media' ? 'yellow' : 'green'}-800 px-3 py-1 rounded-full text-sm font-medium inline-block">
                                    ${event.priority}
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                                <div class="bg-gray-100 text-gray-800 px-3 py-1 rounded-full text-sm font-medium inline-block">
                                    ${event.status}
                                </div>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Área</label>
                                <div class="bg-gray-100 text-gray-800 px-3 py-1 rounded text-sm">${event.area}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Categoría</label>
                                <div class="bg-gray-100 text-gray-800 px-3 py-1 rounded text-sm">${event.categoria}</div>
                            </div>
                        </div>
                        
                        ${event.subcategoria ? `
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Subcategoría</label>
                            <div class="bg-gray-100 text-gray-800 px-3 py-1 rounded text-sm">${event.subcategoria}</div>
                        </div>
                        ` : ''}
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Descripción</label>
                            <p class="text-gray-600 bg-gray-50 p-3 rounded-lg">${event.description}</p>
                        </div>
                    </div>
                `;
            } else if (event.type === 'task') {
                content = `
                    <div class="space-y-4">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 rounded-full bg-green-500 flex items-center justify-center text-white">
                                <i class="fas fa-tasks"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold">Tarea</h4>
                                <p class="text-sm text-gray-500">${event.date.toLocaleDateString()} ${event.time ? ' - ' + event.time : ''}</p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Prioridad</label>
                                <div class="bg-${event.priority === 'alta' || event.priority === 'urgente' ? 'red' : event.priority === 'media' ? 'yellow' : 'green'}-100 text-${event.priority === 'alta' || event.priority === 'urgente' ? 'red' : event.priority === 'media' ? 'yellow' : 'green'}-800 px-3 py-1 rounded-full text-sm font-medium inline-block">
                                    ${event.priority}
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                                <div class="bg-gray-100 text-gray-800 px-3 py-1 rounded-full text-sm font-medium inline-block">
                                    ${event.status}
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Descripción</label>
                            <p class="text-gray-600 bg-gray-50 p-3 rounded-lg">${event.description}</p>
                        </div>
                    </div>
                `;
            } else if (event.type === 'meeting') {
                content = `
                    <div class="space-y-4">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 rounded-full bg-purple-500 flex items-center justify-center text-white">
                                <i class="fas fa-users"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold">Reunión</h4>
                                <p class="text-sm text-gray-500">${event.date.toLocaleDateString()} ${event.time ? ' - ' + event.time : ''}</p>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Descripción</label>
                            <p class="text-gray-600 bg-gray-50 p-3 rounded-lg">${event.description}</p>
                        </div>
                    </div>
                `;
            }
            
            document.getElementById('detail-content').innerHTML = content;
            document.getElementById('detail-modal').classList.remove('hidden');
            
            editingItemId = event.id;
        }

        function openModal() {
            document.getElementById('item-modal').classList.remove('hidden');
            document.getElementById('type-selection').classList.remove('hidden');
            showForm('ticket');
            editingItemId = null;
        }

        function closeModal() {
            document.getElementById('item-modal').classList.add('hidden');
        }

        function closeDetailModal() {
            document.getElementById('detail-modal').classList.add('hidden');
            editingItemId = null;
        }

        function showForm(type) {
            document.querySelectorAll('.item-form').forEach(form => form.classList.add('hidden'));
            document.getElementById(`${type}-form`).classList.remove('hidden');
            
            const titles = {
                'ticket': 'Nuevo Ticket',
                'task': 'Nueva Tarea', 
                'meeting': 'Nueva Reunión'
            };
            document.getElementById('modal-title').textContent = titles[type];
        }

        function saveItem() {
            const activeForm = document.querySelector('.item-form:not(.hidden)');
            if (!activeForm) return;
            
            const formId = activeForm.id.replace('-form', 'Form');
            const form = document.getElementById(formId);
            
            if (form.checkValidity()) {
                form.submit();
            } else {
                form.reportValidity();
            }
        }

        function cancelItem() {
            if (confirm('¿Estás seguro de que quieres cancelar este elemento?')) {
                const [type, id] = editingItemId.split('_');
                
                fetch('cancelar_elemento.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        type: type,
                        id: id
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Elemento cancelado exitosamente');
                        location.reload();
                    } else {
                        alert('Error al cancelar el elemento');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al cancelar el elemento');
                });
                
                closeDetailModal();
            }
        }

        function deleteItem() {
            if (confirm('¿Estás seguro de que quieres eliminar este elemento? Esta acción no se puede deshacer.')) {
                const [type, id] = editingItemId.split('_');
                
                fetch('eliminar_elemento.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        type: type,
                        id: id
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Elemento eliminado exitosamente');
                        location.reload();
                    } else {
                        alert('Error al eliminar el elemento');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al eliminar el elemento');
                });
                
                closeDetailModal();
            }
        }

        function editItem() {
            closeDetailModal();
            
            if (editingItemId) {
                const [type, id] = editingItemId.split('_');
                openModal();
                showForm(type);
            }
        }

        document.addEventListener('DOMContentLoaded', initApp);
    </script>
</body>
</html>