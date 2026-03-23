<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include '../includes/conexionbd.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$id_tarea = $_GET['id'] ?? 0;

// Obtener datos de la tarea
$stmt = $pdo->prepare("SELECT * FROM tareas WHERE id_tarea = ? AND activo = 1");
$stmt->execute([$id_tarea]);
$tarea = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$tarea) {
    $_SESSION['error'] = "Tarea no encontrada";
    header('Location: ../tasks.php?tab=unicas');
    exit;
}

$user_id = $_SESSION['user_id'];

// Obtener listados para selects
$stmt_areas = $pdo->query("SELECT id, nombre FROM areas ORDER BY nombre");
$areas = $stmt_areas->fetchAll(PDO::FETCH_ASSOC);

$stmt_usuarios = $pdo->query("SELECT Id_Usuario, nombre, apellido FROM usuarios ORDER BY nombre");
$usuarios = $stmt_usuarios->fetchAll(PDO::FETCH_ASSOC);

$stmt_categorias = $pdo->query("SELECT id, nombre_cat FROM categoria_servicio_ticket ORDER BY nombre_cat");
$categorias = $stmt_categorias->fetchAll(PDO::FETCH_ASSOC);

$stmt_donde = $pdo->query("SELECT id, nombre FROM donde_ticket ORDER BY nombre");
$donde_opciones = $stmt_donde->fetchAll(PDO::FETCH_ASSOC);

$stmt_detalle = $pdo->query("SELECT id, nombre FROM detalle_donde_ticket ORDER BY nombre");
$detalle_opciones = $stmt_detalle->fetchAll(PDO::FETCH_ASSOC);

// Obtener subcategorías
$stmt_sub = $pdo->prepare("SELECT id, nombre_sucat FROM subcategorias_ticket WHERE id_catServ = ?");
$stmt_sub->execute([$tarea['id_categoria']]);
$subcategorias = $stmt_sub->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Editar Tarea <?= htmlspecialchars($tarea['codigo_tarea']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body class="bg-gray-50">
    <div class="max-w-4xl mx-auto py-8 px-4">
        <div class="bg-white rounded-xl shadow-lg p-8">
            <h1 class="text-2xl font-bold text-gray-800 mb-6">
                <i class="fas fa-edit text-blue-600 mr-2"></i>
                Editar Tarea <?= htmlspecialchars($tarea['codigo_tarea']) ?>
            </h1>

<form method="POST" action="procesar_edicion_unica.php" class="space-y-6">
                    <input type="hidden" name="id_tarea" value="<?= $id_tarea ?>">

                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de Trabajo *</label>
                        <select name="tipo_trabajo" class="w-full border rounded-lg px-3 py-2" required>
                            <option value="interno" <?= $tarea['tipo_trabajo'] == 'interno' ? 'selected' : '' ?>>Interno</option>
                            <option value="colaboracion" <?= $tarea['tipo_trabajo'] == 'colaboracion' ? 'selected' : '' ?>>Colaboración</option>
                            <option value="externo" <?= $tarea['tipo_trabajo'] == 'externo' ? 'selected' : '' ?>>Externo</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Área *</label>
                        <select name="id_area" class="w-full border rounded-lg px-3 py-2" required>
                            <?php foreach ($areas as $area): ?>
                                <option value="<?= $area['id'] ?>" <?= $tarea['id_area'] == $area['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($area['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Rubro *</label>
                        <select name="rubro" class="w-full border rounded-lg px-3 py-2" required>
                            <option value="trabajo_diario" <?= $tarea['rubro'] == 'trabajo_diario' ? 'selected' : '' ?>>Trabajo Diario</option>
                            <option value="minuta" <?= $tarea['rubro'] == 'minuta' ? 'selected' : '' ?>>Minuta</option>
                            <option value="planeacion" <?= $tarea['rubro'] == 'planeacion' ? 'selected' : '' ?>>Planeación</option>
                            <option value="incidente" <?= $tarea['rubro'] == 'incidente' ? 'selected' : '' ?>>Incidente</option>
                            <option value="proyecto" <?= $tarea['rubro'] == 'proyecto' ? 'selected' : '' ?>>Proyecto</option>
                            <option value="requerimiento" <?= $tarea['rubro'] == 'requerimiento' ? 'selected' : '' ?>>Requerimiento</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Prioridad *</label>
                        <select name="prioridad" class="w-full border rounded-lg px-3 py-2" required>
                            <option value="baja" <?= $tarea['prioridad'] == 'baja' ? 'selected' : '' ?>>Baja</option>
                            <option value="media" <?= $tarea['prioridad'] == 'media' ? 'selected' : '' ?>>Media</option>
                            <option value="alta" <?= $tarea['prioridad'] == 'alta' ? 'selected' : '' ?>>Alta</option>
                            <option value="urgente" <?= $tarea['prioridad'] == 'urgente' ? 'selected' : '' ?>>Urgente</option>
                            <option value="critica" <?= $tarea['prioridad'] == 'critica' ? 'selected' : '' ?>>Crítica</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Dónde</label>
                        <select id="id_donde" name="id_donde" class="w-full border rounded-lg px-3 py-2">
                            <option value="">Seleccionar</option>
                            <?php foreach ($donde_opciones as $donde): ?>
                                <option value="<?= $donde['id'] ?>" <?= $tarea['id_donde'] == $donde['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($donde['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Detalle Dónde</label>
                        <select id="id_detalle_donde" name="id_detalle_donde" class="w-full border rounded-lg px-3 py-2">
                            <option value="">Seleccionar</option>
                            <?php foreach ($detalle_opciones as $detalle): ?>
                                <option value="<?= $detalle['id'] ?>" <?= $tarea['id_detalle_donde'] == $detalle['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($detalle['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Responsable Ejecución *</label>
                        <select name="id_responsable_ejecucion" class="w-full border rounded-lg px-3 py-2" required>
                            <?php foreach ($usuarios as $user): ?>
                                <option value="<?= $user['Id_Usuario'] ?>" <?= $tarea['id_responsable_ejecucion'] == $user['Id_Usuario'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($user['nombre'] . ' ' . $user['apellido']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Responsable Supervisión</label>
                        <select name="id_responsable_supervision" class="w-full border rounded-lg px-3 py-2">
                            <option value="">Sin supervisor</option>
                            <?php foreach ($usuarios as $user): ?>
                                <option value="<?= $user['Id_Usuario'] ?>" <?= $tarea['id_responsable_supervision'] == $user['Id_Usuario'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($user['nombre'] . ' ' . $user['apellido']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Categoría *</label>
                        <select id="id_categoria" name="id_categoria" class="w-full border rounded-lg px-3 py-2" required>
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $tarea['id_categoria'] == $cat['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['nombre_cat']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Subcategoría</label>
                        <select id="id_subcategoria" name="id_subcategoria" class="w-full border rounded-lg px-3 py-2">
                            <option value="">Seleccionar</option>
                            <?php foreach ($subcategorias as $sub): ?>
                                <option value="<?= $sub['id'] ?>" <?= $tarea['id_subcategoria'] == $sub['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($sub['nombre_sucat']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Fecha Límite</label>
                        <input type="datetime-local" name="fecha_limite"
                            value="<?= $tarea['fecha_limite'] ? date('Y-m-d\TH:i', strtotime($tarea['fecha_limite'])) : '' ?>"
                            class="w-full border rounded-lg px-3 py-2">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Estado *</label>
                        <select name="estatus" class="w-full border rounded-lg px-3 py-2" required>
                            <option value="pendiente" <?= $tarea['estatus'] == 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                            <option value="en_proceso" <?= $tarea['estatus'] == 'en_proceso' ? 'selected' : '' ?>>En Proceso</option>
                            <option value="completada" <?= $tarea['estatus'] == 'completada' ? 'selected' : '' ?>>Completada</option>
                            <option value="cancelada" <?= $tarea['estatus'] == 'cancelada' ? 'selected' : '' ?>>Cancelada</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Descripción *</label>
                    <textarea name="descripcion" rows="4" class="w-full border rounded-lg px-3 py-2" required><?= htmlspecialchars($tarea['descripcion']) ?></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Motivo del cambio</label>
                    <input type="text" name="motivo_cambio" class="w-full border rounded-lg px-3 py-2" placeholder="¿Por qué estás editando esta tarea?">
                </div>

                <div class="flex justify-end gap-3 pt-6 border-t">
                    <a href="../tasks.php?tab=unicas" class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                        Cancelar
                    </a>
                    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        <i class="fas fa-save mr-2"></i>Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Cargar subcategorías al cambiar categoría
        $('#id_categoria').change(function() {
            const categoriaId = $(this).val();
            const subSelect = $('#id_subcategoria');

            subSelect.html('<option value="">Cargando...</option>');

            $.ajax({
                url: 'task/get_subcategorias.php',
                type: 'POST',
                data: {
                    categoria_id: categoriaId
                },
                dataType: 'json',
                success: function(response) {
                    subSelect.html('<option value="">Seleccionar</option>');
                    response.forEach(function(item) {
                        subSelect.append(`<option value="${item.id}">${item.nombre_sucat}</option>`);
                    });
                }
            });
        });

        // Filtrar detalle_donde según donde seleccionado
        $('#id_donde').change(function() {
            const dondeId = $(this).val();
            const detalleSelect = $('#id_detalle_donde');

            if (!dondeId) {
                detalleSelect.html('<option value="">Seleccionar</option>');
                return;
            }

            <?php
            $detalle_js = [];
            foreach ($detalle_opciones as $d) {
                $detalle_js[$d['id']][] = $d;
            }
            ?>

            const detalles = <?= json_encode($detalle_js) ?>;
            detalleSelect.html('<option value="">Seleccionar</option>');

            if (detalles[dondeId]) {
                detalles[dondeId].forEach(function(item) {
                    detalleSelect.append(`<option value="${item.id}">${item.nombre}</option>`);
                });
            }
        });
    </script>
</body>

</html>