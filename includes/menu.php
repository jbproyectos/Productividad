<?php
// menu.php - Versión corregida con rutas absolutas correctas
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$script_path = $_SERVER['SCRIPT_NAME'];
$base_dir = dirname($script_path);

// Si estamos en la raíz, usar '/' sino el directorio base
if ($base_dir == '/') {
    $base_url = $protocol . "://" . $host . "/";
} else {
    $base_url = $protocol . "://" . $host . $base_dir . "/";
}

// Limpiar dobles barras
$base_url = preg_replace('/([^:])(\/{2,})/', '$1/', $base_url);
$raiz = 'https://kabzo.ddns.net/prod/';

$current_page = basename($_SERVER['PHP_SELF']);
?>
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
        <!-- Calendario -->
        <a href="<?php echo $raiz; ?>calendar.php" 
           class="flex items-center space-x-3 px-3 py-2 <?php echo ($current_page == 'calendar.php') ? 'bg-blue-50 text-blue-600 border-l-4 border-blue-600' : 'text-gray-600 hover:bg-gray-50'; ?> rounded-lg">
            <i class="fas fa-calendar w-5 text-center"></i>
            <span>Calendario</span>
        </a>

        <!-- Kanban -->
        <a href="<?php echo $raiz; ?>kanban.php" 
           class="flex items-center space-x-3 px-3 py-2 <?php echo ($current_page == 'kanban.php') ? 'bg-blue-50 text-blue-600 border-l-4 border-blue-600' : 'text-gray-600 hover:bg-gray-50'; ?> rounded-lg">
            <i class="fas fa-columns w-5 text-center"></i>
            <span class="flex items-center space-x-2">
                <span>Kanban</span>
                <span class="bg-yellow-200 text-yellow-800 text-xs font-semibold px-2 py-0.5 rounded-full">Dev</span>
            </span>
        </a>

        <!-- Tareas -->
        <a href="<?php echo $raiz; ?>tasks.php" 
           class="flex items-center space-x-3 px-3 py-2 <?php echo ($current_page == 'tasks.php') ? 'bg-blue-50 text-blue-600 border-l-4 border-blue-600' : 'text-gray-600 hover:bg-gray-50'; ?> rounded-lg">
            <i class="fas fa-tasks w-5 text-center"></i>
            <span>Tareas</span>
                            <span class="bg-yellow-200 text-yellow-800 text-xs font-semibold px-2 py-0.5 rounded-full">Dev</span>

        </a>

        <!-- Tickets toda mi área -->
        <a href="<?php echo $raiz; ?>tickets.php" 
           class="flex items-center space-x-3 px-3 py-2 <?php echo ($current_page == 'tickets.php') ? 'bg-blue-50 text-blue-600 border-l-4 border-blue-600' : 'text-gray-600 hover:bg-gray-50'; ?> rounded-lg">
            <i class="fas fa-ticket-alt w-5 text-center"></i>
            <span>Tickets (toda mi área)</span>
        </a>

        <!-- Mis Tickets -->
        <a href="<?php echo $raiz; ?>mis-tickets.php" 
           class="flex items-center space-x-3 px-3 py-2 <?php echo ($current_page == 'mis-tickets.php') ? 'bg-blue-50 text-blue-600 border-l-4 border-blue-600' : 'text-gray-600 hover:bg-gray-50'; ?> rounded-lg">
            <i class="fas fa-ticket-alt w-5 text-center"></i>
            <span>Mis Tickets (yo abrí)</span>
        </a>

        <!-- Reuniones -->
        <a href="<?php echo $raiz; ?>meetings.php" 
           class="flex items-center space-x-3 px-3 py-2 <?php echo ($current_page == 'meetings.php') ? 'bg-blue-50 text-blue-600 border-l-4 border-blue-600' : 'text-gray-600 hover:bg-gray-50'; ?> rounded-lg">
            <i class="fas fa-users w-5 text-center"></i>
            <span class="flex items-center space-x-2">
                <span>Reuniones</span>
                <span class="bg-yellow-200 text-yellow-800 text-xs font-semibold px-2 py-0.5 rounded-full">Dev</span>
            </span>
        </a>

        <!-- Avisos -->
        <a href="<?php echo $base_url; ?>notices.php" 
           class="flex items-center space-x-3 px-3 py-2 <?php echo ($current_page == 'notices.php') ? 'bg-blue-50 text-blue-600 border-l-4 border-blue-600' : 'text-gray-600 hover:bg-gray-50'; ?> rounded-lg">
            <i class="fas fa-bell w-5 text-center"></i>
            <span class="flex items-center space-x-2">
                <span>Avisos</span>
                <span class="bg-yellow-200 text-yellow-800 text-xs font-semibold px-2 py-0.5 rounded-full">Dev</span>
            </span>
        </a>

        <!-- Reportes -->
        <a href="<?php echo $base_url; ?>metrics.php" 
           class="flex items-center space-x-3 px-3 py-2 <?php echo ($current_page == 'metrics.php') ? 'bg-blue-50 text-blue-600 border-l-4 border-blue-600' : 'text-gray-600 hover:bg-gray-50'; ?> rounded-lg">
            <i class="fas fa-chart-bar w-5 text-center"></i>
            <span class="flex items-center space-x-2">
                <span>Reportes</span>
                <span class="bg-yellow-200 text-yellow-800 text-xs font-semibold px-2 py-0.5 rounded-full">Dev</span>
            </span>
        </a>

        <!-- Evaluaciones -->
        <a href="<?php echo $base_url; ?>evaluations.php" 
           class="flex items-center space-x-3 px-3 py-2 <?php echo ($current_page == 'evaluations.php') ? 'bg-blue-50 text-blue-600 border-l-4 border-blue-600' : 'text-gray-600 hover:bg-gray-50'; ?> rounded-lg">
            <i class="fas fa-star w-5 text-center"></i>
            <span>Evaluaciones</span>
                            <span class="bg-yellow-200 text-yellow-800 text-xs font-semibold px-2 py-0.5 rounded-full">Dev</span>

        </a>

        <!-- Análisis Personal -->
        <a href="<?php echo $base_url; ?>ticket-evaluations.php" 
           class="flex items-center space-x-3 px-3 py-2 <?php echo ($current_page == 'ticket-evaluations.php') ? 'bg-blue-50 text-blue-600 border-l-4 border-blue-600' : 'text-gray-600 hover:bg-gray-50'; ?> rounded-lg">
            <i class="fas fa-user-chart w-5 text-center"></i>
            <span class="flex items-center space-x-2">
                <span>Análisis Personal</span>
                <span class="bg-yellow-200 text-yellow-800 text-xs font-semibold px-2 py-0.5 rounded-full">Dev</span>
            </span>
        </a>

        <!-- Catálogo Tickets -->
        <div class="group">
            <button class="toggle-catalogos flex items-center justify-between w-full px-3 py-2 text-gray-600 hover:bg-gray-50 rounded-lg focus:outline-none" 
                    data-target="tickets">
                <div class="flex items-center space-x-3">
                    <i class="fas fa-folder-open w-5 text-center"></i>
                    <span>Catálogo-Tickets</span>
                </div>
                <i class="fas fa-chevron-down transition-transform duration-300" data-arrow="tickets"></i>
            </button>

            <div id="submenu-tickets" class="hidden pl-10 mt-1 space-y-1">
                <a href="<?php echo $raiz; ?>catalogos/areas.php" 
                   class="flex items-center space-x-2 px-3 py-1 <?php echo ($current_page == 'areas.php') ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-100'; ?> rounded">
                    <i class="fas fa-building w-4 text-center"></i>
                    <span>Áreas</span>
                </a>
                <a href="<?php echo $raiz; ?>catalogos/categoria_servicio.php" 
                   class="flex items-center space-x-2 px-3 py-1 <?php echo ($current_page == 'categoria_servicio.php') ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-100'; ?> rounded">
                    <i class="fas fa-list-alt w-4 text-center"></i>
                    <span>Categorías de Servicio</span>
                </a>
                <a href="<?php echo $raiz; ?>catalogos/donde.php" 
                   class="flex items-center space-x-2 px-3 py-1 <?php echo ($current_page == 'donde.php') ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-100'; ?> rounded">
                    <i class="fas fa-project-diagram w-4 text-center"></i>
                    <span>Proyectos (Donde)</span>
                </a>
                <a href="<?php echo $raiz; ?>catalogos/subcategoria.php" 
                   class="flex items-center space-x-2 px-3 py-1 <?php echo ($current_page == 'subcategoria.php') ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-100'; ?> rounded">
                    <i class="fas fa-tags w-4 text-center"></i>
                    <span>Subcategorías</span>
                </a>
                <a href="<?php echo $raiz; ?>catalogos/detalle_donde.php" 
                   class="flex items-center space-x-2 px-3 py-1 <?php echo ($current_page == 'detalle_donde.php') ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-100'; ?> rounded">
                    <i class="fas fa-file-alt w-4 text-center"></i>
                    <span>Detalle donde</span>
                </a>
            </div>
        </div>

        <!-- Catálogo Productividad -->
        <div class="group">
            <button class="toggle-catalogos flex items-center justify-between w-full px-3 py-2 text-gray-600 hover:bg-gray-50 rounded-lg focus:outline-none" 
                    data-target="prod">
                <div class="flex items-center space-x-3">
                    <i class="fas fa-folder-open w-5 text-center"></i>
                    <span>Catálogo-Prod</span>
                </div>
                <i class="fas fa-chevron-down transition-transform duration-300" data-arrow="prod"></i>
            </button>

            <div id="submenu-prod" class="hidden pl-10 mt-1 space-y-1">
                <a href="<?php echo $base_url; ?>catalogos-prod/areas.php" 
                   class="flex items-center space-x-2 px-3 py-1 <?php echo ($current_page == 'areas.php' && strpos($_SERVER['REQUEST_URI'], 'catalogos-prod') !== false) ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-100'; ?> rounded">
                    <i class="fas fa-building w-4 text-center"></i>
                    <span>Áreas Prod</span>
                </a>
                <a href="<?php echo $base_url; ?>catalogos-prod/proyectos.php" 
                   class="flex items-center space-x-2 px-3 py-1 <?php echo ($current_page == 'proyectos.php') ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-100'; ?> rounded">
                    <i class="fas fa-tasks w-4 text-center"></i>
                    <span>Proyectos</span>
                </a>
                <a href="<?php echo $base_url; ?>catalogos-prod/tipos-tarea.php" 
                   class="flex items-center space-x-2 px-3 py-1 <?php echo ($current_page == 'tipos-tarea.php') ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-100'; ?> rounded">
                    <i class="fas fa-list w-4 text-center"></i>
                    <span>Tipos de Tarea</span>
                </a>
                <a href="<?php echo $base_url; ?>catalogos-prod/estados.php" 
                   class="flex items-center space-x-2 px-3 py-1 <?php echo ($current_page == 'estados.php') ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-100'; ?> rounded">
                    <i class="fas fa-flag w-4 text-center"></i>
                    <span>Estados</span>
                </a>
                <a href="<?php echo $base_url; ?>catalogos-prod/prioridades.php" 
                   class="flex items-center space-x-2 px-3 py-1 <?php echo ($current_page == 'prioridades.php') ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-100'; ?> rounded">
                    <i class="fas fa-exclamation-circle w-4 text-center"></i>
                    <span>Prioridades</span>
                </a>
            </div>
        </div>

        <!-- Catálogo Reuniones -->
        <div class="group">
            <button class="toggle-catalogos flex items-center justify-between w-full px-3 py-2 text-gray-600 hover:bg-gray-50 rounded-lg focus:outline-none" 
                    data-target="reuni">
                <div class="flex items-center space-x-3">
                    <i class="fas fa-folder-open w-5 text-center"></i>
                    <span>Catálogo-Reuni</span>
                </div>
                <i class="fas fa-chevron-down transition-transform duration-300" data-arrow="reuni"></i>
            </button>

            <div id="submenu-reuni" class="hidden pl-10 mt-1 space-y-1">
                <a href="<?php echo $base_url; ?>catalogos-reuni/salas.php" 
                   class="flex items-center space-x-2 px-3 py-1 <?php echo ($current_page == 'salas.php') ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-100'; ?> rounded">
                    <i class="fas fa-door-open w-4 text-center"></i>
                    <span>Salas</span>
                </a>
                <a href="<?php echo $base_url; ?>catalogos-reuni/tipos-reunion.php" 
                   class="flex items-center space-x-2 px-3 py-1 <?php echo ($current_page == 'tipos-reunion.php') ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-100'; ?> rounded">
                    <i class="fas fa-users w-4 text-center"></i>
                    <span>Tipos de Reunión</span>
                </a>
                <a href="<?php echo $base_url; ?>catalogos-reuni/estados-reunion.php" 
                   class="flex items-center space-x-2 px-3 py-1 <?php echo ($current_page == 'estados-reunion.php') ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-100'; ?> rounded">
                    <i class="fas fa-calendar-check w-4 text-center"></i>
                    <span>Estados Reunión</span>
                </a>
                <a href="<?php echo $base_url; ?>catalogos-reuni/temas.php" 
                   class="flex items-center space-x-2 px-3 py-1 <?php echo ($current_page == 'temas.php') ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-100'; ?> rounded">
                    <i class="fas fa-comments w-4 text-center"></i>
                    <span>Temas</span>
                </a>
                <a href="<?php echo $base_url; ?>catalogos-reuni/participantes.php" 
                   class="flex items-center space-x-2 px-3 py-1 <?php echo ($current_page == 'participantes.php') ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-100'; ?> rounded">
                    <i class="fas fa-user-friends w-4 text-center"></i>
                    <span>Participantes</span>
                </a>
            </div>
        </div>

        <!-- Logout -->
        <a href="<?php echo $raiz; ?>logout.php" 
           class="flex items-center space-x-3 px-3 py-2 rounded-lg text-gray-600 hover:bg-gray-50 hover:text-red-600 transition-colors duration-200">
            <i class="fas fa-sign-out-alt w-5 text-center"></i>
            <span>Cerrar sesión</span>
        </a>
    </nav>
</div>


<script>
    // Script para mostrar/ocultar los submenús de Catálogos
    document.addEventListener('DOMContentLoaded', function() {
        const toggleButtons = document.querySelectorAll('.toggle-catalogos');
        
        toggleButtons.forEach(button => {
            button.addEventListener('click', function() {
                const target = this.getAttribute('data-target');
                const submenu = document.getElementById(`submenu-${target}`);
                const arrow = document.querySelector(`[data-arrow="${target}"]`);
                
                // Cerrar otros submenús
                document.querySelectorAll('[id^="submenu-"]').forEach(menu => {
                    if (menu.id !== `submenu-${target}`) {
                        menu.classList.add('hidden');
                    }
                });
                
                // Resetear otras flechas
                document.querySelectorAll('[data-arrow]').forEach(otherArrow => {
                    if (otherArrow !== arrow) {
                        otherArrow.classList.remove('rotate-180');
                    }
                });
                
                // Toggle el submenú actual
                submenu.classList.toggle('hidden');
                arrow.classList.toggle('rotate-180');
            });
        });

        // Cerrar sidebar en móviles
        const closeSidebar = document.getElementById('close-sidebar');
        if (closeSidebar) {
            closeSidebar.addEventListener('click', function() {
                document.querySelector('.sidebar').classList.add('hidden');
            });
        }

        // Función para abrir automáticamente el submenú correspondiente a la página actual
        function setActiveMenu() {
            const currentPath = window.location.pathname;
            
            // Detectar si estamos en catálogo tickets
            if (currentPath.includes('/catalogos/')) {
                document.getElementById('submenu-tickets').classList.remove('hidden');
                document.querySelector('[data-arrow="tickets"]').classList.add('rotate-180');
            }
            // Detectar si estamos en catálogo productividad
            else if (currentPath.includes('/catalogos-prod/')) {
                document.getElementById('submenu-prod').classList.remove('hidden');
                document.querySelector('[data-arrow="prod"]').classList.add('rotate-180');
            }
            // Detectar si estamos en catálogo reuniones
            else if (currentPath.includes('/catalogos-reuni/')) {
                document.getElementById('submenu-reuni').classList.remove('hidden');
                document.querySelector('[data-arrow="reuni"]').classList.add('rotate-180');
            }
        }

        setActiveMenu();

        // Manejar responsive - mostrar/ocultar sidebar
        function handleResponsive() {
            const sidebar = document.querySelector('.sidebar');
            if (window.innerWidth < 1024) {
                sidebar.classList.add('hidden');
            } else {
                sidebar.classList.remove('hidden');
            }
        }

        // Ejecutar al cargar y al redimensionar
        handleResponsive();
        window.addEventListener('resize', handleResponsive);
    });
</script>