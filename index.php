<?php
// --- CONTROLADOR PRINCIPAL (ROUTER) ---
// Este archivo es el único punto de entrada a la aplicación.
// Se encarga de la seguridad, la carga de configuración y de mostrar la vista correcta.

// 0. Iniciar buffer de salida para evitar errores de "Headers already sent"
// al hacer redirecciones desde las vistas.
ob_start();

// 1. Cargar la configuración y las funciones auxiliares.
require_once 'config.php';

// 2. Verificar que el usuario haya iniciado sesión.
// Todas las páginas, excepto el login, requieren autenticación.
check_login();

// 3. Cargar la cabecera de la página (menú de navegación, estilos, etc.).
// El archivo header.php abre las etiquetas <body>, <main> y el <div> principal.
require_once 'partials/header.php';

// 4. Determinar qué página se debe mostrar.
// Obtenemos el valor del parámetro 'page' de la URL. Si no existe, se usa 'rutas' por defecto.
$page_to_load = $_GET['page'] ?? 'rutas';

// 5. Lista blanca de páginas permitidas.
// Esto es una medida de seguridad para evitar que se incluyan archivos no deseados.
$allowed_pages = [
    'rutas',
    'clientes',
    'agregar_cliente',
    'editar_cliente',
    'eliminar_cliente',
    'atrasados',
    'reporte_general',
    'finalizados' // <-- CORRECCIÓN: Se ha añadido la página 'finalizados'
];

// 6. Cargar la vista correspondiente.
if (in_array($page_to_load, $allowed_pages)) {
    // Si la página solicitada está en la lista permitida, se carga.
    $view_file = 'views/' . $page_to_load . '.php';
    if (file_exists($view_file)) {
        require_once $view_file;
    } else {
        // Si el archivo no existe físicamente
        echo "<div class='bg-red-900 border border-red-700 text-red-200 px-4 py-3 rounded-md mb-4' role='alert'>Error: El archivo de la vista '{$page_to_load}.php' no se encontró.</div>";
    }
} else {
    // Si la página no está en la lista blanca (Error 404)
    echo "<div class='text-red-500 font-bold text-center text-lg'>Error 404: La página solicitada no fue encontrada.</div>";
}

// 7. Cargar el pie de página.
// El archivo footer.php cierra las etiquetas abiertas en header.php
require_once 'partials/footer.php';

?>