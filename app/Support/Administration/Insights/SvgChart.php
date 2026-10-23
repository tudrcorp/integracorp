<?php

declare(strict_types=1);

namespace App\Support\Administration\Insights;

/**
 * Gráficos del Informe administrativo dibujados en SVG en el servidor: DomPDF no ejecuta
 * JavaScript, así que el PDF los recibe ya dibujados. Cada barra y porción lleva un
 * `<title>` con su valor, por si el SVG se abre en un navegador.
 *
 * Solo usa lo que DomPDF dibuja bien: rectángulos, trayectos con arcos y textos.
 */
final class SvgChart
{
    public const BLUE = '#1F4E9E';

    public const GREEN = '#5CE0B0';

    /** @var list<string> */
    public const PALETTE = ['#1F4E9E', '#5CE0B0', '#3B82C4', '#2BB58A', '#93C5FD', '#A7F3D0', '#64748B', '#CBD5E1', '#0E7490', '#FBBF24'];

    private const FONT = 'DejaVu Sans, Helvetica, Arial, sans-serif';

    /**
     * Barras verticales: el valor dentro de la barra y el % sobre ella, como el informe de referencia.
     *
     * @param  list<string>  $labels
     * @param  list<float|int>  $values
     * @param  callable(float): string|null  $format  Cómo se escribe el valor (monto o cantidad).
     */
    public static function bars(array $labels, array $values, ?callable $format = null, int $width = 720, int $height = 160): string
    {
        $format ??= static fn (float $value): string => number_format($value, 0, ',', '.');
        $count = max(count($values), 1);
        $total = array_sum($values);
        $max = max([...$values, 0]) ?: 1;
        $top = 30;
        $bottom = 30;
        $plot = $height - $top - $bottom;
        $slot = $width / $count;
        $barWidth = min(64, $slot * 0.62);
        $body = '<line x1="0" y1="'.($height - $bottom).'" x2="'.$width.'" y2="'.($height - $bottom).'" stroke="#CBD5E1" stroke-width="1"/>';

        foreach (array_values($values) as $index => $value) {
            $value = (float) $value;
            $barHeight = $value <= 0 ? 0 : max(2, $plot * $value / $max);
            $x = $slot * $index + ($slot - $barWidth) / 2;
            $y = $height - $bottom - $barHeight;
            $label = (string) ($labels[$index] ?? '');
            $share = $total > 0 ? number_format($value * 100 / $total, 1, ',', '.').' %' : '0 %';
            $center = $x + $barWidth / 2;
            $formatted = $format($value);

            $body .= '<rect x="'.self::n($x).'" y="'.self::n($y).'" width="'.self::n($barWidth).'" height="'.self::n($barHeight).'" rx="2" fill="'.self::BLUE.'">'
                .'<title>'.e($label.': '.$formatted.' ('.$share.')').'</title></rect>';

            if ($value > 0) {
                $body .= self::text($center, $y - 14, $share, 9, '#0F766E', 'bold');
                $inside = $barHeight >= 16;
                $body .= self::text($center, $inside ? $y + 12 : $y - 3, $formatted, $count > 12 ? 7 : 8.5, $inside ? '#FFFFFF' : '#1E293B', 'bold');
            }

            $body .= self::text($center, $height - $bottom + 14, self::short($label, $count > 12 ? 7 : 12), $count > 12 ? 7 : 8.5, '#334155');
        }

        return self::svg($width, $height, $body);
    }

    /**
     * Barras horizontales para rankings (agencias, agentes): una fila por elemento.
     *
     * @param  list<string>  $labels
     * @param  list<float|int>  $values
     * @param  callable(float): string|null  $format
     */
    public static function horizontalBars(array $labels, array $values, ?callable $format = null, int $width = 720): string
    {
        $format ??= static fn (float $value): string => number_format($value, 0, ',', '.');
        $rowHeight = 16;
        $labelWidth = 230;
        $valueWidth = 110;
        $height = max(count($values), 1) * $rowHeight + 6;
        $max = max([...$values, 0]) ?: 1;
        $plot = $width - $labelWidth - $valueWidth;
        $body = '';

        foreach (array_values($values) as $index => $value) {
            $value = (float) $value;
            $y = $index * $rowHeight + 3;
            $barWidth = $value <= 0 ? 0 : max(2, $plot * $value / $max);
            $label = (string) ($labels[$index] ?? '');
            $formatted = $format($value);

            $body .= self::text($labelWidth - 8, $y + 11, self::short($label, 38), 7.5, '#334155', 'normal', 'end')
                .'<rect x="'.$labelWidth.'" y="'.$y.'" width="'.self::n($barWidth).'" height="'.($rowHeight - 6).'" rx="2" fill="'.($index === 0 ? self::BLUE : '#3B82C4').'">'
                .'<title>'.e($label.': '.$formatted).'</title></rect>'
                .self::text($labelWidth + $barWidth + 6, $y + 11, $formatted, 7.5, '#1E293B', 'bold', 'start');
        }

        return self::svg($width, $height, $body);
    }

    /**
     * Dona con su % en cada porción grande (la leyenda va en la tabla al lado).
     *
     * @param  list<float|int>  $values
     * @param  list<string>  $labels
     */
    public static function donut(array $values, array $labels = [], int $size = 210): string
    {
        $total = array_sum($values);
        $center = $size / 2;
        $radius = $size / 2 - 6;
        $hole = $radius * 0.55;

        if ($total <= 0) {
            return self::svg($size, $size, '<circle cx="'.$center.'" cy="'.$center.'" r="'.$radius.'" fill="#E2E8F0"/>'
                .'<circle cx="'.$center.'" cy="'.$center.'" r="'.self::n($hole).'" fill="#FFFFFF"/>'
                .self::text($center, $center + 4, 'Sin datos', 10, '#64748B'));
        }

        $body = '';
        $angle = -90.0;

        foreach (array_values($values) as $index => $value) {
            $value = (float) $value;

            if ($value <= 0) {
                continue;
            }

            $color = self::PALETTE[$index % count(self::PALETTE)];
            $sweep = 360 * $value / $total;
            $title = '<title>'.e(($labels[$index] ?? '').': '.number_format($value * 100 / $total, 1, ',', '.').' %').'</title>';

            if ($sweep >= 359.99) {
                $body .= '<circle cx="'.$center.'" cy="'.$center.'" r="'.$radius.'" fill="'.$color.'">'.$title.'</circle>';
            } else {
                [$x1, $y1] = self::point($center, $radius, $angle);
                [$x2, $y2] = self::point($center, $radius, $angle + $sweep);
                $body .= '<path d="M'.self::n($center).' '.self::n($center).' L'.self::n($x1).' '.self::n($y1)
                    .' A'.self::n($radius).' '.self::n($radius).' 0 '.($sweep > 180 ? 1 : 0).' 1 '.self::n($x2).' '.self::n($y2).' Z" fill="'.$color.'">'.$title.'</path>';
            }

            if ($sweep >= 18) {
                [$lx, $ly] = self::point($center, ($radius + $hole) / 2, $angle + $sweep / 2);
                $body .= self::text($lx, $ly + 3, number_format($value * 100 / $total, 1, ',', '.').'%', 8.5, '#FFFFFF', 'bold');
            }

            $angle += $sweep;
        }

        $body .= '<circle cx="'.self::n($center).'" cy="'.self::n($center).'" r="'.self::n($hole).'" fill="#FFFFFF"/>';

        return self::svg($size, $size, $body);
    }

    public static function color(int $index): string
    {
        return self::PALETTE[$index % count(self::PALETTE)];
    }

    /**
     * Para el PDF: DomPDF lee el SVG como imagen.
     */
    public static function dataUri(string $svg): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /**
     * @return array{0: float, 1: float}
     */
    private static function point(float $center, float $radius, float $degrees): array
    {
        $radians = deg2rad($degrees);

        return [$center + $radius * cos($radians), $center + $radius * sin($radians)];
    }

    private static function svg(int $width, int $height, string $body): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$width.'" height="'.$height.'" viewBox="0 0 '.$width.' '.$height.'" font-family="'.self::FONT.'">'.$body.'</svg>';
    }

    private static function text(float $x, float $y, string $content, float $size, string $color, string $weight = 'normal', string $anchor = 'middle'): string
    {
        return '<text x="'.self::n($x).'" y="'.self::n($y).'" font-size="'.$size.'" font-weight="'.$weight.'" fill="'.$color.'" text-anchor="'.$anchor.'">'.e($content).'</text>';
    }

    private static function short(string $label, int $max): string
    {
        return mb_strlen($label) > $max ? mb_substr($label, 0, $max - 1).'…' : $label;
    }

    private static function n(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
