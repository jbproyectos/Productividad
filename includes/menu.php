<?php
// menu.php - Menú profesional colapsable
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$script_path = $_SERVER['SCRIPT_NAME'];
$base_dir = dirname($script_path);

if ($base_dir == '/') {
    $base_url = $protocol . "://" . $host . "/";
} else {
    $base_url = $protocol . "://" . $host . $base_dir . "/";
}

$base_url = preg_replace('/([^:])(\/{2,})/', '$1/', $base_url);
$raiz = '/prod/';

$current_page = basename($_SERVER['PHP_SELF']);

// Obtener información del usuario
$user_name = $_SESSION['user_nombre'] ?? 'Usuario';
$user_email = $_SESSION['user_email'] ?? 'usuario@ejemplo.com';
$user_rol = $_SESSION['user_rol'] ?? 'Administrador';
$user_avatar = $_SESSION['user_avatar'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($user_name) . '&background=3B82F6&color=fff';

// Verificar si el menú está colapsado (por defecto expandido)
$menu_colapsado = isset($_COOKIE['menu_colapsado']) ? $_COOKIE['menu_colapsado'] === 'true' : false;
?>

<style>
    /* Variables */
    :root {
        --sidebar-width: 280px;
        --sidebar-collapsed-width: 80px;
        --primary-color: #3B82F6;
        --primary-dark: #2563EB;
        --text-primary: #0F172A;
        --text-secondary: #475569;
        --text-muted: #64748B;
        --bg-hover: #F1F5F9;
        --border-color: #E2E8F0;
    }

    /* Estilos base del sidebar */
    .sidebar {
        height: 100vh;
        top: 0;
        left: 0;
        width: var(--sidebar-width);
        background: white;
        box-shadow: 4px 0 20px rgba(0, 0, 0, 0.03);
        z-index: 50;
        transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        overflow-x: hidden;
        overflow-y: auto;
        border-right: 1px solid var(--border-color);
    }

    .sidebar.collapsed {
        width: var(--sidebar-collapsed-width);
    }

    .sidebar::-webkit-scrollbar {
        width: 4px;
    }

    .sidebar::-webkit-scrollbar-track {
        background: #F1F5F9;
    }

    .sidebar::-webkit-scrollbar-thumb {
        background: #CBD5E1;
        border-radius: 4px;
    }

    /* Header del sidebar */
    .sidebar-header {
        padding: 1.5rem 1rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: 1px solid var(--border-color);
        height: 80px;
    }

    .brand {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .brand-icon {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, var(--primary-color), #8B5CF6);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.2rem;
        font-weight: bold;
        flex-shrink: 0;
    }

    .brand-text {
        transition: opacity 0.2s ease;
        white-space: nowrap;
    }

    .brand-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--text-primary);
        line-height: 1.2;
    }

    .brand-subtitle {
        font-size: 0.7rem;
        color: var(--text-muted);
    }

    /* Botón colapsar */
    .collapse-btn {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-secondary);
        background: transparent;
        border: none;
        cursor: pointer;
        transition: all 0.2s;
        flex-shrink: 0;
    }

    .collapse-btn:hover {
        background: var(--bg-hover);
        color: var(--primary-color);
    }

    .collapse-btn i {
        transition: transform 0.3s ease;
    }

    .sidebar.collapsed .collapse-btn i {
        transform: rotate(180deg);
    }

    /* Perfil de usuario */
    .user-profile {
        padding: 1rem;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        gap: 0.75rem;
        min-height: 80px;
    }

    .user-avatar {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        object-fit: cover;
        border: 2px solid white;
        box-shadow: 0 4px 10px rgba(59, 130, 246, 0.15);
        flex-shrink: 0;
    }

    .user-info {
        overflow: hidden;
        transition: opacity 0.2s ease;
        white-space: nowrap;
    }

    .user-name {
        font-weight: 600;
        color: var(--text-primary);
        font-size: 0.95rem;
    }

    .user-email {
        font-size: 0.7rem;
        color: var(--text-muted);
    }

    .user-role {
        display: inline-block;
        padding: 0.2rem 0.75rem;
        background: #DBEAFE;
        color: #0369A1;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
        margin-top: 0.25rem;
    }

    /* Navegación */
    .nav-section {
        padding: 1rem 0.75rem;
    }

    .nav-item {
        display: flex;
        align-items: center;
        padding: 0.75rem;
        margin: 0.25rem 0;
        border-radius: 10px;
        color: var(--text-secondary);
        transition: all 0.2s ease;
        position: relative;
        white-space: nowrap;
    }

    .nav-item:hover {
        background: var(--bg-hover);
        color: var(--text-primary);
    }

    .nav-item.active {
        background: linear-gradient(90deg, #EFF6FF, white);
        color: var(--primary-color);
        font-weight: 500;
    }

    .nav-item i {
        width: 24px;
        font-size: 1.1rem;
        text-align: center;
        flex-shrink: 0;
    }

    .nav-item span {
        margin-left: 12px;
        font-size: 0.9rem;
        transition: opacity 0.2s ease;
    }

    /* Badges */
    .badge {
        margin-left: 8px;
        padding: 0.15rem 0.5rem;
        border-radius: 12px;
        font-size: 0.6rem;
        font-weight: 600;
        white-space: nowrap;
        flex-shrink: 0;
    }

    .badge-dev {
        background: #FEF3C7;
        color: #92400E;
    }

    .badge-new {
        background: #10B981;
        color: white;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.8; }
    }

    /* Catálogos */
    .catalog-item {
        margin: 0.25rem 0;
    }

    .catalog-trigger {
        display: flex;
        align-items: center;
        justify-content: space-between;
        width: 100%;
        padding: 0.75rem;
        border-radius: 10px;
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.2s;
        white-space: nowrap;
    }

    .catalog-trigger:hover {
        background: var(--bg-hover);
    }

    .catalog-trigger .left-content {
        display: flex;
        align-items: center;
    }

    .catalog-trigger i:first-child {
        width: 24px;
        font-size: 1.1rem;
        text-align: center;
        flex-shrink: 0;
    }

    .catalog-trigger span {
        margin-left: 12px;
        font-size: 0.9rem;
        transition: opacity 0.2s ease;
    }

    .catalog-trigger .fa-chevron-down {
        width: 20px;
        text-align: center;
        transition: transform 0.3s;
        flex-shrink: 0;
    }

    .catalog-trigger .fa-chevron-down.rotated {
        transform: rotate(180deg);
    }

    /* Submenús */
    .submenu {
        margin-left: 36px;
        margin-top: 0.25rem;
        overflow: hidden;
        transition: max-height 0.3s ease;
    }

    .submenu.hidden {
        max-height: 0;
    }

    .submenu-item {
        display: flex;
        align-items: center;
        padding: 0.5rem 0.75rem;
        border-radius: 8px;
        color: var(--text-muted);
        font-size: 0.85rem;
        transition: all 0.2s;
        white-space: nowrap;
    }

    .submenu-item:hover {
        background: var(--bg-hover);
        color: var(--text-primary);
    }

    .submenu-item.active {
        background: #DBEAFE;
        color: #1E40AF;
    }

    .submenu-item i {
        width: 18px;
        font-size: 0.9rem;
        margin-right: 8px;
        flex-shrink: 0;
    }

    /* Separador */
    .nav-divider {
        height: 1px;
        background: linear-gradient(90deg, transparent, var(--border-color), transparent);
        margin: 1rem 0;
    }

    /* Footer */
    .sidebar-footer {
        padding: 1rem 0.75rem;
        border-top: 1px solid var(--border-color);
        margin-top: auto;
    }

    .logout-item {
        display: flex;
        align-items: center;
        padding: 0.75rem;
        border-radius: 10px;
        color: #EF4444;
        background: #FEF2F2;
        transition: all 0.2s;
        white-space: nowrap;
    }

    .logout-item:hover {
        background: #FEE2E2;
    }

    .logout-item i {
        width: 24px;
        font-size: 1.1rem;
        text-align: center;
        flex-shrink: 0;
    }

    .logout-item span {
        margin-left: 12px;
        font-size: 0.9rem;
        font-weight: 500;
        transition: opacity 0.2s ease;
    }

    /* Tooltips para modo colapsado */
    .sidebar.collapsed [data-tooltip] {
        position: relative;
    }

    .sidebar.collapsed [data-tooltip]:hover::after {
        content: attr(data-tooltip);
        position: absolute;
        left: 100%;
        top: 50%;
        transform: translateY(-50%);
        background: #1E293B;
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 6px;
        font-size: 0.8rem;
        white-space: nowrap;
        z-index: 100;
        margin-left: 10px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        pointer-events: none;
    }

    .sidebar.collapsed [data-tooltip]:hover::before {
        content: '';
        position: absolute;
        left: 100%;
        top: 50%;
        transform: translateY(-50%);
        border: 5px solid transparent;
        border-right-color: #1E293B;
        margin-left: 0;
    }

    /* Ocultar textos en modo colapsado */
    .sidebar.collapsed .brand-text,
    .sidebar.collapsed .user-info,
    .sidebar.collapsed .nav-item span:not(.badge),
    .sidebar.collapsed .catalog-trigger span:not(.badge),
    .sidebar.collapsed .logout-item span {
        opacity: 0;
        width: 0;
        display: none;
    }

    .sidebar.collapsed .badge {
        position: absolute;
        left: 40px;
        top: 50%;
        transform: translateY(-50%);
        margin: 0;
    }

    .sidebar.collapsed .catalog-trigger .fa-chevron-down {
        display: none;
    }

    .sidebar.collapsed .submenu {
        display: none;
    }

    /* Versión móvil */
    .sidebar-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(4px);
        z-index: 45;
    }

    .mobile-toggle {
        display: none;
        position: fixed;
        top: 1rem;
        left: 1rem;
        z-index: 40;
        width: 40px;
        height: 40px;
        background: white;
        border-radius: 10px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        border: 1px solid var(--border-color);
        cursor: pointer;
    }

    @media (max-width: 1024px) {
        .sidebar {
            transform: translateX(-100%);
            transition: transform 0.3s ease;
        }
        
        .sidebar.open {
            transform: translateX(0);
        }
        
        .sidebar.collapsed {
            width: var(--sidebar-width);
        }
        
        .sidebar.collapsed .brand-text,
        .sidebar.collapsed .user-info,
        .sidebar.collapsed .nav-item span,
        .sidebar.collapsed .catalog-trigger span,
        .sidebar.collapsed .logout-item span {
            opacity: 1;
            width: auto;
            display: inline;
        }
        
        .sidebar.collapsed .badge {
            position: static;
            transform: none;
        }
        
        .sidebar.collapsed .catalog-trigger .fa-chevron-down {
            display: inline-block;
        }
        
        .sidebar.collapsed .submenu {
            display: block;
        }
        
        .mobile-toggle {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .sidebar-overlay.active {
            display: block;
        }
    }

    /* Ocultar textos y badges en modo colapsado */
.sidebar.collapsed .brand-text,
.sidebar.collapsed .user-info,
.sidebar.collapsed .nav-item span,
.sidebar.collapsed .catalog-trigger span,
.sidebar.collapsed .logout-item span {
    opacity: 0;
    width: 0;
    display: none;
}

/* Ocultar badges completamente */
.sidebar.collapsed .badge,
.sidebar.collapsed .badge-dev,
.sidebar.collapsed .badge-new {
    display: none !important;
    opacity: 0;
    width: 0;
    height: 0;
    visibility: hidden;
}

/* Opcional: Si quieres que los badges aparezcan en el tooltip */
.sidebar.collapsed [data-tooltip] {
    position: relative;
}

.sidebar.collapsed [data-tooltip]:hover::after {
    content: attr(data-tooltip);
    position: absolute;
    left: 100%;
    top: 50%;
    transform: translateY(-50%);
    background: #1E293B;
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-size: 0.8rem;
    white-space: nowrap;
    z-index: 100;
    margin-left: 10px;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    pointer-events: none;
}

.sidebar.collapsed [data-tooltip]:hover::before {
    content: '';
    position: absolute;
    left: 100%;
    top: 50%;
    transform: translateY(-50%);
    border: 5px solid transparent;
    border-right-color: #1E293B;
    margin-left: 0;
}

/* Ocultar completamente los íconos de chevron en modo colapsado */
.sidebar.collapsed .catalog-trigger .fa-chevron-down {
    display: none !important;
    opacity: 0;
    width: 0;
}

/* Ocultar submenús completos en modo colapsado */
.sidebar.collapsed .submenu {
    display: none !important;
    max-height: 0;
    opacity: 0;
    visibility: hidden;
}

/* Asegurar que los elementos no ocupen espacio */
.sidebar.collapsed .catalog-trigger {
    justify-content: center;
    padding: 0.75rem 0;
}

.sidebar.collapsed .catalog-trigger .left-content {
    justify-content: center;
    width: 100%;
}

.sidebar.collapsed .catalog-trigger i:first-child {
    margin: 0 auto;
}

/* Ajustar el padding de los items cuando está colapsado */
.sidebar.collapsed .nav-item {
    justify-content: center;
    padding: 0.75rem 0;
}

.sidebar.collapsed .nav-item i {
    margin: 0 auto;
}

.sidebar.collapsed .logout-item {
    justify-content: center;
    padding: 0.75rem 0;
}

.sidebar.collapsed .logout-item i {
    margin: 0 auto;
}
</style>

<!-- Overlay para móvil -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Botón toggle móvil -->
<button class="mobile-toggle" id="mobileToggle">
    <i class="fas fa-bars text-gray-600"></i>
</button>

<!-- Sidebar -->
<div class="sidebar <?= $menu_colapsado ? 'collapsed' : '' ?>" id="sidebar">
    <!-- Header -->
    <div class="sidebar-header">
        <div class="brand">
            <div class="brand-icon">F</div>
            <div class="brand-text">
                <div class="brand-title">Flow</div>
                <div class="brand-subtitle">Gestión de productividad</div>
            </div>
        </div>
        <button class="collapse-btn" id="collapseBtn" title="Colapsar menú">
            <i class="fas fa-chevron-left"></i>
        </button>
    </div>

    <!-- Perfil -->
    <div class="user-profile">
        <img src="<?= $user_avatar ?>" alt="Avatar" class="user-avatar">
        <div class="user-info">
            <div class="user-name"><?= htmlspecialchars($user_name) ?></div>
            <div class="user-email"><?= htmlspecialchars($user_email) ?></div>
            <span class="user-role"><?= htmlspecialchars($user_rol) ?></span>
        </div>
    </div>

    <!-- Navegación -->
    <div class="nav-section">
        <!-- Calendario -->
        <a href="<?= $raiz ?>calendar.php" 
           class="nav-item <?= ($current_page == 'calendar.php') ? 'active' : '' ?>"
           data-tooltip="Calendario">
            <i class="fas fa-calendar-alt"></i>
            <span>Calendario</span>
        </a>

        <!-- Tareas -->
        <a href="<?= $raiz ?>tasks.php" 
           class="nav-item <?= ($current_page == 'tasks.php') ? 'active' : '' ?>"
           data-tooltip="Tareas">
            <i class="fas fa-tasks"></i>
            <span>Tareas</span>
            <span class="badge badge-dev">Dev</span>
        </a>

        <!-- Tickets área -->
        <a href="<?= $raiz ?>tickets.php" 
           class="nav-item <?= ($current_page == 'tickets.php') ? 'active' : '' ?>"
           data-tooltip="Tickets (toda mi área)">
            <i class="fas fa-ticket-alt"></i>
            <span>Tickets (área)</span>
        </a>

        <!-- Mis Tickets -->
        <a href="<?= $raiz ?>mis-tickets.php" 
           class="nav-item <?= ($current_page == 'mis-tickets.php') ? 'active' : '' ?>"
           data-tooltip="Mis Tickets">
            <i class="fas fa-ticket-alt"></i>
            <span>Mis Tickets</span>
            <span class="badge badge-new">New</span>
        </a>

        <div class="nav-divider"></div>

        <!-- Catálogo Tickets -->
        <div class="catalog-item">
            <div class="catalog-trigger" onclick="toggleSubmenu('tickets')" data-tooltip="Catálogo Tickets">
                <div class="left-content">
                    <i class="fas fa-folder-open"></i>
                    <span>Catálogos</span>
                </div>
                <i class="fas fa-chevron-down" id="arrow-tickets"></i>
            </div>
            <div class="submenu hidden" id="submenu-tickets">
                <a href="<?= $raiz ?>catalogos/areas.php" 
                   class="submenu-item <?= (strpos($_SERVER['REQUEST_URI'], 'catalogos/areas.php') !== false) ? 'active' : '' ?>">
                    <i class="fas fa-building"></i>
                    <span>Áreas</span>
                </a>
                <a href="<?= $raiz ?>catalogos/categoria_servicio.php" 
                   class="submenu-item <?= (strpos($_SERVER['REQUEST_URI'], 'catalogos/categoria_servicio.php') !== false) ? 'active' : '' ?>">
                    <i class="fas fa-list-alt"></i>
                    <span>Categorías</span>
                </a>
                <a href="<?= $raiz ?>catalogos/donde.php" 
                   class="submenu-item <?= (strpos($_SERVER['REQUEST_URI'], 'catalogos/donde.php') !== false) ? 'active' : '' ?>">
                    <i class="fas fa-project-diagram"></i>
                    <span>Proyectos</span>
                </a>
                <a href="<?= $raiz ?>catalogos/subcategoria.php" 
                   class="submenu-item <?= (strpos($_SERVER['REQUEST_URI'], 'catalogos/subcategoria.php') !== false) ? 'active' : '' ?>">
                    <i class="fas fa-tags"></i>
                    <span>Subcategorías</span>
                </a>
                <a href="<?= $raiz ?>catalogos/detalle_donde.php" 
                   class="submenu-item <?= (strpos($_SERVER['REQUEST_URI'], 'catalogos/detalle_donde.php') !== false) ? 'active' : '' ?>">
                    <i class="fas fa-file-alt"></i>
                    <span>Detalle donde</span>
                </a>
                
            </div>
        </div>

        <!-- Catálogo Productividad -->
        <div class="catalog-item hidden">
            <div class="catalog-trigger" onclick="toggleSubmenu('prod')" data-tooltip="Catálogo Productividad">
                <div class="left-content">
                    <i class="fas fa-folder-open"></i>
                    <span>Catálogo Productividad</span>
                </div>
                <i class="fas fa-chevron-down" id="arrow-prod"></i>
            </div>
            <div class="submenu hidden" id="submenu-prod">
                <a href="<?= $raiz ?>catalogos-prod/areas.php" 
                   class="submenu-item <?= (strpos($_SERVER['REQUEST_URI'], 'catalogos-prod/areas.php') !== false) ? 'active' : '' ?>">
                    <i class="fas fa-building"></i>
                    <span>Áreas Prod</span>
                </a>
                <a href="<?= $raiz ?>catalogos-prod/proyectos.php" 
                   class="submenu-item <?= (strpos($_SERVER['REQUEST_URI'], 'catalogos-prod/proyectos.php') !== false) ? 'active' : '' ?>">
                    <i class="fas fa-tasks"></i>
                    <span>Proyectos</span>
                </a>
                <a href="<?= $raiz ?>catalogos-prod/tipos-tarea.php" 
                   class="submenu-item <?= (strpos($_SERVER['REQUEST_URI'], 'catalogos-prod/tipos-tarea.php') !== false) ? 'active' : '' ?>">
                    <i class="fas fa-list"></i>
                    <span>Tipos Tarea</span>
                </a>
                <a href="<?= $raiz ?>catalogos-prod/estados.php" 
                   class="submenu-item <?= (strpos($_SERVER['REQUEST_URI'], 'catalogos-prod/estados.php') !== false) ? 'active' : '' ?>">
                    <i class="fas fa-flag"></i>
                    <span>Estados</span>
                </a>
                <a href="<?= $raiz ?>catalogos-prod/prioridades.php" 
                   class="submenu-item <?= (strpos($_SERVER['REQUEST_URI'], 'catalogos-prod/prioridades.php') !== false) ? 'active' : '' ?>">
                    <i class="fas fa-exclamation-circle"></i>
                    <span>Prioridades</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="sidebar-footer">
        <!-- Perfil (link) -->
        <a href="<?= $raiz ?>perfil.php" 
           class="nav-item <?= ($current_page == 'perfil.php') ? 'active' : '' ?>"
           data-tooltip="Mi Perfil">
            <i class="fas fa-user-circle"></i>
            <span>Mi Perfil</span>
        </a>

        <!-- Información -->
        <a href="<?= $raiz ?>informacion.php" 
           class="nav-item <?= ($current_page == 'informacion.php') ? 'active' : '' ?>"
           data-tooltip="Información">
            <i class="fas fa-info-circle"></i>
            <span>Información</span>
        </a>

        <!-- Logout -->
        <a href="<?= $raiz ?>logout.php" 
           class="logout-item"
           data-tooltip="Cerrar Sesión">
            <i class="fas fa-sign-out-alt"></i>
            <span>Cerrar Sesión</span>
        </a>

        <!-- Versión -->
        <div class="text-center mt-4" style="color: var(--text-muted); font-size: 0.65rem;">
            <span>Versión 2.0.0</span>
        </div>
    </div>
</div>

<script>
    // Elementos del DOM
    const sidebar = document.getElementById('sidebar');
    const collapseBtn = document.getElementById('collapseBtn');
    const mobileToggle = document.getElementById('mobileToggle');
    const overlay = document.getElementById('sidebarOverlay');

    // Función para colapsar/expandir menú
    function toggleCollapse() {
        sidebar.classList.toggle('collapsed');
        const isCollapsed = sidebar.classList.contains('collapsed');
        
        // Guardar preferencia en cookie por 30 días
        document.cookie = `menu_colapsado=${isCollapsed}; path=/; max-age=${30 * 24 * 60 * 60}`;
        
        // Cerrar todos los submenús al colapsar
        if (isCollapsed) {
            document.querySelectorAll('.submenu').forEach(sub => sub.classList.add('hidden'));
            document.querySelectorAll('.catalog-trigger .fa-chevron-down').forEach(arrow => {
                arrow.classList.remove('rotated');
            });
        }
    }

    // Función para toggle de submenús
    function toggleSubmenu(target) {
        if (sidebar.classList.contains('collapsed')) return;
        
        const submenu = document.getElementById(`submenu-${target}`);
        const arrow = document.getElementById(`arrow-${target}`);
        
        if (!submenu || !arrow) return;
        
        // Cerrar otros submenús
        document.querySelectorAll('[id^="submenu-"]').forEach(menu => {
            if (menu.id !== `submenu-${target}`) {
                menu.classList.add('hidden');
            }
        });
        
        document.querySelectorAll('[id^="arrow-"]').forEach(icon => {
            if (icon.id !== `arrow-${target}`) {
                icon.classList.remove('rotated');
            }
        });
        
        // Toggle actual
        submenu.classList.toggle('hidden');
        arrow.classList.toggle('rotated');
    }

    // Funciones para móvil
    function openMobileMenu() {
        sidebar.classList.add('open');
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeMobileMenu() {
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    // Event listeners
    if (collapseBtn) {
        collapseBtn.addEventListener('click', toggleCollapse);
    }

    if (mobileToggle) {
        mobileToggle.addEventListener('click', openMobileMenu);
    }

    if (overlay) {
        overlay.addEventListener('click', closeMobileMenu);
    }

    // Cerrar menú al redimensionar a desktop
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 1024) {
            closeMobileMenu();
        }
    });

    // Inicializar tooltips y submenús según la página actual
    document.addEventListener('DOMContentLoaded', function() {
        const currentPath = window.location.pathname;
        
        // Abrir submenús según la página actual
        if (currentPath.includes('/catalogos/')) {
            document.getElementById('submenu-tickets')?.classList.remove('hidden');
            document.getElementById('arrow-tickets')?.classList.add('rotated');
        } else if (currentPath.includes('/catalogos-prod/')) {
            document.getElementById('submenu-prod')?.classList.remove('hidden');
            document.getElementById('arrow-prod')?.classList.add('rotated');
        }
        
        // Prevenir clicks en catalog-trigger si está colapsado
        if (sidebar.classList.contains('collapsed')) {
            document.querySelectorAll('.catalog-trigger').forEach(trigger => {
                trigger.addEventListener('click', (e) => e.preventDefault());
            });
        }
    });

    // Prevenir comportamiento por defecto de los triggers de catálogo
    document.querySelectorAll('.catalog-trigger').forEach(trigger => {
        trigger.addEventListener('click', function(e) {
            if (sidebar.classList.contains('collapsed')) {
                e.preventDefault();
            }
        });
    });
</script>