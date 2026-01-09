<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('memory_limit', '256M'); // o '512M' si es muy grande


session_start();
include 'includes/conexionbd.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Verificar la conexión
try {
    if (!isset($pdo)) {
        throw new Exception("Variable \$pdo no definida");
    }
    $pdo->query("SELECT 1");
} catch (Exception $e) {
    die("Error en conexión BD: " . $e->getMessage());
}

// Cargar usuario
try {
    $stmt = $pdo->prepare("
        SELECT u.*, p.nombre as puesto_nombre, d.nombre as departamento_nombre, o.nombre as oficina_nombre, u.subarea
        FROM usuarios u 
        LEFT JOIN puestos p ON u.Id_puesto = p.Id_puesto 
        LEFT JOIN departamentos d ON u.Id_departamento = d.id
        LEFT JOIN oficinas o ON u.Id_oficina = o.id 
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

// Tickets
try {
    $stmt_tickets = $pdo->prepare("
        SELECT t.*, a.nombre as area_nombre, cat.nombre_cat as categoria_nombre,
               d.nombre as donde_nombre, dd.nombre as detalle_donde_nombre,
               sc.nombre_cat as subcategoria_nombre, u.nombre as nombre_solicitante, u.whatsapp, t.estado, st.nombre_sucat
        FROM tickets t
        LEFT JOIN areas a ON t.area = a.id
        LEFT JOIN categoria_servicio_ticket cat ON t.categoria_id = cat.id
        LEFT JOIN donde_ticket d ON t.donde = d.id
        LEFT JOIN detalle_donde_ticket dd ON t.detalle_donde = dd.id
        LEFT JOIN categoria_servicio_ticket sc ON t.subcategoria_id = sc.id
        LEFT JOIN usuarios u ON t.usuario_id = u.Id_Usuario
        LEFT JOIN subcategorias_ticket st ON t.subcategoria_id = st.id
        WHERE (t.id_ejecutante = :user_id OR t.usuario_id = :user_id)
        AND t.estado != 'cancelado'
        ORDER BY t.fecha_creacion DESC
    ");
    $stmt_tickets->bindParam(':user_id', $user_id);
    $stmt_tickets->execute();
    $tickets = $stmt_tickets->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $tickets = [];
}

try {
    // Primero obtenemos el nombre de la subárea del usuario
    $stmt_area = $pdo->prepare("SELECT nombre FROM subareas WHERE id = :id");
    $stmt_area->execute(['id' => $usuario['subarea']]);
    $nombre_subarea = $stmt_area->fetchColumn();

    if ($nombre_subarea) {
        // Ahora buscamos tareas cíclicas comparando por nombre
// Consulta alternativa - SIN JOIN, luego buscamos los nombres
$stmt_tc = $pdo->prepare("
    SELECT *
    FROM tareas_instancias ti
    WHERE ti.area = :nombre_subarea
        AND ti.fecha >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)
    ORDER BY ti.fecha, ti.hora
");
        $stmt_tc->execute(['nombre_subarea' => $nombre_subarea]);
        $tareas_ciclicas = $stmt_tc->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $tareas_ciclicas = [];
    }
} catch (Exception $e) {
    $tareas_ciclicas = [];
    error_log("Error al cargar tareas cíclicas: " . $e->getMessage());
}


// Tareas
try {
    $stmt_tareas = $pdo->prepare("
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
}

// Reuniones
try {
    $stmt_reuniones = $pdo->prepare("
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
}

// Datos para formularios -formularios relaciones
try {
    $areas = $pdo->query("SELECT id, nombre FROM areas ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
    $donde_tickets = $pdo->query("SELECT id, nombre, id_area FROM donde_ticket ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
    $detalle_donde_tickets = $pdo->query("SELECT id, nombre, id_area FROM detalle_donde_ticket ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
    $categorias = $pdo->query("SELECT id, nombre_cat, id_area FROM categoria_servicio_ticket ORDER BY nombre_cat")->fetchAll(PDO::FETCH_ASSOC);
    $subcategorias = $pdo->query("SELECT id, nombre_sucat, id_area, id_catServ FROM subcategorias_ticket ORDER BY nombre_sucat")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $areas = $donde_tickets = $detalle_donde_tickets = $categorias = $subcategorias = [];
}

// Preparar eventos
$events = [];

foreach ($tickets as $ticket) {
    $fecha_evento = !empty($ticket['fecha_compromiso']) ? $ticket['fecha_compromiso'] : $ticket['fecha_creacion'];

    $events[] = [
        'id' => 'ticket_' . $ticket['id_ticket'],
        'type' => 'ticket',
        'title' => 'Ticket #' . $ticket['id_ticket'] . ' - ' . ($ticket['descripcion'] ? substr($ticket['descripcion'], 0, 30) . '...' : 'Sin descripción'),
        'date' => $fecha_evento,
        'time' => isset($ticket['fecha_compromiso']) ? date('H:i', strtotime($ticket['fecha_compromiso'])) : '09:00',
        'department' => strtolower($usuario['departamento_nombre'] ?? 'ti'),
        'priority' => $ticket['prioridad'] ?? 'media',
        'description' => $ticket['descripcion'] ?? 'Sin descripción',
        'status' => $ticket['estado'] ?? 'activo',
        'area' => $ticket['area_nombre'] ?? 'No especificado',
        'categoria' => $ticket['categoria_nombre'] ?? 'No especificado',
        'donde' => $ticket['donde_nombre'] ?? 'No especificado',
        'detalle_donde' => $ticket['detalle_donde_nombre'] ?? 'No especificado',
        'subcategoria' => $ticket['subcategoria_nombre'] ?? 'No especificado',
        'db_data' => $ticket
    ];
}

foreach ($tareas as $tarea) {
    $fecha_evento = !empty($tarea['fecha_compromiso']) ? $tarea['fecha_compromiso'] : $tarea['fecha_creacion'];

    $events[] = [
        'id' => 'tarea_' . $tarea['id_tarea'],
        'type' => 'task',
        'title' => $tarea['titulo'] ?? 'Tarea sin título',
        'date' => $fecha_evento,
        'time' => isset($tarea['hora_compromiso']) ? date('H:i', strtotime($tarea['hora_compromiso'])) : '09:00',
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
        'date' => $reunion['fecha'],
        'time' => isset($reunion['hora']) ? date('H:i', strtotime($reunion['hora'])) : '09:00',
        'department' => strtolower($usuario['departamento_nombre'] ?? 'ti'),
        'description' => $reunion['descripcion'] ?? 'Sin descripción',
        'status' => 'activa',
        'db_data' => $reunion
    ];
}


// Tareas cíclicas - CORREGIDO

/* foreach ($tareas_ciclicas as $tarea_ciclica) {
    $fecha_completa = $tarea_ciclica['fecha'] . ' ' . $tarea_ciclica['hora'];

    $events[] = [
        'id' => $tarea_ciclica['id'],
        'type' => 'recurrent_task',
        'title' => $tarea_ciclica['actividad'] ?? 'Tarea cíclica',
        'date' => $fecha_completa, // Usar la fecha completa
        'time' => isset($tarea_ciclica['hora']) ? date('H:i', strtotime($tarea_ciclica['hora'])) : '12:00',
        'department' => strtolower($usuario['departamento_nombre'] ?? 'ti'),
        'priority' => 'media',
        'description' => $tarea_ciclica['actividad'] ?? 'Sin descripción',
        'status' => $tarea_ciclica['estado'] ?? 'pendiente',
        'categoria' => $tarea_ciclica['categoria'] ?? '',
        'subcategoria' => $tarea_ciclica['subcategoria'] ?? '',
        'puesto' => $tarea_ciclica['puesto'] ?? '',
        'db_data' => $tarea_ciclica
    ];
} */


    foreach ($tareas_ciclicas as $tarea_ciclica) {
    // Combinar fecha y hora para crear un datetime completo
    $fecha_completa = $tarea_ciclica['fecha'] . ' ' . $tarea_ciclica['hora'];
    
    // Buscar nombre del usuario asignado (NO el usuario logueado)
    $nombre_asignado = 'Sin asignar'; // Valor por defecto
    
    if (!empty($tarea_ciclica['asignado_a']) && is_numeric($tarea_ciclica['asignado_a'])) {
        try {
            // BUSCAR EL USUARIO ASIGNADO, NO EL LOGUEADO
            $stmt_nombre = $pdo->prepare("SELECT nombre, apellido FROM usuarios WHERE Id_Usuario = ?");
            $stmt_nombre->execute([$tarea_ciclica['asignado_a']]);
            $usuario_asignado = $stmt_nombre->fetch(PDO::FETCH_ASSOC); // Cambié el nombre de la variable
            
            if ($usuario_asignado) {
                $nombre_asignado = $usuario_asignado['nombre'];
                if (!empty($usuario_asignado['apellido'])) {
                    $nombre_asignado .= ' ' . $usuario_asignado['apellido'];
                }
            } else {
                $nombre_asignado = 'ID: ' . $tarea_ciclica['asignado_a'];
            }
        } catch (Exception $e) {
            $nombre_asignado = 'ID: ' . $tarea_ciclica['asignado_a'];
        }
    } elseif (!empty($tarea_ciclica['asignado_a']) && !is_numeric($tarea_ciclica['asignado_a'])) {
        // Si tiene valor pero no es numérico, usar ese valor
        $nombre_asignado = $tarea_ciclica['asignado_a'];
    }
    // Si no tiene asignado_a o está vacío, se queda con 'Sin asignar'
    
    // Crear db_data con el nombre del asignado
    $db_data = $tarea_ciclica;
    $db_data['asignado_a'] = $nombre_asignado; // Usar el nombre encontrado

    $events[] = [
        'id' => $tarea_ciclica['id'],
        'type' => 'recurrent_task',
        'title' => $tarea_ciclica['actividad'] ?? 'Tarea cíclica',
        'date' => $fecha_completa,
        'time' => isset($tarea_ciclica['hora']) ? date('H:i', strtotime($tarea_ciclica['hora'])) : '12:00',
        'department' => strtolower($usuario['departamento_nombre'] ?? 'ti'), // Este SÍ es el usuario logueado
        'priority' => 'media',
        'description' => $tarea_ciclica['actividad'] ?? 'Sin descripción',
        'status' => $tarea_ciclica['estado'] ?? 'pendiente',
        'categoria' => $tarea_ciclica['categoria'] ?? '',
        'subcategoria' => $tarea_ciclica['subcategoria'] ?? '',
        'puesto' => $tarea_ciclica['puesto'] ?? '',
        'db_data' => $db_data  // Usar el array modificado con el nombre del asignado
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
        <?php include 'includes/menu.php' ?>

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
                    <div class="text-xl lg:text-2xl font-bold text-purple-600"><?php echo count($tareas_ciclicas); ?></div>
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
                        <!-- Botón Carga masiva -->
                        <button id="bulk-upload-btn" class="px-3 lg:px-4 py-2 bg-green-600 text-white rounded-lg font-medium flex items-center space-x-2 hover:bg-green-700 text-sm lg:text-base">
                            <i class="fas fa-file-upload"></i>
                            <span class="hidden lg:inline">Carga masiva</span>
                            <span class="lg:hidden">CSV</span>
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

                        <!-- Cabecera de días -->
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

                        <!-- Contenido de horas -->
                        <div id="week-hours" class="p-2 lg:p-4 overflow-x-auto overflow-y-auto max-h-[75vh]"></div>
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

    <!-- Modal para agregar/editar elementos -->
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
                            <span class="bg-yellow-200 text-yellow-800 text-xs font-semibold px-2 py-0.5 rounded-full">Dev</span>

                        </button>
                    </div>
                </div>

                <!-- Formularios (mantener los que ya tienes) -->
                <div id="ticket-form" class="item-form hidden">
                    <?php include 'tickets/ticket_form.php'; ?>
                </div>

                <div id="task-form" class="item-form hidden">
                    <?php include 'task/task_form.php'; ?>
                </div>

                <div id="meeting-form" class="item-form hidden">
                    <?php include 'meeting/meeting_form.php'; ?>
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

    <!-- Modal para ver detalles (contenido dinámico preservado) -->
    <div id="detail-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 p-4">
        <div class="bg-white rounded-xl w-full max-w-2xl mx-auto max-h-[90vh] overflow-hidden flex flex-col">
            <div class="flex justify-between items-center p-4 lg:p-6 border-b border-gray-200">
                <h3 class="text-base lg:text-lg font-semibold" id="detail-title">Detalles del elemento</h3>
                <button id="close-detail-modal" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center">
                    <i class="fas fa-times text-gray-500"></i>
                </button>
            </div>

            <!-- Contenido dinámico -->
            <div class="p-4 lg:p-6 space-y-6 flex-grow overflow-y-auto" id="detail-content"></div>

            <!-- AGREGAR ESTE DIV PARA EL FOOTER DINÁMICO -->
            <div id="detail-footer"></div>
        </div>
    </div>

    <div id="bulk-modal"
        class="hidden fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center z-50">

        <div class="bg-white w-11/12 max-w-lg rounded-xl shadow-lg p-6 space-y-4">

            <h2 class="text-xl font-semibold">Carga masiva desde CSV</h2>

            <input id="csv-file"
                type="file"
                accept=".csv"
                class="w-full border border-gray-300 rounded-lg p-3">

            <div id="csv-error"
                class="hidden text-red-600 text-sm font-medium">
            </div>

            <div id="csv-preview"
                class="hidden max-h-60 overflow-auto border border-gray-200 rounded-lg p-3 text-sm">
            </div>

            <div class="flex justify-end space-x-3">
                <button id="modal-cancel"
                    class="px-4 py-2 bg-gray-300 rounded-lg hover:bg-gray-400">
                    Cancelar
                </button>

                <button id="modal-process"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    Procesar
                </button>
            </div>

        </div>
    </div>



    <!-- JavaScript externo -->
    <script>
        // Pasar datos de PHP a JavaScript
        const usuario = {
            id: <?php echo $user_id; ?>,
            nombre: "<?php echo $usuario['nombre']; ?>",
            apellido: "<?php echo $usuario['apellido']; ?>",
            departamento: "<?php echo strtolower($usuario['departamento_nombre'] ?? 'ti'); ?>",
            puesto: "<?php echo $usuario['puesto_nombre'] ?? 'Usuario'; ?>"
        };

        const eventsData = <?php echo $events_json; ?>;
    </script>


    <script>
        document.getElementById("bulk-upload-btn").onclick = () => {
            document.getElementById("bulk-modal").classList.remove("hidden");
        };

        document.getElementById("modal-cancel").onclick = () => {
            document.getElementById("bulk-modal").classList.add("hidden");
        };

        document.getElementById("modal-process").onclick = () => processCSV();

        function processCSV() {

            const fileInput = document.getElementById("csv-file");
            const errorBox = document.getElementById("csv-error");
            const previewBox = document.getElementById("csv-preview");

            errorBox.classList.add("hidden");
            previewBox.classList.add("hidden");

            if (!fileInput.files.length) {
                errorBox.textContent = "Selecciona un archivo CSV.";
                errorBox.classList.remove("hidden");
                return;
            }

            const file = fileInput.files[0];
            const reader = new FileReader();

            reader.onload = (e) => {

                let text = e.target.result;

                // Convertir a UTF8 para reparar acentos raros
                text = new TextDecoder("utf-8", {
                    fatal: false
                }).decode(
                    new TextEncoder().encode(text)
                );

                const lines = text.split("\n").map(l => l.trim()).filter(l => l !== "");

                // Detectar si usa tabulador o coma
                const delimiter = lines[0].includes("\t") ? "\t" : ",";

                const expected = [
                    "PUESTO",
                    "Actividad del Puesto",
                    "Categoría de Servicio",
                    "Subcategoría de Servicio",
                    "1era Frecuencia",
                    "Cuándo",
                    "Cada cuánto",
                    "Hora",
                    "Subárea"
                ];

                const header = lines[0].split(delimiter).map(h => h.trim());

                // Validar encabezados ignorando acentos y mayúsculas
                const normalize = (s) =>
                    s.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase();

                if (
                    header.length !== expected.length ||
                    !header.every((h, i) => normalize(h) === normalize(expected[i]))
                ) {
                    errorBox.textContent =
                        "El CSV no coincide con las columnas requeridas. Encabezados detectados: " +
                        header.join(", ");
                    errorBox.classList.remove("hidden");
                    return;
                }

                const data = [];

                // Procesar filas
                for (let i = 1; i < lines.length; i++) {
                    const row = lines[i].split(delimiter);

                    data.push({
                        puesto: row[0],
                        actividad: row[1],
                        categoria: row[2],
                        subcategoria: row[3],
                        frecuencia: row[4], // "1era Frecuencia"
                        cuando: row[5],
                        cada_cuanto: row[6],
                        hora: row[7],
                        area: row[8]
                    });
                }

                previewBox.textContent =
                    "Registros detectados: " + data.length + "\n\n" +
                    JSON.stringify(data, null, 2);

                previewBox.classList.remove("hidden");

                sendDataToServer(data);
            };


            reader.readAsText(file, "UTF-8");
        }

        function sendDataToServer(data) {

            fetch("task/carga_masiva.php", {
                    method: "POST",
                    body: JSON.stringify(data),
                    headers: {
                        "Content-Type": "application/json"
                    }
                })
                .then(r => r.json())
                .then(res => {
                    alert("Carga masiva completada: " + res.message);
                })
                .catch(err => {
                    console.error(err);
                    alert("Error al cargar los datos.");
                });
        }
    </script>


    <script src="tickets/calendar.js"></script>
    <script src="tickets/stats.js"></script>
    <script src="tickets/functions.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/eruda"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        eruda.init();
    </script>
</body>

</html>