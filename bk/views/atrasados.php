<?php
// --- LÓGICA DE LA VISTA DE CLIENTES ATRASADOS (OPTIMIZADA) ---

// 1. Configuración y Filtros
$zona_seleccionada = $_GET['zona'] ?? 'all';
$limit_options = [10, 20, 50, 100];
$limit = isset($_GET['limit']) && in_array((int)$_GET['limit'], $limit_options) ? (int)$_GET['limit'] : 10;
$page = isset($_GET['p']) && (int)$_GET['p'] > 0 ? (int)$_GET['p'] : 1;
$offset = ($page - 1) * $limit;

// Nombres de las zonas (idealmente esto vendría de una tabla base de datos o constante global)
$nombres_zonas = [
    1 => 'Santi', 
    2 => 'Juan Pablo', 
    3 => 'Enzo',
    4 => 'Tafi del V', 
    5 => 'Famailla', 
    6 => 'Sgo'
];

try {
    // 2. Construcción de la Consulta Optimizada
    // Usamos JOIN y GROUP BY para filtrar y agregar en una sola pasada.
    // Buscamos cuotas vencidas (fecha_vencimiento < HOY y estado != Pagado)
    
    $where_zona = "";
    $params = [];
    
    if ($zona_seleccionada !== 'all') {
        $where_zona = "AND cr.zona = :zona";
        $params[':zona'] = $zona_seleccionada;
    }

    // --- LOGICA EXPORTACION PDF (JSON) ---
    if (isset($_GET['export']) && $_GET['export'] === 'pdf_data') {
        // Misma query pero SIN limit/offset
        $sql_all = "SELECT 
                    c.nombre,
                    c.telefono,
                    cr.ultimo_pago,
                    cr.monto_cuota,
                    SUM(cc.monto_cuota - cc.monto_pagado) AS total_vencido,
                    COUNT(cc.id) AS cant_cuotas_vencidas,
                    DATEDIFF(CURDATE(), MIN(cc.fecha_vencimiento)) AS dias_atraso
                FROM creditos cr
                JOIN clientes c ON cr.cliente_id = c.id
                JOIN cronograma_cuotas cc ON cr.id = cc.credito_id
                WHERE cr.estado = 'Activo' 
                  AND cc.estado != 'Pagado' 
                  AND cc.fecha_vencimiento < CURDATE()
                  $where_zona
                GROUP BY cr.id
                ORDER BY total_vencido DESC";
        
        $stmt_all = $pdo->prepare($sql_all);
        $stmt_all->execute($params);
        $data = $stmt_all->fetchAll(PDO::FETCH_ASSOC);
        
        // Deolver JSON y terminar script
        ob_clean(); // Limpiar buffer por si acaso
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['data' => $data, 'zona' => $nombres_zonas[$zona_seleccionada] ?? 'Todas']);
        exit;
    }
    // --------------------------------------

    // Consulta para contar total de registros (para paginación)
    $sql_count = "SELECT COUNT(DISTINCT cr.id) 
                  FROM creditos cr
                  JOIN cronograma_cuotas cc ON cr.id = cc.credito_id
                  WHERE cr.estado = 'Activo' 
                    AND cc.estado != 'Pagado' 
                    AND cc.fecha_vencimiento < CURDATE()
                    $where_zona";
    
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute($params);
    $total_records = $stmt_count->fetchColumn();
    $total_pages = ceil($total_records / $limit);

    // Consulta Principal (Paginada)
    // Obtenemos: Datos Cliente, Total Vencido, Días del vencimiento más antiguo, Cantidad de cuotas vencidas
    $sql = "SELECT 
                c.id AS cliente_id,
                c.nombre,
                c.telefono,
                cr.id AS credito_id,
                cr.zona,
                cr.monto_cuota,
                cr.ultimo_pago,
                MIN(cc.fecha_vencimiento) AS fecha_vencimiento_antigua,
                SUM(cc.monto_cuota - cc.monto_pagado) AS total_vencido,
                COUNT(cc.id) AS cant_cuotas_vencidas,
                DATEDIFF(CURDATE(), MIN(cc.fecha_vencimiento)) AS dias_atraso
            FROM creditos cr
            JOIN clientes c ON cr.cliente_id = c.id
            JOIN cronograma_cuotas cc ON cr.id = cc.credito_id
            WHERE cr.estado = 'Activo' 
              AND cc.estado != 'Pagado' 
              AND cc.fecha_vencimiento < CURDATE()
              $where_zona
            GROUP BY cr.id
            ORDER BY total_vencido DESC
            LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    if ($zona_seleccionada !== 'all') {
        $stmt->bindValue(':zona', $zona_seleccionada, PDO::PARAM_INT);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $atrasados = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error en la base de datos: " . $e->getMessage());
}

// 3. Helpers para UI
$title_text = ($zona_seleccionada !== 'all' && isset($nombres_zonas[$zona_seleccionada]))
            ? "Atrasados - " . htmlspecialchars($nombres_zonas[$zona_seleccionada])
            : "Listado General de Atrasados";

function getSeverityClass($dias) {
    if ($dias > 30) return 'bg-red-900/50 text-red-200 border-red-700'; // Crítico
    if ($dias > 7)  return 'bg-orange-900/50 text-orange-200 border-orange-700'; // Moderado
    return 'bg-yellow-900/50 text-yellow-200 border-yellow-700'; // Leve
}
?>

<!-- Librerías para PDF -->
<script src="https://unpkg.com/jspdf@latest/dist/jspdf.umd.min.js"></script>
<script src="https://unpkg.com/jspdf-autotable@latest/dist/jspdf.plugin.autotable.js"></script>

<div class="max-w-7xl mx-auto">
    <!-- Encabezado y Acciones -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6 gap-4">
        <div>
            <h2 class="text-3xl font-bold text-white tracking-tight"><?= $title_text ?></h2>
            <p class="text-gray-400 mt-1">
                <i class="fas fa-exclamation-circle text-red-500 mr-1"></i>
                Se encontraron <span class="text-white font-bold"><?= $total_records ?></span> créditos con cuotas vencidas.
            </p>
        </div>
        <div class="flex flex-col sm:flex-row gap-3">
             <form action="index.php" method="GET" class="flex items-center gap-2 bg-gray-800 p-2 rounded-lg border border-gray-700">
                <input type="hidden" name="page" value="atrasados">
                <i class="fas fa-filter text-gray-400 ml-2"></i>
                <select name="zona" onchange="this.form.submit()" class="bg-transparent text-white text-sm focus:outline-none border-none py-1">
                    <option value="all" <?= $zona_seleccionada === 'all' ? 'selected' : '' ?>>Todas las Zonas</option>
                    <?php foreach ($nombres_zonas as $num => $nombre): ?>
                        <option value="<?= $num ?>" <?= $zona_seleccionada == $num ? 'selected' : '' ?>><?= htmlspecialchars($nombre) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <button type="button" id="export-pdf-btn" class="bg-red-600 hover:bg-red-700 text-white font-semibold py-2 px-4 rounded-lg shadow-md transition-colors flex items-center justify-center">
                <i class="fas fa-file-pdf mr-2"></i> PDF
            </button>
        </div>
    </div>

    <!-- Tabla de Atrasados -->
    <div class="bg-gray-800 rounded-xl shadow-2xl border border-gray-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-900/50 text-gray-400 text-sm uppercase tracking-wider border-b border-gray-700">
                        <th class="px-6 py-4 font-medium text-center">#</th>
                        <th class="px-6 py-4 font-medium">Cliente</th>
                        <th class="px-6 py-4 font-medium text-center">Deuda Total</th>
                        <th class="px-6 py-4 font-medium text-center">Cuotas Venc.</th>
                        <th class="px-6 py-4 font-medium text-center">Antigüedad</th>
                        <th class="px-6 py-4 font-medium text-center">Ult. Pago</th>
                        <th class="px-6 py-4 font-medium text-center">Zona</th>
                        <th class="px-6 py-4 font-medium text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700 text-gray-300">
                    <?php if (empty($atrasados)): ?>
                        <tr>
                            <td colspan="8" class="px-6 py-16 text-center text-gray-500">
                                <div class="flex flex-col items-center">
                                    <div class="bg-gray-700/50 p-4 rounded-full mb-3">
                                        <i class="fas fa-check text-green-500 text-3xl"></i>
                                    </div>
                                    <p class="text-lg font-medium text-gray-300">¡Al día!</p>
                                    <p class="text-sm">No hay clientes con pagos atrasados en este momento.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($atrasados as $i => $row): 
                            $badgeClass = getSeverityClass($row['dias_atraso']);
                            $wa_link = "https://wa.me/" . preg_replace('/[^0-9]/', '', '549' . $row['telefono']); // Asumiendo código país 549
                        ?>
                        <tr class="hover:bg-gray-700/30 transition-colors group">
                            <td class="px-6 py-4 text-center font-mono text-sm text-gray-500">
                                <?= $offset + $i + 1 ?>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10 rounded-full bg-gray-700 flex items-center justify-center text-indigo-400 font-bold border border-gray-600">
                                        <?= strtoupper(substr($row['nombre'], 0, 1)) ?>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-white font-medium group-hover:text-blue-400 transition-colors">
                                            <?= htmlspecialchars($row['nombre']) ?>
                                        </div>
                                        <div class="text-sm text-gray-500 flex items-center gap-1">
                                            <i class="fas fa-phone-alt text-xs"></i> <?= htmlspecialchars($row['telefono'] ?: 'Sin tel.') ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="bg-red-900/30 text-red-300 px-3 py-1 rounded-full text-sm font-bold border border-red-900/50">
                                    <?= formatCurrency($row['total_vencido']) ?>
                                </span>
                            </td>
                             <td class="px-6 py-4 text-center">
                                <span class="text-gray-300 font-medium"><?= $row['cant_cuotas_vencidas'] ?></span>
                                <span class="text-xs text-gray-500 block">($<?= formatCurrency($row['monto_cuota']) ?> c/u)</span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <div class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border <?= $badgeClass ?>">
                                    <i class="fas fa-clock mr-1"></i> <?= $row['dias_atraso'] ?> días
                                </div>
                                <div class="text-xs text-gray-500 mt-1">
                                    Desde <?= date('d/m', strtotime($row['fecha_vencimiento_antigua'])) ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center text-sm text-gray-400">
                                <?= $row['ultimo_pago'] ? date('d/m/Y', strtotime($row['ultimo_pago'])) : '-' ?>
                            </td>
                            <td class="px-6 py-4 text-center text-sm">
                                <?= $nombres_zonas[$row['zona']] ?? $row['zona'] ?>
                            </td>
                            <td class="px-6 py-4 text-center space-x-2">
                                <?php if($row['telefono']): ?>
                                <a href="<?= $wa_link ?>" target="_blank" class="text-green-500 hover:text-green-400 transition-colors bg-gray-700 hover:bg-gray-600 p-2 rounded-lg inline-flex" title="Enviar WhatsApp">
                                    <i class="fab fa-whatsapp fa-lg"></i>
                                </a>
                                <?php endif; ?>
                                <a href="index.php?page=editar_cliente&id=<?= $row['credito_id'] ?>" class="text-blue-500 hover:text-blue-400 transition-colors bg-gray-700 hover:bg-gray-600 p-2 rounded-lg inline-flex" title="Ver Detalles">
                                    <i class="fas fa-arrow-right fa-lg"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Paginación Footer -->
        <div class="bg-gray-900/50 px-6 py-4 border-t border-gray-700 flex flex-col sm:flex-row items-center justify-between gap-4">
            <span class="text-sm text-gray-400">
                Mostrando <?= $offset + 1 ?> a <?= min($offset + $limit, $total_records) ?> de <?= $total_records ?>
            </span>
            
            <?php if ($total_pages > 1): ?>
            <div class="flex items-center gap-2">
                <!-- Anterior -->
                <a href="?page=atrasados&p=<?= max(1, $page-1) ?>&limit=<?= $limit ?>&zona=<?= $zona_seleccionada ?>" 
                   class="px-3 py-1 rounded-md bg-gray-700 text-gray-300 hover:bg-gray-600 hover:text-white transition-colors <?= $page <= 1 ? 'opacity-50 pointer-events-none' : '' ?>">
                    <i class="fas fa-chevron-left"></i>
                </a>
                
                <!-- Números Paginación Simple -->
                <?php
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);
                for ($i = $start; $i <= $end; $i++): 
                ?>
                <a href="?page=atrasados&p=<?= $i ?>&limit=<?= $limit ?>&zona=<?= $zona_seleccionada ?>" 
                   class="px-3 py-1 rounded-md text-sm font-medium transition-colors <?= $i == $page ? 'bg-blue-600 text-white' : 'bg-gray-700 text-gray-300 hover:bg-gray-600' ?>">
                    <?= $i ?>
                </a>
                <?php endfor; ?>
                
                <!-- Siguiente -->
                <a href="?page=atrasados&p=<?= min($total_pages, $page+1) ?>&limit=<?= $limit ?>&zona=<?= $zona_seleccionada ?>" 
                   class="px-3 py-1 rounded-md bg-gray-700 text-gray-300 hover:bg-gray-600 hover:text-white transition-colors <?= $page >= $total_pages ? 'opacity-50 pointer-events-none' : '' ?>">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Lógica JS para PDF -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const btnExport = document.getElementById('export-pdf-btn');
    const nombresZonas = <?= json_encode($nombres_zonas) ?>;
    const zonaActual = "<?= $zona_seleccionada ?>";
    
    if(btnExport) {
        btnExport.addEventListener('click', function () {
            // 1. Mostrar estado de carga y Fetch datos completos
            const originalText = btnExport.innerHTML;
            btnExport.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Generando...';
            btnExport.disabled = true;

            fetch(`index.php?page=atrasados&export=pdf_data&zona=${zonaActual}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.text().then(text => {
                         try {
                             return JSON.parse(text);
                         } catch (e) {
                             console.error("Respuesta no válida del servidor:", text);
                             throw new Error("El servidor devolvió datos inválidos (no JSON).");
                         }
                    });
                })
                .then(res => {
                    const data = res.data;
                    const zonaNombre = res.zona;

                    const { jsPDF } = window.jspdf;
                    const doc = new jsPDF();
                    
                    // Título
                    doc.setFontSize(18);
                    doc.text(`Reporte de Atrasados - Zona: ${zonaNombre}`, 14, 20);
                    doc.setFontSize(11);
                    doc.text("Fecha: " + new Date().toLocaleDateString(), 14, 28);
                    
                    // Helper para fecha
                    const formatDate = (dateString) => {
                        if(!dateString) return '-';
                        const parts = dateString.split('-');
                        return `${parts[2]}/${parts[1]}/${parts[0]}`;
                    };

                    // Tabla
                    doc.autoTable({
                        startY: 35,
                        head: [['#', 'Cliente', 'Celular', 'Deuda Total', 'C. Atras.', 'Cuota', 'Ult. Pago', 'Días Atraso']],
                        body: data.map((r, index) => [
                            index + 1,
                            r.nombre,
                            r.telefono || '-',
                            '$' + new Intl.NumberFormat('es-AR').format(r.total_vencido),
                            r.cant_cuotas_vencidas,
                            '$' + new Intl.NumberFormat('es-AR').format(r.monto_cuota),
                            formatDate(r.ultimo_pago),
                            r.dias_atraso + ' días'
                        ]),
                        theme: 'grid',
                        styles: { fontSize: 8 },
                        headStyles: { fillColor: [220, 53, 69] } // Rojo
                    });
                    
                    // Abrir en nueva ventana
                    doc.output('dataurlnewwindow');
                })
                .catch(err => {
                    console.error(err);
                    alert("Error al generar PDF: " + err.message);
                })
                .finally(() => {
                    btnExport.innerHTML = originalText;
                    btnExport.disabled = false;
                });
        });
    }
});
</script>
