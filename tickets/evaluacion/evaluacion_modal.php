<?php
session_start();
include '../../includes/conexionbd.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$id_ticket = isset($_GET['id_ticket']) ? trim($_GET['id_ticket']) : '';

if (!str_starts_with($id_ticket, '#')) {
    $id_ticket = '#' . $id_ticket;
}

try {
    $stmt = $pdo->prepare("
        SELECT t.folio, t.descripcion, t.id_ejecutante, re.nombre AS nombre_ejecutante, t.estado
        FROM tickets t 
        LEFT JOIN responsable_ejec re ON t.id_ejecutante = re.id 
        WHERE t.folio = ? AND t.estado = 'Cerrado'
    ");
    $stmt->execute([$id_ticket]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        echo "<div class='p-6 text-red-600 font-semibold'>⚠️ Ticket no encontrado o no está cerrado.</div>";
        exit;
    }

    $stmt = $pdo->prepare("SELECT id_evaluacion FROM evaluaciones_tickets WHERE id_ticket = ?");
    $stmt->execute([$id_ticket]);
    $ya_evaluado = $stmt->fetch();

} catch (PDOException $e) {
    echo "<div class='p-6 text-red-600 font-semibold'>Error SQL: " . htmlspecialchars($e->getMessage()) . "</div>";
    exit;
}
?>

<div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[85vh] overflow-y-auto">
        <!-- Header simple -->
        <div class="bg-blue-600 p-6 rounded-t-xl">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-xl font-bold text-white">Evaluar Servicio</h3>
                    <p class="text-blue-100 text-sm mt-1">Ticket: <?php echo htmlspecialchars($ticket['folio']); ?></p>
                </div>
                <button onclick="cerrarModalEvaluacion()" class="text-white hover:text-blue-200 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Formulario compacto -->
        <form id="formEvaluacion" class="p-6">
            <input type="hidden" name="id_ticket" value="<?php echo htmlspecialchars($id_ticket); ?>">
            <input type="hidden" name="id_ejecutante" value="<?php echo htmlspecialchars($ticket['id_ejecutante']); ?>">
            
            <!-- Info del ticket -->
            <div class="bg-blue-50 p-4 rounded-lg mb-6">
                <div class="flex items-center space-x-3">
                    <div class="bg-blue-100 p-2 rounded-lg">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-blue-800 font-medium">Atendido por: <?php echo htmlspecialchars($ticket['nombre_ejecutante']); ?></p>
                        <p class="text-xs text-blue-600 mt-1"><?php echo htmlspecialchars($ticket['descripcion']); ?></p>
                    </div>
                </div>
            </div>

            <?php if ($ya_evaluado): ?>
                <div class="bg-yellow-50 p-3 rounded-lg mb-6 flex items-center space-x-2">
                    <svg class="w-4 h-4 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                    <span class="text-yellow-800 text-sm">Este ticket ya fue evaluado</span>
                </div>
            <?php endif; ?>

            <!-- Grid de 2 columnas para las calificaciones -->
            <div class="grid grid-cols-2 gap-4 mb-6">
                <!-- Calidad de Atención -->
                <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                    <label class="block text-sm font-semibold text-gray-700 mb-3">Atención</label>
                    <div class="flex space-x-1 justify-center mb-2" id="atencion-stars">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <button type="button" 
                                    class="star-btn star-atencion transition-transform duration-150"
                                    data-rating="<?php echo $i; ?>"
                                    onclick="calificar('atencion', <?php echo $i; ?>)">
                                <svg class="w-7 h-7" viewBox="0 0 24 24">
                                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" 
                                          fill="currentColor" 
                                          stroke="currentColor" 
                                          stroke-width="1"/>
                                </svg>
                            </button>
                        <?php endfor; ?>
                    </div>
                    <div class="text-center">
                        <span class="text-xs font-medium text-gray-500" id="atencion-text">-</span>
                    </div>
                    <input type="hidden" name="calificacion_atencion" id="calificacion_atencion" value="0">
                </div>

                <!-- Tiempo de Respuesta -->
                <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                    <label class="block text-sm font-semibold text-gray-700 mb-3">Tiempo</label>
                    <div class="flex space-x-1 justify-center mb-2" id="tiempo-stars">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <button type="button" 
                                    class="star-btn star-tiempo transition-transform duration-150"
                                    data-rating="<?php echo $i; ?>"
                                    onclick="calificar('tiempo', <?php echo $i; ?>)">
                                <svg class="w-7 h-7" viewBox="0 0 24 24">
                                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" 
                                          fill="currentColor" 
                                          stroke="currentColor" 
                                          stroke-width="1"/>
                                </svg>
                            </button>
                        <?php endfor; ?>
                    </div>
                    <div class="text-center">
                        <span class="text-xs font-medium text-gray-500" id="tiempo-text">-</span>
                    </div>
                    <input type="hidden" name="calificacion_tiempo" id="calificacion_tiempo" value="0">
                </div>

                <!-- Calidad de Solución -->
                <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                    <label class="block text-sm font-semibold text-gray-700 mb-3">Solución</label>
                    <div class="flex space-x-1 justify-center mb-2" id="solucion-stars">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <button type="button" 
                                    class="star-btn star-solucion transition-transform duration-150"
                                    data-rating="<?php echo $i; ?>"
                                    onclick="calificar('solucion', <?php echo $i; ?>)">
                                <svg class="w-7 h-7" viewBox="0 0 24 24">
                                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" 
                                          fill="currentColor" 
                                          stroke="currentColor" 
                                          stroke-width="1"/>
                                </svg>
                            </button>
                        <?php endfor; ?>
                    </div>
                    <div class="text-center">
                        <span class="text-xs font-medium text-gray-500" id="solucion-text">-</span>
                    </div>
                    <input type="hidden" name="calificacion_solucion" id="calificacion_solucion" value="0">
                </div>

                <!-- Comunicación -->
                <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                    <label class="block text-sm font-semibold text-gray-700 mb-3">Comunicación</label>
                    <div class="flex space-x-1 justify-center mb-2" id="comunicacion-stars">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <button type="button" 
                                    class="star-btn star-comunicacion transition-transform duration-150"
                                    data-rating="<?php echo $i; ?>"
                                    onclick="calificar('comunicacion', <?php echo $i; ?>)">
                                <svg class="w-7 h-7" viewBox="0 0 24 24">
                                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" 
                                          fill="currentColor" 
                                          stroke="currentColor" 
                                          stroke-width="1"/>
                                </svg>
                            </button>
                        <?php endfor; ?>
                    </div>
                    <div class="text-center">
                        <span class="text-xs font-medium text-gray-500" id="comunicacion-text">-</span>
                    </div>
                    <input type="hidden" name="calificacion_comunicacion" id="calificacion_comunicacion" value="0">
                </div>
            </div>

            <!-- Comentario que ocupa 2 columnas -->
            <div class="mb-6">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Comentario (Opcional)</label>
                <textarea name="comentario" rows="3"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors duration-200 resize-none"
                    placeholder="Comparte tu experiencia..."></textarea>
            </div>

            <!-- Botones -->
            <div class="flex space-x-3">
                <button type="button" onclick="cerrarModalEvaluacion()" 
                        class="flex-1 px-4 py-3 border border-gray-300 text-gray-700 rounded-lg font-medium hover:bg-gray-50 transition-colors duration-200">
                    Cancelar
                </button>
                <button type="button"
                        class="flex-1 px-4 py-3 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                        id="btnEnviarEvaluacion" disabled>
                    <?php echo $ya_evaluado ? 'Actualizar' : 'Enviar Evaluación'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.star-btn {
    color: #d1d5db; /* gray-300 */
    transition: all 0.2s ease;
}

.star-btn:hover {
    color: #fbbf24; /* yellow-400 */
    transform: scale(1.1);
}

.star-btn.active {
    color: #f59e0b; /* yellow-500 */
}
</style>

<script>

</script>