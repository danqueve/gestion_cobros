<?php
// Este script genera un archivo CSV y lo fuerza a ser descargado.
// Ya no es necesario incluir 'config.php' aquí, porque index.php ya lo hizo.
check_login();

try {
    // 1. CONSULTA A LA BASE DE DATOS
    // Obtenemos todos los datos necesarios uniendo las tablas de clientes y créditos.
    $sql = "SELECT 
                cr.zona,
                c.nombre,
                c.direccion,
                cr.cuotas_pagadas,
                cr.total_cuotas,
                cr.ultimo_pago,
                cr.monto_cuota,
                cr.estado
            FROM creditos cr
            JOIN clientes c ON cr.cliente_id = c.id
            ORDER BY cr.zona, c.nombre";
    
    $stmt = $pdo->query($sql);
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. PREPARAR EL ARCHIVO CSV PARA DESCARGA
    $filename = "reporte_cobranza_" . date('Y-m-d') . ".csv";
    
    // Establecer las cabeceras HTTP para forzar la descarga del archivo.
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    // Crear un puntero de archivo que escriba en la salida del script.
    $output = fopen('php://output', 'w');
    
    // Escribir la fila de la cabecera en el archivo CSV.
    fputcsv($output, [
        'Zona',
        'Cliente',
        'Direccion',
        'Cuotas Pagadas',
        'Ultima Fecha de Pago',
        'Dias de Atraso',
        'Total de Cuotas',
        'Monto de la Cuota'
    ]);

    // 3. RECORRER LOS DATOS Y ESCRIBIRLOS EN EL CSV
    foreach ($registros as $row) {
        // Formatear los datos para el reporte
        $cuotas_pagas_str = $row['cuotas_pagadas'] . ' / ' . $row['total_cuotas'];
        $ultima_fecha_pago = $row['ultimo_pago'] ? (new DateTime($row['ultimo_pago']))->format('d/m/Y') : 'N/A';
        
        // Calcular los días de atraso usando la función de config.php
        $atraso_info = calcularAtraso($row['ultimo_pago'], $row['estado']);
        $dias_atraso = ($atraso_info['estado'] == 'Atrasado') ? $atraso_info['dias'] : 0;

        // Escribir la fila en el archivo CSV
        fputcsv($output, [
            $row['zona'],
            $row['nombre'],
            $row['direccion'],
            $row['cuotas_pagadas'],
            $ultima_fecha_pago,
            $dias_atraso,
            $row['total_cuotas'],
            $row['monto_cuota']
        ]);
    }

    // Cerrar el puntero del archivo.
    fclose($output);
    exit;

} catch (PDOException $e) {
    die("Error al generar el reporte: " . $e->getMessage());
}
?>
