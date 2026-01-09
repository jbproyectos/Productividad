<?php
session_start();
include '../includes/conexionbd.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        if (!isset($_SESSION['user_id'])) {
            echo json_encode([
                'success' => false,
                'message' => 'No hay sesión activa. Inicia sesión nuevamente.'
            ]);
            exit();
        }

        $user_id = $_SESSION['user_id'];

        // Generar folio tipo #TK-XXXXXX (aleatorio, único)
        $folio = '#TK-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));

        $sql = "INSERT INTO tickets (
                    folio,
                    area,
                    donde,
                    detalle_donde,
                    categoria_id,
                    subcategoria_id,
                    descripcion,
                    fecha_creacion,
                    estado,
                    usuario_id
                ) VALUES (
                    :folio,
                    :area,
                    :donde,
                    :detalle_donde,
                    :categoria_id,
                    :subcategoria_id,
                    :descripcion,
                    NOW(),
                    :estado,
                    :usuario_id
                )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':folio' => $folio,
            ':area' => $_POST['area'],
            ':donde' => $_POST['donde'],
            ':detalle_donde' => $_POST['detalle_donde'],
            ':categoria_id' => $_POST['categoria_servicio'],
            ':subcategoria_id' => $_POST['subcategoria'],
            ':descripcion' => $_POST['descripcion'],
            ':estado' => 'pendiente',
            ':usuario_id' => $user_id
        ]);

        echo json_encode([
            'success' => true,
            'message' => "Ticket registrado correctamente con folio: $folio",
            'ticket_id' => $folio
        ]);

    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error al registrar el ticket: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
}
?>
