<?php
// --- LÓGICA DE LA VISTA PARA EDITAR Y VER DETALLES DEL CRÉDITO ---
ob_start();

$error = '';
$credito_id = $_GET['id'] ?? null;

// --- MAPA DE NOMBRES PARA LAS ZONAS Y OTROS DATOS ---
$nombres_zonas = [
    1 => 'Santi',
    2 => 'Juan Pablo',
    3 => 'Enzo',
    4 => 'Tafi del V',
    5 => 'Famailla',
    6 => 'Sgo'
];
$dias_semana = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
$frecuencias = ['Semanal', 'Quincenal', 'Mensual'];

if (!$credito_id) {
    header("Location: index.php?page=clientes");
    exit;
}

// --- NUEVO: PROCESAR ELIMINACIÓN DE PAGO ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['eliminar_pago'])) {
    $pago_id_a_eliminar = $_POST['pago_id'];

    try {
        $pdo->beginTransaction();

        // 1. Eliminar el pago de la tabla 'pagos'
        $stmt_delete = $pdo->prepare("DELETE FROM pagos WHERE id = ?");
        $stmt_delete->execute([$pago_id_a_eliminar]);

        // 2. Obtener el nuevo total abonado histórico
        $stmt_total_pagado = $pdo->prepare("SELECT SUM(monto_pagado) FROM pagos WHERE credito_id = ?");
        $stmt_total_pagado->execute([$credito_id]);
        $total_abonado_historico = $stmt_total_pagado->fetchColumn() ?: 0;
        
        // 3. Resetear el cronograma a 'Pendiente'
        $pdo->prepare("UPDATE cronograma_cuotas SET monto_pagado = 0, estado = 'Pendiente' WHERE credito_id = ?")->execute([$credito_id]);
        
        // 4. Re-aplicar los pagos restantes al cronograma
        if ($total_abonado_historico > 0) {
            $stmt_cuotas_reapply = $pdo->prepare("SELECT * FROM cronograma_cuotas WHERE credito_id = ? ORDER BY numero_cuota ASC");
            $stmt_cuotas_reapply->execute([$credito_id]);
            
            $monto_restante_a_aplicar = $total_abonado_historico;

            while ($monto_restante_a_aplicar > 0 && ($cuota = $stmt_cuotas_reapply->fetch(PDO::FETCH_ASSOC))) {
                $monto_cuota_actual = $cuota['monto_cuota'];
                $monto_a_imputar = ($monto_restante_a_aplicar >= $monto_cuota_actual) ? $monto_cuota_actual : $monto_restante_a_aplicar;
                $nuevo_estado_cuota = (bccomp($monto_a_imputar, $monto_cuota_actual, 2) >= 0) ? 'Pagado' : 'Pago Parcial';

                $pdo->prepare("UPDATE cronograma_cuotas SET monto_pagado = ?, estado = ? WHERE id = ?")->execute([$monto_a_imputar, $nuevo_estado_cuota, $cuota['id']]);
                $monto_restante_a_aplicar -= $monto_a_imputar;
            }
        }

        // 5. Recalcular y actualizar el estado general del crédito
        $sql_count_pagadas = "SELECT COUNT(id) FROM cronograma_cuotas WHERE credito_id = ? AND estado = 'Pagado'";
        $stmt_count_pagadas = $pdo->prepare($sql_count_pagadas);
        $stmt_count_pagadas->execute([$credito_id]);
        $nuevas_cuotas_pagadas_reales = $stmt_count_pagadas->fetchColumn();

        $stmt_total = $pdo->prepare("SELECT total_cuotas, ultimo_pago FROM creditos WHERE id = ?");
        $stmt_total->execute([$credito_id]);
        $credito_info = $stmt_total->fetch(PDO::FETCH_ASSOC);
        
        $nuevo_estado_credito = ($nuevas_cuotas_pagadas_reales >= $credito_info['total_cuotas']) ? 'Pagado' : 'Activo';

        // Al eliminar, el 'ultimo_pago' se revierte al pago anterior o a la fecha de inicio
        $stmt_last_payment = $pdo->prepare("SELECT fecha_pago FROM pagos WHERE credito_id = ? ORDER BY fecha_pago DESC, id DESC LIMIT 1");
        $stmt_last_payment->execute([$credito_id]);
        $ultima_fecha_pago = $stmt_last_payment->fetchColumn() ?: $credito_info['ultimo_pago'];

        $pdo->prepare("UPDATE creditos SET cuotas_pagadas = ?, estado = ?, ultimo_pago = ? WHERE id = ?")->execute([$nuevas_cuotas_pagadas_reales, $nuevo_estado_credito, $ultima_fecha_pago, $credito_id]);
        
        $pdo->commit();

        // CORRECCIÓN: Usar redirección con JavaScript para evitar error de headers.
        echo '<script>window.location.href="index.php?page=editar_cliente&id=' . $credito_id . '";</script>';
        exit;

    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Error al eliminar el pago: " . $e->getMessage();
    }
}


// --- PROCESAR EL FORMULARIO AL GUARDAR CAMBIOS ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['cliente_id'])) {
    // Recoger datos del cliente y crédito
    $cliente_id = $_POST['cliente_id'];
    $nombre = trim($_POST['nombre'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $zona = filter_input(INPUT_POST, 'zona', FILTER_VALIDATE_INT);
    $frecuencia = $_POST['frecuencia'] ?? '';
    $total_cuotas = filter_input(INPUT_POST, 'total_cuotas', FILTER_VALIDATE_INT);
    $monto_cuota = filter_input(INPUT_POST, 'monto_cuota', FILTER_VALIDATE_FLOAT);
    $fecha_inicio_str = $_POST['ultimo_pago'] ?? date('Y-m-d');

    $dia_pago = null;
    $dia_vencimiento = null;

    if ($zona == 4 && $frecuencia == 'Semanal') {
        $dia_pago = 'Varios';
    } elseif ($frecuencia == 'Semanal') {
        $dia_pago = $_POST['dia_pago'] ?? '';
    } elseif (in_array($frecuencia, ['Quincenal', 'Mensual'])) {
        $dia_vencimiento = filter_input(INPUT_POST, 'dia_vencimiento', FILTER_VALIDATE_INT);
    }
    
    // Validaciones...
    if (empty($nombre) || empty($zona) || empty($frecuencia) || !$total_cuotas || !$monto_cuota) {
        $error = "Los campos marcados con * son obligatorios.";
    } else {
        try {
            // --- NUEVO: OBTENER EL TOTAL ABONADO ANTES DE CUALQUIER CAMBIO ---
            $stmt_total_pagado = $pdo->prepare("SELECT SUM(monto_pagado) FROM pagos WHERE credito_id = ?");
            $stmt_total_pagado->execute([$credito_id]);
            $total_abonado_historico = $stmt_total_pagado->fetchColumn() ?: 0;

            $pdo->beginTransaction();

            // 1. Obtener datos antiguos para comparar si el cronograma necesita regenerarse
            $stmt_old = $pdo->prepare("SELECT total_cuotas, monto_cuota, frecuencia, ultimo_pago FROM creditos WHERE id = ?");
            $stmt_old->execute([$credito_id]);
            $old_credit_data = $stmt_old->fetch(PDO::FETCH_ASSOC);

            // 2. Actualizar datos del cliente y del crédito
            $pdo->prepare("UPDATE clientes SET nombre = ?, telefono = ?, direccion = ? WHERE id = ?")->execute([$nombre, $telefono, $direccion, $cliente_id]);
            $monto_total = $total_cuotas * $monto_cuota;
            $sql_credito = "UPDATE creditos SET zona = ?, frecuencia = ?, dia_pago = ?, dia_vencimiento = ?, total_cuotas = ?, monto_cuota = ?, monto_total = ?, ultimo_pago = ? WHERE id = ?";
            $pdo->prepare($sql_credito)->execute([$zona, $frecuencia, $dia_pago, $dia_vencimiento, $total_cuotas, $monto_cuota, $monto_total, $fecha_inicio_str, $credito_id]);

            // 3. Verificar si es necesario regenerar el cronograma
            $regenerate_schedule = false;
            if ($old_credit_data['total_cuotas'] != $total_cuotas || 
                $old_credit_data['monto_cuota'] != $monto_cuota || 
                $old_credit_data['frecuencia'] != $frecuencia ||
                $old_credit_data['ultimo_pago'] != $fecha_inicio_str) {
                $regenerate_schedule = true;
            }

            if ($regenerate_schedule) {
                // Borrar el cronograma anterior
                $pdo->prepare("DELETE FROM cronograma_cuotas WHERE credito_id = ?")->execute([$credito_id]);

                // Generar el nuevo cronograma
                $sql_cronograma = "INSERT INTO cronograma_cuotas (credito_id, numero_cuota, fecha_vencimiento, monto_cuota) VALUES (?, ?, ?, ?)";
                $stmt_cronograma = $pdo->prepare($sql_cronograma);
                $fecha_actual = new DateTime($fecha_inicio_str);

                for ($i = 1; $i <= $total_cuotas; $i++) {
                    $fecha_vencimiento = clone $fecha_actual;
                    $stmt_cronograma->execute([$credito_id, $i, $fecha_vencimiento->format('Y-m-d'), $monto_cuota]);
                    
                    switch ($frecuencia) {
                        case 'Semanal': $fecha_actual->modify('+1 week'); break;
                        case 'Quincenal': $fecha_actual->modify('+15 days'); break;
                        case 'Mensual': $fecha_actual->modify('+1 month'); break;
                    }
                }
            }

            // --- NUEVO: REAPLICAR LOS PAGOS HISTÓRICOS AL CRONOGRAMA ---
            if ($total_abonado_historico > 0) {
                $pdo->prepare("UPDATE cronograma_cuotas SET monto_pagado = 0, estado = 'Pendiente' WHERE credito_id = ?")->execute([$credito_id]);
                
                $stmt_cuotas_reapply = $pdo->prepare("SELECT * FROM cronograma_cuotas WHERE credito_id = ? ORDER BY numero_cuota ASC");
                $stmt_cuotas_reapply->execute([$credito_id]);
                
                $monto_restante_a_aplicar = $total_abonado_historico;

                while ($monto_restante_a_aplicar > 0 && ($cuota = $stmt_cuotas_reapply->fetch(PDO::FETCH_ASSOC))) {
                    $monto_cuota_actual = $cuota['monto_cuota'];
                    $monto_a_imputar = ($monto_restante_a_aplicar >= $monto_cuota_actual) ? $monto_cuota_actual : $monto_restante_a_aplicar;
                    $nuevo_estado_cuota = (bccomp($monto_a_imputar, $monto_cuota_actual, 2) >= 0) ? 'Pagado' : 'Pago Parcial';

                    $pdo->prepare("UPDATE cronograma_cuotas SET monto_pagado = ?, estado = ? WHERE id = ?")->execute([$monto_a_imputar, $nuevo_estado_cuota, $cuota['id']]);
                    $monto_restante_a_aplicar -= $monto_a_imputar;
                }
            }

            // --- NUEVO: RECALCULAR Y ACTUALIZAR EL TOTAL DE CUOTAS PAGADAS ---
            $sql_count_pagadas = "SELECT COUNT(id) FROM cronograma_cuotas WHERE credito_id = ? AND estado = 'Pagado'";
            $stmt_count_pagadas = $pdo->prepare($sql_count_pagadas);
            $stmt_count_pagadas->execute([$credito_id]);
            $nuevas_cuotas_pagadas_reales = $stmt_count_pagadas->fetchColumn();
            
            $nuevo_estado_credito = ($nuevas_cuotas_pagadas_reales >= $total_cuotas) ? 'Pagado' : 'Activo';

            $pdo->prepare("UPDATE creditos SET cuotas_pagadas = ?, estado = ? WHERE id = ?")->execute([$nuevas_cuotas_pagadas_reales, $nuevo_estado_credito, $credito_id]);

            $pdo->commit();
            echo '<script>window.location.href="index.php?page=clientes&status=updated";</script>';
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Error al actualizar los datos: " . $e->getMessage();
        }
    }
}


// --- OBTENER DATOS DEL CLIENTE Y SU CRÉDITO PARA MOSTRAR EN EL FORMULARIO ---
try {
    // Datos del cliente y crédito
    $sql_data = "SELECT c.id as cliente_id, c.nombre, c.telefono, c.direccion, cr.*
                 FROM clientes c 
                 JOIN creditos cr ON c.id = cr.cliente_id
                 WHERE cr.id = ?";
    $stmt_data = $pdo->prepare($sql_data);
    $stmt_data->execute([$credito_id]);
    $data = $stmt_data->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        header("Location: index.php?page=clientes&status=notfound");
        exit;
    }

    // Cronograma de cuotas
    $sql_cronograma = "SELECT * FROM cronograma_cuotas WHERE credito_id = ? ORDER BY numero_cuota ASC";
    $stmt_cronograma = $pdo->prepare($sql_cronograma);
    $stmt_cronograma->execute([$credito_id]);
    $cronograma = $stmt_cronograma->fetchAll(PDO::FETCH_ASSOC);

    // Historial de pagos
    $sql_pagos = "SELECT * FROM pagos WHERE credito_id = ? ORDER BY fecha_pago DESC, id DESC";
    $stmt_pagos = $pdo->prepare($sql_pagos);
    $stmt_pagos->execute([$credito_id]);
    $pagos = $stmt_pagos->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error al obtener los datos del registro: " . $e->getMessage());
}
?>

<h2 class="text-2xl font-bold text-gray-200 mb-4">Detalle y Edición de Crédito</h2>

<?php if(!empty($error)): ?>
    <div class="bg-red-900 border border-red-700 text-red-200 px-4 py-3 rounded-md mb-4" role="alert"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Formulario de Edición -->
<div class="bg-gray-800 p-6 rounded-lg border border-gray-700 mb-8">
    <form action="index.php?page=editar_cliente&id=<?= $credito_id ?>" method="POST">
        <input type="hidden" name="cliente_id" value="<?= $data['cliente_id'] ?>">
        
        <!-- Datos Personales -->
        <fieldset class="border border-gray-600 p-4 rounded-md mb-6">
            <legend class="px-2 text-lg font-semibold text-gray-300">Datos Personales</legend>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="nombre" class="block text-sm font-medium text-gray-300">Nombre Completo <span class="text-red-500">*</span></label>
                    <input type="text" id="nombre" name="nombre" required class="mt-1 block w-full rounded-md form-element-dark" value="<?= htmlspecialchars($data['nombre']) ?>">
                </div>
                <div>
                    <label for="telefono" class="block text-sm font-medium text-gray-300">Teléfono</label>
                    <input type="text" id="telefono" name="telefono" class="mt-1 block w-full rounded-md form-element-dark" value="<?= htmlspecialchars($data['telefono']) ?>">
                </div>
                <div class="md:col-span-2">
                    <label for="direccion" class="block text-sm font-medium text-gray-300">Dirección</label>
                    <input type="text" id="direccion" name="direccion" class="mt-1 block w-full rounded-md form-element-dark" value="<?= htmlspecialchars($data['direccion']) ?>">
                </div>
            </div>
        </fieldset>

        <!-- Datos del Crédito -->
        <fieldset class="border border-gray-600 p-4 rounded-md">
            <legend class="px-2 text-lg font-semibold text-gray-300">Datos del Crédito (¡Atención! Cambiar estos datos regenerará el cronograma)</legend>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label for="zona" class="block text-sm font-medium text-gray-300">Zona <span class="text-red-500">*</span></label>
                     <select id="zona" name="zona" required class="mt-1 block w-full rounded-md form-element-dark">
                        <?php foreach($nombres_zonas as $num => $nombre): ?>
                            <option value="<?= $num ?>" <?= ($data['zona'] == $num) ? 'selected' : '' ?>><?= htmlspecialchars($nombre) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="frecuencia" class="block text-sm font-medium text-gray-300">Frecuencia de Pago <span class="text-red-500">*</span></label>
                    <select id="frecuencia" name="frecuencia" required class="mt-1 block w-full rounded-md form-element-dark">
                        <?php foreach ($frecuencias as $f): ?>
                            <option value="<?= $f ?>" <?= ($data['frecuencia'] == $f) ? 'selected' : '' ?>><?= $f ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="dia_pago_container" style="display: none;">
                    <label for="dia_pago" class="block text-sm font-medium text-gray-300">Día de Pago <span class="text-red-500">*</span></label>
                    <select id="dia_pago" name="dia_pago" class="mt-1 block w-full rounded-md form-element-dark">
                         <?php foreach ($dias_semana as $d): ?>
                            <option value="<?= $d ?>" <?= ($data['dia_pago'] == $d) ? 'selected' : '' ?>><?= $d ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="dia_vencimiento_container" style="display: none;">
                    <label for="dia_vencimiento" class="block text-sm font-medium text-gray-300">Día de Vencimiento (1-31) <span class="text-red-500">*</span></label>
                    <input type="number" id="dia_vencimiento" name="dia_vencimiento" min="1" max="31" class="mt-1 block w-full rounded-md form-element-dark" value="<?= htmlspecialchars($data['dia_vencimiento']) ?>">
                </div>
                 <div>
                    <label for="ultimo_pago" class="block text-sm font-medium text-gray-300">Fecha de Inicio <span class="text-red-500">*</span></label>
                    <input type="date" id="ultimo_pago" name="ultimo_pago" required class="mt-1 block w-full rounded-md form-element-dark" value="<?= htmlspecialchars($data['ultimo_pago']) ?>">
                </div>
                <div>
                    <label for="total_cuotas" class="block text-sm font-medium text-gray-300">Total de Cuotas <span class="text-red-500">*</span></label>
                    <input type="number" id="total_cuotas" name="total_cuotas" required class="mt-1 block w-full rounded-md form-element-dark" value="<?= htmlspecialchars($data['total_cuotas']) ?>">
                </div>
                <div>
                    <label for="monto_cuota" class="block text-sm font-medium text-gray-300">Monto de Cuota <span class="text-red-500">*</span></label>
                    <input type="number" step="0.01" id="monto_cuota" name="monto_cuota" required class="mt-1 block w-full rounded-md form-element-dark" value="<?= htmlspecialchars($data['monto_cuota']) ?>">
                </div>
            </div>
        </fieldset>
        <div class="mt-6 flex justify-end gap-4">
            <a href="index.php?page=clientes" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-md transition duration-300">Cancelar</a>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md transition duration-300"><i class="fas fa-save mr-2"></i>Guardar Cambios</button>
        </div>
    </form>
</div>

<!-- Cronograma y Pagos -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <!-- Cronograma de Cuotas -->
    <div class="bg-gray-800 p-6 rounded-lg border border-gray-700">
        <h3 class="text-xl font-semibold text-gray-300 mb-4">Cronograma de Cuotas</h3>
        <div class="overflow-y-auto max-h-96">
            <table class="min-w-full">
                <thead class="sticky top-0 table-header-custom">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-300 uppercase">#</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-300 uppercase">Vencimiento</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-300 uppercase">Importe</th>
                        <th class="px-4 py-2 text-center text-xs font-medium text-gray-300 uppercase">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                    <?php foreach ($cronograma as $cuota): ?>
                        <tr>
                            <td class="px-4 py-2 text-gray-300"><?= $cuota['numero_cuota'] ?></td>
                            <td class="px-4 py-2 text-gray-300"><?= (new DateTime($cuota['fecha_vencimiento']))->format('d/m/Y') ?></td>
                            <td class="px-4 py-2 text-right text-gray-300"><?= formatCurrency($cuota['monto_cuota']) ?></td>
                            <td class="px-4 py-2 text-center">
                                <?php if($cuota['estado'] == 'Pagado'): ?>
                                    <span class="text-green-400 font-bold text-xs">PAGADO</span>
                                <?php elseif($cuota['estado'] == 'Pago Parcial'): ?>
                                    <span class="text-yellow-400 text-xs">PARCIAL (<?= formatCurrency($cuota['monto_pagado']) ?>)</span>
                                <?php else: ?>
                                    <span class="text-red-400 text-xs">PENDIENTE</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagos Realizados -->
    <div class="bg-gray-800 p-6 rounded-lg border border-gray-700">
        <h3 class="text-xl font-semibold text-gray-300 mb-4">Pagos Realizados</h3>
        <div class="overflow-y-auto max-h-96">
            <table class="min-w-full">
                <thead class="sticky top-0 table-header-custom">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-300 uppercase">Fecha</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-300 uppercase">Monto Abonado</th>
                        <th class="px-4 py-2 text-center text-xs font-medium text-gray-300 uppercase">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                    <?php if(empty($pagos)): ?>
                        <tr><td colspan="3" class="text-center py-8 text-gray-500">Aún no se han registrado pagos.</td></tr>
                    <?php else: ?>
                        <?php foreach ($pagos as $pago): ?>
                            <tr>
                                <td class="px-4 py-2 text-gray-300"><?= (new DateTime($pago['fecha_pago']))->format('d/m/Y') ?></td>
                                <td class="px-4 py-2 text-right text-green-400 font-semibold"><?= formatCurrency($pago['monto_pagado']) ?></td>
                                <td class="px-4 py-2 text-center">
                                    <form action="index.php?page=editar_cliente&id=<?= $credito_id ?>" method="POST" onsubmit="return confirm('¿Estás seguro de que quieres eliminar este pago? Esta acción recalculará todo el cronograma.');">
                                        <input type="hidden" name="pago_id" value="<?= $pago['id'] ?>">
                                        <button type="submit" name="eliminar_pago" class="text-red-500 hover:text-red-400" title="Eliminar Pago">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const zonaSelect = document.getElementById('zona');
    const frecuenciaSelect = document.getElementById('frecuencia');
    const diaPagoContainer = document.getElementById('dia_pago_container');
    const diaVencimientoContainer = document.getElementById('dia_vencimiento_container');
    const diaPagoSelect = document.getElementById('dia_pago');
    const diaVencimientoInput = document.getElementById('dia_vencimiento');

    function toggleVencimientoFields() {
        const frecuencia = frecuenciaSelect.value;
        const zona = zonaSelect.value;

        diaPagoContainer.style.display = 'none';
        diaVencimientoContainer.style.display = 'none';
        diaPagoSelect.required = false;
        diaVencimientoInput.required = false;

        if (frecuencia === 'Semanal') {
            if (zona != 4) { 
                diaPagoContainer.style.display = 'block';
                diaPagoSelect.required = true;
            }
        } else if (frecuencia === 'Quincenal' || frecuencia === 'Mensual') {
            diaVencimientoContainer.style.display = 'block';
            diaVencimientoInput.required = true;
        }
    }

    zonaSelect.addEventListener('change', toggleVencimientoFields);
    frecuenciaSelect.addEventListener('change', toggleVencimientoFields);
    toggleVencimientoFields(); // Llamada inicial
});
</script>

