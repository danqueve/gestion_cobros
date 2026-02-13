<?php
// --- LÓGICA DE LA VISTA DE CLIENTES ATRASADOS (ACTUALIZADA) ---

$zona_seleccionada = $_GET['zona'] ?? 'all';

// --- MAPA DE NOMBRES PARA LAS ZONAS ---
$nombres_zonas =[
    1 => 'Santi',
    2 => 'Juan Pablo',
    3 => 'Enzo',
    4 => 'Tafi del V',
    5 => 'Famailla',
    6 => 'Sgo'
];

// --- LÓGICA DE ORDENAMIENTO (se añade 'saldo_cuota') ---
$sort_options = ['nombre', 'zona', 'ultimo_pago', 'dias_atraso', 'frecuencia', 'dia_cobro', 'monto_cuota', 'vencimiento_cuota', 'saldo_cuota'];
$sort_by = isset($_GET['sort_by']) && in_array($_GET['sort_by'], $sort_options) ? $_GET['sort_by'] : 'dias_atraso';
$sort_order = isset($_GET['sort_order']) && in_array(strtoupper($_GET['sort_order']), ['ASC', 'DESC']) ? strtoupper($_GET['sort_order']) : 'ASC';

// --- LÓGICA DE PAGINACIÓN ---
$limit_options = [10, 15, 25, 30];
$limit = isset($_GET['limit']) && in_array($_GET['limit'], $limit_options) ? (int)$_GET['limit'] : 10;
$page_num = isset($_GET['p']) && $_GET['p'] > 0 ? (int)$_GET['p'] : 1;
$offset = ($page_num - 1) * $limit;

try {
    // --- CONSULTA PARA OBTENER LOS CLIENTES ATRASADOS (CON SALDO) ---
    $sql_base = "SELECT c.nombre, c.telefono, cr.zona, cr.ultimo_pago, cr.frecuencia, cr.monto_cuota,
                   CASE 
                        WHEN cr.frecuencia = 'Semanal' THEN cr.dia_pago 
                        ELSE cr.dia_vencimiento 
                   END AS dia_cobro,
                   (SELECT MIN(fecha_vencimiento) FROM cronograma_cuotas cc WHERE cc.credito_id = cr.id AND cc.estado IN ('Pendiente', 'Pago Parcial')) as vencimiento_cuota,
                   DATEDIFF(CURDATE(), (SELECT MIN(fecha_vencimiento) FROM cronograma_cuotas cc WHERE cc.credito_id = cr.id AND cc.estado IN ('Pendiente', 'Pago Parcial'))) AS dias_atraso,
                   (SELECT (monto_cuota - monto_pagado) FROM cronograma_cuotas cc WHERE cc.credito_id = cr.id AND cc.estado IN ('Pendiente', 'Pago Parcial') ORDER BY cc.numero_cuota ASC LIMIT 1) as saldo_cuota
            FROM creditos cr
            JOIN clientes c ON cr.cliente_id = c.id
            WHERE cr.estado = 'Activo'";

    $params = [];
    if ($zona_seleccionada != 'all') {
        $sql_base .= " AND cr.zona = :zona";
        $params[':zona'] = $zona_seleccionada;
    }
    
    $sql_base .= " HAVING vencimiento_cuota IS NOT NULL AND dias_atraso > 0";
    
    // --- CONSULTA PARA CONTAR ---
    $sql_count = "SELECT COUNT(*) FROM ({$sql_base}) AS subquery";
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute($params);
    $total_records = $stmt_count->fetchColumn();
    $total_pages = ceil($total_records / $limit);

    // --- CONSULTA PARA LA VISTA WEB Y PDF ---
    $sql_final = $sql_base . " ORDER BY $sort_by $sort_order";
    
    $stmt_pdf = $pdo->prepare($sql_final);
    $stmt_pdf->execute($params);
    $atrasados_pdf = $stmt_pdf->fetchAll(PDO::FETCH_ASSOC);

    $sql_final .= " LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql_final);
    
    if ($zona_seleccionada != 'all') {
        $stmt->bindValue(':zona', $params[':zona'], PDO::PARAM_INT);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $atrasados = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error al obtener los clientes atrasados: " . $e->getMessage());
}

// Función para generar los enlaces de ordenamiento
function sort_link($column, $text, $current_sort, $current_order) {
    $order = ($current_sort == $column && $current_order == 'ASC') ? 'DESC' : 'ASC';
    $icon = '';
    if ($current_sort == $column) {
        $icon = $current_order == 'ASC' ? '<i class="fas fa-arrow-up ml-1"></i>' : '<i class="fas fa-arrow-down ml-1"></i>';
    }
    $limit_param = isset($_GET['limit']) ? "&limit=" . $_GET['limit'] : "";
    $zona_param = isset($_GET['zona']) ? "&zona=" . $_GET['zona'] : "";
    return "<a href='?page=atrasados&sort_by=$column&sort_order=$order$limit_param$zona_param'>$text $icon</a>";
}

// Lógica para el título dinámico
$title_text = "Listado General de Atrasados";
if ($zona_seleccionada != 'all' && isset($nombres_zonas[$zona_seleccionada])) {
    $title_text = "Listado de Atrasados - " . htmlspecialchars($nombres_zonas[$zona_seleccionada]);
}

// Obtener datos para el PDF
$nombre_cobrador = $_SESSION['user_nombre'] ?? 'Usuario';
date_default_timezone_set('America/Argentina/Buenos_Aires');
$fecha_impresion = date('d/m/Y H:i:s');
?>
<!-- Inclusión de las librerías para generar PDF -->
<script src="https://unpkg.com/jspdf@latest/dist/jspdf.umd.min.js"></script>
<script src="https://unpkg.com/jspdf-autotable@latest/dist/jspdf.plugin.autotable.js"></script>

<!-- ESTILOS Y MEJORAS VISUALES -->
<style>
    .row-even { background-color: #2d3748; }
    .row-odd { background-color: rgba(55, 65, 81, 0.5); }
    tbody tr:hover { background-color: #4a5568; }
</style>

<div class="print-area">
    <h2 class="text-2xl font-bold text-gray-200 mb-4 print-title"><?= $title_text ?></h2>

    <div class="bg-gray-800 p-6 rounded-lg border border-gray-700 print-container">
        <!-- Controles de Filtro y Exportación -->
        <div class="flex flex-col sm:flex-row justify-between items-center mb-4 gap-4 no-print">
            <form action="index.php" method="GET" class="flex items-center gap-4">
                <input type="hidden" name="page" value="atrasados">
                <label for="zona" class="text-sm font-medium text-gray-300">Filtrar por Zona:</label>
                <select name="zona" id="zona" onchange="this.form.submit()" class="text-sm rounded-md form-element-dark">
                    <option value="all" <?= $zona_seleccionada == 'all' ? 'selected' : '' ?>>Todas las Zonas</option>
                    <?php foreach($nombres_zonas as $num => $nombre): ?>
                        <option value="<?= $num ?>" <?= $zona_seleccionada == $num ? 'selected' : '' ?>><?= htmlspecialchars($nombre) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <button type="button" id="export-pdf-btn" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-md">
                <i class="fas fa-file-pdf mr-2"></i>Exportar a PDF
            </button>
        </div>
        
        <!-- Controles de Paginación -->
        <div class="flex flex-col sm:flex-row justify-between items-center mb-4 gap-4 no-print">
            <div class="text-sm text-gray-400">
                <?php
                    $start_item = ($total_records > 0) ? $offset + 1 : 0;
                    $end_item = min($offset + $limit, $total_records);
                    if ($total_records > 0) {
                        echo "Mostrando $start_item a $end_item de $total_records clientes atrasados";
                    }
                ?>
            </div>
            <form action="index.php" method="GET" class="flex items-center gap-2">
                <input type="hidden" name="page" value="atrasados">
                <input type="hidden" name="zona" value="<?= htmlspecialchars($zona_seleccionada) ?>">
                <label for="limit" class="text-sm text-gray-400">Mostrar:</label>
                <select name="limit" id="limit" onchange="this.form.submit()" class="text-sm rounded-md form-element-dark">
                    <?php foreach($limit_options as $option): ?>
                        <option value="<?= $option ?>" <?= $limit == $option ? 'selected' : '' ?>><?= $option ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <!-- Tabla de Clientes Atrasados -->
        <div class="overflow-x-auto no-print">
            <table class="min-w-full divide-y divide-gray-700">
                <thead class="table-header-custom">
                    <tr>
                        <th class="px-2 py-3 text-center text-xs font-medium text-gray-300 uppercase tracking-wider">#</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider"><?= sort_link('nombre', 'Cliente', $sort_by, $sort_order) ?></th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-300 uppercase tracking-wider"><?= sort_link('saldo_cuota', 'Saldo Cuota', $sort_by, $sort_order) ?></th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-300 uppercase tracking-wider"><?= sort_link('monto_cuota', 'Monto Cuota', $sort_by, $sort_order) ?></th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-300 uppercase tracking-wider"><?= sort_link('vencimiento_cuota', 'Venc. Cuota', $sort_by, $sort_order) ?></th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-300 uppercase tracking-wider"><?= sort_link('dias_atraso', 'Días de Atraso', $sort_by, $sort_order) ?></th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Teléfono</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                    <?php if (empty($atrasados)): ?>
                        <tr><td colspan="7" class="px-6 py-12 text-center text-gray-400"><i class="fas fa-check-circle fa-3x mb-3 text-green-500"></i><p>¡Excelente! No hay clientes con atrasos.</p></td></tr>
                    <?php else: ?>
                        <?php foreach (array_values($atrasados) as $index => $cliente): ?>
                        <tr class="<?= ($index % 2 == 0) ? 'row-even' : 'row-odd' ?>">
                            <td class="px-2 py-3 whitespace-nowrap text-center text-gray-300"><?= $offset + $index + 1 ?></td>
                            <td class="px-4 py-3 whitespace-nowrap font-medium text-gray-100"><?= htmlspecialchars($cliente['nombre']) ?></td>
                            <td class="px-4 py-3 whitespace-nowrap text-center text-yellow-400 font-semibold"><?= formatCurrency($cliente['saldo_cuota']) ?></td>
                            <td class="px-4 py-3 whitespace-nowrap text-center text-gray-300 font-semibold"><?= formatCurrency($cliente['monto_cuota']) ?></td>
                            <td class="px-4 py-3 whitespace-nowrap text-center text-gray-300 font-semibold"><?= $cliente['vencimiento_cuota'] ? (new DateTime($cliente['vencimiento_cuota']))->format('d/m/Y') : 'N/A' ?></td>
                            <td class="px-4 py-3 whitespace-nowrap text-center font-bold text-red-500"><?= $cliente['dias_atraso'] ?> días</td>
                            <td class="px-4 py-3 whitespace-nowrap text-gray-300"><?= htmlspecialchars($cliente['telefono'] ?? 'N/A') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Navegación de Paginación -->
        <?php if($total_pages > 1): ?>
        <div class="mt-6 flex justify-center items-center gap-2 no-print">
             <a href="?page=atrasados&p=<?= $page_num - 1 ?>&limit=<?= $limit ?>&zona=<?= $zona_seleccionada ?>&sort_by=<?= $sort_by ?>&sort_order=<?= $sort_order ?>" class="<?= $page_num <= 1 ? 'pointer-events-none text-gray-600' : 'text-blue-400 hover:text-blue-300' ?>"><i class="fas fa-chevron-left"></i> Anterior</a>
             <div class="flex gap-2">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=atrasados&p=<?= $i ?>&limit=<?= $limit ?>&zona=<?= $zona_seleccionada ?>&sort_by=<?= $sort_by ?>&sort_order=<?= $sort_order ?>" class="px-3 py-1 rounded-md <?= $i == $page_num ? 'bg-blue-600 text-white' : 'bg-gray-700 hover:bg-gray-600' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
            <a href="?page=atrasados&p=<?= $page_num + 1 ?>&limit=<?= $limit ?>&zona=<?= $zona_seleccionada ?>&sort_by=<?= $sort_by ?>&sort_order=<?= $sort_order ?>" class="<?= $page_num >= $total_pages ? 'pointer-events-none text-gray-600' : 'text-blue-400 hover:text-blue-300' ?>">Siguiente <i class="fas fa-chevron-right"></i></a>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('export-pdf-btn').addEventListener('click', function() {
        try {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF({
                orientation: 'portrait',
                unit: 'mm',
                format: 'a4'
            });

            const title = "<?= addslashes($title_text) ?>";
            const cobrador = "Cobrador: <?= addslashes($nombre_cobrador) ?>";
            const fecha = "Fecha de Impresión: <?= addslashes($fecha_impresion) ?>";
            
            const head = [['#', 'Cliente', 'Teléfono', 'Día Cobro', 'Venc.', 'Saldo', 'Frec.', 'Cuota', 'Ult. Pago', 'Atraso']];
            const body = <?= json_encode(array_map(function($cliente, $index) {
                return [
                    $index + 1,
                    $cliente['nombre'],
                    $cliente['telefono'] ?? 'N/A',
                    $cliente['dia_cobro'],
                    $cliente['vencimiento_cuota'] ? (new DateTime($cliente['vencimiento_cuota']))->format('d/m/Y') : 'N/A',
                    formatCurrency($cliente['saldo_cuota']),
                    $cliente['frecuencia'],
                    formatCurrency($cliente['monto_cuota']),
                    $cliente['ultimo_pago'] ? (new DateTime($cliente['ultimo_pago']))->format('d/m/Y') : 'N/A',
                    $cliente['dias_atraso'] . ' días'
                ];
            }, $atrasados_pdf, array_keys($atrasados_pdf)), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;

            doc.autoTable({
                head: head,
                body: body,
                margin: { top: 30 },
                didDrawPage: function(data) {
                    doc.setFontSize(16);
                    doc.setTextColor(40);
                    doc.text(title, data.settings.margin.left, 15);
                    doc.setFontSize(9);
                    doc.setTextColor(100);
                    doc.text(cobrador, data.settings.margin.left, 22);
                    doc.text(fecha, doc.internal.pageSize.getWidth() - data.settings.margin.right, 22, { align: 'right' });
                },
                styles: {
                    fontSize: 6.5, 
                    cellPadding: 1,
                    overflow: 'linebreak'
                },
                headStyles: {
                    fillColor: [255, 255, 255],
                    textColor: [0, 0, 0],
                    fontStyle: 'bold'
                },
                 columnStyles: {
                    0: { cellWidth: 7 }, // #
                    1: { cellWidth: 'auto' }, // Cliente
                    2: { cellWidth: 20 }, // Telefono
                    3: { cellWidth: 15 }, // Día Cobro
                    4: { cellWidth: 16 }, // Venc. Cuota
                    5: { cellWidth: 16 }, // Saldo
                    6: { cellWidth: 15 }, // Frec.
                    7: { cellWidth: 16 }, // Monto
                    8: { cellWidth: 16 }, // Últ. Pago
                    9: { cellWidth: 15 }  // Atraso
                }
            });

            doc.output('dataurlnewwindow');

        } catch (e) {
            console.error("Error al generar el PDF:", e);
            alert("Hubo un error al intentar generar el PDF. Revise la consola para más detalles.");
        }
    });
});

function formatCurrency(value) {
    return '$' + new Intl.NumberFormat('es-AR').format(value);
}
</script>

