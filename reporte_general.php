<?php
// --- LÓGICA DE LA VISTA DE REPORTE GENERAL ---

// --- MAPA DE NOMBRES PARA LAS ZONAS ---
$nombres_zonas = [
    1 => 'Santi',
    2 => 'Juan Pablo',
    3 => 'Enzo',
    4 => 'Tafi del V',
    5 => 'Famailla',
    6 => 'Sgo'
];
// --- LÓGICA DE ORDENAMIENTO ---
$sort_options = ['nombre', 'zona', 'frecuencia', 'estado_credito', 'ultimo_pago'];
$sort_by = isset($_GET['sort_by']) && in_array($_GET['sort_by'], $sort_options) ? $_GET['sort_by'] : 'nombre';
$sort_order = isset($_GET['sort_order']) && in_array(strtoupper($_GET['sort_order']), ['ASC', 'DESC']) ? strtoupper($_GET['sort_order']) : 'ASC';

// --- LÓGICA DE PAGINACIÓN ---
$limit_options = [15, 30, 50, 100];
$limit = isset($_GET['limit']) && in_array($_GET['limit'], $limit_options) ? (int)$_GET['limit'] : 15;
$page_num = isset($_GET['p']) && $_GET['p'] > 0 ? (int)$_GET['p'] : 1;
$offset = ($page_num - 1) * $limit;

try {
    // --- CONSULTA PARA CONTAR EL TOTAL DE REGISTROS ---
    $sql_count = "SELECT COUNT(c.id) FROM clientes c";
    $stmt_count = $pdo->query($sql_count);
    $total_records = $stmt_count->fetchColumn();
    $total_pages = ceil($total_records / $limit);

    // --- CONSULTA PARA OBTENER LOS DATOS PAGINADOS ---
    $sql = "SELECT
                c.id AS cliente_id, c.nombre, c.telefono, c.direccion,
                cr.id AS credito_id,
                cr.zona,
                cr.frecuencia,
                CASE WHEN cr.frecuencia = 'Semanal' THEN cr.dia_pago ELSE cr.dia_vencimiento END AS dia_de_cobro,
                cr.monto_cuota, cr.total_cuotas, cr.cuotas_pagadas, cr.estado AS estado_credito, cr.ultimo_pago
            FROM clientes c
            LEFT JOIN creditos cr ON c.id = cr.cliente_id
            ORDER BY $sort_by $sort_order
            LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $reporte_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error al generar el reporte: " . $e->getMessage());
}

// Función para generar los enlaces de ordenamiento
function sort_link_reporte($column, $text, $current_sort, $current_order) {
    $order = ($current_sort == $column && $current_order == 'ASC') ? 'DESC' : 'ASC';
    $icon = '';
    if ($current_sort == $column) {
        $icon = $current_order == 'ASC' ? '<i class="fas fa-arrow-up ml-1"></i>' : '<i class="fas fa-arrow-down ml-1"></i>';
    }
    $limit_param = isset($_GET['limit']) ? "&limit=" . $_GET['limit'] : "";
    return "<a href='?page=reporte_general&sort_by=$column&sort_order=$order$limit_param'>$text $icon</a>";
}
?>

<div class="bg-gray-800 p-6 rounded-lg border border-gray-700">
    <div class="flex flex-col sm:flex-row justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-200">Reporte General de Clientes y Créditos</h2>
        <a href="views/exportar_excel.php" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-md mt-4 sm:mt-0">
            <i class="fas fa-file-excel mr-2"></i>Exportar a Excel
        </a>
    </div>

    <!-- Controles de Paginación -->
    <div class="flex justify-start items-center mb-4">
        <form action="index.php" method="GET" class="flex items-center gap-2">
            <input type="hidden" name="page" value="reporte_general">
            <label for="limit" class="text-sm text-gray-400">Mostrar:</label>
            <select name="limit" id="limit" onchange="this.form.submit()" class="text-sm rounded-md form-element-dark">
                <?php foreach($limit_options as $option): ?>
                    <option value="<?= $option ?>" <?= $limit == $option ? 'selected' : '' ?>><?= $option ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <!-- Tabla del Reporte -->
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-700">
            <thead class="table-header-custom">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider"><?= sort_link_reporte('nombre', 'Cliente', $sort_by, $sort_order) ?></th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Teléfono</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-300 uppercase tracking-wider"><?= sort_link_reporte('zona', 'Zona', $sort_by, $sort_order) ?></th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-300 uppercase tracking-wider">Monto Cuota</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-300 uppercase tracking-wider">Cuotas</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-300 uppercase tracking-wider"><?= sort_link_reporte('estado_credito', 'Estado', $sort_by, $sort_order) ?></th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-300 uppercase tracking-wider"><?= sort_link_reporte('ultimo_pago', 'Último Pago', $sort_by, $sort_order) ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-700">
                <?php if (empty($reporte_data)): ?>
                    <tr><td colspan="7" class="px-6 py-12 text-center text-gray-400">No se encontraron registros.</td></tr>
                <?php else: ?>
                    <?php foreach ($reporte_data as $row): ?>
                    <tr class="table-row-dark hover:bg-gray-700/50">
                        <td class="px-4 py-3 whitespace-nowrap font-medium text-gray-100"><?= htmlspecialchars($row['nombre']) ?></td>
                        <td class="px-4 py-3 whitespace-nowrap text-gray-300"><?= htmlspecialchars($row['telefono'] ?? 'N/A') ?></td>
                        <td class="px-4 py-3 whitespace-nowrap text-center text-gray-300"><?= htmlspecialchars($nombres_zonas[$row['zona']] ?? 'N/A') ?></td>
                        <td class="px-4 py-3 whitespace-nowrap text-center text-gray-300"><?= $row['monto_cuota'] ? formatCurrency($row['monto_cuota']) : 'N/A' ?></td>
                        <td class="px-4 py-3 whitespace-nowrap text-center text-gray-300"><?= $row['credito_id'] ? ($row['cuotas_pagadas'] . ' / ' . $row['total_cuotas']) : 'N/A' ?></td>
                        <td class="px-4 py-3 whitespace-nowrap text-center">
                            <?php if($row['estado_credito'] == 'Pagado'): ?>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-900 text-green-300">Pagado</span>
                            <?php elseif($row['estado_credito'] == 'Activo'): ?>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-900 text-blue-300">Activo</span>
                            <?php else: ?>
                                <span class="text-gray-500">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-center text-gray-300"><?= $row['ultimo_pago'] ? (new DateTime($row['ultimo_pago']))->format('d/m/Y') : 'N/A' ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Navegación de Paginación -->
    <?php if($total_pages > 1): ?>
    <div class="mt-6 flex justify-between items-center text-sm text-gray-400">
        <div>
            Mostrando <?= $offset + 1 ?> a <?= min($offset + $limit, $total_records) ?> de <?= $total_records ?> registros.
        </div>
        <div class="flex items-center gap-2">
            <a href="?page=reporte_general&p=<?= $page_num - 1 ?>&limit=<?= $limit ?>&sort_by=<?= $sort_by ?>&sort_order=<?= $sort_order ?>" class="<?= $page_num <= 1 ? 'pointer-events-none text-gray-600' : 'text-blue-400 hover:text-blue-300' ?>"><i class="fas fa-chevron-left"></i> Anterior</a>
            <div class="flex gap-1">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=reporte_general&p=<?= $i ?>&limit=<?= $limit ?>&sort_by=<?= $sort_by ?>&sort_order=<?= $sort_order ?>" class="px-3 py-1 rounded-md <?= $i == $page_num ? 'bg-blue-600 text-white' : 'bg-gray-700 hover:bg-gray-600' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
            <a href="?page=reporte_general&p=<?= $page_num + 1 ?>&limit=<?= $limit ?>&sort_by=<?= $sort_by ?>&sort_order=<?= $sort_order ?>" class="<?= $page_num >= $total_pages ? 'pointer-events-none text-gray-600' : 'text-blue-400 hover:text-blue-300' ?>">Siguiente <i class="fas fa-chevron-right"></i></a>
        </div>
    </div>
    <?php endif; ?>
</div>

