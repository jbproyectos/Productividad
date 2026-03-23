<?php
// guardar_tarea.php
header("Content-Type: application/json");
include '../includes/conexionbd.php'; // Ajusta la ruta según tu estructura

// --- configurables ---
$months_ahead = 12; // generar instancias hasta +12 meses
$startDate = new DateTimeImmutable('today');
$endDate = $startDate->modify("+{$months_ahead} months");

// --- utilidades ---
function limpiar($s){
    return trim($s === null ? '' : mb_convert_encoding($s, 'UTF-8', 'UTF-8'));
}

function normalizeUpper($s){
    return mb_strtoupper(limpiar($s));
}

// map de días en español a números
$dias_map = [
    'LUNES' => 1,
    'MARTES' => 2,
    'MIERCOLES' => 3,
    'MIÉRCOLES' => 3,
    'MIENCOLES' => 3,
    'JUEVES' => 4,
    'VIERNES' => 5,
    'SABADO' => 6,
    'SÁBADO' => 6,
    'DOMINGO' => 7
];

// obtener nro de día ISO (1..7) desde nombre
function diaNumero($nombre, $dias_map){
    $k = normalizeUpper($nombre);
    return $dias_map[$k] ?? null;
}

// devuelve todas las fechas entre start y end que caen en los weekdays array
function fechasParaWeekdays($start, $end, $weekdays){
    $out = [];
    $cur = $start;
    while ($cur <= $end) {
        $w = (int)$cur->format('N');
        if (in_array($w, $weekdays)) {
            $out[] = $cur;
        }
        $cur = $cur->modify('+1 day');
    }
    return $out;
}

// devuelve las fechas semanales para un weekday
function fechasSemanal($start, $end, $weekday){
    $out = [];
    $cur = clone $start;
    while ((int)$cur->format('N') !== $weekday) {
        $cur = $cur->modify('+1 day');
    }
    while ($cur <= $end) {
        $out[] = clone $cur;
        $cur = $cur->modify('+1 week');
    }
    return $out;
}

// devuelve fechas cada 15 días
function fechasQuincenal($start, $end, $weekday){
    $out = [];
    $cur = clone $start;
    while ((int)$cur->format('N') !== $weekday) {
        $cur = $cur->modify('+1 day');
    }
    while ($cur <= $end) {
        $out[] = clone $cur;
        $cur = $cur->modify('+15 days');
    }
    return $out;
}

// devuelve la fecha del n-ésimo weekday de un mes
function nthWeekdayOfMonth($year, $month, $weekday, $n){
    $date = new DateTimeImmutable("{$year}-{$month}-01");
    while ((int)$date->format('N') !== $weekday) {
        $date = $date->modify('+1 day');
    }
    $date = $date->modify('+'.($n-1).' weeks');
    if ((int)$date->format('n') !== (int)$month) return null;
    return $date;
}

// obtiene "ordinal" desde "3ER","2DO","4TO", etc.
function parseOrdinal($s){
    if (!$s) return null;
    $s = preg_replace('/[^0-9]/', '', $s);
    return intval($s) ?: null;
}

// convierte nombre de mes español a número 1..12
function mesNumero($m){
    if (!$m) return null;
    $m = normalizeUpper($m);
    $map = [
        'ENERO'=>1,'FEBRERO'=>2,'MARZO'=>3,'ABRIL'=>4,'MAYO'=>5,'JUNIO'=>6,
        'JULIO'=>7,'AGOSTO'=>8,'SEPTIEMBRE'=>9,'OCTUBRE'=>10,'NOVIEMBRE'=>11,'DICIEMBRE'=>12
    ];
    return $map[$m] ?? null;
}

// parsea "LUNES A VIERNES" -> array de weekday nums
function parseRangoDias($s, $dias_map){
    $s = normalizeUpper($s);
    if (strpos($s, 'A') !== false && preg_match('/([A-ZÁÉÍÓÚÑ]+)\s*A\s*([A-ZÁÉÍÓÚÑ]+)/u',$s,$m)){
        $inicio = $m[1];
        $fin = $m[2];
        $order = ['LUNES','MARTES','MIERCOLES','JUEVES','VIERNES','SABADO','DOMINGO'];
        $iStart = array_search($inicio, $order);
        $iEnd = array_search($fin, $order);
        if ($iStart === false || $iEnd === false) return [];
        $res = [];
        for ($i = $iStart; ; $i++){
            $res[] = $dias_map[$order[$i]];
            if ($i === $iEnd) break;
        }
        return $res;
    }
    return [];
}

// --- leer entrada ---
$data = json_decode(file_get_contents("php://input"), true);
if (!$data) {
    echo json_encode(["error" => true, "msg" => "No se recibieron datos"]);
    exit;
}

// prepared statements
$stmtInsertBase = $pdo->prepare("
    INSERT INTO tareas_ciclicas (
        puesto, actividad, categoria, subcategoria, frecuencia, cuando, cada_cuanto, hora
    ) VALUES (?,?,?,?,?,?,?,?)
"); 

$stmtInsertInst = $pdo->prepare("
    INSERT INTO tareas_instancias (
        tarea_id, fecha, hora, puesto, actividad, categoria, subcategoria, creado_en, area
    ) VALUES (?,?,?,?,?,?,?,NOW(),?)
");

// transacción
$pdo->beginTransaction();
$insertados = 0;
$instancias_totales = 0;

try {
    foreach ($data as $item) {
        // limpiar y normalizar campos
        $puesto = limpiar($item['puesto']);
        $actividad = limpiar($item['actividad']);
        $categoria = limpiar($item['categoria']);
        $subcategoria = limpiar($item['subcategoria']);
        $frecuencia_raw = normalizeUpper($item['frecuencia']);
        $cuando_raw = normalizeUpper($item['cuando']);
        $cada_raw = limpiar($item['cada_cuanto']);
        $hora_raw = limpiar($item['hora']);
        $area = limpiar($item['area']);
        $descripcion = limpiar($item['descripcion']);

        // insertar base y obtener id
        $stmtInsertBase->execute([
            $puesto, $actividad, $categoria, $subcategoria,
            $frecuencia_raw, $cuando_raw, $cada_raw, $hora_raw
        ]);
        $tarea_id = $pdo->lastInsertId();
        $insertados++;

        // ---- generar instancias según frecuencia ----
        $fechas_generadas = [];

        // DIARIO
        if (stripos($frecuencia_raw, 'DIARI') !== false) {
            $weekdays = parseRangoDias($cuando_raw, $dias_map);
            if (!empty($weekdays)) {
                $fechas_generadas = fechasParaWeekdays($startDate, $endDate, $weekdays);
            } else {
                $fechas_generadas = fechasParaWeekdays($startDate, $endDate, [1,2,3,4,5,6,7]);
            }
        }

        // SEMANAL
        else if (stripos($frecuencia_raw, 'SEMAN') !== false) {
            // cuando puede ser múltiples días (ej: "LUNES MARTES")
            $dias = explode(' ', $cuando_raw);
            foreach ($dias as $dia) {
                $diaNum = diaNumero($dia, $dias_map);
                if ($diaNum) {
                    $fechas = fechasSemanal($startDate, $endDate, $diaNum);
                    $fechas_generadas = array_merge($fechas_generadas, $fechas);
                }
            }
            // Eliminar duplicados y ordenar
            $fechas_generadas = array_unique($fechas_generadas, SORT_REGULAR);
            usort($fechas_generadas, function($a, $b) {
                return $a <=> $b;
            });
        }

        // QUINCENAL
        else if (stripos($frecuencia_raw, 'QUINC') !== false || stripos($frecuencia_raw, '15') !== false) {
            $diaNum = diaNumero($cuando_raw, $dias_map);
            if ($diaNum) {
                $fechas_generadas = fechasQuincenal($startDate, $endDate, $diaNum);
            }
        }

        // MENSUAL
        else if (stripos($frecuencia_raw, 'MENS') !== false) {
            if (preg_match('/DIA\s*(\d{1,2})/i', $cuando_raw, $m)) {
                $dayOfMonth = intval($m[1]);
                $cur = clone $startDate;
                while ($cur <= $endDate) {
                    $y = (int)$cur->format('Y');
                    $mth = (int)$cur->format('n');
                    if (checkdate($mth, $dayOfMonth, $y)) {
                        $d = new DateTimeImmutable("{$y}-{$mth}-".str_pad($dayOfMonth,2,'0',STR_PAD_LEFT));
                        if ($d >= $startDate && $d <= $endDate) $fechas_generadas[] = $d;
                    }
                    $cur = $cur->modify('+1 month')->modify('first day of this month');
                }
            } else {
                $ordinal = parseOrdinal($cuando_raw);
                foreach ($dias_map as $name => $num) {
                    if (stripos($cuando_raw, $name) !== false) {
                        $weekday = $num;
                        break;
                    }
                }
                if (!empty($weekday) && $ordinal) {
                    $cur = clone $startDate;
                    while ($cur <= $endDate) {
                        $y = (int)$cur->format('Y');
                        $mth = (int)$cur->format('n');
                        $d = nthWeekdayOfMonth($y, $mth, $weekday, $ordinal);
                        if ($d && $d >= $startDate && $d <= $endDate) $fechas_generadas[] = $d;
                        $cur = $cur->modify('+1 month')->modify('first day of this month');
                    }
                }
            }
        }

        // BIMESTRAL / TRIMESTRAL / SEMESTRAL / ANUAL
        else if (stripos($frecuencia_raw,'BIMEST') !== false
              || stripos($frecuencia_raw,'TRIMEST') !== false
              || stripos($frecuencia_raw,'SEMEST') !== false
              || stripos($frecuencia_raw,'ANUAL') !== false) {

            if (stripos($frecuencia_raw,'BIMEST') !== false) $interval_months = 2;
            if (stripos($frecuencia_raw,'TRIMEST') !== false) $interval_months = 3;
            if (stripos($frecuencia_raw,'SEMEST') !== false) $interval_months = 6;
            if (stripos($frecuencia_raw,'ANUAL') !== false) $interval_months = 12;

            $ordinal = parseOrdinal($cuando_raw);
            $weekday = null;
            foreach ($dias_map as $name => $num) {
                if (stripos($cuando_raw, $name) !== false) {
                    $weekday = $num;
                    break;
                }
            }

            $mes_inicio = mesNumero($cada_raw);

            $cur = clone $startDate;
            if ($mes_inicio) {
                $year = (int)$cur->format('Y');
                $mth = (int)$cur->format('n');
                if ($mth > $mes_inicio) $year++;
                $cur = new DateTimeImmutable("{$year}-".str_pad($mes_inicio,2,'0',STR_PAD_LEFT)."-01");
            } else {
                $cur = $cur->modify('first day of this month');
            }

            while ($cur <= $endDate) {
                if ($ordinal && $weekday) {
                    $d = nthWeekdayOfMonth((int)$cur->format('Y'), (int)$cur->format('n'), $weekday, $ordinal);
                    if ($d && $d >= $startDate && $d <= $endDate) $fechas_generadas[] = $d;
                } else if (preg_match('/DIA\s*(\d{1,2})/i', $cuando_raw, $m2)) {
                    $dayOfMonth = intval($m2[1]);
                    if (checkdate((int)$cur->format('n'), $dayOfMonth, (int)$cur->format('Y'))) {
                        $d = new DateTimeImmutable($cur->format('Y').'-'.$cur->format('n').'-'.str_pad($dayOfMonth,2,'0',STR_PAD_LEFT));
                        if ($d >= $startDate && $d <= $endDate) $fechas_generadas[] = $d;
                    }
                }
                $cur = $cur->modify("+{$interval_months} months")->modify('first day of this month');
            }
        }

        // ESPORADICO -> no generar instancias automáticas
        else if (stripos($frecuencia_raw,'ESPOR') !== false) {
            $fechas_generadas = [];
        }

        // fallback
        else {
            $diaNum = diaNumero($cuando_raw, $dias_map);
            if ($diaNum) $fechas_generadas = fechasSemanal($startDate, $endDate, $diaNum);
        }

        // insertar instancias
        foreach ($fechas_generadas as $dt) {
            $fecha = $dt->format('Y-m-d');
            $hora_val = ($hora_raw !== '' ? $hora_raw : '08:00:00');
            $stmtInsertInst->execute([
                $tarea_id, $fecha, $hora_val,
                $puesto, $actividad, $categoria, $subcategoria, $area
            ]);
            $instancias_totales++;
        }

    } // foreach data

    $pdo->commit();
    echo json_encode([
        "success" => true,
        "message" => "Tarea creada exitosamente. Bases insertadas: $insertados, Instancias generadas: $instancias_totales",
        "months_ahead" => $months_ahead
    ]);
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(["error" => true, "msg" => $e->getMessage()]);
    exit;
}