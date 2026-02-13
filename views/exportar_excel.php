<?php
// Incluimos el archivo de configuración.
require_once '../config.php';

try {
    // --- CONSULTA PARA OBTENER TODOS LOS DATOS SIN PAGINACIÓN ---
    $sql = "SELECT
                c.nombre, c.telefono, c.direccion,
                CASE cr.zona WHEN 1 THEN 'Santi' WHEN 2 THEN 'Juan Pablo' WHEN 3 THEN 'Enzo' WHEN 4 THEN 'Tafi del V' ELSE 'N/A' END AS nombre_zona,
                cr.frecuencia,
                CASE WHEN cr.frecuencia = 'Semanal' THEN cr.dia_pago ELSE cr.dia_vencimiento END AS dia_de_cobro,
                cr.monto_cuota, cr.total_cuotas, cr.cuotas_pagadas, cr.estado AS estado_credito, cr.ultimo_pago
            FROM clientes c
            LEFT JOIN creditos cr ON c.id = cr.cliente_id
            ORDER BY c.nombre ASC";
    
    $stmt = $pdo->query($sql);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error al exportar los datos: " . $e->getMessage());
}

// --- GENERACIÓN DEL ARCHIVO CSV ---

$filename = "reporte-general-clientes-" . date('Y-m-d') . ".csv";

// Encabezados para forzar la descarga del archivo
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

// Abrimos el flujo de salida de PHP para escribir el CSV
$output = fopen('php://output', 'w');

// Escribimos la fila de encabezados en el archivo
fputcsv($output, [
    'Cliente',
    'Telefono',
    'Direccion',
    'Zona',
    'Frecuencia de Pago',
    'Dia de Cobro',
    'Monto de Cuota',
    'Total de Cuotas',
    'Cuotas Pagadas',
    'Estado del Credito',
    'Fecha de Ultimo Pago'
]);

// Recorremos los datos y los escribimos fila por fila
foreach ($data as $row) {
    fputcsv($output, [
        $row['nombre'],
        $row['telefono'],
        $row['direccion'],
        $row['nombre_zona'],
        $row['frecuencia'],
        $row['dia_de_cobro'],
        $row['monto_cuota'],
        $row['total_cuotas'],
        $row['cuotas_pagadas'],
        $row['estado_credito'],
        $row['ultimo_pago'] ? (new DateTime($row['ultimo_pago']))->format('d/m/Y') : ''
    ]);
}

// Cerramos el flujo
fclose($output);
exit;
?>
