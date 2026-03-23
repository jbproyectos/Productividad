<?php
session_start();
include '../includes/conexionbd.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$id_tarea = $_GET['id'] ?? 0;

// Obtener detalles de la tarea
$sql = "SELECT t.*, 
           u_creador.nombre as creador_nombre, u_creador.apellido as creador_apellido,
           u_ejec.nombre as ejecutor_nombre, u_ejec.apellido as ejecutor_apellido,
           u_sup.nombre as supervisor_nombre, u_sup.apellido as supervisor_apellido,
           a.nombre as area_nombre,
           d.nombre as donde_nombre,
           dd.nombre as detalle_nombre,
           c.nombre_cat as categoria_nombre,
           sc.nombre_sucat as subcategoria_nombre
    FROM tareas t
    LEFT JOIN usuarios u_creador ON t.id_usuario_creador = u_creador.Id_Usuario
    LEFT JOIN usuarios u_ejec ON t.id_responsable_ejecucion = u_ejec.Id_Usuario
    LEFT JOIN usuarios u_sup ON t.id_responsable_supervision = u_sup.Id_Usuario
    LEFT JOIN areas a ON t.id_area = a.id
    LEFT JOIN donde_ticket d ON t.id_donde = d.id
    LEFT JOIN detalle_donde_ticket dd ON t.id_detalle_donde = dd.id
    LEFT JOIN categoria_servicio_ticket c ON t.id_categoria = c.id
    LEFT JOIN subcategorias_ticket sc ON t.id_subcategoria = sc.id
    WHERE t.id_tarea = ? AND t.activo = 1";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id_tarea]);
$tarea = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tarea) {
    $_SESSION['error'] = "Tarea no encontrada";
    header('Location: tareas.php?tab=unicas');
    exit;
}

$seguimiento = json_decode($tarea['seguimiento'], true);
$estados_texto = [
    'pendiente' => 'Pendiente',
    'en_proceso' => 'En Proceso',
    'completada' => 'Completada',
    'cancelada' => 'Cancelada'
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($tarea['codigo_tarea']) ?> - Detalle</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .estado-pendiente { background-color: #FEF3C7; color: #92400E; }
        .estado-en_proceso { background-color: #DBEAFE; color: #1E40AF; }
        .estado-completada { background-color: #D1FAE5; color: #065F46; }
        .estado-cancelada { background-color: #FEE2E2; color: #991B1B; }
        .badge-prioridad-baja { background-color: #6B7280; color: white; }
        .badge-prioridad-media { background-color: #3B82F6; color: white; }
        .badge-prioridad-alta { background-color: #F59E0B; color: white; }
        .badge-prioridad-urgente { background-color: #EF4444; color: white; }
        .badge-prioridad-critica { background-color: #8B5CF6; color: white; }
    </style>
</head>
<body class="bg-gray-50">
    <div class="max-w-7xl mx-auto py-8 px-4">
        <!-- Header -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <div class="flex justify-between items-start">
                <div>
                    <div class="flex items-center gap-3 mb-2">
                        <span class="text-sm font-medium text-blue-600 bg-blue-50 px-3 py-1 rounded-full">
                            <i class="fas fa-tasks mr-1"></i>Tarea Única
                        </span>
                        <span class="text-gray-400">|</span>
                        <span class="text-sm text-gray-500">Creada <?= date('d/m/Y H:i', strtotime($tarea['fecha_creacion'])) ?></span>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-900"><?= htmlspecialchars($tarea['codigo_tarea']) ?></h1>
                    <p class="text-gray-600 mt-2"><?= nl2br(htmlspecialchars($tarea['descripcion'])) ?></p>
                </div>
                <div class="flex gap-2">
                    <span class="px-4 py-2 rounded-lg text-sm font-semibold estado-<?= $tarea['estatus'] ?>">
                        <?= $estados_texto[$tarea['estatus']] ?? ucfirst($tarea['estatus']) ?>
                    </span>
                    <span class="px-4 py-2 rounded-lg text-sm font-semibold badge-prioridad-<?= $tarea['prioridad'] ?>">
                        <?= ucfirst($tarea['prioridad'] ?? 'media') ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Grid de información -->
        <div class="grid grid-cols-3 gap-6">
            <!-- Columna izquierda - Información principal -->
            <div class="col-span-2 space-y-6">
                <!-- Información general -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-info-circle text-blue-500 mr-2"></i>Información General
                    </h2>
                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <dl class="space-y-3">
                                <div>
                                    <dt class="text-sm text-gray-500">Tipo de Trabajo</dt>
                                    <dd class="font-medium text-gray-900"><?= ucfirst($tarea['tipo_trabajo'] ?? 'N/A') ?></dd>
                                </div>
                                <div>
                                    <dt class="text-sm text-gray-500">Rubro</dt>
                                    <dd class="font-medium text-gray-900"><?= ucfirst(str_replace('_', ' ', $tarea['rubro'] ?? 'N/A')) ?></dd>
                                </div>
                                <div>
                                    <dt class="text-sm text-gray-500">Área</dt>
                                    <dd class="font-medium text-gray-900"><?= htmlspecialchars($tarea['area_nombre'] ?? 'N/A') ?></dd>
                                </div>
                                <div>
                                    <dt class="text-sm text-gray-500">Categoría</dt>
                                    <dd class="font-medium text-gray-900"><?= htmlspecialchars($tarea['categoria_nombre'] ?? 'N/A') ?></dd>
                                    <?php if ($tarea['subcategoria_nombre']): ?>
                                        <dd class="text-sm text-gray-600"><?= htmlspecialchars($tarea['subcategoria_nombre']) ?></dd>
                                    <?php endif; ?>
                                </div>
                            </dl>
                        </div>
                        <div>
                            <dl class="space-y-3">
                                <?php if ($tarea['donde_nombre']): ?>
                                <div>
                                    <dt class="text-sm text-gray-500">Ubicación</dt>
                                    <dd class="font-medium text-gray-900"><?= htmlspecialchars($tarea['donde_nombre']) ?></dd>
                                    <?php if ($tarea['detalle_nombre']): ?>
                                        <dd class="text-sm text-gray-600"><?= htmlspecialchars($tarea['detalle_nombre']) ?></dd>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                                <?php if ($tarea['fecha_limite']): ?>
                                <div>
                                    <dt class="text-sm text-gray-500">Fecha Límite</dt>
                                    <dd class="font-medium <?= (strtotime($tarea['fecha_limite']) < time() && $tarea['estatus'] != 'completada') ? 'text-red-600' : 'text-gray-900' ?>">
                                        <?= date('d/m/Y H:i', strtotime($tarea['fecha_limite'])) ?>
                                    </dd>
                                </div>
                                <?php endif; ?>
                                <?php if ($tarea['fecha_completado']): ?>
                                <div>
                                    <dt class="text-sm text-gray-500">Completada</dt>
                                    <dd class="font-medium text-gray-900"><?= date('d/m/Y H:i', strtotime($tarea['fecha_completado'])) ?></dd>
                                </div>
                                <?php endif; ?>
                            </dl>
                        </div>
                    </div>
                </div>

                <!-- Seguimiento Timeline -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-history text-blue-500 mr-2"></i>Seguimiento
                    </h2>
                    <div class="space-y-4">
                        <?php if (!empty($seguimiento)): ?>
                            <?php foreach(array_reverse($seguimiento) as $item): ?>
                                <div class="flex gap-4">
                                    <div class="flex-shrink-0">
                                        <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
                                            <i class="fas fa-user text-blue-600"></i>
                                        </div>
                                    </div>
                                    <div class="flex-1 bg-gray-50 rounded-lg p-4">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <span class="font-medium text-gray-900"><?= htmlspecialchars($item['usuario'] ?? $item['usuario_nombre'] ?? 'Sistema') ?></span>
                                                <span class="text-sm text-gray-500 ml-2"><?= date('d/m/Y H:i', strtotime($item['fecha'])) ?></span>
                                            </div>
                                            <span class="text-xs bg-gray-200 text-gray-700 px-2 py-1 rounded">
                                                <?= ucfirst($item['accion'] ?? 'evento') ?>
                                            </span>
                                        </div>
                                        <?php if (isset($item['comentario']) && $item['comentario']): ?>
                                            <p class="text-gray-700 mt-2"><?= nl2br(htmlspecialchars($item['comentario'])) ?></p>
                                        <?php endif; ?>
                                        <?php if (isset($item['estado_anterior']) && isset($item['estado_nuevo'])): ?>
                                            <p class="text-sm text-gray-500 mt-2">
                                                Cambió de <span class="bg-yellow-100 px-2 py-0.5 rounded"><?= $item['estado_anterior'] ?></span>
                                                a <span class="bg-green-100 px-2 py-0.5 rounded"><?= $item['estado_nuevo'] ?></span>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-gray-500 text-center py-4">No hay seguimiento disponible</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Agregar comentario -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4">Agregar Comentario</h2>
                    <form id="formComentario" onsubmit="agregarComentario(event, <?= $id_tarea ?>)">
                        <textarea id="comentario" rows="3" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Escribe un comentario..."></textarea>
                        <div class="flex justify-end mt-3">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium">
                                <i class="fas fa-paper-plane mr-2"></i>Enviar
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Columna derecha - Responsables y acciones -->
            <div class="space-y-6">
                <!-- Responsables -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-users text-blue-500 mr-2"></i>Responsables
                    </h2>
                    <div class="space-y-4">
                        <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                            <div class="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center mr-3">
                                <i class="fas fa-pen text-purple-600"></i>
                            </div>
                            <div>
                                <div class="text-sm text-gray-500">Creador</div>
                                <div class="font-medium"><?= htmlspecialchars($tarea['creador_nombre'] . ' ' . ($tarea['creador_apellido'] ?? '')) ?></div>
                            </div>
                        </div>
                        
                        <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                            <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center mr-3">
                                <i class="fas fa-user-check text-green-600"></i>
                            </div>
                            <div>
                                <div class="text-sm text-gray-500">Ejecutor</div>
                                <div class="font-medium"><?= htmlspecialchars($tarea['ejecutor_nombre'] . ' ' . ($tarea['ejecutor_apellido'] ?? '')) ?></div>
                            </div>
                        </div>

                        <?php if (!empty($tarea['supervisor_nombre'])): ?>
                        <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                            <div class="w-10 h-10 rounded-full bg-yellow-100 flex items-center justify-center mr-3">
                                <i class="fas fa-eye text-yellow-600"></i>
                            </div>
                            <div>
                                <div class="text-sm text-gray-500">Supervisor</div>
                                <div class="font-medium"><?= htmlspecialchars($tarea['supervisor_nombre'] . ' ' . ($tarea['supervisor_apellido'] ?? '')) ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Acciones rápidas -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4">Acciones</h2>
                    <div class="space-y-3">
                        <?php if ($tarea['estatus'] != 'completada' && $tarea['estatus'] != 'cancelada'): ?>
                        <select id="cambioEstado" class="w-full border border-gray-300 rounded-lg px-3 py-2" onchange="cambiarEstado(<?= $id_tarea ?>, this.value)">
                            <option value="">Cambiar estado...</option>
                            <option value="en_proceso">En Proceso</option>
                            <option value="completada">Completada</option>
                            <option value="cancelada">Cancelada</option>
                        </select>
                        <?php endif; ?>

                        <a href="editar_tarea_unica.php?id=<?= $id_tarea ?>" 
                           class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium flex items-center justify-center gap-2">
                            <i class="fas fa-edit"></i> Editar Tarea
                        </a>
                        
                        <button onclick="duplicarTarea(<?= $id_tarea ?>)" 
                                class="w-full bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg font-medium flex items-center justify-center gap-2">
                            <i class="fas fa-copy"></i> Duplicar
                        </button>
                        
                        <?php if ($tarea['estatus'] != 'cancelada'): ?>
                        <button onclick="cancelarTarea(<?= $id_tarea ?>)" 
                                class="w-full bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium flex items-center justify-center gap-2">
                            <i class="fas fa-ban"></i> Cancelar Tarea
                        </button>
                        <?php endif; ?>
                        
                        <a href="../tasks.php?tab=unicas" 
                           class="w-full bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg font-medium flex items-center justify-center gap-2">
                            <i class="fas fa-arrow-left"></i> Volver
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function mostrarToast(mensaje, tipo = 'success') {
            alert(mensaje); // Simple por ahora
        }

        function cambiarEstado(id, nuevoEstado) {
            if (!nuevoEstado) return;
            
            if (confirm(`¿Cambiar estado a "${nuevoEstado.replace('_', ' ')}"?`)) {
                fetch('task/actualizar_estado.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id, estado: nuevoEstado })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
            }
        }

        function agregarComentario(event, id) {
            event.preventDefault();
            const comentario = document.getElementById('comentario').value;
            
            if (!comentario.trim()) {
                alert('Escribe un comentario');
                return;
            }

            fetch('task/agregar_comentario.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    id: id, 
                    comentario: comentario 
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }

        function duplicarTarea(id) {
            if (confirm('¿Duplicar esta tarea?')) {
                fetch('task/duplicar_tarea.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = 'tareas.php?tab=unicas';
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
            }
        }

        function cancelarTarea(id) {
            const motivo = prompt('Motivo de cancelación:');
            if (motivo !== null) {
                fetch('task/cancelar_tarea.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        id: id, 
                        motivo: motivo 
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
            }
        }
    </script>
</body>
</html>