<?php
// Inicia la sesión en cada página que incluya este archivo.
// Es fundamental para el sistema de login.
session_start();

// --- CONFIGURACIÓN DE LA BASE DE DATOS ---
// Define constantes para los detalles de la conexión.
// Estos son los nuevos datos que proporcionaste.
define('DB_HOST', 'localhost');
define('DB_USER', 'c2881399_cobros'); // Usuario de tu base de datos
define('DB_PASS', 'vanoTOga46');     // Contraseña de tu base de datos
define('DB_NAME', 'c2881399_cobros'); // El nombre de la base de datos que creaste

// --- CONEXIÓN A LA BASE DE DATOS (PDO) ---
try {
  // Intenta crear una nueva instancia de PDO para la conexión.
  $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
  
  // Configura PDO para que lance excepciones en caso de error.
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  
  // Asegura que la comunicación con la base de datos se realice en UTF-8.
  $pdo->exec("SET CHARACTER SET utf8");

} catch (PDOException $e) {
  // Si la conexión falla, se muestra un mensaje de error claro y se detiene la ejecución.
  die("ERROR: No se pudo conectar a la base de datos. " . $e->getMessage());
}

// --- FUNCIONES AUXILIARES GLOBALES ---

/**
* Verifica si el usuario ha iniciado sesión.
* Si no hay una sesión activa, lo redirige a la página de login.
*/
function check_login() {
  if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
  }
}

/**
* Formatea un número como moneda (sin decimales).
* @param float $value El número a formatear.
* @return string La cadena de texto formateada como moneda.
*/
function formatCurrency($value) {
    // number_format($value, 0, ',', '.') formatea 1000.50 como "1.001"
    return '$' . number_format($value, 0, ',', '.');
}


/**
 * CORREGIDO: Calcula los días de atraso de un crédito basándose en su último pago y frecuencia.
 * @param string|null $fecha_ultimo_pago_str La fecha del último pago.
 * @param string $estado El estado actual del crédito ('Activo' o 'Pagado').
 * @param string $frecuencia La frecuencia del crédito ('Semanal', 'Quincenal', 'Mensual').
 * @return array Un array con los días de atraso y el estado.
 */
function calcularAtraso($fecha_ultimo_pago_str, $estado, $frecuencia = 'Semanal') {
    if ($estado == 'Pagado') {
        return ['dias' => 0, 'estado' => 'Pagado'];
    }
    if (empty($fecha_ultimo_pago_str)) {
        return ['dias' => 999, 'estado' => 'Atrasado']; // Nunca ha pagado
    }

    $hoy = new DateTime();
    $ultimo_pago = new DateTime($fecha_ultimo_pago_str);

    // Calcular la próxima fecha de vencimiento para saber si está atrasado
    $proximo_vencimiento = clone $ultimo_pago;
    switch ($frecuencia) {
        case 'Quincenal':
            $proximo_vencimiento->modify('+15 days');
            break;
        case 'Mensual':
            $proximo_vencimiento->modify('+1 month');
            break;
        case 'Semanal':
        default:
            $proximo_vencimiento->modify('+7 days');
            break;
    }

    // Si la fecha de hoy es mayor que el vencimiento, entonces está atrasado
    if ($hoy > $proximo_vencimiento) {
        // Se calcula el total de días transcurridos desde el último pago.
        $diferencia = $hoy->diff($ultimo_pago);
        return ['dias' => $diferencia->days, 'estado' => 'Atrasado'];
    } else {
        // Si no, está al día
        return ['dias' => 0, 'estado' => 'Al día'];
    }
}

/**
 * NUEVO: Genera el HTML para una barra de paginación inteligente.
 * @param int $pagina_actual - La página que se está viendo.
 * @param int $total_paginas - El número total de páginas.
 * @param int $vecinos - Cuántos números mostrar a cada lado de la página actual.
 * @return string - El HTML de la paginación.
 */
function generarPaginacion($pagina_actual, $total_paginas, $vecinos = 2) {
    if ($total_paginas <= 1) {
        return ''; // No mostrar paginación si solo hay 1 página
    }

    $html = '<a href="#" data-page="' . ($pagina_actual - 1) . '" class="page-link ' . ($pagina_actual <= 1 ? 'pointer-events-none text-gray-600' : 'text-blue-400 hover:text-blue-300') . '"><i class="fas fa-chevron-left"></i> Anterior</a>';
    $html .= '<div class="flex gap-2">';

    // Definir el rango de páginas a mostrar
    $inicio = max(1, $pagina_actual - $vecinos);
    $fin = min($total_paginas, $pagina_actual + $vecinos);

    // Botón de primera página y "..." si es necesario
    if ($inicio > 1) {
        $html .= '<a href="#" data-page="1" class="page-link px-3 py-1 rounded-md bg-gray-700 hover:bg-gray-600">1</a>';
        if ($inicio > 2) {
            $html .= '<span class="px-3 py-1 text-gray-500">...</span>';
        }
    }

    // Bucle principal de números
    for ($i = $inicio; $i <= $fin; $i++) {
        $clase_activa = ($i == $pagina_actual) ? 'bg-blue-600 text-white' : 'bg-gray-700 hover:bg-gray-600';
        $html .= "<a href='#' data-page='$i' class='page-link px-3 py-1 rounded-md $clase_activa'>$i</a>";
    }

    // Botón de última página y "..." si es necesario
    if ($fin < $total_paginas) {
        if ($fin < $total_paginas - 1) {
            $html .= '<span class="px-3 py-1 text-gray-500">...</span>';
        }
        $html .= "<a href='#' data-page='$total_paginas' class='page-link px-3 py-1 rounded-md bg-gray-700 hover:bg-gray-600'>$total_paginas</a>";
    }

    $html .= '</div>';
    $html .= '<a href="#" data-page="' . ($pagina_actual + 1) . '" class="page-link ' . ($pagina_actual >= $total_paginas ? 'pointer-events-none text-gray-600' : 'text-blue-400 hover:text-blue-300') . '">Siguiente <i class="fas fa-chevron-right"></i></a>';

    return $html;
}

?>