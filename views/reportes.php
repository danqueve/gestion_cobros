<?php
// --- LÓGICA DE LA VISTA DE REPORTES ---

$zona_seleccionada = $_GET['zona'] ?? 2;
// Se elimina el Domingo de la lista de días.
$dias_semana_es = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

// --- MANEJO DEL RANGO DE FECHAS ---
// Si el usuario selecciona fechas, las usamos. Si no, usamos la semana actual.
if (!empty($_GET['fecha_desde']) && !empty($_GET['fecha_hasta'])) {
    $start_date = $_GET['fecha_desde'];
    $end_date = $_GET['fecha_hasta'];
} else {
    // Por defecto, se usa la semana actual (de Lunes a Sábado)
    $today = new DateTime();
    $start_date = (clone $today)->modify('monday this week')->format('Y-m-d');
    $end_date = (clone $today)->modify('saturday this week')->format('Y-m-d');
}

try {
    // 1. Obtener la Cobranza Estimada por día de la semana
    $sql_estimada = "SELECT dia_pago, SUM(monto_cuota) as estimado 
                     FROM creditos 
                     WHERE zona = :zona AND estado = 'Activo' 
                     GROUP BY dia_pago";
    $stmt_estimada = $pdo->prepare($sql_estimada);
    $stmt_estimada->execute(['zona' => $zona_seleccionada]);
    $estimados_raw = $stmt_estimada->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // CORRECCIÓN PARA DÍAS CON CARACTERES ESPECIALES:
    if (isset($estimados_raw['Miercoles'])) {
        $estimados_raw['Miércoles'] = $estimados_raw['Miercoles'];
        unset($estimados_raw['Miercoles']);
    }
    if (isset($estimados_raw['Sabado'])) {
        $estimados_raw['Sábado'] = $estimados_raw['Sabado'];
        unset($estimados_raw['Sabado']);
    }


    // 2. Obtener la Cobranza Realizada en el rango de fechas seleccionado
    $sql_realizada = "SELECT DATE(p.fecha_pago) as fecha, SUM(p.monto_pagado) as realizado 
                      FROM pagos p 
                      JOIN creditos cr ON p.credito_id = cr.id 
                      WHERE cr.zona = :zona AND p.fecha_pago BETWEEN :start AND :end 
                      GROUP BY fecha";
    $stmt_realizada = $pdo->prepare($sql_realizada);
    $stmt_realizada->execute(['zona' => $zona_seleccionada, 'start' => $start_date, 'end' => $end_date]);
    $realizados_raw = $stmt_realizada->fetchAll(PDO::FETCH_KEY_PAIR);

} catch (PDOException $e) {
    die("Error al generar el reporte: " . $e->getMessage());
}

// 3. Unificar los datos para mostrarlos en la tabla
$reporte_data = [];
$total_estimado = 0;
$total_realizado = 0;

$period = new DatePeriod(
    new DateTime($start_date),
    new DateInterval('P1D'),
    (new DateTime($end_date))->modify('+1 day')
);

foreach ($period as $date) {
    $fecha_actual = $date->format('Y-m-d');
    $dia_nombre_indice = $date->format('N') - 1;
    
    if ($dia_nombre_indice < 6) {
        $dia_nombre = $dias_semana_es[$dia_nombre_indice];
        
        $estimado_dia = $estimados_raw[$dia_nombre] ?? 0;
        $realizado_dia = $realizados_raw[$fecha_actual] ?? 0;

        $reporte_data[$fecha_actual] = [
            'dia_nombre' => $dia_nombre,
            'estimado' => $estimado_dia,
            'realizado' => $realizado_dia
        ];
        $total_estimado += $estimado_dia;
        $total_realizado += $realizado_dia;
    }
}
?>

<!-- ESTILOS ESPECÍFICOS PARA LA IMPRESIÓN DEL REPORTE (CORREGIDOS) -->
<style>
    @media print {
        /* Ocultar los elementos que no queremos imprimir */
        .no-print {
            display: none !important;
        }

        /* Resetear estilos del body para la impresión */
        body {
            background-color: white !important;
            color: black !important;
            padding: 20px !important;
            margin: 0 !important;
        }

        /* Asegurar que el área de impresión se vea bien */
        .report-print-area {
            box-shadow: none !important;
            border: none !important;
        }
        
        .report-container {
            background-color: white !important;
            border: none !important;
            padding: 0 !important;
            color: black !important;
        }

        /* Estilos para el título del reporte */
        .report-title {
            color: black !important;
            text-align: center;
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        /* Estilos para la tabla en la impresión */
        table, thead, tbody, tfoot, tr, th, td {
            color: black !important;
            border: 1px solid #ccc !important;
        }
        th {
            background-color: #f2f2f2 !important;
        }
        tfoot {
            background-color: #e8e8e8 !important;
        }
        .text-green-400 {
            color: black !important; /* Mostramos los totales en negro */
        }
    }
</style>

<div class="report-print-area">
    <h2 class="text-2xl font-bold text-gray-200 mb-4 report-title">Reporte de Cobranza por Fechas</h2>

    <div class="bg-gray-800 p-6 rounded-lg border border-gray-700 report-container">
        <form method="GET" action="index.php" class="flex flex-col sm:flex-row justify-between items-center mb-4 no-print">
            <div class="flex flex-wrap items-center gap-4">
                <input type="hidden" name="page" value="reportes">
                <div>
                    <label for="zona" class="font-medium text-gray-300">Zona:</label>
                    <select id="zona" name="zona" class="rounded-md form-element-dark">
                        <?php for ($i = 1; $i <= 4; $i++): ?>
                            <option value="<?= $i ?>" <?= $zona_seleccionada == $i ? 'selected' : '' ?>>Zona <?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label for="fecha_desde" class="font-medium text-gray-300">Desde:</label>
                    <input type="date" id="fecha_desde" name="fecha_desde" value="<?= htmlspecialchars($start_date) ?>" class="rounded-md form-element-dark">
                </div>
                <div>
                    <label for="fecha_hasta" class="font-medium text-gray-300">Hasta:</label>
                    <input type="date" id="fecha_hasta" name="fecha_hasta" value="<?= htmlspecialchars($end_date) ?>" class="rounded-md form-element-dark">
                </div>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-3 rounded-md">
                    <i class="fas fa-filter"></i> Filtrar
                </button>
            </div>
            <button type="button" onclick="window.print()" class="mt-4 sm:mt-0 bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-md">
                <i class="fas fa-print mr-2"></i>Imprimir Reporte
            </button>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-700">
                <thead class="table-header-custom">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Fecha</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Día</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-300 uppercase tracking-wider">Cobranza Estimada</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-300 uppercase tracking-wider">Cobranza Realizada</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                <?php if (empty($reporte_data)): ?>
                    <tr><td colspan="4" class="text-center py-10 text-gray-400">No hay datos para el rango de fechas seleccionado.</td></tr>
                <?php else: ?>
                    <?php foreach ($reporte_data as $fecha => $data): ?>
                        <tr class="table-row-dark">
                            <td class="px-6 py-4 whitespace-nowrap text-gray-300"><?= (new DateTime($fecha))->format('d/m/Y') ?></td>
                            <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-100"><?= $data['dia_nombre'] ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-gray-300"><?= formatCurrency($data['estimado']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-right font-semibold <?= $data['realizado'] > 0 ? 'text-green-400' : 'text-gray-300' ?>">
                                <?= formatCurrency($data['realizado']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
                <tfoot class="bg-gray-700">
                    <tr>
                        <td colspan="2" class="px-6 py-3 text-left text-sm font-bold text-gray-100 uppercase">Totales del Período</td>
                        <td class="px-6 py-3 text-right text-sm font-bold text-gray-100"><?= formatCurrency($total_estimado) ?></td>
                        <td class="px-6 py-3 text-right text-sm font-bold text-green-400"><?= formatCurrency($total_realizado) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
