<?php
// Incluimos la configuración para acceder a la base de datos.
require_once 'config.php';

$error = '';
$success = '';

// Procesar el formulario cuando se envía.
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Recoger los datos del formulario.
    $nombre = $_POST['nombre'] ?? '';
    $apellido = $_POST['apellido'] ?? '';
    $usuario = $_POST['usuario'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    $nombre_completo = trim($nombre . ' ' . $apellido);

    // --- Validaciones ---
    if (empty($nombre) || empty($apellido) || empty($usuario) || empty($password)) {
        $error = "Todos los campos son obligatorios.";
    } elseif ($password !== $confirm_password) {
        $error = "Las contraseñas no coinciden.";
    } elseif (strlen($password) < 6) {
        $error = "La contraseña debe tener al menos 6 caracteres.";
    } else {
        try {
            // Verificar si el nombre de usuario ya existe.
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE nombre_usuario = ?");
            $stmt->execute([$usuario]);
            if ($stmt->fetch()) {
                $error = "El nombre de usuario ya está en uso. Por favor, elige otro.";
            } else {
                // Si el usuario no existe, procedemos a crearlo.
                
                // Hashear la contraseña por seguridad. ¡Nunca guardes contraseñas en texto plano!
                $password_hashed = password_hash($password, PASSWORD_DEFAULT);

                // Preparar la consulta SQL para insertar el nuevo usuario.
                $sql = "INSERT INTO usuarios (nombre_completo, nombre_usuario, password) VALUES (?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                
                // Ejecutar la consulta.
                if ($stmt->execute([$nombre_completo, $usuario, $password_hashed])) {
                    // Si el registro es exitoso, redirigir al login con un mensaje.
                    header("Location: login.php?status=success");
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
    <title>Registro de Usuario - Sistema de Cobros</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>body { background-color: #111827; }</style>
</head>
<body class="flex items-center justify-center min-h-screen">
    <div class="w-full max-w-md p-8 space-y-6 bg-gray-800 rounded-lg shadow-lg border border-gray-700">
        <div class="text-center">
            <i class="fas fa-user-plus text-blue-500 text-4xl"></i>
            <h2 class="mt-4 text-2xl font-bold text-white">Crear una Cuenta</h2>
        </div>
        
        <?php if(!empty($error)): ?>
            <div class="bg-red-900 border border-red-700 text-red-200 px-4 py-3 rounded-md" role="alert">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form class="space-y-4" action="register.php" method="POST">
            <div class="flex gap-4">
                <div class="w-1/2">
                    <label for="nombre" class="sr-only">Nombre</label>
                    <input id="nombre" name="nombre" type="text" required class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-md text-white placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500" placeholder="Nombre">
                </div>
                <div class="w-1/2">
                    <label for="apellido" class="sr-only">Apellido</label>
                    <input id="apellido" name="apellido" type="text" required class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-md text-white placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500" placeholder="Apellido">
                </div>
            </div>
            <div>
                <label for="usuario" class="sr-only">Usuario</label>
                <input id="usuario" name="usuario" type="text" required class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-md text-white placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500" placeholder="Nombre de usuario">
            </div>
            <div>
                <label for="password" class="sr-only">Contraseña</label>
                <input id="password" name="password" type="password" required class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-md text-white placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500" placeholder="Contraseña (mín. 6 caracteres)">
            </div>
            <div>
                <label for="confirm_password" class="sr-only">Confirmar Contraseña</label>
                <input id="confirm_password" name="confirm_password" type="password" required class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-md text-white placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500" placeholder="Confirmar contraseña">
            </div>
            <div>
                <button type="submit" class="w-full py-2 px-4 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-md transition duration-300">
                    Registrarse
                </button>
            </div>
        </form>
        <p class="text-center text-sm text-gray-400">
            ¿Ya tienes una cuenta? 
            <a href="login.php" class="font-medium text-blue-400 hover:text-blue-300">Inicia sesión aquí</a>
        </p>
    </div>
</body>
</html>
