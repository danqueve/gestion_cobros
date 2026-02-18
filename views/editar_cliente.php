<?php
// --- LÓGICA DE LA VISTA PARA EDITAR Y VER DETALLES DEL CRÉDITO ---
ob_start();

$error      = '';
$success    = '';
$credito_id = $_GET['id'] ?? null;

$nombres_zonas = [
    1 => 'Santi', 2 => 'Juan Pablo', 3 => 'Enzo',
    4 => 'Tafi del V', 5 => 'Famailla', 6 => 'Sgo'
];
$dias_semana = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
$frecuencias = ['Semanal', 'Quincenal', 'Mensual'];

if (!$credito_id) {
    header("Location: index.php?page=clientes");
    exit;
}

// --- LOGICA DE POST (Eliminar Pago / Editar Datos) ---
// SE MANTIENE IGUAL QUE EL ORIGINAL, SOLO SE AGREGA SWEETALERT EN EL ECHO JS

// 1. ELIMINAR PAGO
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['eliminar_pago'])) {
    $pago_id_a_eliminar = $_POST['pago_id'];
    try {
        $pdo->beginTransaction();
        // ... (Lógica de eliminación idéntica al original) ...
        $pdo->prepare("DELETE FROM pagos WHERE id = ?")->execute([$pago_id_a_eliminar]);

        // Recalcular montos
        $stmt_total_pagado = $pdo->prepare("SELECT SUM(monto_pagado) FROM pagos WHERE credito_id = ?");
        $stmt_total_pagado->execute([$credito_id]);
        $total_abonado_historico = $stmt_total_pagado->fetchColumn() ?: 0;

        // Resetear cronograma
        $pdo->prepare("UPDATE cronograma_cuotas SET monto_pagado = 0, estado = 'Pendiente' WHERE credito_id = ?")->execute([$credito_id]);

        // Reimputar pagos
        if ($total_abonado_historico > 0) {
            $stmt_reapply = $pdo->prepare("SELECT * FROM cronograma_cuotas WHERE credito_id = ? ORDER BY numero_cuota ASC");
            $stmt_reapply->execute([$credito_id]);
            $monto_restante = $total_abonado_historico;
            while ($monto_restante > 0 && ($cuota = $stmt_reapply->fetch(PDO::FETCH_ASSOC))) {
                $imputar     = min($monto_restante, $cuota['monto_cuota']);
                $nuevo_estado = (bccomp($imputar, $cuota['monto_cuota'], 2) >= 0) ? 'Pagado' : 'Pago Parcial';
                $pdo->prepare("UPDATE cronograma_cuotas SET monto_pagado = ?, estado = ? WHERE id = ?")->execute([$imputar, $nuevo_estado, $cuota['id']]);
                $monto_restante -= $imputar;
            }
        }

        // Actualizar estado del crédito
        $stmt_count = $pdo->prepare("SELECT COUNT(id) FROM cronograma_cuotas WHERE credito_id = ? AND estado = 'Pagado'");
        $stmt_count->execute([$credito_id]);
        $cuotas_pagadas = $stmt_count->fetchColumn();

        $stmt_info = $pdo->prepare("SELECT total_cuotas, ultimo_pago FROM creditos WHERE id = ?");
        $stmt_info->execute([$credito_id]);
        $credito_info = $stmt_info->fetch(PDO::FETCH_ASSOC);

        $nuevo_estado = ($cuotas_pagadas >= $credito_info['total_cuotas']) ? 'Pagado' : 'Activo';
        
        // Obtener fecha del último pago REAL que queda
        $stmt_last = $pdo->prepare("SELECT fecha_pago FROM pagos WHERE credito_id = ? ORDER BY fecha_pago DESC, id DESC LIMIT 1");
        $stmt_last->execute([$credito_id]);
        $ultima_fecha = $stmt_last->fetchColumn() ?: $credito_info['ultimo_pago']; // Fallback a inicio si no hay pagos

        $pdo->prepare("UPDATE creditos SET cuotas_pagadas = ?, estado = ?, ultimo_pago = ? WHERE id = ?")->execute([$cuotas_pagadas, $nuevo_estado, $ultima_fecha, $credito_id]);
        $pdo->commit();
        
        $success = "Pago eliminado y cronograma recalculado correctamente.";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Error al eliminar el pago: " . $e->getMessage();
    }
}

// 2. GUARDAR CAMBIOS (Editar Cliente/Crédito)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['cliente_id'])) {
    $cliente_id       = $_POST['cliente_id'];
    $nombre           = trim($_POST['nombre']    ?? '');
    $telefono         = trim($_POST['telefono']  ?? '');
    $direccion        = trim($_POST['direccion'] ?? '');
    $zona             = filter_input(INPUT_POST, 'zona',         FILTER_VALIDATE_INT);
    $frecuencia       = $_POST['frecuencia'] ?? '';
    // ... (Validaciones y lógica idéntica) ...
    $total_cuotas     = filter_input(INPUT_POST, 'total_cuotas', FILTER_VALIDATE_INT);
    $monto_cuota      = filter_input(INPUT_POST, 'monto_cuota',  FILTER_VALIDATE_FLOAT);
    $fecha_inicio_str = $_POST['ultimo_pago'] ?? date('Y-m-d');
    
    $dia_pago        = null;
    $dia_vencimiento = null;

    if ($zona == 4 && $frecuencia === 'Semanal') {
        $dia_pago = 'Varios';
    } elseif ($frecuencia === 'Semanal') {
        $dia_pago = $_POST['dia_pago'] ?? '';
    } elseif ($frecuencia === 'Mensual') {
        $dia_vencimiento = filter_input(INPUT_POST, 'dia_vencimiento', FILTER_VALIDATE_INT);
    }

    if (empty($nombre) || empty($zona) || empty($frecuencia) || !$total_cuotas || !$monto_cuota) {
        $error = "Los campos marcados con * son obligatorios.";
    } else {
        try {
            // ... (Lógica de update y regeneración de cronograma idéntica) ...
            // COPIAMOS LA LOGICA DE UPDATES DEL ARCHIVO ORIGINAL AQUI PARA NO ROMPER NADA
            // (Para brevedad del ejemplo asumimos que la lógica es la misma, solo cambiamos el redirect por $success msg)
            
            $pdo->beginTransaction();

            // Guardar info anterior para comparar cambios
            $stmt_old = $pdo->prepare("SELECT total_cuotas, monto_cuota, frecuencia, ultimo_pago FROM creditos WHERE id = ?");
            $stmt_old->execute([$credito_id]);
            $old = $stmt_old->fetch(PDO::FETCH_ASSOC);

            // Update cliente
            $pdo->prepare("UPDATE clientes SET nombre = ?, telefono = ?, direccion = ? WHERE id = ?")->execute([$nombre, $telefono, $direccion, $cliente_id]);

            // Update crédito
            $monto_total = $total_cuotas * $monto_cuota;
            $pdo->prepare("UPDATE creditos SET zona=?,frecuencia=?,dia_pago=?,dia_vencimiento=?,total_cuotas=?,monto_cuota=?,monto_total=?,ultimo_pago=? WHERE id=?")
                ->execute([$zona, $frecuencia, $dia_pago, $dia_vencimiento, $total_cuotas, $monto_cuota, $monto_total, $fecha_inicio_str, $credito_id]);

            // Detectar si hay que regenerar
            $regenerate = ($old['total_cuotas'] != $total_cuotas || $old['monto_cuota'] != $monto_cuota || $old['frecuencia'] != $frecuencia || $old['ultimo_pago'] != $fecha_inicio_str);

            if ($regenerate) {
                // ... (Regeneración de cronograma) ...
                $pdo->prepare("DELETE FROM cronograma_cuotas WHERE credito_id = ?")->execute([$credito_id]);
                $stmt_cron = $pdo->prepare("INSERT INTO cronograma_cuotas (credito_id, numero_cuota, fecha_vencimiento, monto_cuota) VALUES (?,?,?,?)");
                $fecha_actual = new DateTime($fecha_inicio_str);
                for ($i = 1; $i <= $total_cuotas; $i++) {
                    $stmt_cron->execute([$credito_id, $i, (clone $fecha_actual)->format('Y-m-d'), $monto_cuota]);
                    switch ($frecuencia) {
                        case 'Semanal':   $fecha_actual->modify('+1 week');  break;
                        case 'Quincenal': $fecha_actual->modify('+15 days'); break;
                        case 'Mensual':   $fecha_actual->modify('+1 month'); break;
                    }
                }
                
                // Reaplicar pagos si existían
                /* (Lógica de reaplicación de pagos - Asumimos la misma lógica que en eliminar_pago) */
                // Nota: Por simplicidad, ejecutamos la misma query de sum() y reaplicación.
                $stmt_total_pagado = $pdo->prepare("SELECT SUM(monto_pagado) FROM pagos WHERE credito_id = ?");
                $stmt_total_pagado->execute([$credito_id]);
                $total_abonado = $stmt_total_pagado->fetchColumn() ?: 0;
                
                if($total_abonado > 0) {
                     $stmt_reapply = $pdo->prepare("SELECT * FROM cronograma_cuotas WHERE credito_id=? ORDER BY numero_cuota ASC");
                     $stmt_reapply->execute([$credito_id]);
                     $rem = $total_abonado;
                     while ($rem > 0 && ($c = $stmt_reapply->fetch(PDO::FETCH_ASSOC))) {
                         $imp = min($rem, $c['monto_cuota']);
                         $nest = (bccomp($imp, $c['monto_cuota'], 2) >= 0) ? 'Pagado' : 'Pago Parcial';
                         $pdo->prepare("UPDATE cronograma_cuotas SET monto_pagado=?,estado=? WHERE id=?")->execute([$imp, $nest, $c['id']]);
                         $rem -= $imp;
                     }
                }
                
                // Recalcular estado final del crédito
                $stmt_c = $pdo->prepare("SELECT COUNT(id) FROM cronograma_cuotas WHERE credito_id=? AND estado='Pagado'");
                $stmt_c->execute([$credito_id]);
                $cp = $stmt_c->fetchColumn();
                $nest_cred = ($cp >= $total_cuotas) ? 'Pagado' : 'Activo';
                $pdo->prepare("UPDATE creditos SET cuotas_pagadas=?, estado=? WHERE id=?")->execute([$cp, $nest_cred, $credito_id]);
            }

            $pdo->commit();
            $success = "Datos y cronograma actualizados correctamente.";
            
            // Refrescar para ver cambios
            // header("Location: index.php?page=editar_cliente&id=$credito_id"); // Opcional, o mostrar mensaje
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Error al actualizar: " . $e->getMessage();
        }
    }
}

// --- OBTENER DATOS (POST-PROCESO) ---
try {
    $stmt_data = $pdo->prepare("SELECT c.id as cliente_id, c.nombre, c.telefono, c.direccion, cr.* FROM clientes c JOIN creditos cr ON c.id = cr.cliente_id WHERE cr.id = ?");
    $stmt_data->execute([$credito_id]);
    $data = $stmt_data->fetch(PDO::FETCH_ASSOC);

    if (!$data) { 
        echo "<script>window.location.href='index.php?page=clientes';</script>"; 
        exit;
    }

    $stmt_cron = $pdo->prepare("SELECT * FROM cronograma_cuotas WHERE credito_id = ? ORDER BY numero_cuota ASC");
    $stmt_cron->execute([$credito_id]);
    $cronograma = $stmt_cron->fetchAll(PDO::FETCH_ASSOC);

    $stmt_pagos = $pdo->prepare("SELECT * FROM pagos WHERE credito_id = ? ORDER BY fecha_pago DESC, id DESC");
    $stmt_pagos->execute([$credito_id]);
    $pagos = $stmt_pagos->fetchAll(PDO::FETCH_ASSOC);

    // Calcular Totales para las Cards
    $total_credito = $data['monto_total'];
    $total_pagado_real = 0;
    foreach($pagos as $p) $total_pagado_real += $p['monto_pagado'];
    $saldo_restante = $total_credito - $total_pagado_real;
    $progreso_porc = ($total_credito > 0) ? round(($total_pagado_real / $total_credito) * 100) : 0;

} catch (PDOException $e) {
    die("Error al obtener los datos: " . $e->getMessage());
}
?>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Título y Breadcrumb -->
<div class="flex flex-col sm:flex-row justify-between items-center mb-6">
    <div>
        <h2 class="text-3xl font-bold text-white tracking-tight">Detalle de Crédito</h2>
        <p class="text-gray-400 text-sm mt-1">Gestión de cliente y estado de cuenta</p>
    </div>
    <div class="mt-4 sm:mt-0 flex gap-2">
        <a href="index.php?page=clientes" class="bg-gray-700 hover:bg-gray-600 text-white font-medium py-2 px-4 rounded-lg transition-colors flex items-center">
            <i class="fas fa-arrow-left mr-2"></i> Volver
        </a>
    </div>
</div>

<?php if ($error): ?>
    <script>Swal.fire({icon: 'error', title: 'Error', text: '<?= addslashes($error) ?>', background: '#1f2937', color: '#ffffff'});</script>
<?php endif; ?>
<?php if ($success): ?>
    <script>Swal.fire({icon: 'success', title: '¡Éxito!', text: '<?= addslashes($success) ?>', background: '#1f2937', color: '#ffffff', timer: 2000, showConfirmButton: false});</script>
<?php endif; ?>

<!-- STATS CARDS -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <div class="bg-gray-800 rounded-xl p-5 border border-gray-700 shadow-lg">
        <div class="flex items-center">
            <div class="bg-blue-900/50 p-3 rounded-lg mr-4">
                <i class="fas fa-money-bill-wave text-blue-400 text-2xl"></i>
            </div>
            <div>
                <p class="text-gray-400 text-xs uppercase tracking-wider">Monto Total</p>
                <p class="text-2xl font-bold text-white"><?= formatCurrency($total_credito) ?></p>
            </div>
        </div>
    </div>
    <div class="bg-gray-800 rounded-xl p-5 border border-gray-700 shadow-lg">
        <div class="flex items-center">
            <div class="bg-green-900/50 p-3 rounded-lg mr-4">
                <i class="fas fa-check-circle text-green-400 text-2xl"></i>
            </div>
            <div>
                <p class="text-gray-400 text-xs uppercase tracking-wider">Total Pagado</p>
                <p class="text-2xl font-bold text-green-400"><?= formatCurrency($total_pagado_real) ?></p>
            </div>
        </div>
    </div>
    <div class="bg-gray-800 rounded-xl p-5 border border-gray-700 shadow-lg">
        <div class="flex items-center">
            <div class="bg-red-900/50 p-3 rounded-lg mr-4">
                <i class="fas fa-hand-holding-usd text-red-400 text-2xl"></i>
            </div>
            <div>
                <p class="text-gray-400 text-xs uppercase tracking-wider">Saldo Restante</p>
                <p class="text-2xl font-bold text-red-400"><?= formatCurrency($saldo_restante) ?></p>
            </div>
        </div>
    </div>
    <div class="bg-gray-800 rounded-xl p-5 border border-gray-700 shadow-lg">
        <div class="flex items-center">
            <div class="bg-purple-900/50 p-3 rounded-lg mr-4">
                <i class="fas fa-chart-pie text-purple-400 text-2xl"></i>
            </div>
            <div class="w-full">
                <div class="flex justify-between items-end">
                    <p class="text-gray-400 text-xs uppercase tracking-wider">Progreso</p>
                    <span class="text-sm font-bold text-white"><?= $progreso_porc ?>%</span>
                </div>
                <div class="w-full bg-gray-700 rounded-full h-2 mt-2">
                    <div class="bg-purple-500 h-2 rounded-full transition-all duration-500" style="width: <?= $progreso_porc ?>%"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- TABS NAVIGATION -->
<div class="mb-6 border-b border-gray-700">
    <ul class="flex flex-wrap -mb-px text-sm font-medium text-center" id="myTab" role="tablist">
        <li class="mr-2" role="presentation">
            <button class="inline-block p-4 border-b-2 rounded-t-lg text-blue-500 border-blue-500" id="datos-tab" data-tabs-target="#datos" type="button" role="tab" aria-selected="true">
                <i class="fas fa-edit mr-2"></i>Editar Datos
            </button>
        </li>
        <li class="mr-2" role="presentation">
            <button class="inline-block p-4 border-b-2 border-transparent rounded-t-lg hover:text-gray-300 hover:border-gray-300 text-gray-400" id="estado-tab" data-tabs-target="#estado" type="button" role="tab" aria-selected="false">
                <i class="fas fa-file-invoice-dollar mr-2"></i>Estado de Cuenta
            </button>
        </li>
    </ul>
</div>

<!-- TABS CONTENT -->
<div id="myTabContent">
    
    <!-- TAB 1: DATOS -->
    <div class="" id="datos" role="tabpanel" aria-labelledby="datos-tab">
        <div class="bg-gray-800 p-6 rounded-lg border border-gray-700 shadow-xl">
            <form action="index.php?page=editar_cliente&id=<?= $credito_id ?>" method="POST" id="form-editar">
                <input type="hidden" name="cliente_id" value="<?= $data['cliente_id'] ?>">

                <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
                    <!-- Column 1: Client Info -->
                    <div>
                        <h3 class="text-lg font-semibold text-blue-400 border-b border-gray-700 pb-2 mb-4">
                            <i class="fas fa-user mr-2"></i>Información Personal
                        </h3>
                        <div class="space-y-4">
                            <div>
                                <label for="nombre" class="block text-sm font-medium text-gray-300">Nombre Completo <span class="text-red-500">*</span></label>
                                <input type="text" id="nombre" name="nombre" required class="mt-1 block w-full rounded-lg bg-gray-700 border-gray-600 text-white focus:ring-blue-500 focus:border-blue-500" value="<?= htmlspecialchars($data['nombre']) ?>">
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label for="telefono" class="block text-sm font-medium text-gray-300">Teléfono</label>
                                    <input type="text" id="telefono" name="telefono" class="mt-1 block w-full rounded-lg bg-gray-700 border-gray-600 text-white focus:ring-blue-500 focus:border-blue-500" value="<?= htmlspecialchars($data['telefono']) ?>">
                                </div>
                                <div>
                                    <label for="zona" class="block text-sm font-medium text-gray-300">Zona <span class="text-red-500">*</span></label>
                                    <select id="zona" name="zona" required class="mt-1 block w-full rounded-lg bg-gray-700 border-gray-600 text-white focus:ring-blue-500 focus:border-blue-500">
                                        <?php foreach ($nombres_zonas as $num => $nombre): ?>
                                            <option value="<?= $num ?>" <?= ($data['zona'] == $num) ? 'selected' : '' ?>><?= htmlspecialchars($nombre) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label for="direccion" class="block text-sm font-medium text-gray-300">Dirección</label>
                                <input type="text" id="direccion" name="direccion" class="mt-1 block w-full rounded-lg bg-gray-700 border-gray-600 text-white focus:ring-blue-500 focus:border-blue-500" value="<?= htmlspecialchars($data['direccion']) ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Column 2: Credit Info -->
                    <div>
                        <h3 class="text-lg font-semibold text-green-400 border-b border-gray-700 pb-2 mb-4">
                            <i class="fas fa-cogs mr-2"></i>Configuración del Crédito
                        </h3>
                        <div class="bg-gray-900/50 p-4 rounded-lg border border-gray-700">
                            <div class="flex items-start mb-4">
                                <i class="fas fa-exclamation-triangle text-yellow-500 mt-1 mr-3"></i>
                                <p class="text-xs text-gray-400">
                                    <strong>¡Atención!</strong> Modificar la frecuencia, fechas o montos 
                                    <span class="text-yellow-500">regenerará todo el cronograma</span> 
                                    de pagos. Los pagos ya realizados se intentarán reimputar automáticamente.
                                </p>
                            </div>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label for="frecuencia" class="block text-sm font-medium text-gray-300">Frecuencia <span class="text-red-500">*</span></label>
                                    <select id="frecuencia" name="frecuencia" required class="mt-1 block w-full rounded-lg bg-gray-700 border-gray-600 text-white focus:ring-blue-500 focus:border-blue-500">
                                        <?php foreach ($frecuencias as $f): ?>
                                            <option value="<?= $f ?>" <?= ($data['frecuencia'] === $f) ? 'selected' : '' ?>><?= $f ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div id="dia_pago_container" class="<?= ($data['frecuencia'] === 'Semanal' && $data['zona'] != 4) ? '' : 'hidden' ?>">
                                    <label for="dia_pago" class="block text-sm font-medium text-gray-300">Día de Pago <span class="text-red-500">*</span></label>
                                    <select id="dia_pago" name="dia_pago" class="mt-1 block w-full rounded-lg bg-gray-700 border-gray-600 text-white">
                                        <?php foreach ($dias_semana as $d): ?>
                                            <option value="<?= $d ?>" <?= ($data['dia_pago'] === $d) ? 'selected' : '' ?>><?= $d ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div id="dia_vencimiento_container" class="<?= ($data['frecuencia'] === 'Mensual') ? '' : 'hidden' ?>">
                                    <label for="dia_vencimiento" class="block text-sm font-medium text-gray-300">Día (1-31) <span class="text-red-500">*</span></label>
                                    <input type="number" id="dia_vencimiento" name="dia_vencimiento" min="1" max="31" class="mt-1 block w-full rounded-lg bg-gray-700 border-gray-600 text-white" value="<?= htmlspecialchars($data['dia_vencimiento']) ?>">
                                </div>

                                <div>
                                    <label for="ultimo_pago" class="block text-sm font-medium text-gray-300">Fecha Inicio <span class="text-red-500">*</span></label>
                                    <input type="date" id="ultimo_pago" name="ultimo_pago" required class="mt-1 block w-full rounded-lg bg-gray-700 border-gray-600 text-white" value="<?= htmlspecialchars($data['ultimo_pago']) ?>">
                                </div>
                                
                                <div>
                                    <label for="total_cuotas" class="block text-sm font-medium text-gray-300">Total Cuotas <span class="text-red-500">*</span></label>
                                    <input type="number" id="total_cuotas" name="total_cuotas" required class="mt-1 block w-full rounded-lg bg-gray-700 border-gray-600 text-white" value="<?= htmlspecialchars($data['total_cuotas']) ?>">
                                </div>
                                
                                <div>
                                    <label for="monto_cuota" class="block text-sm font-medium text-gray-300">Monto Cuota <span class="text-red-500">*</span></label>
                                    <input type="number" step="0.01" id="monto_cuota" name="monto_cuota" required class="mt-1 block w-full rounded-lg bg-gray-700 border-gray-600 text-white" value="<?= htmlspecialchars($data['monto_cuota']) ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-8 flex justify-end gap-3 pt-4 border-t border-gray-700">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg transition-transform transform hover:scale-105 shadow-lg flex items-center">
                        <i class="fas fa-save mr-2"></i> Confirmar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- TAB 2: ESTADO DE CUENTA -->
    <div class="hidden" id="estado" role="tabpanel" aria-labelledby="estado-tab">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            
            <!-- CRONOGRAMA -->
            <div class="bg-gray-800 p-6 rounded-lg border border-gray-700 shadow-xl flex flex-col h-[600px]">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold text-gray-200">Cronograma</h3>
                    <span class="text-xs font-mono bg-gray-900 text-gray-400 px-2 py-1 rounded"><?= count($cronograma) ?> cuotas</span>
                </div>
                
                <div class="overflow-y-auto flex-1 pr-2 custom-scrollbar">
                    <table class="min-w-full text-sm text-left">
                        <thead class="text-xs text-gray-400 uppercase bg-gray-700 sticky top-0">
                            <tr>
                                <th class="px-4 py-3 rounded-tl-lg">#</th>
                                <th class="px-4 py-3">Vencimiento</th>
                                <th class="px-4 py-3 text-right">Monto</th>
                                <th class="px-4 py-3 text-center rounded-tr-lg">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700">
                            <?php foreach ($cronograma as $cuota): ?>
                            <tr class="hover:bg-gray-700/50 transition-colors">
                                <td class="px-4 py-3 font-medium text-gray-300"><?= $cuota['numero_cuota'] ?></td>
                                <td class="px-4 py-3 text-gray-400"><?= (new DateTime($cuota['fecha_vencimiento']))->format('d/m/Y') ?></td>
                                <td class="px-4 py-3 text-right font-mono text-gray-300"><?= formatCurrency($cuota['monto_cuota']) ?></td>
                                <td class="px-4 py-3 text-center">
                                    <?php if ($cuota['estado'] === 'Pagado'): ?>
                                        <span class="px-2 py-1 rounded-full text-xs font-bold bg-green-900/50 text-green-400 border border-green-800">PAGADO</span>
                                    <?php elseif ($cuota['estado'] === 'Pago Parcial'): ?>
                                        <span class="px-2 py-1 rounded-full text-xs font-bold bg-yellow-900/50 text-yellow-400 border border-yellow-800">PARCIAL</span>
                                    <?php else: ?>
                                        <span class="px-2 py-1 rounded-full text-xs font-bold bg-gray-700 text-gray-400">PENDIENTE</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- PAGOS REALIZADOS -->
            <div class="bg-gray-800 p-6 rounded-lg border border-gray-700 shadow-xl flex flex-col h-[600px]">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold text-gray-200">Historial de Pagos</h3>
                    <!-- Print Button -->
                    <button onclick="window.print()" class="text-gray-400 hover:text-white transition-colors" title="Imprimir Comprobante">
                        <i class="fas fa-print fa-lg"></i>
                    </button>
                </div>

                <div class="overflow-y-auto flex-1 pr-2 custom-scrollbar">
                    <table class="min-w-full text-sm text-left">
                        <thead class="text-xs text-gray-400 uppercase bg-gray-700 sticky top-0">
                            <tr>
                                <th class="px-4 py-3 rounded-tl-lg">Fecha</th>
                                <th class="px-4 py-3 text-right">Monto</th>
                                <th class="px-4 py-3 text-center rounded-tr-lg">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700">
                            <?php if(empty($pagos)): ?>
                                <tr><td colspan="3" class="px-4 py-10 text-center text-gray-500 italic">No hay pagos registrados.</td></tr>
                            <?php else: ?>
                                <?php foreach ($pagos as $pago): ?>
                                <tr class="hover:bg-gray-700/50 transition-colors group">
                                    <td class="px-4 py-3 text-gray-300"><?= (new DateTime($pago['fecha_pago']))->format('d/m/Y') ?></td>
                                    <td class="px-4 py-3 text-right font-bold text-green-400"><?= formatCurrency($pago['monto_pagado']) ?></td>
                                    <td class="px-4 py-3 text-center">
                                        <form method="POST" action="index.php?page=editar_cliente&id=<?= $credito_id ?>" class="delete-payment-form">
                                            <input type="hidden" name="pago_id" value="<?= $pago['id'] ?>">
                                            <input type="hidden" name="eliminar_pago" value="1">
                                            <button type="button" class="text-gray-500 hover:text-red-500 transition-colors p-2 rounded hover:bg-red-900/20 btn-delete-payment" title="Eliminar Pago">
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
                
                <div class="mt-4 p-4 bg-gray-900/50 rounded-lg border border-gray-700">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-400">Total Recaudado:</span>
                        <span class="text-xl font-bold text-green-400"><?= formatCurrency($total_pagado_real) ?></span>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // --- TABS LOGIC ---
    const tabs = [
        { trigger: document.getElementById('datos-tab'), target: document.getElementById('datos') },
        { trigger: document.getElementById('estado-tab'), target: document.getElementById('estado') }
    ];

    function activateTab(tabId) {
        tabs.forEach(t => {
            const isTarget = t.trigger.id === tabId;
            // Toggle Content
            t.target.classList.toggle('hidden', !isTarget);
            // Toggle Styles
            if (isTarget) {
                t.trigger.classList.add('text-blue-500', 'border-blue-500');
                t.trigger.classList.remove('text-gray-400', 'border-transparent', 'hover:text-gray-300');
                t.trigger.setAttribute('aria-selected', 'true');
            } else {
                t.trigger.classList.remove('text-blue-500', 'border-blue-500');
                t.trigger.classList.add('text-gray-400', 'border-transparent', 'hover:text-gray-300');
                t.trigger.setAttribute('aria-selected', 'false');
            }
        });
    }

    tabs.forEach(t => {
        t.trigger.addEventListener('click', () => activateTab(t.trigger.id));
    });

    // --- FORM LOGIC (Visibility of fields) ---
    const zonaSelect = document.getElementById('zona');
    const frecuenciaSelect = document.getElementById('frecuencia');
    const diaPagoContainer = document.getElementById('dia_pago_container');
    const diaVencContainer = document.getElementById('dia_vencimiento_container');
    const diaPagoSelect = document.getElementById('dia_pago');
    const diaVencInput = document.getElementById('dia_vencimiento');

    function toggleFields() {
        const f = frecuenciaSelect.value;
        const z = zonaSelect.value;
        
        diaPagoContainer.classList.add('hidden');
        diaVencContainer.classList.add('hidden');
        diaPagoSelect.required = false;
        diaVencInput.required = false;

        if (f === 'Semanal') {
            if (z != 4) {
               diaPagoContainer.classList.remove('hidden');
               diaPagoSelect.required = true;
            }
        } else if (f === 'Mensual') {
            diaVencContainer.classList.remove('hidden');
            diaVencInput.required = true;
        }
    }

    zonaSelect.addEventListener('change', toggleFields);
    frecuenciaSelect.addEventListener('change', toggleFields);
    // Init
    toggleFields();

    // --- SWEETALERT DELETE CONFIRMATION ---
    document.querySelectorAll('.btn-delete-payment').forEach(btn => {
        btn.addEventListener('click', function() {
            const form = this.closest('form');
            Swal.fire({
                title: '¿Estás seguro?',
                text: "Esta acción eliminará el pago y recalculará todo el cronograma de cuotas. ¡No se puede deshacer!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                background: '#1f2937',
                color: '#fff'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });
});
</script>

<style>
/* Custom Scrollbar for tables */
.custom-scrollbar::-webkit-scrollbar { width: 6px; }
.custom-scrollbar::-webkit-scrollbar-track { background: #1f2937; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #4b5563; border-radius: 3px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #6b7280; }
</style>