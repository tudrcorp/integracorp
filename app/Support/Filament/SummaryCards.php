<?php

declare(strict_types=1);

namespace App\Support\Filament;

use Illuminate\Support\HtmlString;

/**
 * Tarjetas de resumen para el subtítulo de una página de listado (totales de la
 * tabla). Estilos en línea más utilidades de texto ya compiladas por el tema, para
 * no depender de clases nuevas de Tailwind; funcionan en claro y oscuro.
 */
final class SummaryCards
{
    public const BLUE = '#3b82f6';

    public const RED = '#dc2626';

    public const AMBER = '#d97706';

    public const GREEN = '#16a34a';

    /**
     * `breakdown` agrega una fila de cifras dentro de la tarjeta. Cada cifra puede
     * llevar `action`, una llamada Livewire del componente de la página (por
     * ejemplo aplicar un filtro): entonces es un botón, y `active` lo resalta.
     *
     * @param  list<array{label: string, value: string, detail?: string|null, color: string, breakdown?: list<array{label: string, value: string, detail?: string|null, action?: string|null, active?: bool, title?: string|null}>}>  $cards
     */
    public static function render(array $cards): HtmlString
    {
        $html = '';

        foreach ($cards as $card) {
            $html .= self::card($card['label'], $card['value'], $card['detail'] ?? null, $card['color'], $card['breakdown'] ?? []);
        }

        // Misma altura en la fila y contenido centrado: una tarjeta con desglose ya
        // no deja a las demás con el texto pegado arriba y un hueco debajo.
        return new HtmlString('<div class="tdg-summary-cards" style="display:flex;flex-wrap:wrap;align-items:stretch;gap:10px;width:100%;margin-top:8px;">'.$html.'</div>');
    }

    /**
     * Las mismas tarjetas dentro de un panel que se abre y se cierra, **cerrado por
     * defecto**. Usa el diseño de los paneles colapsables de Ventas («Análisis de
     * ingresos»): mismas clases `fi-admin-sales-stats-panel*` del tema, así que
     * lucen igual en claro y oscuro.
     *
     * El estado vive en Alpine y no en Livewire: filtrar o paginar la tabla vuelve
     * a pintar el subtítulo, y un estado del servidor lo cerraría en cada clic.
     *
     * `$hint` (p. ej. el total) se muestra junto a «Mostrar» mientras está
     * cerrado, para no perder la cifra clave sin abrir el panel.
     *
     * @param  list<array{label: string, value: string, detail?: string|null, color: string, breakdown?: list<array{label: string, value: string, detail?: string|null, action?: string|null, active?: bool, title?: string|null}>}>  $cards
     */
    public static function collapsible(
        array $cards,
        string $label,
        ?string $description = null,
        string $icon = 'heroicon-o-banknotes',
        ?string $hint = null,
    ): HtmlString {
        $collapsedText = 'Colapsado · haz clic para ver las métricas';
        $expandedText = filled($description) ? $description : $collapsedText;

        $trigger = '<button type="button" class="fi-admin-sales-stats-panel__trigger" x-on:click="open = ! open"'
            .' aria-expanded="false" x-bind:aria-expanded="open ? \'true\' : \'false\'">'
            .'<span class="fi-admin-sales-stats-panel__trigger-main">'
            .'<span class="fi-admin-sales-stats-panel__icon" aria-hidden="true">'.svg($icon, 'fi-admin-sales-stats-panel__icon-svg')->toHtml().'</span>'
            .'<span class="min-w-0 text-left">'
            .'<span class="fi-admin-sales-stats-panel__title">'.e(mb_strtoupper($label)).'</span>'
            .'<span class="fi-admin-sales-stats-panel__subtitle" x-text="open ? '.e(json_encode($expandedText, JSON_UNESCAPED_UNICODE)).' : '.e(json_encode($collapsedText, JSON_UNESCAPED_UNICODE)).'">'.e($collapsedText).'</span>'
            .'</span>'
            .'</span>'
            .'<span class="fi-admin-sales-stats-panel__trigger-meta">'
            .(filled($hint)
                ? '<span class="text-gray-700 dark:text-gray-200" x-show="! open" style="font-size:.8rem;font-weight:700;font-variant-numeric:tabular-nums;white-space:nowrap;">'.e($hint).'</span>'
                    .'<span class="fi-admin-sales-stats-panel__state" x-show="! open" aria-hidden="true">·</span>'
                : '')
            .'<span class="fi-admin-sales-stats-panel__state" x-text="open ? \'Ocultar\' : \'Mostrar\'">Mostrar</span>'
            .'<span class="fi-admin-sales-stats-panel__chevron" aria-hidden="true">'
            .'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" width="16" height="16" class="size-4">'
            .'<path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" /></svg>'
            .'</span>'
            .'</span>'
            .'</button>';

        return new HtmlString(
            '<div class="tdg-summary-cards fi-admin-sales-stats-widget--ingresos" x-data="{ open: false }" style="width:100%;margin-top:8px;">'
            .'<div class="fi-admin-sales-stats-panel" data-expanded="false" x-bind:data-expanded="open ? \'true\' : \'false\'">'
            .$trigger
            .'<div class="fi-admin-sales-stats-panel__body" x-show="open" x-collapse x-cloak>'.self::render($cards)->toHtml().'</div>'
            .'</div>'
            .'</div>'
        );
    }

    public static function money(float $amount): string
    {
        return 'US$ '.number_format($amount, 2, ',', '.');
    }

    public static function count(int $count, string $singular, string $plural): string
    {
        return $count === 1 ? '1 '.$singular : number_format($count, 0, ',', '.').' '.$plural;
    }

    /**
     * @param  list<array{label: string, value: string, detail?: string|null, action?: string|null, active?: bool, title?: string|null}>  $breakdown
     */
    private static function card(string $label, string $value, ?string $detail, string $color, array $breakdown = []): string
    {
        [$red, $green, $blue] = sscanf($color, '#%02x%02x%02x');
        $tint = static fn (float $alpha): string => 'rgba('.$red.','.$green.','.$blue.','.$alpha.')';

        $grow = $breakdown !== [] ? '2 1 320px' : '1 1 170px';

        return '<div style="flex:'.$grow.';display:flex;flex-direction:column;align-items:center;justify-content:center;gap:1px;text-align:center;'
            .'padding:10px 14px;border-radius:14px;border:1px solid '.$tint(.3).';background:'.$tint(.08).';">'
            .'<div style="font-size:.62rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:'.e($color).';">'.e($label).'</div>'
            .'<div class="text-gray-900 dark:text-white" style="font-size:1.15rem;font-weight:700;font-variant-numeric:tabular-nums;line-height:1.25;">'.e($value).'</div>'
            .($detail !== null && $detail !== '' ? '<div class="text-gray-600 dark:text-gray-300" style="font-size:.7rem;">'.e($detail).'</div>' : '')
            .self::breakdown($breakdown, $tint)
            .'</div>';
    }

    /**
     * @param  list<array{label: string, value: string, detail?: string|null, action?: string|null, active?: bool, title?: string|null}>  $items
     * @param  callable(float): string  $tint
     */
    private static function breakdown(array $items, callable $tint): string
    {
        if ($items === []) {
            return '';
        }

        $html = '';

        foreach ($items as $item) {
            $active = (bool) ($item['active'] ?? false);
            $action = trim((string) ($item['action'] ?? ''));
            $style = 'flex:1 1 0;min-width:96px;padding:5px 8px;border-radius:10px;text-align:center;'
                .'border:1px solid '.$tint($active ? .7 : .25).';background:'.$tint($active ? .22 : .06).';';
            $content = '<div style="font-size:.58rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;" class="text-gray-600 dark:text-gray-300">'.e($item['label']).'</div>'
                .'<div class="text-gray-900 dark:text-white" style="font-size:.78rem;font-weight:700;font-variant-numeric:tabular-nums;white-space:nowrap;">'.e($item['value']).'</div>'
                .(filled($item['detail'] ?? null) ? '<div class="text-gray-600 dark:text-gray-300" style="font-size:.62rem;">'.e($item['detail']).'</div>' : '');

            $html .= $action !== ''
                ? '<button type="button" wire:click="'.e($action).'" wire:loading.attr="disabled"'
                    .' aria-pressed="'.($active ? 'true' : 'false').'"'
                    .(filled($item['title'] ?? null) ? ' title="'.e($item['title']).'"' : '')
                    .' style="'.$style.'cursor:pointer;">'.$content.'</button>'
                : '<div style="'.$style.'">'.$content.'</div>';
        }

        return '<div style="display:flex;flex-wrap:wrap;justify-content:center;gap:6px;width:100%;margin-top:6px;">'.$html.'</div>';
    }
}
