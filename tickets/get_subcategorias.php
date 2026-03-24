<?php
include '../includes/conexionbd.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

$categoria_id = $_GET['categoria_id'] ?? 0;

$stmt = $pdo->prepare("SELECT id, nombre_sucat FROM subcategorias_ticket WHERE id_catServ = ? AND estatus_activo_sub = 'ACTIVO' ORDER BY nombre_sucat ASC");
$stmt->execute([$categoria_id]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
