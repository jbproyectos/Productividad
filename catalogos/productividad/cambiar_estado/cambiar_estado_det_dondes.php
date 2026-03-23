<?php
include '../../../includes/conexionbd.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

$id = $_POST['id'];
$estado = $_POST['estatus_activo_det_donde'];

$stmt = $pdo->prepare("
    UPDATE detalle_donde_ticket
    SET estatus_activo_det_donde = ?
    WHERE id = ?
");

$stmt->execute([$estado, $id]);

echo "ok";
?>