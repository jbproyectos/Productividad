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
    // $stmt = $pdo->prepare("INSERT INTO categoria_servicio_ticket(
    //             id_area, nombre_cat
    //         ) VALUES (?, ?)");
    $stmt = $pdo->prepare("
        INSERT INTO categoria_servicio_ticket(
            id_area, nombre_cat 
        )
        SELECT a.id, ?
        FROM areas a
        WHERE a.nombre = ?
    ");

    $categoriasInsertadas = [];

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

        $categoria = trim($row[1]);
        // echo "Categorias: $categoria<br>";
        $area = trim($row[0]);

        // Evitar duplicados
        if(in_array($categoria, $categoriasInsertadas)){
            continue;
        }
    
        try{
            $stmt->execute([$categoria, $area]);
            $categoriasInsertadas[] = $categoria;
        } catch (Exception $ex) {
            $errors[] = "Fila $rowCount: Error al insertar - " . $ex->getMessage();
            }
        $rowCount++;
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