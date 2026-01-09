<?php
session_start();
include 'includes/conexionbd.php';

$token = $_GET['token'] ?? '';

if (empty($token)) {
    $_SESSION['error'] = "Token inválido";
    header('Location: login.php');
    exit();
}

// Verificar token
try {
    $stmt = $conexion->prepare("SELECT Id_Usuario FROM usuarios WHERE token_reset = :token AND token_expiracion > NOW()");
    $stmt->bindParam(':token', $token);
    $stmt->execute();
    
    if ($stmt->rowCount() !== 1) {
        $_SESSION['error'] = "Token inválido o expirado";
        header('Location: login.php');
        exit();
    }
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $user_id = $user['Id_Usuario'];
    
} catch(PDOException $e) {
    $_SESSION['error'] = "Error en el sistema: " . $e->getMessage();
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (strlen($password) < 6) {
        $error = "La contraseña debe tener al menos 6 caracteres";
    } elseif ($password !== $confirm_password) {
        $error = "Las contraseñas no coinciden";
    } else {
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $conexion->prepare("UPDATE usuarios SET password = :password, token_reset = NULL, token_expiracion = NULL WHERE Id_Usuario = :id");
            $stmt->bindParam(':password', $hashed_password);
            $stmt->bindParam(':id', $user_id);
            $stmt->execute();
            
            $_SESSION['success'] = "Contraseña actualizada exitosamente. Ahora puedes iniciar sesión.";
            header('Location: login.php');
            exit();
            
        } catch(PDOException $e) {
            $error = "Error al actualizar contraseña: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Contraseña - Sistema de Gestión</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full space-y-8 p-8">
        <div class="bg-white rounded-2xl shadow-lg p-8">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-gray-900">Flow</h1>
                <p class="text-gray-600 mt-2">Establecer nueva contraseña</p>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="space-y-6">
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">Nueva contraseña</label>
                    <input type="password" id="password" name="password" required 
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    <p class="mt-1 text-sm text-gray-500">Mínimo 6 caracteres</p>
                </div>
                
                <div>
                    <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirmar nueva contraseña</label>
                    <input type="password" id="confirm_password" name="confirm_password" required 
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Establecer nueva contraseña
                </button>
            </form>
        </div>
    </div>
</body>
</html>