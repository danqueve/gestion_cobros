<?php
// --- LÓGICA DE LA VISTA DE GESTIÓN DE CRÉDITOS (MEJORADA) ---

// NOTA: No incluimos 'config.php' aquí porque ya está cargado en index.php

// Parámetros de búsqueda y paginación iniciales
$search_term = $_GET['search'] ?? '';
$nombres_zonas = [
    1 => 'Santi',
    2 => 'Juan Pablo',
    3 => 'Enzo',
    4 => 'Tafi del V',
    5 => 'Famailla',
    6 => 'Sgo'
];

$limit_options = [10, 20, 50, 100];
$limit = isset($_GET['limit']) && in_array($_GET['limit'], $limit_options) ? (int)$_GET['limit'] : 10;
$page_num = isset($_GET['p']) && $_GET['p'] > 0 ? (int)$_GET['p'] : 1;
$offset = ($page_num - 1) * $limit;

// --- PROCESAR PAGO RÁPIDO (MISMA LÓGICA ROBUSTA) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['pago_rapido'])) {
    if (!isset($_SESSION['user_id'])) {
        $error = "Error de sesión: No se pudo identificar al usuario cobrador.";
    } else {
        $credito_id = $_POST['credito_id_pago'];
        $monto_cobrado = filter_input(INPUT_POST, 'monto_cobrado', FILTER_VALIDATE_FLOAT);
        $fecha_pago_str = $_POST['fecha_pago'] ?? date('Y-m-d');
        $usuario_id = $_SESSION['user_id'];

        if ($monto_cobrado > 0) {
            try {
                $pdo->beginTransaction();

                // Verificar usuario
                $stmt_check_user = $pdo->prepare("SELECT id FROM usuarios WHERE id = ?");
                $stmt_check_user->execute([$usuario_id]);
                if (!$stmt_check_user->fetch()) throw new Exception("Usuario inválido.");

                // Registrar pago
                $stmt_pago = $pdo->prepare("INSERT INTO pagos (credito_id, usuario_id, monto_pagado, fecha_pago) VALUES (?, ?, ?, ?)");
                $stmt_pago->execute([$credito_id, $usuario_id, $monto_cobrado, $fecha_pago_str]);

                // Aplicar al cronograma
                $monto_restante = $monto_cobrado;
                $stmt_cuotas = $pdo->prepare("SELECT * FROM cronograma_cuotas WHERE credito_id = ? AND estado IN ('Pendiente', 'Pago Parcial') ORDER BY numero_cuota ASC");
                $stmt_cuotas->execute([$credito_id]);

                while ($monto_restante > 0 && ($cuota = $stmt_cuotas->fetch(PDO::FETCH_ASSOC))) {
                    $faltante = $cuota['monto_cuota'] - $cuota['monto_pagado'];
                    if (bccomp($monto_restante, $faltante, 2) >= 0) {
                        $imputar = $faltante;
                        $nuevo_estado = 'Pagado';
                    } else {
                        $imputar = $monto_restante;
                        $nuevo_estado = 'Pago Parcial';
                    }
                    $pdo->prepare("UPDATE cronograma_cuotas SET monto_pagado = monto_pagado + ?, estado = ? WHERE id = ?")->execute([$imputar, $nuevo_estado, $cuota['id']]);
                    $monto_restante -= $imputar;
                }

                // Actualizar crédito principal
                $total_pagadas = $pdo->query("SELECT COUNT(id) FROM cronograma_cuotas WHERE credito_id = $credito_id AND estado = 'Pagado'")->fetchColumn();
                $total_cuotas = $pdo->query("SELECT total_cuotas FROM creditos WHERE id = $credito_id")->fetchColumn();
                $estado_credito = ($total_pagadas >= $total_cuotas) ? 'Pagado' : 'Activo';
                
                $pdo->prepare("UPDATE creditos SET cuotas_pagadas = ?, ultimo_pago = ?, estado = ? WHERE id = ?")->execute([$total_pagadas, $fecha_pago_str, $estado_credito, $credito_id]);

                $pdo->commit();
                $success = "¡Pago registrado correctamente!";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Error al registrar el pago: " . $e->getMessage();
            }
        } else {
             $error = "El monto debe ser mayor a 0.";
        }
    }
}

// --- CONSULTA INICIAL (SOLO PARA CARGA DE PÁGINA) ---
try {
    $sql_count = "SELECT COUNT(cr.id) FROM creditos cr JOIN clientes c ON cr.cliente_id = c.id WHERE c.nombre LIKE :search";
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute([':search' => '%' . $search_term . '%']);
    $total_records = $stmt_count->fetchColumn();
    $total_pages = ceil($total_records / $limit);
    
    if ($page_num > $total_pages && $total_pages > 0) {
        $page_num = $total_pages;
        $offset = ($page_num - 1) * $limit;
    }

    // Consulta completa con todos los campos requeridos
    $sql = "SELECT 
                cr.id as credito_id,
                c.id as cliente_id,
                c.nombre,
                cr.zona,
                cr.frecuencia,
                cr.dia_pago,
                cr.dia_vencimiento,
                cr.estado,
                cr.monto_cuota,
                cr.cuotas_pagadas,
                cr.total_cuotas,
                (SELECT (monto_cuota - monto_pagado) FROM cronograma_cuotas WHERE credito_id = cr.id AND estado != 'Pagado' ORDER BY numero_cuota ASC LIMIT 1) as saldo_pendiente
            FROM creditos cr
            JOIN clientes c ON cr.cliente_id = c.id
            WHERE c.nombre LIKE :search
            ORDER BY c.nombre ASC, cr.id DESC 
            LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':search', '%' . $search_term . '%', PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $creditos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error al obtener los créditos: " . $e->getMessage());
}
?>

<h2 class="text-2xl font-bold text-gray-200 mb-4">Gestión de Créditos</h2>

<?php if(!empty($success)): ?>
    <div class="bg-green-900 border border-green-700 text-green-200 px-4 py-3 rounded-md mb-4" role="alert"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if(!empty($error)): ?>
    <div class="bg-red-900 border border-red-700 text-red-200 px-4 py-3 rounded-md mb-4" role="alert"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Barra de Búsqueda y Botón de Agregar -->
<div class="flex flex-col sm:flex-row justify-between items-center mb-4 gap-4 no-print">
    <div class="w-full sm:w-1/2 lg:w-1/3">
        <div class="relative">
            <!-- Input con evento para búsqueda en tiempo real -->
            <input type="text" id="search-input" name="search" class="w-full pl-10 pr-4 py-2 rounded-lg form-element-dark" placeholder="Buscar por nombre de cliente..." value="<?= htmlspecialchars($search_term) ?>" autocomplete="off">
            <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
        </div>
    </div>
    <a href="index.php?page=agregar_cliente" class="w-full sm:w-auto bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg transition duration-300 flex-shrink-0">
        <i class="fas fa-plus mr-2"></i>Agregar Cliente
    </a>
</div>

<!-- Controles de Paginación -->
<div class="flex flex-col sm:flex-row justify-between items-center mb-4 gap-4 no-print">
    <div id="results-counter" class="text-sm text-gray-400">
        Mostrando <?= ($total_records > 0) ? $offset + 1 : 0 ?> a <?= min($offset + $limit, $total_records) ?> de <?= $total_records ?> créditos
    </div>
    <div class="flex items-center gap-2">
        <label for="limit-select" class="text-sm text-gray-400">Mostrar:</label>
        <select id="limit-select" name="limit" class="text-sm rounded-md form-element-dark">
            <?php foreach($limit_options as $option): ?>
                <option value="<?= $option ?>" <?= $limit == $option ? 'selected' : '' ?>><?= $option ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<!-- Tabla de Créditos -->
<div class="overflow-x-auto rounded-lg shadow border border-gray-700">
    <table class="min-w-full divide-y divide-gray-700">
        <thead class="table-header-custom">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Cliente</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-300 uppercase tracking-wider">Zona</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-300 uppercase tracking-wider">Frecuencia</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-300 uppercase tracking-wider">Día Pago</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-300 uppercase tracking-wider">Estado</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-300 uppercase tracking-wider">Monto Cuota</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-300 uppercase tracking-wider">Venc. Cuotas</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-300 uppercase tracking-wider no-print">Acciones</th>
            </tr>
        </thead>
        <tbody id="clientes-table-body" class="divide-y divide-gray-700">
            <?php if (empty($creditos)): ?>
                <tr><td colspan="8" class="px-6 py-12 text-center text-gray-400 table-row-dark"><i class="fas fa-search-minus fa-3x mb-3"></i><p>No se encontraron créditos.</p></td></tr>
            <?php else: ?>
                <?php foreach ($creditos as $credito): 
                    $dia_cobro = ($credito['frecuencia'] == 'Semanal') ? $credito['dia_pago'] : 'Día ' . $credito['dia_vencimiento'];
                ?>
                <tr class="table-row-dark hover:bg-gray-700/50">
                    <td class="px-4 py-4 whitespace-nowrap font-medium text-gray-100">
                        <a href="index.php?page=editar_cliente&id=<?= $credito['credito_id'] ?>" class="hover:underline">
                            <?= htmlspecialchars($credito['nombre']) ?>
                        </a>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap text-center text-gray-300"><?= htmlspecialchars($nombres_zonas[$credito['zona']] ?? 'N/A') ?></td>
                    <td class="px-4 py-4 whitespace-nowrap text-center text-gray-300"><?= htmlspecialchars($credito['frecuencia']) ?></td>
                    <td class="px-4 py-4 whitespace-nowrap text-center text-gray-300"><?= htmlspecialchars($dia_cobro ?? '-') ?></td>
                    <td class="px-4 py-4 whitespace-nowrap text-center">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $credito['estado'] == 'Pagado' ? 'bg-green-900 text-green-300' : 'bg-blue-900 text-blue-300' ?>">
                            <?= htmlspecialchars($credito['estado']) ?>
                        </span>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap text-right text-gray-300"><?= formatCurrency($credito['monto_cuota']) ?></td>
                    <td class="px-4 py-4 whitespace-nowrap text-center text-gray-300">
                        <?= $credito['cuotas_pagadas'] ?> / <?= $credito['total_cuotas'] ?>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap text-center no-print flex justify-center gap-2">
                        <!-- BOTÓN DE PAGO RÁPIDO -->
                        <?php if ($credito['estado'] != 'Pagado'): ?>
                        <button onclick="abrirModalPago(<?= $credito['credito_id'] ?>, '<?= htmlspecialchars($credito['nombre'], ENT_QUOTES) ?>', <?= $credito['saldo_pendiente'] ?: 0 ?>)" class="text-green-500 hover:text-green-400" title="Pago Rápido">
                            <i class="fas fa-money-bill-wave"></i>
                        </button>
                        <?php endif; ?>
                        
                        <a href="index.php?page=editar_cliente&id=<?= $credito['credito_id'] ?>" class="text-blue-400 hover:text-blue-300" title="Ver Detalle / Editar"><i class="fas fa-pencil-alt"></i></a>
                        <a href="index.php?page=eliminar_cliente&id=<?= $credito['cliente_id'] ?>" class="text-red-500 hover:text-red-400" title="Eliminar" onclick="return confirm('¿Estás seguro?')"><i class="fas fa-trash-alt"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Navegación de Paginación -->
<div id="pagination-container" class="mt-6 flex justify-center items-center gap-2 no-print">
    <?php echo generarPaginacion($page_num, $total_pages, 2); ?>
</div>

<!-- MODAL DE PAGO RÁPIDO -->
<div id="modalPago" class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center hidden z-50">
    <div class="bg-gray-800 p-6 rounded-lg border border-gray-700 w-full max-w-md shadow-xl">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-white">Registrar Pago</h3>
            <button onclick="cerrarModalPago()" class="text-gray-400 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" action="index.php?page=clientes">
            <input type="hidden" name="pago_rapido" value="1">
            <input type="hidden" id="modal_credito_id" name="credito_id_pago">
            
            <p class="text-gray-300 mb-2">Cliente: <span id="modal_cliente_nombre" class="font-semibold text-white"></span></p>
            
            <div class="mb-4">
                <label for="modal_monto" class="block text-sm font-medium text-gray-300 mb-1">Monto a Abonar:</label>
                <input type="number" step="0.01" id="modal_monto" name="monto_cobrado" class="w-full rounded-md form-element-dark p-2 text-white" required>
                <p class="text-xs text-gray-500 mt-1">Saldo sugerido: <span id="modal_saldo"></span></p>
            </div>

            <div class="mb-6">
                <label for="modal_fecha" class="block text-sm font-medium text-gray-300 mb-1">Fecha de Pago:</label>
                <input type="date" id="modal_fecha" name="fecha_pago" class="w-full rounded-md form-element-dark p-2 text-white" value="<?= date('Y-m-d') ?>" required>
            </div>

            <div class="flex justify-end gap-3">
                <button type="button" onclick="cerrarModalPago()" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-md">Cancelar</button>
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-md">Confirmar Pago</button>
            </div>
        </form>
    </div>
</div>

<script>
// --- FUNCIONES DEL MODAL DE PAGO ---
function abrirModalPago(id, nombre, saldo) {
    document.getElementById('modal_credito_id').value = id;
    document.getElementById('modal_cliente_nombre').textContent = nombre;
    document.getElementById('modal_monto').value = saldo; 
    document.getElementById('modal_saldo').textContent = new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(saldo);
    document.getElementById('modalPago').classList.remove('hidden');
    document.getElementById('modal_monto').focus(); 
}

function cerrarModalPago() {
    document.getElementById('modalPago').classList.add('hidden');
}

// --- SCRIPT DE BÚSQUEDA EN TIEMPO REAL (AJAX) ---
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('search-input');
    const limitSelect = document.getElementById('limit-select');
    const tableBody = document.getElementById('clientes-table-body');
    const paginationContainer = document.getElementById('pagination-container');
    const resultsCounter = document.getElementById('results-counter');
    
    let currentPage = 1;
    let debounceTimer;

    // Función para realizar la búsqueda AJAX
    function fetchCreditos() {
        const searchTerm = searchInput.value;
        const limit = limitSelect.value;
        
        // Indicador de carga sutil en la tabla
        tableBody.style.opacity = '0.5';

        // IMPORTANTE: Se llama a 'views/buscar_clientes.php' que debe devolver JSON
        fetch(`views/buscar_clientes.php?search=${encodeURIComponent(searchTerm)}&limit=${limit}&p=${currentPage}`)
            .then(response => {
                if (!response.ok) throw new Error('Error en la red');
                return response.json();
            })
            .then(data => {
                if(data.error) {
                    tableBody.innerHTML = `<tr><td colspan="8" class="px-6 py-12 text-center text-red-400">${data.error}</td></tr>`;
                    return;
                }
                tableBody.innerHTML = data.tableBody;
                paginationContainer.innerHTML = data.pagination;
                resultsCounter.innerHTML = data.resultsCounter;
            })
            .catch(error => {
                console.error('Error:', error);
                tableBody.innerHTML = '<tr><td colspan="8" class="px-6 py-12 text-center text-red-400">Ocurrió un error al buscar.</td></tr>';
            })
            .finally(() => {
                tableBody.style.opacity = '1';
            });
    }

    // Debounce para no saturar el servidor al escribir
    function debounce(func, delay) {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(func, delay);
    }

    // Evento al escribir (Búsqueda en tiempo real)
    searchInput.addEventListener('keyup', () => {
        currentPage = 1; // Resetear a página 1 al buscar
        debounce(fetchCreditos, 300); // Esperar 300ms
    });

    // Evento al cambiar el límite de registros
    limitSelect.addEventListener('change', () => {
        currentPage = 1;
        fetchCreditos();
    });

    // Evento para la paginación (Delegación)
    paginationContainer.addEventListener('click', function(e) {
        e.preventDefault();
        const target = e.target.closest('.page-link');
        if (target && target.dataset.page) {
            const page = parseInt(target.dataset.page, 10);
            if (page > 0) {
                currentPage = page;
                fetchCreditos();
            }
        }
    });
});
</script>