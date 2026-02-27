<?php
// Inicia la sesión si no está iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- CONFIGURACIÓN REGIONAL ---
date_default_timezone_set('America/Argentina/Buenos_Aires');

// --- CONSTANTES DE BASE DE DATOS ---
define('DB_HOST', 'localhost');
define('DB_USER', 'c2881399_cobros');
define('DB_PASS', 'vanoTOga46');
define('DB_NAME', 'c2881399_cobros');

// --- DATOS GLOBALES DEL SISTEMA ---
$NOMBRES_ZONAS = [
    1 => 'Santi',
    2 => 'Juan Pablo',
    3 => 'Enzo',
    4 => 'Tafi del V',
    5 => 'Famailla',
    6 => 'Sgo'
];

$DIAS_SEMANA = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
$FRECUENCIAS_PAGO = ['Semanal', 'Quincenal', 'Mensual'];

// --- CONEXIÓN A LA BASE DE DATOS (PDO) ---
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET CHARACTER SET utf8");
} catch (PDOException $e) {
    error_log("Error de conexión a BD: " . $e->getMessage()); 
    die("ERROR CRÍTICO: No se pudo conectar a la base de datos. Por favor, intente más tarde.");
}

// =============================================================================
// SECCIÓN 1: SEGURIDAD (MODO COMPATIBILIDAD - SIN ROLES)
// =============================================================================

function check_login() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }
}

/**
 * Funciones de permisos "Dummy" para compatibilidad.
 * Al no haber roles, siempre devuelven TRUE (Acceso total).
 */
function esAdmin() {
    return true; 
}

function tienePermisoZona($zona_id) {
    return true; 
}

function getFiltroZonasSQL($columna_zona = 'zona') {
    return ""; // Sin filtro SQL adicional
}

// =============================================================================
// SECCIÓN 2: LÓGICA DE NEGOCIO (COBROS)
// =============================================================================

function registrarPago($pdo, $credito_id, $monto, $user_id, $fecha_pago) {
    if ($monto <= 0) {
        throw new Exception("El monto debe ser mayor a 0.");
    }

    try {
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
        }

        // 1. Registrar pago
        $stmt = $pdo->prepare("INSERT INTO pagos (credito_id, usuario_id, monto_pagado, fecha_pago) VALUES (?, ?, ?, ?)");
        $stmt->execute([$credito_id, $user_id, $monto, $fecha_pago]);

        // 2. Obtener cuotas pendientes
        $stmt_cuotas = $pdo->prepare("SELECT * FROM cronograma_cuotas WHERE credito_id = ? AND estado != 'Pagado' ORDER BY numero_cuota ASC");
        $stmt_cuotas->execute([$credito_id]);
        $cuotas = $stmt_cuotas->fetchAll(PDO::FETCH_ASSOC);

        $monto_restante = $monto;

        // 3. Imputar pago
        foreach ($cuotas as $cuota) {
            if ($monto_restante <= 0) break;

            $saldo_cuota = $cuota['monto_cuota'] - $cuota['monto_pagado'];
            $imputar = min($monto_restante, $saldo_cuota);
            $nuevo_pagado = $cuota['monto_pagado'] + $imputar;
            $nuevo_estado = ($nuevo_pagado >= $cuota['monto_cuota'] - 0.01) ? 'Pagado' : 'Pago Parcial';

            $update = $pdo->prepare("UPDATE cronograma_cuotas SET monto_pagado = ?, estado = ? WHERE id = ?");
            $update->execute([$nuevo_pagado, $nuevo_estado, $cuota['id']]);

            $monto_restante -= $imputar;
        }
        
        // 4. Actualizar crédito
        $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM cronograma_cuotas WHERE credito_id = ? AND estado = 'Pagado'");
        $stmt_count->execute([$credito_id]);
        $cuotas_pagadas_count = $stmt_count->fetchColumn();

        $stmt_info = $pdo->prepare("SELECT total_cuotas FROM creditos WHERE id = ?");
        $stmt_info->execute([$credito_id]);
        $info_credito = $stmt_info->fetch(PDO::FETCH_ASSOC);

        $nuevo_estado_credito = ($cuotas_pagadas_count >= $info_credito['total_cuotas']) ? 'Pagado' : 'Activo';

        $update_credito = $pdo->prepare("UPDATE creditos SET cuotas_pagadas = ?, estado = ?, ultimo_pago = ? WHERE id = ?");
        $update_credito->execute([$cuotas_pagadas_count, $nuevo_estado_credito, $fecha_pago, $credito_id]);

        $pdo->commit();
        return true;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function formatCurrency($value) {
    return '$' . number_format($value, 0, ',', '.');
}

function generarPaginacion($pagina_actual, $total_paginas, $vecinos = 2) {
    if ($total_paginas <= 1) return '';
    
    $html = '<nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">';
    
    // Anterior
    $prev_disabled = ($pagina_actual <= 1) ? 'pointer-events-none opacity-50' : '';
    $html .= '<a href="#" data-page="' . ($pagina_actual - 1) . '" class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-600 hover:bg-gray-700 ' . $prev_disabled . '"><span class="sr-only">Anterior</span><i class="fas fa-chevron-left h-5 w-5"></i></a>';

    $inicio = max(1, $pagina_actual - $vecinos);
    $fin = min($total_paginas, $pagina_actual + $vecinos);

    // Primera página si está lejos
    if ($inicio > 1) {
        $html .= '<a href="#" data-page="1" class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-300 ring-1 ring-inset ring-gray-600 hover:bg-gray-700">1</a>';
        if ($inicio > 2) $html .= '<span class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-500 ring-1 ring-inset ring-gray-600">...</span>';
    }

    // Números centrales
    for ($i = $inicio; $i <= $fin; $i++) {
        $active_class = ($i == $pagina_actual) ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-700 ring-1 ring-inset ring-gray-600';
        $html .= '<a href="#" data-page="' . $i . '" class="relative inline-flex items-center px-4 py-2 text-sm font-semibold ' . $active_class . '">' . $i . '</a>';
    }

    // Última página si está lejos
    if ($fin < $total_paginas) {
        if ($fin < $total_paginas - 1) $html .= '<span class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-500 ring-1 ring-inset ring-gray-600">...</span>';
        $html .= '<a href="#" data-page="' . $total_paginas . '" class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-300 ring-1 ring-inset ring-gray-600 hover:bg-gray-700">' . $total_paginas . '</a>';
    }

    // Siguiente
    $next_disabled = ($pagina_actual >= $total_paginas) ? 'pointer-events-none opacity-50' : '';
    $html .= '<a href="#" data-page="' . ($pagina_actual + 1) . '" class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-600 hover:bg-gray-700 ' . $next_disabled . '"><span class="sr-only">Siguiente</span><i class="fas fa-chevron-right h-5 w-5"></i></a>';
    
    $html .= '</nav>';
    return $html;
}

// Función renderCreditoRow (usada en clientes.php)
function renderCreditoRow($credito, $NOMBRES_ZONAS) {
    $dia_cobro = ($credito['frecuencia'] == 'Semanal') ? $credito['dia_pago'] : 'Día ' . $credito['dia_vencimiento'];
    $estado_class = $credito['estado'] == 'Pagado' ? 'bg-green-900 text-green-200' : 'bg-blue-900 text-blue-200';
    $saldo_pendiente = isset($credito['saldo_pendiente']) ? $credito['saldo_pendiente'] : 0;
    $monto_cuota = '$' . number_format($credito['monto_cuota'], 0, ',', '.');
    
    $html = '<tr class="hover:bg-gray-800 transition-colors">';
    $html .= '<td class="px-4 py-4 whitespace-nowrap font-medium text-white">';
    $html .= '<a href="index.php?page=editar_cliente&id=' . $credito['credito_id'] . '" class="hover:text-blue-400 hover:underline">' . htmlspecialchars($credito['nombre']) . '</a>';
    $html .= '</td>';
    $html .= '<td class="px-4 py-4 whitespace-nowrap text-center text-gray-300 text-sm">' . htmlspecialchars($NOMBRES_ZONAS[$credito['zona']] ?? 'N/A') . '</td>';
    $html .= '<td class="px-4 py-4 whitespace-nowrap text-center text-gray-300 text-sm">' . htmlspecialchars($credito['frecuencia']) . '</td>';
    $html .= '<td class="px-4 py-4 whitespace-nowrap text-center text-gray-300 text-sm">' . htmlspecialchars($dia_cobro ?? '-') . '</td>';
    $html .= '<td class="px-4 py-4 whitespace-nowrap text-center">';
    $html .= '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ' . $estado_class . '">' . htmlspecialchars($credito['estado']) . '</span>';
    $html .= '</td>';
    $html .= '<td class="px-4 py-4 whitespace-nowrap text-right text-gray-300 text-sm">' . $monto_cuota . '</td>';
    $html .= '<td class="px-4 py-4 whitespace-nowrap text-center text-gray-300 text-sm">' . $credito['cuotas_pagadas'] . ' / ' . $credito['total_cuotas'] . '</td>';
    $html .= '<td class="px-4 py-4 whitespace-nowrap text-center no-print flex justify-center gap-3">';
    
    if ($credito['estado'] != 'Pagado') {
        $nombre_safe = htmlspecialchars($credito['nombre'], ENT_QUOTES);
        $html .= '<button onclick="abrirModalPago(' . $credito['credito_id'] . ', \'' . $nombre_safe . '\', ' . ($saldo_pendiente ?: 0) . ')" class="text-green-500 hover:text-green-400 transition-colors" title="Pago Rápido">';
        $html .= '<i class="fas fa-money-bill-wave fa-lg"></i>';
        $html .= '</button>';
    }
    
    $html .= '<a href="index.php?page=editar_cliente&id=' . $credito['credito_id'] . '" class="text-blue-400 hover:text-blue-300 transition-colors" title="Editar">';
    $html .= '<i class="fas fa-edit fa-lg"></i>';
    $html .= '</a>';
    
    // Todos pueden eliminar (Modo sin roles)
    $html .= '<a href="index.php?page=eliminar_cliente&id=' . $credito['cliente_id'] . '" class="text-red-500 hover:text-red-400 transition-colors" title="Eliminar" onclick="return confirm(\'ATENCIÓN: Se eliminará al cliente y TODO su historial de pagos. ¿Continuar?\')">';
    $html .= '<i class="fas fa-trash-alt fa-lg"></i>';
    $html .= '</a>';
    
    $html .= '</td>';
    $html .= '</tr>';
    
    return $html;
}
?>