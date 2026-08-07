{{-- Plugin Chart.js: valor encima de cada barra (Metrics). --}}
<div wire:ignore>
    <script>
        (function () {
            if (window.__fiMetricsBarValueLabelsReady) {
                return;
            }

            window.filamentChartJsPlugins = window.filamentChartJsPlugins || [];

            const alreadyRegistered = window.filamentChartJsPlugins.some(
                (plugin) => plugin && plugin.id === 'fiMetricsBarValueLabels',
            );

            if (alreadyRegistered) {
                window.__fiMetricsBarValueLabelsReady = true;
                return;
            }

            const formatUsdAmount = (value) => {
                const number = Number(value);

                if (!Number.isFinite(number) || number <= 0) {
                    return null;
                }

                const amount = number.toLocaleString('es-VE', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                });

                return `US$ ${amount}`;
            };

            const formatBarValue = (value, format) => {
                if (format === 'usd') {
                    return formatUsdAmount(value);
                }

                const number = Number(value);

                if (!Number.isFinite(number) || number <= 0) {
                    return null;
                }

                if (Math.abs(number - Math.round(number)) < 0.001) {
                    return Math.round(number).toLocaleString('es-VE');
                }

                if (Math.abs(number) >= 1000) {
                    return number.toLocaleString('es-VE', {
                        maximumFractionDigits: 0,
                    });
                }

                return number.toLocaleString('es-VE', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                });
            };

            window.filamentChartJsPlugins.push({
                id: 'fiMetricsBarValueLabels',
                afterDatasetsDraw(chart) {
                    const { ctx } = chart;
                    const isDark = document.documentElement.classList.contains('dark');
                    const labelColor = isDark
                        ? 'rgba(248, 250, 252, 0.96)'
                        : 'rgba(15, 23, 42, 0.9)';
                    const shadowColor = isDark
                        ? 'rgba(2, 6, 23, 0.65)'
                        : 'rgba(255, 255, 255, 0.85)';

                    chart.data.datasets.forEach((dataset, datasetIndex) => {
                        const meta = chart.getDatasetMeta(datasetIndex);

                        if (!meta || meta.type !== 'bar' || meta.hidden) {
                            return;
                        }

                        const format = dataset.valueLabelFormat === 'usd' ? 'usd' : 'number';
                        const totalPoints = meta.data.length;
                        const fontSize = format === 'usd'
                            ? (totalPoints > 14 ? 8 : totalPoints > 10 ? 9 : 10)
                            : (totalPoints > 18 ? 9 : totalPoints > 12 ? 10 : 11);

                        meta.data.forEach((element, index) => {
                            const raw = dataset.data?.[index];
                            const label = formatBarValue(raw, format);

                            if (!label || !element || element.hidden) {
                                return;
                            }

                            const { x, y } = element.tooltipPosition();

                            ctx.save();
                            ctx.font = `700 ${fontSize}px ui-sans-serif, -apple-system, BlinkMacSystemFont, "SF Pro Text", system-ui, sans-serif`;
                            ctx.fillStyle = labelColor;
                            ctx.textAlign = 'center';
                            ctx.textBaseline = 'bottom';
                            ctx.shadowColor = shadowColor;
                            ctx.shadowBlur = 4;
                            ctx.fillText(label, x, y - 6);
                            ctx.restore();
                        });
                    });
                },
            });

            window.__fiMetricsBarValueLabelsReady = true;
        })();
    </script>
</div>
