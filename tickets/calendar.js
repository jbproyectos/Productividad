// ============================================
// CONFIGURACIÓN Y CONSTANTES GLOBALES
// ============================================

const CONFIG = {
    departments: {
        'ti': { name: 'TI', positions: ['Desarrollador', 'Analista de Sistemas', 'Administrador de Red', 'Soporte Técnico', 'Arquitecto de Software'] },
        'ventas': { name: 'Ventas', positions: ['Ejecutivo de Ventas', 'Gerente de Ventas', 'Asesor Comercial', 'Coordinador de Ventas'] },
        'marketing': { name: 'Marketing', positions: ['Especialista en Marketing', 'Community Manager', 'Analista de Mercado', 'Diseñador Gráfico'] },
        'rh': { name: 'Recursos Humanos', positions: ['Reclutador', 'Especialista en Nómina', 'Coordinador de Capacitación', 'Analista de RH'] },
        'contabilidad': { name: 'Contabilidad', positions: ['Contador', 'Auxiliar Contable', 'Analista Financiero', 'Auditor'] }
    },
    holidays: [
        '2023-01-01', '2023-02-05', '2023-03-21', '2023-05-01',
        '2023-09-16', '2023-12-25'
    ],
    months: ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio",
        "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"],
    days: ["Domingo", "Lunes", "Martes", "Miércoles", "Jueves", "Viernes", "Sábado"],

    // Configuración de colores por tipo
    typeColors: {
        ticket: 'green',      // Verde para tickets
        task: 'blue',         // Azul para tareas normales
        meeting: 'purple',    // Púrpura para reuniones
        recurrent_task: 'orange'  // Naranja para tareas cíclicas
    },

    // Estados disponibles
    estadosTicket: ['Pendiente', 'En Proceso', 'Completada', 'Cancelado'],
    estadosTarea: ['Pendiente', 'En Proceso', 'Completada', 'Cancelada']
};

// ============================================
// ESTADO GLOBAL DE LA APLICACIÓN
// ============================================

const AppState = {
    // Fecha actual
    currentDate: new Date(),

    // Navegación
    currentMonth: new Date().getMonth(),
    currentYear: new Date().getFullYear(),
    currentWeek: 0,
    currentDay: new Date().getDate(),

    // Vistas
    currentView: 'month',
    currentMainView: 'calendar',

    // Edición
    editingItemId: null,
    editingAllSimilar: false,
    editingEvent: null,

    // Eventos
    events: [],

    // FILTROS GLOBALES (se aplican a TODAS las vistas)
    filters: {
        puesto: '',
        estado: '',
        asignado: '',
        verTodasAreas: false
    },

    // Usuario actual
    user: null,

    // Cache de usuarios para resolver nombres
    users: [],

    // Inicializar estado
    init(userData, eventsData, usersData = []) {
        this.user = userData;
        this.users = usersData;
        this.events = this.processEvents(eventsData);

        // Por defecto, NO aplicar filtros automáticos
        this.filters = {
            puesto: '',
            estado: '',
            asignado: '',
            verTodasAreas: true // Mostrar todo por defecto
        };

        //console.log('Eventos cargados:', this.events.length);
        //console.log('Usuario:', this.user);
        //console.log('Usuarios cargados:', this.users.length);
    },

    // Procesar eventos
    processEvents(eventsData) {
        return eventsData.map(event => ({
            ...event,
            date: new Date(event.date || event.fecha_creacion)
        }));
    },

    // Obtener nombre de usuario por ID
    getUserNameById(userId) {
        if (!userId) return 'No asignado';

        const user = this.users.find(u => u.Id_Usuario == userId);
        if (user) {
            return `${user.nombre} ${user.apellido || ''}`.trim();
        }
        return `Usuario #${userId}`;
    },

    // Actualizar eventos
    updateEvents(newEvents) {
        this.events = newEvents;
    }
};

// ============================================
// UTILIDADES Y HELPERS
// ============================================

const Utils = {
    // Normalizar texto para comparaciones
    normalizeText(text) {
        if (!text) return '';
        return String(text).toLowerCase()
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .trim();
    },

    // Formatear fecha
    formatDate(date, format = 'iso') {
        if (format === 'iso') {
            return date.toISOString().split('T')[0];
        }
        return date.toLocaleDateString();
    },

    // Verificar si es día festivo
    isHoliday(date) {
        return CONFIG.holidays.includes(this.formatDate(date));
    },

    // Obtener color por estado
    getColorByStatus(status) {
        const colors = {
            'Pendiente': '#eab308',
            'En Proceso': '#3b82f6',
            'Resuelto': '#10b981',
            'Cancelado': '#6b7280',
            'Cancelado': '#ef4444',
            'Completada': '#10b981'
        };
        return colors[status] || '#6b7280';
    },

    // Obtener clase de prioridad
    getPriorityColor(priority) {
        const priorityMap = {
            0: 'text-purple-600',
            2: 'text-red-600',
            7: 'text-yellow-600',
            15: 'text-green-600',
            30: 'text-blue-600',
            60: 'text-indigo-600',
            90: 'text-pink-600',
            180: 'text-orange-600'
        };
        return priorityMap[priority] || 'text-gray-600';
    },

    // Obtener texto de prioridad
    getPriorityText(priority) {
        const priorityMap = {
            0: 'Mismo día',
            2: 'Alto - máx 2 días',
            7: 'Medio - máx 7 días',
            15: 'Bajo - máx 15 días',
            30: 'Act Mensual - máx 30 días',
            60: 'Act Bimestral - máx 60 días',
            90: 'Act Trimestral - máx 90 días',
            180: 'Semestral - máx 180 días',
            365: 'Anual - máx 365 días'
        };
        return priorityMap[priority] || 'Sin definir';
    },

    // Obtener clase de prioridad de tarea
    getTaskPriorityClass(priority) {
        const classMap = {
            'alta': 'bg-red-100 text-red-800',
            'media': 'bg-yellow-100 text-yellow-800',
            'baja': 'bg-green-100 text-green-800'
        };
        return classMap[priority] || 'bg-gray-100 text-gray-800';
    },

    // Generar color consistente para strings
    getConsistentColor(str) {
        const colors = ['red', 'yellow', 'blue', 'indigo', 'purple', 'pink', 'green', 'gray'];
        const hash = Array.from(String(str)).reduce((acc, char) => acc + char.charCodeAt(0), 0);
        return colors[hash % colors.length];
    },

    // Generar URL de WhatsApp
    generateWhatsAppUrl(event) {
        const telefono = event.db_data?.whatsapp?.replace(/\D/g, '');
        if (!telefono) return '#';

        const folio = event.db_data?.folio || 'Sin folio';
        const descripcion = event.db_data?.descripcion || event.description || 'Sin descripción';
        const fecha = event.db_data?.fecha_creacion || 'No disponible';
        const estado = event.db_data?.estado || 'Pendiente';

        const mensaje = `Hola, soy del área de soporte.\n\n` +
            `Te contacto respecto al ticket *${folio}*\n` +
            `_Asunto:_ ${descripcion}\n\n` +
            `Podrías brindarme más detalles o apoyo para continuar con la atención del ticket.\n\n` +
            `*Fecha de creación:* ${fecha}\n` +
            `*Estado actual:* ${estado}\n\n` +
            `Gracias por tu tiempo`;

        return `https://wa.me/${telefono}?text=${encodeURIComponent(mensaje)}`;
    },

    // Obtener valores únicos para filtros
    getUniqueFilterValues() {
        const values = {
            puestos: new Set(),
            estados: new Set(),
            asignados: new Map() // id -> nombre
        };

        AppState.events.forEach(event => {
            // Puestos
            if (event.db_data?.puesto) {
                values.puestos.add(event.db_data.puesto);
            } else if (event.puesto) {
                values.puestos.add(event.puesto);
            }

            // Estados (unificar)
            const estado = event.estatus || event.status || event.db_data?.estado;
            if (estado) {
                values.estados.add(estado);
            }

            // Asignados (por ID o nombre)
            if (event.db_data?.id_usuario_creador) {
                const nombre = AppState.getUserNameById(event.db_data.id_usuario_creador);
                values.asignados.set(event.db_data.id_usuario_creador, nombre);
            } else if (event.id_responsable_ejecucion) {
                const nombre = AppState.getUserNameById(event.id_responsable_ejecucion);
                values.asignados.set(event.id_responsable_ejecucion, nombre);
            } else if (event.db_data?.asignado_a_nombre) {
                values.asignados.set(event.db_data.asignado_a_nombre, event.db_data.asignado_a_nombre);
            }
        });

        return values;
    },

    // APLICAR FILTROS GLOBALES a cualquier conjunto de eventos
    filterEvents(events) {
        const { puesto, estado, asignado, verTodasAreas } = AppState.filters;

        // Si "ver todas las áreas" está activo, mostrar todo
        if (verTodasAreas) {
            return events;
        }

        // Si no hay filtros aplicados, mostrar todo
        if (!puesto && !estado && !asignado) {
            return events;
        }

        return events.filter(event => {
            let passes = true;

            // Filtro de puesto
            if (puesto && passes) {
                const eventPuesto = event.db_data?.puesto || event.puesto || '';
                passes = passes && eventPuesto &&
                    this.normalizeText(eventPuesto) === this.normalizeText(puesto);
            }

            // Filtro de estado
            if (estado && passes) {
                const eventEstado = event.estatus || event.status || event.db_data?.estado || '';
                passes = passes && this.normalizeText(eventEstado) === this.normalizeText(estado);
            }

            // Filtro de asignado
            if (asignado && passes) {
                // Buscar por ID o por nombre
                let eventAsignado = '';
                if (event.db_data?.id_usuario_creador) {
                    eventAsignado = String(event.db_data.id_usuario_creador);
                } else if (event.id_responsable_ejecucion) {
                    eventAsignado = String(event.id_responsable_ejecucion);
                } else if (event.db_data?.asignado_a_nombre) {
                    eventAsignado = event.db_data.asignado_a_nombre;
                }

                passes = passes && this.normalizeText(eventAsignado) === this.normalizeText(asignado);
            }

            return passes;
        });
    }
};

// ============================================
// MANEJADOR DE FILTROS GLOBALES
// ============================================

const FilterManager = {
    // Inicializar barra de filtros global
    init() {
        this.renderFilterBar();
    },

    renderFilterBar() {
        // Buscar donde insertar los filtros
        const targetContainer = document.querySelector('.calendar-header') ||
            document.querySelector('.flex.justify-between.items-center.mb-6');

        if (!targetContainer) {
            setTimeout(() => this.init(), 200);
            return;
        }

        // Evitar duplicados
        if (document.getElementById('global-filters')) return;

        const values = Utils.getUniqueFilterValues();

        const filterBar = document.createElement('div');
        filterBar.id = 'global-filters';
        filterBar.className = 'bg-white p-4 rounded-lg shadow-sm border border-gray-200 mb-4';
        filterBar.innerHTML = `
            <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 flex-1">
                    <!-- Puesto -->
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Filtrar por Puesto</label>
                        <select id="filter-puesto" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            <option value="">Todos los puestos</option>
                            ${Array.from(values.puestos).map(p =>
            `<option value="${p}">${p}</option>`
        ).join('')}
                        </select>
                    </div>
                    
                    <!-- Estado -->
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Filtrar por Estado</label>
                        <select id="filter-estado" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            <option value="">Todos los estados</option>
                            ${Array.from(values.estados).map(e =>
            `<option value="${e}">${e}</option>`
        ).join('')}
                        </select>
                    </div>
                    
                    <!-- Asignado -->
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Asignado a</label>
                        <select id="filter-asignado" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            <option value="">Todos</option>
                            ${Array.from(values.asignados.entries()).map(([id, nombre]) =>
            `<option value="${id}">${nombre}</option>`
        ).join('')}
                        </select>
                    </div>
                    
                    <!-- Ver todas las áreas -->
                    <div class="flex items-center pt-6">
                        <label class="flex items-center space-x-2 cursor-pointer">
                            <input type="checkbox" id="filter-ver-todas" class="rounded text-blue-600" checked>
                            <span class="text-sm text-gray-700">Ver todas las áreas</span>
                        </label>
                    </div>
                </div>
                
                <div class="flex gap-2">
                    <button id="apply-filters" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm whitespace-nowrap">
                        Aplicar Filtros
                    </button>
                    <button id="clear-filters" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 text-sm whitespace-nowrap">
                        Limpiar
                    </button>
                </div>
            </div>
            
            <div id="filter-stats" class="mt-3 text-xs text-gray-500 border-t pt-2">
                Cargando estadísticas...
            </div>
        `;

        // Insertar después del header
        targetContainer.parentNode.insertBefore(filterBar, targetContainer.nextSibling);

        // Estado inicial: "ver todas las áreas" activado
        document.getElementById('filter-ver-todas').checked = true;
        ['filter-puesto', 'filter-estado', 'filter-asignado'].forEach(id => {
            document.getElementById(id).disabled = true;
        });

        // Event listeners
        document.getElementById('apply-filters').addEventListener('click', () => this.applyFilters());
        document.getElementById('clear-filters').addEventListener('click', () => this.clearFilters());

        document.getElementById('filter-ver-todas').addEventListener('change', (e) => {
            const disabled = e.target.checked;
            ['filter-puesto', 'filter-estado', 'filter-asignado'].forEach(id => {
                document.getElementById(id).disabled = disabled;
            });
        });

        // Actualizar estadísticas
        this.updateStats();
    },

    applyFilters() {
        const verTodas = document.getElementById('filter-ver-todas').checked;

        if (verTodas) {
            AppState.filters = {
                puesto: '',
                estado: '',
                asignado: '',
                verTodasAreas: true
            };
        } else {
            AppState.filters = {
                puesto: document.getElementById('filter-puesto').value,
                estado: document.getElementById('filter-estado').value,
                asignado: document.getElementById('filter-asignado').value,
                verTodasAreas: false
            };
        }

        //console.log('Filtros globales aplicados:', AppState.filters);
        this.updateStats();
        ViewManager.refreshCurrentView();
    },

    clearFilters() {
        document.getElementById('filter-puesto').value = '';
        document.getElementById('filter-estado').value = '';
        document.getElementById('filter-asignado').value = '';
        document.getElementById('filter-ver-todas').checked = true;

        ['filter-puesto', 'filter-estado', 'filter-asignado'].forEach(id => {
            document.getElementById(id).disabled = true;
        });

        AppState.filters = {
            puesto: '',
            estado: '',
            asignado: '',
            verTodasAreas: true
        };

        console.log('Filtros globales limpiados');
        this.updateStats();
        ViewManager.refreshCurrentView();
    },

    updateStats() {
        const container = document.getElementById('filter-stats');
        if (!container) return;

        const total = AppState.events.length;
        const filtered = Utils.filterEvents(AppState.events).length;
        const percent = total > 0 ? Math.round((filtered / total) * 100) : 0;

        let filterInfo = [];
        if (!AppState.filters.verTodasAreas) {
            if (AppState.filters.puesto) filterInfo.push(`Puesto: ${AppState.filters.puesto}`);
            if (AppState.filters.estado) filterInfo.push(`Estado: ${AppState.filters.estado}`);
            if (AppState.filters.asignado) {
                // Intentar mostrar nombre en lugar de ID
                const values = Utils.getUniqueFilterValues();
                const nombre = values.asignados.get(AppState.filters.asignado) || AppState.filters.asignado;
                filterInfo.push(`Asignado: ${nombre}`);
            }
        }

        container.innerHTML = `
            <div class="flex justify-between items-center">
                <span>
                    <i class="fas fa-chart-bar mr-1"></i>
                    Mostrando <strong>${filtered}</strong> de <strong>${total}</strong> eventos (${percent}%)
                </span>
                ${filterInfo.length > 0 ? `
                    <span class="text-gray-600">
                        <i class="fas fa-filter mr-1"></i>
                        ${filterInfo.join(' • ')}
                    </span>
                ` : ''}
                ${AppState.filters.verTodasAreas ? `
                    <span class="text-blue-600">
                        <i class="fas fa-globe mr-1"></i>
                        Mostrando todas las áreas
                    </span>
                ` : ''}
            </div>
        `;
    }
};

// ============================================
// MANEJADOR DE ALERTAS Y NOTIFICACIONES
// ============================================

const AlertManager = {
    show(message, type = 'info') {
        if (typeof Swal !== 'undefined') {
            this.showSweetAlert(message, type);
        } else {
            this.showFallbackAlert(message, type);
        }
    },

    showSweetAlert(message, type) {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });

        Toast.fire({
            icon: type,
            title: message
        });
    },

    showFallbackAlert(message, type) {
        const alertDiv = document.createElement('div');
        const typeConfig = {
            success: 'bg-green-100 text-green-700 border-green-400',
            error: 'bg-red-100 text-red-700 border-red-400',
            warning: 'bg-yellow-100 text-yellow-700 border-yellow-400',
            info: 'bg-blue-100 text-blue-700 border-blue-400'
        };

        alertDiv.className = `fixed top-4 right-4 z-50 px-4 py-3 rounded-lg shadow-lg border ${typeConfig[type] || typeConfig.info}`;
        alertDiv.innerHTML = `
            <div class="flex items-center">
                <i class="fas fa-${this.getIcon(type)} mr-2"></i>
                <span>${message}</span>
            </div>
        `;

        document.body.appendChild(alertDiv);
        setTimeout(() => alertDiv.remove(), 3000);
    },

    getIcon(type) {
        const icons = {
            success: 'check-circle',
            error: 'exclamation-circle',
            warning: 'exclamation-triangle',
            info: 'info-circle'
        };
        return icons[type] || 'info-circle';
    },

    confirm(options) {
        return Swal.fire({
            title: options.title || 'Confirmar',
            text: options.text || '¿Estás seguro?',
            icon: options.icon || 'question',
            showCancelButton: true,
            confirmButtonText: options.confirmText || 'Sí',
            cancelButtonText: options.cancelText || 'Cancelar',
            ...options
        });
    }
};

// ============================================
// MANEJADOR DE EVENTOS DEL CALENDARIO
// ============================================

const EventManager = {
    // Obtener eventos para una fecha específica (CON FILTROS)
    getEventsForDate(date) {
        const dayEvents = AppState.events.filter(event => {
            if (!event?.date) return false;
            const eventDate = new Date(event.date);
            return eventDate.getDate() === date.getDate() &&
                eventDate.getMonth() === date.getMonth() &&
                eventDate.getFullYear() === date.getFullYear();
        });

        // Aplicar filtros globales
        return Utils.filterEvents(dayEvents);
    },

    // Obtener eventos para fecha y hora específica
    getEventsForDateAndHour(date, hour) {
        return this.getEventsForDate(date).filter(event => {
            if (!event.time) return false;
            return parseInt(event.time.split(':')[0]) === hour;
        });
    },

    // Obtener valores únicos para filtros (ya está en Utils)

    // Actualizar evento en calendario (para FullCalendar)
    updateEventInCalendar(type, id, newStatus) {
        if (typeof calendar !== 'undefined' && calendar) {
            const events = calendar.getEvents();
            const evento = events.find(e => {
                if (e.extendedProps.type === type) {
                    if (type === 'ticket') {
                        return e.extendedProps.db_data?.folio === id;
                    }
                    return e.extendedProps.id === id;
                }
                return false;
            });

            if (evento) {
                if (type === 'ticket') {
                    evento.setExtendedProp('db_data', {
                        ...evento.extendedProps.db_data,
                        estado: newStatus
                    });
                } else {
                    evento.setExtendedProp('status', newStatus);
                }

                const color = Utils.getColorByStatus(newStatus);
                evento.setProp('backgroundColor', color);
                evento.setProp('borderColor', color);
                calendar.refetchEvents();
            }
        }
    },

    // Encontrar eventos similares
    findSimilarEvents(event) {
        return AppState.events.filter(e =>
            e.type === event.type &&
            e.puesto === event.puesto &&
            e.title === event.title &&
            e.description === event.description
        );
    }
};

// ============================================
// MANEJADOR DE VISTAS (MODIFICADO PARA USAR FILTROS GLOBALES)
// ============================================

const ViewManager = {
    // Cambiar vista
    switchView(view) {
        AppState.currentView = view;

        // Ocultar todas las vistas
        document.querySelectorAll('.calendar-view').forEach(v => v.classList.add('hidden'));

        // Mostrar vista seleccionada
        const targetView = document.getElementById(`${view}-view`);
        if (targetView) targetView.classList.remove('hidden');

        // Actualizar título
        const titles = {
            'month': 'Calendario Mensual',
            'week': 'Calendario Semanal',
            'day': 'Vista Diaria'
        };

        const viewTitle = document.getElementById('view-title');
        if (viewTitle) viewTitle.textContent = titles[view];

        // Renderizar vista correspondiente
        this.refreshCurrentView();
    },

    // Refrescar vista actual (usando filtros globales)
    refreshCurrentView() {
        switch (AppState.currentView) {
            case 'month': this.renderMonthView(); break;
            case 'week': this.renderWeekView(); break;
            case 'day': this.renderDayView(); break;
        }
        this.updateHeader();
        FilterManager.updateStats();
    },

    // Navegar entre periodos
    navigate(direction) {
        const { currentView } = AppState;

        if (currentView === 'month') {
            AppState.currentMonth += direction;
            this.adjustMonthYear();
            this.renderMonthView();
        } else if (currentView === 'week') {
            AppState.currentWeek += direction;
            this.renderWeekView();
        } else if (currentView === 'day') {
            this.navigateDay(direction);
        }

        this.updateHeader();
        FilterManager.updateStats();
    },

    adjustMonthYear() {
        if (AppState.currentMonth < 0) {
            AppState.currentMonth = 11;
            AppState.currentYear--;
        } else if (AppState.currentMonth > 11) {
            AppState.currentMonth = 0;
            AppState.currentYear++;
        }
    },

    navigateDay(direction) {
        AppState.currentDay += direction;
        const daysInMonth = new Date(AppState.currentYear, AppState.currentMonth + 1, 0).getDate();

        if (AppState.currentDay < 1) {
            AppState.currentMonth--;
            this.adjustMonthYear();
            AppState.currentDay = new Date(AppState.currentYear, AppState.currentMonth + 1, 0).getDate();
        } else if (AppState.currentDay > daysInMonth) {
            AppState.currentDay = 1;
            AppState.currentMonth++;
            this.adjustMonthYear();
        }

        this.renderDayView();
    },

    updateHeader() {
        const header = document.getElementById('current-period');
        if (!header) return;

        const { currentView, currentMonth, currentYear, currentWeek, currentDay } = AppState;

        if (currentView === 'month') {
            header.textContent = `${CONFIG.months[currentMonth]} ${currentYear}`;
        } else if (currentView === 'week') {
            const weekStart = new Date(currentYear, currentMonth, currentWeek * 7 + 1);
            const weekEnd = new Date(currentYear, currentMonth, currentWeek * 7 + 7);
            header.textContent = `Semana ${currentWeek + 1} - ${weekStart.getDate()} al ${weekEnd.getDate()} de ${CONFIG.months[currentMonth]}`;
        } else if (currentView === 'day') {
            const date = new Date(currentYear, currentMonth, currentDay);
            header.textContent = `${CONFIG.days[date.getDay()]}, ${currentDay} de ${CONFIG.months[currentMonth]} ${currentYear}`;
        }
    },

    // ============================================
    // VISTA MENSUAL (MODIFICADA PARA USAR FILTROS)
    // ============================================

    renderMonthView() {
        const container = document.getElementById('month-days');
        if (!container) return;

        container.innerHTML = '';

        const firstDay = new Date(AppState.currentYear, AppState.currentMonth, 1).getDay();
        const daysInMonth = new Date(AppState.currentYear, AppState.currentMonth + 1, 0).getDate();
        const startDay = firstDay === 0 ? 6 : firstDay - 1;

        // Días vacíos
        for (let i = 0; i < startDay; i++) {
            container.appendChild(this.createEmptyDayCell());
        }

        // Días del mes
        for (let day = 1; day <= daysInMonth; day++) {
            const date = new Date(AppState.currentYear, AppState.currentMonth, day);
            container.appendChild(this.createDayCell(day, date));
        }
    },

    createEmptyDayCell() {
        const cell = document.createElement('div');
        cell.className = 'min-h-20 lg:min-h-24 p-1 lg:p-2 border border-gray-200 rounded-lg bg-gray-50';
        return cell;
    },

    createDayCell(day, date) {
        const cell = document.createElement('div');
        cell.className = 'min-h-20 lg:min-h-24 p-1 lg:p-2 border border-gray-200 rounded-lg hover:border-blue-300 transition-colors';

        // Aplicar estilos condicionales
        this.applyDayCellStyles(cell, date);

        // Número del día
        cell.appendChild(this.createDayNumber(day, date));

        // Eventos del día (CON FILTROS APLICADOS)
        const dayEvents = EventManager.getEventsForDate(date);
        if (dayEvents.length > 0) {
            cell.appendChild(this.createEventIndicators(dayEvents));
        }

        return cell;
    },

    applyDayCellStyles(cell, date) {
        const isToday = date.getDate() === AppState.currentDate.getDate() &&
            date.getMonth() === AppState.currentDate.getMonth() &&
            date.getFullYear() === AppState.currentDate.getFullYear();

        if (isToday) cell.classList.add('bg-blue-50', 'border-blue-300');
        if (Utils.isHoliday(date)) cell.classList.add('bg-red-50', 'border-red-200');
    },

    createDayNumber(day, date) {
        const dayNumber = document.createElement('div');
        dayNumber.className = 'font-medium text-gray-900 mb-1 flex justify-between items-center text-sm lg:text-base';

        const isToday = day === AppState.currentDate.getDate() &&
            AppState.currentMonth === AppState.currentDate.getMonth() &&
            AppState.currentYear === AppState.currentDate.getFullYear();

        if (isToday) dayNumber.classList.add('text-blue-600');
        if (Utils.isHoliday(date)) dayNumber.classList.add('text-red-600');

        dayNumber.innerHTML = `
            <span>${day}</span>
            ${Utils.isHoliday(date) ? '<i class="fas fa-star text-red-500 text-xs"></i>' : ''}
        `;

        return dayNumber;
    },

    createEventIndicators(events) {
        const container = document.createElement('div');
        container.className = 'space-y-1';

        events.slice(0, 2).forEach(event => {
            container.appendChild(this.createEventIndicator(event));
        });

        if (events.length > 2) {
            container.appendChild(this.createMoreIndicator(events.length - 2));
        }

        return container;
    },

    createEventIndicator(event) {

        const indicator = document.createElement('div');
        indicator.className = 'text-xs flex items-center space-x-1 event-indicator p-1 rounded cursor-pointer hover:bg-gray-100';
        indicator.addEventListener('click', (e) => {
            e.stopPropagation();
            ModalManager.showEventDetails(event);
        });

        const dot = document.createElement('div');
        dot.className = `w-2 h-2 rounded-full flex-shrink-0 bg-${CONFIG.typeColors[event.type]}-500`;

        const text = document.createElement('span');
        text.className = 'truncate text-gray-600 flex-1';
        text.textContent = (event.description || event.description || 'Sin título').substring(0, 10) + '...';

        indicator.appendChild(dot);
        indicator.appendChild(text);


        return indicator;
    },

    createMoreIndicator(count) {
        const indicator = document.createElement('div');
        indicator.className = 'text-xs text-gray-500 p-1';
        indicator.textContent = `+${count} más`;
        return indicator;
    },

    // ============================================
    // VISTA SEMANAL (MODIFICADA PARA USAR FILTROS)
    // ============================================

    renderWeekView() {
        this.updateWeekDays();
        this.renderWeekHours();
    },

    updateWeekDays() {
        for (let i = 0; i < 7; i++) {
            const day = new Date(AppState.currentYear, AppState.currentMonth, AppState.currentWeek * 7 + i + 1);
            const dayElement = document.getElementById(`week-day-${i}`);

            if (dayElement) {
                const dayName = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'][day.getDay()];
                dayElement.textContent = `${dayName} ${day.getDate()}`;

                if (Utils.isHoliday(day)) {
                    dayElement.classList.add('text-red-600');
                } else {
                    dayElement.classList.remove('text-red-600');
                }
            }
        }
    },

    renderWeekHours() {
        const container = document.getElementById('week-hours');
        if (!container) return;

        container.innerHTML = '';

        for (let hour = 6; hour <= 22; hour++) {
            container.appendChild(this.createHourRow(hour));
        }
    },

    createHourRow(hour) {
        const row = document.createElement('div');
        row.className = 'grid grid-cols-8 gap-1 lg:gap-2 mb-2';

        // Etiqueta de hora
        const label = document.createElement('div');
        label.className = 'text-xs lg:text-sm text-gray-500 py-2';
        label.textContent = `${hour}:00`;
        row.appendChild(label);

        // Celdas para cada día
        for (let day = 0; day < 7; day++) {
            const date = new Date(AppState.currentYear, AppState.currentMonth, AppState.currentWeek * 7 + day + 1);
            date.setHours(hour);
            row.appendChild(this.createHourCell(date, hour));
        }

        return row;
    },

    createHourCell(date, hour) {
        const cell = document.createElement('div');
        cell.className = 'min-h-10 lg:min-h-12 border border-gray-200 rounded p-1';

        if (Utils.isHoliday(date)) {
            cell.classList.add('bg-red-50', 'border-red-200');
        }

        const hourEvents = EventManager.getEventsForDateAndHour(date, hour);
        hourEvents.forEach(event => {
            cell.appendChild(this.createHourEvent(event));
        });

        return cell;
    },

    createHourEvent(event) {
        const element = document.createElement('div');
        element.className = `text-xs p-1 rounded mb-1 text-white truncate cursor-pointer bg-${CONFIG.typeColors[event.type]}-500`;
        element.addEventListener('click', () => ModalManager.showEventDetails(event));
        element.textContent = (event.description || event.description || 'Evento').substring(0, 15) + '...';
        return element;
    },

    // ============================================
    // VISTA DIARIA (MODIFICADA PARA USAR FILTROS)
    // ============================================

    renderDayView() {
        const title = document.getElementById('day-title');
        const container = document.getElementById('day-events');

        if (!title || !container) return;

        const date = new Date(AppState.currentYear, AppState.currentMonth, AppState.currentDay);
        title.textContent = `${CONFIG.days[date.getDay()]}, ${AppState.currentDay} de ${CONFIG.months[AppState.currentMonth]} ${AppState.currentYear}`;

        this.renderHolidayNotice(date, container);

        const dayEvents = EventManager.getEventsForDate(date);

        if (dayEvents.length === 0) {
            this.renderEmptyDay(container);
            return;
        }

        this.renderDayEvents(dayEvents.sort((a, b) => (a.time || '00:00').localeCompare(b.time || '00:00')), container);
    },

    renderHolidayNotice(date, container) {
        if (Utils.isHoliday(date)) {
            const notice = document.createElement('div');
            notice.className = 'bg-red-50 border border-red-200 rounded-lg p-4 mb-4';
            notice.innerHTML = `
                <div class="flex items-center space-x-2 text-red-800">
                    <i class="fas fa-star"></i>
                    <span class="font-medium">Día festivo</span>
                </div>
                <p class="text-sm text-red-600 mt-1">Este día es considerado festivo. Las tareas programadas pueden requerir aprobación especial.</p>
            `;
            container.appendChild(notice);
        }
    },

    renderEmptyDay(container) {
        container.innerHTML = `
            <div class="text-center py-8 lg:py-12 text-gray-500">
                <i class="fas fa-calendar-day text-2xl lg:text-4xl mb-4"></i>
                <p class="text-base lg:text-lg">No hay eventos programados para este día</p>
                <p class="text-xs lg:text-sm">Haz clic en "Nuevo elemento" para agregar uno</p>
            </div>
        `;
    },

    renderDayEvents(events, container) {
        container.innerHTML = '';

        events.forEach(event => {
            container.appendChild(this.createDayEventElement(event));
        });
    },

    createDayEventElement(event) {
        const element = document.createElement('div');
        element.className = 'flex items-start space-x-3 lg:space-x-4 p-3 lg:p-4 border border-gray-200 rounded-lg mb-3 cursor-pointer hover:bg-gray-50';
        element.addEventListener('click', () => ModalManager.showEventDetails(event));

        element.appendChild(this.createEventIcon(event));
        element.appendChild(this.createEventContent(event));

        return element;
    },

    createEventIcon(event) {
        const icon = document.createElement('div');
        icon.className = `w-8 h-8 lg:w-10 lg:h-10 rounded-full flex items-center justify-center text-white flex-shrink-0 bg-${CONFIG.typeColors[event.type]}-500`;

        const icons = {
            ticket: 'fa-ticket-alt',
            task: 'fa-tasks',
            meeting: 'fa-users',
            recurrent_task: 'fa-redo-alt'
        };

        icon.innerHTML = `<i class="fas ${icons[event.type]} text-sm lg:text-base"></i>`;
        return icon;
    },

    createEventContent(event) {
        const content = document.createElement('div');
        content.className = 'flex-1';

        // Header
        const header = document.createElement('div');
        header.className = 'flex flex-col lg:flex-row lg:justify-between lg:items-start mb-2';

        const title = document.createElement('h4');
        title.className = 'font-medium text-gray-900 text-sm lg:text-base mb-1 lg:mb-0';
        title.textContent = event.description || event.description || 'Sin título';

        const time = document.createElement('span');
        time.className = 'text-xs lg:text-sm text-gray-500';
        time.textContent = event.time || 'Todo el día';
        console.log(event.time);
        

        header.appendChild(title);
        header.appendChild(time);
        content.appendChild(header);

        // Detalles específicos según tipo
        if (event.type === 'ticket') {
            content.appendChild(this.createTicketDetails(event));
        }

        // === BADGES PARA TICKETS, TAREAS Y TAREAS CÍCLICAS ===
        if (event.type === 'task' || event.type === 'ticket' || event.type === 'recurrent_task') {
            const badgesContainer = document.createElement('div');
            badgesContainer.className = 'flex flex-wrap items-center gap-1.5 mt-1';

            // 1. Prioridad
            const prioridad = event.priority || event.db_data?.prioridad;
            if (prioridad) {
                let prioridadClass = '';
                let prioridadIcon = '';
                let prioridadTexto = prioridad;

                if (prioridad === 'alta' || prioridad === 'Alta' || prioridad === '2') {
                    prioridadClass = 'bg-red-50 text-red-700';
                    prioridadIcon = 'fa-bolt';
                    prioridadTexto = 'Alta';
                } else if (prioridad === 'media' || prioridad === 'Media' || prioridad === '7') {
                    prioridadClass = 'bg-yellow-50 text-yellow-700';
                    prioridadIcon = 'fa-chart-line';
                    prioridadTexto = 'Media';
                } else if (prioridad === 'baja' || prioridad === 'Baja' || prioridad === '15') {
                    prioridadClass = 'bg-green-50 text-green-700';
                    prioridadIcon = 'fa-arrow-down';
                    prioridadTexto = 'Baja';
                } else {
                    prioridadClass = 'bg-gray-50 text-gray-700';
                    prioridadIcon = 'fa-flag';
                }

                const badge = document.createElement('span');
                badge.className = `inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${prioridadClass}`;
                badge.innerHTML = `<i class="fas ${prioridadIcon} mr-1"></i>${prioridadTexto}`;
                badgesContainer.appendChild(badge);
            }

            // 2. Rubro (para tareas)
            const rubro = event.db_data?.rubro || event.rubro;
            if (rubro) {
                const badge = document.createElement('span');
                badge.className = 'inline-flex items-center px-2 py-0.5 bg-purple-50 text-purple-700 rounded-full text-xs font-medium';
                badge.innerHTML = `<i class="fas fa-tasks mr-1"></i>${rubro}`;
                badgesContainer.appendChild(badge);
            }

            // 3. Tipo de trabajo (para tareas)
            const tipoTrabajo = event.db_data?.tipo_trabajo || event.tipo_trabajo;
            if (tipoTrabajo) {
                let icon = 'fa-users';
                if (tipoTrabajo === 'individual') icon = 'fa-user';
                else if (tipoTrabajo === 'colaboracion') icon = 'fa-handshake';

                const badge = document.createElement('span');
                badge.className = 'inline-flex items-center px-2 py-0.5 bg-blue-50 text-blue-700 rounded-full text-xs font-medium';
                badge.innerHTML = `<i class="fas ${icon} mr-1"></i>${tipoTrabajo}`;
                badgesContainer.appendChild(badge);
            }

            // 4. Categoría
            const categoria = event.categoria || event.db_data?.categoria_nombre || event.db_data?.categoria;
            if (categoria) {
                const badge = document.createElement('span');
                badge.className = 'inline-flex items-center px-2 py-0.5 bg-indigo-50 text-indigo-700 rounded-full text-xs font-medium';
                badge.innerHTML = `<i class="fas fa-folder mr-1"></i>${categoria}`;
                badgesContainer.appendChild(badge);
            }

            // 5. Subcategoría
            const subcategoria = event.subcategoria || event.db_data?.subcategoria_nombre || event.db_data?.subcategoria;
            if (subcategoria) {
                const badge = document.createElement('span');
                badge.className = 'inline-flex items-center px-2 py-0.5 bg-teal-50 text-teal-700 rounded-full text-xs font-medium';
                badge.innerHTML = `<i class="fas fa-tag mr-1"></i>${subcategoria}`;
                badgesContainer.appendChild(badge);
            }

            // 6. Área
            const area = event.area || event.db_data?.area_nombre || event.db_data?.area;
            if (area) {
                const badge = document.createElement('span');
                badge.className = 'inline-flex items-center px-2 py-0.5 bg-gray-50 text-gray-700 rounded-full text-xs font-medium';
                badge.innerHTML = `<i class="fas fa-building mr-1"></i>${area}`;
                badgesContainer.appendChild(badge);
            }

            // 7. Ubicación (donde + detalle)
            const donde = [];
            if (event.db_data?.donde_nombre || event.donde) donde.push(event.db_data?.donde_nombre || event.donde);
            if (event.db_data?.detalle_donde_nombre || event.detalle_donde) donde.push(event.db_data?.detalle_donde_nombre || event.detalle_donde);

            if (donde.length > 0) {
                const badge = document.createElement('span');
                badge.className = 'inline-flex items-center px-2 py-0.5 bg-orange-50 text-orange-700 rounded-full text-xs font-medium';
                badge.innerHTML = `<i class="fas fa-map-marker-alt mr-1"></i>${donde.join(' - ')}`;
                badgesContainer.appendChild(badge);
            }

            // Solo agregar si hay badges
            if (badgesContainer.children.length > 0) {
                content.appendChild(badgesContainer);
            }
        }
        // === FIN DE BADGES ===

        // === ASIGNADO SOLO PARA TAREAS CÍCLICAS (se mantiene igual que antes) ===
        if (event.type === 'recurrent_task') {
            if (event.db_data?.asignado_a_nombre || event.db_data?.puesto || event.puesto) {
                content.appendChild(this.createAssignmentDetails(event));
            }
        }

        return content;
    },

    createTicketDetails(event) {
        const details = document.createElement('div');
        details.className = 'text-xs lg:text-sm text-gray-600 space-y-1';

        details.innerHTML = `
            <div class="flex items-center space-x-2">
                <span class="font-medium">Área:</span>
                <span class="bg-gray-100 px-2 py-1 rounded text-xs">${event.area || 'Sin área'}</span>
                <span class="font-medium">Prioridad:</span>
                <span class="bg-${this.getPriorityBg(event.priority || event.prioridad)}-100 text-${this.getPriorityBg(event.priority || event.prioridad)}-800 px-2 py-1 rounded text-xs">${event.priority || event.prioridad || 'Normal'}</span>
            </div>
        `;

        return details;
    },

    getPriorityBg(priority) {
        if (priority === 'alta' || priority === 'urgente') return 'red';
        if (priority === 'media') return 'yellow';
        return 'green';
    },

    createAssignmentDetails(event) {
        const details = document.createElement('div');
        details.className = 'text-xs lg:text-sm text-gray-600 space-y-1';

        const puesto = event.db_data?.puesto || event.puesto || 'Sin puesto';
        const estado = event.estatus || event.status || event.db_data?.estado || 'Sin estado';

        const puestoColor = Utils.getConsistentColor(puesto);
        const estadoColor = Utils.getConsistentColor(estado);

        // Obtener nombre del responsable
        let responsable = 'No asignado';
        if (event.db_data?.asignado_a_nombre) {
            responsable = event.db_data.asignado_a_nombre;
        } else if (event.id_responsable_ejecucion) {
            responsable = AppState.getUserNameById(event.id_responsable_ejecucion);
        } else if (event.db_data?.id_usuario_creador) {
            responsable = AppState.getUserNameById(event.db_data.id_usuario_creador);
        }

        details.innerHTML = `
            <div class="flex items-center space-x-2 flex-wrap gap-1">
                <span class="font-medium">Puesto:</span>
                <span class="bg-${puestoColor}-100 text-${puestoColor}-800 px-2 py-1 rounded text-xs">${puesto}</span>
                <span class="font-medium">Estado:</span>
                <span class="bg-${estadoColor}-100 text-${estadoColor}-800 px-2 py-1 rounded text-xs">${estado}</span>        
                <span class="font-medium">Asignado a:</span>
                <span class="bg-${estadoColor}-100 text-${estadoColor}-800 px-2 py-1 rounded text-xs">${responsable}</span>
            </div>
        `;

        return details;
    }
};

// ============================================
// MANEJADOR DE MODALES (COMPLETO, SIN CAMBIOS)
// ============================================

const ModalManager = {
    open() {
        document.getElementById('item-modal').classList.remove('hidden');
        document.getElementById('type-selection').classList.remove('hidden');
        this.showForm('ticket');
        AppState.editingItemId = null;
    },

    close() {
        document.getElementById('item-modal').classList.add('hidden');
    },

    closeDetail() {
        document.getElementById('detail-modal').classList.add('hidden');
        AppState.editingItemId = null;
        AppState.editingEvent = null;
    },

    showForm(type) {
        document.querySelectorAll('.item-form').forEach(form => form.classList.add('hidden'));
        document.getElementById(`${type}-form`).classList.remove('hidden');

        const titles = {
            'ticket': 'Nuevo Ticket',
            'task': 'Nueva Tarea',
            'meeting': 'Nueva Reunión'
        };
        document.getElementById('modal-title').textContent = titles[type];
    },

    showEventDetails(event) {
        AppState.editingEvent = event;
        document.getElementById('detail-title').textContent = 'Detalles del elemento';

        const content = this.generateDetailContent(event);
        const footer = this.generateDetailFooter(event);

        document.getElementById('detail-content').innerHTML = content;

        const footerElement = document.getElementById('detail-footer');
        if (footerElement) {
            footerElement.innerHTML = footer;
            this.attachDetailEvents(event);
        }

        const modal = document.getElementById('detail-modal');
        modal.setAttribute('data-ticket-id', event.db_data?.folio || event.id || event.id_tarea);
        modal.setAttribute('data-tipo', event.type);
        modal.classList.remove('hidden');

        AppState.editingItemId = event.id || event.id_tarea;
    },

    generateDetailContent(event) {
        switch (event.type) {
            case 'ticket': return this.generateTicketDetail(event);
            case 'recurrent_task': return this.generateRecurrentTaskDetail(event);
            case 'task': return this.generateTaskDetail(event);
            default: return '';
        }
    },

    generateTicketDetail(event) {
        return `
            <div class="space-y-3">
                <div class="flex items-center justify-between p-2 bg-blue-50 rounded">
                    <div class="flex items-center space-x-2">
                        <div class="w-8 h-8 rounded bg-blue-500 flex items-center justify-center text-white text-sm">
                            <i class="fas fa-ticket-alt"></i>
                        </div>
                        <div>
                            <h4 class="font-bold text-gray-800 text-sm">${event.db_data?.folio || 'Sin folio'}</h4>
                            <p class="text-xs text-blue-600">${event.db_data?.fecha_creacion || 'Sin fecha'}</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-xs text-gray-500">Prioridad</div>
                        <div class="text-xs font-medium ${Utils.getPriorityColor(event.priority)}">
                            ${Utils.getPriorityText(event.priority)}
                        </div>
                    </div>
                </div>

                <div class="flex items-center space-x-2 text-sm">
                    <i class="fas fa-circle text-gray-400 text-xs"></i>
                    <span class="text-gray-600">Estado:</span>
                    <span class="font-medium text-gray-800">${event.db_data?.estado || 'Sin estado'}</span>
                </div>

                <div class="flex items-center space-x-2 text-sm">
                    <i class="fas fa-building text-gray-400 text-xs"></i>
                    <span class="text-gray-600">Área:</span>
                    <span class="font-medium text-gray-800">${event.area || 'Sin área'}</span>
                </div>

                ${event.categoria || event.db_data?.nombre_sucat ? `
                    <div class="flex items-center space-x-2 text-sm">
                        <i class="fas fa-tags text-gray-400 text-xs"></i>
                        <span class="text-gray-600">Categoría:</span>
                        <span class="font-medium text-gray-800">${event.categoria || ''}</span>
                        ${event.db_data?.nombre_sucat ? `
                            <i class="fas fa-chevron-right text-gray-300 text-xs"></i>
                            <span class="font-medium text-gray-700">${event.db_data.nombre_sucat}</span>
                        ` : ''}
                    </div>
                ` : ''}

                <div class="mt-2">
                    <div class="flex items-center space-x-2 text-sm mb-1">
                        <i class="fas fa-align-left text-gray-400 text-xs"></i>
                        <span class="text-gray-600">Descripción</span>
                    </div>
                    <div class="bg-gray-50 p-3 rounded border border-gray-200">
                        <p class="text-gray-700 text-sm leading-relaxed">${event.description || event.db_data?.descripcion || 'Sin descripción'}</p>
                    </div>
                </div>
            </div>
        `;
    },

    generateRecurrentTaskDetail(event) {
        return `
            <div class="space-y-3">
                <div class="flex items-center justify-between p-2 bg-purple-50 rounded">
                    <div class="flex items-center space-x-2">
                        <div class="w-8 h-8 rounded bg-purple-500 flex items-center justify-center text-white text-sm">
                            <i class="fas fa-redo-alt"></i>
                        </div>
                        <div>
                            <h4 class="font-bold text-gray-800 text-sm">Tarea Cíclica</h4>
                            <p class="text-xs text-purple-600">${event.db_data?.fecha ? new Date(event.db_data.fecha).toLocaleDateString() : 'Sin fecha'}</p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center space-x-2 text-sm">
                    <i class="fas fa-briefcase text-gray-400 text-xs"></i>
                    <span class="text-gray-600">Puesto:</span>
                    <span class="font-medium text-gray-800">${event.puesto || event.db_data?.puesto || 'Sin puesto'}</span>
                </div>

                ${event.categoria ? `
                    <div class="flex items-center space-x-2 text-sm">
                        <i class="fas fa-tag text-gray-400 text-xs"></i>
                        <span class="text-gray-600">Categoría:</span>
                        <span class="font-medium text-gray-800">${event.categoria}</span>
                    </div>
                ` : ''}

                ${event.subcategoria ? `
                    <div class="flex items-center space-x-2 text-sm">
                        <i class="fas fa-tags text-gray-400 text-xs"></i>
                        <span class="text-gray-600">Subcategoría:</span>
                        <span class="font-medium text-gray-800">${event.subcategoria}</span>
                    </div>
                ` : ''}

                <div class="flex items-center space-x-2 text-sm">
                    <i class="fas fa-circle text-gray-400 text-xs"></i>
                    <span class="text-gray-600">Estado:</span>
                    <span class="font-medium text-gray-800">${event.status || event.db_data?.estado || 'Sin estado'}</span>
                </div>

                <div class="mt-2">
                    <div class="flex items-center space-x-2 text-sm mb-1">
                        <i class="fas fa-align-left text-gray-400 text-xs"></i>
                        <span class="text-gray-600">Actividad</span>
                    </div>
                    <div class="bg-gray-50 p-3 rounded border border-gray-200">
                        <p class="text-gray-700 text-sm leading-relaxed">${event.description || event.db_data?.descripcion || 'Sin descripción'}</p>
                    </div>
                </div>
            </div>
        `;
    },

    generateTaskDetail(event) {
        return `
            <div class="space-y-4">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-full bg-green-500 flex items-center justify-center text-white">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div>
                        <h4 class="font-semibold">Tarea</h4>
                        <p class="text-sm text-gray-500">${new Date(event.date).toLocaleDateString()} ${event.time ? ' - ' + event.time : ''}</p>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Prioridad</label>
                        <div class="${Utils.getTaskPriorityClass(event.priority || event.prioridad)} px-3 py-1 rounded-full text-sm font-medium inline-block">
                            ${event.priority || event.prioridad || 'Normal'}
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                        <div class="bg-gray-100 text-gray-800 px-3 py-1 rounded-full text-sm font-medium inline-block">
                            ${event.status || event.estatus || 'Pendiente'}
                        </div>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Descripción</label>
                    <p class="text-gray-600 bg-gray-50 p-3 rounded-lg">${event.description || event.descripcion || 'Sin descripción'}</p>
                </div>
            </div>
        `;
    },

    generateDetailFooter(event) {
        if (event.type === 'ticket') {
            return this.generateTicketFooter(event);
        } else if (event.type === 'recurrent_task' || event.type === 'task') {
            return this.generateTaskFooter(event);
        }
        return '';
    },

    generateTicketFooter(event) {
        return `
            <div class="border-t border-gray-200 p-4 lg:p-6">
                <div class="flex items-center gap-4 w-full justify-between">
                    <select id="estado-select" 
                            class="border border-gray-300 rounded-lg px-3 py-2 text-gray-700 focus:ring focus:ring-blue-200 focus:outline-none w-full max-w-xs">
                        <option value="">Selecciona estado...</option>
                        ${CONFIG.estadosTicket.map(estado =>
            `<option value="${estado}">${estado}</option>`
        ).join('')}
                    </select>
                    
                    ${event.db_data?.whatsapp ? `
                        <a href="${Utils.generateWhatsAppUrl(event)}"
                           target="_blank"
                           class="flex items-center gap-2 text-green-600 hover:text-green-700 px-4 py-2 border border-green-200 rounded-lg hover:bg-green-50 transition-colors whitespace-nowrap">
                            <i class="fab fa-whatsapp text-xl"></i>
                            <span class="text-sm">Pedir más info</span>
                        </a>
                    ` : ''}
                </div>
            </div>
        `;
    },

    generateTaskFooter(event) {
        return `
            <div class="border-t border-gray-200 p-4 lg:p-6">
                <div class="flex items-center gap-4 w-full justify-between">
                    <select id="estado-select" 
                            class="border border-gray-300 rounded-lg px-3 py-2 text-gray-700 focus:ring focus:ring-blue-200 focus:outline-none w-full max-w-xs">
                        <option value="">Selecciona estado...</option>
                        ${CONFIG.estadosTarea.map(estado =>
            `<option value="${estado}">${estado}</option>`
        ).join('')}
                    </select>
                </div>
            </div>
        `;
    },

    attachDetailEvents(event) {
        const select = document.getElementById('estado-select');
        if (!select) return;

        // Establecer valor actual
        if (event.type === 'ticket') {
            select.value = String(event.db_data?.estado || '');
        } else {
            select.value = String(event.status || event.estatus || '');
        }

        select.onchange = () => {
            const newValue = select.value;
            AlertManager.confirm({
                title: 'Confirmar cambio',
                text: `¿Estás seguro de cambiar el estado a "${newValue}"?`,
                icon: 'warning'
            }).then((result) => {
                if (result.isConfirmed) {
                    this.handleStatusChange(event, newValue);
                } else {
                    this.resetSelectValue(event, select);
                }
            });
        };
    },

    handleStatusChange(event, newValue) {
        if (event.type === 'ticket') {
            API.updateTicketStatus(event.db_data?.folio, newValue);
        } else if (event.type === 'recurrent_task') {
            this.handleRecurrentTaskStatusChange(event, newValue);
        } else if (event.type === 'task') {
            API.updateTaskStatus(event.id || event.id_tarea, newValue);
        }
    },

    handleRecurrentTaskStatusChange(event, newValue) {
        AlertManager.confirm({
            title: '¿Editar todas las tareas similares?',
            text: '¿Deseas aplicar este cambio solo a esta tarea o a todas las tareas similares?',
            icon: 'question',
            showDenyButton: true,
            confirmButtonText: 'Solo esta',
            denyButtonText: 'Todas las similares'
        }).then((result) => {
            if (result.isConfirmed) {
                API.updateRecurrentTaskStatus(event.id, newValue);
            } else if (result.isDenied) {
                this.editAllSimilarTasks(event, newValue);
            }
        });
    },

    editAllSimilarTasks(event, newValue) {
        const similarTasks = EventManager.findSimilarEvents(event);

        if (similarTasks.length === 0) {
            AlertManager.show('No se encontraron tareas similares', 'warning');
            return;
        }

        AlertManager.confirm({
            title: `¿Editar ${similarTasks.length} tareas?`,
            text: `Se aplicará el estado "${newValue}" a ${similarTasks.length} tareas similares.`,
            icon: 'warning'
        }).then((result) => {
            if (result.isConfirmed) {
                similarTasks.forEach(task => {
                    API.updateRecurrentTaskStatus(task.id, newValue);
                });
                AlertManager.show(`Se actualizaron ${similarTasks.length} tareas`, 'success');
            }
        });
    },

    resetSelectValue(event, select) {
        if (event.type === 'ticket') {
            select.value = String(event.db_data?.estado || '');
        } else {
            select.value = String(event.status || event.estatus || '');
        }
    }
};

// ============================================
// MANEJADOR DE API (PETICIONES HTTP)
// ============================================

const API = {
    async request(endpoint, data) {
        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            return await response.json();
        } catch (error) {
            AlertManager.show('Error de conexión', 'error');
            throw error;
        }
    },

    async updateTicketStatus(folio, newStatus) {
        const result = await this.request('tickets/actualizar_ticket.php', {
            id: folio,
            campo: 'estado',
            valor: newStatus
        });

        if (result.success) {
            AlertManager.show(`Ticket ${folio}: Estado cambiado a ${newStatus}`, 'success');
            this.updateEventInApp('ticket', folio, newStatus);
        } else {
            AlertManager.show(`Error: ${result.error}`, 'error');
        }
    },

    async updateRecurrentTaskStatus(id, newStatus) {
        const result = await this.request('task/actualizar_ciclica.php', {
            id: id,
            estado: newStatus
        });

        if (result.success) {
            AlertManager.show(`Tarea cíclica: Estado cambiado a ${newStatus}`, 'success');
            this.updateEventInApp('recurrent_task', id, newStatus);
        } else {
            AlertManager.show(`Error: ${result.error}`, 'error');
        }
    },

    async updateTaskStatus(id, newStatus) {
        const result = await this.request('task/actualizar_tarea.php', {
            id: id,
            campo: 'estatus',  // Cambiado: enviar 'campo' en lugar de 'estado'
            valor: newStatus   // Cambiado: enviar 'valor' con el nuevo estado
        });

        if (result.success) {
            AlertManager.show(`Tarea: Estado cambiado a ${newStatus}`, 'success');
            this.updateEventInApp('task', id, newStatus);
        } else {
            AlertManager.show(`Error: ${result.error}`, 'error');
        }
    },

    async deleteItem(type, id) {
        const endpoints = {
            ticket: 'tickets/eliminar_ticket.php',
            recurrent_task: 'task/eliminar_ciclica.php',
            task: 'task/eliminar_tarea.php'
        };

        const data = type === 'ticket' ? { folio: id } : { id: id };

        const result = await this.request(endpoints[type], data);

        if (result.success) {
            AlertManager.show('Elemento eliminado correctamente', 'success');
            this.removeEventFromApp(type, id);
            ModalManager.closeDetail();
            ViewManager.refreshCurrentView();
        } else {
            AlertManager.show(`Error: ${result.error}`, 'error');
        }
    },

    updateEventInApp(type, id, newStatus) {
        // Actualizar en AppState
        const event = AppState.events.find(e => {
            if (type === 'ticket') return e.db_data?.folio === id;
            return (e.id || e.id_tarea) == id;
        });

        if (event) {
            if (type === 'ticket') {
                if (event.db_data) event.db_data.estado = newStatus;
            } else {
                event.status = newStatus;
                event.estatus = newStatus;
            }
        }

        // Actualizar en FullCalendar si existe
        EventManager.updateEventInCalendar(type, id, newStatus);

        // Actualizar vista actual
        ViewManager.refreshCurrentView();
    },

    removeEventFromApp(type, id) {
        AppState.events = AppState.events.filter(event => {
            if (type === 'ticket') {
                return event.db_data?.folio !== id;
            }
            return (event.id || event.id_tarea) != id;
        });
    },

    async loadSubcategorias(categoriaId) {
        if (!categoriaId) return;

        const select = document.getElementById('subcategoria');
        if (!select) return;

        select.innerHTML = '<option value="">Selecciona una subcategoría</option>';

        try {
            const response = await fetch(`tickets/get_subcategorias.php?categoria_id=${categoriaId}`);
            const data = await response.json();

            data.forEach(subcat => {
                const option = document.createElement('option');
                option.value = subcat.id;
                option.textContent = subcat.nombre_sucat;
                select.appendChild(option);
            });
        } catch (error) {
            console.error('Error al cargar subcategorías:', error);
        }
    },

    async saveTicket(formData) {
        try {
            const response = await fetch("tickets/insert_ticket.php", {
                method: "POST",
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                AlertManager.show(result.message, 'success');
                ModalManager.close();
                // Resetear formulario
                document.querySelectorAll("#ticket-form select, #ticket-form textarea").forEach(el => el.value = "");
                // Recargar datos si es necesario
            } else {
                AlertManager.show(result.message, 'error');
            }
        } catch (error) {
            AlertManager.show("Error al enviar el ticket: " + error.message, 'error');
        }
    }
};

// ============================================
// MANEJADOR DE EDICIÓN DE TAREAS
// ============================================

const EditManager = {
    openEditModal(event) {
        AlertManager.confirm({
            title: 'Editar Tarea',
            html: this.getEditModalHtml(event),
            showCancelButton: true,
            confirmButtonText: 'Guardar',
            cancelButtonText: 'Cancelar',
            showConfirmButton: false,
            showCloseButton: true,
            didOpen: () => this.setupEditModalEvents(event),
            preConfirm: () => this.getEditModalData()
        }).then((result) => {
            if (result.isConfirmed) {
                this.saveEdit(event, result.value);
            }
        });
    },

    getEditModalHtml(event) {
        return `
            <div class="text-left space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">¿Qué deseas editar?</label>
                    <div class="flex space-x-2">
                        <button id="editar-una-btn" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex-1">
                            Solo esta tarea
                        </button>
                        <button id="editar-todas-btn" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 flex-1">
                            Todas las similares
                        </button>
                    </div>
                </div>
                
                <div id="formulario-edicion" class="hidden space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Título</label>
                        <input type="text" id="edit-titulo" value="${event.description || event.description || ''}" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Descripción</label>
                        <textarea id="edit-descripcion" class="w-full border border-gray-300 rounded-lg px-3 py-2" rows="3">${event.description || event.descripcion || ''}</textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                        <select id="edit-estado" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                            ${CONFIG.estadosTarea.map(estado => `
                                <option value="${estado}" ${(event.status || event.estatus || event.db_data?.estado) === estado ? 'selected' : ''}>
                                    ${estado}
                                </option>
                            `).join('')}
                        </select>
                    </div>
                    <div id="advertencia-todas" class="bg-yellow-50 p-3 rounded-lg border border-yellow-200 hidden">
                        <p class="text-sm text-yellow-700">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            Esta edición se aplicará a todas las tareas similares (mismo puesto, título y descripción).
                        </p>
                    </div>
                </div>
            </div>
        `;
    },

    setupEditModalEvents(event) {
        const unaBtn = document.getElementById('editar-una-btn');
        const todasBtn = document.getElementById('editar-todas-btn');
        const formulario = document.getElementById('formulario-edicion');
        const advertencia = document.getElementById('advertencia-todas');

        unaBtn.addEventListener('click', () => {
            formulario.classList.remove('hidden');
            advertencia.classList.add('hidden');
            AppState.editingAllSimilar = false;
            Swal.getConfirmButton().style.display = 'block';
        });

        todasBtn.addEventListener('click', () => {
            formulario.classList.remove('hidden');
            advertencia.classList.remove('hidden');
            AppState.editingAllSimilar = true;
            Swal.getConfirmButton().style.display = 'block';
        });

        Swal.getConfirmButton().style.display = 'none';
    },

    getEditModalData() {
        return {
            titulo: document.getElementById('edit-titulo')?.value,
            descripcion: document.getElementById('edit-descripcion')?.value,
            estado: document.getElementById('edit-estado')?.value,
            todasSimilares: AppState.editingAllSimilar
        };
    },

    saveEdit(event, data) {
        let tasksToEdit;

        if (data.todasSimilares) {
            tasksToEdit = EventManager.findSimilarEvents(event);
        } else {
            tasksToEdit = [event];
        }

        tasksToEdit.forEach(task => {
            task.title = data.titulo;
            task.titulo = data.titulo;
            task.description = data.descripcion;
            task.descripcion = data.descripcion;
            if (task.type === 'ticket') {
                if (task.db_data) task.db_data.estado = data.estado;
            } else {
                task.status = data.estado;
                task.estatus = data.estado;
            }
        });

        const message = data.todasSimilares
            ? `Se actualizaron ${tasksToEdit.length} tareas similares`
            : 'Se actualizó la tarea';

        AlertManager.show(message, 'success');

        ViewManager.refreshCurrentView();
        ModalManager.closeDetail();
    }
};

// ============================================
// MANEJADOR DE EVENTOS DE UI
// ============================================

const UIEventHandler = {
    init() {
        this.setupMobileMenu();
        this.setupViewToggles();
        this.setupNavigation();
        this.setupModals();
        this.setupItemTypeSelection();
        this.setupCategoriaChange();
    },

    setupMobileMenu() {
        const mobileBtn = document.getElementById('mobile-menu-btn');
        const closeBtn = document.getElementById('close-sidebar');

        mobileBtn?.addEventListener('click', () => {
            document.querySelector('.sidebar').classList.add('active');
        });

        closeBtn?.addEventListener('click', () => {
            document.querySelector('.sidebar').classList.remove('active');
        });
    },

    setupViewToggles() {
        document.querySelectorAll('.view-toggle').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.view-toggle').forEach(b => {
                    b.classList.remove('bg-blue-600', 'text-white');
                    b.classList.add('text-gray-600', 'hover:bg-gray-100');
                });
                this.classList.remove('text-gray-600', 'hover:bg-gray-100');
                this.classList.add('bg-blue-600', 'text-white');

                ViewManager.switchView(this.dataset.view);
            });
        });
    },

    setupNavigation() {
        document.querySelectorAll('.nav-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                ViewManager.navigate(parseInt(this.dataset.direction));
            });
        });
    },

    setupModals() {
        document.getElementById('add-item-btn')?.addEventListener('click', () => ModalManager.open());
        document.getElementById('close-modal')?.addEventListener('click', () => ModalManager.close());
        document.getElementById('cancel-btn')?.addEventListener('click', () => ModalManager.close());
        document.getElementById('save-btn')?.addEventListener('click', () => this.saveItem());

        document.getElementById('close-detail-modal')?.addEventListener('click', () => ModalManager.closeDetail());
        document.getElementById('cancel-item-btn')?.addEventListener('click', () => ModalManager.closeDetail());
        document.getElementById('delete-item-btn')?.addEventListener('click', () => this.deleteItem());
        document.getElementById('edit-item-btn')?.addEventListener('click', () => {
            if (AppState.editingEvent) {
                EditManager.openEditModal(AppState.editingEvent);
            }
        });
    },

    setupItemTypeSelection() {
        document.querySelectorAll('.item-type-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                ModalManager.showForm(this.dataset.type);
            });
        });
    },

    setupCategoriaChange() {
        document.getElementById('categoria')?.addEventListener('change', function () {
            API.loadSubcategorias(this.value);
        });
    },

    async saveItem() {
        const formContainer = document.getElementById("ticket-form");
        const formData = new FormData();

        formData.append("area", formContainer.querySelector("#area").value);
        formData.append("donde", formContainer.querySelector("#donde").value);
        formData.append("detalle_donde", formContainer.querySelector("#detalle_donde").value);
        formData.append("categoria_servicio", formContainer.querySelector("#categoria").value);
        formData.append("subcategoria", formContainer.querySelector("#subcategoria").value);
        formData.append("descripcion", formContainer.querySelector("#descripcion").value);

        await API.saveTicket(formData);
    },

    deleteItem() {
        const modal = document.getElementById('detail-modal');
        const tipo = modal.getAttribute('data-tipo');
        const id = modal.getAttribute('data-ticket-id');

        AlertManager.confirm({
            title: '¿Estás seguro?',
            text: "Esta acción no se puede deshacer",
            icon: 'warning',
            confirmButtonColor: '#d33'
        }).then((result) => {
            if (result.isConfirmed) {
                API.deleteItem(tipo, id);
            }
        });
    }
};

// ============================================
// INICIALIZACIÓN DE LA APLICACIÓN
// ============================================

function initApp() {
    console.log('Inicializando aplicación...');

    // Verificar datos necesarios
    if (typeof usuario === 'undefined') {
        console.error('Error: variable "usuario" no definida');
        return;
    }

    if (typeof eventsData === 'undefined') {
        console.error('Error: variable "eventsData" no definida');
        return;
    }

    // Inicializar estado con datos de usuarios si están disponibles
    AppState.init(usuario, eventsData, typeof usersData !== 'undefined' ? usersData : []);

    // Inicializar UI
    ViewManager.updateHeader();
    ViewManager.renderMonthView();

    // Inicializar filtros globales
    FilterManager.init();

    // Configurar event listeners
    UIEventHandler.init();

    console.log('Aplicación lista');
    console.log('Eventos cargados:', AppState.events.length);
    console.log('Usuario:', AppState.user);
}

// Iniciar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', initApp);