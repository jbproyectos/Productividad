<div class="bg-white rounded-xl shadow-lg p-6">
    <!-- Encabezado -->
    <div class="mb-8">
        <h2 class="text-2xl font-bold text-gray-800">Nueva Tarea</h2>
        <p class="text-gray-600 mt-1">Complete el formulario para crear una nueva tarea cíclica</p>
    </div>

    <!-- Tabs -->
    <div class="flex border-b border-gray-200 mb-8">
        <button id="btnUnica" type="button" 
            class="tab-btn flex-1 py-3 px-4 text-center font-medium text-gray-600 hover:text-blue-600 transition-all duration-200">
            <i class="far fa-calendar-alt mr-2"></i>Tarea Única
        </button>
        <button id="btnCiclica" type="button" 
            class="tab-btn flex-1 py-3 px-4 text-center font-medium text-blue-600 border-b-2 border-blue-600 transition-all duration-200">
            <i class="fas fa-sync-alt mr-2"></i>Tarea Cíclica
        </button>
    </div>

    <!-- Formulario Tarea Única (oculto inicialmente) -->
    <form id="formUnica" class="space-y-6 hidden">
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-5 mb-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-300">Información General</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Tipo de trabajo <span class="text-red-500">*</span>
                    </label>
                    <select name="tipo_trabajo" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200" required>
                        <option value="">Seleccione tipo</option>
                        <option value="correctivo">Correctivo</option>
                        <option value="preventivo">Preventivo</option>
                        <option value="proyecto">Proyecto</option>
                        <option value="rutinario">Rutinario</option>
                    </select>
                    <div class="text-red-600 text-sm mt-1 hidden" data-for="tipo_trabajo">Este campo es requerido</div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Fecha específica <span class="text-red-500">*</span>
                    </label>
                    <input type="date" name="fecha_unica" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200" required>
                    <div class="text-red-600 text-sm mt-1 hidden" data-for="fecha_unica">Este campo es requerido</div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Área <span class="text-red-500">*</span>
                    </label>
                    <select name="area" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200" required>
                        <option value="">Seleccione área</option>
                        <option value="produccion">Producción</option>
                        <option value="calidad">Calidad</option>
                        <option value="logistica">Logística</option>
                        <option value="administracion">Administración</option>
                    </select>
                    <div class="text-red-600 text-sm mt-1 hidden" data-for="area">Este campo es requerido</div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Prioridad <span class="text-red-500">*</span>
                    </label>
                    <select name="prioridad" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200" required>
                        <option value="baja">Baja</option>
                        <option value="media">Media</option>
                        <option value="alta">Alta</option>
                        <option value="urgente">Urgente</option>
                    </select>
                </div>
            </div>

            <div class="mt-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Descripción <span class="text-red-500">*</span>
                </label>
                <textarea name="descripcion" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200" rows="3" placeholder="Describe la tarea..." required></textarea>
                <div class="text-red-600 text-sm mt-1 hidden" data-for="descripcion">Este campo es requerido</div>
            </div>
        </div>

        <div class="flex justify-end space-x-4">
            <button type="button" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium py-2.5 px-5 rounded-lg transition-all duration-200" onclick="alternarFormulario()">
                <i class="fas fa-sync-alt mr-2"></i>Cambiar a Cíclica
            </button>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 px-5 rounded-lg transition-all duration-200 flex items-center justify-center">
                <i class="fas fa-save mr-2"></i>Guardar Tarea Única
            </button>
        </div>
    </form>

    <!-- Formulario Tarea Cíclica (visible inicialmente) -->
    <form id="formCiclica" class="space-y-6">
        <!-- Sección 1: Información básica -->
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-5 mb-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-300">Información Básica</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        PUESTO <span class="text-red-500">*</span>
                    </label>
                    <select name="puesto" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200" required>
                        <option value="">Seleccione puesto</option>
                        <option value="supervisor">Supervisor</option>
                        <option value="operario">Operario</option>
                        <option value="tecnico">Técnico</option>
                        <option value="administrativo">Administrativo</option>
                        <option value="gerente">Gerente</option>
                    </select>
                    <div class="text-red-600 text-sm mt-1 hidden" data-for="puesto">Este campo es requerido</div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Actividad del Puesto <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="actividad_puesto" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200" 
                           placeholder="Ej: Revisión de equipos, Limpieza de área, etc." required>
                    <div class="text-red-600 text-sm mt-1 hidden" data-for="actividad_puesto">Este campo es requerido</div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Categoría de Servicio <span class="text-red-500">*</span>
                    </label>
                    <select id="categoriaServicio" name="categoria_servicio" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200" required>
                        <option value="">Seleccione categoría</option>
                        <option value="mantenimiento">Mantenimiento</option>
                        <option value="limpieza">Limpieza</option>
                        <option value="seguridad">Seguridad</option>
                        <option value="administrativo">Administrativo</option>
                        <option value="operativo">Operativo</option>
                    </select>
                    <div class="text-red-600 text-sm mt-1 hidden" data-for="categoria_servicio">Este campo es requerido</div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Subcategoría de Servicio <span class="text-red-500">*</span>
                    </label>
                    <select id="subcategoriaServicio" name="subcategoria_servicio" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200" disabled required>
                        <option value="">Primero seleccione una categoría</option>
                    </select>
                    <div class="text-red-600 text-sm mt-1 hidden" data-for="subcategoria_servicio">Este campo es requerido</div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Área <span class="text-red-500">*</span>
                    </label>
                    <select name="area" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200" required>
                        <option value="">Seleccione área</option>
                        <option value="produccion">Producción</option>
                        <option value="calidad">Calidad</option>
                        <option value="logistica">Logística</option>
                        <option value="administracion">Administración</option>
                    </select>
                    <div class="text-red-600 text-sm mt-1 hidden" data-for="area">Este campo es requerido</div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Descripción <span class="text-red-500">*</span>
                    </label>
                    <textarea name="descripcion" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200" rows="2" placeholder="Descripción de la tarea..." required></textarea>
                    <div class="text-red-600 text-sm mt-1 hidden" data-for="descripcion">Este campo es requerido</div>
                </div>
            </div>
        </div>

        <!-- Sección 2: Programación -->
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-5 mb-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-300">Programación</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Frecuencia <span class="text-red-500">*</span>
                    </label>
                    <select id="frecuencia" name="frecuencia" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200" required>
                        <option value="">Seleccione frecuencia</option>
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
                    <div class="text-red-600 text-sm mt-1 hidden" data-for="frecuencia">Este campo es requerido</div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Hora <span class="text-red-500">*</span>
                    </label>
                    <input type="time" name="hora" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200" value="08:00" required>
                    <div class="text-red-600 text-sm mt-1 hidden" data-for="hora">Este campo es requerido</div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Fecha de inicio <span class="text-red-500">*</span>
                    </label>
                    <input type="date" name="fecha_inicio" id="fechaInicio" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200" required>
                    <div class="text-red-600 text-sm mt-1 hidden" data-for="fecha_inicio">Este campo es requerido</div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Fecha de fin (opcional)
                    </label>
                    <input type="date" name="fecha_fin" id="fechaFin" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200">
                    <p class="text-xs text-gray-500 mt-1">Dejar vacío para tarea continua</p>
                </div>
            </div>

            <!-- Opciones de frecuencia dinámicas -->
            <div id="opcionesFrecuencia" class="mt-6 space-y-6 hidden">
                <!-- Se carga dinámicamente según la frecuencia -->
            </div>
        </div>

        <!-- Botones de acción -->
        <div class="flex justify-between pt-6 border-t border-gray-200">
            <button type="button" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium py-2.5 px-5 rounded-lg transition-all duration-200" onclick="alternarFormulario()">
                <i class="far fa-calendar-alt mr-2"></i>Cambiar a Única
            </button>
            <div class="space-x-3">
                <button type="button" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium py-2.5 px-5 rounded-lg transition-all duration-200" onclick="resetearFormulario()">
                    <i class="fas fa-redo mr-2"></i>Limpiar
                </button>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 px-5 rounded-lg transition-all duration-200 flex items-center justify-center">
                    <i class="fas fa-sync-alt mr-2"></i>Crear Tarea Cíclica
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Modal de carga -->
<div id="loadingModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg p-6 max-w-sm w-full mx-4">
        <div class="flex flex-col items-center">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mb-4"></div>
            <p class="text-gray-700 font-medium">Guardando tarea...</p>
            <p class="text-gray-500 text-sm mt-2">Por favor espere</p>
        </div>
    </div>
</div>

<!-- Modal de éxito -->
<div id="successModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg p-6 max-w-sm w-full mx-4">
        <div class="flex flex-col items-center text-center">
            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mb-4">
                <i class="fas fa-check text-green-600 text-xl"></i>
            </div>
            <h3 class="text-lg font-semibold text-gray-800 mb-2">¡Tarea creada!</h3>
            <p class="text-gray-600 mb-6">La tarea cíclica se ha creado exitosamente.</p>
            <button onclick="cerrarModalExito()" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded-lg transition-all duration-200">
                Aceptar
            </button>
        </div>
    </div>
</div>

<!-- Incluye Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<script>
// Configuración global
const config = {
    subcategorias: {
        mantenimiento: ['Preventivo', 'Correctivo', 'Predictivo', 'Lubricación', 'Calibración'],
        limpieza: ['General', 'Profunda', 'Desinfección', 'Limpieza técnica'],
        seguridad: ['Inspección', 'Capacitación', 'Equipos de protección', 'Procedimientos'],
        administrativo: ['Reportes', 'Documentación', 'Archivo', 'Correspondencia'],
        operativo: ['Producción', 'Control calidad', 'Embalaje', 'Despacho']
    },
    
    meses: [
        { value: 'enero', label: 'Enero' },
        { value: 'febrero', label: 'Febrero' },
        { value: 'marzo', label: 'Marzo' },
        { value: 'abril', label: 'Abril' },
        { value: 'mayo', label: 'Mayo' },
        { value: 'junio', label: 'Junio' },
        { value: 'julio', label: 'Julio' },
        { value: 'agosto', label: 'Agosto' },
        { value: 'septiembre', label: 'Septiembre' },
        { value: 'octubre', label: 'Octubre' },
        { value: 'noviembre', label: 'Noviembre' },
        { value: 'diciembre', label: 'Diciembre' }
    ],
    
    diasSemana: [
        { value: 'lunes', label: 'Lunes' },
        { value: 'martes', label: 'Martes' },
        { value: 'miercoles', label: 'Miércoles' },
        { value: 'jueves', label: 'Jueves' },
        { value: 'viernes', label: 'Viernes' },
        { value: 'sabado', label: 'Sábado' },
        { value: 'domingo', label: 'Domingo' }
    ],
    
    ordinales: [
        { value: '1er', label: 'Primer' },
        { value: '2do', label: 'Segundo' },
        { value: '3er', label: 'Tercer' },
        { value: '4to', label: 'Cuarto' },
        { value: '5to', label: 'Quinto' }
    ]
};

// Elementos DOM
const elementos = {
    btnUnica: document.getElementById('btnUnica'),
    btnCiclica: document.getElementById('btnCiclica'),
    formUnica: document.getElementById('formUnica'),
    formCiclica: document.getElementById('formCiclica'),
    categoriaServicio: document.getElementById('categoriaServicio'),
    subcategoriaServicio: document.getElementById('subcategoriaServicio'),
    frecuencia: document.getElementById('frecuencia'),
    opcionesFrecuencia: document.getElementById('opcionesFrecuencia'),
    fechaInicio: document.getElementById('fechaInicio'),
    fechaFin: document.getElementById('fechaFin'),
    loadingModal: document.getElementById('loadingModal'),
    successModal: document.getElementById('successModal')
};

// Estado de la aplicación
let estado = {
    formularioActual: 'ciclica',
    frecuenciaActual: ''
};

// URL del endpoint PHP (ajusta según tu estructura)
const API_URL = 'task/procesar_tarea.php';

// Inicialización
document.addEventListener('DOMContentLoaded', function() {
    // Establecer fecha mínima para fecha de inicio (hoy)
    const hoy = new Date().toISOString().split('T')[0];
    elementos.fechaInicio.min = hoy;
    elementos.fechaInicio.value = hoy;
    
    // Event listeners para fecha fin
    elementos.fechaInicio.addEventListener('change', function() {
        elementos.fechaFin.min = this.value;
        if (elementos.fechaFin.value && elementos.fechaFin.value < this.value) {
            elementos.fechaFin.value = this.value;
        }
    });
    
    // Cargar subcategorías dinámicamente
    elementos.categoriaServicio.addEventListener('change', cargarSubcategorias);
    
    // Cambio de frecuencia
    elementos.frecuencia.addEventListener('change', manejarCambioFrecuencia);
    
    // Submit formularios
    elementos.formUnica.addEventListener('submit', manejarSubmitUnica);
    elementos.formCiclica.addEventListener('submit', manejarSubmitCiclica);
});

// Funciones de utilidad
function crearElemento(tag, clases = '', contenido = '') {
    const elemento = document.createElement(tag);
    if (clases) elemento.className = clases;
    if (contenido) elemento.innerHTML = contenido;
    return elemento;
}

function mostrarError(campo, mensaje) {
    const input = elementos.formCiclica.querySelector(`[name="${campo}"]`);
    const errorDiv = elementos.formCiclica.querySelector(`[data-for="${campo}"]`);
    
    if (input) input.classList.add('border-red-500');
    if (errorDiv) {
        errorDiv.textContent = mensaje;
        errorDiv.classList.remove('hidden');
    }
}

function limpiarErrores() {
    elementos.formCiclica.querySelectorAll('.border-red-500').forEach(el => {
        el.classList.remove('border-red-500');
    });
    elementos.formCiclica.querySelectorAll('[data-for]').forEach(el => {
        el.classList.add('hidden');
    });
}

function mostrarCarga() {
    elementos.loadingModal.classList.remove('hidden');
}

function ocultarCarga() {
    elementos.loadingModal.classList.add('hidden');
}

function mostrarExito() {
    elementos.successModal.classList.remove('hidden');
}

function cerrarModalExito() {
    elementos.successModal.classList.add('hidden');
    resetearFormulario();
}

// Funciones principales
function alternarFormulario() {
    const esCiclica = estado.formularioActual === 'ciclica';
    
    estado.formularioActual = esCiclica ? 'unica' : 'ciclica';
    
    // Mostrar/ocultar formularios
    elementos.formUnica.classList.toggle('hidden', !esCiclica);
    elementos.formCiclica.classList.toggle('hidden', esCiclica);
    
    // Actualizar tabs
    const tabs = [elementos.btnUnica, elementos.btnCiclica];
    tabs.forEach(tab => {
        tab.classList.remove('text-blue-600', 'border-b-2', 'border-blue-600');
        tab.classList.add('text-gray-600');
    });
    
    if (esCiclica) {
        elementos.btnUnica.classList.add('text-blue-600', 'border-b-2', 'border-blue-600');
        elementos.btnCiclica.classList.remove('text-blue-600', 'border-b-2', 'border-blue-600');
    } else {
        elementos.btnCiclica.classList.add('text-blue-600', 'border-b-2', 'border-blue-600');
        elementos.btnUnica.classList.remove('text-blue-600', 'border-b-2', 'border-blue-600');
    }
}

function cargarSubcategorias() {
    const categoria = elementos.categoriaServicio.value;
    const select = elementos.subcategoriaServicio;
    
    select.innerHTML = '<option value="">Seleccione subcategoría</option>';
    
    if (categoria && config.subcategorias[categoria]) {
        select.disabled = false;
        config.subcategorias[categoria].forEach(subcat => {
            const option = document.createElement('option');
            option.value = subcat.toLowerCase().replace(/ /g, '_');
            option.textContent = subcat;
            select.appendChild(option);
        });
    } else {
        select.disabled = true;
    }
}

function manejarCambioFrecuencia() {
    const frecuencia = elementos.frecuencia.value;
    estado.frecuenciaActual = frecuencia;
    elementos.opcionesFrecuencia.innerHTML = '';
    
    if (!frecuencia || frecuencia === 'esporadico') {
        elementos.opcionesFrecuencia.classList.add('hidden');
        return;
    }
    
    elementos.opcionesFrecuencia.classList.remove('hidden');
    
    switch(frecuencia) {
        case 'diario':
            cargarOpcionesDiario();
            break;
        case 'semanal':
            cargarOpcionesSemanal();
            break;
        case 'quincenal':
            cargarOpcionesQuincenal();
            break;
        case 'mensual':
            cargarOpcionesMensual();
            break;
        case 'bimestral':
        case 'trimestral':
        case 'semestral':
        case 'anual':
            cargarOpcionesPeriodica(frecuencia);
            break;
    }
}

function cargarOpcionesDiario() {
    const contenedor = crearElemento('div', 'grid grid-cols-1 md:grid-cols-2 gap-6');
    
    // Campo: Cuándo
    const divCuando = crearElemento('div');
    divCuando.appendChild(crearElemento('label', 'block text-sm font-medium text-gray-700 mb-2', 'Cuándo <span class="text-red-500">*</span>'));
    
    const selectCuando = crearElemento('select', 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200');
    selectCuando.name = 'cuando_diario';
    selectCuando.innerHTML = `
        <option value="">Seleccione rango</option>
        <option value="lunes_viernes">Lunes a Viernes</option>
        <option value="lunes_sabado">Lunes a Sábado</option>
        <option value="todos_dias">Lunes a Domingo</option>
    `;
    divCuando.appendChild(selectCuando);
    
    // Campo: Cada cuánto
    const divCada = crearElemento('div');
    divCada.appendChild(crearElemento('label', 'block text-sm font-medium text-gray-700 mb-2', 'Cada cuánto días <span class="text-red-500">*</span>'));
    
    const inputCada = crearElemento('input', 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200');
    inputCada.type = 'number';
    inputCada.name = 'cada_dia';
    inputCada.min = '1';
    inputCada.value = '1';
    divCada.appendChild(inputCada);
    
    contenedor.appendChild(divCuando);
    contenedor.appendChild(divCada);
    elementos.opcionesFrecuencia.appendChild(contenedor);
}

function cargarOpcionesSemanal() {
    const contenedor = crearElemento('div', 'space-y-4');
    
    // Días de la semana
    const divDias = crearElemento('div');
    divDias.appendChild(crearElemento('label', 'block text-sm font-medium text-gray-700 mb-2', 'Días de la semana <span class="text-red-500">*</span>'));
    
    const divCheckboxes = crearElemento('div', 'grid grid-cols-2 md:grid-cols-4 gap-3');
    config.diasSemana.forEach(dia => {
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
    
    // Cada cuánto semanas
    const divCada = crearElemento('div');
    divCada.appendChild(crearElemento('label', 'block text-sm font-medium text-gray-700 mb-2', 'Cada cuántas semanas <span class="text-red-500">*</span>'));
    
    const inputCada = crearElemento('input', 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200');
    inputCada.type = 'number';
    inputCada.name = 'cada_semana';
    inputCada.min = '1';
    inputCada.value = '1';
    divCada.appendChild(inputCada);
    
    contenedor.appendChild(divDias);
    contenedor.appendChild(divCada);
    elementos.opcionesFrecuencia.appendChild(contenedor);
}

function cargarOpcionesQuincenal() {
    const contenedor = crearElemento('div', 'grid grid-cols-1 md:grid-cols-2 gap-6');
    
    // Campo: Día
    const divDia = crearElemento('div');
    divDia.appendChild(crearElemento('label', 'block text-sm font-medium text-gray-700 mb-2', 'Día de la semana <span class="text-red-500">*</span>'));
    
    const selectDia = crearElemento('select', 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200');
    selectDia.name = 'cuando_quincenal';
    selectDia.innerHTML = '<option value="">Seleccione día</option>';
    config.diasSemana.forEach(dia => {
        const option = crearElemento('option');
        option.value = dia.value;
        option.textContent = dia.label;
        selectDia.appendChild(option);
    });
    divDia.appendChild(selectDia);
    
    // Campo: Cada cuánto
    const divCada = crearElemento('div');
    divCada.appendChild(crearElemento('label', 'block text-sm font-medium text-gray-700 mb-2', 'Cada cuántas quincenas'));
    
    const inputCada = crearElemento('input', 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200');
    inputCada.type = 'number';
    inputCada.name = 'cada_quincena';
    inputCada.min = '1';
    inputCada.value = '1';
    divCada.appendChild(inputCada);
    
    contenedor.appendChild(divDia);
    contenedor.appendChild(divCada);
    elementos.opcionesFrecuencia.appendChild(contenedor);
}

function cargarOpcionesMensual() {
    const contenedor = crearElemento('div', 'space-y-4');
    
    // Opción 1: Día específico del mes
    const divOpcion1 = crearElemento('div', 'space-y-4');
    divOpcion1.appendChild(crearElemento('h4', 'font-medium text-gray-700', 'Opción 1: Día específico del mes'));
    
    const divDiaMes = crearElemento('div', 'grid grid-cols-2 gap-4');
    
    const divDia = crearElemento('div');
    divDia.appendChild(crearElemento('label', 'block text-sm font-medium text-gray-700 mb-2', 'Día del mes (1-31)'));
    const inputDia = crearElemento('input', 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200');
    inputDia.type = 'number';
    inputDia.name = 'dia_mes';
    inputDia.min = '1';
    inputDia.max = '31';
    inputDia.placeholder = 'Ej: 15';
    divDia.appendChild(inputDia);
    
    const divCada = crearElemento('div');
    divCada.appendChild(crearElemento('label', 'block text-sm font-medium text-gray-700 mb-2', 'Cada cuántos meses'));
    const inputCada = crearElemento('input', 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200');
    inputCada.type = 'number';
    inputCada.name = 'cada_mes';
    inputCada.min = '1';
    inputCada.value = '1';
    divCada.appendChild(inputCada);
    
    divDiaMes.appendChild(divDia);
    divDiaMes.appendChild(divCada);
    divOpcion1.appendChild(divDiaMes);
    
    // Separador
    const separator = crearElemento('div', 'flex items-center my-4');
    separator.innerHTML = `
        <div class="flex-1 border-t border-gray-300"></div>
        <span class="px-3 text-sm text-gray-500">O</span>
        <div class="flex-1 border-t border-gray-300"></div>
    `;
    
    // Opción 2: Día ordinal de la semana
    const divOpcion2 = crearElemento('div', 'space-y-4');
    divOpcion2.appendChild(crearElemento('h4', 'font-medium text-gray-700', 'Opción 2: Día ordinal de la semana'));
    
    const divOrdinal = crearElemento('div', 'grid grid-cols-1 md:grid-cols-3 gap-4');
    
    // Ordinal
    const divOrdinalSelect = crearElemento('div');
    divOrdinalSelect.appendChild(crearElemento('label', 'block text-sm font-medium text-gray-700 mb-2', 'Ordinal'));
    const selectOrdinal = crearElemento('select', 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200');
    selectOrdinal.name = 'ordinal_mensual';
    selectOrdinal.innerHTML = '<option value="">Seleccione</option>';
    config.ordinales.forEach(ord => {
        const option = crearElemento('option');
        option.value = ord.value;
        option.textContent = ord.label;
        selectOrdinal.appendChild(option);
    });
    divOrdinalSelect.appendChild(selectOrdinal);
    
    // Día de la semana
    const divDiaSemana = crearElemento('div');
    divDiaSemana.appendChild(crearElemento('label', 'block text-sm font-medium text-gray-700 mb-2', 'Día de la semana'));
    const selectDiaSemana = crearElemento('select', 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200');
    selectDiaSemana.name = 'dia_semana_mensual';
    selectDiaSemana.innerHTML = '<option value="">Seleccione día</option>';
    config.diasSemana.forEach(dia => {
        const option = crearElemento('option');
        option.value = dia.value;
        option.textContent = dia.label;
        selectDiaSemana.appendChild(option);
    });
    divDiaSemana.appendChild(selectDiaSemana);
    
    // Cada cuánto
    const divCadaMensual = crearElemento('div');
    divCadaMensual.appendChild(crearElemento('label', 'block text-sm font-medium text-gray-700 mb-2', 'Cada cuánto'));
    const inputCadaMensual = crearElemento('input', 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200');
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
    
    contenedor.appendChild(divOpcion1);
    contenedor.appendChild(separator);
    contenedor.appendChild(divOpcion2);
    elementos.opcionesFrecuencia.appendChild(contenedor);
}

function cargarOpcionesPeriodica(frecuencia) {
    const contenedor = crearElemento('div', 'space-y-6');
    
    // Nombre de la frecuencia
    const nombresFrecuencia = {
        bimestral: 'Bimestral',
        trimestral: 'Trimestral',
        semestral: 'Semestral',
        anual: 'Anual'
    };
    
    contenedor.appendChild(crearElemento('h4', 'font-medium text-gray-700', 
        `Configuración ${nombresFrecuencia[frecuencia]}`));
    
    // Primera fila: Ordinal y Día
    const divFila1 = crearElemento('div', 'grid grid-cols-1 md:grid-cols-3 gap-4');
    
    // Ordinal
    const divOrdinal = crearElemento('div');
    divOrdinal.appendChild(crearElemento('label', 'block text-sm font-medium text-gray-700 mb-2', 'Ordinal'));
    const selectOrdinal = crearElemento('select', 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200');
    selectOrdinal.name = `ordinal_${frecuencia}`;
    selectOrdinal.innerHTML = '<option value="">Seleccione</option>';
    config.ordinales.forEach(ord => {
        const option = crearElemento('option');
        option.value = ord.value;
        option.textContent = ord.label;
        selectOrdinal.appendChild(option);
    });
    divOrdinal.appendChild(selectOrdinal);
    
    // Día de la semana
    const divDiaSemana = crearElemento('div');
    divDiaSemana.appendChild(crearElemento('label', 'block text-sm font-medium text-gray-700 mb-2', 'Día de la semana'));
    const selectDiaSemana = crearElemento('select', 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200');
    selectDiaSemana.name = `dia_semana_${frecuencia}`;
    selectDiaSemana.innerHTML = '<option value="">Seleccione día</option>';
    config.diasSemana.forEach(dia => {
        const option = crearElemento('option');
        option.value = dia.value;
        option.textContent = dia.label;
        selectDiaSemana.appendChild(option);
    });
    divDiaSemana.appendChild(selectDiaSemana);
    
    // Mes
    const divMes = crearElemento('div');
    divMes.appendChild(crearElemento('label', 'block text-sm font-medium text-gray-700 mb-2', 'Mes'));
    const selectMes = crearElemento('select', 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200');
    selectMes.name = `mes_${frecuencia}`;
    selectMes.innerHTML = '<option value="">Seleccione mes</option>';
    config.meses.forEach(mes => {
        const option = crearElemento('option');
        option.value = mes.value;
        option.textContent = mes.label;
        selectMes.appendChild(option);
    });
    divMes.appendChild(selectMes);
    
    divFila1.appendChild(divOrdinal);
    divFila1.appendChild(divDiaSemana);
    divFila1.appendChild(divMes);
    
    // Segunda fila: Cada cuánto
    const divFila2 = crearElemento('div', 'grid grid-cols-1 md:grid-cols-2 gap-4');
    
    const divCada = crearElemento('div');
    divCada.appendChild(crearElemento('label', 'block text-sm font-medium text-gray-700 mb-2', 'Cada cuánto'));
    
    const inputGroup = crearElemento('div', 'flex items-center space-x-2');
    const inputCada = crearElemento('input', 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 w-24');
    inputCada.type = 'number';
    inputCada.name = `cada_${frecuencia}`;
    
    // Valores predeterminados según frecuencia
    const valoresDefault = {
        bimestral: '2',
        trimestral: '3',
        semestral: '6',
        anual: '1'
    };
    
    inputCada.min = valoresDefault[frecuencia];
    inputCada.value = valoresDefault[frecuencia];
    
    const unidad = frecuencia === 'anual' ? 'año(s)' : 'mes(es)';
    inputGroup.appendChild(inputCada);
    inputGroup.appendChild(document.createTextNode(` ${unidad}`));
    divCada.appendChild(inputGroup);
    
    divFila2.appendChild(divCada);
    
    contenedor.appendChild(divFila1);
    contenedor.appendChild(divFila2);
    elementos.opcionesFrecuencia.appendChild(contenedor);
}

// Función para preparar datos según tu script PHP
function prepararDatosParaPHP(formData, frecuencia) {
    let cuando = '';
    let cadaCuanto = '';
    
    switch(frecuencia.toLowerCase()) {
        case 'diario':
            const cuandoDiario = formData.get('cuando_diario');
            const cadaDia = formData.get('cada_dia') || '1';
            
            // Convertir a formato que entiende tu PHP
            if (cuandoDiario === 'lunes_viernes') cuando = 'LUNES A VIERNES';
            else if (cuandoDiario === 'lunes_sabado') cuando = 'LUNES A SABADO';
            else if (cuandoDiario === 'todos_dias') cuando = 'LUNES A DOMINGO';
            
            cadaCuanto = cadaDia;
            break;
            
        case 'semanal':
            const diasSeleccionados = formData.getAll('dias_semana[]');
            const cadaSemana = formData.get('cada_semana') || '1';
            
            if (diasSeleccionados.length > 0) {
                // Convertir a mayúsculas
                cuando = diasSeleccionados.map(d => d.toUpperCase()).join(' ');
                cadaCuanto = cadaSemana;
            }
            break;
            
        case 'quincenal':
            const diaQuincenal = formData.get('cuando_quincenal');
            const cadaQuincena = formData.get('cada_quincena') || '1';
            
            if (diaQuincenal) {
                cuando = diaQuincenal.toUpperCase();
                cadaCuanto = cadaQuincena;
            }
            break;
            
        case 'mensual':
            const diaMes = formData.get('dia_mes');
            const cadaMes = formData.get('cada_mes') || '1';
            const ordinalMensual = formData.get('ordinal_mensual');
            const diaSemanaMensual = formData.get('dia_semana_mensual');
            
            if (diaMes) {
                cuando = `DIA ${diaMes}`;
                cadaCuanto = cadaMes;
            } else if (ordinalMensual && diaSemanaMensual) {
                cuando = `${ordinalMensual.toUpperCase()} ${diaSemanaMensual.toUpperCase()}`;
                cadaCuanto = formData.get('cada_mensual') || '1';
            }
            break;
            
        case 'bimestral':
        case 'trimestral':
        case 'semestral':
        case 'anual':
            const ordinal = formData.get(`ordinal_${frecuencia}`);
            const diaSemana = formData.get(`dia_semana_${frecuencia}`);
            const mes = formData.get(`mes_${frecuencia}`);
            const cadaPeriodo = formData.get(`cada_${frecuencia}`) || 
                (frecuencia === 'bimestral' ? '2' : 
                 frecuencia === 'trimestral' ? '3' : 
                 frecuencia === 'semestral' ? '6' : '1');
            
            if (ordinal && diaSemana) {
                cuando = `${ordinal.toUpperCase()} ${diaSemana.toUpperCase()}`;
                cadaCuanto = mes ? mes.toUpperCase() : 'ENERO';
            }
            break;
            
        case 'esporadico':
            cuando = 'ESPORADICO';
            cadaCuanto = '';
            break;
    }
    
    return {
        puesto: formData.get('puesto'),
        actividad: formData.get('actividad_puesto'),
        categoria: formData.get('categoria_servicio'),
        subcategoria: formData.get('subcategoria_servicio'),
        frecuencia: frecuencia.toUpperCase(),
        cuando: cuando,
        cada_cuanto: cadaCuanto,
        hora: formData.get('hora'),
        area: formData.get('area'),
        descripcion: formData.get('descripcion')
    };
}

async function enviarTareaCiclica(datos) {
    try {
        mostrarCarga();
        
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify([datos]) // Envía como array para compatibilidad con tu script
        });
        
        const result = await response.json();
        
        ocultarCarga();
        
        if (result.error) {
            throw new Error(result.msg || 'Error al guardar la tarea');
        }
        
        mostrarExito();
        return result;
        
    } catch (error) {
        ocultarCarga();
        console.error('Error:', error);
        alert(`Error: ${error.message}`);
        throw error;
    }
}

function manejarSubmitUnica(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    let valido = true;
    
    // Validar campos requeridos
    const camposRequeridos = ['tipo_trabajo', 'fecha_unica', 'area', 'descripcion'];
    camposRequeridos.forEach(campo => {
        if (!formData.get(campo)) {
            alert(`Por favor complete el campo: ${campo}`);
            valido = false;
        }
    });
    
    if (valido) {
        // Aquí puedes agregar la lógica para guardar tarea única
        alert('Tarea única guardada (esta funcionalidad puede implementarse separadamente)');
        e.target.reset();
        alternarFormulario();
    }
}

async function manejarSubmitCiclica(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    let valido = true;
    
    // Validar campos básicos requeridos
    const camposRequeridos = [
        'puesto', 'actividad_puesto', 'categoria_servicio',
        'subcategoria_servicio', 'frecuencia', 'hora', 'fecha_inicio',
        'area', 'descripcion'
    ];
    
    camposRequeridos.forEach(campo => {
        if (!formData.get(campo)) {
            alert(`Por favor complete el campo: ${campo}`);
            valido = false;
        }
    });
    
    // Validar subcategoría
    if (formData.get('categoria_servicio') && !formData.get('subcategoria_servicio')) {
        alert('Debe seleccionar una subcategoría');
        valido = false;
    }
    
    // Validar configuración de frecuencia
    const frecuencia = formData.get('frecuencia');
    if (frecuencia && frecuencia !== 'esporadico') {
        switch(frecuencia) {
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
    
    if (!valido) return;
    
    try {
        // Preparar datos según tu script PHP
        const datos = prepararDatosParaPHP(formData, frecuencia);
        
        // Enviar a la base de datos
        const resultado = await enviarTareaCiclica(datos);
        
        console.log('Resultado:', resultado);
        
    } catch (error) {
        console.error('Error al guardar:', error);
    }
}

function resetearFormulario() {
    elementos.formCiclica.reset();
    
    // Restaurar valores por defecto
    const hoy = new Date().toISOString().split('T')[0];
    elementos.fechaInicio.value = hoy;
    elementos.fechaFin.value = '';
    
    // Limpiar opciones de frecuencia
    elementos.opcionesFrecuencia.innerHTML = '';
    elementos.opcionesFrecuencia.classList.add('hidden');
    
    // Limpiar errores
    limpiarErrores();
    
    // Restaurar select de subcategoría
    elementos.subcategoriaServicio.disabled = true;
    elementos.subcategoriaServicio.innerHTML = '<option value="">Primero seleccione una categoría</option>';
}
</script>