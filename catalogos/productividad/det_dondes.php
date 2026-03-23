<?php
include '../../includes/conexionbd.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_FILES["subeDetDondes"]) || $_FILES["subeDetDondes"]["error"] !== UPLOAD_ERR_OK) {
    exit("Error al subir el archivo. Por favor, verifica que sea un archivo válido.");
}

$fh = fopen($_FILES["subeDetDondes"]["tmp_name"], "r");
if ($fh === false) {
    exit("No se pudo abrir el archivo CSV.");
}

$rowCount = 0; // Contador de filas procesadas
$errors = []; // Lista para capturar errores

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // while (($row = fgetcsv($fh)) !== false) {
    while (($row = fgetcsv($fh, 1000, ",")) !== false) {
        // Saltar encabezado
        
        if ($rowCount === 0) {
            $rowCount++;
            continue;
        }

        if(count($row) < 2){
            $errors[] = "Fila $rowCount: columnas insuficientes";
            continue;
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO detalle_donde_ticket (
                    nombre, id_area, descripcion
                )
                SELECT ?, a.id, ?
                FROM areas a
                WHERE a.nombre = ?
            ");

            $stmt->execute([
                $row[0], $row[2], $row[1]
            ]);

            $rowCount++;
        } catch (Exception $ex) {
            $errors[] = "Fila $rowCount: Error al insertar - " . $ex->getMessage();
        }
    }

    fclose($fh);

    $totalProcesadas = $rowCount - 1; // quitando encabezado
    $totalErrores = count($errors);
    $totalInsertadas = $totalProcesadas - $totalErrores;

    // echo "Total en CSV: $totalProcesadas<br>";
    // echo "Insertadas: $totalInsertadas<br>";
    // echo "Errores: $totalErrores<br>";

    echo json_encode([
        "total" => $totalProcesadas,
        "insertadas" => $totalInsertadas,
        "errores" => $totalErrores,
        "detalle" => $errors
    ]);

    // if ($totalErrores > 0) {
    //     echo "Lista de errores:<br>" . implode("<br>", $errors);
    // }

} catch (Exception $e) {
    exit("Error al procesar el archivo: " . $e->getMessage());
}
?>