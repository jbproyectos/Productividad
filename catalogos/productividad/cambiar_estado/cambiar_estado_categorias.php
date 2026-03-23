<?php
include '../../../includes/conexionbd.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

$id = $_POST['id'];
$estado = $_POST['estatus_activo_cat'];

$stmt = $pdo->prepare("
    UPDATE categoria_servicio_ticket
    SET estatus_activo_cat = ?
    WHERE id = ?
");

$stmt->execute([$estado, $id]);

echo "ok";
?>