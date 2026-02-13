<?php
// No se necesita lógica de PHP del lado del servidor para esta vista,
// ya que todos los cálculos se realizan en tiempo real con JavaScript.
?>

<h2 class="text-2xl font-bold text-gray-200 mb-4">Calculadora de Pagos con Tarjeta</h2>

<div class="bg-gray-800 p-6 sm:p-8 rounded-lg border border-gray-700 max-w-lg mx-auto">
    <p class="text-gray-400 mb-6 text-center">
        Ingresa el monto a cobrar en efectivo para calcular el total con el recargo de la tarjeta (25%).
    </p>

    <div class="space-y-4">
        <div>
            <label for="monto_efectivo" class="block text-sm font-medium text-gray-300 mb-2">Monto en Efectivo</label>
            <div class="relative">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">$</span>
                <input type="number" 
                       id="monto_efectivo" 
                       class="w-full pl-7 pr-4 py-3 rounded-lg form-element-dark text-lg" 
                       placeholder="0.00" 
                       step="any"
                       autofocus>
            </div>
        </div>

        <div class="flex justify-center items-center pt-4">
            <i class="fas fa-arrow-down text-blue-500 fa-2x"></i>
        </div>
        
        <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Total a Cobrar con Tarjeta (Monto x 1.25)</label>
            <div id="resultado_tarjeta" class="w-full bg-gray-900 border border-gray-600 rounded-lg p-4 text-center">
                <span class="text-3xl font-bold text-green-400">$0.00</span>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const montoEfectivoInput = document.getElementById('monto_efectivo');
    const resultadoTarjetaDiv = document.getElementById('resultado_tarjeta');
    const resultadoSpan = resultadoTarjetaDiv.querySelector('span');

    // Función para formatear un número como moneda local (Argentina)
    function formatCurrency(value) {
        return '$' + new Intl.NumberFormat('es-AR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(value);
    }

    // Función para calcular y actualizar el resultado en tiempo real
    function calcularTotal() {
        // Obtiene el valor del input y lo convierte a un número.
        const monto = parseFloat(montoEfectivoInput.value);

        // Verifica si el valor es un número válido y mayor que cero.
        if (!isNaN(monto) && monto > 0) {
            // Calcula el total multiplicando por 1.25
            const total = monto * 1.25;
            // Muestra el resultado formateado como moneda.
            resultadoSpan.textContent = formatCurrency(total);
        } else {
            // Si no hay un valor válido, muestra $0.00
            resultadoSpan.textContent = '$0.00';
        }
    }

    // Agrega un "escuchador" al campo de texto que llama a la función de cálculo
    // cada vez que el usuario escribe algo.
    montoEfectivoInput.addEventListener('input', calcularTotal);
});
</script>