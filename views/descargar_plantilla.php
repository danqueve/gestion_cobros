<?php
// Este script genera un archivo CSV y lo fuerza a ser descargado.

// Establecer las cabeceras para la descarga del archivo.
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="plantilla_importacion.csv"');

// Crear un puntero de archivo que escriba en la salida del script.
$output = fopen('php://output', 'w');

// Escribir la fila de la cabecera en el archivo CSV.
// Es crucial que el usuario use estas columnas exactas.
fputcsv($output, [
    'nombre_completo',
    'direccion',
    'telefono',
    'zona',
    'dia_pago',
    'cuotas_pagadas',
    'total_cuotas',
    'monto_cuota',
    'ultimo_pago_YYYY-MM-DD'
]);

// Escribir una fila de ejemplo para guiar al usuario.
fputcsv($output, [
    'Juan Perez Ejemplo',
    'Calle Falsa 123',
    '3811234567',
    '1',
    'Lunes',
    '5',
    '20',
    '35000.00',
    '2025-08-12'
]);

// Cerrar el puntero del archivo.
fclose($output);
exit;
?>