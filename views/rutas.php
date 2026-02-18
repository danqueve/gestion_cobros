<?php
// --- LÓGICA DE LA VISTA DE RUTAS CON LÓGICA CENTRALIZADA (OPTIMIZADA) ---

// 1. Procesamiento de Pagos
$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['registrar_pago'])) {
    $credito_id = $_POST['credito_id'];
    $fecha_pago_str = $_POST['fecha_pago'] ?? date('Y-m-d');
    if(empty($fecha_pago_str)) $fecha_pago_str = date('Y-m-d');
    $monto_cobrado = filter_input(INPUT_POST, 'monto_cobrado', FILTER_VALIDATE_FLOAT);

    try {
        if (registrarPago($pdo, $credito_id, $monto_cobrado, $_SESSION['user_id'], $fecha_pago_str)) {
            $success = "¡Pago registrado correctamente!";
        }
    } catch (Exception $e) {
        $error = "Error al registrar: " . $e->getMessage();
    }
}

// 2. Filtros
$zona_seleccionada = $_GET['zona'] ?? 1;
$dia_seleccionado = $_GET['dia'] ?? 'Lunes';

// --- NUEVO: VERIFICACIÓN DE PERMISOS ---
// Si el usuario intenta acceder a una zona que no tiene permitida, lo redirigimos o mostramos error.
// (Excepto si zona_seleccionada es 'all' y filtramos en la query, pero aquí parece que siempre se selecciona una zona específica)
if (!tienePermisoZona($zona_seleccionada)) {
    // Si no tiene permiso, intentamos asignarle la primera zona que sí tenga permitida
    if (!esAdmin() && !empty($_SESSION['zonas_asignadas'])) {
        $zonas_permitidas = explode(',', $_SESSION['zonas_asignadas']);
        $zona_seleccionada = $zonas_permitidas[0]; // Forzar la primera zona válida
    } else {
        // Si no tiene zonas o es admin (y algo falló), default a 1
        $zona_seleccionada = 1; 
    }
}

// 3. Consulta Optimizada
try {
    $sql = "SELECT 
                c.id AS cliente_id,
                c.nombre, 
                cr.id AS credito_id,
                cr.monto_cuota,
                cr.total_cuotas,
                cr.cuotas_pagadas,
                cr.frecuencia,
                MIN(cc.fecha_vencimiento) as proximo_vencimiento,
                SUM(cc.monto_cuota - cc.monto_pagado) as saldo_total_pendiente,
                (
                    SELECT (cc2.monto_cuota - cc2.monto_pagado) 
                    FROM cronograma_cuotas cc2 
                    WHERE cc2.credito_id = cr.id AND cc2.estado != 'Pagado' 
                    ORDER BY cc2.fecha_vencimiento ASC, cc2.numero_cuota ASC 
                    LIMIT 1
                ) as saldo_cuota_pendiente
            FROM creditos cr 
            JOIN clientes c ON cr.cliente_id = c.id 
            JOIN cronograma_cuotas cc ON cr.id = cc.credito_id
            WHERE cr.zona = ? 
              AND cr.estado = 'Activo'
              AND cc.estado != 'Pagado'";

    // Aplicar Filtro de Permisos (Redundancia de seguridad)
    // Aunque ya forzamos $zona_seleccionada arriba, esto asegura que la query nunca traiga datos prohibidos
    $sql .= getFiltroZonasSQL('cr.zona');

    $params = [$zona_seleccionada];

    // Filtro por Día
    // CORRECCIÓN: He eliminado la condición `if (!in_array($zona_seleccionada, [4, 5, 6]))`
    // Ahora el filtro de día se aplica SIEMPRE, permitiendo elegir día para cualquier zona.
    // Si realmente esas zonas NO deben tener filtro de día, puedes descomentar la línea de abajo.
    // if (!in_array($zona_seleccionada, [4, 5, 6])) { 
        $sql .= " AND ( (cr.frecuencia = 'Semanal' AND cr.dia_pago = ?) OR (cr.frecuencia IN ('Quincenal', 'Mensual')) )";
        $params[] = $dia_seleccionado;
    // }

    $sql .= " GROUP BY cr.id ORDER BY c.nombre ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $clientes_filtrados = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "<div class='bg-red-500 text-white p-4 rounded'>Error BD: " . $e->getMessage() . "</div>";
    $clientes_filtrados = [];
}

// 4. Cálculos para Summary Cards (Por Frecuencia)
$total_semanal = 0;
$total_quincenal = 0;
$total_mensual = 0;

foreach ($clientes_filtrados as $c) {
    // Se cobra lo que diga el saldo de la cuota pendiente, 
    // o el monto de cuota regular si por error no vino el dato
    $monto = $c['saldo_cuota_pendiente'] ?? $c['monto_cuota'];
    
    // Sumar según frecuencia
    // Normalizamos a minúsculas por si acaso, aunque en BD suelen estar Capitalizadas (Semanal, Quincenal, Mensual)
    $freq = strtolower($c['frecuencia']);
    
    if ($freq === 'semanal') {
        $total_semanal += $monto;
    } elseif ($freq === 'quincenal') {
        $total_quincenal += $monto;
    } elseif ($freq === 'mensual') {
        $total_mensual += $monto;
    }
}
?>

<!-- SweetAlert2 Injection -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php if($success): ?>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        Swal.fire({
            icon: 'success',
            title: '¡Excelente!',
            text: '<?= addslashes($success) ?>',
            timer: 2000,
            showConfirmButton: false,
            background: '#1f2937',
            color: '#fff'
        });
    });
</script>
<?php endif; ?>
<?php if($error): ?>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: '<?= addslashes($error) ?>',
            background: '#1f2937',
            color: '#fff'
        });
    });
</script>
<?php endif; ?>


<!-- CONTENIDO PRINCIPAL -->
<div class="max-w-7xl mx-auto space-y-6">
    
    <!-- Header y Filtros -->
    <div class="bg-gray-800 p-6 rounded-xl shadow-lg border border-gray-700 flex flex-col lg:flex-row justify-between items-center gap-6 no-print">
        <div class="flex-1 w-full lg:w-auto">
            <h2 class="text-2xl font-bold text-white tracking-tight flex items-center gap-2">
                <i class="fas fa-route text-blue-500"></i> Rutas de Cobro
            </h2>
            <p class="text-gray-400 text-sm mt-1">Administra la cobranza diaria por zona.</p>
        </div>

        <form method="GET" action="index.php" class="flex flex-col sm:flex-row items-end gap-4 w-full lg:w-auto">
            <input type="hidden" name="page" value="rutas">
            
            <div class="w-full sm:w-40">
                <label for="zona" class="block text-xs font-semibold text-gray-400 mb-1 uppercase">Zona</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-map-marker-alt text-gray-500"></i>
                    </div>
                    <select id="zona" name="zona" onchange="this.form.submit()" class="block w-full pl-10 pr-10 py-2 bg-gray-700 border-gray-600 text-white text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 appearance-none">
                        <?php foreach($NOMBRES_ZONAS as $num => $nombre): ?>
                            <?php 
                            // Mostrar solo las zonas permitidas en el select
                            if(tienePermisoZona($num)): 
                            ?>
                                <option value="<?= $num ?>" <?= $zona_seleccionada == $num ? 'selected' : '' ?>><?= htmlspecialchars($nombre) ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <!-- Selector de Día (Ahora visible siempre para todas las zonas) -->
            <div class="w-full sm:w-40">
                <label for="dia" class="block text-xs font-semibold text-gray-400 mb-1 uppercase">Día</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-calendar-day text-gray-500"></i>
                    </div>
                    <select id="dia" name="dia" class="block w-full pl-10 pr-10 py-2 bg-gray-700 border-gray-600 text-white text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 appearance-none">
                        <?php foreach ($DIAS_SEMANA as $d): ?>
                            <option value="<?= $d ?>" <?= $dia_seleccionado == $d ? 'selected' : '' ?>><?= $d ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <button type="submit" class="w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded-lg shadow-md transition-all flex items-center justify-center gap-2 h-[38px]">
                <i class="fas fa-filter"></i> Filtrar
            </button>
        </form>
        
        <?php 
            $print_link = "views/imprimir_planilla.php?zona=" . $zona_seleccionada . "&dia=" . urlencode($dia_seleccionado);
        ?>
        <a href="<?= $print_link ?>" target="_blank" class="w-full lg:w-auto bg-gray-700 hover:bg-gray-600 text-white font-medium py-2 px-6 rounded-lg border border-gray-600 transition-colors flex items-center justify-center gap-2 shadow-sm h-[38px]">
            <i class="fas fa-print"></i> Planilla
        </a>
    </div>

    <!-- Stats Cards (MODIFICADO: Semanal, Quincenal, Mensual) -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 no-print">
        <!-- Semanal (Antes: Estimado) -->
        <div class="bg-gradient-to-br from-gray-800 to-gray-900 p-6 rounded-xl border border-gray-700 shadow-lg relative overflow-hidden group">
            <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                <i class="fas fa-calendar-week fa-4x text-blue-400"></i>
            </div>
            <p class="text-sm font-medium text-blue-400 uppercase tracking-wider mb-1">Semanal</p>
            <h3 class="text-3xl font-extrabold text-white"><?= formatCurrency($total_semanal) ?></h3>
            <div class="mt-4 h-1 w-full bg-gray-700 rounded-full overflow-hidden">
                <div class="h-full bg-blue-500 w-full opacity-75"></div>
            </div>
        </div>

        <!-- Quincenales (Antes: Cobrado Hoy) -->
        <div class="bg-gradient-to-br from-gray-800 to-gray-900 p-6 rounded-xl border border-gray-700 shadow-lg relative overflow-hidden group">
            <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                <i class="fas fa-calendar-check fa-4x text-orange-400"></i>
            </div>
            <p class="text-sm font-medium text-orange-400 uppercase tracking-wider mb-1">Quincenales</p>
            <h3 class="text-3xl font-extrabold text-white"><?= formatCurrency($total_quincenal) ?></h3>
            <div class="mt-4 h-1 w-full bg-gray-700 rounded-full overflow-hidden">
                <div class="h-full bg-orange-500 w-full opacity-75"></div>
            </div>
        </div>

        <!-- Mensuales (Antes: Faltante) -->
        <div class="bg-gradient-to-br from-gray-800 to-gray-900 p-6 rounded-xl border border-gray-700 shadow-lg relative overflow-hidden group">
            <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                <i class="fas fa-calendar-alt fa-4x text-purple-400"></i>
            </div>
            <p class="text-sm font-medium text-purple-400 uppercase tracking-wider mb-1">Mensuales</p>
            <h3 class="text-3xl font-extrabold text-white"><?= formatCurrency($total_mensual) ?></h3>
             <div class="mt-4 h-1 w-full bg-gray-700 rounded-full overflow-hidden">
                <div class="h-full bg-purple-500 w-full opacity-75"></div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-gray-800 rounded-xl shadow-2xl border border-gray-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-900/50 text-gray-400 text-xs uppercase tracking-wider border-b border-gray-700">
                        <th class="px-6 py-4 font-medium">Cliente</th>
                        <th class="px-6 py-4 font-medium text-center">Progreso</th>
                        <th class="px-6 py-4 font-medium text-center">Vencimiento</th>
                        <th class="px-6 py-4 font-medium text-right">Saldo Cuota</th>
                        <th class="px-6 py-4 font-medium text-right">Deuda Total</th>
                        <th class="px-6 py-4 font-medium text-right">Valor Cuota</th>
                        <th class="px-6 py-4 font-medium text-center">Estado</th>
                        <th class="px-6 py-4 font-medium text-center no-print">Acción Rápida</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700 text-gray-300">
                    <?php if (empty($clientes_filtrados)): ?>
                        <tr>
                            <td colspan="8" class="px-6 py-16 text-center text-gray-500">
                                <div class="flex flex-col items-center justify-center">
                                    <div class="bg-gray-700/50 p-4 rounded-full mb-3">
                                        <i class="fas fa-clipboard-check text-green-500 text-3xl"></i>
                                    </div>
                                    <p class="text-lg font-medium text-gray-300">Ruta Completada</p>
                                    <p class="text-sm">No hay cobros pendientes para los filtros seleccionados.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($clientes_filtrados as $cliente): 
                            // Lógica de estado local (para visualización rápida)
                            $dias_atraso = 0;
                            $es_atrasado = false;
                            if ($cliente['proximo_vencimiento']) {
                                $venc = new DateTime($cliente['proximo_vencimiento']);
                                $hoy = new DateTime();
                                if ($hoy > $venc) {
                                    $es_atrasado = true;
                                    $dias_atraso = $hoy->diff($venc)->days;
                                }
                            }
                        ?>
                        <tr class="hover:bg-gray-700/50 transition-colors group">
                            <!-- Cliente -->
                            <td class="px-6 py-4">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10 rounded-full bg-gray-700 flex items-center justify-center text-indigo-400 font-bold border border-gray-600 shadow-sm">
                                        <?= strtoupper(substr($cliente['nombre'], 0, 1)) ?>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-white font-medium group-hover:text-blue-400 transition-colors">
                                            <?= htmlspecialchars($cliente['nombre']) ?>
                                        </div>
                                        <div class="text-xs text-gray-500">ID: <?= $cliente['credito_id'] ?></div>
                                    </div>
                                </div>
                            </td>
                            
                            <!-- Progreso -->
                            <td class="px-6 py-4 text-center">
                                <div class="inline-flex items-center justify-center px-2.5 py-0.5 rounded-full bg-gray-700 text-xs font-medium text-gray-300 border border-gray-600">
                                    <?= $cliente['cuotas_pagadas'] ?> / <?= $cliente['total_cuotas'] ?>
                                </div>
                            </td>

                            <!-- Vencimiento -->
                            <td class="px-6 py-4 text-center text-sm">
                                <?= $cliente['proximo_vencimiento'] ? (new DateTime($cliente['proximo_vencimiento']))->format('d/m/Y') : '<span class="text-gray-600">-</span>' ?>
                            </td>

                            <!-- Saldo Cuota -->
                            <td class="px-6 py-4 text-right">
                                <span class="text-yellow-400 font-bold text-sm">
                                    <?= formatCurrency($cliente['saldo_cuota_pendiente'] ?? 0) ?>
                                </span>
                            </td>

                            <!-- Deuda Total -->
                            <td class="px-6 py-4 text-right text-sm text-blue-300">
                                <?= formatCurrency($cliente['saldo_total_pendiente'] ?? 0) ?>
                            </td>
                            
                            <!-- Valor Cuota -->
                            <td class="px-6 py-4 text-right text-sm text-gray-400">
                                <?= formatCurrency($cliente['monto_cuota']) ?>
                            </td>

                            <!-- Estado -->
                            <td class="px-6 py-4 text-center">
                                <?php if ($es_atrasado): ?>
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-900/50 text-red-200 border border-red-800">
                                        <?= $dias_atraso ?> días atraso
                                    </span>
                                <?php else: ?>
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-900/50 text-green-200 border border-green-800">
                                        Al día
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Acción Rápida -->
                            <td class="px-6 py-4 text-center no-print">
                                <form action="index.php?page=rutas&zona=<?= $zona_seleccionada ?>&dia=<?= urlencode($dia_seleccionado) ?>" method="POST" class="flex items-center justify-center gap-2">
                                    <input type="hidden" name="credito_id" value="<?= $cliente['credito_id'] ?>"> <!-- USAR ID DE CREDITO, NO CLIENTE -->
                                    
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-2 flex items-center pointer-events-none">
                                            <span class="text-gray-500 text-xs">$</span>
                                        </div>
                                        <input type="number" step="0.01" name="monto_cobrado" 
                                               class="block w-24 pl-5 pr-2 py-1.5 bg-gray-700 border-gray-600 text-white rounded-md text-sm placeholder-gray-500 focus:ring-blue-500 focus:border-blue-500 transition-all" 
                                               placeholder="<?= number_format($cliente['saldo_cuota_pendiente'] ?? 0, 2, '.', '') ?>" 
                                               autocomplete="off">
                                    </div>

                                    <button type="submit" name="registrar_pago" class="bg-green-600 hover:bg-green-700 text-white p-1.5 rounded-md shadow transition-colors" title="Cobrar">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    
                                    <a href="index.php?page=editar_cliente&id=<?= $cliente['credito_id'] ?>" class="bg-gray-700 hover:bg-gray-600 text-blue-400 p-1.5 rounded-md shadow transition-colors" title="Ver Detalle">
                                        <i class="fas fa-eye"></i>
                                    </a>
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