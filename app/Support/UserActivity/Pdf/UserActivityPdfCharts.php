<?php

declare(strict_types=1);

namespace App\Support\UserActivity\Pdf;

use App\Support\UserActivity\UserActivityState;
use App\Support\UserActivity\Workday\WorkMinuteKind;

/**
 * Gráficos del PDF de actividad de una persona, como SVG dibujado en el servidor y
 * embebido como imagen (DomPDF no ejecuta JS; rect y text sí los pinta, y una imagen
 * por barra es mucho más rápida que cientos de celdas de tabla).
 *
 * Usan los mismos colores que la pantalla: {@see WorkMinuteKind::color()} y
 * {@see UserActivityState::color()}.
 */
final class UserActivityPdfCharts
{
    /** Fondo de la barra (minutos sin conexión). */
    private const TRACK = '#EEF2F7';

    private const MARK_WORK = '#475569';

    private const MARK_GUARD = '#6366F1';

    private const MARK_GUARD_EXTRA = '#4338CA';

    /**
     * Barra del día minuto a minuto: los tramos con el color de su categoría, las guías
     * cada 3 h y, encima, la jornada y las guardias como marcas finas (línea superior e
     * inicio/fin), igual que en la pantalla.
     *
     * @param  list<array<string, mixed>>  $segments  Tramos con `from`, `to` (inclusive) y `kind` o `state`.
     * @param  list<array<string, mixed>>  $bands  Franjas con `from`, `to` y `type` (work, guard, guard-extra).
     */
    public static function dayline(array $segments, array $bands = [], int $height = 10): string
    {
        $body = '<rect x="0" y="0" width="1440" height="'.$height.'" fill="'.self::TRACK.'"/>';

        foreach ($segments as $segment) {
            $body .= '<rect x="'.(int) $segment['from'].'" y="0" width="'.((int) $segment['to'] - (int) $segment['from'] + 1).'" height="'.$height.'" fill="'.self::segmentColor($segment).'"/>';
        }

        foreach ([180, 360, 540, 720, 900, 1080, 1260] as $guide) {
            $body .= '<rect x="'.$guide.'" y="0" width="1.5" height="'.$height.'" fill="'.($guide % 360 === 0 ? '#94A3B8' : '#CBD5E1').'" fill-opacity="0.7"/>';
        }

        foreach ($bands as $band) {
            $color = match ($band['type'] ?? '') {
                'work' => self::MARK_WORK,
                'guard' => self::MARK_GUARD,
                default => self::MARK_GUARD_EXTRA,
            };
            $from = (int) $band['from'];
            $width = max(1, (int) $band['to'] - $from);
            $body .= '<rect x="'.$from.'" y="0" width="'.$width.'" height="'.round($height * 0.16, 2).'" fill="'.$color.'"/>'
                .'<rect x="'.$from.'" y="0" width="3" height="'.$height.'" fill="'.$color.'"/>'
                .'<rect x="'.max(0, $from + $width - 3).'" y="0" width="3" height="'.$height.'" fill="'.$color.'"/>';
        }

        return self::svg(1440, $height, $body, 'none');
    }

    /**
     * Escala de horas alineada con la barra (12a … 12a).
     */
    public static function hourScale(): string
    {
        $body = '';

        foreach ([0, 3, 6, 9, 12, 15, 18, 21, 24] as $hour) {
            $label = match (true) {
                $hour === 0 || $hour === 24 => '12a',
                $hour < 12 => $hour.'a',
                $hour === 12 => '12p',
                default => ($hour - 12).'p',
            };
            $anchor = match ($hour) {
                0 => 'start',
                24 => 'end',
                default => 'middle',
            };
            $body .= '<text x="'.($hour * 60).'" y="17" font-family="Helvetica" font-size="18" fill="#64748B" text-anchor="'.$anchor.'">'.$label.'</text>';
        }

        return self::svg(1440, 22, $body);
    }

    /**
     * Mapa de calor día de la semana × hora (minutos activos), con rótulos.
     *
     * @param  array<int, list<int>>  $heatmap  Lunes = 0 … domingo = 6, 24 horas cada uno.
     */
    public static function heatmap(array $heatmap, int $max): string
    {
        $labelWidth = 40;
        $cell = 26;
        $gap = 3;
        $top = 18;
        $max = max(1, $max);
        $body = '';

        for ($hour = 0; $hour < 24; $hour += 3) {
            $body .= '<text x="'.($labelWidth + $hour * ($cell + $gap) + $cell / 2).'" y="12" font-family="Helvetica" font-size="11" fill="#64748B" text-anchor="middle">'.$hour.'</text>';
        }

        foreach (['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $weekday => $label) {
            $y = $top + $weekday * ($cell * 0.62 + $gap);
            $body .= '<text x="0" y="'.round($y + $cell * 0.45, 2).'" font-family="Helvetica" font-size="11" fill="#475569">'.$label.'</text>';

            foreach ($heatmap[$weekday] ?? [] as $hour => $minutes) {
                $minutes = (int) $minutes;
                $fill = $minutes > 0 ? '#16A34A' : self::TRACK;
                $opacity = $minutes > 0 ? round(0.15 + 0.85 * $minutes / $max, 2) : 1;
                $body .= '<rect x="'.($labelWidth + $hour * ($cell + $gap)).'" y="'.round($y, 2).'" width="'.$cell.'" height="'.round($cell * 0.62, 2).'" rx="3" fill="'.$fill.'" fill-opacity="'.$opacity.'"/>';
            }
        }

        $width = $labelWidth + 24 * ($cell + $gap);
        $height = (int) ceil($top + 7 * ($cell * 0.62 + $gap));

        return self::svg($width, $height, $body);
    }

    public static function dataUri(string $svg): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /**
     * @param  array<string, mixed>  $segment
     */
    private static function segmentColor(array $segment): string
    {
        $kind = $segment['kind'] ?? null;

        if ($kind instanceof WorkMinuteKind) {
            return $kind === WorkMinuteKind::Free ? self::TRACK : $kind->color();
        }

        $state = $segment['state'] ?? null;

        return $state instanceof UserActivityState ? $state->color() : self::TRACK;
    }

    private static function svg(int $width, int $height, string $body, string $aspect = 'xMidYMid meet'): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$width.'" height="'.$height.'" viewBox="0 0 '.$width.' '.$height.'" preserveAspectRatio="'.$aspect.'">'.$body.'</svg>';
    }
}
