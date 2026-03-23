<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

include '../includes/conexionbd.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Método no permitido";
    header('Location: ../tasks.php?tab=unicas');
    exit;
}

// Función para generar código único
function generarCodigoTarea($pdo) {
    $year = date('y');
    $month = date('m');
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM tareas WHERE YEAR(fecha_creacion) = " . date('Y'));
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $numero = str_pad($result['total'] + 1, 4, '0', STR_PAD_LEFT);
    
    return "TAR-{$year}{$month}-{$numero}";
}

// Obtener datos
$user_id = $_SESSION['user_id'];
$tipo_trabajo = $_POST['tipo_trabajo'];
$area = $_POST['area'];
$rubro = $_POST['rubro'];
$prioridad = $_POST['prioridad'];
$donde = $_POST['donde'];
$detalle_donde = $_POST['detalle_donde'] ?: null;
$responsable_sup = $_POST['responsable_supervision'] ?: null;
$responsable_ejec = $_POST['responsable_ejecucion'];
$categoria = $_POST['categoria_servicio_extra'];
$subcategoria = $_POST['subcategoriaExtraordinaria'] ?: null;
$descripcion = $_POST['descripcion'];
$estatus = $_POST['estatus'];
$fecha_limite = $_POST['fecha_limite'] ?? null;

// Validar campos obligatorios
$campos_requeridos = [
    'tipo_trabajo' => $tipo_trabajo,
    'area' => $area,
    'rubro' => $rubro,
    'prioridad' => $prioridad,
    'donde' => $donde,
    'responsable_ejecucion' => $responsable_ejec,
    'categoria_servicio_extra' => $categoria,
    'descripcion' => $descripcion
];

foreach ($campos_requeridos as $campo => $valor) {
    if (empty($valor)) {
        $_SESSION['error'] = "El campo " . str_replace('_', ' ', $campo) . " es obligatorio";
        header('Location: ../tasks.php?tab=unicas');
        exit;
    }
}

try {
    $pdo->beginTransaction();
    
    // Obtener nombre del creador
    $stmt = $pdo->prepare("SELECT CONCAT(nombre, ' ', apellido) as nombre FROM usuarios WHERE Id_Usuario = ?");
    $stmt->execute([$user_id]);
    $creador_nombre = $stmt->fetchColumn();
    
    // Generar código
    $codigo = generarCodigoTarea($pdo);
    $titulo = $codigo . " - " . ucfirst($rubro);
    
    // Crear seguimiento inicial
    $seguimiento = json_encode([[
        'fecha' => date('Y-m-d H:i:s'),
        'usuario' => $creador_nombre,
        'accion' => 'creacion',
        'comentario' => 'Tarea creada'
    ]]);
    
    // Insertar tarea
    $sql = "INSERT INTO tareas (
        codigo_tarea, id_usuario_creador, id_area, id_responsable_ejecucion, id_responsable_supervision,
        tipo_trabajo, rubro, prioridad, estatus,
        id_donde, id_detalle_donde, id_categoria, id_subcategoria,
        titulo, descripcion, fecha_limite,
        fecha_creacion, seguimiento
    ) VALUES (
        :codigo, :creador, :area, :responsable_ejec, :responsable_sup,
        :tipo_trabajo, :rubro, :prioridad, :estatus,
        :donde, :detalle_donde, :categoria, :subcategoria,
        :titulo, :descripcion, :fecha_limite,
        NOW(), :seguimiento
    )";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':codigo' => $codigo,
        ':creador' => $user_id,
        ':area' => $area,
        ':responsable_ejec' => $responsable_ejec,
        ':responsable_sup' => $responsable_sup,
        ':tipo_trabajo' => $tipo_trabajo,
        ':rubro' => $rubro,
        ':prioridad' => $prioridad,
        ':estatus' => $estatus,
        ':donde' => $donde,
        ':detalle_donde' => $detalle_donde,
        ':categoria' => $categoria,
        ':subcategoria' => $subcategoria,
        ':titulo' => $titulo,
        ':descripcion' => $descripcion,
        ':fecha_limite' => $fecha_limite,
        ':seguimiento' => $seguimiento
    ]);
    
    $pdo->commit();
    
    $_SESSION['exito'] = "Tarea $codigo creada exitosamente";
    header('Location: ../tasks.php?tab=unicas');
    exit;
    
} catch (PDOException $e) {
    $pdo->rollBack();
    $_SESSION['error'] = "Error al crear la tarea: " . $e->getMessage();
    header('Location: ../tasks.php?tab=unicas');
    exit;
}
?>