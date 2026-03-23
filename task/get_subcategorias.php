<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include '../includes/conexionbd.php';

$categoria_id = $_POST['categoria_id'];

$stmt = $pdo->prepare("SELECT id, nombre_sucat FROM subcategorias_ticket WHERE id_catServ = ? ORDER BY nombre_sucat");
$stmt->execute([$categoria_id]);
$subcategorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($subcategorias);
?>