<?php
include '../includes/conexionbd.php';
$area_id = $_GET['area_id'];
$stmt = $pdo->prepare("SELECT id, nombre FROM donde_ticket WHERE id_area = ? AND estatus_activo_donde = 'ACTIVO' ORDER BY nombre");
$stmt->execute([$area_id]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
