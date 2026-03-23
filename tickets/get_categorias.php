<?php
include '../includes/conexionbd.php';
$area_id = $_GET['area_id'];
$stmt = $pdo->prepare("SELECT id, nombre_cat FROM categoria_servicio_ticket WHERE id_area = ? ORDER BY nombre_cat");
$stmt->execute([$area_id]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>