<?php
include '../includes/conexionbd.php';
$area_id = $_GET['area_id'];
$stmt = $pdo->prepare("SELECT id, nombre_cat FROM categoria_servicio_ticket WHERE id_area = ? AND estatus_activo_cat = 'ACTIVO' ORDER BY nombre_cat");
$stmt->execute([$area_id]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
