<?php
// --- LÓGICA DE BÚSQUEDA ASÍNCRONA PARA CRÉDITOS ---
require_once '../config.php';

// --- MAPA DE NOMBRES PARA LAS ZONAS ---
$nombres_zonas = [
    1 => 'Santi',
    2 => 'Juan Pablo',
    3 => 'Enzo',
    4 => 'Tafi del V',
    5 => 'Famailla',
    6 => 'Sgo'
];

// --- OBTENER PARÁMETROS ---
$search_term = $_GET['search'] ?? '';
$limit = isset($_GET['limit']) && $_GET['limit'] > 0 ? (int)$_GET['limit'] : 10;
$page_num = isset($_GET['p']) && $_GET['p'] > 0 ? (int)$_GET['p'] : 1;
$offset = ($page_num - 1) * $limit;

try {
    // --- CONSULTAS ACTUALIZADAS PARA BUSCAR CRÉDITOS ---
    $sql_count = "SELECT COUNT(cr.id) FROM creditos cr JOIN clientes c ON cr.cliente_id = c.id WHERE c.nombre LIKE :search";
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute([':search' => '%' . $search_term . '%']);
    $total_records = $stmt_count->fetchColumn();
    $total_pages = ceil($total_records / $limit);

    if ($page_num > $total_pages && $total_pages > 0) {
        $page_num = $total_pages;
        $offset = ($page_num - 1) * $limit;
    }

    // Consulta completa idéntica a clientes.php
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
                (SELECT (monto_cuota - monto_pagado) FROM cronograma_cuotas WHERE credito_id = cr.id AND estado != 'Pagado' ORDER BY numero_cuota ASC LIMIT 1) as saldo_pendiente
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

    // --- GENERAR HTML PARA LA RESPUESTA ---
    ob_start();

    // Contenido del cuerpo de la tabla
    if (empty($creditos)) {
        echo '<tr><td colspan="8" class="px-6 py-12 text-center text-gray-400 table-row-dark"><i class="fas fa-search-minus fa-3x mb-3"></i><p>No se encontraron créditos.</p></td></tr>';
    } else {
        foreach ($creditos as $index => $credito) {
            $row_class = ($index % 2 == 0) ? 'row-even' : 'row-odd';
            
            // Lógica para mostrar el día de pago o vencimiento
            $dia_cobro = ($credito['frecuencia'] == 'Semanal') ? $credito['dia_pago'] : 'Día ' . $credito['dia_vencimiento'];
            
            // Lógica para obtener la fecha de vencimiento de la próxima cuota (opcional, para mostrar en la tabla si lo deseas)
            $stmt_venc = $pdo->prepare("SELECT fecha_vencimiento FROM cronograma_cuotas WHERE credito_id = ? AND estado != 'Pagado' ORDER BY numero_cuota ASC LIMIT 1");
            $stmt_venc->execute([$credito['credito_id']]);
            $vencimiento = $stmt_venc->fetchColumn();

            ?>
            <tr class="<?= $row_class ?> hover:bg-gray-700/50">
                <td class="px-4 py-4 whitespace-nowrap font-medium text-gray-100">
                    <a href="index.php?page=editar_cliente&id=<?= $credito['credito_id'] ?>" class="hover:underline">
                        <?= htmlspecialchars($credito['nombre']) ?>
                    </a>
                </td>
                <td class="px-4 py-4 whitespace-nowrap text-center text-gray-300"><?= htmlspecialchars($nombres_zonas[$credito['zona']] ?? 'N/A') ?></td>
                <td class="px-4 py-4 whitespace-nowrap text-center text-gray-300"><?= htmlspecialchars($credito['frecuencia']) ?></td>
                <td class="px-4 py-4 whitespace-nowrap text-center text-gray-300"><?= htmlspecialchars($dia_cobro ?? '-') ?></td>
                <td class="px-4 py-4 whitespace-nowrap text-center">
                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $credito['estado'] == 'Pagado' ? 'bg-green-900 text-green-300' : 'bg-blue-900 text-blue-300' ?>">
                        <?= htmlspecialchars($credito['estado']) ?>
                    </span>
                </td>
                <td class="px-4 py-4 whitespace-nowrap text-right text-gray-300"><?= formatCurrency($credito['monto_cuota']) ?></td>
                <td class="px-4 py-4 whitespace-nowrap text-center text-gray-300">
                    <?= $credito['cuotas_pagadas'] ?> / <?= $credito['total_cuotas'] ?>
                    <?php if ($vencimiento): ?>
                        <br><span class="text-xs text-gray-500"><?= (new DateTime($vencimiento))->format('d/m') ?></span>
                    <?php endif; ?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-center no-print flex justify-center gap-2">
                    <!-- BOTÓN DE PAGO RÁPIDO -->
                    <?php if ($credito['estado'] != 'Pagado'): ?>
                        <button onclick="abrirModalPago(<?= $credito['credito_id'] ?>, '<?= htmlspecialchars($credito['nombre'], ENT_QUOTES) ?>', <?= $credito['saldo_pendiente'] ?: 0 ?>)" class="text-green-500 hover:text-green-400" title="Registrar Pago Rápido">
                            <i class="fas fa-money-bill-wave"></i>
                        </button>
                    <?php endif; ?>

                    <a href="index.php?page=editar_cliente&id=<?= $credito['credito_id'] ?>" class="text-blue-400 hover:text-blue-300" title="Ver Detalle / Editar"><i class="fas fa-pencil-alt"></i></a>
                    <a href="index.php?page=eliminar_cliente&id=<?= $credito['cliente_id'] ?>" class="text-red-500 hover:text-red-400 ml-4" title="Eliminar Cliente y Crédito" onclick="return confirm('¿Estás seguro de que quieres eliminar a este cliente y todos sus datos asociados? Esta acción no se puede deshacer.')"><i class="fas fa-trash-alt"></i></a>
                </td>
            </tr>
            <?php
        }
    }
    $tableBody = ob_get_clean();

    // Contenido de la paginación (CORREGIDO)
    ob_start();
    echo generarPaginacion($page_num, $total_pages, 2); 
    $pagination = ob_get_clean();

    // Contador de resultados
    $start_item = ($total_records > 0) ? $offset + 1 : 0;
    $end_item = min($offset + $limit, $total_records);
    $resultsCounter = "Mostrando $start_item a $end_item de $total_records créditos";
    
    // Devolver JSON
    header('Content-Type: application/json');
    echo json_encode([
        'tableBody' => $tableBody,
        'pagination' => $pagination,
        'resultsCounter' => $resultsCounter
    ]);

} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Error en la base de datos: ' . $e->getMessage()]);
}
?>