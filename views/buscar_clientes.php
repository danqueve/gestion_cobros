<?php
// --- ENDPOINT PARA BÚSQUEDA AJAX DE CLIENTES ---
// Este archivo NO DEBE tener salidas HTML (headers, espacios en blanco, etc.)
// porque index.php no se carga aquí, evitando que se inyecte el menú principal.

require_once '../config.php'; // Incluye la conexión y renderCreditoRow

// Verificación de sesión
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Sesión expirada. Recargue la página.']);
    exit;
}

$search_term = $_GET['search'] ?? '';
$limit = isset($_GET['limit']) && $_GET['limit'] > 0 ? (int)$_GET['limit'] : 10;
$page_num = isset($_GET['p']) && $_GET['p'] > 0 ? (int)$_GET['p'] : 1;
$offset = ($page_num - 1) * $limit;

try {
    // 1. Contar total para paginación
    $sql_count = "SELECT COUNT(cr.id) FROM creditos cr JOIN clientes c ON cr.cliente_id = c.id WHERE c.nombre LIKE :search";
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute([':search' => '%' . $search_term . '%']);
    $total_records = $stmt_count->fetchColumn();
    $total_pages = ceil($total_records / $limit);

    if ($page_num > $total_pages && $total_pages > 0) {
        $page_num = $total_pages;
        $offset = ($page_num - 1) * $limit;
    }

    // 2. Consulta OPTIMIZADA (Misma que en clientes.php)
    $sql = "SELECT 
                cr.id as credito_id,
                c.id as cliente_id,
                c.nombre,
                cr.zona,
                cr.frecuencia,
                cr.dia_pago,
                cr.dia_vencimiento,
                cr.estado,
                cr.monto_cuota,
                cr.cuotas_pagadas,
                cr.total_cuotas,
                (SELECT (monto_cuota - monto_pagado) 
                 FROM cronograma_cuotas 
                 WHERE credito_id = cr.id AND estado != 'Pagado' 
                 ORDER BY numero_cuota ASC LIMIT 1) as saldo_pendiente
            FROM creditos cr
            JOIN clientes c ON cr.cliente_id = c.id
            WHERE c.nombre LIKE :search
            ORDER BY c.nombre ASC, cr.id DESC 
            LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':search', '%' . $search_term . '%', PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $creditos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- CONSTRUCCIÓN DE RESPUESTA JSON ---
    $tableBody = '';
    if (empty($creditos)) {
        $tableBody = '<tr><td colspan="8" class="px-6 py-12 text-center text-gray-400"><i class="fas fa-search mb-2 text-2xl"></i><p>No se encontraron registros.</p></td></tr>';
    } else {
        foreach ($creditos as $credito) {
            // Usamos la función global definida en config.php
            $tableBody .= renderCreditoRow($credito, $NOMBRES_ZONAS);
        }
    }

    $pagination = generarPaginacion($page_num, $total_pages, 2);
    $start = ($total_records > 0) ? $offset + 1 : 0;
    $end = min($offset + $limit, $total_records);
    $resultsCounter = "Mostrando $start a $end de $total_records";

    header('Content-Type: application/json');
    echo json_encode([
        'tableBody' => $tableBody,
        'pagination' => $pagination,
        'resultsCounter' => $resultsCounter
    ]);

} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Error BD: ' . $e->getMessage()]);
}
?>