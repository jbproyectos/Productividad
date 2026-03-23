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

// Obtener supervisores del área del usuario
$supervisores = [];
if ($area_usuario) {
    $sql_supervisores = "SELECT id, nombre FROM responsable_sup WHERE id_area = :area_usuario";
    $stmt_supervisores = $pdo->prepare($sql_supervisores);
    $stmt_supervisores->execute([':area_usuario' => $area_usuario]);
    $supervisores = $stmt_supervisores->fetchAll(PDO::FETCH_ASSOC);
}

// Obtener ejecutantes del área del usuario
$ejecutantes = [];
if ($area_usuario) {
    $sql_ejecutantes = "SELECT id, nombre FROM responsable_ejec WHERE id_area = :area_usuario";
    $stmt_ejecutantes = $pdo->prepare($sql_ejecutantes);
    $stmt_ejecutantes->execute([':area_usuario' => $area_usuario]);
    $ejecutantes = $stmt_ejecutantes->fetchAll(PDO::FETCH_ASSOC);
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
WHERE usuario_id = ?";

$stmt_contadores = $pdo->prepare($sql_contadores);
$stmt_contadores->execute([$usuario_id]);
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
        WHERE t.usuario_id = ?
        ORDER BY t.fecha_creacion DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$usuario_id]);
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
                        <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
                            <tr>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    <div class="flex items-center space-x-1">
                                        <i class="fas fa-hashtag text-gray-400"></i>
                                        <span>Folio</span>
                                    </div>
                                </th>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    <div class="flex items-center space-x-1">
                                        <i class="fas fa-align-left text-gray-400"></i>
                                        <span>Descripción</span>
                                    </div>
                                </th>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    <div class="flex items-center space-x-1">
                                        <i class="fas fa-user-tie text-gray-400"></i>
                                        <span>Supervisor</span>
                                    </div>
                                </th>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    <div class="flex items-center space-x-1">
                                        <i class="fas fa-user-cog text-gray-400"></i>
                                        <span>Ejecutante</span>
                                    </div>
                                </th>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    <div class="flex items-center space-x-1">
                                        <i class="fas fa-exclamation-triangle text-gray-400"></i>
                                        <span>Prioridad</span>
                                    </div>
                                </th>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    <div class="flex items-center space-x-1">
                                        <i class="fas fa-circle text-gray-400"></i>
                                        <span>Estado</span>
                                    </div>
                                </th>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    <div class="flex items-center space-x-1">
                                        <i class="fas fa-star text-gray-400"></i>
                                        <span>Evaluación</span>
                                    </div>
                                </th>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    <div class="flex items-center space-x-1">
                                        <i class="fas fa-calendar-alt text-gray-400"></i>
                                        <span>Fecha</span>
                                    </div>
                                </th>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    <div class="flex items-center space-x-1">
                                        <i class="fas fa-cog text-gray-400"></i>
                                        <span>Acciones</span>
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($tickets as $ticket):
                                // Determinar clase de prioridad basada en días
                                $prioridad_valor = intval($ticket['prioridad'] ?? 0);
                                if ($prioridad_valor <= 2) {
                                    $prioridad_clase = 'priority-critical';
                                    $prioridad_texto = 'Crítica';
                                    $prioridad_color = 'bg-red-100 text-red-800 border-red-200';
                                    $prioridad_icono = 'fa-bolt';
                                } elseif ($prioridad_valor <= 7) {
                                    $prioridad_clase = 'priority-high';
                                    $prioridad_texto = 'Alta';
                                    $prioridad_color = 'bg-orange-100 text-orange-800 border-orange-200';
                                    $prioridad_icono = 'fa-arrow-up';
                                } elseif ($prioridad_valor <= 15) {
                                    $prioridad_clase = 'priority-medium';
                                    $prioridad_texto = 'Media';
                                    $prioridad_color = 'bg-yellow-100 text-yellow-800 border-yellow-200';
                                    $prioridad_icono = 'fa-minus';
                                } else {
                                    $prioridad_clase = 'priority-low';
                                    $prioridad_texto = 'Baja';
                                    $prioridad_color = 'bg-green-100 text-green-800 border-green-200';
                                    $prioridad_icono = 'fa-arrow-down';
                                }

                                // Determinar clase de estado
                                $estado_clase = match ($ticket['estado']) {
                                    'Pendiente' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
                                    'En Proceso' => 'bg-blue-100 text-blue-800 border-blue-200',
                                    'Resuelto' => 'bg-green-100 text-green-800 border-green-200',
                                    'Cerrado' => 'bg-gray-100 text-gray-800 border-gray-200',
                                    'Cancelado' => 'bg-red-100 text-red-800 border-red-200',
                                    default => 'bg-gray-100 text-gray-800 border-gray-200'
                                };

                                $estado_icono = match ($ticket['estado']) {
                                    'Pendiente' => 'fa-clock',
                                    'En Proceso' => 'fa-spinner fa-pulse',
                                    'Resuelto' => 'fa-check-circle',
                                    'Cerrado' => 'fa-lock',
                                    'Cancelado' => 'fa-times-circle',
                                    default => 'fa-question-circle'
                                };

                                // Calcular días transcurridos
                                $fecha_creacion = new DateTime($ticket['fecha_creacion']);
                                $hoy = new DateTime();
                                $dias_transcurridos = $hoy->diff($fecha_creacion)->days;
                            ?>
                                <tr class="ticket-row <?= $prioridad_clase ?> group hover:bg-gradient-to-r hover:from-blue-50/50 hover:to-indigo-50/50 transition-all duration-200">
                                    <!-- Folio -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            
                                            <div class="ml-3">
                                                <span class="text-sm font-mono font-medium text-gray-900"><?= htmlspecialchars($ticket['folio']) ?></span>
                                                <?php if ($dias_transcurridos > 0): ?>
                                                    <div class="text-xs text-gray-500">
                                                        <i class="far fa-calendar-alt mr-1"></i><?= $dias_transcurridos ?> días
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Descripción -->
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900 font-medium group-hover:text-blue-600 transition-colors line-clamp-2 max-w-xs">
                                            <?= htmlspecialchars($ticket['descripcion']) ?>
                                        </div>
                                        <div class="flex items-center mt-1 space-x-2">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">
                                                <i class="fas fa-folder-open mr-1"></i>
                                                <?= htmlspecialchars($ticket['area'] ?? 'Sin área') ?>
                                            </span>
                                            <?php if (!empty($ticket['categoria'])): ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-600">
                                                    <?= htmlspecialchars($ticket['categoria']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <!-- Supervisor -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($ticket['supervisor_nombre']): ?>
                                            <div class="flex items-center">
                                                <div class="w-7 h-7 rounded-full bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center text-white text-xs font-bold shadow-sm">
                                                    <?= substr($ticket['supervisor_nombre'], 0, 1) ?>
                                                </div>
                                                <div class="ml-2">
                                                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($ticket['supervisor_nombre']) ?></div>
                                                    <div class="text-xs text-gray-500">Supervisor</div>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="flex items-center text-gray-400">
                                                <i class="fas fa-user-slash text-sm mr-2"></i>
                                                <span class="text-sm italic">Pendiente</span>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Ejecutante -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($ticket['ejecutante_nombre']): ?>
                                            <div class="flex items-center">
                                                <div class="w-7 h-7 rounded-full bg-gradient-to-br from-green-400 to-green-600 flex items-center justify-center text-white text-xs font-bold shadow-sm">
                                                    <?= substr($ticket['ejecutante_nombre'], 0, 1) ?>
                                                </div>
                                                <div class="ml-2">
                                                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($ticket['ejecutante_nombre']) ?></div>
                                                    <div class="text-xs text-gray-500">Ejecutante</div>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="flex items-center text-gray-400">
                                                <i class="fas fa-user-slash text-sm mr-2"></i>
                                                <span class="text-sm italic">Pendiente</span>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Prioridad -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex flex-col space-y-1">
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium <?= $prioridad_color ?>">
                                                <i class="fas <?= $prioridad_icono ?> mr-1"></i>
                                                <?= $prioridad_texto ?>
                                            </span>
                                            <span class="text-xs text-gray-500">
                                                <i class="far fa-clock mr-1"></i><?= $prioridad_valor ?> días
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Estado -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex flex-col space-y-1">
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium <?= $estado_clase ?>">
                                                <i class="fas <?= $estado_icono ?> mr-1"></i>
                                                <?= htmlspecialchars($ticket['estado'] ?? '') ?>
                                            </span>
                                            <?php if ($ticket['estado'] === 'En Proceso' && isset($ticket['fecha_compromiso'])): ?>
                                                <span class="text-xs <?= strtotime($ticket['fecha_compromiso']) < time() ? 'text-red-600' : 'text-green-600' ?>">
                                                    <i class="far fa-calendar-check mr-1"></i>
                                                    <?= date('d/m', strtotime($ticket['fecha_compromiso'])) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <!-- Evaluación -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php
                                        $stmt_eval = $pdo->prepare("SELECT id_evaluacion, calificacion_atencion, calificacion_tiempo, calificacion_solucion FROM evaluaciones_tickets WHERE id_ticket = ?");
                                        $stmt_eval->execute([$ticket['id_ticket']]);
                                        $evaluacion = $stmt_eval->fetch(PDO::FETCH_ASSOC);
                                        ?>

                                        <?php if ($ticket['estado'] === 'Cerrado'): ?>
                                            <?php if ($evaluacion): ?>
                                                <button onclick="verResultadosEvaluacion('<?= ltrim($ticket['folio'], '#') ?>')"
                                                    class="group relative inline-flex items-center px-3 py-1.5 bg-gradient-to-r from-green-50 to-emerald-50 text-green-700 rounded-lg border border-green-200 hover:from-green-100 hover:to-emerald-100 transition-all duration-200 shadow-sm hover:shadow"
                                                    title="Ver evaluación completa">
                                                    <div class="flex items-center">
                                                        <?php
                                                        $promedio = ($evaluacion['calificacion_atencion'] + $evaluacion['calificacion_tiempo'] + $evaluacion['calificacion_solucion']) / 3;
                                                        $estrellas = round($promedio);
                                                        for ($i = 1; $i <= 5; $i++):
                                                        ?>
                                                            <i class="fas fa-star <?= $i <= $estrellas ? 'text-yellow-400' : 'text-gray-300' ?> text-xs"></i>
                                                        <?php endfor; ?>
                                                    </div>
                                                    <span class="ml-1 text-xs font-medium"><?= number_format($promedio, 1) ?></span>
                                                </button>
                                            <?php else: ?>
                                                <button onclick="mostrarEvaluacion('<?= ltrim($ticket['folio'], '#') ?>', '<?= $ticket['id_ejecutante'] ?>')"
                                                    class="inline-flex items-center px-3 py-1.5 bg-gradient-to-r from-purple-50 to-pink-50 text-purple-700 rounded-lg border border-purple-200 hover:from-purple-100 hover:to-pink-100 transition-all duration-200 shadow-sm hover:shadow group"
                                                    title="Calificar este ticket">
                                                    <i class="fas fa-star text-purple-400 group-hover:text-purple-600 mr-1 text-xs"></i>
                                                    <span class="text-xs font-medium">Evaluar</span>
                                                    <i class="fas fa-chevron-right ml-1 text-purple-400 group-hover:translate-x-0.5 transition-transform text-xs"></i>
                                                </button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-3 py-1.5 bg-gray-50 text-gray-500 rounded-lg border border-gray-200 text-xs">
                                                <i class="fas fa-hourglass-half mr-1"></i>
                                                Pendiente
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Fecha -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex flex-col">
                                            <span class="text-sm font-medium text-gray-900">
                                                <?= date('d/m/Y', strtotime($ticket['fecha_creacion'])) ?>
                                            </span>
                                            <span class="text-xs text-gray-500">
                                                <i class="far fa-clock mr-1"></i>
                                                <?= date('H:i', strtotime($ticket['fecha_creacion'])) ?> hrs
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Acciones -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center space-x-2">
                                            <!-- Ver ticket 
                                            <a href="ver_ticket.php?id=<?= $ticket['id_ticket'] ?>"
                                                class="p-2 text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded-lg transition-all duration-200 group relative"
                                                title="Ver detalles completos">
                                                <i class="fas fa-eye"></i>
                                                <span class="absolute -top-8 left-1/2 transform -translate-x-1/2 bg-gray-800 text-white text-xs py-1 px-2 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">
                                                    Ver detalles
                                                </span>
                                            </a>-->

                                            <!-- Editar ticket 
                                            <a href="editar_ticket.php?id=<?= $ticket['id_ticket'] ?>"
                                                class="p-2 text-amber-600 hover:text-amber-800 hover:bg-amber-50 rounded-lg transition-all duration-200 group relative"
                                                title="Editar ticket">
                                                <i class="fas fa-edit"></i>
                                                <span class="absolute -top-8 left-1/2 transform -translate-x-1/2 bg-gray-800 text-white text-xs py-1 px-2 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">
                                                    Editar
                                                </span>
                                            </a> -->

                                            <!-- WhatsApp -->
                                            <?php
                                            $mensaje_whatsapp = urlencode(
                                                "🚀 *NUEVO TICKET ASIGNADO* 🚀\n\n" .
                                                    "┌─────────────────────┐\n" .
                                                    "📋 *Folio:* {$ticket['folio']}\n" .
                                                    "📝 *Descripción:* {$ticket['descripcion']}\n" .
                                                    "🏢 *Área:* {$ticket['area']}\n" .
                                                    "⚡ *Prioridad:* {$prioridad_texto} ({$prioridad_valor} días)\n" .
                                                    "📊 *Estado:* {$ticket['estado']}\n" .
                                                    "└─────────────────────┘\n\n" .
                                                    "🔗 Ver más: " . (isset($_SERVER['HTTPS']) ? "https://" : "http://") . $_SERVER['HTTP_HOST'] . "/ver_ticket.php?id={$ticket['id_ticket']}"
                                            );
                                            $numero_grupo = "528130711406";
                                            ?>
                                            <a href="https://wa.me/<?= $numero_grupo ?>?text=<?= $mensaje_whatsapp ?>"
                                                target="_blank"
                                                class="p-2 text-green-600 hover:text-green-800 hover:bg-green-50 rounded-lg transition-all duration-200 group relative"
                                                title="Compartir por WhatsApp">
                                                <i class="fab fa-whatsapp"></i>
                                                <span class="absolute -top-8 left-1/2 transform -translate-x-1/2 bg-gray-800 text-white text-xs py-1 px-2 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">
                                                    WhatsApp
                                                </span>
                                            </a>

                                            <!-- Historial 
                                            <button onclick="verHistorial(<?= $ticket['id_ticket'] ?>)"
                                                class="p-2 text-purple-600 hover:text-purple-800 hover:bg-purple-50 rounded-lg transition-all duration-200 group relative"
                                                title="Ver historial de cambios">
                                                <i class="fas fa-history"></i>
                                                <span class="absolute -top-8 left-1/2 transform -translate-x-1/2 bg-gray-800 text-white text-xs py-1 px-2 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">
                                                    Historial
                                                </span>
                                            </button>-->

                                            <!-- Más opciones 
                                            <div class="relative" x-data="{ open: false }">
                                                <button @click="open = !open"
                                                    class="p-2 text-gray-600 hover:text-gray-800 hover:bg-gray-50 rounded-lg transition-all duration-200 group relative"
                                                    title="Más opciones">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <div x-show="open"
                                                    @click.away="open = false"
                                                    class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-xl border border-gray-200 py-1 z-10"
                                                    x-transition:enter="transition ease-out duration-100"
                                                    x-transition:enter-start="transform opacity-0 scale-95"
                                                    x-transition:enter-end="transform opacity-100 scale-100"
                                                    x-transition:leave="transition ease-in duration-75"
                                                    x-transition:leave-start="transform opacity-100 scale-100"
                                                    x-transition:leave-end="transform opacity-0 scale-95"
                                                    style="display: none;">
                                                    <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                        <i class="fas fa-print mr-2"></i>Imprimir
                                                    </a>
                                                    <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                        <i class="fas fa-share-alt mr-2"></i>Compartir
                                                    </a>
                                                    <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                        <i class="fas fa-clipboard mr-2"></i>Duplicar
                                                    </a>
                                                    <hr class="my-1">
                                                    <a href="#" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                                        <i class="fas fa-archive mr-2"></i>Archivar
                                                    </a>
                                                </div>
                                            </div>-->
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (empty($tickets)): ?>
                                <tr>
                                    <td colspan="9" class="px-6 py-12 text-center">
                                        <div class="flex flex-col items-center justify-center text-gray-400">
                                            <i class="fas fa-ticket-alt text-5xl mb-3"></i>
                                            <p class="text-lg font-medium">No hay tickets para mostrar</p>
                                            <p class="text-sm">Comienza creando un nuevo ticket</p>
                                            <a href="crear_ticket.php" class="mt-4 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                                <i class="fas fa-plus mr-2"></i>Crear Ticket
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <style>
                        /* Estilos adicionales para mejorar la tabla */
                        .ticket-row {
                            transition: all 0.2s ease;
                        }

                        .ticket-row.priority-critical {
                            border-left: 4px solid #dc2626;
                        }

                        .ticket-row.priority-high {
                            border-left: 4px solid #f97316;
                        }

                        .ticket-row.priority-medium {
                            border-left: 4px solid #eab308;
                        }

                        .ticket-row.priority-low {
                            border-left: 4px solid #22c55e;
                        }

                        .line-clamp-2 {
                            display: -webkit-box;
                            -webkit-line-clamp: 2;
                            -webkit-box-orient: vertical;
                            overflow: hidden;
                        }

                        /* Animaciones para los tooltips */
                        [title] {
                            position: relative;
                        }

                        [title]:hover::after {
                            content: attr(title);
                            position: absolute;
                            bottom: 100%;
                            left: 50%;
                            transform: translateX(-50%);
                            background: #1f2937;
                            color: white;
                            padding: 4px 8px;
                            border-radius: 4px;
                            font-size: 12px;
                            white-space: nowrap;
                            z-index: 50;
                            margin-bottom: 5px;
                        }
                    </style>

                    <!-- Agregar Alpine.js para el menú desplegable -->
                    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

                    <script>
                        function verHistorial(ticketId) {
                            // Función para ver historial del ticket
                            Swal.fire({
                                title: 'Historial del Ticket',
                                html: '<div class="text-center">Cargando historial...</div>',
                                showConfirmButton: false,
                                didOpen: async () => {
                                    try {
                                        const response = await fetch(`tickets/historial.php?id=${ticketId}`);
                                        const data = await response.text();
                                        Swal.update({
                                            html: data,
                                            showConfirmButton: true
                                        });
                                    } catch (error) {
                                        Swal.fire('Error', 'No se pudo cargar el historial', 'error');
                                    }
                                }
                            });
                        }
                    </script>
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