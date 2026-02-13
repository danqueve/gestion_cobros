<?php
// Se corrige la ruta para incluir el archivo principal de configuración.
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

// Obtenemos la zona y el día de los parámetros de la URL.
$zona_seleccionada = htmlspecialchars($_GET['zona'] ?? 0);
$dia_seleccionado = htmlspecialchars($_GET['dia'] ?? '');

// --- CONSTRUCCIÓN DINÁMICA DE LA CONSULTA (CORREGIDA) ---
$sql = "SELECT c.nombre, c.direccion, c.telefono, cr.*
        FROM creditos cr 
        JOIN clientes c ON cr.cliente_id = c.id 
        WHERE cr.zona = ? AND cr.estado = 'Activo'";
$params = [$zona_seleccionada];

// --- CAMBIO APLICADO AQUÍ ---
// Antes decía: if ($zona_seleccionada != 4)
// Ahora excluimos 4, 5 y 6 del filtro por día para que traiga todo.
if (!in_array($zona_seleccionada, [4, 5, 6])) {
    // CORRECCIÓN: Se incluyen clientes Semanales del día Y todos los Quincenales/Mensuales.
    $sql .= " AND ( (cr.frecuencia = 'Semanal' AND cr.dia_pago = ?) OR (cr.frecuencia IN ('Quincenal', 'Mensual')) )";
    $params[] = $dia_seleccionado;
}

$sql .= " ORDER BY cr.frecuencia, c.nombre ASC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $clientes_filtrados = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error al generar la planilla: " . $e->getMessage());
}

// --- PROCESAMIENTO DE DATOS PARA AGRUPAR POR FRECUENCIA ---
$clientes_por_frecuencia = [
    'Semanal' => [],
    'Quincenal' => [],
    'Mensual' => []
];
$totales_por_frecuencia = [
    'Semanal' => 0,
    'Quincenal' => 0,
    'Mensual' => 0
];
$total_general = 0;

foreach ($clientes_filtrados as $cliente) {
    $frecuencia = $cliente['frecuencia'];
    if (array_key_exists($frecuencia, $clientes_por_frecuencia)) {
        // Verificamos si el cliente realmente tiene una cuota pendiente.
        $stmt_saldo = $pdo->prepare("SELECT COUNT(id) FROM cronograma_cuotas WHERE credito_id = ? AND estado IN ('Pendiente', 'Pago Parcial')");
        $stmt_saldo->execute([$cliente['id']]);
        
        if ($stmt_saldo->fetchColumn() > 0) {
             $clientes_por_frecuencia[$frecuencia][] = $cliente;
             // Se suma el monto completo de la cuota para los totales.
             $totales_por_frecuencia[$frecuencia] += $cliente['monto_cuota'];
             $total_general += $cliente['monto_cuota'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Planilla de Cobro - <?= htmlspecialchars($nombres_zonas[$zona_seleccionada] ?? 'Zona Desconocida') ?></title>
    <!-- Estilos CSS optimizados para impresión -->
    <style>
        body { font-family: Arial, sans-serif; font-size: 9px; margin: 15px; }
        table { width: 100%; border-collapse: collapse; margin-top: 5px; margin-bottom: 20px; }
        th, td { border: 1px solid #000; padding: 3px; text-align: left; word-wrap: break-word; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .header { text-align: center; margin-bottom: 15px; }
        h1 { margin: 0; font-size: 18px; }
        h2 { margin: 3px 0; font-size: 14px; font-weight: normal; }
        h3 { margin-top: 20px; font-size: 12px; border-bottom: 2px solid #000; padding-bottom: 3px; }
        .footer-row td { background-color: #e0e0e0; font-weight: bold; }
        .summary { margin-top: 25px; padding-top: 8px; border-top: 3px double #000; text-align: right; }
        .summary p { font-size: 14px; font-weight: bold; margin: 0; }
        @media print {
            body { print-color-adjust: exact; -webkit-print-color-adjust: exact; margin: 10mm; }
        }
    </style>
</head>
<!-- El evento onload ejecuta el diálogo de impresión automáticamente -->
<body onload="window.print()">
    <div class="header">
        <h1>Planilla de Cobro</h1>
        <h2>
            Zona: <?= htmlspecialchars($nombres_zonas[$zona_seleccionada] ?? 'Desconocida') ?>
            <?php 
            // --- CAMBIO APLICADO AQUÍ TAMBIÉN ---
            // Solo mostrar el día si NO es zona 4, 5 o 6
            if (!in_array($zona_seleccionada, [4, 5, 6])): 
            ?>
                - Día: <?= $dia_seleccionado ?>
            <?php endif; ?>
        </h2>
    </div>

    <?php $global_counter = 1; ?>
    <?php foreach ($clientes_por_frecuencia as $frecuencia => $clientes): ?>
        <?php if (!empty($clientes)): ?>
            <h3>Cobros <?= htmlspecialchars($frecuencia) ?></h3>
            <table>
                <thead>
                    <tr>
                        <th style="width: 5%;">#</th>
                        <th style="width: 22%;">Cliente</th>
                        <th style="width: 22%;">Dirección</th>
                        <th style="width: 13%;">Celular</th>
                        <th style="width: 8%;">Cuotas</th>
                        <th style="width: 8%;">Venc.</th>
                        <th style="width: 12%;">Monto Cuota</th>
                        <th style="width: 10%;">Días Atraso</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clientes as $cliente): ?>
                        <?php
                        $dias_atraso_display = 0;
                        $estado_atraso_display = 'Al día';
                        $proximo_vencimiento_str = null;

                        $stmt_vencimiento = $pdo->prepare("SELECT fecha_vencimiento FROM cronograma_cuotas WHERE credito_id = :credito_id AND estado IN ('Pendiente', 'Pago Parcial') ORDER BY numero_cuota ASC LIMIT 1");
                        $stmt_vencimiento->execute([':credito_id' => $cliente['id']]);
                        $proximo_vencimiento_str = $stmt_vencimiento->fetchColumn();

                        if ($proximo_vencimiento_str) {
                            $hoy = new DateTime();
                            $proximo_vencimiento = new DateTime($proximo_vencimiento_str);

                            if ($hoy > $proximo_vencimiento) {
                                $diferencia = $hoy->diff($proximo_vencimiento);
                                $dias_atraso_display = $diferencia->days;
                                $estado_atraso_display = 'Atrasado';
                            }
                        }
                        ?>
                        <tr>
                            <td style="text-align: center;"><?= $global_counter++ ?></td>
                            <td><?= htmlspecialchars($cliente['nombre']) ?></td>
                            <td><?= htmlspecialchars($cliente['direccion'] ?? '') ?></td>
                            <td><?= htmlspecialchars($cliente['telefono'] ?? '') ?></td>
                            <td style="text-align: center;"><?= $cliente['cuotas_pagadas'] ?> / <?= $cliente['total_cuotas'] ?></td>
                            <td style="text-align: center;"><?= $proximo_vencimiento_str ? (new DateTime($proximo_vencimiento_str))->format('d/m') : 'N/A' ?></td>
                            <td style="text-align: right;"><?= formatCurrency($cliente['monto_cuota']) ?></td>
                            <td style="text-align: center; font-weight: bold;"><?= $estado_atraso_display == 'Atrasado' ? $dias_atraso_display : '-' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="footer-row">
                        <td colspan="6" style="text-align: right;">Subtotal <?= htmlspecialchars($frecuencia) ?>:</td>
                        <td colspan="2" style="text-align: right;"><?= formatCurrency($totales_por_frecuencia[$frecuencia]) ?></td>
                    </tr>
                </tfoot>
            </table>
        <?php endif; ?>
    <?php endforeach; ?>

    <?php if ($total_general > 0): ?>
        <div class="summary">
            <p>TOTAL GENERAL A COBRAR: <?= formatCurrency($total_general) ?></p>
        </div>
    <?php endif; ?>

    <?php if (empty($clientes_filtrados)): ?>
        <p style="text-align: center;">No hay clientes activos para esta ruta.</p>
    <?php endif; ?>

</body>
</html>