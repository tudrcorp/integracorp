{{-- Plugin Chart.js v9: outlabels responsivos, siempre dentro del área visible. --}}
<div wire:ignore wire:key="fi-metrics-pie-outlabels-v9">
    <script>
        (function () {
            const PLUGIN_ID = 'fiMetricsPieOutLabels';
            const PLUGIN_VERSION = 9;

            window.filamentChartJsPlugins = window.filamentChartJsPlugins || [];

            const alreadyCurrent = window.__fiMetricsPieOutLabelsVersion === PLUGIN_VERSION
                && window.filamentChartJsPlugins.some((plugin) => plugin && plugin.id === PLUGIN_ID);

            if (alreadyCurrent) {
                return;
            }

            window.filamentChartJsPlugins = window.filamentChartJsPlugins.filter(
                (plugin) => ! plugin || plugin.id !== PLUGIN_ID,
            );

            const formatUsd = (value) => {
                const number = Number(value);

                if (! Number.isFinite(number)) {
                    return 'US$ 0,00';
                }

                return 'US$ ' + number.toLocaleString('es-VE', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                });
            };

            const formatCount = (value) => {
                const number = Number(value);

                if (! Number.isFinite(number)) {
                    return '0';
                }

                return Math.round(number).toLocaleString('es-VE');
            };

            const formatValueLabel = (value, dataset) => {
                const format = String(dataset?.valueFormat || dataset?.label || 'usd').toLowerCase();

                if (format === 'count' || format === 'afiliaciones') {
                    return formatCount(value) + ' afil.';
                }

                return formatUsd(value);
            };

            const shortenLabel = (label, maxChars) => {
                const text = String(label || '').trim();

                if (text.length <= maxChars) {
                    return text;
                }

                return text.slice(0, Math.max(1, maxChars - 1)).trimEnd() + '…';
            };

            const packLabelsWithoutOverlap = (items, minY, maxY, minGap) => {
                if (items.length === 0) {
                    return [];
                }

                const available = Math.max(minGap, maxY - minY);
                const maxSlots = Math.max(1, Math.floor(available / minGap) + 1);

                const ranked = [...items].sort((left, right) => {
                    if (right.value !== left.value) {
                        return right.value - left.value;
                    }

                    return left.idealY - right.idealY;
                });

                const visible = ranked.slice(0, maxSlots).sort((left, right) => left.idealY - right.idealY);
                const totalSpan = Math.max(0, (visible.length - 1) * minGap);

                if (visible.length === 1) {
                    visible[0].y = Math.min(maxY, Math.max(minY, visible[0].idealY));
                    visible[0].stemExtra = 0;

                    return visible;
                }

                visible[0].y = Math.max(minY, Math.min(maxY - totalSpan, visible[0].idealY));

                for (let index = 1; index < visible.length; index++) {
                    const floor = visible[index - 1].y + minGap;
                    visible[index].y = Math.max(floor, visible[index].idealY);
                }

                if (visible[visible.length - 1].y > maxY || visible[0].y < minY) {
                    const startY = minY + Math.max(0, (available - totalSpan) / 2);

                    visible.forEach((item, index) => {
                        item.y = startY + index * minGap;
                    });
                }

                visible.forEach((item, index) => {
                    item.stemExtra = index;
                });

                return visible;
            };

            const resolveSliceColor = (dataset, index) => {
                const colors = dataset?.backgroundColor;

                if (Array.isArray(colors)) {
                    return colors[index] || '#38bdf8';
                }

                return colors || '#38bdf8';
            };

            /**
             * Escala tipografía/rieles según el ancho útil del canvas.
             */
            const responsiveMetrics = (areaWidth) => {
                if (areaWidth < 300) {
                    return {
                        useLegend: false,
                        labelBlockHeight: 24,
                        minGap: 28,
                        railGap: 12,
                        stemBase: 8,
                        stemStep: 4,
                        nameChars: 8,
                        fontTitle: '700 9px ui-sans-serif, system-ui, sans-serif',
                        fontValue: '500 8px ui-sans-serif, system-ui, sans-serif',
                        textPad: 5,
                    };
                }

                if (areaWidth < 420) {
                    return {
                        useLegend: false,
                        labelBlockHeight: 26,
                        minGap: 32,
                        railGap: 18,
                        stemBase: 16,
                        stemStep: 8,
                        nameChars: 10,
                        fontTitle: '700 10px ui-sans-serif, system-ui, sans-serif',
                        fontValue: '500 9px ui-sans-serif, system-ui, sans-serif',
                        textPad: 7,
                    };
                }

                if (areaWidth < 560) {
                    return {
                        useLegend: false,
                        labelBlockHeight: 28,
                        minGap: 34,
                        railGap: 24,
                        stemBase: 28,
                        stemStep: 12,
                        nameChars: 12,
                        fontTitle: '700 10px ui-sans-serif, system-ui, sans-serif',
                        fontValue: '500 9px ui-sans-serif, system-ui, sans-serif',
                        textPad: 8,
                    };
                }

                return {
                    useLegend: false,
                    labelBlockHeight: 30,
                    minGap: 38,
                    railGap: 32,
                    stemBase: 48,
                    stemStep: 16,
                    nameChars: 15,
                    fontTitle: '700 11px ui-sans-serif, system-ui, sans-serif',
                    fontValue: '500 10px ui-sans-serif, system-ui, sans-serif',
                    textPad: 10,
                };
            };

            const plugin = {
                id: PLUGIN_ID,
                afterDatasetsDraw(chart) {
                    if (! chart || ! chart.ctx) {
                        return;
                    }

                    const meta = chart.getDatasetMeta(0);

                    if (! meta || (meta.type !== 'pie' && meta.type !== 'doughnut') || meta.hidden) {
                        return;
                    }

                    const dataset = chart.data.datasets?.[0];
                    const labels = chart.data.labels || [];
                    const values = Array.isArray(dataset?.data) ? dataset.data : [];
                    const total = values.reduce((sum, value) => sum + Number(value || 0), 0);

                    if (! dataset || total <= 0 || ! meta.data?.length) {
                        return;
                    }

                    const { ctx, chartArea } = chart;
                    const areaWidth = Math.max(0, chartArea.right - chartArea.left);
                    const metrics = responsiveMetrics(areaWidth);

                    // Reservado por si en el futuro se usa leyenda nativa en viewports extremos.
                    if (metrics.useLegend) {
                        return;
                    }

                    const isDark = document.documentElement.classList.contains('dark');
                    const leftLabels = [];
                    const rightLabels = [];
                    let maxOuterRadius = 0;
                    let pieCenterX = (chartArea.left + chartArea.right) / 2;
                    let pieCenterY = (chartArea.top + chartArea.bottom) / 2;

                    meta.data.forEach((arc, index) => {
                        const value = Number(values[index] || 0);

                        if (! arc || value <= 0) {
                            return;
                        }

                        const props = typeof arc.getProps === 'function'
                            ? arc.getProps(['startAngle', 'endAngle', 'outerRadius', 'x', 'y'], true)
                            : arc;
                        const startAngle = Number(props.startAngle);
                        const endAngle = Number(props.endAngle);
                        const outerRadius = Number(props.outerRadius);
                        const midAngle = (startAngle + endAngle) / 2;
                        const sliceCenterX = Number(props.x ?? pieCenterX);
                        const sliceCenterY = Number(props.y ?? pieCenterY);

                        if (! Number.isFinite(midAngle) || ! Number.isFinite(outerRadius) || outerRadius <= 0) {
                            return;
                        }

                        pieCenterX = sliceCenterX;
                        pieCenterY = sliceCenterY;
                        maxOuterRadius = Math.max(maxOuterRadius, outerRadius);

                        const cos = Math.cos(midAngle);
                        const sin = Math.sin(midAngle);
                        const isRight = cos >= 0;
                        const rimRadius = outerRadius * 0.985;
                        const percent = ((value / total) * 100).toFixed(1).replace('.', ',') + '%';
                        const name = shortenLabel(labels[index] || 'Plan', metrics.nameChars);

                        const entry = {
                            index,
                            value,
                            color: resolveSliceColor(dataset, index),
                            line1: percent + '  ' + name,
                            line2: formatValueLabel(value, dataset),
                            isRight,
                            startX: sliceCenterX + cos * rimRadius,
                            startY: sliceCenterY + sin * rimRadius,
                            idealY: sliceCenterY + sin * (outerRadius + metrics.railGap),
                            y: sliceCenterY + sin * (outerRadius + metrics.railGap),
                            stemExtra: 0,
                        };

                        if (isRight) {
                            rightLabels.push(entry);
                        } else {
                            leftLabels.push(entry);
                        }
                    });

                    if (maxOuterRadius <= 0) {
                        return;
                    }

                    let leftRailX = pieCenterX - maxOuterRadius - metrics.railGap;
                    let rightRailX = pieCenterX + maxOuterRadius + metrics.railGap;

                    // Mantener rieles dentro del área dibujable.
                    leftRailX = Math.max(chartArea.left + 4, leftRailX);
                    rightRailX = Math.min(chartArea.right - 4, rightRailX);

                    const minY = chartArea.top + metrics.labelBlockHeight / 2 + 6;
                    const maxY = chartArea.bottom - metrics.labelBlockHeight / 2 - 6;
                    const visible = [
                        ...packLabelsWithoutOverlap(leftLabels, minY, maxY, metrics.minGap),
                        ...packLabelsWithoutOverlap(rightLabels, minY, maxY, metrics.minGap),
                    ];

                    visible.forEach((entry) => {
                        const railX = entry.isRight ? rightRailX : leftRailX;
                        const stem = metrics.stemBase + (entry.stemExtra || 0) * metrics.stemStep;
                        let endX = railX + (entry.isRight ? stem : -stem);
                        const endY = entry.y;

                        ctx.save();
                        ctx.font = metrics.fontTitle;
                        const line1Width = ctx.measureText(entry.line1).width;
                        ctx.font = metrics.fontValue;
                        const line2Width = ctx.measureText(entry.line2).width;
                        const textWidth = Math.max(line1Width, line2Width) + metrics.textPad + 4;

                        if (entry.isRight) {
                            const maxEnd = chartArea.right - textWidth;
                            endX = Math.min(endX, maxEnd);
                            endX = Math.max(endX, railX + 8);
                        } else {
                            const minEnd = chartArea.left + textWidth;
                            endX = Math.max(endX, minEnd);
                            endX = Math.min(endX, railX - 8);
                        }

                        const textX = endX + (entry.isRight ? metrics.textPad : -metrics.textPad);

                        ctx.strokeStyle = entry.color;
                        ctx.fillStyle = entry.color;
                        ctx.lineWidth = 1.5;
                        ctx.lineJoin = 'round';
                        ctx.lineCap = 'round';

                        ctx.beginPath();
                        ctx.moveTo(entry.startX, entry.startY);
                        ctx.lineTo(railX, entry.startY);
                        ctx.lineTo(railX, endY);
                        ctx.lineTo(endX, endY);
                        ctx.stroke();

                        ctx.beginPath();
                        ctx.arc(endX, endY, 2.5, 0, Math.PI * 2);
                        ctx.fill();

                        ctx.textAlign = entry.isRight ? 'left' : 'right';
                        ctx.textBaseline = 'middle';
                        ctx.shadowColor = isDark
                            ? 'rgba(2, 6, 23, 0.75)'
                            : 'rgba(255, 255, 255, 0.95)';
                        ctx.shadowBlur = 3;

                        ctx.font = metrics.fontTitle;
                        ctx.fillText(entry.line1, textX, endY - 6);

                        ctx.font = metrics.fontValue;
                        ctx.globalAlpha = 0.95;
                        ctx.fillText(entry.line2, textX, endY + 7);
                        ctx.restore();
                    });
                },
            };

            window.filamentChartJsPlugins.push(plugin);
            window.__fiMetricsPieOutLabelsVersion = PLUGIN_VERSION;
            window.__fiMetricsPieOutLabelsReady = true;
        })();
    </script>
</div>
