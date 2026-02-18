<?php
// Incluir configuración (inicia sesión automáticamente si no está iniciada)
require_once 'config.php';

// Si ya está logueado, redirigir al panel principal
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = "Por favor ingrese usuario y contraseña.";
    } else {
        try {
            // CORRECCIÓN: Usamos 'nombre_usuario' en la cláusula WHERE y seleccionamos los campos correctos
            // Buscamos por nombre_usuario, pero traemos nombre_completo para mostrarlo
            $sql = "SELECT id, nombre_usuario, nombre_completo, password, rol, zonas_asignadas FROM usuarios WHERE nombre_usuario = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                // Login Exitoso: Guardamos datos críticos en sesión
                $_SESSION['user_id'] = $user['id'];
                
                // Usamos nombre_completo para mostrar "Hola, Juan", si no existe usamos el usuario
                $_SESSION['user_nombre'] = !empty($user['nombre_completo']) ? $user['nombre_completo'] : $user['nombre_usuario'];
                
                // Guardamos Rol y Zonas para el sistema de permisos
                $_SESSION['rol'] = $user['rol'] ?? 'cobrador'; 
                $_SESSION['zonas_asignadas'] = $user['zonas_asignadas'] ?? '';

                // Regenerar ID de sesión por seguridad
                session_regenerate_id(true);

                // Redirigir al dashboard
                header("Location: index.php");
                exit;
            } else {
                $error = "Usuario o contraseña incorrectos.";
            }
        } catch (PDOException $e) {
            // En producción, es mejor loguear el error y mostrar un mensaje genérico
            error_log("Login Error: " . $e->getMessage());
            // Mostramos el error técnico solo si es necesario depurar (puedes cambiarlo por un mensaje genérico luego)
            $error = "Error de base de datos: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Gestión de Cobros</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #111827; color: #e2e8f0; } /* bg-gray-900 text-gray-200 */
        .login-card { background-color: #1f2937; } /* bg-gray-800 */
        /* Animación suave para el botón */
        .btn-login { transition: all 0.2s ease-in-out; }
        .btn-login:hover { transform: translateY(-1px); }
    </style>
</head>
<body class="h-screen flex items-center justify-center bg-gray-900">
    <div class="login-card p-8 rounded-xl shadow-2xl w-full max-w-sm border border-gray-700">
        <div class="text-center mb-8">
            <div class="bg-blue-600 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4 shadow-lg">
                <i class="fas fa-wallet text-3xl text-white"></i>
            </div>
            <h1 class="text-2xl font-bold text-white tracking-wide">Gestión de Cobros</h1>
            <p class="text-gray-400 text-sm mt-1">Ingrese sus credenciales para acceder</p>
        </div>

        <?php if(!empty($error)): ?>
            <div class="bg-red-900/50 border border-red-500 text-red-200 px-4 py-3 rounded-lg mb-6 text-sm flex items-center shadow-sm">
                <i class="fas fa-exclamation-circle mr-2 text-red-400 text-lg"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php else: ?>
            <?php if(isset($_GET['registered'])): ?>
                <div class="bg-green-900/50 border border-green-500 text-green-200 px-4 py-3 rounded-lg mb-6 text-sm flex items-center shadow-sm">
                    <i class="fas fa-check-circle mr-2 text-green-400 text-lg"></i>
                    <span>¡Cuenta creada! Inicia sesión.</span>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <form method="POST" action="login.php" autocomplete="off">
            <div class="mb-5">
                <label class="block text-gray-300 text-xs font-bold mb-2 uppercase tracking-wider" for="username">Usuario</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500 pointer-events-none">
                        <i class="fas fa-user"></i>
                    </span>
                    <input class="w-full bg-gray-700 text-white border border-gray-600 rounded-lg py-3 pl-10 pr-3 leading-tight focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-colors placeholder-gray-500" 
                           id="username" name="username" type="text" placeholder="Su usuario" required autofocus>
                </div>
            </div>
            
            <div class="mb-8">
                <label class="block text-gray-300 text-xs font-bold mb-2 uppercase tracking-wider" for="password">Contraseña</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500 pointer-events-none">
                        <i class="fas fa-lock"></i>
                    </span>
                    <input class="w-full bg-gray-700 text-white border border-gray-600 rounded-lg py-3 pl-10 pr-3 leading-tight focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-colors placeholder-gray-500" 
                           id="password" name="password" type="password" placeholder="Su contraseña" required>
                </div>
            </div>
            
            <div class="flex items-center justify-between">
                <button class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg focus:outline-none focus:shadow-outline shadow-lg btn-login flex items-center justify-center gap-2" type="submit">
                    <span>Ingresar al Sistema</span>
                    <i class="fas fa-arrow-right text-sm"></i>
                </button>
            </div>
        </form>
        
        <div class="mt-6 text-center text-xs text-gray-500">
            &copy; <?= date('Y') ?> Sistema de Gestión v1.0
        </div>
    </div>
</body>
</html>