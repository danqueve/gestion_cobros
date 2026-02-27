<?php
// --- VISTA: LOG DE ACTIVIDAD DE PAGOS ---
// Muestra el historial de los últimos pagos registrados en el sistema.

// --- PARÁMETROS ---
$fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-d', strtotime('-7 days'));
$fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');
$limit_options = [20, 50, 100, 200];
$limit = isset($_GET['limit']) && in_array($_GET['limit'], $limit_options) ? (int)$_GET['limit'] : 20;
$page_num = isset($_GET['p']) && $_GET['p'] > 0 ? (int)$_GET['p'] : 1;
$offset = ($page_num - 1) * $limit;

// --- CONSULTA ---
$pagos = [];
$total_records = 0;
$total_pages = 0;
$total_monto = 0;

try {
    // Contar total de registros en el rango
    $sql_count = "SELECT COUNT(p.id)
                  FROM pagos p
                  JOIN creditos cr ON p.credito_id = cr.id
                  JOIN clientes c  ON cr.cliente_id = c.id
                  WHERE DATE(p.fecha_pago) BETWEEN :desde AND :hasta";
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute([':desde' => $fecha_desde, ':hasta' => $fecha_hasta]);
    $total_records = (int)$stmt_count->fetchColumn();
    $total_pages   = ($limit > 0) ? (int)ceil($total_records / $limit) : 0;

    if ($page_num > $total_pages && $total_pages > 0) {
        $page_num = $total_pages;
        $offset   = ($page_num - 1) * $limit;
    }

    // Sumatoria del período
    $sql_sum = "SELECT COALESCE(SUM(p.monto_pagado), 0)
                FROM pagos p
                WHERE DATE(p.fecha_pago) BETWEEN :desde AND :hasta";
    $stmt_sum = $pdo->prepare($sql_sum);
    $stmt_sum->execute([':desde' => $fecha_desde, ':hasta' => $fecha_hasta]);
    $total_monto = (float)$stmt_sum->fetchColumn();

    // Pagos con detalle
    $sql = "SELECT
                p.id            AS pago_id,
                p.monto_pagado,
                p.fecha_pago,
                c.nombre        AS cliente_nombre,
                cr.id           AS credito_id,
                cr.zona,
                cr.frecuencia,
                u.nombre_completo AS cobrador_nombre
            FROM pagos p
            JOIN creditos cr ON p.credito_id = cr.id
            JOIN clientes c  ON cr.cliente_id = c.id
            LEFT JOIN usuarios u ON p.usuario_id = u.id
            WHERE DATE(p.fecha_pago) BETWEEN :desde AND :hasta
            ORDER BY p.id DESC
            LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':desde',  $fecha_desde, PDO::PARAM_STR);
    $stmt->bindValue(':hasta',  $fecha_hasta, PDO::PARAM_STR);
    $stmt->bindValue(':limit',  $limit,       PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset,      PDO::PARAM_INT);
    $stmt->execute();
    $pagos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_db = "Error al cargar el historial: " . $e->getMessage();
}

// Colores por zona
function getZonaColor($zona) {
    $colors = [
        1 => ['bg' => 'bg-blue-900',   'text' => 'text-blue-300',   'border' => 'border-blue-700'],
        2 => ['bg' => 'bg-purple-900', 'text' => 'text-purple-300', 'border' => 'border-purple-700'],
        3 => ['bg' => 'bg-green-900',  'text' => 'text-green-300',  'border' => 'border-green-700'],
        4 => ['bg' => 'bg-yellow-900', 'text' => 'text-yellow-300', 'border' => 'border-yellow-700'],
        5 => ['bg' => 'bg-orange-900', 'text' => 'text-orange-300', 'border' => 'border-orange-700'],
        6 => ['bg' => 'bg-red-900',    'text' => 'text-red-300',    'border' => 'border-red-700'],
    ];
    return $colors[$zona] ?? ['bg' => 'bg-gray-700', 'text' => 'text-gray-300', 'border' => 'border-gray-600'];
}
?>

<!-- ===================== ENCABEZADO ===================== -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div class="flex items-center gap-3">
        <div class="bg-indigo-600 p-3 rounded-xl shadow-lg">
            <i class="fas fa-history text-white text-xl"></i>
        </div>
        <div>
            <h2 class="text-2xl font-bold text-gray-100">Log de Actividad</h2>
            <p class="text-sm text-gray-400">Historial de pagos registrados en el sistema</p>
        </div>
    </div>
</div>

<?php if (!empty($error_db)): ?>
    <div class="bg-red-900 border border-red-700 text-red-200 px-4 py-3 rounded-md mb-4 flex items-center">
        <i class="fas fa-exclamation-triangle mr-3 text-xl"></i>
        <p><?= htmlspecialchars($error_db) ?></p>
    </div>
<?php endif; ?>

<!-- ===================== FILTROS ===================== -->
<form method="GET" action="index.php" class="bg-gray-800 border border-gray-700 rounded-xl p-4 mb-6 no-print">
    <input type="hidden" name="page" value="log_pagos">
    <div class="flex flex-col sm:flex-row gap-4 items-end">
        <div class="flex-1">
            <label class="block text-xs font-medium text-gray-400 mb-1 uppercase tracking-wide">
                <i class="fas fa-calendar-alt mr-1"></i>Desde
            </label>
            <input type="date" name="fecha_desde" value="<?= htmlspecialchars($fecha_desde) ?>"
                   class="w-full rounded-lg form-element-dark border border-gray-600 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div class="flex-1">
            <label class="block text-xs font-medium text-gray-400 mb-1 uppercase tracking-wide">
                <i class="fas fa-calendar-alt mr-1"></i>Hasta
            </label>
            <input type="date" name="fecha_hasta" value="<?= htmlspecialchars($fecha_hasta) ?>"
                   class="w-full rounded-lg form-element-dark border border-gray-600 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-400 mb-1 uppercase tracking-wide">Mostrar</label>
            <select name="limit" class="rounded-lg form-element-dark border border-gray-600 px-3 py-2 text-sm">
                <?php foreach ($limit_options as $opt): ?>
                    <option value="<?= $opt ?>" <?= $limit == $opt ? 'selected' : '' ?>><?= $opt ?> registros</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2">
                <i class="fas fa-filter"></i>Filtrar
            </button>
            <a href="index.php?page=log_pagos"
               class="bg-gray-700 hover:bg-gray-600 text-gray-200 px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2">
                <i class="fas fa-undo"></i>
            </a>
        </div>
    </div>
</form>

<!-- ===================== TARJETAS RESUMEN ===================== -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <!-- Total pagos -->
    <div class="bg-gray-800 border border-gray-700 rounded-xl p-4 flex items-center gap-4">
        <div class="bg-green-900 p-3 rounded-lg">
            <i class="fas fa-money-bill-wave text-green-400 text-xl"></i>
        </div>
        <div>
            <p class="text-xs text-gray-400 uppercase tracking-wide">Total Recaudado</p>
            <p class="text-2xl font-bold text-green-400">$<?= number_format($total_monto, 0, ',', '.') ?></p>
        </div>
    </div>
    <!-- Cantidad de pagos -->
    <div class="bg-gray-800 border border-gray-700 rounded-xl p-4 flex items-center gap-4">
        <div class="bg-indigo-900 p-3 rounded-lg">
            <i class="fas fa-receipt text-indigo-400 text-xl"></i>
        </div>
        <div>
            <p class="text-xs text-gray-400 uppercase tracking-wide">Pagos Registrados</p>
            <p class="text-2xl font-bold text-indigo-400"><?= number_format($total_records) ?></p>
        </div>
    </div>
    <!-- Promedio -->
    <div class="bg-gray-800 border border-gray-700 rounded-xl p-4 flex items-center gap-4">
        <div class="bg-blue-900 p-3 rounded-lg">
            <i class="fas fa-chart-bar text-blue-400 text-xl"></i>
        </div>
        <div>
            <p class="text-xs text-gray-400 uppercase tracking-wide">Promedio por Pago</p>
            <p class="text-2xl font-bold text-blue-400">
                $<?= $total_records > 0 ? number_format($total_monto / $total_records, 0, ',', '.') : '0' ?>
            </p>
        </div>
    </div>
</div>

<!-- ===================== TABLA DE PAGOS ===================== -->
<?php if (empty($pagos) && empty($error_db)): ?>
    <div class="bg-gray-800 border border-gray-700 rounded-xl p-12 text-center">
        <i class="fas fa-inbox text-4xl text-gray-600 mb-3 block"></i>
        <p class="text-gray-400 text-lg">No hay pagos registrados en este período.</p>
        <p class="text-gray-500 text-sm mt-1">Ajustá el rango de fechas para ver más resultados.</p>
    </div>
<?php else: ?>

<!-- Contador -->
<div class="flex justify-between items-center mb-3 text-sm text-gray-400">
    <span>
        Mostrando <?= $offset + 1 ?> – <?= min($offset + $limit, $total_records) ?> de <?= $total_records ?> pagos
    </span>
    <span class="text-xs text-gray-500">
        Período: <?= date('d/m/Y', strtotime($fecha_desde)) ?> al <?= date('d/m/Y', strtotime($fecha_hasta)) ?>
    </span>
</div>

<div class="space-y-2">
    <?php 
    $fecha_anterior = null;
    foreach ($pagos as $pago): 
        $fecha_pago = date('Y-m-d', strtotime($pago['fecha_pago']));
        $zona_color = getZonaColor($pago['zona']);
        $nombre_zona = $NOMBRES_ZONAS[$pago['zona']] ?? 'N/A';
        
        // Separador de fecha
        if ($fecha_pago !== $fecha_anterior):
            $fecha_anterior = $fecha_pago;
            $es_hoy = ($fecha_pago === date('Y-m-d'));
            $es_ayer = ($fecha_pago === date('Y-m-d', strtotime('-1 day')));
            $label_fecha = $es_hoy ? '📅 Hoy' : ($es_ayer ? '📅 Ayer' : '📅 ' . date('d \d\e F Y', strtotime($fecha_pago)));
    ?>
        <div class="flex items-center gap-3 mt-5 mb-2 first:mt-0">
            <span class="text-xs font-semibold text-gray-400 uppercase tracking-widest whitespace-nowrap"><?= $label_fecha ?></span>
            <div class="flex-1 border-t border-gray-700"></div>
        </div>
    <?php endif; ?>

        <!-- Fila de pago -->
        <div class="bg-gray-800 border border-gray-700 hover:border-gray-500 rounded-xl px-4 py-3 flex items-center gap-4 transition-all duration-150 group">
            
            <!-- Ícono de pago -->
            <div class="flex-shrink-0">
                <div class="w-10 h-10 rounded-full bg-green-900 border border-green-700 flex items-center justify-center shadow-sm">
                    <i class="fas fa-check text-green-400 text-sm"></i>
                </div>
            </div>

            <!-- Info del cliente -->
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <a href="index.php?page=editar_cliente&id=<?= $pago['credito_id'] ?>"
                       class="font-semibold text-white hover:text-indigo-400 transition-colors truncate">
                        <?= htmlspecialchars($pago['cliente_nombre']) ?>
                    </a>
                    <span class="<?= $zona_color['bg'] ?> <?= $zona_color['text'] ?> border <?= $zona_color['border'] ?> text-xs px-2 py-0.5 rounded-full font-medium flex-shrink-0">
                        <?= htmlspecialchars($nombre_zona) ?>
                    </span>
                    <span class="bg-gray-700 text-gray-400 text-xs px-2 py-0.5 rounded-full flex-shrink-0">
                        <?= htmlspecialchars($pago['frecuencia']) ?>
                    </span>
                </div>
                <div class="flex items-center gap-3 mt-1 text-xs text-gray-500">
                    <?php if (!empty($pago['cobrador_nombre'])): ?>
                        <span><i class="fas fa-user-tie mr-1"></i><?= htmlspecialchars($pago['cobrador_nombre']) ?></span>
                    <?php endif; ?>

                    <span class="text-gray-600">#<?= $pago['pago_id'] ?></span>
                </div>
            </div>

            <!-- Monto -->
            <div class="flex-shrink-0 text-right">
                <p class="text-xl font-bold text-green-400">$<?= number_format($pago['monto_pagado'], 0, ',', '.') ?></p>
                <p class="text-xs text-gray-500"><?= date('d/m/Y', strtotime($pago['fecha_pago'])) ?></p>
            </div>

            <!-- Ver detalle -->
            <div class="flex-shrink-0 no-print">
                <a href="index.php?page=editar_cliente&id=<?= $pago['credito_id'] ?>"
                   class="text-gray-600 hover:text-indigo-400 transition-colors opacity-0 group-hover:opacity-100"
                   title="Ver detalle del crédito">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </div>
        </div>

    <?php endforeach; ?>
</div>

<!-- Paginación -->
<?php if ($total_pages > 1): ?>
<div class="mt-6 flex justify-center no-print">
    <?= generarPaginacion($page_num, $total_pages, 2) ?>
</div>
<?php endif; ?>

<?php endif; ?>
