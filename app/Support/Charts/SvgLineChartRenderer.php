<?php

declare(strict_types=1);

namespace App\Support\Charts;

final class SvgLineChartRenderer
{
    /**
     * Genera un PNG (data URI) de gráfico de línea a partir de etiquetas y valores mensuales.
     * DomPDF renderiza imágenes raster de forma más fiable que SVG interactivo.
     *
     * @param  list<string>  $labels
     * @param  list<int|float>  $values
     */
    public static function toPngDataUri(
        array $labels,
        array $values,
        string $title = 'Servicios FINALIZADO por mes',
        int $width = 920,
        int $height = 260,
    ): string {
        if (! function_exists('imagecreatetruecolor')) {
            return self::toSvgDataUri($labels, $values, $title, $width, $height);
        }

        [$labels, $values] = self::normalizeSeries($labels, $values);
        $count = count($labels);

        $image = imagecreatetruecolor($width, $height);
        if ($image === false) {
            return self::toSvgDataUri($labels, $values, $title, $width, $height);
        }

        imageantialias($image, true);

        $white = imagecolorallocate($image, 255, 255, 255);
        $slate = imagecolorallocate($image, 71, 85, 105);
        $grid = imagecolorallocate($image, 226, 232, 240);
        $axis = imagecolorallocate($image, 148, 163, 184);
        $line = imagecolorallocate($image, 14, 165, 233);
        $fill = imagecolorallocatealpha($image, 14, 165, 233, 95);
        $point = imagecolorallocate($image, 3, 105, 161);
        $titleColor = imagecolorallocate($image, 12, 74, 110);

        imagefilledrectangle($image, 0, 0, $width, $height, $white);

        $paddingLeft = 48;
        $paddingRight = 24;
        $paddingTop = 36;
        $paddingBottom = 42;
        $plotWidth = max(1, $width - $paddingLeft - $paddingRight);
        $plotHeight = max(1, $height - $paddingTop - $paddingBottom);

        imagestring($image, 3, 12, 10, self::gdSafeText($title), $titleColor);

        $maxValue = max($values);
        $maxValue = $maxValue <= 0 ? 1.0 : $maxValue;
        $yTicks = 4;

        for ($i = 0; $i <= $yTicks; $i++) {
            $ratio = $i / $yTicks;
            $y = (int) round($paddingTop + ($plotHeight * $ratio));
            imageline($image, $paddingLeft, $y, $width - $paddingRight, $y, $grid);
            $tickValue = (int) round($maxValue * (1 - $ratio));
            imagestring($image, 2, 8, $y - 6, (string) $tickValue, $slate);
        }

        imageline($image, $paddingLeft, $paddingTop, $paddingLeft, $height - $paddingBottom, $axis);
        imageline($image, $paddingLeft, $height - $paddingBottom, $width - $paddingRight, $height - $paddingBottom, $axis);

        $points = [];
        for ($i = 0; $i < $count; $i++) {
            $xRatio = $count === 1 ? 0.5 : $i / ($count - 1);
            $x = (int) round($paddingLeft + ($plotWidth * $xRatio));
            $yRatio = $values[$i] / $maxValue;
            $y = (int) round($paddingTop + $plotHeight - ($plotHeight * $yRatio));
            $points[] = [$x, $y];
        }

        $polygon = [];
        $polygon[] = $paddingLeft;
        $polygon[] = $height - $paddingBottom;
        foreach ($points as [$x, $y]) {
            $polygon[] = $x;
            $polygon[] = $y;
        }
        $polygon[] = $points[$count - 1][0];
        $polygon[] = $height - $paddingBottom;
        imagefilledpolygon($image, $polygon, $fill);

        for ($i = 1; $i < $count; $i++) {
            imageline(
                $image,
                $points[$i - 1][0],
                $points[$i - 1][1],
                $points[$i][0],
                $points[$i][1],
                $line,
            );
        }

        foreach ($points as $index => [$x, $y]) {
            imagefilledellipse($image, $x, $y, 8, 8, $point);
            imagestring($image, 2, max(2, $x - 8), $height - $paddingBottom + 8, self::gdSafeText((string) $labels[$index]), $slate);
            if ($values[$index] > 0) {
                imagestring($image, 2, $x - 3, $y - 14, (string) (int) $values[$index], $point);
            }
        }

        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        return 'data:image/png;base64,'.base64_encode($png);
    }

    /**
     * @param  list<string>  $labels
     * @param  list<int|float>  $values
     */
    public static function toSvgDataUri(
        array $labels,
        array $values,
        string $title = 'Servicios FINALIZADO por mes',
        int $width = 920,
        int $height = 260,
    ): string {
        return 'data:image/svg+xml;base64,'.base64_encode(self::toSvg($labels, $values, $title, $width, $height));
    }

    /**
     * @param  list<string>  $labels
     * @param  list<int|float>  $values
     */
    public static function toSvg(
        array $labels,
        array $values,
        string $title = 'Servicios FINALIZADO por mes',
        int $width = 920,
        int $height = 260,
    ): string {
        [$labels, $values] = self::normalizeSeries($labels, $values);
        $count = count($labels);

        $paddingLeft = 48;
        $paddingRight = 24;
        $paddingTop = 36;
        $paddingBottom = 42;
        $plotWidth = max(1, $width - $paddingLeft - $paddingRight);
        $plotHeight = max(1, $height - $paddingTop - $paddingBottom);
        $maxValue = max($values);
        $maxValue = $maxValue <= 0 ? 1.0 : $maxValue;
        $axisY = $paddingTop + $plotHeight;
        $axisXEnd = $width - $paddingRight;

        $points = [];
        for ($i = 0; $i < $count; $i++) {
            $xRatio = $count === 1 ? 0.5 : $i / ($count - 1);
            $x = $paddingLeft + ($plotWidth * $xRatio);
            $yRatio = $values[$i] / $maxValue;
            $y = $paddingTop + $plotHeight - ($plotHeight * $yRatio);
            $points[] = [round($x, 2), round($y, 2)];
        }

        $polyline = implode(' ', array_map(
            static fn (array $point): string => $point[0].','.$point[1],
            $points,
        ));
        $areaPoints = $paddingLeft.','.$axisY.' '.$polyline.' '.$points[$count - 1][0].','.$axisY;

        $labelNodes = '';
        $valueNodes = '';
        $dotNodes = '';
        foreach ($points as $index => [$x, $y]) {
            $label = htmlspecialchars((string) $labels[$index], ENT_QUOTES | ENT_XML1);
            $labelNodes .= '<text x="'.$x.'" y="'.($height - 14).'" text-anchor="middle" font-size="11" fill="#475569">'.$label.'</text>';
            $dotNodes .= '<circle cx="'.$x.'" cy="'.$y.'" r="4" fill="#0369a1" />';
            if ($values[$index] > 0) {
                $valueNodes .= '<text x="'.$x.'" y="'.($y - 10).'" text-anchor="middle" font-size="10" fill="#0369a1">'.(int) $values[$index].'</text>';
            }
        }

        $grid = '';
        for ($i = 0; $i <= 4; $i++) {
            $ratio = $i / 4;
            $y = $paddingTop + ($plotHeight * $ratio);
            $tick = (int) round($maxValue * (1 - $ratio));
            $grid .= '<line x1="'.$paddingLeft.'" y1="'.$y.'" x2="'.$axisXEnd.'" y2="'.$y.'" stroke="#e2e8f0" stroke-width="1" />';
            $grid .= '<text x="8" y="'.($y + 4).'" font-size="10" fill="#475569">'.$tick.'</text>';
        }

        $safeTitle = htmlspecialchars($title, ENT_QUOTES | ENT_XML1);

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$width}" height="{$height}" viewBox="0 0 {$width} {$height}">
  <rect width="100%" height="100%" fill="#ffffff"/>
  <text x="12" y="20" font-size="13" font-family="DejaVu Sans, sans-serif" fill="#0c4a6e" font-weight="bold">{$safeTitle}</text>
  {$grid}
  <line x1="{$paddingLeft}" y1="{$paddingTop}" x2="{$paddingLeft}" y2="{$axisY}" stroke="#94a3b8" stroke-width="1.5"/>
  <line x1="{$paddingLeft}" y1="{$axisY}" x2="{$axisXEnd}" y2="{$axisY}" stroke="#94a3b8" stroke-width="1.5"/>
  <polygon points="{$areaPoints}" fill="#0ea5e92e"/>
  <polyline points="{$polyline}" fill="none" stroke="#0ea5e9" stroke-width="3" stroke-linejoin="round" stroke-linecap="round"/>
  {$dotNodes}
  {$valueNodes}
  {$labelNodes}
</svg>
SVG;
    }

    /**
     * @param  list<string>  $labels
     * @param  list<int|float>  $values
     * @return array{0: list<string>, 1: list<float>}
     */
    private static function normalizeSeries(array $labels, array $values): array
    {
        $count = min(count($labels), count($values));
        $labels = array_map(
            static fn (mixed $label): string => (string) $label,
            array_slice(array_values($labels), 0, $count),
        );
        $values = array_map(
            static fn (mixed $value): float => (float) $value,
            array_slice(array_values($values), 0, $count),
        );

        if ($count === 0) {
            return [['—'], [0.0]];
        }

        return [$labels, $values];
    }

    private static function gdSafeText(string $text): string
    {
        $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $text);

        return is_string($converted) && $converted !== ''
            ? $converted
            : (preg_replace('/[^\x20-\x7E]/', '', $text) ?? $text);
    }
}
