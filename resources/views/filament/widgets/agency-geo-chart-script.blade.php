<div wire:ignore>
    <script>
        (function () {
            const DATALABELS_URL = 'https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2';

            const initDatalabels = () => {
                // 1. Esperar a que Chart.js (de Filament) esté disponible
                if (typeof Chart === 'undefined') {
                    setTimeout(initDatalabels, 200);
                    return;
                }

                // 2. Si ya está registrado, no hacer nada
                if (window.ChartDataLabelsRegistered) return;

                // 3. Cargar el script de forma dinámica para evitar que se ejecute antes de tiempo
                const script = document.createElement('script');
                script.src = DATALABELS_URL;
                script.onload = () => {
                    if (typeof ChartDataLabels !== 'undefined') {
                        // 4. Registrar el plugin globalmente
                        Chart.register(ChartDataLabels);
                        window.ChartDataLabelsRegistered = true;

                        // Forzar actualización de los gráficos existentes para que muestren las flechas
                        Object.values(Chart.instances).forEach(chart => chart.update());

                        console.log('✅ Plugin Datalabels cargado y registrado correctamente.');
                    }
                };
                script.onerror = () => console.error('❌ No se pudo cargar el plugin de etiquetas.');
                document.head.appendChild(script);
            };

            // Iniciar el chequeo
            initDatalabels();
        })();
    </script>
</div>