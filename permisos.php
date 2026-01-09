<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flow | Gestión de Roles y Permisos</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
        .role-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }
        .role-table th, .role-table td {
            border: 1px solid #e5e7eb;
            padding: 0.75rem;
            text-align: left;
        }
        .role-table th {
            background-color: #f9fafb;
            font-weight: 600;
        }
        .role-header {
            background-color: #e5e7eb;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .role-header:hover {
            background-color: #d1d5db;
        }
        .role-sections {
            background-color: #f8fafc;
        }
        .checkbox-cell {
            text-align: center;
        }
        .permission-checkbox {
            transform: scale(1.2);
        }
        .task-card:hover {
            transform: translateY(-2px);
            transition: transform 0.3s ease;
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 font-sans">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <div class="w-64 bg-white border-r border-gray-200 p-6">
            <div class="mb-8">
                <h1 class="text-xl font-bold text-gray-900">Flow</h1>
                <p class="text-sm text-gray-500">Gestión de permisos</p>
            </div>
            
            <nav class="space-y-2">
                <a href="calendar.html" class="flex items-center space-x-3 px-3 py-2 text-gray-600 hover:bg-gray-50 rounded-lg">
                    <i class="fas fa-calendar w-5 text-center"></i>
                    <span>Calendario</span>
                </a>
                <a href="kanban.html" class="flex items-center space-x-3 px-3 py-2 text-gray-600 hover:bg-gray-50 rounded-lg">
                    <i class="fas fa-columns w-5 text-center"></i>
                    <span>Kanban</span>
                </a>
                <a href="#" class="flex items-center space-x-3 px-3 py-2 bg-blue-50 text-blue-600 rounded-lg border-l-4 border-blue-600">
                    <i class="fas fa-user-shield w-5 text-center"></i>
                    <span>Roles y Permisos</span>
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
                <a href="evaluations.html" class="flex items-center space-x-3 px-3 py-2 text-gray-600 hover:bg-gray-50 rounded-lg">
                    <i class="fas fa-star w-5 text-center"></i>
                    <span>Evaluaciones</span>
                </a>
                <a href="ticket-evaluations.html" class="flex items-center space-x-3 px-3 py-2 text-gray-600 hover:bg-gray-50 rounded-lg">
                    <i class="fas fa-user-chart w-5 text-center"></i>
                    <span>Análisis Personal</span>
                </a>
                <a href="gantt.html" class="flex items-center space-x-3 px-3 py-2 text-gray-600 hover:bg-gray-50 rounded-lg">
                    <i class="fas fa-chart-gantt w-5 text-center"></i>
                    <span>Diagrama Gantt</span>
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="flex-1 p-8">
            <!-- Header -->
            <div class="flex justify-between items-center mb-8">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Gestión de Roles y Permisos</h1>
                    <p class="text-gray-500">Administra roles, permisos y asignaciones de usuarios en tu sistema</p>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-medium">
                        Modo: Sin autenticación
                    </div>
                    <div class="flex items-center space-x-3 bg-white px-4 py-2 rounded-lg border border-gray-200">
                        <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center text-white font-medium">AD</div>
                        <div>
                            <span class="text-gray-700">Administrador</span>
                            <div class="text-xs text-gray-500">Modo de prueba</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-xl border border-gray-200 p-6 task-card">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-gray-500 text-sm">Total Roles</p>
                            <h3 class="text-2xl font-bold text-gray-900 mt-1" id="total-roles">0</h3>
                            <p class="text-blue-500 text-sm mt-1">
                                <i class="fas fa-users mr-1"></i> En el sistema
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-user-shield text-blue-600"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl border border-gray-200 p-6 task-card">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-gray-500 text-sm">Secciones</p>
                            <h3 class="text-2xl font-bold text-gray-900 mt-1" id="total-modelos">0</h3>
                            <p class="text-green-500 text-sm mt-1">
                                <i class="fas fa-cube mr-1"></i> Módulos del sistema
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-cubes text-green-600"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl border border-gray-200 p-6 task-card">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-gray-500 text-sm">Permisos</p>
                            <h3 class="text-2xl font-bold text-gray-900 mt-1" id="total-permisos">0</h3>
                            <p class="text-yellow-500 text-sm mt-1">
                                <i class="fas fa-key mr-1"></i> Tipos de permisos
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-key text-yellow-600"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl border border-gray-200 p-6 task-card">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-gray-500 text-sm">Asignaciones</p>
                            <h3 class="text-2xl font-bold text-gray-900 mt-1" id="total-asignaciones">0</h3>
                            <p class="text-purple-500 text-sm mt-1">
                                <i class="fas fa-link mr-1"></i> Relaciones activas
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-link text-purple-600"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
                <!-- Tabla de permisos -->
                <div class="lg:col-span-3">
                    <div class="bg-white rounded-xl border border-gray-200 p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-lg font-semibold text-gray-900">Gestión de Permisos por Rol</h2>
                            <div class="flex space-x-2">
                                <button class="px-3 py-1 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50" id="expand-all">
                                    <i class="fas fa-expand mr-1"></i>Expandir Todo
                                </button>
                                <button class="px-3 py-1 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50" id="collapse-all">
                                    <i class="fas fa-compress mr-1"></i>Contraer Todo
                                </button>
                            </div>
                        </div>
                        
                        <form id="asignarPermisosRol">
                            <div class="overflow-x-auto">
                                <table class="role-table">
                                    <thead>
                                        <tr>
                                            <th class="w-1/6">Rol</th>
                                            <th class="w-1/6">Sección</th>
                                            <th class="w-1/12 text-center" title="Permite ver el contenido">
                                                Ver
                                            </th>
                                            <th class="w-1/12 text-center" title="Permite crear nuevos elementos">
                                                Crear
                                            </th>
                                            <th class="w-1/12 text-center" title="Permite modificar elementos existentes">
                                                Editar
                                            </th>
                                            <th class="w-1/12 text-center" title="Permite eliminar elementos">
                                                Eliminar
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody id="roles-table-body">
                                        <!-- Los datos se cargarán dinámicamente aquí -->
                                    </tbody>
                                </table>
                            </div>

                            <!-- Botón de guardar -->
                            <div class="mt-6 flex justify-end">
                                <button type="submit" 
                                        class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium transition-colors duration-200 flex items-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Guardar Cambios
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="space-y-8">
                    <!-- Quick Actions -->
                    <div class="bg-white rounded-xl border border-gray-200 p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4">Acciones Rápidas</h2>
                        <div class="grid grid-cols-2 gap-4">
                            <button class="flex flex-col items-center justify-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50" id="new-role-btn">
                                <i class="fas fa-plus-circle text-blue-600 text-xl mb-2"></i>
                                <span class="text-sm font-medium">Nuevo Rol</span>
                            </button>
                            <button class="flex flex-col items-center justify-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50" id="new-section-btn">
                                <i class="fas fa-cube text-purple-600 text-xl mb-2"></i>
                                <span class="text-sm font-medium">Nueva Sección</span>
                            </button>
                            <button class="flex flex-col items-center justify-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50" id="reset-permissions">
                                <i class="fas fa-undo text-green-600 text-xl mb-2"></i>
                                <span class="text-sm font-medium">Restablecer</span>
                            </button>
                            <button class="flex flex-col items-center justify-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50" id="export-permissions">
                                <i class="fas fa-download text-orange-600 text-xl mb-2"></i>
                                <span class="text-sm font-medium">Exportar</span>
                            </button>
                        </div>
                    </div>

                    <!-- Statistics -->
                    <div class="bg-white rounded-xl border border-gray-200 p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4">Estadísticas</h2>
                        <div class="space-y-4">
                            <div>
                                <div class="flex justify-between mb-1">
                                    <span class="text-sm font-medium text-gray-700">Permisos de Ver</span>
                                    <span class="text-sm font-medium text-gray-700" id="view-percentage">0%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-blue-500 h-2 rounded-full" id="view-bar" style="width: 0%"></div>
                                </div>
                            </div>
                            
                            <div>
                                <div class="flex justify-between mb-1">
                                    <span class="text-sm font-medium text-gray-700">Permisos de Crear</span>
                                    <span class="text-sm font-medium text-gray-700" id="create-percentage">0%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-green-500 h-2 rounded-full" id="create-bar" style="width: 0%"></div>
                                </div>
                            </div>
                            
                            <div>
                                <div class="flex justify-between mb-1">
                                    <span class="text-sm font-medium text-gray-700">Permisos de Editar</span>
                                    <span class="text-sm font-medium text-gray-700" id="edit-percentage">0%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-yellow-500 h-2 rounded-full" id="edit-bar" style="width: 0%"></div>
                                </div>
                            </div>
                            
                            <div>
                                <div class="flex justify-between mb-1">
                                    <span class="text-sm font-medium text-gray-700">Permisos de Eliminar</span>
                                    <span class="text-sm font-medium text-gray-700" id="delete-percentage">0%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-red-500 h-2 rounded-full" id="delete-bar" style="width: 0%"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="bg-white rounded-xl border border-gray-200 p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4">Actividad Reciente</h2>
                        <div class="space-y-3">
                            <div class="flex items-center p-3 border border-gray-200 rounded-lg">
                                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                    <i class="fas fa-save text-blue-600"></i>
                                </div>
                                <div>
                                    <h3 class="text-sm font-medium text-gray-900">Permisos guardados</h3>
                                    <p class="text-xs text-gray-500">Hace 2 horas</p>
                                </div>
                            </div>
                            <div class="flex items-center p-3 border border-gray-200 rounded-lg">
                                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                                    <i class="fas fa-user-plus text-green-600"></i>
                                </div>
                                <div>
                                    <h3 class="text-sm font-medium text-gray-900">Nuevo rol creado</h3>
                                    <p class="text-xs text-gray-500">Hace 1 día</p>
                                </div>
                            </div>
                            <div class="flex items-center p-3 border border-gray-200 rounded-lg">
                                <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center mr-3">
                                    <i class="fas fa-cube text-orange-600"></i>
                                </div>
                                <div>
                                    <h3 class="text-sm font-medium text-gray-900">Sección agregada</h3>
                                    <p class="text-xs text-gray-500">Hace 3 días</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- New Role Modal -->
    <div id="roleModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-xl p-6 w-full max-w-md">
            <h2 class="text-xl font-bold text-gray-900 mb-4">Nuevo Rol</h2>
            <form id="roleForm">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nombre del Rol</label>
                        <input type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Descripción</label>
                        <textarea class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" rows="3"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nivel de Acceso</label>
                        <select class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="low">Básico</option>
                            <option value="medium">Intermedio</option>
                            <option value="high">Avanzado</option>
                            <option value="admin">Administrador</option>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" id="cancelRole" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg font-medium hover:bg-gray-50">Cancelar</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Datos de ejemplo para simular la base de datos
            const roles = [
                { id: 1, nombre: 'Administrador' },
                { id: 2, nombre: 'Supervisor' },
                { id: 3, nombre: 'Usuario' },
                { id: 4, nombre: 'Invitado' }
            ];
            
            const modelos = [
                { id: 1, nombre: 'Usuarios' },
                { id: 2, nombre: 'Roles' },
                { id: 3, nombre: 'Tareas' },
                { id: 4, nombre: 'Reportes' },
                { id: 5, nombre: 'Configuración' }
            ];
            
            const permisos = [
                { id: 1, nombre: 'ver', descripcion: 'Permite ver el contenido' },
                { id: 2, nombre: 'crear', descripcion: 'Permite crear nuevos elementos' },
                { id: 3, nombre: 'editar', descripcion: 'Permite modificar elementos existentes' },
                { id: 4, nombre: 'eliminar', descripcion: 'Permite eliminar elementos' }
            ];
            
            // Asignaciones de permisos (simulando permisos_modelos)
            let permisosAsignados = [
                { rol_id: 1, modelo_id: 1, permiso_id: 1 },
                { rol_id: 1, modelo_id: 1, permiso_id: 2 },
                { rol_id: 1, modelo_id: 1, permiso_id: 3 },
                { rol_id: 1, modelo_id: 1, permiso_id: 4 },
                { rol_id: 1, modelo_id: 2, permiso_id: 1 },
                { rol_id: 1, modelo_id: 2, permiso_id: 2 },
                { rol_id: 1, modelo_id: 2, permiso_id: 3 },
                { rol_id: 1, modelo_id: 2, permiso_id: 4 },
                { rol_id: 1, modelo_id: 3, permiso_id: 1 },
                { rol_id: 1, modelo_id: 3, permiso_id: 2 },
                { rol_id: 1, modelo_id: 3, permiso_id: 3 },
                { rol_id: 1, modelo_id: 3, permiso_id: 4 },
                { rol_id: 1, modelo_id: 4, permiso_id: 1 },
                { rol_id: 1, modelo_id: 4, permiso_id: 2 },
                { rol_id: 1, modelo_id: 4, permiso_id: 3 },
                { rol_id: 1, modelo_id: 4, permiso_id: 4 },
                { rol_id: 1, modelo_id: 5, permiso_id: 1 },
                { rol_id: 1, modelo_id: 5, permiso_id: 2 },
                { rol_id: 1, modelo_id: 5, permiso_id: 3 },
                { rol_id: 1, modelo_id: 5, permiso_id: 4 },
                
                { rol_id: 2, modelo_id: 1, permiso_id: 1 },
                { rol_id: 2, modelo_id: 1, permiso_id: 3 },
                { rol_id: 2, modelo_id: 2, permiso_id: 1 },
                { rol_id: 2, modelo_id: 3, permiso_id: 1 },
                { rol_id: 2, modelo_id: 3, permiso_id: 2 },
                { rol_id: 2, modelo_id: 3, permiso_id: 3 },
                { rol_id: 2, modelo_id: 4, permiso_id: 1 },
                { rol_id: 2, modelo_id: 5, permiso_id: 1 },
                
                { rol_id: 3, modelo_id: 1, permiso_id: 1 },
                { rol_id: 3, modelo_id: 3, permiso_id: 1 },
                { rol_id: 3, modelo_id: 3, permiso_id: 2 },
                { rol_id: 3, modelo_id: 3, permiso_id: 3 },
                { rol_id: 3, modelo_id: 4, permiso_id: 1 },
                
                { rol_id: 4, modelo_id: 3, permiso_id: 1 }
            ];

            // Actualizar estadísticas
            function updateStats() {
                document.getElementById('total-roles').textContent = roles.length;
                document.getElementById('total-modelos').textContent = modelos.length;
                document.getElementById('total-permisos').textContent = permisos.length;
                document.getElementById('total-asignaciones').textContent = permisosAsignados.length;
                
                // Calcular porcentajes de permisos
                const totalPossible = roles.length * modelos.length;
                
                const viewCount = permisosAsignados.filter(p => p.permiso_id === 1).length;
                const createCount = permisosAsignados.filter(p => p.permiso_id === 2).length;
                const editCount = permisosAsignados.filter(p => p.permiso_id === 3).length;
                const deleteCount = permisosAsignados.filter(p => p.permiso_id === 4).length;
                
                const viewPercentage = Math.round((viewCount / totalPossible) * 100);
                const createPercentage = Math.round((createCount / totalPossible) * 100);
                const editPercentage = Math.round((editCount / totalPossible) * 100);
                const deletePercentage = Math.round((deleteCount / totalPossible) * 100);
                
                document.getElementById('view-percentage').textContent = `${viewPercentage}%`;
                document.getElementById('create-percentage').textContent = `${createPercentage}%`;
                document.getElementById('edit-percentage').textContent = `${editPercentage}%`;
                document.getElementById('delete-percentage').textContent = `${deletePercentage}%`;
                
                document.getElementById('view-bar').style.width = `${viewPercentage}%`;
                document.getElementById('create-bar').style.width = `${createPercentage}%`;
                document.getElementById('edit-bar').style.width = `${editPercentage}%`;
                document.getElementById('delete-bar').style.width = `${deletePercentage}%`;
            }

            // Renderizar tabla de permisos
            function renderPermissionsTable() {
                const tableBody = document.getElementById('roles-table-body');
                tableBody.innerHTML = '';
                
                roles.forEach(rol => {
                    // Fila del rol
                    const roleRow = document.createElement('tr');
                    roleRow.className = 'role-header';
                    roleRow.setAttribute('onclick', `toggleRole(${rol.id})`);
                    
                    roleRow.innerHTML = `
                        <td class="font-semibold">
                            <div class="flex items-center">
                                <span>${rol.nombre}</span>
                                <svg class="w-4 h-4 ml-2 transition-transform" id="icon-${rol.id}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </div>
                        </td>
                        <td colspan="5" class="text-gray-500">
                            Click para expandir/contraer
                        </td>
                    `;
                    
                    tableBody.appendChild(roleRow);
                    
                    // Secciones del rol
                    modelos.forEach(modelo => {
                        const sectionRow = document.createElement('tr');
                        sectionRow.className = `role-sections role-sections-${rol.id}`;
                        sectionRow.style.display = 'none';
                        
                        let rowContent = `
                            <td></td>
                            <td class="font-medium">${modelo.nombre}</td>
                        `;
                        
                        permisos.forEach(permiso => {
                            const checked = permisosAsignados.some(pa => 
                                pa.rol_id === rol.id && 
                                pa.modelo_id === modelo.id && 
                                pa.permiso_id === permiso.id
                            ) ? 'checked' : '';
                            
                            rowContent += `
                                <td class="checkbox-cell">
                                    <input type="checkbox" 
                                           class="permission-checkbox"
                                           name="permisos[${rol.id}][${modelo.id}][${permiso.id}]" 
                                           value="1" 
                                           ${checked}>
                                </td>
                            `;
                        });
                        
                        sectionRow.innerHTML = rowContent;
                        tableBody.appendChild(sectionRow);
                    });
                });
                
                updateStats();
            }

            // Funcionalidad para expandir/contraer roles
            window.toggleRole = function(roleId) {
                const rows = document.querySelectorAll(`.role-sections-${roleId}`);
                const icon = document.getElementById(`icon-${roleId}`);
                
                rows.forEach(row => {
                    row.style.display = row.style.display === 'none' ? 'table-row' : 'none';
                });
                
                // Rotar ícono
                if (icon) {
                    icon.style.transform = icon.style.transform === 'rotate(180deg)' ? 'rotate(0deg)' : 'rotate(180deg)';
                }
            }

            // Expandir todo
            document.getElementById('expand-all').addEventListener('click', function() {
                roles.forEach(rol => {
                    const rows = document.querySelectorAll(`.role-sections-${rol.id}`);
                    const icon = document.getElementById(`icon-${rol.id}`);
                    
                    rows.forEach(row => {
                        row.style.display = 'table-row';
                    });
                    
                    if (icon) {
                        icon.style.transform = 'rotate(180deg)';
                    }
                });
            });

            // Contraer todo
            document.getElementById('collapse-all').addEventListener('click', function() {
                roles.forEach(rol => {
                    const rows = document.querySelectorAll(`.role-sections-${rol.id}`);
                    const icon = document.getElementById(`icon-${rol.id}`);
                    
                    rows.forEach(row => {
                        row.style.display = 'none';
                    });
                    
                    if (icon) {
                        icon.style.transform = 'rotate(0deg)';
                    }
                });
            });

            // Manejo del formulario
            document.getElementById('asignarPermisosRol').addEventListener('submit', async function(e) {
                e.preventDefault();

                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                
                // Mostrar loading
                submitBtn.innerHTML = '<svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Guardando...';
                submitBtn.disabled = true;

                try {
                    // Simular procesamiento
                    await new Promise(resolve => setTimeout(resolve, 1500));
                    
                    // Actualizar permisosAsignados según los checkboxes
                    permisosAsignados = [];
                    
                    const formData = new FormData(this);
                    const checkboxes = this.querySelectorAll('input[type="checkbox"]:checked');
                    
                    checkboxes.forEach(checkbox => {
                        const name = checkbox.getAttribute('name');
                        const matches = name.match(/permisos\[(\d+)\]\[(\d+)\]\[(\d+)\]/);
                        
                        if (matches) {
                            const rolId = parseInt(matches[1]);
                            const modeloId = parseInt(matches[2]);
                            const permisoId = parseInt(matches[3]);
                            
                            permisosAsignados.push({
                                rol_id: rolId,
                                modelo_id: modeloId,
                                permiso_id: permisoId
                            });
                        }
                    });
                    
                    await Swal.fire({
                        icon: 'success',
                        title: '¡Éxito!',
                        text: 'Los permisos han sido actualizados correctamente.',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    
                    updateStats();

                } catch (error) {
                    console.error('Error:', error);
                    await Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Ocurrió un error al procesar la solicitud.'
                    });
                } finally {
                    // Restaurar botón
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            });

            // Nuevo rol modal
            const newRoleBtn = document.getElementById('new-role-btn');
            const roleModal = document.getElementById('roleModal');
            const cancelRole = document.getElementById('cancelRole');
            const roleForm = document.getElementById('roleForm');
            
            newRoleBtn.addEventListener('click', function() {
                roleModal.classList.remove('hidden');
            });
            
            cancelRole.addEventListener('click', function() {
                roleModal.classList.add('hidden');
            });
            
            roleForm.addEventListener('submit', function(e) {
                e.preventDefault();
                // En una implementación real, aquí se enviaría el formulario al servidor
                Swal.fire({
                    icon: 'success',
                    title: 'Rol creado',
                    text: 'El nuevo rol ha sido creado exitosamente.',
                    timer: 2000,
                    showConfirmButton: false
                });
                roleModal.classList.add('hidden');
                roleForm.reset();
            });

            // Inicializar la tabla
            renderPermissionsTable();
        });
    </script>
</body>
</html>