<?php
// config.php
define('EN_DESARROLLO', true);

// Lista de módulos habilitados
$modulos_habilitados = [
    'calendar' => true,
    'kanban' => true,
    'tasks' => true,
    'tickets' => true,
    'mis_tickets' => true,
    'meetings' => false,      // En desarrollo
    'notices' => false,       // En desarrollo
    'metrics' => false,       // En desarrollo
    'evaluations' => false,   // En desarrollo
    'analisis_personal' => false, // En desarrollo
    'catalogos_prod' => false,    // En desarrollo
    'catalogos_reuni' => false,   // En desarrollo
];