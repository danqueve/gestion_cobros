<?php
// --- LÓGICA DE LA VISTA PARA IMPORTAR CLIENTES ---

$error = '';
$success = '';
$imported_count = 0;
$failed_rows = [];

// Procesar el archivo subido
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["archivo_csv"])) {
    
    // Validar que el archivo se haya subido correctamente
    if ($_FILES["archivo_csv"]["error"] == UPLOAD_ERR_OK) {
        $file_tmp_path = $_FILES["archivo_csv"]["tmp_name"];
        $file_name = $_FILES["archivo_csv"]["name"];
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if ($file_extension == 'csv') {
            // Abrir el archivo CSV para lectura
            if (($handle = fopen($file_tmp_path, "r")) !== FALSE) {
                
                // Omitir la fila de cabecera
                fgetcsv($handle);
                $row_num = 1;

                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    $row_num++;
                    // Asignar datos de las columnas a variables
                    $nombre = trim($data[0] ?? '');
                    $direccion = trim($data[1] ?? '');
                    $telefono = trim($data[2] ?? '');
                    $zona = filter_var($data[3] ?? '', FILTER_VALIDATE_INT);
                    $dia_pago = trim($data[4] ?? '');
                    $cuotas_pagadas = filter_var($data[5] ?? 0, FILTER_VALIDATE_INT);
                    $total_cuotas = filter_var($data[6] ?? 0, FILTER_VALIDATE_INT);
                    $monto_cuota = filter_var($data[7] ?? 0, FILTER_VALIDATE_FLOAT);
                    $ultimo_pago = trim($data[8] ?? null);

                    // Validación básica de datos
                    if (empty($nombre) || $total_cuotas === false || $monto_cuota === false) {
                        $failed_rows[] = $row_num;
                        continue; // Saltar a la siguiente fila
                    }

                    try {
                        $pdo->beginTransaction();
                        // Insertar cliente
                        $stmt_cliente = $pdo->prepare("INSERT INTO clientes (nombre, direccion, telefono) VALUES (?, ?, ?)");
                        $stmt_cliente->execute([$nombre, $direccion, $telefono]);
                        $cliente_id = $pdo->lastInsertId();
                        
                        // Insertar crédito
                        $monto_total = $total_cuotas * $monto_cuota;
                        $estado = ($cuotas_pagadas >= $total_cuotas) ? 'Pagado' : 'Activo';
                        $stmt_credito = $pdo->prepare("INSERT INTO creditos (cliente_id, zona, dia_pago, monto_total, total_cuotas, cuotas_pagadas, monto_cuota, ultimo_pago, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt_credito->execute([$cliente_id, $zona, $dia_pago, $monto_total, $total_cuotas, $cuotas_pagadas, $monto_cuota, $ultimo_pago, $estado]);
                        
                        $pdo->commit();
                        $imported_count++;

                    } catch (PDOException $e) {
                        $pdo->rollBack();
                        $failed_rows[] = $row_num;
                    }
                }
                fclose($handle);
                $success = "Proceso de importación finalizado. Se importaron $imported_count clientes.";
                if (!empty($failed_rows)) {
                    $error = "No se pudieron importar las siguientes filas: " . implode(', ', $failed_rows);
                }
            }
        } else {
            $error = "Formato de archivo incorrecto. Por favor, sube un archivo CSV.";
        }
    } else {
        $error = "Hubo un error al subir el archivo.";
    }
}
?>

<h2 class="text-2xl font-bold text-gray-200 mb-4">Importar Clientes desde CSV</h2>

<?php if(!empty($success)): ?>
    <div class="bg-green-900 border border-green-700 text-green-200 px-4 py-3 rounded-md mb-4" role="alert"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if(!empty($error)): ?>
    <div class="bg-red-900 border border-red-700 text-red-200 px-4 py-3 rounded-md mb-4" role="alert"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="bg-gray-800 p-6 rounded-lg border border-gray-700">
    <div class="mb-6 bg-gray-900 p-4 rounded-md border border-gray-600">
        <h3 class="text-lg font-semibold text-gray-200 mb-2"><i class="fas fa-info-circle text-blue-400 mr-2"></i>Instrucciones</h3>
        <p class="text-gray-400 text-sm">
            1. Descarga la plantilla CSV para asegurar el formato correcto de los datos.
        </p>
        <p class="text-gray-400 text-sm">
            2. Rellena la plantilla con los datos de tus clientes. No elimines ni cambies el orden de las columnas.
        </p>
        <p class="text-gray-400 text-sm">
            3. Guarda el archivo en formato CSV (delimitado por comas).
        </p>
        <p class="text-gray-400 text-sm">
            4. Sube el archivo usando el formulario de abajo.
        </p>
        <a href="index.php?page=descargar_plantilla" class="mt-4 inline-block bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition duration-300">
            <i class="fas fa-download mr-2"></i>Descargar Plantilla CSV
        </a>
    </div>

    <form action="index.php?page=importar_clientes" method="POST" enctype="multipart/form-data">
        <div>
            <label for="archivo_csv" class="block text-sm font-medium text-gray-300 mb-2">Seleccionar archivo CSV</label>
            <input type="file" name="archivo_csv" id="archivo_csv" required accept=".csv" class="block w-full text-sm text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-600 file:text-white hover:file:bg-blue-700">
        </div>
        <div class="mt-6 flex justify-end">
            <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg transition duration-300">
                <i class="fas fa-upload mr-2"></i>Importar Clientes
            </button>
        </div>
    </form>
</div>
