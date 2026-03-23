<?php
include '../../../includes/conexionbd.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

$id = $_POST['id'];
$estado = $_POST['estatus_activo_subareas'];

$stmt = $pdo->prepare("
    UPDATE subareas
    SET estatus_activo_subareas = ?
    WHERE id = ?
");

$stmt->execute([$estado, $id]);

echo "ok";
?> 