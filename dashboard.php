<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flow | Tablero Kanbans</title>
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
            min-height: 600px;
        }
        .kanban-card {
            transition: all 0.2s ease;
            cursor: move;
        }
        .kanban-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        .dragging {
            opacity: 0.5;
            transform: rotate(5deg);
        }
        .priority-high { border-left: 4px solid #EF4444; }
        .priority-medium { border-left: 4px solid #F59E0B; }
        .priority-low { border-left: 4px solid #10B981; }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 font-sans">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <div class="w-64 bg-white border-r border-gray-200 p-6">
            <div class="mb-8">
                <h1 class="text-xl font-bold text-gray-900">Flow</h1>
                <p class="text-sm text-gray-500">Gestión de productividad</p>
            </div>
            
            <nav class="space-y-2">
                <a href="calendar.html" class="flex items-center space-x-3 px-3 py-2 text-gray-600 hover:bg-gray-50 rounded-lg">
                    <i class="fas fa-calendar w-5 text-center"></i>
                    <span>Calendario</span>
                </a>
                <a href="kanban.html" class="flex items-center space-x-3 px-3 py-2 bg-blue-50 text-blue-600 rounded-lg border-l-4 border-blue-600">
                    <i class="fas fa-columns w-5 text-center"></i>
                    <span>Kanban</span>
                </a>
                <a href="tasks.html" class="flex items-center space-x-3 px-3 py-2 text-gray-600 hover:bg-gray-50 rounded-lg">
                    <i class="fas fa-tasks w-5 text-center"></i>
                    <span>Tareas</span>
                </a>
                <a href="tickets.html" class="flex items-center space-x-3 px-3 py-2 text-gray-600 hover:bg-gray-50 rounded-lg">
                    <i class="fas fa-ticket-alt w-5 text-center"></i>
                    <span>Tickets</span>
                </a>
                <a href="meetings.html" class="flex items-center space-x-3 px-3 py-2 text-gray-600 hover:bg-gray-50 rounded-lg">
                    <i class="fas fa-users w-5 text-center"></i>
                    <span>Reuniones</span>
                </a>
                <a href="notices.html" class="flex items-center space-x-3 px-3 py-2 text-gray-600 hover:bg-gray-50 rounded-lg">
                    <i class="fas fa-bell w-5 text-center"></i>
                    <span>Avisos</span>
                </a>
                <a href="metrics.html" class="flex items-center space-x-3 px-3 py-2 text-gray-600 hover:bg-gray-50 rounded-lg">
                    <i class="fas fa-chart-bar w-5 text-center"></i>
                    <span>Reportes</span>
                </a>
                <a href="evaluations.html" class=flex items-center space-x-3 px-3 py-2 text-gray-600 hover:bg-gray-50 rounded-lg"">
                    <i class="fas fa-star w-5 text-center"></i>
                    <span>Evaluaciones</span>
                </a>
                <a href="ticket-evaluations.html"  class="flex items-center space-x-3 px-3 py-2 text-gray-600 hover:bg-gray-50 rounded-lg">
                    <i class="fas fa-user-chart w-5 text-center"></i>
                    <span>Análisis Personal</span>
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="flex-1 p-8">
            <!-- Header -->
            <div class="flex justify-between items-center mb-8">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Tablero Kanban</h1>
                    <p class="text-gray-500">Gestión visual de tareas y proyectos</p>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-medium">
                        Proyecto: Sistema Flow
                    </div>
                    <div class="flex items-center space-x-3 bg-white px-4 py-2 rounded-lg border border-gray-200">
                        <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center text-white font-medium">JD</div>
                        <div>
                            <span class="text-gray-700">John Doe</span>
                            <div class="text-xs text-gray-500">Administrador</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                <div class="bg-white p-6 rounded-xl border border-gray-200">
                    <div class="text-2xl font-bold text-blue-600">24</div>
                    <div class="text-gray-500 text-sm">Total de tareas</div>
                </div>
                <div class="bg-white p-6 rounded-xl border border-gray-200">
                    <div class="text-2xl font-bold text-warning">8</div>
                    <div class="text-gray-500 text-sm">En progreso</div>
                </div>
                <div class="bg-white p-6 rounded-xl border border-gray-200">
                    <div class="text-2xl font-bold text-success">12</div>
                    <div class="text-gray-500 text-sm">Completadas</div>
                </div>
                <div class="bg-white p-6 rounded-xl border border-gray-200">
                    <div class="text-2xl font-bold text-purple-600">4</div>
                    <div class="text-gray-500 text-sm">En revisión</div>
                </div>
            </div>

            <!-- Action Bar -->
            <div class="flex justify-between items-center mb-6">
                <div class="flex space-x-2">
                    <button id="add-task-btn" class="px-4 py-2 bg-blue-600 text-white rounded-lg font-medium flex items-center space-x-2 hover:bg-blue-700">
                        <i class="fas fa-plus"></i>
                        <span>Nueva Tarea</span>
                    </button>
                    <button class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg font-medium hover:bg-gray-50">
                        <i class="fas fa-filter mr-2"></i>Filtrar
                    </button>
                    <button class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg font-medium hover:bg-gray-50">
                        <i class="fas fa-cog mr-2"></i>Configurar
                    </button>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <input type="text" placeholder="Buscar tareas..." class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                    </div>
                </div>
            </div>

            <!-- Kanban Board -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 overflow-x-auto pb-6">
                <!-- Pendiente Column -->
                <div class="kanban-column bg-gray-50 rounded-xl p-4 min-w-80">
                    <div class="flex justify-between items-center mb-4">
                        <div class="flex items-center space-x-2">
                            <div class="w-3 h-3 bg-gray-400 rounded-full"></div>
                            <h3 class="font-semibold text-gray-700">Pendiente</h3>
                        </div>
                        <span class="bg-gray-200 text-gray-700 rounded-full px-2 py-1 text-xs font-medium">6</span>
                    </div>
                    <div class="space-y-3" id="pending-column" ondrop="drop(event)" ondragover="allowDrop(event)">
                        <!-- Task Cards will be populated by JavaScript -->
                    </div>
                    <button class="w-full mt-4 py-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">
                        <i class="fas fa-plus mr-2"></i>Agregar tarea
                    </button>
                </div>
                
                <!-- En Progreso Column -->
                <div class="kanban-column bg-blue-50 rounded-xl p-4 min-w-80">
                    <div class="flex justify-between items-center mb-4">
                        <div class="flex items-center space-x-2">
                            <div class="w-3 h-3 bg-blue-500 rounded-full"></div>
                            <h3 class="font-semibold text-gray-700">En Progreso</h3>
                        </div>
                        <span class="bg-blue-200 text-blue-800 rounded-full px-2 py-1 text-xs font-medium">8</span>
                    </div>
                    <div class="space-y-3" id="progress-column" ondrop="drop(event)" ondragover="allowDrop(event)">
                        <!-- Task Cards will be populated by JavaScript -->
                    </div>
                    <button class="w-full mt-4 py-2 text-gray-500 hover:text-gray-700 hover:bg-blue-100 rounded-lg transition-colors">
                        <i class="fas fa-plus mr-2"></i>Agregar tarea
                    </button>
                </div>
                
                <!-- Revisión Column -->
                <div class="kanban-column bg-yellow-50 rounded-xl p-4 min-w-80">
                    <div class="flex justify-between items-center mb-4">
                        <div class="flex items-center space-x-2">
                            <div class="w-3 h-3 bg-yellow-500 rounded-full"></div>
                            <h3 class="font-semibold text-gray-700">Revisión</h3>
                        </div>
                        <span class="bg-yellow-200 text-yellow-800 rounded-full px-2 py-1 text-xs font-medium">4</span>
                    </div>
                    <div class="space-y-3" id="review-column" ondrop="drop(event)" ondragoover="allowDrop(event)">
                        <!-- Task Cards will be populated by JavaScript -->
                    </div>
                    <button class="w-full mt-4 py-2 text-gray-500 hover:text-gray-700 hover:bg-yellow-100 rounded-lg transition-colors">
                        <i class="fas fa-plus mr-2"></i>Agregar tarea
                    </button>
                </div>
                
                <!-- Completado Column -->
                <div class="kanban-column bg-green-50 rounded-xl p-4 min-w-80">
                    <div class="flex justify-between items-center mb-4">
                        <div class="flex items-center space-x-2">
                            <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                            <h3 class="font-semibold text-gray-700">Completado</h3>
                        </div>
                        <span class="bg-green-200 text-green-800 rounded-full px-2 py-1 text-xs font-medium">6</span>
                    </div>
                    <div class="space-y-3" id="completed-column" ondrop="drop(event)" ondragover="allowDrop(event)">
                        <!-- Task Cards will be populated by JavaScript -->
                    </div>
                    <button class="w-full mt-4 py-2 text-gray-500 hover:text-gray-700 hover:bg-green-100 rounded-lg transition-colors">
                        <i class="fas fa-plus mr-2"></i>Agregar tarea
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal for Task Details -->
    <div id="task-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 p-4">
        <div class="bg-white rounded-xl w-full max-w-2xl mx-auto max-h-[90vh] overflow-hidden">
            <!-- Modal content would go here -->
        </div>
    </div>

    <script>
        // Sample Kanban data
        const kanbanTasks = [
            { 
                id: 1, 
                title: 'Desarrollar módulo de reportes', 
                description: 'Crear nuevo módulo para generar reportes automatizados del sistema',
                department: 'ti',
                dueDate: '2023-09-30',
                assignees: ['JD', 'MG'],
                status: 'progress',
                priority: 'high',
                tags: ['desarrollo', 'reportes']
            },
            { 
                id: 2, 
                title: 'Revisar documentación técnica', 
                description: 'Actualizar documentación del API y manual de usuario',
                department: 'ti',
                dueDate: '2023-09-25',
                assignees: ['MG'],
                status: 'pending',
                priority: 'medium',
                tags: ['documentación']
            },
            { 
                id: 3, 
                title: 'Corregir errores de UI', 
                description: 'Solucionar problemas de diseño en pantallas móviles',
                department: 'ti',
                dueDate: '2023-09-28',
                assignees: ['CL'],
                status: 'review',
                priority: 'medium',
                tags: ['frontend', 'ui/ux']
            },
            { 
                id: 4, 
                title: 'Implementar autenticación 2FA', 
                description: 'Sistema de autenticación de dos factores para seguridad',
                department: 'ti',
                dueDate: '2023-10-05',
                assignees: ['JD', 'CL'],
                status: 'progress',
                priority: 'high',
                tags: ['seguridad', 'backend']
            },
            { 
                id: 5, 
                title: 'Migrar base de datos', 
                description: 'Migrar datos a la nueva versión del sistema de gestión',
                department: 'ti',
                dueDate: '2023-09-20',
                assignees: ['JD'],
                status: 'pending',
                priority: 'high',
                tags: ['base de datos', 'migración']
            },
            { 
                id: 6, 
                title: 'Capacitación equipo de ventas', 
                description: 'Entrenamiento en uso del nuevo CRM',
                department: 'ventas',
                dueDate: '2023-09-22',
                assignees: ['AM'],
                status: 'completed',
                priority: 'medium',
                tags: ['capacitación', 'crm']
            }
        ];

        // Departments data
        const departments = {
            'ti': { name: 'TI', color: 'blue' },
            'ventas': { name: 'Ventas', color: 'green' },
            'marketing': { name: 'Marketing', color: 'purple' },
            'rh': { name: 'RH', color: 'orange' }
        };

        // Initialize Kanban board
        document.addEventListener('DOMContentLoaded', function() {
            renderKanbanBoard();
            setupEventListeners();
        });

        function renderKanbanBoard() {
            // Clear all columns
            document.getElementById('pending-column').innerHTML = '';
            document.getElementById('progress-column').innerHTML = '';
            document.getElementById('review-column').innerHTML = '';
            document.getElementById('completed-column').innerHTML = '';

            // Render tasks in their respective columns
            kanbanTasks.forEach(task => {
                const taskElement = createTaskCard(task);
                document.getElementById(`${task.status}-column`).appendChild(taskElement);
            });
        }

        function createTaskCard(task) {
            const taskElement = document.createElement('div');
            taskElement.classList.add('kanban-card', 'bg-white', 'p-4', 'rounded-lg', 'border', 'border-gray-200', 'shadow-sm');
            taskElement.setAttribute('draggable', 'true');
            taskElement.setAttribute('ondragstart', 'drag(event)');
            taskElement.setAttribute('id', `task-${task.id}`);
            taskElement.addEventListener('click', () => showTaskDetails(task));

            // Priority class
            const priorityClass = `priority-${task.priority}`;
            taskElement.classList.add(priorityClass);

            // Priority badge color
            let priorityColor = 'gray';
            let priorityText = 'Baja';
            if (task.priority === 'high') {
                priorityColor = 'red';
                priorityText = 'Alta';
            } else if (task.priority === 'medium') {
                priorityColor = 'yellow';
                priorityText = 'Media';
            }

            // Department info
            const dept = departments[task.department];

            taskElement.innerHTML = `
                <div class="flex justify-between items-start mb-2">
                    <h4 class="font-medium text-gray-900 text-sm flex-1">${task.title}</h4>
                    <div class="flex space-x-1 ml-2">
                        <span class="bg-${dept.color}-100 text-${dept.color}-800 text-xs px-2 py-1 rounded">${dept.name}</span>
                    </div>
                </div>
                <p class="text-gray-600 text-xs mb-3 line-clamp-2">${task.description}</p>
                
                <div class="flex flex-wrap gap-1 mb-3">
                    ${task.tags.map(tag => 
                        `<span class="bg-gray-100 text-gray-700 text-xs px-2 py-1 rounded">${tag}</span>`
                    ).join('')}
                </div>
                
                <div class="flex justify-between items-center">
                    <div class="flex items-center space-x-1 text-xs text-gray-500">
                        <i class="fas fa-calendar"></i>
                        <span>${new Date(task.dueDate).getDate()} ${getMonthName(new Date(task.dueDate).getMonth())}</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span class="bg-${priorityColor}-100 text-${priorityColor}-800 text-xs px-2 py-1 rounded">${priorityText}</span>
                        <div class="flex -space-x-2">
                            ${task.assignees.map(assignee => 
                                `<div class="w-6 h-6 bg-blue-500 rounded-full border-2 border-white flex items-center justify-center text-white text-xs">${assignee}</div>`
                            ).join('')}
                        </div>
                    </div>
                </div>
            `;
            
            return taskElement;
        }

        function getMonthName(monthIndex) {
            const months = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
            return months[monthIndex];
        }

        function setupEventListeners() {
            // Add task button
            document.getElementById('add-task-btn').addEventListener('click', function() {
                alert('Funcionalidad para agregar nueva tarea');
            });

            // Task detail modal
            document.getElementById('task-modal').addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.add('hidden');
                }
            });
        }

        function showTaskDetails(task) {
            document.getElementById('task-modal').classList.remove('hidden');
            // In a real application, you would populate the modal with task details
        }

        // Drag and Drop functionality
        function allowDrop(ev) {
            ev.preventDefault();
        }

        function drag(ev) {
            ev.dataTransfer.setData("text", ev.target.id);
            ev.target.classList.add('dragging');
        }

        function drop(ev) {
            ev.preventDefault();
            const data = ev.dataTransfer.getData("text");
            const draggedElement = document.getElementById(data);
            draggedElement.classList.remove('dragging');
            
            // Get the task ID from the element ID
            const taskId = parseInt(data.replace('task-', ''));
            
            // Determine the new status based on the column
            let newStatus = 'pending';
            if (ev.target.id === 'progress-column' || ev.target.closest('#progress-column')) {
                newStatus = 'progress';
            } else if (ev.target.id === 'review-column' || ev.target.closest('#review-column')) {
                newStatus = 'review';
            } else if (ev.target.id === 'completed-column' || ev.target.closest('#completed-column')) {
                newStatus = 'completed';
            }
            
            // Update the task status
            updateTaskStatus(taskId, newStatus);
            
            // Only append if dropping on a column (not on another card)
            if (ev.target.classList.contains('kanban-column') || 
                ev.target.id.includes('column') || 
                ev.target.classList.contains('space-y-3')) {
                ev.target.appendChild(draggedElement);
            } else if (ev.target.closest('.space-y-3')) {
                ev.target.closest('.space-y-3').appendChild(draggedElement);
            }
        }

        function updateTaskStatus(taskId, newStatus) {
            const taskIndex = kanbanTasks.findIndex(task => task.id === taskId);
            if (taskIndex !== -1) {
                kanbanTasks[taskIndex].status = newStatus;
                // In a real application, you would save this change to a database
                console.log(`Tarea ${taskId} movida a: ${newStatus}`);
            }
        }
    </script>
</body>
</html>
