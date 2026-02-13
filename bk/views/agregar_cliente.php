<?php
// --- LÓGICA DE LA VISTA PARA AGREGAR CLIENTES Y CRÉDITOS (ACTUALIZADA) ---

// Inicia el búfer de salida para permitir redirecciones después de enviar HTML.
ob_start();

$error = '';
$success = '';

// --- MAPA DE NOMBRES PARA LAS ZONAS ---
$nombres_zonas =[
    1 => 'Santi',
    2 => 'Juan Pablo',
    3 => 'Enzo',
    4 => 'Tafi del V',
    5 => 'Famailla',
    6 => 'Sgo'
];

$dias_semana = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
$frecuencias = ['Semanal', 'Quincenal', 'Mensual'];

// Procesar el formulario cuando se envía
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Recoger y limpiar los datos del formulario
    $nombre = trim($_POST['nombre'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    
    $zona = filter_input(INPUT_POST, 'zona', FILTER_VALIDATE_INT);
    $frecuencia = $_POST['frecuencia'] ?? '';
    $total_cuotas = filter_input(INPUT_POST, 'total_cuotas', FILTER_VALIDATE_INT);
    $monto_cuota = filter_input(INPUT_POST, 'monto_cuota', FILTER_VALIDATE_FLOAT);
    $fecha_inicio_str = $_POST['ultimo_pago'] ?? date('Y-m-d');
    if(empty($fecha_inicio_str)) $fecha_inicio_str = date('Y-m-d');

    // Lógica para dia_pago y dia_vencimiento
    $dia_pago = null;
    $dia_vencimiento = null;

    if ($zona == 4 && $frecuencia == 'Semanal') {
        $dia_pago = 'Varios'; 
    } elseif ($frecuencia == 'Semanal') {
        $dia_pago = $_POST['dia_pago'] ?? '';
    } elseif ($frecuencia == 'Mensual') { // Solo para Mensual
        $dia_vencimiento = filter_input(INPUT_POST, 'dia_vencimiento', FILTER_VALIDATE_INT);
    }

    // --- Validaciones (ACTUALIZADAS) ---
    if (empty($nombre) || empty($zona) || empty($frecuencia) || empty($total_cuotas) || empty($monto_cuota)) {
        $error = "Los campos marcados con * son obligatorios.";
    } elseif ($frecuencia == 'Semanal' && $zona != 4 && empty($dia_pago)) {
        $error = "Debe seleccionar un día de pago para la frecuencia semanal.";
    } elseif ($frecuencia == 'Mensual' && (empty($dia_vencimiento) || $dia_vencimiento < 1 || $dia_vencimiento > 31)) {
        $error = "Debe ingresar un día de vencimiento válido (1-31) para la frecuencia mensual.";
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Insertar el nuevo cliente
            $sql_cliente = "INSERT INTO clientes (nombre, telefono, direccion) VALUES (?, ?, ?)";
            $stmt_cliente = $pdo->prepare($sql_cliente);
            $stmt_cliente->execute([$nombre, $telefono, $direccion]);
            $cliente_id = $pdo->lastInsertId();

            // 2. Insertar el nuevo crédito
            $monto_total = $total_cuotas * $monto_cuota;
            $sql_credito = "INSERT INTO creditos (cliente_id, zona, frecuencia, dia_pago, dia_vencimiento, monto_total, total_cuotas, monto_cuota, ultimo_pago) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_credito = $pdo->prepare($sql_credito);
            $stmt_credito->execute([$cliente_id, $zona, $frecuencia, $dia_pago, $dia_vencimiento, $monto_total, $total_cuotas, $monto_cuota, $fecha_inicio_str]);
            $credito_id = $pdo->lastInsertId();

            // 3. Generar y guardar el cronograma de cuotas
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

            $pdo->commit();
            
            echo '<script>window.location.href="index.php?page=clientes&status=success";</script>';
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Error al guardar el cliente y su cronograma: " . $e->getMessage();
        }
    }
}
?>

<h2 class="text-2xl font-bold text-gray-200 mb-4">Agregar Nuevo Cliente y Crédito</h2>

<?php if(!empty($error)): ?>
    <div class="bg-red-900 border border-red-700 text-red-200 px-4 py-3 rounded-md mb-4" role="alert">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<div class="bg-gray-800 p-6 rounded-lg border border-gray-700">
    <form action="index.php?page=agregar_cliente" method="POST">
        <!-- Sección de Datos del Cliente -->
        <fieldset class="border border-gray-600 p-4 rounded-md mb-6">
            <legend class="px-2 text-lg font-semibold text-gray-300">Datos Personales</legend>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="nombre" class="block text-sm font-medium text-gray-300">Nombre Completo <span class="text-red-500">*</span></label>
                    <input type="text" id="nombre" name="nombre" required class="mt-1 block w-full rounded-md form-element-dark" value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>">
                </div>
                <div>
                    <label for="telefono" class="block text-sm font-medium text-gray-300">Teléfono</label>
                    <input type="text" id="telefono" name="telefono" class="mt-1 block w-full rounded-md form-element-dark" value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>">
                </div>
                <div class="md:col-span-2">
                    <label for="direccion" class="block text-sm font-medium text-gray-300">Dirección</label>
                    <input type="text" id="direccion" name="direccion" class="mt-1 block w-full rounded-md form-element-dark" value="<?= htmlspecialchars($_POST['direccion'] ?? '') ?>">
                </div>
            </div>
        </fieldset>

        <!-- Sección de Datos del Crédito -->
        <fieldset class="border border-gray-600 p-4 rounded-md">
            <legend class="px-2 text-lg font-semibold text-gray-300">Datos del Crédito</legend>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label for="zona" class="block text-sm font-medium text-gray-300">Zona <span class="text-red-500">*</span></label>
                    <select id="zona" name="zona" required class="mt-1 block w-full rounded-md form-element-dark">
                        <option value="">Seleccione...</option>
                        <?php foreach($nombres_zonas as $num => $nombre): ?>
                            <option value="<?= $num ?>" <?= (($_POST['zona'] ?? '') == $num) ? 'selected' : '' ?>><?= htmlspecialchars($nombre) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="frecuencia" class="block text-sm font-medium text-gray-300">Frecuencia de Pago <span class="text-red-500">*</span></label>
                    <select id="frecuencia" name="frecuencia" required class="mt-1 block w-full rounded-md form-element-dark">
                        <option value="">Seleccione...</option>
                        <?php foreach ($frecuencias as $f): ?>
                            <option value="<?= $f ?>" <?= (($_POST['frecuencia'] ?? '') == $f) ? 'selected' : '' ?>><?= $f ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Campo dinámico para Día de Pago (Semanal) -->
                <div id="dia_pago_container" style="display: none;">
                    <label for="dia_pago" class="block text-sm font-medium text-gray-300">Día de Pago <span class="text-red-500">*</span></label>
                    <select id="dia_pago" name="dia_pago" class="mt-1 block w-full rounded-md form-element-dark">
                        <option value="">Seleccione...</option>
                        <?php foreach ($dias_semana as $d): ?>
                             <option value="<?= $d ?>" <?= (($_POST['dia_pago'] ?? '') == $d) ? 'selected' : '' ?>><?= $d ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Campo dinámico para Día de Vencimiento (Mensual) -->
                <div id="dia_vencimiento_container" style="display: none;">
                    <label for="dia_vencimiento" class="block text-sm font-medium text-gray-300">Día de Vencimiento (1-31) <span class="text-red-500">*</span></label>
                    <input type="number" id="dia_vencimiento" name="dia_vencimiento" min="1" max="31" class="mt-1 block w-full rounded-md form-element-dark" value="<?= htmlspecialchars($_POST['dia_vencimiento'] ?? '') ?>">
                </div>

                <div>
                    <label for="total_cuotas" class="block text-sm font-medium text-gray-300">Total de Cuotas <span class="text-red-500">*</span></label>
                    <input type="number" id="total_cuotas" name="total_cuotas" required class="mt-1 block w-full rounded-md form-element-dark" value="<?= htmlspecialchars($_POST['total_cuotas'] ?? '') ?>">
                </div>
                <div>
                    <label for="monto_cuota" class="block text-sm font-medium text-gray-300">Monto de Cuota <span class="text-red-500">*</span></label>
                    <input type="number" step="0.01" id="monto_cuota" name="monto_cuota" required class="mt-1 block w-full rounded-md form-element-dark" value="<?= htmlspecialchars($_POST['monto_cuota'] ?? '') ?>">
                </div>
                 <div>
                    <label for="ultimo_pago" class="block text-sm font-medium text-gray-300">Fecha de Inicio (Primer Vencimiento) <span class="text-red-500">*</span></label>
                    <input type="date" id="ultimo_pago" name="ultimo_pago" required class="mt-1 block w-full rounded-md form-element-dark" value="<?= htmlspecialchars($_POST['ultimo_pago'] ?? date('Y-m-d')) ?>">
                </div>
            </div>
        </fieldset>

        <!-- Botones de Acción -->
        <div class="mt-6 flex justify-end gap-4">
            <a href="index.php?page=clientes" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-md transition duration-300">
                Cancelar
            </a>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md transition duration-300">
                <i class="fas fa-save mr-2"></i>Guardar Cliente
            </button>
        </div>
    </form>
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

        // Ocultar ambos contenedores y quitar 'required'
        diaPagoContainer.style.display = 'none';
        diaVencimientoContainer.style.display = 'none';
        diaPagoSelect.required = false;
        diaVencimientoInput.required = false;

        if (frecuencia === 'Semanal') {
            // Caso especial: Tafi del V y Semanal, no se muestra nada.
            if (zona != 4) { 
                diaPagoContainer.style.display = 'block';
                diaPagoSelect.required = true;
            }
        } else if (frecuencia === 'Mensual') { // Solo para Mensual
            diaVencimientoContainer.style.display = 'block';
            diaVencimientoInput.required = true;
        }
        // Para Quincenal, no se muestra ningún campo de día, se calcula solo.
    }

    // Añadir listeners para los cambios
    zonaSelect.addEventListener('change', toggleVencimientoFields);
    frecuenciaSelect.addEventListener('change', toggleVencimientoFields);

    // Llamar a la función al cargar la página para establecer el estado inicial
    toggleVencimientoFields();
});
</script>

