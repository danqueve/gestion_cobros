<?php
// --- LÓGICA DE LA VISTA DE CRÉDITOS FINALIZADOS ---
// CORRECCIONES DE CONSISTENCIA:
//   #1 — sort_link_historial() eliminada; se usa sort_link() de config.php
//   #2 — Todos los href que decían ?page=historial corregidos a ?page=finalizados

$nombres_zonas = [
    1 => 'Santi',
    2 => 'Juan Pablo',
    3 => 'Enzo',
    4 => 'Tafi del V',
    5 => 'Famailla',
    6 => 'Sgo'
];

$sort_options = ['nombre', 'zona', 'fecha_pago_real', 'monto_total', 'fecha_vencimiento_programada'];
$sort_by      = isset($_GET['sort_by'])    && in_array($_GET['sort_by'],    $sort_options)                     ? $_GET['sort_by']             : 'fecha_pago_real';
$sort_order   = isset($_GET['sort_order']) && in_array(strtoupper($_GET['sort_order']), ['ASC', 'DESC'])        ? strtoupper($_GET['sort_order']) : 'DESC';

$limit_options = [15, 30, 50, 100];
$limit    = isset($_GET['limit']) && in_array($_GET['limit'], $limit_options) ? (int)$_GET['limit'] : 15;
$page_num = isset($_GET['p'])     && $_GET['p'] > 0                           ? (int)$_GET['p']     : 1;
$offset   = ($page_num - 1) * $limit;

$nombre_cobrador = $_SESSION['user_nombre'] ?? 'Usuario';
date_default_timezone_set('America/Argentina/Buenos_Aires');
$fecha_impresion = date('d/m/Y H:i:s');
$title_text      = "Historial de Créditos Pagados";

// Parámetros extra que se pasan a sort_link() para que la paginación
// y el limit se preserven al hacer clic en los encabezados de columna
$sort_extra = array_filter([
    'limit' => $limit !== 15 ? $limit : null,
    'p'     => $page_num > 1  ? $page_num : null,
]);

try {
    $sql_base = "SELECT
                    c.nombre,
                    cr.id AS credito_id,
                    cr.zona,
                    cr.monto_total,
                    cr.ultimo_pago AS fecha_pago_real,
                    (SELECT fecha_vencimiento
                     FROM cronograma_cuotas cc
                     WHERE cc.credito_id = cr.id
                     ORDER BY cc.numero_cuota DESC
                     LIMIT 1
                    ) AS fecha_vencimiento_programada
                FROM creditos cr
                JOIN clientes c ON cr.cliente_id = c.id
                WHERE cr.estado = 'Pagado'";

    // Contar total
    $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM ({$sql_base}) AS sub");
    $stmt_count->execute();
    $total_records = $stmt_count->fetchColumn();
    $total_pages   = ceil($total_records / $limit);

    // Datos para PDF (todos, sin paginar)
    $stmt_pdf = $pdo->prepare($sql_base . " ORDER BY $sort_by $sort_order");
    $stmt_pdf->execute();
    $creditos_pdf_data = $stmt_pdf->fetchAll(PDO::FETCH_ASSOC);

    // Datos para la vista web (paginados)
    $stmt = $pdo->prepare($sql_base . " ORDER BY $sort_by $sort_order LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $creditos_finalizados = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error al generar el historial: " . $e->getMessage());
}
?>

<script src="https://unpkg.com/jspdf@latest/dist/jspdf.umd.min.js"></script>
<script src="https://unpkg.com/jspdf-autotable@latest/dist/jspdf.plugin.autotable.js"></script>

<div class="bg-gray-800 p-6 rounded-lg border border-gray-700">
    <div class="flex flex-col sm:flex-row justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-200"><?= $title_text ?></h2>
        <button type="button" id="export-pdf-btn"
                class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-md mt-4 sm:mt-0 no-print">
            <i class="fas fa-file-pdf mr-2"></i>Exportar a PDF
        </button>
    </div>

    <!-- Control de cantidad de registros por página -->
    <div class="flex justify-start items-center mb-4 no-print">
        <!-- CORRECCIÓN #2: action apunta a finalizados, no a historial -->
        <form action="index.php" method="GET" class="flex items-center gap-2">
            <input type="hidden" name="page"       value="finalizados">
            <input type="hidden" name="sort_by"    value="<?= htmlspecialchars($sort_by) ?>">
            <input type="hidden" name="sort_order" value="<?= htmlspecialchars($sort_order) ?>">
            <label for="limit" class="text-sm text-gray-400">Mostrar:</label>
            <select name="limit" id="limit" onchange="this.form.submit()" class="text-sm rounded-md form-element-dark">
                <?php foreach ($limit_options as $option): ?>
                    <option value="<?= $option ?>" <?= $limit == $option ? 'selected' : '' ?>><?= $option ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <!-- Tabla -->
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-700">
            <thead class="table-header-custom">
                <tr>
                    <!-- CORRECCIÓN #1 y #2: sort_link() unificada con page='finalizados' -->
                    <th class="px-4 py-3 text-left   text-xs font-medium text-gray-300 uppercase tracking-wider">
                        <?= sort_link('nombre',                      'Cliente',          $sort_by, $sort_order, 'finalizados', $sort_extra) ?>
                    </th>
                    <th class="px-4 py-3 text-center  text-xs font-medium text-gray-300 uppercase tracking-wider">
                        <?= sort_link('zona',                        'Zona',             $sort_by, $sort_order, 'finalizados', $sort_extra) ?>
                    </th>
                    <th class="px-4 py-3 text-center  text-xs font-medium text-gray-300 uppercase tracking-wider">
                        <?= sort_link('monto_total',                 'Monto Total',      $sort_by, $sort_order, 'finalizados', $sort_extra) ?>
                    </th>
                    <th class="px-4 py-3 text-center  text-xs font-medium text-gray-300 uppercase tracking-wider">
                        <?= sort_link('fecha_vencimiento_programada','Venc. Programado', $sort_by, $sort_order, 'finalizados', $sort_extra) ?>
                    </th>
                    <th class="px-4 py-3 text-center  text-xs font-medium text-gray-300 uppercase tracking-wider">
                        <?= sort_link('fecha_pago_real',             'Fecha Pago Final', $sort_by, $sort_order, 'finalizados', $sort_extra) ?>
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-700">
                <?php if (empty($creditos_finalizados)): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-gray-400">
                            No se encontraron créditos en el historial.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($creditos_finalizados as $index => $row): ?>
                    <tr class="<?= ($index % 2 === 0) ? 'row-even' : 'row-odd' ?> hover:bg-gray-700/50">
                        <td class="px-4 py-3 whitespace-nowrap">
                            <a href="index.php?page=editar_cliente&id=<?= $row['credito_id'] ?>"
                               class="font-medium text-blue-400 hover:underline">
                                <?= htmlspecialchars($row['nombre']) ?>
                            </a>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-center text-gray-300">
                            <?= htmlspecialchars($nombres_zonas[$row['zona']] ?? 'N/A') ?>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-center text-gray-300">
                            <?= formatCurrency($row['monto_total']) ?>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-center text-gray-300">
                            <?= $row['fecha_vencimiento_programada']
                                ? (new DateTime($row['fecha_vencimiento_programada']))->format('d/m/Y')
                                : 'N/A' ?>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-center text-gray-300">
                            <?= $row['fecha_pago_real']
                                ? (new DateTime($row['fecha_pago_real']))->format('d/m/Y')
                                : 'N/A' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Paginación -->
    <?php if ($total_pages > 1): ?>
    <div class="mt-6 flex justify-between items-center text-sm text-gray-400 no-print">
        <div>
            Mostrando <?= ($total_records > 0) ? $offset + 1 : 0 ?>
            a <?= min($offset + $limit, $total_records) ?>
            de <?= $total_records ?> registros.
        </div>
        <div class="flex items-center gap-2">
            <!-- CORRECCIÓN #2: Todos los href usan page=finalizados -->
            <a href="?page=finalizados&p=<?= $page_num - 1 ?>&limit=<?= $limit ?>&sort_by=<?= $sort_by ?>&sort_order=<?= $sort_order ?>"
               class="<?= $page_num <= 1 ? 'pointer-events-none text-gray-600' : 'text-blue-400 hover:text-blue-300' ?>">
                <i class="fas fa-chevron-left"></i> Anterior
            </a>
            <div class="flex gap-1">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=finalizados&p=<?= $i ?>&limit=<?= $limit ?>&sort_by=<?= $sort_by ?>&sort_order=<?= $sort_order ?>"
                       class="px-3 py-1 rounded-md <?= $i === $page_num ? 'bg-blue-600 text-white' : 'bg-gray-700 hover:bg-gray-600' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
            <a href="?page=finalizados&p=<?= $page_num + 1 ?>&limit=<?= $limit ?>&sort_by=<?= $sort_by ?>&sort_order=<?= $sort_order ?>"
               class="<?= $page_num >= $total_pages ? 'pointer-events-none text-gray-600' : 'text-blue-400 hover:text-blue-300' ?>">
                Siguiente <i class="fas fa-chevron-right"></i>
            </a>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- PDF -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('export-pdf-btn').addEventListener('click', function () {
        try {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });

            const title    = "<?= addslashes($title_text) ?>";
            const cobrador = "Generado por: <?= addslashes($nombre_cobrador) ?>";
            const fecha    = "Fecha: <?= addslashes($fecha_impresion) ?>";

            const head = [['#', 'Cliente', 'Zona', 'Monto Total', 'Venc. Programado', 'Pago Final']];
            const body  = <?= json_encode(array_map(function ($row, $index) use ($nombres_zonas) {
                return [
                    $index + 1,
                    $row['nombre'],
                    $nombres_zonas[$row['zona']] ?? 'N/A',
                    formatCurrency($row['monto_total']),
                    $row['fecha_vencimiento_programada'] ? (new DateTime($row['fecha_vencimiento_programada']))->format('d/m/Y') : 'N/A',
                    $row['fecha_pago_real']              ? (new DateTime($row['fecha_pago_real']))->format('d/m/Y')              : 'N/A',
                ];
            }, $creditos_pdf_data, array_keys($creditos_pdf_data)),
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;

            doc.autoTable({
                head, body,
                margin: { top: 30 },
                didDrawPage: function (data) {
                    doc.setFontSize(16); doc.setTextColor(40);
                    doc.text(title, data.settings.margin.left, 15);
                    doc.setFontSize(9); doc.setTextColor(100);
                    doc.text(cobrador, data.settings.margin.left, 22);
                    doc.text(fecha, doc.internal.pageSize.getWidth() - data.settings.margin.right, 22, { align: 'right' });
                },
                styles:     { fontSize: 9, cellPadding: 2 },
                headStyles: { fillColor: [255,255,255], textColor: [0,0,0], fontStyle: 'bold' },
                columnStyles: {
                    0: { cellWidth: 8 },
                    1: { cellWidth: 'auto' },
                    2: { cellWidth: 30 },
                    3: { cellWidth: 30, halign: 'right' },
                    4: { cellWidth: 30, halign: 'center' },
                    5: { cellWidth: 30, halign: 'center' },
                }
            });

            doc.output('dataurlnewwindow');
        } catch (e) {
            console.error('Error al generar el PDF:', e);
            alert('Hubo un error al generar el PDF. Revise la consola.');
        }
    });
});

function formatCurrency(value) {
    return '$' + new Intl.NumberFormat('es-AR').format(value);
}
</script>