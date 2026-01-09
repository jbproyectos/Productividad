<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../includes/conexionbd.php';

function procesarTareaCiclica($conn, $data) {
    // Validar campos requeridos
    $campos_requeridos = ['puesto', 'actividad_puesto', 'categoria_servicio', 'subcategoria_servicio', 'frecuencia', 'hora', 'fecha_inicio'];
    
    foreach ($campos_requeridos as $campo) {
        if (empty($data[$campo])) {
            echo json_encode(['success' => false, 'message' => "El campo $campo es requerido"]);
            return;
        }
    }

    // Procesar configuración de frecuencia
    $config_frecuencia = procesarConfiguracionFrecuencia($data);

    // Insertar en la base de datos
    $sql = "INSERT INTO task_cicle (
        puesto, actividad_puesto, categoria_servicio, subcategoria_servicio,
        frecuencia, cuando_dia_semana, cuando_dia_mes, cuando_tipo_relativo, cuando_mes,
        cada_cantidad, cada_unidad, hora, fecha_inicio, fecha_fin, prioridad, estatus
    ) VALUES (
        :puesto, :actividad_puesto, :categoria_servicio, :subcategoria_servicio,
        :frecuencia, :cuando_dia_semana, :cuando_dia_mes, :cuando_tipo_relativo, :cuando_mes,
        :cada_cantidad, :cada_unidad, :hora, :fecha_inicio, :fecha_fin, :prioridad, :estatus
    )";

    $stmt = $pdo->prepare($sql);
    
    $params = [
        ':puesto' => $data['puesto'],
        ':actividad_puesto' => $data['actividad_puesto'],
        ':categoria_servicio' => $data['categoria_servicio'],
        ':subcategoria_servicio' => $data['subcategoria_servicio'],
        ':frecuencia' => $data['frecuencia'],
        ':hora' => $data['hora'],
        ':fecha_inicio' => $data['fecha_inicio'],
        ':fecha_fin' => $data['fecha_fin'] ?? null,
        ':prioridad' => $data['prioridad'] ?? 'media',
        ':estatus' => 'activa'
    ];

    // Combinar con la configuración de frecuencia
    $params = array_merge($params, $config_frecuencia);

    if ($stmt->execute($params)) {
        $task_id = $conn->lastInsertId();
        
        // Generar las próximas ocurrencias
        generarProximasOcurrencias($conn, $task_id, $data, $config_frecuencia);
        
        echo json_encode(['success' => true, 'message' => 'Tarea cíclica guardada exitosamente', 'task_id' => $task_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al guardar la tarea']);
    }
}

function procesarConfiguracionFrecuencia($data) {
    $frecuencia = $data['frecuencia'];
    $config = [
        ':cuando_dia_semana' => null,
        ':cuando_dia_mes' => null,
        ':cuando_tipo_relativo' => null,
        ':cuando_mes' => null,
        ':cada_cantidad' => 1,
        ':cada_unidad' => 'dias'
    ];

    switch ($frecuencia) {
        case 'diario':
            $config[':cuando_dia_semana'] = $data['cuando_diario'] ?? null;
            $config[':cada_cantidad'] = $data['cada_dia'] ?? 1;
            $config[':cada_unidad'] = 'dias';
            break;

        case 'quincenal':
            $config[':cuando_dia_semana'] = $data['cuando_quincenal'] ?? null;
            $config[':cada_cantidad'] = $data['cada_quincena'] ?? 2;
            $config[':cada_unidad'] = 'semanas';
            break;

        case 'mensual':
            if (isset($data['cuando_mensual']) && $data['cuando_mensual'] === 'dia-especifico') {
                $config[':cuando_dia_mes'] = $data['dia_mensual'] ?? 1;
            } else {
                $config[':cuando_tipo_relativo'] = $data['cuando_mensual'] ?? null;
            }
            $config[':cada_cantidad'] = $data['cada_mes'] ?? 1;
            $config[':cada_unidad'] = 'meses';
            break;

        case 'bimestral':
            if (isset($data['cuando_bimestral']) && $data['cuando_bimestral'] === 'dia-especifico') {
                $config[':cuando_dia_mes'] = $data['dia_bimestral'] ?? 1;
            } else {
                $config[':cuando_tipo_relativo'] = $data['cuando_bimestral'] ?? null;
            }
            $config[':cada_cantidad'] = $data['cada_bimestre'] ?? 2;
            $config[':cada_unidad'] = 'meses';
            break;

        case 'trimestral':
            if (isset($data['cuando_trimestral']) && $data['cuando_trimestral'] === 'dia-especifico') {
                $config[':cuando_dia_mes'] = $data['dia_trimestral'] ?? 1;
            } else {
                $config[':cuando_tipo_relativo'] = $data['cuando_trimestral'] ?? null;
            }
            $config[':cada_cantidad'] = $data['cada_trimestre'] ?? 3;
            $config[':cada_unidad'] = 'meses';
            break;

        case 'semestral':
            if (isset($data['cuando_semestral']) && $data['cuando_semestral'] === 'dia-especifico') {
                $config[':cuando_dia_mes'] = $data['dia_semestral'] ?? 1;
            } else {
                $config[':cuando_tipo_relativo'] = $data['cuando_semestral'] ?? null;
            }
            $config[':cada_cantidad'] = $data['cada_semestre'] ?? 6;
            $config[':cada_unidad'] = 'meses';
            break;

        case 'anual':
            $config[':cuando_mes'] = $data['mes_anual'] ?? 1;
            $config[':cuando_dia_mes'] = $data['dia_anual'] ?? 1;
            $config[':cada_cantidad'] = $data['cada_anio'] ?? 1;
            $config[':cada_unidad'] = 'años';
            break;
    }

    return $config;
}

function generarProximasOcurrencias($conn, $task_id, $data, $config) {
    // Crear tabla para ocurrencias si no existe
    $sql_ocurrencias = "
        CREATE TABLE IF NOT EXISTS task_occurrences (
            id INT PRIMARY KEY AUTO_INCREMENT,
            task_cicle_id INT,
            fecha_ejecucion DATE NOT NULL,
            hora TIME NOT NULL,
            estatus ENUM('pendiente', 'completada', 'cancelada') DEFAULT 'pendiente',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (task_cicle_id) REFERENCES task_cicle(id) ON DELETE CASCADE
        )
    ";
    $conn->exec($sql_ocurrencias);

    // Generar las próximas 12 ocurrencias
    $fecha_inicio = new DateTime($data['fecha_inicio']);
    $hora = $data['hora'];
    $frecuencia = $data['frecuencia'];
    
    $ocurrencias = [];
    
    for ($i = 0; $i < 12; $i++) {
        $fecha_ocurrencia = calcularSiguienteOcurrencia($frecuencia, $fecha_inicio, $config, $i);
        
        if ($fecha_ocurrencia) {
            $ocurrencias[] = [
                'task_cicle_id' => $task_id,
                'fecha_ejecucion' => $fecha_ocurrencia->format('Y-m-d'),
                'hora' => $hora
            ];
        }
    }

    // Insertar ocurrencias
    $sql_insert = "INSERT INTO task_occurrences (task_cicle_id, fecha_ejecucion, hora) VALUES (:task_id, :fecha, :hora)";
    $stmt = $conn->prepare($sql_insert);
    
    foreach ($ocurrencias as $ocurrencia) {
        $stmt->execute([
            ':task_id' => $ocurrencia['task_cicle_id'],
            ':fecha' => $ocurrencia['fecha_ejecucion'],
            ':hora' => $ocurrencia['hora']
        ]);
    }
}

function calcularSiguienteOcurrencia($frecuencia, $fecha_base, $config, $iteracion) {
    $fecha = clone $fecha_base;
    
    switch ($frecuencia) {
        case 'diario':
            $dias = $config[':cada_cantidad'] * $iteracion;
            $fecha->modify("+$dias days");
            break;
            
        case 'quincenal':
            $semanas = $config[':cada_cantidad'] * $iteracion;
            $fecha->modify("+$semanas weeks");
            break;
            
        case 'mensual':
            $meses = $config[':cada_cantidad'] * $iteracion;
            $fecha->modify("+$meses months");
            break;
            
        case 'bimestral':
            $meses = $config[':cada_cantidad'] * $iteracion;
            $fecha->modify("+$meses months");
            break;
            
        case 'trimestral':
            $meses = $config[':cada_cantidad'] * $iteracion;
            $fecha->modify("+$meses months");
            break;
            
        case 'semestral':
            $meses = $config[':cada_cantidad'] * $iteracion;
            $fecha->modify("+$meses months");
            break;
            
        case 'anual':
            $años = $config[':cada_cantidad'] * $iteracion;
            $fecha->modify("+$años years");
            break;
    }
    
    return $fecha;
}
?>