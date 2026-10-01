<?php

declare(strict_types=1);

namespace App\Support\Filament;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Encabezado de ficha para páginas View/Edit de Filament: avatar (logo o iniciales),
 * etiqueta superior, nombre, estado, etiquetas de contexto y una fila de datos clave.
 *
 * Usa estilos en línea más las pocas utilidades de color de texto que ya compila el
 * tema (claro/oscuro), para no depender de clases nuevas de Tailwind.
 */
final class RecordPageHeader
{
    public const TONE_SUCCESS = 'success';

    public const TONE_DANGER = 'danger';

    public const TONE_WARNING = 'warning';

    public const TONE_INFO = 'info';

    public const TONE_VIOLET = 'violet';

    public const TONE_NEUTRAL = 'neutral';

    /**
     * @param  array{label: string, tone: string}|null  $status
     * @param  list<array{label: string, tone: string}|null>  $chips
     * @param  array<string, string|null>|list<array<string, string|null>>  $facts  etiqueta => valor (una
     *                                                                              fila) o lista de filas; los vacíos se omiten
     */
    public static function render(
        string $eyebrow,
        string $title,
        ?array $status = null,
        array $chips = [],
        array $facts = [],
        ?string $imageUrl = null,
    ): HtmlString {
        $badges = '';

        if ($status !== null && trim($status['label']) !== '') {
            $badges .= self::statusPill($status['label'], $status['tone']);
        }

        foreach (array_filter($chips) as $chip) {
            if (trim($chip['label']) !== '') {
                $badges .= self::chip($chip['label'], $chip['tone']);
            }
        }

        $factRows = array_is_list($facts) && $facts !== [] && is_array($facts[0]) ? $facts : [$facts];
        $factsHtml = '';

        foreach ($factRows as $row) {
            $rowHtml = '';

            foreach ($row as $label => $value) {
                $value = trim((string) ($value ?? ''));

                if ($value === '') {
                    continue;
                }

                $rowHtml .= '<span style="display:flex;flex-direction:column;gap:1px;min-width:0;max-width:260px;">'
                    .'<span class="text-gray-600 dark:text-gray-300" style="font-size:.68rem;font-weight:600;letter-spacing:.06em;text-transform:uppercase;opacity:.8;">'.e((string) $label).'</span>'
                    .'<span class="text-gray-900 dark:text-white" style="font-size:.875rem;font-weight:600;overflow-wrap:break-word;">'.str_replace('@', '@<wbr>', e($value)).'</span>'
                    .'</span>';
            }

            if ($rowHtml !== '') {
                $factsHtml .= '<div style="display:flex;flex-wrap:wrap;align-items:flex-start;gap:8px 28px;max-width:680px;">'.$rowHtml.'</div>';
            }
        }

        return new HtmlString(
            '<div style="display:flex;align-items:flex-start;gap:16px;padding:10px 0;">'
            .self::avatar($title, $imageUrl)
            .'<div style="display:flex;flex-direction:column;gap:8px;min-width:0;flex:1;">'
            .'<span class="text-gray-600 dark:text-gray-300" style="font-size:.75rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;">'.e($eyebrow).'</span>'
            .'<span class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white" style="line-height:1.15;overflow-wrap:anywhere;">'.e($title).'</span>'
            .($badges !== '' ? '<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">'.$badges.'</div>' : '')
            .($factsHtml !== '' ? '<div style="display:flex;flex-direction:column;gap:8px;margin-top:2px;">'.$factsHtml.'</div>' : '')
            .'</div>'
            .'</div>'
        );
    }

    /**
     * @return array{label: string, tone: string}
     */
    public static function tag(string $label, string $tone = self::TONE_INFO): array
    {
        return ['label' => $label, 'tone' => $tone];
    }

    /**
     * @return array{label: string, tone: string}|null
     */
    public static function statusFor(?string $status): ?array
    {
        $label = Str::upper(trim((string) ($status ?? '')));

        if ($label === '') {
            return null;
        }

        $tone = match (Str::ascii($label)) {
            'ACTIVO', 'ACTIVA', 'VIGENTE', 'APROBADA', 'APROBADO' => self::TONE_SUCCESS,
            'PRE-APROBADA', 'PRE-APROBADO', 'POR REVISION', 'PERIODO DE RENOVACION', 'PENDIENTE' => self::TONE_WARNING,
            'INACTIVO', 'INACTIVA', 'EXCLUIDO', 'EXCLUIDA', 'ANULADA', 'ANULADO', 'VENCIDA', 'VENCIDO', 'RECHAZADA' => self::TONE_DANGER,
            default => self::TONE_NEUTRAL,
        };

        return self::tag($label, $tone);
    }

    /**
     * @return array{label: string, tone: string}|null
     */
    public static function remainingDaysTag(mixed $remainingDays): ?array
    {
        if (! is_numeric($remainingDays)) {
            return null;
        }

        $days = (int) $remainingDays;

        return match (true) {
            $days < 0 => self::tag('Vencida hace '.abs($days).' '.(abs($days) === 1 ? 'día' : 'días'), self::TONE_DANGER),
            $days === 0 => self::tag('Vence hoy', self::TONE_DANGER),
            $days <= 30 => self::tag('Vence en '.$days.' '.($days === 1 ? 'día' : 'días'), self::TONE_WARNING),
            default => self::tag('Faltan '.$days.' días', self::TONE_INFO),
        };
    }

    public static function money(mixed $amount, bool $hideZero = false): ?string
    {
        if ($amount === null || $amount === '' || ! is_numeric($amount)) {
            return null;
        }

        if ($hideZero && (float) $amount == 0.0) {
            return null;
        }

        return 'US$ '.number_format((float) $amount, 2, ',', '.');
    }

    public static function initials(string $name): string
    {
        $words = array_values(array_filter(
            preg_split('/\s+/u', trim($name)) ?: [],
            fn (string $word): bool => preg_match('/^\p{L}/u', $word) === 1,
        ));

        if ($words === []) {
            return '·';
        }

        $first = mb_substr($words[0], 0, 1);
        $second = isset($words[1]) ? mb_substr($words[1], 0, 1) : '';

        return mb_strtoupper($first.$second);
    }

    private static function avatar(string $title, ?string $imageUrl): string
    {
        $box = 'flex-shrink:0;width:56px;height:56px;border-radius:16px;overflow:hidden;display:flex;align-items:center;justify-content:center;box-shadow:0 6px 18px rgba(5,47,96,.25);';

        if (filled($imageUrl)) {
            return '<span style="'.$box.'background:#fff;border:1px solid rgba(148,163,184,.35);">'
                .'<img src="'.e($imageUrl).'" alt="" style="width:100%;height:100%;object-fit:contain;padding:6px;" loading="lazy">'
                .'</span>';
        }

        return '<span aria-hidden="true" style="'.$box.'background:linear-gradient(135deg,#052F60 0%,#2d89ca 100%);color:#fff;font-size:1.15rem;font-weight:700;letter-spacing:.02em;">'
            .e(self::initials($title))
            .'</span>';
    }

    private static function statusPill(string $label, string $tone): string
    {
        $color = self::toneColor($tone);

        return '<span style="display:inline-flex;align-items:center;gap:6px;background-color:'.$color.';color:#fff;padding:4px 12px;border-radius:999px;font-size:.75rem;font-weight:700;letter-spacing:.02em;box-shadow:0 6px 16px '.self::rgba($color, .35).';">'
            .'<span style="width:6px;height:6px;border-radius:999px;background:#fff;opacity:.9;"></span>'
            .e($label)
            .'</span>';
    }

    private static function chip(string $label, string $tone): string
    {
        $color = self::toneColor($tone);

        return '<span style="display:inline-flex;align-items:center;background-color:'.self::rgba($color, .14).';color:'.$color.';border:1px solid '.self::rgba($color, .35).';padding:3px 10px;border-radius:999px;font-size:.75rem;font-weight:600;">'
            .e($label)
            .'</span>';
    }

    private static function toneColor(string $tone): string
    {
        return match ($tone) {
            self::TONE_SUCCESS => '#16a34a',
            self::TONE_DANGER => '#dc2626',
            self::TONE_WARNING => '#d97706',
            self::TONE_INFO => '#3b82f6',
            self::TONE_VIOLET => '#8b5cf6',
            default => '#6b7280',
        };
    }

    private static function rgba(string $hex, float $alpha): string
    {
        [$red, $green, $blue] = sscanf($hex, '#%02x%02x%02x');

        return 'rgba('.$red.','.$green.','.$blue.','.$alpha.')';
    }
}
