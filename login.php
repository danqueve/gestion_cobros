<?php
// Incluye el archivo de configuración. Es lo primero que se debe hacer
// para tener acceso a la sesión y a la conexión con la base de datos.
require_once 'config.php';

// Variable para almacenar mensajes de error.
$error = '';

// Si el usuario ya ha iniciado sesión (es decir, ya existe la variable de sesión),
// no tiene sentido mostrarle el login de nuevo. Lo redirigimos a la página principal.
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// El código dentro de este 'if' solo se ejecuta cuando el usuario envía el formulario (método POST).
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validar que los campos no estén vacíos.
    if (empty($_POST['username']) || empty($_POST['password'])) {
        $error = "Por favor, complete ambos campos.";
    } else {
        $username = $_POST['username'];
        $password = $_POST['password'];

        try {
            // Preparamos una consulta SQL segura para buscar al usuario por su nombre de usuario.
            // Usar consultas preparadas previene inyecciones SQL.
            $stmt = $pdo->prepare("SELECT id, nombre_usuario, password FROM usuarios WHERE nombre_usuario = ?");
            $stmt->execute([$username]);
            
            // Obtenemos el resultado como un array asociativo.
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Verificamos si se encontró un usuario y si la contraseña enviada coincide
            // con el hash almacenado en la base de datos.
            if ($user && password_verify($password, $user['password'])) {
                // Si la validación es exitosa:
                // 1. Guardamos el ID y el nombre de usuario en la sesión.
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['nombre_usuario'];
                
                // 2. Redirigimos al usuario a la página principal del sistema.
                header("Location: index.php");
                exit;
            } else {
                // Si no se encontró el usuario o la contraseña es incorrecta.
                $error = "Nombre de usuario o contraseña incorrectos.";
            }
        } catch (PDOException $e) {
            // Si ocurre un error con la base de datos.
            $error = "Error al consultar la base de datos: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login - Sistema de Cobros</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #111827; }
    </style>
</head>
<body class="flex items-center justify-center h-screen">
    <div class="w-full max-w-md p-8 space-y-8 bg-gray-800 rounded-lg shadow-lg border border-gray-700">
        <div class="text-center">
            <i class="fas fa-cash-register text-blue-500 text-4xl"></i>
            <h2 class="mt-4 text-2xl font-bold text-white">Sistema de Cobros</h2>
        </div>
        
        <?php if(!empty($error)): ?>
            <div class="bg-red-900 border border-red-700 text-red-200 px-4 py-3 rounded-md" role="alert">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form class="mt-8 space-y-6" action="login.php" method="POST">
            <input type="hidden" name="remember" value="true">
            <div class="rounded-md shadow-sm -space-y-px">
                <div>
                    <label for="username" class="sr-only">Usuario</label>
                    <input id="username" name="username" type="text" required class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-md text-white placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500" placeholder="Usuario (admin)">
                </div>
                <div class="pt-4">
                    <label for="password" class="sr-only">Contraseña</label>
                    <input id="password" name="password" type="password" required class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-md text-white placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500" placeholder="Contraseña (admin)">
                </div>
            </div>

            <div>
                <button type="submit" class="w-full py-2 px-4 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-md transition duration-300">
                    Entrar
                </button>
            </div>
        </form>
    </div>
</body>
</html>
