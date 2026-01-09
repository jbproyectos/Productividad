<?php
session_start();
include 'includes/conexionbd.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    
    try {
        $stmt = $conexion->prepare("SELECT Id_Usuario, nombre FROM usuarios WHERE email = :email AND activo = 1");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->rowCount() === 1) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $token = bin2hex(random_bytes(50));
            $expiracion = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            $stmt = $conexion->prepare("UPDATE usuarios SET token_reset = :token, token_expiracion = :expiracion WHERE Id_Usuario = :id");
            $stmt->bindParam(':token', $token);
            $stmt->bindParam(':expiracion', $expiracion);
            $stmt->bindParam(':id', $user['Id_Usuario']);
            $stmt->execute();
            
            // En producción, enviar email aquí
            $reset_link = "http://tudominio.com/reset_password.php?token=" . $token;
            
            $_SESSION['info'] = "Se ha enviado un enlace de recuperación a <strong>{$email}</strong>. Revisa tu bandeja de entrada.";
            
        } else {
            $_SESSION['info'] = "Si el email existe en nuestro sistema, recibirás un enlace de recuperación.";
        }
        
    } catch(PDOException $e) {
        $_SESSION['error'] = "Error en el sistema: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flow | Recuperar Contraseña</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3B82F6',
                        secondary: '#64748B',
                        success: '#10B981',
                        warning: '#F59E0B',
                        error: '#EF4444',
                        surface: '#F8FAFC',
                        border: '#E2E8F0'
                    }
                }
            }
        }
    </script>
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .card-hover {
            transition: all 0.3s ease;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        .input-focus:focus {
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
    </style>
</head>
<body class="min-h-screen gradient-bg flex items-center justify-center p-4">
    <div class="max-w-md w-full">
        <div class="bg-white rounded-2xl shadow-2xl p-8 card-hover">
            <div class="text-center mb-8">
                <div class="w-16 h-16 bg-blue-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-key text-blue-600 text-2xl"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-900">¿Olvidaste tu contraseña?</h3>
                <p class="text-gray-600 mt-2">Te enviaremos un enlace para restablecerla</p>
            </div>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl flex items-center space-x-3">
                    <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-exclamation-triangle text-red-600"></i>
                    </div>
                    <span class="text-red-700 font-medium"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['info'])): ?>
                <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-xl">
                    <div class="flex items-center space-x-3 mb-2">
                        <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-envelope text-blue-600"></i>
                        </div>
                        <span class="text-blue-800 font-medium">Correo enviado</span>
                    </div>
                    <p class="text-blue-700"><?php echo $_SESSION['info']; unset($_SESSION['info']); ?></p>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <div class="space-y-2">
                    <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-envelope text-gray-400"></i>
                        </div>
                        <input type="email" id="email" name="email" required 
                               class="pl-10 w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent input-focus transition-all duration-200"
                               placeholder="tu@email.com"
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                </div>
                
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 px-4 rounded-xl font-medium transition-all duration-200 transform hover:scale-[1.02] focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                    <i class="fas fa-paper-plane mr-2"></i>
                    Enviar enlace de recuperación
                </button>
            </form>

            <div class="mt-8 pt-6 border-t border-gray-200 text-center">
                <p class="text-gray-600">
                    <a href="index.php" class="text-blue-600 hover:text-blue-500 font-medium transition-colors duration-200 flex items-center justify-center space-x-2">
                        <i class="fas fa-arrow-left"></i>
                        <span>Volver al inicio de sesión</span>
                    </a>
                </p>
            </div>
        </div>
        
        <div class="mt-6 text-center text-white/80">
            <p>¿Necesitas ayuda? <a href="#" class="text-white hover:text-white/90 font-medium">Contáctanos</a></p>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('input');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.classList.add('ring-2', 'ring-blue-200', 'bg-blue-50');
                });
                input.addEventListener('blur', function() {
                    this.parentElement.classList.remove('ring-2', 'ring-blue-200', 'bg-blue-50');
                });
            });
        });
    </script>
</body>
</html>