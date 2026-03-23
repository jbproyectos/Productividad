<?php
session_start();
include 'includes/conexionbd.php';
$page_title = "Estado del Proyecto - Flow";
$current_page = "informacion";
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    
    <div class="max-w-4xl mx-auto px-4 py-8">
        
        <!-- Header simple -->
        <div class="bg-blue-600 text-white rounded-2xl p-6 mb-6">
            <h1 class="text-3xl font-bold">Estado del Proyecto</h1>
            <p class="text-blue-100 mt-1">Así vamos con el desarrollo de la plataforma</p>
        </div>

        <!-- Progreso general -->
        <div class="bg-white rounded-2xl shadow-lg p-6 mb-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">📈 ¿Cómo vamos?</h2>
            
            <!-- Barra de progreso total -->
            <div class="mb-6">
                <div class="flex justify-between mb-1">
                    <span class="text-gray-600">Progreso general</span>
                    <span class="font-bold text-blue-600">65%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-4">
                    <div class="bg-blue-600 h-4 rounded-full" style="width: 65%"></div>
                </div>
            </div>

            <!-- Módulos específicos -->
            <div class="space-y-4">
                <!-- Productividad -->
                <div>
                    <div class="flex justify-between mb-1">
                        <div class="flex items-center">
                            <span class="w-2 h-2 bg-green-500 rounded-full mr-2"></span>
                            <span class="text-gray-700">Módulo de Productividad</span>
                        </div>
                        <span class="font-bold text-green-600">80%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-3">
                        <div class="bg-green-500 h-3 rounded-full" style="width: 80%"></div>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">✓ Tareas, proyectos, seguimiento</p>
                </div>

                <!-- Catálogos -->
                <div>
                    <div class="flex justify-between mb-1">
                        <div class="flex items-center">
                            <span class="w-2 h-2 bg-blue-500 rounded-full mr-2"></span>
                            <span class="text-gray-700">Catálogos y Configuración</span>
                        </div>
                        <span class="font-bold text-blue-600">90%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-3">
                        <div class="bg-blue-500 h-3 rounded-full" style="width: 90%"></div>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">✓ Áreas, usuarios, categorías, tipos</p>
                </div>

                <!-- Reuniones -->
                <div>
                    <div class="flex justify-between mb-1">
                        <div class="flex items-center">
                            <span class="w-2 h-2 bg-gray-300 rounded-full mr-2"></span>
                            <span class="text-gray-700">Módulo de Reuniones</span>
                        </div>
                        <span class="font-bold text-gray-400">0%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-3">
                        <div class="bg-gray-300 h-3 rounded-full" style="width: 0%"></div>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">⏳ Pendiente por comenzar</p>
                </div>
            </div>
        </div>

        <!-- Qué sigue -->
        <div class="bg-white rounded-2xl shadow-lg p-6 mb-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">🎯 ¿Qué sigue?</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="border-l-4 border-green-500 pl-4">
                    <p class="font-medium text-gray-800">En estas semanas</p>
                    <p class="text-sm text-gray-600">Terminar productividad y catálogos</p>
                </div>
                <div class="border-l-4 border-yellow-500 pl-4">
                    <p class="font-medium text-gray-800">Próximo mes</p>
                    <p class="text-sm text-gray-600">Empezar con el módulo de reuniones</p>
                </div>
                <div class="border-l-4 border-blue-500 pl-4">
                    <p class="font-medium text-gray-800">En 2 meses</p>
                    <p class="text-sm text-gray-600">Pruebas con usuarios reales</p>
                </div>
                <div class="border-l-4 border-purple-500 pl-4">
                    <p class="font-medium text-gray-800">Lanzamiento</p>
                    <p class="text-sm text-gray-600">Versión completa en Junio 2024</p>
                </div>
            </div>
        </div>

        <!-- Equipo de desarrollo -->
        <div class="bg-white rounded-2xl shadow-lg p-6 mb-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">👨‍💻 ¿Quiénes están desarrollando?</h2>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <!-- Gustavo -->
                <div class="text-center p-3 bg-gray-50 rounded-xl">
                    <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-2">
                        <span class="text-2xl font-bold text-blue-600">G</span>
                    </div>
                    <h3 class="font-semibold text-gray-800">Gustavo</h3>
                    <p class="text-xs text-gray-500">Desarrollador</p>
                    <p class="text-xs text-blue-600 mt-1">gustavo@flow.com</p>
                </div>

                <!-- Juan -->
                <div class="text-center p-3 bg-gray-50 rounded-xl">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-2">
                        <span class="text-2xl font-bold text-green-600">J</span>
                    </div>
                    <h3 class="font-semibold text-gray-800">Juan</h3>
                    <p class="text-xs text-gray-500">Desarrollador</p>
                    <p class="text-xs text-green-600 mt-1">juan@flow.com</p>
                </div>

                <!-- Espacio para más devs -->
                <div class="text-center p-3 bg-gray-50 rounded-xl opacity-50">
                    <div class="w-16 h-16 bg-gray-200 rounded-full flex items-center justify-center mx-auto mb-2">
                        <i class="fas fa-plus text-gray-400"></i>
                    </div>
                    <h3 class="font-semibold text-gray-400">Disponible</h3>
                </div>

                <div class="text-center p-3 bg-gray-50 rounded-xl opacity-50">
                    <div class="w-16 h-16 bg-gray-200 rounded-full flex items-center justify-center mx-auto mb-2">
                        <i class="fas fa-plus text-gray-400"></i>
                    </div>
                    <h3 class="font-semibold text-gray-400">Disponible</h3>
                </div>
            </div>
        </div>

        <!-- Contacto y canales -->
        <div class="bg-white rounded-2xl shadow-lg p-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">📞 Contactos</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Email -->
                <div class="flex items-center p-3 bg-blue-50 rounded-xl">
                    <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-envelope text-white"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Email</p>
                        <p class="font-medium text-gray-800">soporte@flow.com</p>
                    </div>
                </div>

                <!-- Teléfono -->
                <div class="flex items-center p-3 bg-green-50 rounded-xl">
                    <div class="w-10 h-10 bg-green-600 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-phone text-white"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Teléfono</p>
                        <p class="font-medium text-gray-800">55 1234 5678</p>
                    </div>
                </div>

                <!-- Slack -->
                <div class="flex items-center p-3 bg-purple-50 rounded-xl">
                    <div class="w-10 h-10 bg-purple-600 rounded-lg flex items-center justify-center mr-3">
                        <i class="fab fa-slack text-white"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Slack</p>
                        <p class="font-medium text-gray-800">#flow-proyecto</p>
                    </div>
                </div>
            </div>

            <!-- Personas de contacto directo -->
            <div class="mt-6 pt-4 border-t">
                <h3 class="font-medium text-gray-700 mb-3">Personas a cargo</h3>
                <div class="flex flex-wrap gap-4">
                    <div class="flex items-center">
                        <span class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center mr-2">
                            <span class="text-sm font-bold text-blue-600">G</span>
                        </span>
                        <span class="text-gray-700">Gustavo (desarrollo)</span>
                    </div>
                    <div class="flex items-center">
                        <span class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center mr-2">
                            <span class="text-sm font-bold text-green-600">J</span>
                        </span>
                        <span class="text-gray-700">Juan (desarrollo)</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Fechas importantes -->
        <div class="mt-6 bg-yellow-50 rounded-2xl p-4 border border-yellow-200">
            <div class="flex items-start">
                <i class="fas fa-calendar-alt text-yellow-600 mt-1 mr-3"></i>
                <div>
                    <p class="font-medium text-yellow-800">📅 Próxima entrega parcial</p>
                    <p class="text-sm text-yellow-700">30 de Abril 2024 - Módulos de productividad y catálogos completos</p>
                    <p class="text-sm text-yellow-700 mt-1">15 de Mayo 2024 - Inicio de pruebas con usuarios</p>
                </div>
            </div>
        </div>

        <!-- Leyenda simple -->
        <div class="mt-4 text-xs text-gray-400 text-center">
            <i class="fas fa-circle text-green-500 mr-1"></i> Completado 
            <span class="mx-2">•</span>
            <i class="fas fa-circle text-blue-500 mr-1"></i> En desarrollo 
            <span class="mx-2">•</span>
            <i class="fas fa-circle text-gray-300 mr-1"></i> Por empezar
        </div>
    </div>

</body>
</html>