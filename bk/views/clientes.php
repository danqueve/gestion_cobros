<?php
// --- LÓGICA DE LA VISTA DE GESTIÓN DE CRÉDITOS ---

// NOTA: config.php ya está cargado y expone renderCreditoRow(), $NOMBRES_ZONAS,
//       registrarPago(), generarPaginacion() y formatCurrency().

// --- PARÁMETROS DE ENTRADA ---
$search_term   = $_GET['search'] ?? '';
$limit_options = [10, 20, 50, 100];
$limit         = isset($_GET['limit']) && in_array($_GET['limit'], $limit_options) ? (int)$_GET['limit'] : 10;
$page_num      = isset($_GET['p']) && $_GET['p'] > 0 ? (int)$_GET['p'] : 1;
$offset        = ($page_num - 1) * $limit;

// --- PROCESAR PAGO RÁPIDO ---
$error = '';

// =========================================================================
// BUG #5 — Flash message vía sesión
// $success ya no se pasa como variable PHP al template, porque con PRG
// (bug #1) la variable desaparecería al hacer el redirect.
// Se usa $_SESSION['flash_success'] como canal de un solo uso.
// =========================================================================
$success = '';
if (!empty($_SESSION['flash_success'])) {
    $success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);       // leer y destruir — un solo disparo
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['pago_rapido'])) {
    if (!isset($_SESSION['user_id'])) {
        $error = "Error de sesión: no se pudo identificar al usuario cobrador.";
    } else {
        $credito_id     = (int)$_POST['credito_id_pago'];
        $monto_cobrado  = filter_input(INPUT_POST, 'monto_cobrado', FILTER_VALIDATE_FLOAT);
        $fecha_pago_str = $_POST['fecha_pago'] ?? date('Y-m-d');
        if (empty($fecha_pago_str)) $fecha_pago_str = date('Y-m-d');

        try {
            registrarPago($pdo, $credito_id, $monto_cobrado, $_SESSION['user_id'], $fecha_pago_str);

            // =========================================================
            // BUG #1 — Post-Redirect-Get (PRG)
            // Sin el redirect, pulsar F5 reenvía el POST → pago duplicado.
            // El mensaje de éxito viaja en sesión (bug #5).
            //
            // BUG #3 — Preservar contexto de búsqueda post-pago
            // Antes: action="index.php?page=clientes" (sin parámetros)
            //        → el usuario perdía el filtro y la página actual.
            // Ahora: se reconstruye la URL con los mismos search/limit/p
            //        que tenía antes de abrir el modal.
            // =========================================================
            $_SESSION['flash_success'] = "¡Pago registrado correctamente!";

            $redirect_params = http_build_query(array_filter([
                'page'   => 'clientes',
                'search' => $search_term ?: null,
                'limit'  => $limit !== 10 ? $limit : null,   // omitir si es el default
                'p'      => $page_num > 1 ? $page_num : null, // omitir si es la página 1
            ]));
            header("Location: index.php?" . $redirect_params);
            exit;

        } catch (Exception $e) {
            $error = "Error al registrar el pago: " . $e->getMessage();
        }
    }
}

// --- CONSULTA DE DATOS ---
// =========================================================================
// BUG #2 — Variables indefinidas cuando PDO falla
// Antes: $total_records, $total_pages, $offset se usaban en el HTML sin
//        estar definidas si el try/catch capturaba una PDOException.
//        Resultado: PHP Notices + valores vacíos o rotos en la vista.
// Ahora: se inicializan en 0 ANTES del try para que el HTML sea siempre
//        seguro independientemente de lo que ocurra en la consulta.
// =========================================================================
$creditos      = [];
$total_records = 0;
$total_pages   = 0;

try {
    $sql_count = "SELECT COUNT(cr.id)
                  FROM creditos cr
                  JOIN clientes c ON cr.cliente_id = c.id
                  WHERE c.nombre LIKE :search";
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute([':search' => '%' . $search_term . '%']);
    $total_records = (int)$stmt_count->fetchColumn();
    $total_pages   = ($limit > 0) ? (int)ceil($total_records / $limit) : 0;

    // Ajustar página si el filtro dejó menos páginas que la actual
    if ($page_num > $total_pages && $total_pages > 0) {
        $page_num = $total_pages;
        $offset   = ($page_num - 1) * $limit;
    }

    $sql = "SELECT
                cr.id             AS credito_id,
                c.id              AS cliente_id,
                c.nombre,
                cr.zona,
                cr.frecuencia,
                cr.dia_pago,
                cr.dia_vencimiento,
                cr.estado,
                cr.monto_cuota,
                cr.cuotas_pagadas,
                cr.total_cuotas,
                (SELECT (monto_cuota - monto_pagado)
                 FROM cronograma_cuotas
                 WHERE credito_id = cr.id AND estado != 'Pagado'
                 ORDER BY numero_cuota ASC LIMIT 1
                ) AS saldo_pendiente
            FROM creditos cr
            JOIN clientes c ON cr.cliente_id = c.id
            WHERE c.nombre LIKE :search
            ORDER BY c.nombre ASC, cr.id DESC
            LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':search', '%' . $search_term . '%', PDO::PARAM_STR);
    $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $creditos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Error al cargar datos: " . $e->getMessage();
    // $creditos, $total_records y $total_pages ya están en sus valores seguros (0/[])
}
?>

<h2 class="text-2xl font-bold text-gray-200 mb-4">Gestión de Créditos</h2>

<!-- TOAST de notificación -->
<div id="toast-notification" class="hidden fixed bottom-5 right-5 flex items-center w-full max-w-xs p-4 space-x-4 text-white bg-gray-800 rounded-lg shadow border border-gray-700 z-50 transition-opacity duration-300 opacity-0">
    <div id="toast-icon-container" class="inline-flex items-center justify-center flex-shrink-0 w-8 h-8 text-green-500 bg-green-900 rounded-lg">
        <i class="fas fa-check"></i>
    </div>
    <div class="ml-3 text-sm font-normal" id="toast-message">Mensaje de notificación.</div>
    <button type="button" onclick="hideToast()" class="ml-auto text-gray-400 hover:text-white rounded-lg p-1.5 hover:bg-gray-700">
        <i class="fas fa-times"></i>
    </button>
</div>

<?php if (!empty($error)): ?>
    <div class="bg-red-900 border border-red-700 text-red-200 px-4 py-3 rounded-md mb-4 flex items-center" role="alert">
        <i class="fas fa-exclamation-triangle mr-3 text-xl"></i>
        <div>
            <p class="font-bold">Error del Sistema</p>
            <p class="text-sm"><?= htmlspecialchars($error) ?></p>
        </div>
    </div>
<?php endif; ?>

<!-- Barra de Búsqueda -->
<div class="flex flex-col sm:flex-row justify-between items-center mb-4 gap-4 no-print">
    <div class="w-full sm:w-1/2 lg:w-1/3">
        <div class="relative">
            <input type="text" id="search-input" class="w-full pl-10 pr-4 py-2 rounded-lg form-element-dark border-gray-600 focus:border-blue-500 focus:ring-blue-500"
                   placeholder="Buscar cliente por nombre…"
                   value="<?= htmlspecialchars($search_term) ?>"
                   autocomplete="off">
            <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
        </div>
    </div>
    <a href="index.php?page=agregar_cliente"
       class="w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg flex-shrink-0 flex items-center justify-center">
        <i class="fas fa-user-plus mr-2"></i>Nuevo Cliente
    </a>
</div>

<!-- Controles de cantidad -->
<div class="flex flex-col sm:flex-row justify-between items-center mb-4 gap-4 no-print">
    <div id="results-counter" class="text-sm text-gray-400">
        <?php if ($total_records > 0): ?>
            Mostrando <?= $offset + 1 ?> a <?= min($offset + $limit, $total_records) ?> de <?= $total_records ?>
        <?php else: ?>
            Sin resultados
        <?php endif; ?>
    </div>
    <div class="flex items-center gap-2">
        <label for="limit-select" class="text-sm text-gray-400">Mostrar:</label>
        <select id="limit-select" class="text-sm rounded-md form-element-dark border-gray-600">
            <?php foreach ($limit_options as $option): ?>
                <option value="<?= $option ?>" <?= $limit == $option ? 'selected' : '' ?>><?= $option ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<!-- Tabla -->
<div class="overflow-x-auto rounded-lg shadow border border-gray-700">
    <table class="min-w-full divide-y divide-gray-700">
        <thead class="bg-gray-800">
            <tr>
                <th class="px-4 py-3 text-left   text-xs font-medium text-gray-400 uppercase tracking-wider">Cliente</th>
                <th class="px-4 py-3 text-center  text-xs font-medium text-gray-400 uppercase tracking-wider">Zona</th>
                <th class="px-4 py-3 text-center  text-xs font-medium text-gray-400 uppercase tracking-wider">Frecuencia</th>
                <th class="px-4 py-3 text-center  text-xs font-medium text-gray-400 uppercase tracking-wider">Día Pago</th>
                <th class="px-4 py-3 text-center  text-xs font-medium text-gray-400 uppercase tracking-wider">Estado</th>
                <th class="px-4 py-3 text-right   text-xs font-medium text-gray-400 uppercase tracking-wider">Valor Cuota</th>
                <th class="px-4 py-3 text-center  text-xs font-medium text-gray-400 uppercase tracking-wider">Progreso</th>
                <th class="px-4 py-3 text-center  text-xs font-medium text-gray-400 uppercase tracking-wider no-print">Acciones</th>
            </tr>
        </thead>
        <tbody id="clientes-table-body" class="divide-y divide-gray-700 bg-gray-900">
            <?php if (empty($creditos)): ?>
                <tr>
                    <td colspan="8" class="px-6 py-12 text-center text-gray-400">
                        <i class="fas fa-search mb-2 text-2xl block"></i>
                        <p>No se encontraron registros.</p>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($creditos as $credito): ?>
                    <?= renderCreditoRow($credito, $NOMBRES_ZONAS) ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Paginación -->
<div id="pagination-container" class="mt-6 flex justify-center no-print">
    <?= generarPaginacion($page_num, $total_pages, 2) ?>
</div>

<!-- =====================================================================
     MODAL DE PAGO RÁPIDO
     BUG #3 — El action se genera dinámicamente en JS preservando
              search, limit y p actuales (ver función abrirModalPago).
     ===================================================================== -->
<div id="modalPago" class="fixed inset-0 bg-gray-900 bg-opacity-80 flex items-center justify-center hidden z-50 backdrop-blur-sm">
    <div class="bg-gray-800 p-6 rounded-lg border border-gray-700 w-full max-w-md shadow-2xl">
        <div class="flex justify-between items-center mb-5 border-b border-gray-700 pb-3">
            <h3 class="text-lg font-bold text-white">
                <i class="fas fa-cash-register text-green-500 mr-2"></i>Registrar Pago Rápido
            </h3>
            <button onclick="cerrarModalPago()" class="text-gray-400 hover:text-white transition-colors">
                <i class="fas fa-times fa-lg"></i>
            </button>
        </div>
        <!-- BUG #3: el action se inyecta en abrirModalPago() con los parámetros de contexto -->
        <form id="formPago" method="POST">
            <input type="hidden" name="pago_rapido"     value="1">
            <input type="hidden" id="modal_credito_id"  name="credito_id_pago">
            <div class="mb-4 bg-gray-700 p-3 rounded text-center">
                <p class="text-gray-400 text-xs uppercase tracking-wide mb-1">Cliente</p>
                <p id="modal_cliente_nombre" class="font-bold text-white text-lg"></p>
            </div>
            <div class="mb-4">
                <label for="modal_monto" class="block text-sm font-medium text-gray-300 mb-1">Monto a Abonar</label>
                <div class="relative rounded-md shadow-sm">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <span class="text-gray-500 sm:text-sm">$</span>
                    </div>
                    <input type="number" step="0.01" min="0.01" id="modal_monto" name="monto_cobrado"
                           class="block w-full rounded-md border-0 py-2 pl-7 pr-2 text-white bg-gray-900 ring-1 ring-inset ring-gray-600 focus:ring-blue-600 sm:text-sm"
                           required>
                </div>
                <!-- BUG #4 — Saldo pendiente puede ser NULL/0; se muestra aviso en ese caso -->
                <p class="text-xs mt-2 text-right">
                    Saldo Cuota:
                    <span id="modal_saldo" class="font-medium"></span>
                </p>
                <p id="modal_saldo_warning" class="hidden text-xs text-yellow-400 mt-1">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    Este crédito no tiene cuotas pendientes registradas. Verificá el cronograma antes de abonar.
                </p>
            </div>
            <div class="mb-6">
                <label for="modal_fecha" class="block text-sm font-medium text-gray-300 mb-1">Fecha de Pago</label>
                <input type="date" id="modal_fecha" name="fecha_pago"
                       class="block w-full rounded-md border-0 py-2 text-white bg-gray-900 ring-1 ring-inset ring-gray-600 focus:ring-blue-600 sm:text-sm"
                       value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button type="button" onclick="cerrarModalPago()"
                        class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-gray-200 rounded-md text-sm font-medium">
                    Cancelar
                </button>
                <button type="submit" id="modal_submit_btn"
                        class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-md text-sm font-medium">
                    Confirmar Pago
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// =========================================================================
// Toast
// =========================================================================
function showToast(message) {
    const toast = document.getElementById('toast-notification');
    document.getElementById('toast-message').textContent = message;
    toast.classList.remove('hidden');
    void toast.offsetWidth;                 // reflow para reiniciar la transición
    toast.classList.remove('opacity-0');
    setTimeout(hideToast, 4000);
}
function hideToast() {
    const toast = document.getElementById('toast-notification');
    toast.classList.add('opacity-0');
    setTimeout(() => toast.classList.add('hidden'), 300);
}

// BUG #5 — Flash message desde sesión (sobrevive al redirect PRG)
document.addEventListener('DOMContentLoaded', function () {
    <?php if (!empty($success)): ?>
        showToast(<?= json_encode($success) ?>);
    <?php endif; ?>
});

// =========================================================================
// Modal de Pago Rápido
// =========================================================================

// Capturar los parámetros de contexto actuales (PHP → JS)
// para reconstituir la URL de redirect correcta en el action del form.
// BUG #3: antes el form tenía action hardcodeado sin estos parámetros.
const _ctxSearch = <?= json_encode($search_term) ?>;
const _ctxLimit  = <?= json_encode((string)$limit) ?>;
const _ctxPage   = <?= json_encode((string)$page_num) ?>;

function abrirModalPago(id, nombre, saldo) {
    document.getElementById('modal_credito_id').value = id;
    document.getElementById('modal_cliente_nombre').textContent = nombre;

    const montoInput   = document.getElementById('modal_monto');
    const saldoSpan    = document.getElementById('modal_saldo');
    const saldoWarning = document.getElementById('modal_saldo_warning');
    const submitBtn    = document.getElementById('modal_submit_btn');

    // BUG #4 — Saldo NULL o 0: mostrar aviso y deshabilitar submit
    if (!saldo || saldo <= 0) {
        montoInput.value = '';
        saldoSpan.textContent = 'Sin datos';
        saldoSpan.className   = 'font-medium text-gray-500';
        saldoWarning.classList.remove('hidden');
        submitBtn.disabled = true;
        submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
    } else {
        montoInput.value = saldo.toFixed(2);
        saldoSpan.textContent = new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(saldo);
        saldoSpan.className   = 'font-medium text-yellow-400';
        saldoWarning.classList.add('hidden');
        submitBtn.disabled = false;
        submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
    }

    // BUG #3 — Construir action preservando el contexto de búsqueda
    const params = new URLSearchParams({ page: 'clientes' });
    if (_ctxSearch) params.set('search', _ctxSearch);
    if (_ctxLimit !== '10') params.set('limit', _ctxLimit);   // omitir el default
    if (_ctxPage  !== '1')  params.set('p', _ctxPage);        // omitir página 1
    document.getElementById('formPago').action = 'index.php?' + params.toString();

    document.getElementById('modalPago').classList.remove('hidden');
    setTimeout(() => montoInput.focus(), 50);
}

function cerrarModalPago() {
    document.getElementById('modalPago').classList.add('hidden');
}

// =========================================================================
// Búsqueda AJAX con debounce
// =========================================================================
document.addEventListener('DOMContentLoaded', function () {
    const searchInput         = document.getElementById('search-input');
    const limitSelect         = document.getElementById('limit-select');
    const tableBody           = document.getElementById('clientes-table-body');
    const paginationContainer = document.getElementById('pagination-container');
    const resultsCounter      = document.getElementById('results-counter');

    let currentPage   = 1;
    let debounceTimer = null;

    function fetchCreditos() {
        const searchTerm = searchInput.value;
        const limit      = limitSelect.value;
        tableBody.style.opacity = '0.5';

        const url = `views/buscar_clientes.php?search=${encodeURIComponent(searchTerm)}&limit=${limit}&p=${currentPage}`;

        fetch(url)
            .then(response => {
                if (!response.ok) throw new Error('Error en la red: ' + response.status);
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    tableBody.innerHTML = `<tr><td colspan="8" class="px-6 py-12 text-center text-red-400">${data.error}</td></tr>`;
                } else {
                    tableBody.innerHTML         = data.tableBody;
                    paginationContainer.innerHTML = data.pagination;
                    resultsCounter.innerHTML    = data.resultsCounter;
                }
            })
            .catch(error => {
                console.error('fetchCreditos error:', error);
                tableBody.innerHTML = '<tr><td colspan="8" class="px-6 py-12 text-center text-red-400">Error de conexión. Intente nuevamente.</td></tr>';
            })
            .finally(() => {
                tableBody.style.opacity = '1';
            });
    }

    searchInput.addEventListener('input', () => {
        currentPage = 1;
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(fetchCreditos, 400);
    });

    limitSelect.addEventListener('change', () => {
        currentPage = 1;
        fetchCreditos();
    });

    paginationContainer.addEventListener('click', function (e) {
        e.preventDefault();
        const target = e.target.closest('a[data-page]');
        if (!target) return;
        const page = parseInt(target.dataset.page, 10);
        if (page > 0) {
            currentPage = page;
            fetchCreditos();
        }
    });
});
</script>
<!-- SweetAlert2 & Helper Functions -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function confirmarEliminar(clienteId) {
    Swal.fire({
        title: '¿Eliminar Cliente?',
        text: "Se eliminará el cliente y todo su historial de créditos y pagos. ¡No se puede deshacer!",
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
            window.location.href = 'index.php?page=eliminar_cliente&id=' + clienteId;
        }
    });
}
</script>