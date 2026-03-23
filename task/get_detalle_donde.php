<?php
include 'includes/conexionbd.php';

$donde_id = $_POST['donde_id'];

$stmt = $conn->prepare("SELECT id, nombre FROM detalle_donde_ticket WHERE id_donde = ? ORDER BY nombre");
$stmt->execute([$donde_id]);
$detalle = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($detalle);
?>