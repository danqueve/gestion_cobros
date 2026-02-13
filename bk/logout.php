<?php
// Inicia o reanuda la sesión existente.
// Es necesario para poder acceder a la información de la sesión y destruirla.
session_start();

// Elimina todas las variables de la sesión.
// Esto asegura que datos como 'user_id' y 'username' sean borrados.
session_unset();

// Destruye toda la información registrada en una sesión.
// Esto finaliza la sesión del lado del servidor.
session_destroy();

// Redirige al usuario a la página de login.
// El usuario deberá volver a introducir sus credenciales para acceder al sistema.
header("Location: login.php");

// Detiene la ejecución del script para asegurar que la redirección se complete
// y no se ejecute ningún código adicional.
exit;
?>
