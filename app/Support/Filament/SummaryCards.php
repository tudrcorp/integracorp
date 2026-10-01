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
     * @param  list<array{label: string, value: string, detail?: string|null, color: string}>  $cards
     */
    public static function render(array $cards): HtmlString
    {
        $html = '';

        foreach ($cards as $card) {
            $html .= self::card($card['label'], $card['value'], $card['detail'] ?? null, $card['color']);
        }

        return new HtmlString('<div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:8px;">'.$html.'</div>');
    }

    public static function money(float $amount): string
    {
        return 'US$ '.number_format($amount, 2, ',', '.');
    }

    public static function count(int $count, string $singular, string $plural): string
    {
        return $count === 1 ? '1 '.$singular : number_format($count, 0, ',', '.').' '.$plural;
    }

    private static function card(string $label, string $value, ?string $detail, string $color): string
    {
        [$red, $green, $blue] = sscanf($color, '#%02x%02x%02x');
        $tint = static fn (float $alpha): string => 'rgba('.$red.','.$green.','.$blue.','.$alpha.')';

        return '<div style="min-width:200px;padding:10px 14px;border-radius:14px;border:1px solid '.$tint(.3).';background:'.$tint(.08).';">'
            .'<div style="font-size:.68rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:'.e($color).';">'.e($label).'</div>'
            .'<div class="text-gray-900 dark:text-white" style="font-size:1.25rem;font-weight:700;font-variant-numeric:tabular-nums;line-height:1.3;">'.e($value).'</div>'
            .($detail !== null && $detail !== '' ? '<div class="text-gray-600 dark:text-gray-300" style="font-size:.75rem;">'.e($detail).'</div>' : '')
            .'</div>';
    }
}
