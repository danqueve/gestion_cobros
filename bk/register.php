<?php
// Incluimos la configuración para acceder a la base de datos y sesión
require_once 'config.php';

// Si el usuario ya está logueado, redirigir al dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';
$success = '';

// Procesar el formulario cuando se envía.
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Recoger los datos del formulario.
    // NOTA: El sistema actual usa 'nombre_usuario' como identificador en la BD.
    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // --- Validaciones ---
    if (empty($usuario) || empty($password)) {
        $error = "Todos los campos son obligatorios.";
    } elseif ($password !== $confirm_password) {
        $error = "Las contraseñas no coinciden.";
    } elseif (strlen($password) < 6) {
        $error = "La contraseña debe tener al menos 6 caracteres.";
    } else {
        try {
            // Verificar si el nombre de usuario ya existe.
            // CORRECCIÓN: Usamos 'nombre_usuario' que es la columna real en tu BD
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE nombre_usuario = ?");
            $stmt->execute([$usuario]);
            
            if ($stmt->fetch()) {
                $error = "El nombre de usuario ya está en uso. Por favor, elige otro.";
            } else {
                // Hashear la contraseña.
                $password_hashed = password_hash($password, PASSWORD_DEFAULT);

                // --- NUEVO PROTOCOLO ---
                // Por defecto, el usuario se crea como 'cobrador' y SIN zonas asignadas.
                $rol_default = 'cobrador';
                $zonas_default = ''; 

                // CORRECCIÓN: Ajustamos la consulta a las columnas existentes en la BD
                // 1. Usamos 'nombre_usuario' en lugar de 'nombre'
                // 2. Agregamos 'nombre_completo' (usando el mismo usuario) para mantener compatibilidad
                $sql = "INSERT INTO usuarios (nombre_usuario, nombre_completo, password, rol, zonas_asignadas) VALUES (?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                
                if ($stmt->execute([$usuario, $usuario, $password_hashed, $rol_default, $zonas_default])) {
                    // Redirigir al login con mensaje de éxito
                    header("Location: login.php?registered=1");
                    exit;
                } else {
                    $error = "Hubo un error al crear la cuenta. Inténtalo de nuevo.";
                }
            }
        } catch (PDOException $e) {
            $error = "Error de base de datos: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro - Gestión de Cobros</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>body { background-color: #111827; }</style>
</head>
<body class="flex items-center justify-center min-h-screen p-4">
    <div class="w-full max-w-md p-8 space-y-6 bg-gray-800 rounded-lg shadow-lg border border-gray-700">
        <div class="text-center">
            <div class="bg-blue-600 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4 shadow-lg">
                <i class="fas fa-user-plus text-3xl text-white"></i>
            </div>
            <h2 class="mt-2 text-2xl font-bold text-white">Crear una Cuenta</h2>
            <p class="text-gray-400 text-sm mt-1">Regístrate para acceder al sistema</p>
        </div>
        
        <?php if(!empty($error)): ?>
            <div class="bg-red-900/50 border border-red-500 text-red-200 px-4 py-3 rounded-lg text-sm flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form class="space-y-4" action="register.php" method="POST" autocomplete="off">
            <!-- Usuario -->
            <div>
                <label for="usuario" class="block text-gray-300 text-xs font-bold mb-2 uppercase tracking-wide">Usuario</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500"><i class="fas fa-user"></i></span>
                    <input id="usuario" name="usuario" type="text" required 
                           class="w-full bg-gray-700 text-white border border-gray-600 rounded-lg py-2 pl-10 pr-3 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 placeholder-gray-500 transition-colors" 
                           placeholder="Nombre de usuario" value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>">
                </div>
            </div>

            <!-- Contraseña -->
            <div>
                <label for="password" class="block text-gray-300 text-xs font-bold mb-2 uppercase tracking-wide">Contraseña</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500"><i class="fas fa-lock"></i></span>
                    <input id="password" name="password" type="password" required 
                           class="w-full bg-gray-700 text-white border border-gray-600 rounded-lg py-2 pl-10 pr-3 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 placeholder-gray-500 transition-colors" 
                           placeholder="Mínimo 6 caracteres">
                </div>
            </div>

            <!-- Confirmar Contraseña -->
            <div>
                <label for="confirm_password" class="block text-gray-300 text-xs font-bold mb-2 uppercase tracking-wide">Confirmar Contraseña</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500"><i class="fas fa-check-circle"></i></span>
                    <input id="confirm_password" name="confirm_password" type="password" required 
                           class="w-full bg-gray-700 text-white border border-gray-600 rounded-lg py-2 pl-10 pr-3 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 placeholder-gray-500 transition-colors" 
                           placeholder="Repite tu contraseña">
                </div>
            </div>

            <div class="pt-2">
                <button type="submit" class="w-full py-3 px-4 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-lg transition duration-300 transform hover:scale-[1.02] shadow-lg">
                    Registrarse
                </button>
            </div>
        </form>
        
        <div class="text-center border-t border-gray-700 pt-4">
            <p class="text-sm text-gray-400">
                ¿Ya tienes cuenta? 
                <a href="login.php" class="font-medium text-blue-400 hover:text-blue-300 transition-colors">Inicia sesión</a>
            </p>
        </div>
    </div>
</body>
</html>