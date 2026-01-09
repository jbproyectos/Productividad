<?php
session_start();
include '../../includes/conexionbd.php';

// Recibir folio
$id_ticket = '#' . $_GET['id_ticket'] ?? '';
if (empty($id_ticket)) die('Ticket no válido');
echo $id_ticket;
// Buscar evaluación
$stmt = $pdo->prepare("
    SELECT t.folio, t.descripcion, re.nombre as nombre_ejecutante,
           e.calificacion_atencion, e.calificacion_tiempo, e.calificacion_solucion, e.comentario
    FROM tickets t
    LEFT JOIN responsable_ejec re ON t.id_ejecutante = re.id
    LEFT JOIN evaluaciones_tickets e ON t.id_ticket = e.id_ticket
    WHERE t.folio = ?
");
$stmt->execute([$id_ticket]);
$eval = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$eval) die('No hay evaluación disponible');
?>


<div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full">
        <!-- Header con gradiente -->
        <div class="bg-gradient-to-r from-green-600 to-emerald-600 p-6 rounded-t-xl">
            <div class="flex justify-between items-start">
                <div class="flex items-center space-x-3">
                    <div class="bg-white/20 p-2 rounded-lg">
                        <i class="fas fa-chart-bar text-white text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-white">Resultados de Evaluación</h3>
                        <p class="text-green-100 text-sm mt-1">Detalles completos del servicio</p>
                    </div>
                </div>
                <button onclick="cerrarModalResultados()" class="text-white hover:text-green-200 transition-colors mt-1">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Contenido -->
        <div class="p-6">
            <!-- Info del ticket -->
            <div class="bg-gray-50 p-4 rounded-lg mb-6">
                <div class="flex items-center space-x-3">
                    <div class="bg-blue-100 p-2 rounded-lg">
                        <i class="fas fa-ticket-alt text-blue-600 text-lg"></i>
                    </div>
                    <div class="flex-1">
                        <h4 class="font-semibold text-gray-800"><?= htmlspecialchars($eval['folio']) ?></h4>
                        <p class="text-sm text-gray-600 mt-1"><?= htmlspecialchars($eval['descripcion']) ?></p>
                        <div class="flex items-center mt-2 text-sm text-gray-500">
                            <i class="fas fa-user mr-2"></i>
                            <?= htmlspecialchars($eval['nombre_ejecutante']) ?>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Grid de calificaciones -->
            <div class="grid grid-cols-2 gap-4 mb-6">
                <!-- Atención -->
                <div class="bg-white p-4 rounded-lg border border-gray-200">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-semibold text-gray-700">Atención</span>
                        <span class="text-lg font-bold text-blue-600"><?= $eval['calificacion_atencion'] ?></span>
                    </div>
                    <div class="flex space-x-1">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star text-<?= $i <= $eval['calificacion_atencion'] ? 'yellow-400' : 'gray-300' ?> text-sm"></i>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- Tiempo -->
                <div class="bg-white p-4 rounded-lg border border-gray-200">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-semibold text-gray-700">Tiempo</span>
                        <span class="text-lg font-bold text-green-600"><?= $eval['calificacion_tiempo'] ?></span>
                    </div>
                    <div class="flex space-x-1">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star text-<?= $i <= $eval['calificacion_tiempo'] ? 'yellow-400' : 'gray-300' ?> text-sm"></i>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- Solución -->
                <div class="bg-white p-4 rounded-lg border border-gray-200">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-semibold text-gray-700">Solución</span>
                        <span class="text-lg font-bold text-purple-600"><?= $eval['calificacion_solucion'] ?></span>
                    </div>
                    <div class="flex space-x-1">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star text-<?= $i <= $eval['calificacion_solucion'] ? 'yellow-400' : 'gray-300' ?> text-sm"></i>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- Comunicación -->
                <div class="bg-white p-4 rounded-lg border border-gray-200">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-semibold text-gray-700">Comunicación (no bd)</span>
                        <span class="text-lg font-bold text-indigo-600"><?= $eval['calificacion_comunicacion'] ?></span>
                    </div>
                    <div class="flex space-x-1">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star text-<?= $i <= $eval['calificacion_comunicacion'] ? 'yellow-400' : 'gray-300' ?> text-sm"></i>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>

            <!-- Comentario -->
            <?php if (!empty($eval['comentario'])): ?>
                <div class="mb-6">
                    <label class="block text-sm font-semibold text-gray-700 mb-3 flex items-center">
                        <i class="fas fa-comment mr-2 text-gray-500"></i>
                        Comentario
                    </label>
                    <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                        <p class="text-sm text-gray-700 leading-relaxed"><?= nl2br(htmlspecialchars($eval['comentario'])) ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Fecha -->
            <div class="text-center">
                <p class="text-xs text-gray-500">
                    Evaluado el <?= date('d/m/Y H:i', strtotime($eval['fecha_evaluacion'])) ?>
                </p>
            </div>
        </div>

        <!-- Footer -->
        <div class="bg-gray-50 px-6 py-4 rounded-b-xl border-t border-gray-200">
            <div class="flex justify-end">
                <button onclick="cerrarModalResultados()"
                        class="px-6 py-2 bg-gray-600 text-white rounded-lg font-medium hover:bg-gray-700 transition-colors duration-200">
                    Cerrar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function cerrarModalResultados() {
    document.querySelector('#modalResultados').remove();
}
</script>