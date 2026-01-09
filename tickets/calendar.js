// Configuración global
const departments = {
    'ti': { name: 'TI', positions: ['Desarrollador', 'Analista de Sistemas', 'Administrador de Red', 'Soporte Técnico', 'Arquitecto de Software'] },
    'ventas': { name: 'Ventas', positions: ['Ejecutivo de Ventas', 'Gerente de Ventas', 'Asesor Comercial', 'Coordinador de Ventas'] },
    'marketing': { name: 'Marketing', positions: ['Especialista en Marketing', 'Community Manager', 'Analista de Mercado', 'Diseñador Gráfico'] },
    'rh': { name: 'Recursos Humanos', positions: ['Reclutador', 'Especialista en Nómina', 'Coordinador de Capacitación', 'Analista de RH'] },
    'contabilidad': { name: 'Contabilidad', positions: ['Contador', 'Auxiliar Contable', 'Analista Financiero', 'Auditor'] }
};

const holidays = [
    '2023-01-01', '2023-02-05', '2023-03-21', '2023-05-01', '2023-09-16', '2023-12-25'
];

const months = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
const days = ["Domingo", "Lunes", "Martes", "Miércoles", "Jueves", "Viernes", "Sábado"];

// Estado global
const currentDate = new Date();
let currentMonth = currentDate.getMonth();
let currentYear = currentDate.getFullYear();
let currentWeek = 0;
let currentDay = currentDate.getDate();
let currentView = 'month';
let currentMainView = 'calendar';
let editingItemId = null;

// Procesar eventos desde PHP
let events = eventsData.map(event => {
    return {
        ...event,
        date: new Date(event.date)
    };
});

// Inicializar aplicación
function initApp() {
    initCalendar();
    setupEventListeners();
    console.log('Eventos cargados:', events); // Debug
}

// Inicializar calendario
function initCalendar() {
    updateCalendarHeader();
    renderMonthView();
}

// Configurar event listeners
function setupEventListeners() {
    console.log("🟢 setupEventListeners iniciado");

    // Mobile menu
    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
    const closeSidebarBtn = document.getElementById('close-sidebar');

    if (mobileMenuBtn && closeSidebarBtn) {
        mobileMenuBtn.addEventListener('click', () => {
            console.log("📱 Abriendo menú lateral");
            document.querySelector('.sidebar').classList.add('active');
        });

        closeSidebarBtn.addEventListener('click', () => {
            console.log("📱 Cerrando menú lateral");
            document.querySelector('.sidebar').classList.remove('active');
        });
    }

    // View toggles
    document.querySelectorAll('.view-toggle').forEach(btn => {
        btn.addEventListener('click', function () {
            console.log("🔄 Cambiando vista:", this.dataset.view);
            document.querySelectorAll('.view-toggle').forEach(b => {
                b.classList.remove('bg-blue-600', 'text-white');
                b.classList.add('text-gray-600', 'hover:bg-gray-100');
            });
            this.classList.remove('text-gray-600', 'hover:bg-gray-100');
            this.classList.add('bg-blue-600', 'text-white');

            currentView = this.dataset.view;
            switchView(currentView);
        });
    });

    // Navigation
    document.querySelectorAll('.nav-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const direction = parseInt(this.dataset.direction);
            console.log("⏩ Navegando periodo:", direction);
            navigatePeriod(direction);
        });
    });

    // Modal buttons
    const addBtn = document.getElementById('add-item-btn');
    const closeBtn = document.getElementById('close-modal');
    const cancelBtn = document.getElementById('cancel-btn');
    const saveBtn = document.getElementById('save-btn');

    if (addBtn) addBtn.addEventListener('click', openModal);
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
    if (saveBtn) saveBtn.addEventListener('click', saveItem);

    // Detail Modal
    const closeDetail = document.getElementById('close-detail-modal');
    const cancelItemBtn = document.getElementById('cancel-item-btn');
    const deleteBtn = document.getElementById('delete-item-btn');
    const editBtn = document.getElementById('edit-item-btn');

    if (closeDetail) closeDetail.addEventListener('click', closeDetailModal);
    if (cancelItemBtn) cancelItemBtn.addEventListener('click', cancelItem);
    if (deleteBtn) deleteBtn.addEventListener('click', deleteItem);
    if (editBtn) editBtn.addEventListener('click', editItem);

    // Item type selection (Ticket / Task / Meeting)
    const itemButtons = document.querySelectorAll('.item-type-btn');
    if (itemButtons.length > 0) {
        itemButtons.forEach(btn => {
            btn.addEventListener('click', function () {
                const type = this.dataset.type;
                console.log("🟦 Click en botón de tipo:", type);
                showForm(type);
            });
        });
    } else {
        console.warn("⚠️ No se encontraron botones .item-type-btn al iniciar");
    }

    // Cargar subcategorías dinámicamente
    const categoriaSelect = document.getElementById('categoria');
    if (categoriaSelect) {
        categoriaSelect.addEventListener('change', function () {
            console.log("📂 Categoría cambiada:", this.value);
            loadSubcategorias(this.value);
        });
    }
}

// Cargar subcategorías
function loadSubcategorias(categoriaId) {
    const subcategoriaSelect = document.getElementById('subcategoria');
    if (!subcategoriaSelect) return;

    subcategoriaSelect.innerHTML = '<option value="">Selecciona una subcategoría</option>';

    if (!categoriaId) return;

    fetch('tickets/get_subcategorias.php?categoria_id=' + categoriaId)
        .then(response => response.json())
        .then(data => {
            data.forEach(subcat => {
                const option = document.createElement('option');
                option.value = subcat.id;
                option.textContent = subcat.nombre_sucat;
                subcategoriaSelect.appendChild(option);
            });
        })
        .catch(error => {
            console.error('Error al cargar subcategorías:', error);
        });
}

// Cambiar vista
function switchView(view) {
    document.querySelectorAll('.calendar-view').forEach(v => v.classList.add('hidden'));
    document.getElementById(`${view}-view`).classList.remove('hidden');

    const titles = {
        'month': 'Calendario Mensual',
        'week': 'Calendario Semanal',
        'day': 'Vista Diaria'
    };
    document.getElementById('view-title').textContent = titles[view];

    if (view === 'month') {
        renderMonthView();
    } else if (view === 'week') {
        renderWeekView();
    } else if (view === 'day') {
        renderDayView();
    }
}

// Navegar entre periodos
function navigatePeriod(direction) {
    if (currentView === 'month') {
        currentMonth += direction;
        if (currentMonth < 0) {
            currentMonth = 11;
            currentYear--;
        } else if (currentMonth > 11) {
            currentMonth = 0;
            currentYear++;
        }
        renderMonthView();
    } else if (currentView === 'week') {
        currentWeek += direction;
        renderWeekView();
    } else if (currentView === 'day') {
        currentDay += direction;
        const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
        if (currentDay < 1) {
            currentMonth--;
            if (currentMonth < 0) {
                currentMonth = 11;
                currentYear--;
            }
            currentDay = new Date(currentYear, currentMonth + 1, 0).getDate();
        } else if (currentDay > daysInMonth) {
            currentDay = 1;
            currentMonth++;
            if (currentMonth > 11) {
                currentMonth = 0;
                currentYear++;
            }
        }
        renderDayView();
    }
    updateCalendarHeader();
}

// Actualizar encabezado del calendario
function updateCalendarHeader() {
    if (currentView === 'month') {
        document.getElementById('current-period').textContent = `${months[currentMonth]} ${currentYear}`;
    } else if (currentView === 'week') {
        const weekStart = new Date(currentYear, currentMonth, currentWeek * 7 + 1);
        const weekEnd = new Date(currentYear, currentMonth, currentWeek * 7 + 7);
        document.getElementById('current-period').textContent =
            `Semana ${currentWeek + 1} - ${weekStart.getDate()} al ${weekEnd.getDate()} de ${months[currentMonth]}`;
    } else if (currentView === 'day') {
        const date = new Date(currentYear, currentMonth, currentDay);
        document.getElementById('current-period').textContent =
            `${days[date.getDay()]}, ${currentDay} de ${months[currentMonth]} ${currentYear}`;
    }
}

// Renderizar vista mensual
function renderMonthView() {
    const monthDays = document.getElementById('month-days');
    monthDays.innerHTML = '';

    const firstDay = new Date(currentYear, currentMonth, 1).getDay();
    const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
    const startDay = firstDay === 0 ? 6 : firstDay - 1;

    // Días vacíos antes del primer día
    for (let i = 0; i < startDay; i++) {
        const emptyDay = document.createElement('div');
        emptyDay.classList.add('min-h-20', 'lg:min-h-24', 'p-1', 'lg:p-2', 'border', 'border-gray-200', 'rounded-lg', 'bg-gray-50');
        monthDays.appendChild(emptyDay);
    }

    // Días del mes
    for (let day = 1; day <= daysInMonth; day++) {
        const dayElement = document.createElement('div');
        const dayDate = new Date(currentYear, currentMonth, day);

        dayElement.classList.add('min-h-20', 'lg:min-h-24', 'p-1', 'lg:p-2', 'border', 'border-gray-200', 'rounded-lg', 'hover:border-blue-300', 'transition-colors');

        // Verificar si es hoy
        if (day === currentDate.getDate() && currentMonth === currentDate.getMonth() && currentYear === currentDate.getFullYear()) {
            dayElement.classList.add('bg-blue-50', 'border-blue-300');
        }

        // Verificar si es festivo
        if (isHoliday(dayDate)) {
            dayElement.classList.add('bg-red-50', 'border-red-200');
        }

        const dayNumber = document.createElement('div');
        dayNumber.classList.add('font-medium', 'text-gray-900', 'mb-1', 'flex', 'justify-between', 'items-center', 'text-sm', 'lg:text-base');

        if (day === currentDate.getDate() && currentMonth === currentDate.getMonth() && currentYear === currentDate.getFullYear()) {
            dayNumber.classList.add('text-blue-600');
        }

        if (isHoliday(dayDate)) {
            dayNumber.classList.add('text-red-600');
        }

        dayNumber.innerHTML = `
            <span>${day}</span>
            ${isHoliday(dayDate) ? '<i class="fas fa-star text-red-500 text-xs"></i>' : ''}
        `;
        dayElement.appendChild(dayNumber);

        const dayEvents = getEventsForDate(dayDate);
        console.log(`Día ${day}:`, dayEvents); // Debug

        if (dayEvents.length > 0) {
            const eventIndicators = document.createElement('div');
            eventIndicators.classList.add('space-y-1');

            dayEvents.slice(0, 2).forEach(event => {
                const indicator = document.createElement('div');
                indicator.classList.add('text-xs', 'flex', 'items-center', 'space-x-1', 'event-indicator', 'p-1', 'rounded');
                indicator.addEventListener('click', () => showEventDetails(event));

                const dot = document.createElement('div');
                dot.classList.add('w-2', 'h-2', 'rounded-full', 'flex-shrink-0');

                // Asignar colores según el tipo de evento
                if (event.type === 'ticket') dot.classList.add('bg-blue-500');
                else if (event.type === 'task') dot.classList.add('bg-warning');
                else if (event.type === 'meeting') dot.classList.add('bg-success');
                else if (event.type === 'recurrent_task') dot.classList.add('bg-red-500'); // Nuevo color para tareas cíclicas

                const text = document.createElement('span');
                text.classList.add('truncate', 'text-gray-600', 'flex-1');
                text.textContent = event.title.substring(0, 10) + '...';

                indicator.appendChild(dot);
                indicator.appendChild(text);
                eventIndicators.appendChild(indicator);
            });

            if (dayEvents.length > 2) {
                const moreIndicator = document.createElement('div');
                moreIndicator.classList.add('text-xs', 'text-gray-500', 'p-1');
                moreIndicator.textContent = `+${dayEvents.length - 2} más`;
                eventIndicators.appendChild(moreIndicator);
            }

            dayElement.appendChild(eventIndicators);
        }

        monthDays.appendChild(dayElement);
    }
}

// Renderizar vista semanal
function renderWeekView() {
    for (let i = 0; i < 7; i++) {
        const day = new Date(currentYear, currentMonth, currentWeek * 7 + i + 1);
        const dayElement = document.getElementById(`week-day-${i}`);
        const dayName = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'][day.getDay()];
        dayElement.textContent = `${dayName} ${day.getDate()}`;

        if (isHoliday(day)) {
            dayElement.classList.add('text-red-600');
        } else {
            dayElement.classList.remove('text-red-600');
        }
    }

    const weekHours = document.getElementById('week-hours');
    weekHours.innerHTML = '';

    for (let hour = 6; hour <= 22; hour++) {
        const timeRow = document.createElement('div');
        timeRow.classList.add('grid', 'grid-cols-8', 'gap-1', 'lg:gap-2', 'mb-2');

        const timeLabel = document.createElement('div');
        timeLabel.classList.add('text-xs', 'lg:text-sm', 'text-gray-500', 'py-2');
        timeLabel.textContent = `${hour}:00`;
        timeRow.appendChild(timeLabel);

        for (let day = 0; day < 7; day++) {
            const dayCell = document.createElement('div');
            dayCell.classList.add('min-h-10', 'lg:min-h-12', 'border', 'border-gray-200', 'rounded', 'p-1');

            const dayDate = new Date(currentYear, currentMonth, currentWeek * 7 + day + 1);
            dayDate.setHours(hour);

            if (isHoliday(dayDate)) {
                dayCell.classList.add('bg-red-50', 'border-red-200');
            }

            const hourEvents = getEventsForDateAndHour(dayDate, hour);
            hourEvents.forEach(event => {
                const eventElement = document.createElement('div');
                eventElement.classList.add('text-xs', 'p-1', 'rounded', 'mb-1', 'text-white', 'truncate', 'cursor-pointer');
                eventElement.addEventListener('click', () => showEventDetails(event));

                // Asignar colores según el tipo de evento
                if (event.type === 'ticket') eventElement.classList.add('bg-blue-500');
                else if (event.type === 'task') eventElement.classList.add('bg-warning');
                else if (event.type === 'meeting') eventElement.classList.add('bg-success');
                else if (event.type === 'recurrent_task') eventElement.classList.add('bg-blue-500'); // Nuevo color para tareas cíclicas

                eventElement.textContent = event.title.substring(0, 15) + '...';
                dayCell.appendChild(eventElement);
            });

            timeRow.appendChild(dayCell);
        }

        weekHours.appendChild(timeRow);
    }
}

// Renderizar vista diaria
function renderDayView() {
    const dayTitle = document.getElementById('day-title');
    const dayDate = new Date(currentYear, currentMonth, currentDay);
    dayTitle.textContent = `${days[dayDate.getDay()]}, ${currentDay} de ${months[currentMonth]} ${currentYear}`;

    const dayEventsContainer = document.getElementById('day-events');
    dayEventsContainer.innerHTML = '';

    if (isHoliday(dayDate)) {
        const holidayNotice = document.createElement('div');
        holidayNotice.classList.add('bg-red-50', 'border', 'border-red-200', 'rounded-lg', 'p-4', 'mb-4');
        holidayNotice.innerHTML = `
            <div class="flex items-center space-x-2 text-red-800">
                <i class="fas fa-star"></i>
                <span class="font-medium">Día festivo</span>
            </div>
            <p class="text-sm text-red-600 mt-1">Este día es considerado festivo. Las tareas programadas pueden requerir aprobación especial.</p>
        `;
        dayEventsContainer.appendChild(holidayNotice);
    }

    const dayEvents = getEventsForDate(dayDate);

    if (dayEvents.length === 0) {
        dayEventsContainer.innerHTML = `
            <div class="text-center py-8 lg:py-12 text-gray-500">
                <i class="fas fa-calendar-day text-2xl lg:text-4xl mb-4"></i>
                <p class="text-base lg:text-lg">No hay eventos programados para hoy</p>
                <p class="text-xs lg:text-sm">Haz clic en "Nuevo elemento" para agregar uno</p>
            </div>
        `;
        return;
    }

    dayEvents.sort((a, b) => (a.time || '00:00').localeCompare(b.time || '00:00'));

    dayEvents.forEach(event => {
        const eventElement = document.createElement('div');
        eventElement.classList.add('flex', 'items-start', 'space-x-3', 'lg:space-x-4', 'p-3', 'lg:p-4', 'border', 'border-gray-200', 'rounded-lg', 'mb-3', 'cursor-pointer');
        eventElement.addEventListener('click', () => showEventDetails(event));

        const icon = document.createElement('div');
        icon.classList.add('w-8', 'h-8', 'lg:w-10', 'lg:h-10', 'rounded-full', 'flex', 'items-center', 'justify-center', 'text-white', 'flex-shrink-0');

        // Asignar colores según el tipo de evento
        if (event.type === 'ticket') icon.classList.add('bg-blue-500');
        else if (event.type === 'task') icon.classList.add('bg-warning');
        else if (event.type === 'meeting') icon.classList.add('bg-success');
        else if (event.type === 'recurrent_task') icon.classList.add('bg-blue-500'); // Nuevo color para tareas cíclicas

        // Asignar iconos según el tipo de evento
        if (event.type === 'ticket') icon.innerHTML = `<i class="fas fa-ticket-alt text-sm lg:text-base"></i>`;
        else if (event.type === 'task') icon.innerHTML = `<i class="fas fa-tasks text-sm lg:text-base"></i>`;
        else if (event.type === 'meeting') icon.innerHTML = `<i class="fas fa-users text-sm lg:text-base"></i>`;
        else if (event.type === 'recurrent_task') icon.innerHTML = `<i class="fas fa-redo-alt text-sm lg:text-base"></i>`; // Nuevo icono para tareas cíclicas

        const content = document.createElement('div');
        content.classList.add('flex-1');

        const header = document.createElement('div');
        header.classList.add('flex', 'flex-col', 'lg:flex-row', 'lg:justify-between', 'lg:items-start', 'mb-2');

        const title = document.createElement('h4');
        title.classList.add('font-medium', 'text-gray-900', 'text-sm', 'lg:text-base', 'mb-1', 'lg:mb-0');
        title.textContent = event.title;

        const time = document.createElement('span');
        time.classList.add('text-xs', 'lg:text-sm', 'text-gray-500');
        time.textContent = event.time || 'Todo el día';

        header.appendChild(title);
        header.appendChild(time);

        const details = document.createElement('div');
        details.classList.add('text-xs', 'lg:text-sm', 'text-gray-600', 'space-y-1');

        if (event.type === 'ticket') {
            const ticketInfo = document.createElement('div');
            ticketInfo.classList.add('flex', 'items-center', 'space-x-2');
            ticketInfo.innerHTML = `
                <span class="font-medium">Área:</span>
                <span class="bg-gray-100 px-2 py-1 rounded text-xs">${event.area}</span>
                <span class="font-medium">Prioridad:</span>
                <span class="bg-${event.priority === 'alta' || event.priority === 'urgente' ? 'red' : event.priority === 'media' ? 'yellow' : 'green'}-100 text-${event.priority === 'alta' || event.priority === 'urgente' ? 'red' : event.priority === 'media' ? 'yellow' : 'green'}-800 px-2 py-1 rounded text-xs">${event.priority}</span>
            `;
            details.appendChild(ticketInfo);
        }

        if (event.db_data?.puesto) {
            const priorityInfo = document.createElement('div');
            priorityInfo.classList.add('flex', 'items-center', 'space-x-2');

            // Si la tarea es de tu puesto, poner verde
            let puestoBg, puestoText;
            if (event.db_data.puesto === usuario.puesto) {
                puestoBg = 'bg-green-100';
                puestoText = 'text-green-800';
            } else {
                // Generar color dinámico para otros puestos
                const colors = ['red', 'yellow', 'blue', 'indigo', 'purple', 'pink', 'gray'];
                const hash = Array.from(event.db_data.puesto).reduce((acc, char) => acc + char.charCodeAt(0), 0);
                const color = colors[hash % colors.length];
                puestoBg = `bg-${color}-100`;
                puestoText = `text-${color}-800`;
            }

            // Color dinámico para estado
            const estadoColors = ['red', 'yellow', 'green', 'blue', 'indigo', 'purple', 'pink', 'gray'];
            const estadoHash = Array.from(event.db_data.estado).reduce((acc, char) => acc + char.charCodeAt(0), 0);
            const estadoColor = estadoColors[estadoHash % estadoColors.length];
            const estadoBg = `bg-${estadoColor}-100`;
            const estadoText = `text-${estadoColor}-800`;

            priorityInfo.innerHTML = `
        <span class="font-medium">Tarea puesto:</span>
        <span class="${puestoBg} ${puestoText} px-2 py-1 rounded text-xs">${event.db_data.puesto}</span>
        <span class="font-medium">Estado:</span>
        <span class="${estadoBg} ${estadoText} px-2 py-1 rounded text-xs">${event.db_data.estado}</span>        
        <span class="font-medium">Asignado a:</span>
        <span class="${estadoBg} ${estadoText} px-2 py-1 rounded text-xs">${event.db_data.asignado_a}</span>
    `;
            details.appendChild(priorityInfo);
        }



        content.appendChild(header);
        content.appendChild(details);

        eventElement.appendChild(icon);
        eventElement.appendChild(content);
        dayEventsContainer.appendChild(eventElement);
    });
}

// Obtener eventos para una fecha específica
function getEventsForDate(date) {
    const dayEvents = [];

    events.forEach(event => {
        const eventDate = new Date(event.date);
        if (eventDate.getDate() === date.getDate() &&
            eventDate.getMonth() === date.getMonth() &&
            eventDate.getFullYear() === date.getFullYear()) {
            dayEvents.push(event);
        }
    });

    return dayEvents;
}

// Obtener eventos para fecha y hora específica
function getEventsForDateAndHour(date, hour) {
    return getEventsForDate(date).filter(event => {
        if (!event.time) return false;
        const eventHour = parseInt(event.time.split(':')[0]);
        return eventHour === hour;
    });
}

// Verificar si es día festivo
function isHoliday(date) {
    const dateString = date.toISOString().split('T')[0];
    return holidays.includes(dateString);
}

// Mostrar detalles del evento

function showEventDetails(event) {
    document.getElementById('detail-title').textContent = 'Detalles del elemento';
    
    let content = '';
    let footerContent = '';
    
    // Configurar contenido según el tipo de evento
    if (event.type === 'ticket') {
        content = `
        <div class="space-y-3">
            <!-- Header super compacto -->
            <div class="flex items-center justify-between p-2 bg-blue-50 rounded">
                <div class="flex items-center space-x-2">
                    <div class="w-8 h-8 rounded bg-blue-500 flex items-center justify-center text-white text-sm">
                        <i class="fas fa-ticket-alt"></i>
                    </div>
                    <div>
                        <h4 class="font-bold text-gray-800 text-sm">${event.db_data.folio}</h4>
                        <p class="text-xs text-blue-600">${event.db_data.fecha_creacion}</p>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-xs text-gray-500">Prioridad</div>
                    <div class="text-xs font-medium ${getPriorityColorClass(event.priority)}">
                        ${getPriorityText(event.priority)}
                    </div>
                </div>
            </div>

            <!-- Estado simple -->
            <div class="flex items-center space-x-2 text-sm">
                <i class="fas fa-circle text-gray-400 text-xs"></i>
                <span class="text-gray-600">Estado:</span>
                <span class="font-medium text-gray-800">${event.db_data.estado}</span>
            </div>

            <!-- Área simple -->
            <div class="flex items-center space-x-2 text-sm">
                <i class="fas fa-building text-gray-400 text-xs"></i>
                <span class="text-gray-600">Área:</span>
                <span class="font-medium text-gray-800">${event.area}</span>
            </div>

            <!-- Categoría y subcategoría en línea -->
            <div class="flex items-center space-x-2 text-sm">
                <i class="fas fa-tags text-gray-400 text-xs"></i>
                <span class="text-gray-600">Categoría:</span>
                <span class="font-medium text-gray-800">${event.categoria}</span>
                ${event.categoria ? `
                <i class="fas fa-chevron-right text-gray-300 text-xs"></i>
                <span class="font-medium text-gray-700">${event.db_data.nombre_sucat}</span>
                ` : ''}
            </div>

            <!-- Descripción con buen espacio -->
            <div class="mt-2">
                <div class="flex items-center space-x-2 text-sm mb-1">
                    <i class="fas fa-align-left text-gray-400 text-xs"></i>
                    <span class="text-gray-600">Descripción</span>
                </div>
                <div class="bg-gray-50 p-3 rounded border border-gray-200">
                    <p class="text-gray-700 text-sm leading-relaxed">${event.description}</p>
                </div>
            </div>
        </div>
        `;
        
        // FOOTER PARA TICKETS (con WhatsApp)
        footerContent = `
            <div class="border-t border-gray-200 p-4 lg:p-6">
                <div class="flex items-center gap-4 w-full justify-between">
                    <!-- Select de cambio de estado -->
                    <select id="estado-select" 
                            class="border border-gray-300 rounded-lg px-3 py-2 text-gray-700 focus:ring focus:ring-blue-200 focus:outline-none w-full max-w-xs">
                        <option value="">Selecciona estado...</option>
                        <option value="Pendiente">Pendiente</option>
                        <option value="En Proceso">En Proceso</option>
                        <option value="Cerrado">Cerrado</option>
                        <option value="Cancelado">Cancelado</option>
                    </select>
                    
                    <!-- Botón WhatsApp (solo para tickets) -->
                    ${event.db_data.whatsapp ? `
                    <a href="${generarURLWhatsApp(event)}"
                       target="_blank"
                       class="flex items-center gap-2 text-green-600 hover:text-green-700 px-4 py-2 border border-green-200 rounded-lg hover:bg-green-50 transition-colors whitespace-nowrap">
                        <i class="fab fa-whatsapp text-xl"></i>
                        <span class="text-sm">Pedir más info</span>
                    </a>
                    ` : ''}
                </div>
            </div>
        `;
        
    } else if (event.type === 'recurrent_task') {
        content = `
            <div class="space-y-3">
                <!-- Header para tarea cíclica -->
                <div class="flex items-center justify-between p-2 bg-purple-50 rounded">
                    <div class="flex items-center space-x-2">
                        <div class="w-8 h-8 rounded bg-purple-500 flex items-center justify-center text-white text-sm">
                            <i class="fas fa-redo-alt"></i>
                        </div>
                        <div>
                            <h4 class="font-bold text-gray-800 text-sm">Tarea Cíclica</h4>
                            <p class="text-xs text-purple-600">${event.db_data.fecha ? new Date(event.db_data.fecha).toLocaleDateString() : 'Sin fecha'}</p>
                        </div>
                    </div>
                </div>

                <!-- Información básica -->
                <div class="flex items-center space-x-2 text-sm">
                    <i class="fas fa-briefcase text-gray-400 text-xs"></i>
                    <span class="text-gray-600">Puesto:</span>
                    <span class="font-medium text-gray-800">${event.puesto}</span>
                </div>

                <!-- Categoría y subcategoría -->
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

                <!-- Estado -->
                <div class="flex items-center space-x-2 text-sm">
                    <i class="fas fa-circle text-gray-400 text-xs"></i>
                    <span class="text-gray-600">Estado:</span>
                    <span class="font-medium text-gray-800">${event.status}</span>
                </div>

                <!-- Descripción -->
                <div class="mt-2">
                    <div class="flex items-center space-x-2 text-sm mb-1">
                        <i class="fas fa-align-left text-gray-400 text-xs"></i>
                        <span class="text-gray-600">Actividad</span>
                    </div>
                    <div class="bg-gray-50 p-3 rounded border border-gray-200">
                        <p class="text-gray-700 text-sm leading-relaxed">${event.description}</p>
                    </div>
                </div>
            </div>
        `;
        
        // FOOTER PARA TAREAS CÍCLICAS (solo select, sin WhatsApp)
        footerContent = `
            <div class="border-t border-gray-200 p-4 lg:p-6">
                <div class="flex items-center gap-4 w-full justify-between">
                    <!-- Select de cambio de estado -->
                    <select id="estado-select" 
                            class="border border-gray-300 rounded-lg px-3 py-2 text-gray-700 focus:ring focus:ring-blue-200 focus:outline-none w-full max-w-xs">
                        <option value="">Selecciona estado...</option>
                        <option value="Pendiente">Pendiente</option>
                        <option value="En Proceso">En Proceso</option>
                        <option value="Completada">Completada</option>
                        <option value="Cancelada">Cancelada</option>
                    </select>
                </div>
            </div>
        `;
        
    } else if (event.type === 'task') {
        content = `
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
                        <div class="${getTaskPriorityClass(event.priority)} px-3 py-1 rounded-full text-sm font-medium inline-block">
                            ${event.priority}
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                        <div class="bg-gray-100 text-gray-800 px-3 py-1 rounded-full text-sm font-medium inline-block">
                            ${event.status}
                        </div>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Descripción</label>
                    <p class="text-gray-600 bg-gray-50 p-3 rounded-lg">${event.description}</p>
                </div>
            </div>
        `;
        
        // FOOTER PARA TAREAS NORMALES
        footerContent = `
            <div class="border-t border-gray-200 p-4 lg:p-6">
                <div class="flex items-center gap-4 w-full justify-between">
                    <select id="estado-select" 
                            class="border border-gray-300 rounded-lg px-3 py-2 text-gray-700 focus:ring focus:ring-blue-200 focus:outline-none w-full max-w-xs">
                        <option value="">Selecciona estado...</option>
                        <option value="Pendiente">Pendiente</option>
                        <option value="En Proceso">En Proceso</option>
                        <option value="Completada">Completada</option>
                        <option value="Cancelada">Cancelada</option>
                    </select>
                </div>
            </div>
        `;
        
    } else if (event.type === 'meeting') {
        // ... contenido para reuniones ...
    }
    
    // Actualizar contenido principal
    document.getElementById('detail-content').innerHTML = content;

    // Actualizar footer dinámicamente
    const footerElement = document.getElementById('detail-footer');
    if (footerElement) {
        footerElement.innerHTML = footerContent;

        // Configurar el select según el tipo de evento
        if (event.type === 'ticket' || event.type === 'recurrent_task' || event.type === 'task') {
            const select = document.getElementById('estado-select');

            if (select) {
                // Asignar valor según el tipo de evento
                if (event.type === 'ticket') {
                    select.value = String(event.db_data.estado || '');
                } else if (event.type === 'recurrent_task' || event.type === 'task') {
                    select.value = String(event.status || '');
                }

                // Limpiar evento previo
                select.onchange = null;

                // Asignar nuevo evento según el tipo
                select.onchange = function () {
    const nuevoValor = this.value;

    Swal.fire({
        title: 'Confirmar cambio',
        text: `¿Estás seguro de cambiar el estado a "${nuevoValor}"?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, cambiar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            if (event.type === 'ticket') {
                cambiarEstadoTicket(event.db_data.folio, nuevoValor);
            } else if (event.type === 'recurrent_task') {
                cambiarEstadoCiclica(event.id, nuevoValor);
            } else if (event.type === 'task') {
                cambiarEstadoTarea(event.id, nuevoValor);
            }
        } else {
            // Revertir al valor anterior
            if (event.type === 'ticket') {
                select.value = String(event.db_data.estado || '');
            } else {
                select.value = String(event.status || '');
            }
        }
    });
};

            }
        }

    } else {
        console.error('Elemento detail-footer no encontrado');
        // Si no existe, crearlo dinámicamente
        const modalContent = document.querySelector('#detail-modal > div > div');
        const newFooter = document.createElement('div');
        newFooter.id = 'detail-footer';
        newFooter.innerHTML = footerContent;
        modalContent.appendChild(newFooter);
        
        // También configurar el select si se creó dinámicamente
        setTimeout(() => {
            if (event.type === 'ticket' || event.type === 'recurrent_task' || event.type === 'task') {
                const select = document.getElementById('estado-select');
                if (select) {
                    // Asignar valor según el tipo de evento
                    if (event.type === 'ticket') {
                        select.value = String(event.db_data.estado || '');
                    } else if (event.type === 'recurrent_task' || event.type === 'task') {
                        select.value = String(event.status || '');
                    }

                    // Asignar evento
                    select.onchange = function () {
                        if (confirm(`¿Estás seguro de cambiar el estado a "${this.value}"?`)) {
                            if (event.type === 'ticket') {
                                cambiarEstadoTicket(event.db_data.folio, this.value);
                            } else if (event.type === 'recurrent_task') {
                                cambiarEstadoCiclica(event.id, this.value);
                            } else if (event.type === 'task') {
                                cambiarEstadoTarea(event.id, this.value);
                            }
                        } else {
                            // Revertir al valor anterior
                            if (event.type === 'ticket') {
                                this.value = String(event.db_data.estado || '');
                            } else {
                                this.value = String(event.status || '');
                            }
                        }
                    };
                }
            }
        }, 10);
    }

    // Guarda el folio del ticket en el modal
    const modal = document.getElementById('detail-modal');
    modal.setAttribute('data-ticket-id', event.db_data?.folio || event.id);
    modal.setAttribute('data-tipo', event.type);

    // Mostrar el modal
    modal.classList.remove('hidden');

    editingItemId = event.id;
}

// Funciones auxiliares
function getPriorityColorClass(priority) {
    return priority == 0 ? 'text-purple-600' :
           priority == 2 ? 'text-red-600' :
           priority == 7 ? 'text-yellow-600' :
           priority == 15 ? 'text-green-600' :
           priority == 30 ? 'text-blue-600' :
           priority == 60 ? 'text-indigo-600' :
           priority == 90 ? 'text-pink-600' :
           priority == 180 ? 'text-orange-600' :
           'text-gray-600';
}

function getPriorityText(priority) {
    return priority == 0 ? 'Mismo día' :
           priority == 2 ? 'Alto - máx 2 días' :
           priority == 7 ? 'Medio - máx 7 días' :
           priority == 15 ? 'Bajo - máx 15 días' :
           priority == 30 ? 'Act Mensual - máx 30 días' :
           priority == 60 ? 'Act Bimestral - máx 60 días' :
           priority == 90 ? 'Act Trimestral - máx 90 días' :
           priority == 180 ? 'Semestral - máx 180 días' :
           priority == 365 ? 'Anual - máx 365 días' :
           'Sin definir';
}

function getTaskPriorityClass(priority) {
    return priority === 'alta' ? 'bg-red-100 text-red-800' : 
           priority === 'media' ? 'bg-yellow-100 text-yellow-800' : 
           'bg-green-100 text-green-800';
}



// Función para tickets
function cambiarEstadoTicket(folio, nuevoEstado) {
    const select = document.getElementById('estado-select');
    
    if (select) {
        select.disabled = true;
    }
    
    fetch('tickets/actualizar_ticket.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            id: folio,
            campo: 'estado',
            valor: nuevoEstado
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            mostrarAlerta(`Ticket ${folio}: Estado cambiado a ${nuevoEstado}`, 'success');
            actualizarEventoEnCalendario('ticket', folio, nuevoEstado);
        } else {
            mostrarAlerta(`Error: ${data.error}`, 'error');
            if (select) {
                select.value = select.oldValue || '';
            }
        }
    })
    .catch(error => {
        mostrarAlerta('Error de conexión', 'error');
        if (select) {
            select.value = select.oldValue || '';
        }
    })
    .finally(() => {
        if (select) {
            select.disabled = false;
        }
    });
}

// Función para tareas cíclicas
function cambiarEstadoCiclica(id, nuevoEstado) {
    const select = document.getElementById('estado-select');
    
    if (select) {
        select.disabled = true;
    }
    
    fetch('task/actualizar_ciclica.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            id: id,
            estado: nuevoEstado
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            mostrarAlerta(`Tarea cíclica: Estado cambiado a ${nuevoEstado}`, 'success');
            actualizarEventoEnCalendario('recurrent_task', id, nuevoEstado);
        } else {
            mostrarAlerta(`Error: ${data.error}`, 'error');
            if (select) {
                select.value = select.oldValue || '';
            }
        }
    })
    .catch(error => {
        mostrarAlerta('Error de conexión', 'error');
        if (select) {
            select.value = select.oldValue || '';
        }
    })
    .finally(() => {
        if (select) {
            select.disabled = false;
        }
    });
}

// Función para tareas normales (ajusta el endpoint según necesites)
function cambiarEstadoTarea(id, nuevoEstado) {
    const select = document.getElementById('estado-select');
    
    if (select) {
        select.disabled = true;
    }
    
    fetch('task/actualizar_tarea.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            id: id,
            estado: nuevoEstado
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            mostrarAlerta(`Tarea: Estado cambiado a ${nuevoEstado}`, 'success');
            actualizarEventoEnCalendario('task', id, nuevoEstado);
        } else {
            mostrarAlerta(`Error: ${data.error}`, 'error');
            if (select) {
                select.value = select.oldValue || '';
            }
        }
    })
    .catch(error => {
        mostrarAlerta('Error de conexión', 'error');
        if (select) {
            select.value = select.oldValue || '';
        }
    })
    .finally(() => {
        if (select) {
            select.disabled = false;
        }
    });
}

// Función para actualizar en calendario
function actualizarEventoEnCalendario(tipo, id, nuevoEstado) {
    if (typeof calendar !== 'undefined' && calendar) {
        const events = calendar.getEvents();
        const evento = events.find(e => {
            if (e.extendedProps.type === tipo) {
                if (tipo === 'ticket') {
                    return e.extendedProps.db_data?.folio === id;
                } else {
                    return e.extendedProps.id === id;
                }
            }
            return false;
        });
        
        if (evento) {
            if (tipo === 'ticket') {
                evento.setExtendedProp('db_data', {
                    ...evento.extendedProps.db_data,
                    estado: nuevoEstado
                });
            } else {
                evento.setExtendedProp('status', nuevoEstado);
            }
            
            // Actualizar color
            const color = obtenerColorPorEstado(nuevoEstado);
            evento.setProp('backgroundColor', color);
            evento.setProp('borderColor', color);
            
            calendar.refetchEvents();
        }
    }
}

function actualizarEstadoEnCalendario(folio, nuevoEstado) {
    // Buscar el evento en el calendario de FullCalendar
    if (typeof calendar !== 'undefined' && calendar) {
        const events = calendar.getEvents();
        const evento = events.find(e =>
            e.extendedProps &&
            e.extendedProps.db_data &&
            e.extendedProps.db_data.folio === folio
        );

        if (evento) {
            // Actualizar las propiedades del evento
            evento.setExtendedProp('db_data', {
                ...evento.extendedProps.db_data,
                estado: nuevoEstado
            });

            // También actualizar el título si es necesario
            evento.setProp('title', `${folio} - ${nuevoEstado}`);

            // Cambiar color según el estado
            const color = obtenerColorPorEstado(nuevoEstado);
            evento.setProp('backgroundColor', color);
            evento.setProp('borderColor', color);

            // Refrescar el calendario
            calendar.refetchEvents();
        }
    }
}

function obtenerColorPorEstado(estado) {
    const colores = {
        'Pendiente': '#eab308',     // amarillo
        'En Proceso': '#3b82f6',    // azul
        'Resuelto': '#10b981',      // verde
        'Cerrado': '#6b7280',       // gris
        'Cancelado': '#ef4444'      // rojo
    };
    return colores[estado] || '#6b7280';
}

function actualizarEstadoEnInterfaz(folio, nuevoEstado) {
    // Si tienes una tabla de tickets, actualizar ahí también
    const filas = document.querySelectorAll(`[data-folio="${folio}"]`);
    filas.forEach(fila => {
        const celdaEstado = fila.querySelector('.estado-ticket');
        if (celdaEstado) {
            celdaEstado.textContent = nuevoEstado;
            celdaEstado.className = `estado-ticket ${obtenerClaseEstado(nuevoEstado)}`;
        }
    });
}

function obtenerClaseEstado(estado) {
    const clases = {
        'Pendiente': 'bg-yellow-100 text-yellow-800',
        'En Proceso': 'bg-blue-100 text-blue-800',
        'Resuelto': 'bg-green-100 text-green-800',
        'Cerrado': 'bg-gray-100 text-gray-800',
        'Cancelado': 'bg-red-100 text-red-800'
    };
    return clases[estado] || 'bg-gray-100 text-gray-800';
}

function mostrarAlerta(mensaje, tipo = 'info') {
    // Si usas SweetAlert2
    if (typeof Swal !== 'undefined') {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.onmouseenter = Swal.stopTimer;
                toast.onmouseleave = Swal.resumeTimer;
            }
        });

        Toast.fire({
            icon: tipo,
            title: mensaje
        });
    } else {
        // Si no tienes SweetAlert2, crear alerta simple
        const alertDiv = document.createElement('div');
        alertDiv.className = `fixed top-4 right-4 z-50 px-4 py-3 rounded-lg shadow-lg ${tipo === 'success' ? 'bg-green-100 text-green-700 border border-green-400' :
                tipo === 'error' ? 'bg-red-100 text-red-700 border border-red-400' :
                    'bg-blue-100 text-blue-700 border border-blue-400'
            }`;
        alertDiv.innerHTML = `
            <div class="flex items-center">
                <i class="fas fa-${tipo === 'success' ? 'check-circle' :
                tipo === 'error' ? 'exclamation-circle' :
                    'info-circle'
            } mr-2"></i>
                <span>${mensaje}</span>
            </div>
        `;

        document.body.appendChild(alertDiv);

        // Remover después de 3 segundos
        setTimeout(() => {
            alertDiv.remove();
        }, 3000);
    }
}

// Función para generar URL de WhatsApp
function generarURLWhatsApp(event) {
    const telefono = event.db_data.whatsapp ? event.db_data.whatsapp.replace(/\D/g, '') : '';
    if (!telefono) return '#';

    const folio = event.db_data.folio || 'Sin folio';
    const descripcion = event.db_data.descripcion || event.description || 'Sin descripción';
    const fecha = event.db_data.fecha_creacion || 'No disponible';
    const estado = event.db_data.estado || 'Pendiente';

    const mensaje = `👋 Hola, soy del área de soporte.\n\nTe contacto respecto al ticket *${folio}* 🧾\n_Asunto:_ ${descripcion}\n\nPodrías brindarme más detalles o apoyo para continuar con la atención del ticket.\n\n📅 *Fecha de creación:* ${fecha}\n🏷️ *Estado actual:* ${estado}\n\nGracias por tu tiempo 🙌`;

    return `https://wa.me/${telefono}?text=${encodeURIComponent(mensaje)}`;
}

// Función para generar URL de Google Calendar
function generarURLGoogleCalendar(event) {
    const fecha = new Date(event.date);
    const fechaInicio = fecha.toISOString().replace(/[-:]/g, '').split('.')[0] + 'Z';
    const fechaFin = new Date(fecha.getTime() + 60 * 60 * 1000).toISOString().replace(/[-:]/g, '').split('.')[0] + 'Z';

    const detalles = `details=${encodeURIComponent(event.description || 'Reunión')}`;
    const lugar = `location=${encodeURIComponent(event.location || '')}`;
    const titulo = `text=${encodeURIComponent(event.title || 'Reunión')}`;
    const fechas = `dates=${fechaInicio}/${fechaFin}`;

    return `https://calendar.google.com/calendar/render?action=TEMPLATE&${fechas}&${titulo}&${detalles}&${lugar}`;
}
// Funciones del modal
function openModal() {
    document.getElementById('item-modal').classList.remove('hidden');
    document.getElementById('type-selection').classList.remove('hidden');
    showForm('ticket');
    editingItemId = null;
}

function closeModal() {
    document.getElementById('item-modal').classList.add('hidden');
}

function closeDetailModal() {
    document.getElementById('detail-modal').classList.add('hidden');
    editingItemId = null;
}

function showForm(type) {
    document.querySelectorAll('.item-form').forEach(form => form.classList.add('hidden'));
    document.getElementById(`${type}-form`).classList.remove('hidden');

    const titles = {
        'ticket': 'Nuevo Ticket',
        'task': 'Nueva Tarea',
        'meeting': 'Nueva Reunión'
    };
    document.getElementById('modal-title').textContent = titles[type];
}

async function saveItem() {
    // Obtenemos los valores desde los select y textarea
    const formContainer = document.getElementById("ticket-form");

    const formData = new FormData();
    formData.append("area", formContainer.querySelector("#area").value);
    formData.append("donde", formContainer.querySelector("#donde").value);
    formData.append("detalle_donde", formContainer.querySelector("#detalle_donde").value);
    formData.append("categoria_servicio", formContainer.querySelector("#categoria").value);
    formData.append("subcategoria", formContainer.querySelector("#subcategoria").value);
    formData.append("descripcion", formContainer.querySelector("#descripcion").value);

    try {
        const response = await fetch("tickets/insert_ticket.php", {
            method: "POST",
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            alert("✅ " + result.message);
            closeModal(); // cerrar modal
            // Limpia los campos después de guardar
            formContainer.querySelectorAll("select, textarea").forEach(el => el.value = "");
        } else {
            alert("⚠️ " + result.message);
        }
    } catch (error) {
        alert("❌ Error al enviar el ticket: " + error.message);
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', initApp);