<?php
session_start();
include 'includes/conexionbd.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Obtener datos para selects
$puestos = $pdo->query("SELECT Id_puesto, nombre FROM puestos")->fetchAll(PDO::FETCH_ASSOC);
$departamentos = $pdo->query("SELECT id, nombre FROM departamentos")->fetchAll(PDO::FETCH_ASSOC);
$oficinas = $pdo->query("SELECT id, nombre FROM oficinas")->fetchAll(PDO::FETCH_ASSOC);
$areas = $pdo->query("SELECT id, nombre FROM subareas ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Datos básicos del usuario
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $whatsapp = trim($_POST['whatsapp'] ?? '');
    
    // Datos laborales (opcionales para usuario normal, requeridos para admin)
    $id_puesto = $_POST['id_puesto'] ?? null;
    $id_departamento = $_POST['id_departamento'] ?? null;
    $id_oficina = $_POST['id_oficina'] ?? null;
    $areas_seleccionadas = $_POST['areas'] ?? []; // Array de áreas múltiples
    
    $errors = [];
    
    // Validaciones básicas
    if (empty($nombre)) $errors[] = "El nombre es requerido";
    if (empty($apellido)) $errors[] = "El apellido es requerido";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Email válido es requerido";
    if (strlen($password) < 6) $errors[] = "La contraseña debe tener al menos 6 caracteres";
    if ($password !== $confirm_password) $errors[] = "Las contraseñas no coinciden";
    
    // Validar WhatsApp (opcional pero con formato si se proporciona)
    if (!empty($whatsapp) && !preg_match('/^[0-9]{10,15}$/', $whatsapp)) {
        $errors[] = "El número de WhatsApp debe contener solo números (10-15 dígitos)";
    }
    
    // Verificar si el email ya existe
    try {
        $stmt = $pdo->prepare("SELECT Id_Usuario FROM usuarios WHERE email = :email");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        if ($stmt->rowCount() > 0) $errors[] = "El email ya está registrado";
    } catch(PDOException $e) {
        $errors[] = "Error en el sistema: " . $e->getMessage();
    }
    
    if (empty($errors)) {
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $rolActual = 2; // Rol de usuario por defecto
            $estatu = 1; // Activo
            
            // Convertir array de áreas a JSON para almacenar en el campo subarea
            $subarea_json = !empty($areas_seleccionadas) ? json_encode($areas_seleccionadas) : null;
            
            $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, apellido, email, contrasena, rolActual, Id_puesto, Id_departamento, Id_oficina, estatu, subarea, whatsapp) 
                                   VALUES (:nombre, :apellido, :email, :password, :rol, :puesto, :departamento, :oficina, :estatu, :subarea, :whatsapp)");
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':apellido', $apellido);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password', $hashed_password);
            $stmt->bindParam(':rol', $rolActual);
            $stmt->bindParam(':puesto', $id_puesto);
            $stmt->bindParam(':departamento', $id_departamento);
            $stmt->bindParam(':oficina', $id_oficina);
            $stmt->bindParam(':estatu', $estatu);
            $stmt->bindParam(':subarea', $subarea_json);
            $stmt->bindParam(':whatsapp', $whatsapp);
            $stmt->execute();
            
            $_SESSION['success'] = "Registro exitoso. Ahora puedes iniciar sesión.";
            header('Location: index.php');
            exit();
            
        } catch(PDOException $e) {
            $errors[] = "Error al registrar usuario: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flow | Crear Cuenta</title>
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
        .step {
            display: none;
            animation: fadeIn 0.5s ease-in;
        }
        .step.active {
            display: block;
        }
        .progress-bar {
            transition: width 0.5s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .form-group {
            position: relative;
            margin-bottom: 1.5rem;
        }
        .floating-label {
            position: absolute;
            top: 50%;
            left: 12px;
            transform: translateY(-50%);
            background: white;
            padding: 0 4px;
            color: #6b7280;
            transition: all 0.3s ease;
            pointer-events: none;
        }
        .form-input:focus + .floating-label,
        .form-input:not(:placeholder-shown) + .floating-label {
            top: 0;
            font-size: 0.75rem;
            color: #3b82f6;
        }
        .select-arrow {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.5rem center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
        }
        .areas-select {
            height: 150px;
            padding: 10px;
        }
        .areas-select option {
            padding: 8px 12px;
            margin: 2px 0;
            border-radius: 4px;
        }
        .areas-select option:checked {
            background-color: #3b82f6;
            color: white;
        }
    </style>
</head>
<body class="min-h-screen gradient-bg flex items-center justify-center p-4">
    <div class="max-w-2xl w-full">
        <div class="bg-white rounded-2xl shadow-2xl p-8 card-hover">
            <!-- Progress Bar -->
            <div class="mb-8">
                <div class="flex justify-between items-center mb-4">
                    <div class="flex space-x-4">
                        <div class="step-indicator flex items-center space-x-2">
                            <div class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center text-sm font-medium">1</div>
                            <span class="text-sm font-medium text-gray-700">Información Personal</span>
                        </div>
                        <div class="step-indicator flex items-center space-x-2 opacity-50">
                            <div class="w-8 h-8 rounded-full bg-gray-300 text-gray-600 flex items-center justify-center text-sm font-medium">2</div>
                            <span class="text-sm font-medium text-gray-500">Información Laboral</span>
                        </div>
                        <div class="step-indicator flex items-center space-x-2 opacity-50">
                            <div class="w-8 h-8 rounded-full bg-gray-300 text-gray-600 flex items-center justify-center text-sm font-medium">3</div>
                            <span class="text-sm font-medium text-gray-500">Confirmación</span>
                        </div>
                    </div>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="progress-bar bg-blue-600 h-2 rounded-full w-1/3"></div>
                </div>
            </div>

            <!-- Error Messages -->
            <?php if (!empty($errors)): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl">
                    <div class="flex items-center space-x-3 mb-2">
                        <div class="w-6 h-6 bg-red-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-exclamation-triangle text-red-600 text-sm"></i>
                        </div>
                        <span class="text-red-800 font-medium">Errores en el formulario:</span>
                    </div>
                    <ul class="list-disc list-inside text-red-700 space-y-1">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6" id="registerForm">
                
                <!-- Step 1: Personal Information -->
                <div class="step active" id="step1">
                    <div class="text-center mb-8">
                        <div class="w-16 h-16 bg-blue-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-user text-blue-600 text-2xl"></i>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900">Información Personal</h3>
                        <p class="text-gray-600 mt-2">Comencemos con tus datos básicos</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="form-group">
                            <input type="text" id="nombre" name="nombre" required 
                                   class="form-input w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent input-focus transition-all duration-200"
                                   placeholder=" "
                                   value="<?php echo isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : ''; ?>">
                            <label for="nombre" class="floating-label">Nombre *</label>
                        </div>
                        
                        <div class="form-group">
                            <input type="text" id="apellido" name="apellido" required 
                                   class="form-input w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent input-focus transition-all duration-200"
                                   placeholder=" "
                                   value="<?php echo isset($_POST['apellido']) ? htmlspecialchars($_POST['apellido']) : ''; ?>">
                            <label for="apellido" class="floating-label">Apellido *</label>
                        </div>
                    </div>

                    <div class="form-group">
                        <input type="email" id="email" name="email" required 
                               class="form-input w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent input-focus transition-all duration-200"
                               placeholder=" "
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        <label for="email" class="floating-label">Email *</label>
                    </div>

                    <!-- NUEVO CAMPO: WhatsApp -->
                    <div class="form-group">
                        <input type="tel" id="whatsapp" name="whatsapp" 
                               class="form-input w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent input-focus transition-all duration-200"
                               placeholder=" "
                               value="<?php echo isset($_POST['whatsapp']) ? htmlspecialchars($_POST['whatsapp']) : ''; ?>"
                               pattern="[0-9]{10,15}"
                               title="Solo números, 10-15 dígitos">
                        <label for="whatsapp" class="floating-label">WhatsApp de la empresa</label>
                        <p class="text-xs text-gray-500 mt-1">Opcional - Solo números sin espacios ni símbolos</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="form-group">
                            <input type="password" id="password" name="password" required 
                                   class="form-input w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent input-focus transition-all duration-200"
                                   placeholder=" "
                                   oninput="checkPasswordStrength(this.value)">
                            <label for="password" class="floating-label">Contraseña *</label>
                            <div id="password-strength" class="password-strength mt-2">
                                <div class="flex space-x-1 mb-1">
                                    <div class="h-1 flex-1 bg-gray-200 rounded-full" id="strength-bar-1"></div>
                                    <div class="h-1 flex-1 bg-gray-200 rounded-full" id="strength-bar-2"></div>
                                    <div class="h-1 flex-1 bg-gray-200 rounded-full" id="strength-bar-3"></div>
                                    <div class="h-1 flex-1 bg-gray-200 rounded-full" id="strength-bar-4"></div>
                                </div>
                                <p class="text-xs text-gray-500" id="strength-text">Mínimo 6 caracteres</p>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <input type="password" id="confirm_password" name="confirm_password" required 
                                   class="form-input w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent input-focus transition-all duration-200"
                                   placeholder=" "
                                   oninput="checkPasswordMatch()">
                            <label for="confirm_password" class="floating-label">Confirmar Contraseña *</label>
                            <p class="text-xs mt-2" id="match-text"></p>
                        </div>
                    </div>

                    <div class="flex justify-end mt-8">
                        <button type="button" onclick="nextStep(2)" class="bg-blue-600 hover:bg-blue-700 text-white py-3 px-6 rounded-xl font-medium transition-all duration-200 transform hover:scale-[1.02] focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                            Siguiente
                            <i class="fas fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                </div>

                <!-- Step 2: Work Information -->
                <div class="step" id="step2">
                    <div class="text-center mb-8">
                        <div class="w-16 h-16 bg-green-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-briefcase text-green-600 text-2xl"></i>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900">Información Laboral</h3>
                        <p class="text-gray-600 mt-2">Esta información es opcional para usuarios regulares</p>
                    </div>

                    <div class="space-y-6">
                        <div class="form-group">
                            <select id="id_puesto" name="id_puesto" class="select-arrow w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent input-focus transition-all duration-200 appearance-none bg-white">
                                <option value="">Selecciona tu puesto</option>
                                <?php foreach ($puestos as $puesto): ?>
                                    <option value="<?= $puesto['Id_puesto'] ?>" <?= (isset($_POST['id_puesto']) && $_POST['id_puesto'] == $puesto['Id_puesto']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($puesto['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <select id="id_departamento" name="id_departamento" class="select-arrow w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent input-focus transition-all duration-200 appearance-none bg-white">
                                <option value="">Selecciona tu departamento</option>
                                <?php foreach ($departamentos as $departamento): ?>
                                    <option value="<?= $departamento['id'] ?>" <?= (isset($_POST['id_departamento']) && $_POST['id_departamento'] == $departamento['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($departamento['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <select id="id_oficina" name="id_oficina" class="select-arrow w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent input-focus transition-all duration-200 appearance-none bg-white">
                                <option value="">Selecciona tu oficina</option>
                                <?php foreach ($oficinas as $oficina): ?>
                                    <option value="<?= $oficina['id'] ?>" <?= (isset($_POST['id_oficina']) && $_POST['id_oficina'] == $oficina['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($oficina['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- NUEVO CAMPO: Selección múltiple de áreas -->
                        <div class="form-group">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Selecciona tus áreas de responsabilidad
                                <span class="text-xs text-gray-500">(Mantén Ctrl/Cmd para seleccionar múltiples)</span>
                            </label>
                            <select id="areas" name="areas[]" multiple 
                                    class="areas-select w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent input-focus transition-all duration-200 appearance-none bg-white">
                                <?php foreach ($areas as $area): ?>
                                    <option value="<?= $area['id'] ?>" 
                                        <?= (isset($_POST['areas']) && is_array($_POST['areas']) && in_array($area['id'], $_POST['areas'])) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($area['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Selecciona todas las áreas bajo tu responsabilidad</p>
                        </div>
                    </div>

                    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mt-6">
                        <div class="flex items-start space-x-3">
                            <i class="fas fa-info-circle text-blue-600 mt-1"></i>
                            <div>
                                <p class="text-blue-800 text-sm">
                                    <strong>Nota:</strong> Esta información puede ser completada más tarde por un administrador.
                                    Puedes omitirla si aún no conoces estos datos.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-between mt-8">
                        <button type="button" onclick="prevStep(1)" class="bg-gray-500 hover:bg-gray-600 text-white py-3 px-6 rounded-xl font-medium transition-all duration-200 transform hover:scale-[1.02] focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                            <i class="fas fa-arrow-left mr-2"></i>
                            Anterior
                        </button>
                        <button type="button" onclick="nextStep(3)" class="bg-blue-600 hover:bg-blue-700 text-white py-3 px-6 rounded-xl font-medium transition-all duration-200 transform hover:scale-[1.02] focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                            Siguiente
                            <i class="fas fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                </div>

                <!-- Step 3: Confirmation -->
                <div class="step" id="step3">
                    <div class="text-center mb-8">
                        <div class="w-16 h-16 bg-purple-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-check-circle text-purple-600 text-2xl"></i>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900">Confirmar Registro</h3>
                        <p class="text-gray-600 mt-2">Revisa tu información antes de enviar</p>
                    </div>

                    <div class="bg-gray-50 rounded-xl p-6 space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-600">Nombre completo</p>
                                <p class="font-medium" id="review-nombre"></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Email</p>
                                <p class="font-medium" id="review-email"></p>
                            </div>
                        </div>
                        
                        <!-- WhatsApp en revisión -->
                        <div>
                            <p class="text-sm text-gray-600">WhatsApp Empresa</p>
                            <p class="font-medium" id="review-whatsapp">No especificado</p>
                        </div>
                        
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <p class="text-sm text-gray-600">Puesto</p>
                                <p class="font-medium" id="review-puesto">No especificado</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Departamento</p>
                                <p class="font-medium" id="review-departamento">No especificado</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Oficina</p>
                                <p class="font-medium" id="review-oficina">No especificado</p>
                            </div>
                        </div>
                        
                        <!-- Áreas en revisión -->
                        <div>
                            <p class="text-sm text-gray-600">Áreas de Responsabilidad</p>
                            <p class="font-medium" id="review-areas">No especificado</p>
                        </div>
                    </div>

                    <div class="flex items-start space-x-3 mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-xl">
                        <input type="checkbox" id="terms" name="terms" required class="w-4 h-4 text-blue-600 rounded focus:ring-blue-500 border-gray-300 mt-1">
                        <label for="terms" class="text-sm text-gray-600">
                            Acepto los <a href="#" class="text-blue-600 hover:text-blue-500 font-medium">términos y condiciones</a> 
                            y la <a href="#" class="text-blue-600 hover:text-blue-500 font-medium">política de privacidad</a>
                        </label>
                    </div>

                    <div class="flex justify-between mt-8">
                        <button type="button" onclick="prevStep(2)" class="bg-gray-500 hover:bg-gray-600 text-white py-3 px-6 rounded-xl font-medium transition-all duration-200 transform hover:scale-[1.02] focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                            <i class="fas fa-arrow-left mr-2"></i>
                            Anterior
                        </button>
                        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white py-3 px-6 rounded-xl font-medium transition-all duration-200 transform hover:scale-[1.02] focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                            <i class="fas fa-user-plus mr-2"></i>
                            Completar Registro
                        </button>
                    </div>
                </div>
            </form>

            <div class="mt-8 pt-6 border-t border-gray-200 text-center">
                <p class="text-gray-600">
                    ¿Ya tienes una cuenta?
                    <a href="./" class="text-blue-600 hover:text-blue-500 font-medium transition-colors duration-200">
                        Iniciar sesión
                    </a>
                </p>
            </div>
        </div>
    </div>

    <script>
        let currentStep = 1;
        const totalSteps = 3;

        function nextStep(step) {
            if (validateStep(currentStep)) {
                document.getElementById(`step${currentStep}`).classList.remove('active');
                document.getElementById(`step${step}`).classList.add('active');
                updateProgress(step);
                currentStep = step;
                
                if (step === 3) {
                    updateReview();
                }
            }
        }

        function prevStep(step) {
            document.getElementById(`step${currentStep}`).classList.remove('active');
            document.getElementById(`step${step}`).classList.add('active');
            updateProgress(step);
            currentStep = step;
        }

        function updateProgress(step) {
            const progress = (step / totalSteps) * 100;
            document.querySelector('.progress-bar').style.width = `${progress}%`;
            
            // Update step indicators
            document.querySelectorAll('.step-indicator').forEach((indicator, index) => {
                const stepNumber = index + 1;
                const circle = indicator.querySelector('div');
                if (stepNumber <= step) {
                    indicator.classList.remove('opacity-50');
                    circle.className = 'w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center text-sm font-medium';
                } else {
                    indicator.classList.add('opacity-50');
                    circle.className = 'w-8 h-8 rounded-full bg-gray-300 text-gray-600 flex items-center justify-center text-sm font-medium';
                }
            });
        }

        function validateStep(step) {
            let isValid = true;
            
            if (step === 1) {
                const requiredFields = ['nombre', 'apellido', 'email', 'password', 'confirm_password'];
                requiredFields.forEach(field => {
                    const input = document.getElementById(field);
                    if (!input.value.trim()) {
                        input.classList.add('border-red-500');
                        isValid = false;
                    } else {
                        input.classList.remove('border-red-500');
                    }
                });
                
                // Validate password match
                const password = document.getElementById('password').value;
                const confirmPassword = document.getElementById('confirm_password').value;
                if (password !== confirmPassword) {
                    document.getElementById('confirm_password').classList.add('border-red-500');
                    isValid = false;
                } else {
                    document.getElementById('confirm_password').classList.remove('border-red-500');
                }
                
                // Validate WhatsApp format if provided
                const whatsapp = document.getElementById('whatsapp').value;
                if (whatsapp && !/^[0-9]{10,15}$/.test(whatsapp)) {
                    document.getElementById('whatsapp').classList.add('border-red-500');
                    isValid = false;
                } else {
                    document.getElementById('whatsapp').classList.remove('border-red-500');
                }
            }
            
            return isValid;
        }

        function updateReview() {
            // Personal information
            document.getElementById('review-nombre').textContent = 
                `${document.getElementById('nombre').value} ${document.getElementById('apellido').value}`;
            document.getElementById('review-email').textContent = document.getElementById('email').value;
            
            // WhatsApp
            const whatsapp = document.getElementById('whatsapp').value;
            document.getElementById('review-whatsapp').textContent = 
                whatsapp ? `+${whatsapp}` : 'No especificado';
            
            // Work information
            const puestoSelect = document.getElementById('id_puesto');
            const departamentoSelect = document.getElementById('id_departamento');
            const oficinaSelect = document.getElementById('id_oficina');
            
            document.getElementById('review-puesto').textContent = 
                puestoSelect.value ? puestoSelect.options[puestoSelect.selectedIndex].text : 'No especificado';
            document.getElementById('review-departamento').textContent = 
                departamentoSelect.value ? departamentoSelect.options[departamentoSelect.selectedIndex].text : 'No especificado';
            document.getElementById('review-oficina').textContent = 
                oficinaSelect.value ? oficinaSelect.options[oficinaSelect.selectedIndex].text : 'No especificado';
            
            // Areas
            const areasSelect = document.getElementById('areas');
            let areasText = 'No especificado';
            if (areasSelect.selectedOptions.length > 0) {
                const areasArray = Array.from(areasSelect.selectedOptions).map(opt => opt.text);
                areasText = areasArray.join(', ');
            }
            document.getElementById('review-areas').textContent = areasText;
        }

        // Password strength and match functions
        function checkPasswordStrength(password) {
            const bars = [
                document.getElementById('strength-bar-1'),
                document.getElementById('strength-bar-2'),
                document.getElementById('strength-bar-3'),
                document.getElementById('strength-bar-4')
            ];
            const strengthText = document.getElementById('strength-text');
            
            bars.forEach(bar => bar.className = 'h-1 flex-1 bg-gray-200 rounded-full');
            
            let strength = 0;
            let text = 'Muy débil';
            let color = 'red';
            
            if (password.length >= 6) strength++;
            if (password.length >= 8) strength++;
            if (/[A-Z]/.test(password) && /[a-z]/.test(password)) strength++;
            if (/[0-9]/.test(password) && /[^A-Za-z0-9]/.test(password)) strength++;
            
            if (strength >= 1) {
                bars[0].className = 'h-1 flex-1 bg-red-500 rounded-full';
                text = 'Débil';
            }
            if (strength >= 2) {
                bars[1].className = 'h-1 flex-1 bg-yellow-500 rounded-full';
                text = 'Regular';
                color = 'yellow';
            }
            if (strength >= 3) {
                bars[2].className = 'h-1 flex-1 bg-blue-500 rounded-full';
                text = 'Buena';
                color = 'blue';
            }
            if (strength >= 4) {
                bars[3].className = 'h-1 flex-1 bg-green-500 rounded-full';
                text = 'Excelente';
                color = 'green';
            }
            
            strengthText.textContent = `Fortaleza: ${text}`;
            strengthText.className = `text-xs text-${color}-500`;
        }
        
        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchText = document.getElementById('match-text');
            
            if (confirmPassword === '') {
                matchText.textContent = '';
                return;
            }
            
            if (password === confirmPassword) {
                matchText.textContent = 'Las contraseñas coinciden';
                matchText.className = 'text-xs text-green-500';
                document.getElementById('confirm_password').classList.remove('border-red-500');
            } else {
                matchText.textContent = 'Las contraseñas no coinciden';
                matchText.className = 'text-xs text-red-500';
                document.getElementById('confirm_password').classList.add('border-red-500');
            }
        }

        // Floating labels functionality
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('.form-input');
            inputs.forEach(input => {
                // Initialize floating labels
                if (input.value) {
                    input.nextElementSibling.classList.add('top-0', 'text-xs', 'text-blue-600');
                }
                
                input.addEventListener('focus', function() {
                    this.parentElement.classList.add('ring-2', 'ring-blue-200');
                    this.nextElementSibling.classList.add('top-0', 'text-xs', 'text-blue-600');
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.classList.remove('ring-2', 'ring-blue-200');
                    if (!this.value) {
                        this.nextElementSibling.classList.remove('top-0', 'text-xs', 'text-blue-600');
                    }
                });
            });
            
            // Initialize selects
            const selects = document.querySelectorAll('select');
            selects.forEach(select => {
                select.addEventListener('focus', function() {
                    this.parentElement.classList.add('ring-2', 'ring-blue-200');
                });
                
                select.addEventListener('blur', function() {
                    this.parentElement.classList.remove('ring-2', 'ring-blue-200');
                });
            });
        });
    </script>
</body>
</html>