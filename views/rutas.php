<?php
// --- LÓGICA DE LA VISTA DE RUTAS CON LÓGICA DE PAGO AVANZADA (ACTUALIZADA) ---

$error = '';
$success = '';

// --- MAPA DE NOMBRES PARA LAS ZONAS ---
$nombres_zonas = [
    1 => 'Santi',
    2 => 'Juan Pablo',
    3 => 'Enzo',
    4 => 'Tafi del V',
    5 => 'Famailla',
    6 => 'Sgo'
];

// --- PROCESAR REGISTRO DE PAGO ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['registrar_pago'])) {
    
    $credito_id = $_POST['credito_id'];
    $fecha_pago_str = $_POST['fecha_pago'] ?? date('Y-m-d');
    if(empty($fecha_pago_str)) $fecha_pago_str = date('Y-m-d');
    $monto_cobrado = filter_input(INPUT_POST, 'monto_cobrado', FILTER_VALIDATE_FLOAT);

    if (empty($monto_cobrado) || $monto_cobrado <= 0) {
        $error = "Debe ingresar un monto válido para registrar el pago.";
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Registrar el pago en la tabla 'pagos'
            $sql_pago = "INSERT INTO pagos (credito_id, usuario_id, monto_pagado, fecha_pago) VALUES (?, ?, ?, ?)";
            $stmt_pago = $pdo->prepare($sql_pago);
            $stmt_pago->execute([$credito_id, $_SESSION['user_id'], $monto_cobrado, $fecha_pago_str]);
            
            // 2. Aplicar el monto cobrado a las cuotas del cronograma
            $monto_restante_pago = $monto_cobrado;

            $sql_cuotas = "SELECT * FROM cronograma_cuotas WHERE credito_id = ? AND estado IN ('Pendiente', 'Pago Parcial') ORDER BY numero_cuota ASC";
            $stmt_cuotas = $pdo->prepare($sql_cuotas);
            $stmt_cuotas->execute([$credito_id]);

            while ($monto_restante_pago > 0 && ($cuota = $stmt_cuotas->fetch(PDO::FETCH_ASSOC))) {
                $faltante_cuota = $cuota['monto_cuota'] - $cuota['monto_pagado'];

                if (bccomp($monto_restante_pago, $faltante_cuota, 2) >= 0) {
                    $monto_a_imputar = $faltante_cuota;
                    $nuevo_estado_cuota = 'Pagado';
                } else {
                    $monto_a_imputar = $monto_restante_pago;
                    $nuevo_estado_cuota = 'Pago Parcial';
                }

                $sql_update_cuota = "UPDATE cronograma_cuotas SET monto_pagado = monto_pagado + ?, estado = ? WHERE id = ?";
                $stmt_update_cuota = $pdo->prepare($sql_update_cuota);
                $stmt_update_cuota->execute([$monto_a_imputar, $nuevo_estado_cuota, $cuota['id']]);

                $monto_restante_pago -= $monto_a_imputar;
            }

            // 3. Recalcular y actualizar el estado general del crédito principal
            $sql_count_pagadas = "SELECT COUNT(id) FROM cronograma_cuotas WHERE credito_id = ? AND estado = 'Pagado'";
            $stmt_count_pagadas = $pdo->prepare($sql_count_pagadas);
            $stmt_count_pagadas->execute([$credito_id]);
            $total_cuotas_pagadas = $stmt_count_pagadas->fetchColumn();
            
            $stmt_total = $pdo->prepare("SELECT total_cuotas FROM creditos WHERE id = ?");
            $stmt_total->execute([$credito_id]);
            $total_cuotas_credito = $stmt_total->fetchColumn();

            $nuevo_estado_credito = ($total_cuotas_pagadas >= $total_cuotas_credito) ? 'Pagado' : 'Activo';

            $sql_update_credito = "UPDATE creditos SET cuotas_pagadas = ?, ultimo_pago = ?, estado = ? WHERE id = ?";
            $stmt_update_credito = $pdo->prepare($sql_update_credito);
            $stmt_update_credito->execute([$total_cuotas_pagadas, $fecha_pago_str, $nuevo_estado_credito, $credito_id]);

            $pdo->commit();
            $success = "¡Pago Cargado y aplicado al cronograma!";

        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Error al registrar el pago: " . $e->getMessage();
        }
    }
}


// --- LÓGICA DE FILTRADO (ACTUALIZADA) ---
$zona_seleccionada = $_GET['zona'] ?? 1; // Zona 1 por defecto
$dia_seleccionado = $_GET['dia'] ?? 'Lunes';
$dias_semana = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

// --- CONSTRUCCIÓN DINÁMICA DE LA CONSULTA (CORREGIDA) ---
// AHORA FILTRA SOLO POR cr.estado = 'Activo'
$sql = "SELECT c.nombre, cr.* FROM creditos cr 
        JOIN clientes c ON cr.cliente_id = c.id 
        WHERE cr.zona = ? AND cr.estado = 'Activo'"; 
$params = [$zona_seleccionada];

// --- CAMBIO REALIZADO AQUÍ ---
// Si la zona NO es 4, 5 ni 6, se aplica el filtro por día.
// Usamos !in_array para verificar si la zona actual NO está en la lista de excepciones.
if (!in_array($zona_seleccionada, [4, 5, 6])) {
    // Muestra los clientes Semanales del día, y TODOS los Quincenales/Mensuales.
    $sql .= " AND ( (cr.frecuencia = 'Semanal' AND cr.dia_pago = ?) OR (cr.frecuencia IN ('Quincenal', 'Mensual')) )";
    $params[] = $dia_seleccionado;
}

$sql .= " ORDER BY c.nombre ASC"; 

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clientes_filtrados = $stmt->fetchAll(PDO::FETCH_ASSOC);

$cobranza_estimada = 0;
foreach ($clientes_filtrados as $cliente) {
    // La cobranza estimada ya solo incluirá clientes activos gracias al filtro SQL
    $stmt_prox_cuota = $pdo->prepare("SELECT (monto_cuota - monto_pagado) as saldo FROM cronograma_cuotas WHERE credito_id = ? AND estado IN ('Pendiente', 'Pago Parcial') ORDER BY numero_cuota ASC LIMIT 1");
    $stmt_prox_cuota->execute([$cliente['id']]);
    $saldo_cuota = $stmt_prox_cuota->fetchColumn();
    $cobranza_estimada += $saldo_cuota ?: $cliente['monto_cuota'];
}
?>

<!-- MENSAJES DE ÉXITO O ERROR -->
<?php if(!empty($error)): ?>
    <div class="bg-red-900 border border-red-700 text-red-200 px-4 py-3 rounded-md mb-4" role="alert"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- FORMULARIO DE FILTROS Y BOTÓN DE IMPRESIÓN -->
<div class="bg-gray-800 p-4 rounded-lg shadow-md mb-6 border border-gray-700 flex flex-col sm:flex-row justify-between items-center gap-4 no-print">
    <form method="GET" action="index.php" class="flex flex-col sm:flex-row items-center gap-4">
        <input type="hidden" name="page" value="rutas">
        <div>
            <label for="zona" class="block text-sm font-medium text-gray-300">Zona:</label>
            <select id="zona" name="zona" class="mt-1 block w-full pl-3 pr-10 py-2 text-base rounded-md form-element-dark">
                <?php foreach($nombres_zonas as $num => $nombre): ?>
                    <option value="<?= $num ?>" <?= $zona_seleccionada == $num ? 'selected' : '' ?>><?= htmlspecialchars($nombre) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <?php 
        // Ocultar selector de día también para zonas 4, 5 y 6
        if(!in_array($zona_seleccionada, [4, 5, 6])): 
        ?>
        <div>
            <label for="dia" class="block text-sm font-medium text-gray-300">Día:</label>
            <select id="dia" name="dia" class="mt-1 block w-full pl-3 pr-10 py-2 text-base rounded-md form-element-dark">
                <?php foreach ($dias_semana as $d): ?>
                    <option value="<?= $d ?>" <?= $dia_seleccionado == $d ? 'selected' : '' ?>><?= $d ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <button type="submit" class="w-full sm:w-auto mt-4 sm:mt-0 self-end bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md"><i class="fas fa-filter"></i> Filtrar</button>
    </form>
    
    <?php 
        $print_link = "views/imprimir_planilla.php?zona=" . $zona_seleccionada;
        // Solo agregar el parámetro día si NO es zona 4, 5 o 6
        if (!in_array($zona_seleccionada, [4, 5, 6])) {
            $print_link .= "&dia=" . urlencode($dia_seleccionado);
        }
    ?>
    <a href="<?= $print_link ?>" target="_blank" class="w-full sm:w-auto bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-md text-center">
        <i class="fas fa-print mr-2"></i>Imprimir Planilla
    </a>
</div>


<!-- TARJETAS DE RESUMEN -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8 no-print">
    <div class="summary-card"><h3 class="text-lg font-medium text-gray-400">Cobranza Estimada</h3><p id="total-estimado" data-valor="<?= $cobranza_estimada ?>" class="text-3xl font-bold text-gray-100 mt-2">$0</p></div>
    <div class="summary-card"><h3 class="text-lg font-medium text-gray-400">Total Cobrado</h3><p id="total-cobrado" class="text-3xl font-bold text-green-400 mt-2">$0</p></div>
    <div class="summary-card"><h3 class="text-lg font-medium text-gray-400">Faltante</h3><p id="total-faltante" class="text-3xl font-bold text-red-400 mt-2">$0</p></div>
</div>

<!-- TABLA DE COBROS -->
<div class="overflow-x-auto rounded-lg shadow border border-gray-700">
    <table class="min-w-full divide-y divide-gray-700">
        <thead class="table-header-custom">
             <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Cliente</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-300 uppercase tracking-wider">Cuotas</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-300 uppercase tracking-wider">Venc. Cuota</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-300 uppercase tracking-wider">Saldo Cuota</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-300 uppercase tracking-wider">Saldo Total</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-300 uppercase tracking-wider">Cuota</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-300 uppercase tracking-wider">Días Atraso</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-300 uppercase tracking-wider no-print">Acciones de Cobro</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-700">
            <?php if (empty($clientes_filtrados)): ?>
                <tr><td colspan="8" class="px-6 py-12 text-center text-gray-400 table-row-dark"><i class="fas fa-folder-open fa-3x mb-3"></i><p>No hay clientes para esta ruta.</p></td></tr>
            <?php else: ?>
                <?php foreach ($clientes_filtrados as $cliente): ?>
                    <?php
                    // La lógica de cálculo de saldos y atraso sigue igual
                    $dias_atraso_display = 0;
                    $estado_atraso_display = 'Al día'; // Por defecto, si no debe nada o está pagado
                    $saldo_pendiente_cuota = 0;
                    $saldo_total_credito = 0;
                    $proximo_vencimiento_str = null;

                    // Solo recalculamos si el estado es 'Activo'
                    // (Aunque ya filtramos por 'Activo' en el SQL principal, mantenemos la robustez)
                    if ($cliente['estado'] == 'Activo') {
                        $stmt_saldos = $pdo->prepare("
                            SELECT (monto_cuota - monto_pagado) as saldo_cuota_actual,
                                   (SELECT SUM(monto_cuota - monto_pagado) FROM cronograma_cuotas WHERE credito_id = :id1 AND estado IN ('Pendiente', 'Pago Parcial')) as saldo_total,
                                   fecha_vencimiento
                            FROM cronograma_cuotas 
                            WHERE credito_id = :id2 AND estado IN ('Pendiente', 'Pago Parcial') 
                            ORDER BY numero_cuota ASC LIMIT 1");
                        $stmt_saldos->execute([':id1' => $cliente['id'], ':id2' => $cliente['id']]);
                        $saldos_y_vencimiento = $stmt_saldos->fetch(PDO::FETCH_ASSOC);

                        $saldo_pendiente_cuota = $saldos_y_vencimiento['saldo_cuota_actual'] ?? 0;
                        $saldo_total_credito = $saldos_y_vencimiento['saldo_total'] ?? 0;
                        $proximo_vencimiento_str = $saldos_y_vencimiento['fecha_vencimiento'] ?? null;

                        if ($proximo_vencimiento_str) {
                            $hoy = new DateTime();
                            $proximo_vencimiento = new DateTime($proximo_vencimiento_str);
                            if ($hoy > $proximo_vencimiento) {
                                $diferencia = $hoy->diff($proximo_vencimiento);
                                $dias_atraso_display = $diferencia->days;
                                $estado_atraso_display = 'Atrasado';
                            }
                        }
                    }
                    ?>
                    <tr class="table-row-dark">
                        <td class="px-4 py-4 whitespace-nowrap">
                            <a href="index.php?page=editar_cliente&id=<?= $cliente['id'] ?>" class="font-medium text-blue-400 hover:underline">
                                <?= htmlspecialchars($cliente['nombre']) ?>
                            </a>
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap text-center text-gray-300"><?= $cliente['cuotas_pagadas'] ?> / <?= $cliente['total_cuotas'] ?></td>
                        <td class="px-4 py-4 whitespace-nowrap text-center text-gray-300 font-semibold"><?= $proximo_vencimiento_str ? (new DateTime($proximo_vencimiento_str))->format('d/m/Y') : 'N/A' ?></td>
                        <td class="px-4 py-4 whitespace-nowrap text-right font-semibold text-yellow-400"><?= formatCurrency($saldo_pendiente_cuota) ?></td>
                        <td class="px-4 py-4 whitespace-nowrap text-right font-semibold text-blue-400"><?= formatCurrency($saldo_total_credito) ?></td>
                        <td class="px-4 py-4 whitespace-nowrap text-right text-gray-300"><?= formatCurrency($cliente['monto_cuota']) ?></td>
                        <td class="px-4 py-4 whitespace-nowrap text-center">
                            <?php if ($estado_atraso_display == 'Atrasado'): ?><span class="late-payment"><?= $dias_atraso_display ?> días</span>
                            <?php else: ?><span class="on-time">Al día</span><?php endif; ?>
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap no-print">
                            <form action="index.php?page=rutas&zona=<?= $zona_seleccionada ?><?= (!in_array($zona_seleccionada, [4, 5, 6]) ? '&dia='.urlencode($dia_seleccionado) : '') ?>" method="POST" class="flex items-center gap-2 justify-center">
                                <input type="hidden" name="credito_id" value="<?= $cliente['id'] ?>">
                                <input type="date" name="fecha_pago" class="w-32 rounded-md shadow-sm form-element-dark" title="Fecha de Pago" value="<?= date('Y-m-d') ?>">
                                <input type="number" step="0.01" name="monto_cobrado" class="monto-cobrado-input w-32 rounded-md shadow-sm form-element-dark" placeholder="<?= number_format($saldo_pendiente_cuota, 2, '.', '') ?>" autocomplete="off">
                                <button type="submit" name="registrar_pago" class="bg-green-600 hover:bg-green-700 text-white font-bold py-1 px-3 rounded-md text-sm transition duration-300">
                                    <i class="fas fa-check"></i> Registrar
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- POPUP DE NOTIFICACIÓN DE ÉXITO -->
<div id="success-popup" class="hidden fixed bottom-5 right-5 bg-green-600 text-white py-3 px-5 rounded-lg shadow-lg flex items-center">
    <i class="fas fa-check-circle mr-3"></i>
    <span id="popup-message"></span>
</div>

<!-- SCRIPT PARA MANEJAR EL POPUP -->
<?php if(!empty($success)): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const popup = document.getElementById('success-popup');
        const messageEl = document.getElementById('popup-message');
        if (popup && messageEl) {
            messageEl.textContent = "<?= htmlspecialchars($success) ?>";
            popup.classList.remove('hidden');
            setTimeout(() => {
                popup.classList.add('hidden');
            }, 3000);
        }
    });
</script>
<?php endif; ?>