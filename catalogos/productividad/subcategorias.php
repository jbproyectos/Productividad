<?php
include '../../includes/conexionbd.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_FILES["subeCat"]) || $_FILES["subeCat"]["error"] !== UPLOAD_ERR_OK) {
    exit("Error al subir el archivo. Por favor, verifica que sea un archivo válido.");
}

$fh = fopen($_FILES["subeCat"]["tmp_name"], "r");
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
                INSERT INTO subcategorias_ticket (
                    nombre_sucat, id_area, id_catServ, duracion, unidad_duracion, comentarios
                )
                SELECT ?, a.id, c.id, ?, ?, ?
                FROM categoria_servicio_ticket c
                JOIN areas a ON a.nombre = ?
                WHERE c.nombre_cat = ?
            ");

            $stmt->execute([
                $row[2], $row[3], $row[4], $row[5], $row[0], $row[1]
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