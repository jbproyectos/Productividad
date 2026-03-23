<?php
include '../includes/conexionbd.php';
$area_id = $_GET['area_id'];
$categoria_id = $_GET['categoria_id'];
$stmt = $pdo->prepare("SELECT id, nombre_sucat FROM subcategorias_ticket WHERE id_area = ? AND id_categoria = ? ORDER BY nombre_sucat");
$stmt->execute([$area_id, $categoria_id]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>