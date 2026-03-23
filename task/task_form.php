<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// Al inicio del archivo, después de incluir la conexión
include 'includes/conexionbd.php';

// Obtener ID del usuario de la sesión
$user_id = $_SESSION['user_id'];

// Obtener información del usuario y su área usando PDO
$stmt_usuario = $pdo->prepare("SELECT u.*, a.nombre as area_nombre, a.id as area_id 
                                 FROM usuarios u 
                                 LEFT JOIN areas a ON u.Id_departamento = a.id 
                                 WHERE u.Id_Usuario = ?");
$stmt_usuario->execute([$user_id]);
$usuario = $stmt_usuario->fetch(PDO::FETCH_ASSOC);
$area_usuario = $usuario['area_id'];
$area_nombre = $usuario['area_nombre'];

// Obtener lista de áreas para el selector
$stmt_areas = $pdo->query("SELECT id, nombre FROM areas ORDER BY nombre");
$areas = $stmt_areas->fetchAll(PDO::FETCH_ASSOC);

// Obtener responsables de supervisión (filtrados por área del usuario)
$stmt_resp_sup = $pdo->prepare("SELECT Id_Usuario, nombre, apellido FROM usuarios WHERE Id_departamento = ? ORDER BY nombre");
$stmt_resp_sup->execute([$area_usuario]);
$responsables_sup = $stmt_resp_sup->fetchAll(PDO::FETCH_ASSOC);

// Obtener responsables de ejecución (filtrados por área del usuario)
$stmt_resp_ejec = $pdo->prepare("SELECT Id_Usuario, nombre, apellido FROM usuarios WHERE Id_departamento = ? ORDER BY nombre");
$stmt_resp_ejec->execute([$area_usuario]);
$responsables_ejec = $stmt_resp_ejec->fetchAll(PDO::FETCH_ASSOC);

// Obtener categorías de servicio
$stmt_categorias = $pdo->query("SELECT id, nombre_cat FROM categoria_servicio_ticket ORDER BY nombre_cat");
$categorias = $stmt_categorias->fetchAll(PDO::FETCH_ASSOC);

// Obtener estatus
$estatus = [
    'Pendiente' => 'Pendiente',
    'En Proceso' => 'En Proceso',
    'completada' => 'Completada',
    'cancelada' => 'Cancelada',
];

$stmt_actividades = $pdo->prepare("
    SELECT DISTINCT ti.actividad, a.nombre AS area
    FROM tareas_instancias ti
    LEFT JOIN areas a ON ti.area = a.nombre
    LEFT JOIN subareas s ON ti.area = s.nombre
    WHERE a.id = :area_id OR s.id = :area_id OR ti.area IS NULL
    ORDER BY ti.actividad
");
$stmt_actividades->execute(['area_id' => $area_usuario]);
$actividades = $stmt_actividades->fetchAll(PDO::FETCH_ASSOC);

//var_dump($actividades);

// Obtener opciones para Dónde (filtradas por área)
$stmt_donde = $pdo->prepare("SELECT id, nombre FROM donde_ticket WHERE id_area = ? ORDER BY nombre");
$stmt_donde->execute([$area_usuario]);
$donde_opciones = $stmt_donde->fetchAll(PDO::FETCH_ASSOC);

$stmt_detalle_donde = $pdo->prepare("SELECT id, nombre FROM detalle_donde_ticket WHERE id_area = ? ORDER BY nombre");
$stmt_detalle_donde->execute([$area_usuario]);
$detalle_donde_opciones = $stmt_detalle_donde->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Tabs con diseño uniforme -->
<div class="flex border-b border-gray-200 mb-8">
    <button id="btnExtraordinaria" type="button"
        class="tab-btn flex-1 py-3 px-4 text-center font-medium text-blue-600 border-b-2 border-blue-600 transition-all duration-200">
        <i class="fas fa-star mr-2"></i>Tarea Extraordinaria
    </button>
    <button id="btnCiclica" type="button"
        class="tab-btn flex-1 py-3 px-4 text-center font-medium text-gray-600 hover:text-blue-600 transition-all duration-200">
        <i class="fas fa-sync-alt mr-2"></i>Tarea Cíclica
    </button>
</div>

<!-- Formulario Tarea Extraordinaria -->
<form id="formExtraordinaria" method="POST" action="task/procesar_tarea_extraordinaria.php" class="space-y-6">
    <!-- Fila 1: Tipo de Trabajo y Área -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">TIPO DE TRABAJO <span class="text-red-500">*</span></label>
            <select name="tipo_trabajo" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                <option value="">Selecciona tipo de trabajo</option>
                <option value="interno">Interno</option>
                <option value="colaboracion">Colaboración</option>
                <option value="externo">Externo</option>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">ÁREA <span class="text-red-500">*</span></label>
            <select id="area" name="area" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                <option value="">Selecciona un área</option>
                <?php foreach ($areas as $area): ?>
                    <option value="<?= $area['id'] ?>" <?= ($area['id'] == $area_usuario) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($area['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Fila 2: Rubro y Prioridad -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">RUBRO <span class="text-red-500">*</span></label>
            <select name="rubro" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                <option value="">Selecciona rubro</option>
                <option value="trabajo_diario">Trabajo Diario</option>
                <option value="minuta">Minuta</option>
                <option value="planeacion">Planeación</option>
                <option value="incidente">Incidente</option>
                <option value="proyecto">Proyecto</option>
                <option value="requerimiento">Requerimiento</option>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">PRIORIDAD <span class="text-red-500">*</span></label>
            <select name="prioridad" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                <option value="">Selecciona prioridad</option>
                <option value="baja">Baja</option>
                <option value="media">Media</option>
                <option value="alta">Alta</option>
                <option value="urgente">Urgente</option>
                <option value="critica">Crítica</option>
            </select>
        </div>
    </div>

    <!-- Fila 3: Dónde y Detalle Dónde -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">DÓNDE <span class="text-red-500">*</span></label>
            <select id="donde" name="donde" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                <option value="">Selecciona una ubicación</option>
                <?php foreach ($donde_opciones as $donde): ?>
                    <option value="<?= $donde['id'] ?>"><?= htmlspecialchars($donde['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">DETALLE DÓNDE</label>
            <select id="detalle_donde" name="detalle_donde" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="">Selecciona un detalle</option>
                <?php foreach ($detalle_donde_opciones as $detalle_donde): ?>
                    <option value="<?= $detalle_donde['id'] ?>"><?= htmlspecialchars($detalle_donde['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Fila 4: Responsables -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">RESPONSABLE DE SUPERVISIÓN <span class="text-red-500">*</span></label>
            <select name="responsable_supervision" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                <option value="">Selecciona responsable</option>
                <?php foreach ($responsables_sup as $resp): ?>
                    <option value="<?= $resp['Id_Usuario'] ?>" <?= ($resp['Id_Usuario'] == $user_id) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($resp['nombre'] . ' ' . $resp['apellido']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">RESPONSABLE DE EJECUCIÓN <span class="text-red-500">*</span></label>
            <select name="responsable_ejecucion" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                <option value="">Selecciona responsable</option>
                <?php foreach ($responsables_ejec as $resp): ?>
                    <option value="<?= $resp['Id_Usuario'] ?>">
                        <?= htmlspecialchars($resp['nombre'] . ' ' . $resp['apellido']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Fila 5: Categoría y Subcategoría -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">CATEGORÍA DE SERVICIOS <span class="text-red-500">*</span></label>
            <select id="categoria_servicio_extra" name="categoria_servicio_extra" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                <option value="">Selecciona categoría</option>
                <?php foreach ($categorias as $cat): ?>
                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nombre_cat']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">SUBCATEGORÍA</label>
            <select id="subcategoriaExtraordinaria" name="subcategoriaExtraordinaria" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="">Primero selecciona categoría</option>
            </select>
        </div>
    </div>

    <!-- Fila 6: Actividades existentes (opcional) -->
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">ACTIVIDADES EXISTENTES (Opcional)</label>
        <select id="actividad_existente" name="actividad_existente" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            <option value="">Selecciona una actividad existente para cargar datos</option>
            <?php foreach ($actividades as $act): ?>
                <option value="<?= htmlspecialchars($act['actividad']) ?>">
                    <?= htmlspecialchars($act['actividad']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Descripción -->
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">DESCRIPCIÓN <span class="text-red-500">*</span></label>
        <textarea name="descripcion" id="descripcion" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" rows="4" placeholder="Describe la tarea extraordinaria en detalle..." required></textarea>
    </div>

    <!-- Estatus -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">ESTATUS <span class="text-red-500">*</span></label>
            <select name="estatus" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                <option value="">Selecciona estatus</option>
                <?php foreach ($estatus as $key => $value): ?>
                    <option value="<?= $key ?>" <?= ($key == 'pendiente') ? 'selected' : '' ?>>
                        <?= $value ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Botones -->
    <div class="flex justify-end pt-6 border-t border-gray-200">
    <div class="flex space-x-3">
        <button type="button" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium py-2.5 px-5 rounded-lg transition-all duration-200" onclick="resetearFormularioExtraordinaria()">
            <i class="fas fa-redo mr-2"></i>Limpiar
        </button>
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 px-5 rounded-lg transition-all duration-200 flex items-center justify-center">
            <i class="fas fa-save mr-2"></i>Guardar Tarea Extraordinaria
        </button>
    </div>
</div>
</form>

<!-- Formulario Tarea Cíclica (oculto inicialmente) -->
<form id="formCiclica" method="POST" action="procesar_tarea_ciclica.php" class="space-y-6 hidden">
    <!-- Primera fila -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">PUESTO <span class="text-red-500">*</span></label>
            <select name="puesto" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                <option value="">Selecciona un puesto</option>
                <option value="supervisor">Supervisor</option>
                <option value="operario">Operario</option>
                <option value="tecnico">Técnico</option>
                <option value="administrativo">Administrativo</option>
                <option value="gerente">Gerente</option>
                <option value="coordinador">Coordinador</option>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Actividad del Puesto <span class="text-red-500">*</span></label>
            <input type="text" name="actividad_puesto" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                placeholder="Ej: Revisión de equipos, Limpieza de área, etc." required>
        </div>
    </div>

    <!-- Segunda fila -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Categoría de Servicio <span class="text-red-500">*</span></label>
            <select id="categoriaServicio" name="categoria_servicio" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                <option value="">Selecciona una categoría</option>
                <?php foreach ($categorias as $cat): ?>
                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nombre_cat']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Subcategoría <span class="text-red-500">*</span></label>
            <select id="subcategoriaServicio" name="subcategoria_servicio" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" disabled required>
                <option value="">Primero selecciona una categoría</option>
            </select>
        </div>
    </div>

    <!-- Tercera fila -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Área <span class="text-red-500">*</span></label>
            <select name="area" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                <option value="">Selecciona un área</option>
                <?php foreach ($areas as $area): ?>
                    <option value="<?= $area['id'] ?>" <?= ($area['id'] == $area_usuario) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($area['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Hora <span class="text-red-500">*</span></label>
            <input type="time" name="hora" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" value="08:00" required>
        </div>
    </div>

    <!-- Cuarta fila - Fechas -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Fecha de inicio <span class="text-red-500">*</span></label>
            <input type="date" name="fecha_inicio" id="fechaInicio" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Fecha de fin (opcional)</label>
            <input type="date" name="fecha_fin" id="fechaFin" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            <p class="text-xs text-gray-500 mt-1">Dejar vacío para tarea continua</p>
        </div>
    </div>

    <!-- Frecuencia -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Frecuencia <span class="text-red-500">*</span></label>
            <select id="frecuencia" name="frecuencia" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                <option value="">Selecciona frecuencia</option>
                <option value="diario">Diario</option>
                <option value="semanal">Semanal</option>
                <option value="quincenal">Quincenal</option>
                <option value="mensual">Mensual</option>
                <option value="bimestral">Bimestral</option>
                <option value="trimestral">Trimestral</option>
                <option value="semestral">Semestral</option>
                <option value="anual">Anual</option>
                <option value="esporadico">Esporádico</option>
            </select>
        </div>
    </div>

    <!-- Opciones de frecuencia dinámicas -->
    <div id="opcionesFrecuencia" class="space-y-4"></div>

    <!-- Descripción -->
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Descripción <span class="text-red-500">*</span></label>
        <textarea name="descripcion" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" rows="4" placeholder="Describe la tarea cíclica..." required></textarea>
    </div>

    <!-- Botones -->
    <div class="flex justify-end pt-6 border-t border-gray-200">
    <div class="flex space-x-3">
        <button type="button" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium py-2.5 px-5 rounded-lg transition-all duration-200" onclick="resetearFormularioCiclica()">
            <i class="fas fa-redo mr-2"></i>Limpiar
        </button>
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 px-5 rounded-lg transition-all duration-200 flex items-center justify-center">
            <i class="fas fa-sync-alt mr-2"></i>Crear Tarea Cíclica
        </button>
    </div>
</div>
</form>

<!-- Incluye Font Awesome y jQuery (para AJAX) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
    // Elementos DOM
    const elementos = {
        btnExtraordinaria: document.getElementById('btnExtraordinaria'),
        btnCiclica: document.getElementById('btnCiclica'),
        formExtraordinaria: document.getElementById('formExtraordinaria'),
        formCiclica: document.getElementById('formCiclica'),
        area: document.getElementById('area'),
        donde: document.getElementById('donde'),
        detalleDonde: document.getElementById('detalle_donde'),
        categoriaServicio: document.getElementById('categoria_servicio_extra'),
        subcategoria: document.getElementById('subcategoria'),
        categoriaServicioCiclica: document.getElementById('categoriaServicio'),
        subcategoriaServicio: document.getElementById('subcategoriaServicio'),
        frecuencia: document.getElementById('frecuencia'),
        opcionesFrecuencia: document.getElementById('opcionesFrecuencia'),
        fechaInicio: document.getElementById('fechaInicio'),
        fechaFin: document.getElementById('fechaFin'),
        actividadExistente: document.getElementById('actividad_existente'),
        descripcion: document.getElementById('descripcion')
    };

    let estado = {
        formularioActual: 'extraordinaria'
    };

    // Inicialización
    document.addEventListener('DOMContentLoaded', function() {
        // Establecer fecha mínima para fecha de inicio (hoy)
        const hoy = new Date().toISOString().split('T')[0];
        if (elementos.fechaInicio) {
            elementos.fechaInicio.min = hoy;
            elementos.fechaInicio.value = hoy;
        }

        // Event listeners para fecha fin
        if (elementos.fechaInicio) {
            elementos.fechaInicio.addEventListener('change', function() {
                if (elementos.fechaFin) {
                    elementos.fechaFin.min = this.value;
                    if (elementos.fechaFin.value && elementos.fechaFin.value < this.value) {
                        elementos.fechaFin.value = this.value;
                    }
                }
            });
        }

        // Event listeners para tabs
        if (elementos.btnExtraordinaria) {
            elementos.btnExtraordinaria.addEventListener('click', function() {
                cambiarTab('extraordinaria');
            });
        }

        if (elementos.btnCiclica) {
            elementos.btnCiclica.addEventListener('click', function() {
                cambiarTab('ciclica');
            });
        }

        if (elementos.categoriaServicio) {
            elementos.categoriaServicio.addEventListener('change', cargarSubcategorias);
        }

        if (elementos.categoriaServicioCiclica) {
            elementos.categoriaServicioCiclica.addEventListener('change', cargarSubcategoriasCiclica);
        }

        if (elementos.frecuencia) {
            elementos.frecuencia.addEventListener('change', manejarCambioFrecuencia);
        }

        if (elementos.actividadExistente) {
            elementos.actividadExistente.addEventListener('change', cargarActividadExistente);
        }

        // Submit formularios
        if (elementos.formExtraordinaria) {
            elementos.formExtraordinaria.addEventListener('submit', manejarSubmitExtraordinaria);
        }

        if (elementos.formCiclica) {
            elementos.formCiclica.addEventListener('submit', manejarSubmitCiclica);
        }
    });

    function cambiarTab(tab) {
        estado.formularioActual = tab;

        // Actualizar visibilidad de formularios
        elementos.formExtraordinaria.classList.toggle('hidden', tab !== 'extraordinaria');
        elementos.formCiclica.classList.toggle('hidden', tab !== 'ciclica');

        // Actualizar estilos de tabs
        const tabs = [elementos.btnExtraordinaria, elementos.btnCiclica];
        tabs.forEach(btn => {
            if (btn) {
                btn.classList.remove('text-blue-600', 'border-b-2', 'border-blue-600');
                btn.classList.add('text-gray-600');
            }
        });

        if (tab === 'extraordinaria' && elementos.btnExtraordinaria) {
            elementos.btnExtraordinaria.classList.remove('text-gray-600');
            elementos.btnExtraordinaria.classList.add('text-blue-600', 'border-b-2', 'border-blue-600');
        } else if (tab === 'ciclica' && elementos.btnCiclica) {
            elementos.btnCiclica.classList.remove('text-gray-600');
            elementos.btnCiclica.classList.add('text-blue-600', 'border-b-2', 'border-blue-600');
        }
    }


    // Función para cargar subcategorías (Extraordinaria)
    function cargarSubcategorias() {

        const categoriaId = elementos.categoriaServicio.value; // Extraordinaria
        const selectSub = document.getElementById('subcategoriaExtraordinaria');


        // Vaciar select y mostrar mensaje de carga
        selectSub.innerHTML = '<option value="">Cargando...</option>';
        selectSub.disabled = true;

        if (!categoriaId) {
            selectSub.innerHTML = '<option value="">Primero selecciona categoría</option>';
            return;
        }


        $.ajax({
            url: 'task/get_subcategorias.php',
            type: 'POST',
            data: {
                categoria_id: categoriaId
            },
            dataType: 'json',
            success: function(response) {
                console.log("Respuesta AJAX recibida:", response);

                if (!Array.isArray(response) || response.length === 0) {
                    selectSub.innerHTML = '<option value="">No hay subcategorías</option>';
                    selectSub.disabled = false;
                    return;
                }

                selectSub.innerHTML = '<option value="">Selecciona subcategoría</option>';
                response.forEach(function(item, index) {
                    const option = document.createElement('option');
                    option.value = item.id;
                    option.textContent = item.nombre_sucat;
                    selectSub.appendChild(option);
                });

                selectSub.disabled = false;
            },
            error: function(xhr, status, error) {
                selectSub.innerHTML = '<option value="">Error al cargar subcategorías</option>';
                selectSub.disabled = false;
            }
        });
    }

    // Función para cargar subcategorías (Cíclica)
    function cargarSubcategoriasCiclica() {
        const categoriaId = elementos.categoriaServicioCiclica.value;
        const selectSub = elementos.subcategoriaServicio;

        selectSub.innerHTML = '<option value="">Cargando...</option>';
        selectSub.disabled = true;

        if (!categoriaId) {
            selectSub.innerHTML = '<option value="">Primero selecciona categoría</option>';
            selectSub.disabled = true;
            return;
        }

        $.ajax({
            url: 'task/get_subcategorias.php',
            type: 'POST',
            data: {
                categoria_id: categoriaId
            },
            dataType: 'json',
            success: function(response) {
                selectSub.innerHTML = '<option value="">Selecciona subcategoría</option>';
                response.forEach(function(item) {
                    const option = document.createElement('option');
                    option.value = item.id;
                    option.textContent = item.nombre_sucat;
                    selectSub.appendChild(option);
                });
                selectSub.disabled = false;
            },
            error: function() {
                selectSub.innerHTML = '<option value="">Error al cargar datos</option>';
                selectSub.disabled = false;
            }
        });
    }

    // Función para cargar actividad existente
    function cargarActividadExistente() {
        const actividad = elementos.actividadExistente.options[elementos.actividadExistente.selectedIndex];
        if (actividad && actividad.value) {
            if (elementos.descripcion) {
                elementos.descripcion.value = actividad.value;
            }
        }
    }

    // Funciones de frecuencia (mantener las mismas del código anterior)
    function manejarCambioFrecuencia() {
        const frecuencia = elementos.frecuencia.value;
        elementos.opcionesFrecuencia.innerHTML = '';

        if (!frecuencia || frecuencia === 'esporadico') {
            elementos.opcionesFrecuencia.classList.add('hidden');
            return;
        }

        elementos.opcionesFrecuencia.classList.remove('hidden');

        function crearElemento(tag, clases = '', contenido = '') {
            const elemento = document.createElement(tag);
            if (clases) elemento.className = clases;
            if (contenido) elemento.innerHTML = contenido;
            return elemento;
        }

        const configFrecuencia = {
            meses: [{
                    value: 'enero',
                    label: 'Enero'
                },
                {
                    value: 'febrero',
                    label: 'Febrero'
                },
                {
                    value: 'marzo',
                    label: 'Marzo'
                },
                {
                    value: 'abril',
                    label: 'Abril'
                },
                {
                    value: 'mayo',
                    label: 'Mayo'
                },
                {
                    value: 'junio',
                    label: 'Junio'
                },
                {
                    value: 'julio',
                    label: 'Julio'
                },
                {
                    value: 'agosto',
                    label: 'Agosto'
                },
                {
                    value: 'septiembre',
                    label: 'Septiembre'
                },
                {
                    value: 'octubre',
                    label: 'Octubre'
                },
                {
                    value: 'noviembre',
                    label: 'Noviembre'
                },
                {
                    value: 'diciembre',
                    label: 'Diciembre'
                }
            ],
            diasSemana: [{
                    value: 'lunes',
                    label: 'Lunes'
                },
                {
                    value: 'martes',
                    label: 'Martes'
                },
                {
                    value: 'miercoles',
                    label: 'Miércoles'
                },
                {
                    value: 'jueves',
                    label: 'Jueves'
                },
                {
                    value: 'viernes',
                    label: 'Viernes'
                },
                {
                    value: 'sabado',
                    label: 'Sábado'
                },
                {
                    value: 'domingo',
                    label: 'Domingo'
                }
            ],
            ordinales: [{
                    value: '1er',
                    label: 'Primer'
                },
                {
                    value: '2do',
                    label: 'Segundo'
                },
                {
                    value: '3er',
                    label: 'Tercer'
                },
                {
                    value: '4to',
                    label: 'Cuarto'
                },
                {
                    value: '5to',
                    label: 'Quinto'
                }
            ]
        };

        switch (frecuencia) {
            case 'diario':
                const contenedorDiario = crearElemento('div', 'grid grid-cols-1 md:grid-cols-2 gap-4');

                const divCuando = crearElemento('div');
                divCuando.appendChild(crearElemento('label', 'block text-sm font-medium text-gray-700 mb-2', 'Cuándo <span class="text-red-500">*</span>'));

                const selectCuando = crearElemento('select', 'w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent');
                selectCuando.name = 'cuando_diario';
                selectCuando.innerHTML = `
                <option value="">Selecciona rango</option>
                <option value="lunes_viernes">Lunes a Viernes</option>
                <option value="lunes_sabado">Lunes a Sábado</option>
                <option value="todos_dias">Lunes a Domingo</option>
            `;
                divCuando.appendChild(selectCuando);

                const divCada = crearElemento('div');
                divCada.appendChild(crearElemento('label', 'block text-sm font-medium text-gray-700 mb-2', 'Cada cuánto días <span class="text-red-500">*</span>'));

                const inputCada = crearElemento('input', 'w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent');
                inputCada.type = 'number';
                inputCada.name = 'cada_dia';
                inputCada.min = '1';
                inputCada.value = '1';
                divCada.appendChild(inputCada);

                contenedorDiario.appendChild(divCuando);
                contenedorDiario.appendChild(divCada);
                elementos.opcionesFrecuencia.appendChild(contenedorDiario);
                break;

            case 'semanal':
                const contenedorSemanal = crearElemento('div', 'space-y-4');

                const divDias = crearElemento('div');
                divDias.appendChild(crearElemento('label', 'block text-sm font-medium text-gray-700 mb-2', 'Días de la semana <span class="text-red-500">*</span>'));

                const divCheckboxes = crearElemento('div', 'grid grid-cols-2 md:grid-cols-4 gap-3');
                configFrecuencia.diasSemana.forEach(dia => {
                    const label = crearElemento('label', 'flex items-center space-x-2');
                    const checkbox = crearElemento('input');
                    checkbox.type = 'checkbox';
                    checkbox.name = 'dias_semana[]';
                    checkbox.value = dia.value;
                    checkbox.className = 'h-4 w-4 text-blue-600 rounded';

                    label.appendChild(checkbox);
                    label.appendChild(document.createTextNode(dia.label));
                    divCheckboxes.appendChild(label);
                });
                divDias.appendChild(divCheckboxes);

                const divCadaSemana = crearElemento('div');
                divCadaSemana.appendChild(crearElemento('label', 'block text-sm font-medium text-gray-700 mb-2', 'Cada cuántas semanas <span class="text-red-500">*</span>'));

                const inputCadaSemana = crearElemento('input', 'w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent');
                inputCadaSemana.type = 'number';
                inputCadaSemana.name = 'cada_semana';
                inputCadaSemana.min = '1';
                inputCadaSemana.value = '1';
                divCadaSemana.appendChild(inputCadaSemana);

                contenedorSemanal.appendChild(divDias);
                contenedorSemanal.appendChild(divCadaSemana);
                elementos.opcionesFrecuencia.appendChild(contenedorSemanal);
                break;

            case 'quincenal':
                const contenedorQuincenal = crearElemento('div', 'grid grid-cols-1 md:grid-cols-2 gap-4');

                const divDiaQuincenal = crearElemento('div');
                divDiaQuincenal.appendChild(crearElemento('label', 'block text-sm font-medium text-gray-700 mb-2', 'Día de la semana <span class="text-red-500">*</span>'));

                const selectDiaQuincenal = crearElemento('select', 'w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent');
                selectDiaQuincenal.name = 'cuando_quincenal';
                selectDiaQuincenal.innerHTML = '<option value="">Selecciona día</option>';
                configFrecuencia.diasSemana.forEach(dia => {
                    const option = crearElemento('option');
                    option.value = dia.value;
                    option.textContent = dia.label;
                    selectDiaQuincenal.appendChild(option);
                });
                divDiaQuincenal.appendChild(selectDiaQuincenal);

                const divCadaQuincena = crearElemento('div');
                divCadaQuincena.appendChild(crearElemento('label', 'block text-sm font-medium text-gray-700 mb-2', 'Cada cuántas quincenas'));

                const inputCadaQuincena = crearElemento('input', 'w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent');
                inputCadaQuincena.type = 'number';
                inputCadaQuincena.name = 'cada_quincena';
                inputCadaQuincena.min = '1';
                inputCadaQuincena.value = '1';
                divCadaQuincena.appendChild(inputCadaQuincena);

                contenedorQuincenal.appendChild(divDiaQuincenal);
                contenedorQuincenal.appendChild(divCadaQuincena);
                elementos.opcionesFrecuencia.appendChild(contenedorQuincenal);
                break;

            case 'mensual':
                const contenedorMensual = crearElemento('div', 'space-y-4');

                const divOpcion1 = crearElemento('div', 'space-y-4');
                divOpcion1.appendChild(crearElemento('h4', 'font-medium text-gray-700', 'Opción 1: Día específico del mes'));

                const divDiaMes = crearElemento('div', 'grid grid-cols-2 gap-4');

                const divDia = crearElemento('div');
                divDia.appendChild(crearElemento('label', 'block text-sm font-medium text-gray-700 mb-2', 'Día del mes (1-31)'));
                const inputDia = crearElemento('input', 'w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent');
                inputDia.type = 'number';
                inputDia.name = 'dia_mes';
                inputDia.min = '1';
                inputDia.max = '31';
                inputDia.placeholder = 'Ej: 15';
                divDia.appendChild(inputDia);

                const divCadaMes = crearElemento('div');
                divCadaMes.appendChild(crearElemento('label', 'block text-sm font-medium text-gray-700 mb-2', 'Cada cuántos meses'));
                const inputCadaMes = crearElemento('input', 'w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent');
                inputCadaMes.type = 'number';
                inputCadaMes.name = 'cada_mes';
                inputCadaMes.min = '1';
                inputCadaMes.value = '1';
                divCadaMes.appendChild(inputCadaMes);

                divDiaMes.appendChild(divDia);
                divDiaMes.appendChild(divCadaMes);
                divOpcion1.appendChild(divDiaMes);

                const separator = crearElemento('div', 'flex items-center my-4');
                separator.innerHTML = `
                <div class="flex-1 border-t border-gray-300"></div>
                <span class="px-3 text-sm text-gray-500">O</span>
                <div class="flex-1 border-t border-gray-300"></div>
            `;

                const divOpcion2 = crearElemento('div', 'space-y-4');
                divOpcion2.appendChild(crearElemento('h4', 'font-medium text-gray-700', 'Opción 2: Día ordinal de la semana'));

                const divOrdinal = crearElemento('div', 'grid grid-cols-1 md:grid-cols-3 gap-4');

                const divOrdinalSelect = crearElemento('div');
                divOrdinalSelect.appendChild(crearElemento('label', 'block text-sm font-medium text-gray-700 mb-2', 'Ordinal'));
                const selectOrdinal = crearElemento('select', 'w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent');
                selectOrdinal.name = 'ordinal_mensual';
                selectOrdinal.innerHTML = '<option value="">Selecciona</option>';
                configFrecuencia.ordinales.forEach(ord => {
                    const option = crearElemento('option');
                    option.value = ord.value;
                    option.textContent = ord.label;
                    selectOrdinal.appendChild(option);
                });
                divOrdinalSelect.appendChild(selectOrdinal);

                const divDiaSemana = crearElemento('div');
                divDiaSemana.appendChild(crearElemento('label', 'block text-sm font-medium text-gray-700 mb-2', 'Día de la semana'));
                const selectDiaSemana = crearElemento('select', 'w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent');
                selectDiaSemana.name = 'dia_semana_mensual';
                selectDiaSemana.innerHTML = '<option value="">Selecciona día</option>';
                configFrecuencia.diasSemana.forEach(dia => {
                    const option = crearElemento('option');
                    option.value = dia.value;
                    option.textContent = dia.label;
                    selectDiaSemana.appendChild(option);
                });
                divDiaSemana.appendChild(selectDiaSemana);

                const divCadaMensual = crearElemento('div');
                divCadaMensual.appendChild(crearElemento('label', 'block text-sm font-medium text-gray-700 mb-2', 'Cada cuánto'));
                const inputCadaMensual = crearElemento('input', 'w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent');
                inputCadaMensual.type = 'number';
                inputCadaMensual.name = 'cada_mensual';
                inputCadaMensual.min = '1';
                inputCadaMensual.value = '1';
                inputCadaMensual.placeholder = 'Meses';
                divCadaMensual.appendChild(inputCadaMensual);

                divOrdinal.appendChild(divOrdinalSelect);
                divOrdinal.appendChild(divDiaSemana);
                divOrdinal.appendChild(divCadaMensual);
                divOpcion2.appendChild(divOrdinal);

                contenedorMensual.appendChild(divOpcion1);
                contenedorMensual.appendChild(separator);
                contenedorMensual.appendChild(divOpcion2);
                elementos.opcionesFrecuencia.appendChild(contenedorMensual);
                break;

            case 'bimestral':
            case 'trimestral':
            case 'semestral':
            case 'anual':
                const nombresFrecuencia = {
                    bimestral: 'Bimestral',
                    trimestral: 'Trimestral',
                    semestral: 'Semestral',
                    anual: 'Anual'
                };

                const contenedorPeriodico = crearElemento('div', 'space-y-4');
                contenedorPeriodico.appendChild(crearElemento('h4', 'font-medium text-gray-700',
                    `Configuración ${nombresFrecuencia[frecuencia]}`));

                const divFila1 = crearElemento('div', 'grid grid-cols-1 md:grid-cols-3 gap-4');

                const divOrdinalPeriodico = crearElemento('div');
                divOrdinalPeriodico.appendChild(crearElemento('label', 'block text-sm font-medium text-gray-700 mb-2', 'Ordinal'));
                const selectOrdinalPeriodico = crearElemento('select', 'w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent');
                selectOrdinalPeriodico.name = `ordinal_${frecuencia}`;
                selectOrdinalPeriodico.innerHTML = '<option value="">Selecciona</option>';
                configFrecuencia.ordinales.forEach(ord => {
                    const option = crearElemento('option');
                    option.value = ord.value;
                    option.textContent = ord.label;
                    selectOrdinalPeriodico.appendChild(option);
                });
                divOrdinalPeriodico.appendChild(selectOrdinalPeriodico);

                const divDiaSemanaPeriodico = crearElemento('div');
                divDiaSemanaPeriodico.appendChild(crearElemento('label', 'block text-sm font-medium text-gray-700 mb-2', 'Día de la semana'));
                const selectDiaSemanaPeriodico = crearElemento('select', 'w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent');
                selectDiaSemanaPeriodico.name = `dia_semana_${frecuencia}`;
                selectDiaSemanaPeriodico.innerHTML = '<option value="">Selecciona día</option>';
                configFrecuencia.diasSemana.forEach(dia => {
                    const option = crearElemento('option');
                    option.value = dia.value;
                    option.textContent = dia.label;
                    selectDiaSemanaPeriodico.appendChild(option);
                });
                divDiaSemanaPeriodico.appendChild(selectDiaSemanaPeriodico);

                const divMes = crearElemento('div');
                divMes.appendChild(crearElemento('label', 'block text-sm font-medium text-gray-700 mb-2', 'Mes'));
                const selectMes = crearElemento('select', 'w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent');
                selectMes.name = `mes_${frecuencia}`;
                selectMes.innerHTML = '<option value="">Selecciona mes</option>';
                configFrecuencia.meses.forEach(mes => {
                    const option = crearElemento('option');
                    option.value = mes.value;
                    option.textContent = mes.label;
                    selectMes.appendChild(option);
                });
                divMes.appendChild(selectMes);

                divFila1.appendChild(divOrdinalPeriodico);
                divFila1.appendChild(divDiaSemanaPeriodico);
                divFila1.appendChild(divMes);

                const divFila2 = crearElemento('div', 'grid grid-cols-1 md:grid-cols-2 gap-4');

                const divCadaPeriodico = crearElemento('div');
                divCadaPeriodico.appendChild(crearElemento('label', 'block text-sm font-medium text-gray-700 mb-2', 'Cada cuánto'));

                const inputGroup = crearElemento('div', 'flex items-center space-x-2');
                const inputCadaPeriodico = crearElemento('input', 'w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent w-24');
                inputCadaPeriodico.type = 'number';
                inputCadaPeriodico.name = `cada_${frecuencia}`;

                const valoresDefault = {
                    bimestral: '2',
                    trimestral: '3',
                    semestral: '6',
                    anual: '1'
                };

                inputCadaPeriodico.min = valoresDefault[frecuencia];
                inputCadaPeriodico.value = valoresDefault[frecuencia];

                const unidad = frecuencia === 'anual' ? 'año(s)' : 'mes(es)';
                inputGroup.appendChild(inputCadaPeriodico);
                inputGroup.appendChild(document.createTextNode(` ${unidad}`));
                divCadaPeriodico.appendChild(inputGroup);

                divFila2.appendChild(divCadaPeriodico);

                contenedorPeriodico.appendChild(divFila1);
                contenedorPeriodico.appendChild(divFila2);
                elementos.opcionesFrecuencia.appendChild(contenedorPeriodico);
                break;
        }
    }

    function manejarSubmitExtraordinaria(e) {
        e.preventDefault();

        const formData = new FormData(e.target);
        let valido = true;

        const camposRequeridos = [
            'tipo_trabajo', 'area', 'rubro', 'prioridad', 'donde',
            'responsable_supervision', 'responsable_ejecucion', 'categoria_servicio_extra', 'descripcion', 'estatus'
        ];

        camposRequeridos.forEach(campo => {
            if (!formData.get(campo)) {
                alert(`Por favor complete el campo: ${campo.replace('_', ' ')}`);
                valido = false;
            }
        });

        if (valido) {
            e.target.submit();
        }
    }

    function manejarSubmitCiclica(e) {
        e.preventDefault();

        const formData = new FormData(e.target);
        let valido = true;

        const camposRequeridos = [
            'puesto', 'actividad_puesto', 'categoria_servicio',
            'subcategoria_servicio', 'area', 'hora', 'fecha_inicio',
            'frecuencia', 'descripcion'
        ];

        camposRequeridos.forEach(campo => {
            if (!formData.get(campo)) {
                alert(`Por favor complete el campo: ${campo.replace('_', ' ')}`);
                valido = false;
            }
        });

        if (formData.get('categoria_servicio') && !formData.get('subcategoria_servicio')) {
            alert('Debe seleccionar una subcategoría');
            valido = false;
        }

        const frecuencia = formData.get('frecuencia');
        if (frecuencia && frecuencia !== 'esporadico') {
            switch (frecuencia) {
                case 'diario':
                    if (!formData.get('cuando_diario')) {
                        valido = false;
                        alert('Por favor complete la configuración de frecuencia diaria');
                    }
                    break;
                case 'semanal':
                    const diasSeleccionados = formData.getAll('dias_semana[]');
                    if (diasSeleccionados.length === 0) {
                        valido = false;
                        alert('Debe seleccionar al menos un día de la semana');
                    }
                    break;
                case 'quincenal':
                    if (!formData.get('cuando_quincenal')) {
                        valido = false;
                        alert('Por favor seleccione un día para la frecuencia quincenal');
                    }
                    break;
                case 'mensual':
                    const diaMes = formData.get('dia_mes');
                    const ordinal = formData.get('ordinal_mensual');
                    const diaSemana = formData.get('dia_semana_mensual');

                    if (!diaMes && (!ordinal || !diaSemana)) {
                        valido = false;
                        alert('Por favor complete la configuración mensual (día del mes o día ordinal)');
                    }
                    break;
                case 'bimestral':
                case 'trimestral':
                case 'semestral':
                case 'anual':
                    if (!formData.get(`ordinal_${frecuencia}`) ||
                        !formData.get(`dia_semana_${frecuencia}`) ||
                        !formData.get(`mes_${frecuencia}`)) {
                        valido = false;
                        alert(`Por favor complete toda la configuración ${frecuencia}`);
                    }
                    break;
            }
        }

        if (valido) {
            e.target.submit();
        }
    }

    function resetearFormularioExtraordinaria() {
        if (elementos.formExtraordinaria) {
            elementos.formExtraordinaria.reset();
        }

        if (elementos.detalleDonde) {
            elementos.detalleDonde.innerHTML = '<option value="">Selecciona un detalle</option>';
        }

        if (elementos.subcategoria) {
            elementos.subcategoria.innerHTML = '<option value="">Primero selecciona categoría</option>';
            elementos.subcategoria.disabled = true;
        }
    }

    function resetearFormularioCiclica() {
        if (elementos.formCiclica) {
            elementos.formCiclica.reset();
        }

        const hoy = new Date().toISOString().split('T')[0];
        if (elementos.fechaInicio) {
            elementos.fechaInicio.value = hoy;
        }
        if (elementos.fechaFin) {
            elementos.fechaFin.value = '';
        }

        if (elementos.opcionesFrecuencia) {
            elementos.opcionesFrecuencia.innerHTML = '';
            elementos.opcionesFrecuencia.classList.add('hidden');
        }

        if (elementos.subcategoriaServicio) {
            elementos.subcategoriaServicio.disabled = true;
            elementos.subcategoriaServicio.innerHTML = '<option value="">Primero selecciona una categoría</option>';
        }
    }
</script>