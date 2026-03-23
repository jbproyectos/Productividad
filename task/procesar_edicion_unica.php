<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include '../includes/conexionbd.php';

// Debug: Ver qué datos están llegando
file_put_contents('debug_log.txt', "=== " . date('Y-m-d H:i:s') . " ===\n", FILE_APPEND);
file_put_contents('debug_log.txt', "POST Data: " . print_r($_POST, true) . "\n", FILE_APPEND);
file_put_contents('debug_log.txt', "SESSION: " . print_r($_SESSION, true) . "\n", FILE_APPEND);

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    file_put_contents('debug_log.txt', "ERROR: No hay sesión\n", FILE_APPEND);
    header('Location: ../login.php');
    exit;
}

// Verificar que sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    file_put_contents('debug_log.txt', "ERROR: Método no es POST\n", FILE_APPEND);
    $_SESSION['error'] = "Método no permitido";
    header('Location: ../tasks.php?tab=unicas');
    exit;
}

// Verificar que existe el ID de tarea
if (!isset($_POST['id_tarea']) || empty($_POST['id_tarea'])) {
    file_put_contents('debug_log.txt', "ERROR: No hay id_tarea en POST\n", FILE_APPEND);
    $_SESSION['error'] = "ID de tarea no proporcionado";
    header('Location: ../tasks.php?tab=unicas');
    exit;
}

$id_tarea = filter_input(INPUT_POST, 'id_tarea', FILTER_VALIDATE_INT);
if (!$id_tarea) {
    file_put_contents('debug_log.txt', "ERROR: id_tarea no es válido: " . $_POST['id_tarea'] . "\n", FILE_APPEND);
    $_SESSION['error'] = "ID de tarea no válido";
    header('Location: ../tasks.php?tab=unicas');
    exit;
}

file_put_contents('debug_log.txt', "ID Tarea: $id_tarea\n", FILE_APPEND);

try {
    // Verificar que la tarea existe
    $stmt_check = $pdo->prepare("SELECT id_tarea, codigo_tarea FROM tareas WHERE id_tarea = ? AND activo = 1");
    $stmt_check->execute([$id_tarea]);
    $tarea_existente = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$tarea_existente) {
        file_put_contents('debug_log.txt', "ERROR: Tarea no encontrada en BD\n", FILE_APPEND);
        $_SESSION['error'] = "La tarea no existe o fue eliminada";
        header('Location: ../tasks.php?tab=unicas');
        exit;
    }

    file_put_contents('debug_log.txt', "Tarea encontrada: " . $tarea_existente['codigo_tarea'] . "\n", FILE_APPEND);

    // Recoger y validar datos del formulario
    $tipo_trabajo = isset($_POST['tipo_trabajo']) ? trim($_POST['tipo_trabajo']) : '';
    $id_area = isset($_POST['id_area']) ? (int)$_POST['id_area'] : 0;
    $rubro = isset($_POST['rubro']) ? trim($_POST['rubro']) : '';
    $prioridad = isset($_POST['prioridad']) ? trim($_POST['prioridad']) : '';
    $id_donde = isset($_POST['id_donde']) && !empty($_POST['id_donde']) ? (int)$_POST['id_donde'] : null;
    $id_detalle_donde = isset($_POST['id_detalle_donde']) && !empty($_POST['id_detalle_donde']) ? (int)$_POST['id_detalle_donde'] : null;
    $id_responsable_ejecucion = isset($_POST['id_responsable_ejecucion']) ? (int)$_POST['id_responsable_ejecucion'] : 0;
    $id_responsable_supervision = isset($_POST['id_responsable_supervision']) && !empty($_POST['id_responsable_supervision']) ? (int)$_POST['id_responsable_supervision'] : null;
    $id_categoria = isset($_POST['id_categoria']) ? (int)$_POST['id_categoria'] : 0;
    $id_subcategoria = isset($_POST['id_subcategoria']) && !empty($_POST['id_subcategoria']) ? (int)$_POST['id_subcategoria'] : null;
    $fecha_limite = isset($_POST['fecha_limite']) && !empty($_POST['fecha_limite']) ? $_POST['fecha_limite'] : null;
    $estatus = isset($_POST['estatus']) ? trim($_POST['estatus']) : '';
    $descripcion = isset($_POST['descripcion']) ? trim($_POST['descripcion']) : '';
    $motivo_cambio = isset($_POST['motivo_cambio']) ? trim($_POST['motivo_cambio']) : '';

    file_put_contents('debug_log.txt', "Datos procesados:\n", FILE_APPEND);
    file_put_contents('debug_log.txt', "tipo_trabajo: $tipo_trabajo\n", FILE_APPEND);
    file_put_contents('debug_log.txt', "id_area: $id_area\n", FILE_APPEND);
    file_put_contents('debug_log.txt', "rubro: $rubro\n", FILE_APPEND);
    file_put_contents('debug_log.txt', "prioridad: $prioridad\n", FILE_APPEND);
    file_put_contents('debug_log.txt', "estatus: $estatus\n", FILE_APPEND);

    // Validar campos requeridos
    $errores = [];

    if (!$tipo_trabajo) $errores[] = "Tipo de trabajo es requerido";
    if (!$id_area) $errores[] = "Área es requerida";
    if (!$rubro) $errores[] = "Rubro es requerido";
    if (!$prioridad) $errores[] = "Prioridad es requerida";
    if (!$id_responsable_ejecucion) $errores[] = "Responsable de ejecución es requerido";
    if (!$id_categoria) $errores[] = "Categoría es requerida";
    if (!$descripcion) $errores[] = "Descripción es requerida";
    if (!$estatus) $errores[] = "Estado es requerido";

    // Si hay errores, redirigir con mensajes
    if (!empty($errores)) {
        file_put_contents('debug_log.txt', "ERRORES DE VALIDACIÓN: " . print_r($errores, true) . "\n", FILE_APPEND);
        $_SESSION['error'] = implode("<br>", $errores);
        header("Location: ../editar_tarea_unica.php?id=$id_tarea");
        exit;
    }

    // Validar y formatear fecha límite
    if ($fecha_limite) {
        try {
            $fecha_limite_obj = new DateTime($fecha_limite);
            $fecha_limite = $fecha_limite_obj->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            $fecha_limite = null;
        }
    }

    // Iniciar transacción
    $pdo->beginTransaction();

    // Obtener datos antiguos para el log
    $stmt_old = $pdo->prepare("SELECT * FROM tareas WHERE id_tarea = ?");
    $stmt_old->execute([$id_tarea]);
    $old_data = $stmt_old->fetch(PDO::FETCH_ASSOC);

    file_put_contents('debug_log.txt', "Datos antiguos obtenidos\n", FILE_APPEND);

    // Actualizar tarea
    $sql = "UPDATE tareas SET 
            tipo_trabajo = ?,
            id_area = ?,
            rubro = ?,
            prioridad = ?,
            id_donde = ?,
            id_detalle_donde = ?,
            id_responsable_ejecucion = ?,
            id_responsable_supervision = ?,
            id_categoria = ?,
            id_subcategoria = ?,
            fecha_limite = ?,
            estatus = ?,
            descripcion = ?,
            fecha_modificacion = NOW(),
            modificado_por = ?
            WHERE id_tarea = ? AND activo = 1";

    $stmt = $pdo->prepare($sql);
    $resultado = $stmt->execute([
        $tipo_trabajo,
        $id_area,
        $rubro,
        $prioridad,
        $id_donde,
        $id_detalle_donde,
        $id_responsable_ejecucion,
        $id_responsable_supervision,
        $id_categoria,
        $id_subcategoria,
        $fecha_limite,
        $estatus,
        $descripcion,
        $_SESSION['user_id'],
        $id_tarea
    ]);

    if (!$resultado) {
        throw new Exception("Error al ejecutar la actualización: " . print_r($stmt->errorInfo(), true));
    }

    file_put_contents('debug_log.txt', "UPDATE ejecutado correctamente. Filas afectadas: " . $stmt->rowCount() . "\n", FILE_APPEND);

    // Registrar en log de cambios si hay motivo
    if (!empty($motivo_cambio)) {
        $stmt_log = $pdo->prepare("INSERT INTO tareas_log 
            (id_tarea, id_usuario, accion, motivo, fecha) 
            VALUES (?, ?, 'edicion', ?, NOW())");
        $stmt_log->execute([
            $id_tarea,
            $_SESSION['user_id'],
            $motivo_cambio
        ]);
        file_put_contents('debug_log.txt', "Log de cambios registrado\n", FILE_APPEND);
    }

    // Commit de la transacción
    $pdo->commit();
    file_put_contents('debug_log.txt', "TRANSACCIÓN COMMITEADA EXITOSAMENTE\n", FILE_APPEND);

    // Mensaje de éxito
    $_SESSION['success'] = "Tarea " . $tarea_existente['codigo_tarea'] . " actualizada correctamente";
    
    file_put_contents('debug_log.txt', "Redirigiendo a: ../tasks.php?tab=unicas\n", FILE_APPEND);
    file_put_contents('debug_log.txt', "==========================\n\n", FILE_APPEND);
    
    // Redirigir
    header('Location: ../tasks.php?tab=unicas');
    exit;

} catch (Exception $e) {
    // Rollback en caso de error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Log del error
    file_put_contents('debug_log.txt', "EXCEPCIÓN: " . $e->getMessage() . "\n", FILE_APPEND);
    file_put_contents('debug_log.txt', "==========================\n\n", FILE_APPEND);
    
    $_SESSION['error'] = "Error al actualizar la tarea: " . $e->getMessage();
    header("Location: ../editar_tarea_unica.php?id=$id_tarea");
    exit;
}
?>