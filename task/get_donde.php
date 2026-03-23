<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include '../includes/conexionbd.php';

$area_id = $_POST['area_id'];

$stmt = $pdo->prepare("SELECT id, nombre FROM donde_ticket WHERE id_area = ? ORDER BY nombre");
$stmt->execute([$area_id]);
$donde = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($donde);
?>