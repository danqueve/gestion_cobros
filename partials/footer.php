<?php
// Este archivo cierra las etiquetas HTML abiertas en header.php
// y puede contener scripts de JavaScript globales.
?>
    </div> <!-- Cierre del div principal del contenido -->
</main> <!-- Cierre de la etiqueta main -->

<!-- Scripts de JavaScript globales (si los necesitas) -->
<script>
    // Ejemplo: Lógica para calcular totales en la página de rutas
    document.addEventListener('DOMContentLoaded', function() {
        if (document.getElementById('total-estimado')) {
            const estimado = parseFloat(document.getElementById('total-estimado').dataset.valor) || 0;
            let cobrado = 0;

            document.querySelectorAll('.monto-cobrado-input').forEach(input => {
                input.addEventListener('input', function() {
                    cobrado = 0;
                    document.querySelectorAll('.monto-cobrado-input').forEach(i => {
                        const valor = parseFloat(i.value);
                        if (!isNaN(valor) && valor > 0) {
                            cobrado += valor;
                        }
                    });
                    actualizarTotales();
                });
            });

            function actualizarTotales() {
                const faltante = estimado - cobrado;
                document.getElementById('total-estimado').textContent = '$' + estimado.toLocaleString('es-AR');
                document.getElementById('total-cobrado').textContent = '$' + cobrado.toLocaleString('es-AR');
                document.getElementById('total-faltante').textContent = '$' + faltante.toLocaleString('es-AR');
            }
            
            actualizarTotales();
        }
    });
</script>

</body>
</html>
