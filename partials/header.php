<?php
// Asegurar que la sesión está iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Obtener la página actual para resaltar el enlace activo
$current_page = $_GET['page'] ?? 'home';

// Obtener el rol del usuario (por defecto 'cobrador' si no está definido)
$rol_usuario = $_SESSION['rol'] ?? 'cobrador';
$nombre_usuario = $_SESSION['user_nombre'] ?? 'Usuario';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Cobros</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <!-- Estilos Globales Personalizados -->
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #111827; color: #f3f4f6; } /* bg-gray-900 text-gray-100 */
        .form-element-dark { background-color: #374151; color: white; border-color: #4b5563; }
        .form-element-dark:focus { border-color: #3b82f6; ring: 2px; ring-color: #3b82f6; }
        
        /* Scrollbar personalizada para Webkit */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #1f2937; }
        ::-webkit-scrollbar-thumb { background: #4b5563; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #6b7280; }
        
        /* Estilos de Impresión */
        @media print {
            .no-print { display: none !important; }
            .print-area { display: block !important; }
            body { background-color: white; color: black; }
            .print-container { border: none; shadow: none; }
            .print-title { color: black !important; }
        }
    </style>
</head>
<body class="flex flex-col min-h-screen">

    <!-- Navbar -->
    <nav class="bg-gray-900 border-b border-gray-800 sticky top-0 z-50 shadow-lg no-print">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                
                <!-- Logo -->
                <div class="flex items-center">
                    <a href="index.php" class="flex-shrink-0 flex items-center gap-2 group">
                        <div class="bg-blue-600 text-white p-2 rounded-lg group-hover:bg-blue-500 transition-colors shadow-md">
                            <i class="fas fa-wallet fa-lg"></i>
                        </div>
                        <span class="font-bold text-xl tracking-tight text-gray-100 group-hover:text-blue-400 transition-colors hidden sm:block">
                            CobrosApp
                        </span>
                    </a>
                </div>
                
                <!-- Menú de Escritorio -->
                <div class="hidden md:block">
                    <div class="ml-10 flex items-baseline space-x-4">
                        <!-- Enlaces Comunes -->
                        <a href="index.php?page=rutas" class="<?= $current_page == 'rutas' ? 'bg-gray-800 text-white shadow-sm' : 'text-gray-300 hover:bg-gray-700 hover:text-white' ?> px-3 py-2 rounded-md text-sm font-medium transition-all duration-200 transform hover:scale-105">
                            <i class="fas fa-route mr-1"></i> Rutas
                        </a>
                        <a href="index.php?page=clientes" class="<?= $current_page == 'clientes' ? 'bg-gray-800 text-white shadow-sm' : 'text-gray-300 hover:bg-gray-700 hover:text-white' ?> px-3 py-2 rounded-md text-sm font-medium transition-all duration-200 transform hover:scale-105">
                            <i class="fas fa-users mr-1"></i> Clientes
                        </a>
                        <a href="index.php?page=atrasados" class="<?= $current_page == 'atrasados' ? 'bg-gray-800 text-white shadow-sm' : 'text-gray-300 hover:bg-gray-700 hover:text-white' ?> px-3 py-2 rounded-md text-sm font-medium transition-all duration-200 transform hover:scale-105">
                            <i class="fas fa-clock mr-1"></i> Atrasados
                        </a>
                        
                        <!-- ENLACE RESTRINGIDO: Solo visible para Admins -->
                        <?php if($rol_usuario === 'admin'): ?>
                        <div class="border-l border-gray-700 pl-4 ml-2">
                            <a href="index.php?page=usuarios" class="<?= $current_page == 'usuarios' ? 'bg-blue-900/50 text-blue-100 border border-blue-500/30' : 'text-blue-300 hover:bg-gray-700 hover:text-white' ?> px-3 py-2 rounded-md text-sm font-medium transition-all duration-200 transform hover:scale-105 flex items-center">
                                <i class="fas fa-users-cog mr-2"></i> Usuarios
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Menú de Usuario (Derecha) -->
                <div class="hidden md:block">
                    <div class="ml-4 flex items-center md:ml-6 gap-4">
                        <div class="text-right">
                            <div class="text-gray-200 text-sm font-semibold">Hola, <?= htmlspecialchars($nombre_usuario) ?></div>
                            <div class="text-xs text-gray-500 uppercase tracking-wide"><?= htmlspecialchars($rol_usuario) ?></div>
                        </div>
                        <button onclick="confirmarLogout()" class="bg-red-600 hover:bg-red-700 text-white p-2 rounded-md text-sm font-medium transition-colors shadow-sm" title="Cerrar Sesión">
                            <i class="fas fa-sign-out-alt fa-lg"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Botón Menú Móvil -->
                <div class="-mr-2 flex md:hidden">
                    <button type="button" onclick="toggleMobileMenu()" class="bg-gray-800 inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-800 focus:ring-white">
                        <span class="sr-only">Abrir menú</span>
                        <i class="fas fa-bars fa-lg"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Menú Móvil (Desplegable) -->
        <div class="hidden md:hidden bg-gray-800 border-t border-gray-700" id="mobile-menu">
            <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3">
                <a href="index.php?page=rutas" class="text-gray-300 hover:bg-gray-700 hover:text-white block px-3 py-2 rounded-md text-base font-medium">
                    <i class="fas fa-route mr-2 w-5 text-center"></i> Rutas
                </a>
                <a href="index.php?page=clientes" class="text-gray-300 hover:bg-gray-700 hover:text-white block px-3 py-2 rounded-md text-base font-medium">
                    <i class="fas fa-users mr-2 w-5 text-center"></i> Clientes
                </a>
                <a href="index.php?page=atrasados" class="text-gray-300 hover:bg-gray-700 hover:text-white block px-3 py-2 rounded-md text-base font-medium">
                    <i class="fas fa-clock mr-2 w-5 text-center"></i> Atrasados
                </a>
                
                <!-- ENLACE RESTRINGIDO MÓVIL -->
                <?php if($rol_usuario === 'admin'): ?>
                <div class="border-t border-gray-700 pt-2 mt-2">
                    <a href="index.php?page=usuarios" class="text-blue-300 hover:bg-gray-700 hover:text-white block px-3 py-2 rounded-md text-base font-medium">
                        <i class="fas fa-users-cog mr-2 w-5 text-center"></i> Usuarios y Permisos
                    </a>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Perfil Usuario Móvil -->
            <div class="pt-4 pb-4 border-t border-gray-700">
                <div class="flex items-center px-5">
                    <div class="flex-shrink-0">
                        <div class="h-10 w-10 rounded-full bg-gray-600 flex items-center justify-center text-white font-bold text-lg">
                            <?= strtoupper(substr($nombre_usuario, 0, 1)) ?>
                        </div>
                    </div>
                    <div class="ml-3">
                        <div class="text-base font-medium leading-none text-white"><?= htmlspecialchars($nombre_usuario) ?></div>
                        <div class="text-sm font-medium leading-none text-gray-400 mt-1"><?= htmlspecialchars($rol_usuario) ?></div>
                    </div>
                    <button onclick="confirmarLogout()" class="ml-auto bg-red-600 flex-shrink-0 p-2 rounded-full text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-800 focus:ring-white">
                        <i class="fas fa-sign-out-alt"></i>
                    </button>
                </div>
            </div>
        </div>
    </nav>
    
    <!-- SweetAlert2 (asegurar que esté disponible globalmente para el logout) -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function toggleMobileMenu() {
            const menu = document.getElementById('mobile-menu');
            menu.classList.toggle('hidden');
        }

        function confirmarLogout() {
            Swal.fire({
                title: '¿Cerrar Sesión?',
                text: "¿Estás seguro que deseas salir del sistema?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, salir',
                cancelButtonText: 'Cancelar',
                background: '#1f2937',
                color: '#fff'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'logout.php';
                }
            });
        }
    </script>

    <!-- Contenedor Principal -->
    <main class="flex-grow container mx-auto px-4 sm:px-6 lg:px-8 py-6">
