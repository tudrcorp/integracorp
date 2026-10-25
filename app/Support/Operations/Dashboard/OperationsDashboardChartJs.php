<?php

declare(strict_types=1);

namespace App\Support\Operations\Dashboard;

use Filament\Support\RawJs;

/**
 * Opciones de Chart.js del Dashboard de Operaciones.
 *
 * Los colores se resuelven en el navegador según el tema (claro u oscuro), por
 * eso viajan como funciones y no en los datos: Filament re-inicializa el gráfico
 * al cambiar de tema y las funciones vuelven a leerlo. Paleta validada con la
 * guía de visualización (CVD y contraste en ambos modos):
 *
 * - serie principal: azul; comparación: gris neutro;
 * - estatus: colores de estado reservados (pendiente = advertencia,
 *   finalizado = correcto), siempre con etiqueta y conteo al lado;
 * - línea de negocio: azul y naranja.
 */
final class OperationsDashboardChartJs
{
    /**
     * Paleta por rol y modo. Mismo contenido que los tokens CSS del dashboard.
     *
     * @var array<string, array<string, string>>
     */
    public const PALETTE = [
        'light' => [
            'series1' => '#2a78d6',
            'series2' => '#eb6834',
            'previous' => '#a3a29c',
            'muted' => '#898781',
            'text' => '#52514e',
            'grid' => '#e1e0d9',
            'surface' => '#fcfcfb',
            'tooltip' => '#1a1a19',
            'tooltipText' => '#f0efec',
            'good' => '#0ca30c',
            'warning' => '#fab219',
            'critical' => '#d03b3b',
            'violet' => '#6250d6',
        ],
        'dark' => [
            'series1' => '#3987e5',
            'series2' => '#d95926',
            'previous' => '#6f6e69',
            'muted' => '#898781',
            'text' => '#c3c2b7',
            'grid' => '#2c2c2a',
            'surface' => '#1a1a19',
            'tooltip' => '#f0efec',
            'tooltipText' => '#0b0b0b',
            'good' => '#0ca30c',
            'warning' => '#fab219',
            'critical' => '#d03b3b',
            'violet' => '#9085e9',
        ],
    ];

    /**
     * Color de cada estatus (rol de la paleta). Lo que no esté aquí va en gris.
     *
     * @var array<string, string>
     */
    public const STATUS_ROLES = [
        'PENDIENTE' => 'warning',
        'EN GESTION' => 'series1',
        'FINALIZADO' => 'good',
        'REASIGNADO A TDG' => 'violet',
        'CANCELADO' => 'critical',
        'CANCELADA' => 'critical',
        'CADUCADA' => 'critical',
        'CADUCADO' => 'critical',
        /** Estados del caso (dashboard del proveedor). */
        'ASIGNADO' => 'warning',
        'EN SEGUIMIENTO' => 'series1',
        'ALTA MEDICA' => 'good',
    ];

    public static function color(string $role, bool $dark = false): string
    {
        return self::PALETTE[$dark ? 'dark' : 'light'][$role] ?? self::PALETTE['light']['muted'];
    }

    public static function statusRole(string $status): string
    {
        return self::STATUS_ROLES[mb_strtoupper(trim($status))] ?? 'muted';
    }

    /**
     * Opciones completas: `$body` es el interior del objeto de opciones y puede
     * usar `ops` (paleta del tema activo) y `fmt` (número con separador de miles).
     */
    public static function options(string $body): RawJs
    {
        $palette = self::jsLiteral(self::PALETTE);
        $statusRoles = self::jsLiteral(self::STATUS_ROLES);

        return RawJs::make(<<<JS
        (() => {
            const ops = {$palette}[document.documentElement.classList.contains('dark') ? 'dark' : 'light'];
            const statusRoles = {$statusRoles};
            const statusColor = (label) => ops[statusRoles[String(label ?? '').trim().toUpperCase()] ?? 'muted'];
            const fmt = (value) => new Intl.NumberFormat('es-VE').format(value ?? 0);
            const pct = (value, total) => total > 0 ? new Intl.NumberFormat('es-VE', { maximumFractionDigits: 1 }).format(value * 100 / total) + ' %' : '0 %';
            const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            const nf1 = (value) => new Intl.NumberFormat('es-VE', { maximumFractionDigits: 1 }).format(value ?? 0);
            const money = (value) => 'US$ ' + new Intl.NumberFormat('es-VE', { maximumFractionDigits: 0 }).format(value ?? 0);
            const moneyShort = (value) => 'US$ ' + new Intl.NumberFormat('es-VE', { notation: 'compact', maximumFractionDigits: 1 }).format(value ?? 0);
            const tipClass = 'pointer-events-none absolute z-20 w-60 rounded-xl bg-gray-950 px-3.5 py-3 text-white shadow-xl ring-1 ring-white/10 dark:bg-white dark:text-gray-950 dark:ring-gray-950/10';

            /*
             * Tooltip propio (mismo diseño que el del mapa de calor). Se arma con
             * textContent: un nombre de servicio o paciente nunca se lee como HTML.
             * spec = { title, rows: [{ swatch, value, unit, big }], lines: [], footer }.
             */
            const renderTip = (context, build) => {
                const chart = context.chart;
                const tooltip = context.tooltip;
                const host = chart.canvas.parentNode;

                if (getComputedStyle(host).position === 'static') { host.style.position = 'relative'; }

                let tip = host.querySelector(':scope > .ops-chart-tip');

                if (!tip) {
                    tip = document.createElement('div');
                    tip.className = 'ops-chart-tip ' + tipClass;
                    tip.setAttribute('role', 'tooltip');
                    tip.style.opacity = '0';
                    tip.style.transform = 'translate(-50%, -100%)';
                    tip.style.transition = reduced ? 'none' : 'opacity 150ms ease, left 120ms ease, top 120ms ease';
                    host.appendChild(tip);
                }

                const spec = tooltip && tooltip.opacity !== 0 && tooltip.dataPoints && tooltip.dataPoints.length ? build(tooltip, chart) : null;

                if (!spec) { tip.style.opacity = '0'; return; }

                const node = (tag, className, text) => {
                    const element = document.createElement(tag);
                    element.className = className;
                    if (text !== undefined && text !== null) { element.textContent = String(text); }
                    return element;
                };

                tip.replaceChildren(node('p', 'text-xs font-medium opacity-70', spec.title));

                (spec.rows || []).forEach((row) => {
                    const line = node('div', 'mt-1 flex items-center gap-2');
                    if (row.swatch) {
                        const swatch = node('span', 'size-3 shrink-0 rounded-[3px] ring-1 ring-white/20 dark:ring-gray-950/20');
                        swatch.style.background = row.swatch;
                        line.appendChild(swatch);
                    }
                    line.appendChild(node('span', row.big === false ? 'text-sm font-semibold tabular-nums' : 'text-xl font-semibold tabular-nums', row.value));
                    line.appendChild(node('span', 'text-xs opacity-70', row.unit || ''));
                    tip.appendChild(line);
                });

                (spec.lines || []).forEach((text) => tip.appendChild(node('p', 'mt-1 text-xs opacity-80', text)));

                if (spec.footer) {
                    tip.appendChild(node('p', 'mt-2 border-t border-white/10 pt-1.5 text-[11px] opacity-60 dark:border-gray-950/10', spec.footer));
                }

                const half = tip.offsetWidth / 2;
                tip.style.left = Math.min(Math.max(tooltip.caretX, half), Math.max(half, host.clientWidth - half)) + 'px';
                tip.style.top = (tooltip.caretY - 12) + 'px';
                tip.style.opacity = '1';
            };

            return {
                responsive: true,
                maintainAspectRatio: false,
                animation: reduced ? false : { duration: 900, easing: 'easeOutQuart' },
                transitions: { resize: { animation: { duration: reduced ? 0 : 650, easing: 'easeOutQuart' } } },
                layout: { padding: { top: 6, right: 10, bottom: 2, left: 2 } },
                {$body}
            };
        })()
        JS);
    }

    /**
     * Texto como literal JS entre comillas simples (nunca dobles: ver {@see self::jsLiteral()}).
     */
    public static function jsString(string $value): string
    {
        return "'".str_replace(['\\', "'", "\n", "\r", '"'], ['\\\\', "\\'", ' ', ' ', '\\u0022'], $value)."'";
    }

    /**
     * Singular de los sustantivos que usa el dashboard.
     */
    public static function singular(string $plural): string
    {
        return match ($plural) {
            'servicios' => 'servicio',
            'consultas' => 'consulta',
            'casos' => 'caso',
            'pacientes' => 'paciente',
            default => rtrim($plural, 's'),
        };
    }

    /**
     * Objeto JS con comillas simples.
     *
     * Las opciones viajan dentro de un atributo HTML con comillas dobles
     * (`x-data="chart({...})"`): una sola comilla doble cierra el atributo y el
     * código se imprime como texto en la página. Por eso nada aquí puede llevar
     * comillas dobles.
     *
     * @param  array<string, mixed>  $value
     */
    public static function jsLiteral(array $value): string
    {
        $json = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_HEX_APOS);

        return str_replace('"', "'", $json);
    }

    /**
     * Tooltip propio del dashboard. `$builder` es una función JS
     * `(tooltip, chart) => spec` (ver `renderTip` en {@see self::options()}).
     */
    public static function tooltip(string $builder): string
    {
        return <<<JS
        tooltip: {
            enabled: false,
            external: (context) => renderTip(context, {$builder}),
        },
        JS;
    }

    /**
     * Ejes discretos: grilla en línea fina, sin borde, rótulos en tinta tenue.
     */
    public static function axis(string $extra = '', bool $grid = true, string $ticks = ''): string
    {
        $gridDisplay = $grid ? 'true' : 'false';

        return <<<JS
        {
            border: { display: false },
            grid: { display: {$gridDisplay}, color: ops.grid, drawTicks: false, lineWidth: 1 },
            ticks: { color: ops.text, padding: 8, font: { size: 11 }, {$ticks} },
            {$extra}
        }
        JS;
    }

    /**
     * Clic en un elemento → abre el detalle de ese corte en el dashboard.
     */
    public static function onClickDetail(string $dimension): string
    {
        return <<<JS
        onClick: (event, elements, chart) => {
            if (!elements || !elements.length) { return; }
            const label = chart.data.labels[elements[0].index];
            \$wire.dispatch('operations-dashboard-detail', { dimension: '{$dimension}', value: String(label) });
        },
        onHover: (event, elements) => {
            if (event?.native?.target) { event.native.target.style.cursor = elements.length ? 'pointer' : 'default'; }
        },
        JS;
    }
}
