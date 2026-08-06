<?php

declare(strict_types=1);

namespace App\Support\Charts;

/**
 * Gráfico de línea con dos series (p. ej. individual vs corporativo) para incrustar en PDF vía DomPDF.
 */
final class SvgDualLineChartRenderer
{
    /**
     * @param  list<string>  $labels
     * @param  list<int|float>  $seriesA
     * @param  list<int|float>  $seriesB
     */
    public static function toPngDataUri(
        array $labels,
        array $seriesA,
        array $seriesB,
        string $title = 'Ventas individual vs corporativo',
        string $labelA = 'Individual',
        string $labelB = 'Corporativo',
        int $width = 920,
        int $height = 280,
    ): string {
        if (! function_exists('imagecreatetruecolor')) {
            return self::toSvgDataUri($labels, $seriesA, $seriesB, $title, $labelA, $labelB, $width, $height);
        }

        [$labels, $seriesA, $seriesB] = self::normalizeSeries($labels, $seriesA, $seriesB);
        $count = count($labels);

        $image = imagecreatetruecolor($width, $height);
        if ($image === false) {
            return self::toSvgDataUri($labels, $seriesA, $seriesB, $title, $labelA, $labelB, $width, $height);
        }

        imageantialias($image, true);

        $white = imagecolorallocate($image, 255, 255, 255);
        $slate = imagecolorallocate($image, 71, 85, 105);
        $grid = imagecolorallocate($image, 226, 232, 240);
        $axis = imagecolorallocate($image, 148, 163, 184);
        $lineA = imagecolorallocate($image, 14, 165, 233);
        $pointA = imagecolorallocate($image, 3, 105, 161);
        $lineB = imagecolorallocate($image, 245, 158, 11);
        $pointB = imagecolorallocate($image, 180, 83, 9);
        $titleColor = imagecolorallocate($image, 12, 74, 110);

        imagefilledrectangle($image, 0, 0, $width, $height, $white);

        $paddingLeft = 56;
        $paddingRight = 24;
        $paddingTop = 40;
        $paddingBottom = 52;
        $plotWidth = max(1, $width - $paddingLeft - $paddingRight);
        $plotHeight = max(1, $height - $paddingTop - $paddingBottom);

        imagestring($image, 3, 12, 10, self::gdSafeText($title), $titleColor);
        imagestring($image, 2, $width - 220, 12, self::gdSafeText($labelA), $lineA);
        imagestring($image, 2, $width - 110, 12, self::gdSafeText($labelB), $lineB);

        $maxValue = max(max($seriesA), max($seriesB), 1.0);
        $yTicks = 4;

        for ($i = 0; $i <= $yTicks; $i++) {
            $ratio = $i / $yTicks;
            $y = (int) round($paddingTop + ($plotHeight * $ratio));
            imageline($image, $paddingLeft, $y, $width - $paddingRight, $y, $grid);
            $tickValue = (int) round($maxValue * (1 - $ratio));
            imagestring($image, 2, 6, $y - 6, (string) $tickValue, $slate);
        }

        imageline($image, $paddingLeft, $paddingTop, $paddingLeft, $height - $paddingBottom, $axis);
        imageline($image, $paddingLeft, $height - $paddingBottom, $width - $paddingRight, $height - $paddingBottom, $axis);

        $pointsA = self::buildPoints($seriesA, $count, $paddingLeft, $paddingTop, $plotWidth, $plotHeight, $maxValue);
        $pointsB = self::buildPoints($seriesB, $count, $paddingLeft, $paddingTop, $plotWidth, $plotHeight, $maxValue);

        self::drawPolyline($image, $pointsA, $lineA);
        self::drawPolyline($image, $pointsB, $lineB);

        foreach ($pointsA as [$x, $y]) {
            imagefilledellipse($image, $x, $y, 7, 7, $pointA);
        }

        foreach ($pointsB as [$x, $y]) {
            imagefilledellipse($image, $x, $y, 7, 7, $pointB);
        }

        foreach ($pointsA as $index => [$x]) {
            imagestring($image, 2, max(2, $x - 8), $height - $paddingBottom + 10, self::gdSafeText((string) $labels[$index]), $slate);
        }

        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        return 'data:image/png;base64,'.base64_encode($png);
    }

    /**
     * @param  list<string>  $labels
     * @param  list<int|float>  $seriesA
     * @param  list<int|float>  $seriesB
     */
    public static function toSvgDataUri(
        array $labels,
        array $seriesA,
        array $seriesB,
        string $title = 'Ventas individual vs corporativo',
        string $labelA = 'Individual',
        string $labelB = 'Corporativo',
        int $width = 920,
        int $height = 280,
    ): string {
        return 'data:image/svg+xml;base64,'.base64_encode(
            self::toSvg($labels, $seriesA, $seriesB, $title, $labelA, $labelB, $width, $height)
        );
    }

    /**
     * @param  list<string>  $labels
     * @param  list<int|float>  $seriesA
     * @param  list<int|float>  $seriesB
     */
    public static function toSvg(
        array $labels,
        array $seriesA,
        array $seriesB,
        string $title = 'Ventas individual vs corporativo',
        string $labelA = 'Individual',
        string $labelB = 'Corporativo',
        int $width = 920,
        int $height = 280,
    ): string {
        [$labels, $seriesA, $seriesB] = self::normalizeSeries($labels, $seriesA, $seriesB);
        $count = count($labels);

        $paddingLeft = 56;
        $paddingRight = 24;
        $paddingTop = 40;
        $paddingBottom = 52;
        $plotWidth = max(1, $width - $paddingLeft - $paddingRight);
        $plotHeight = max(1, $height - $paddingTop - $paddingBottom);
        $maxValue = max(max($seriesA), max($seriesB), 1.0);
        $axisY = $paddingTop + $plotHeight;
        $axisXEnd = $width - $paddingRight;

        $pointsA = [];
        $pointsB = [];
        for ($i = 0; $i < $count; $i++) {
            $xRatio = $count === 1 ? 0.5 : $i / ($count - 1);
            $x = round($paddingLeft + ($plotWidth * $xRatio), 2);
            $pointsA[] = [$x, round($paddingTop + $plotHeight - ($plotHeight * ($seriesA[$i] / $maxValue)), 2)];
            $pointsB[] = [$x, round($paddingTop + $plotHeight - ($plotHeight * ($seriesB[$i] / $maxValue)), 2)];
        }

        $polylineA = implode(' ', array_map(static fn (array $p): string => $p[0].','.$p[1], $pointsA));
        $polylineB = implode(' ', array_map(static fn (array $p): string => $p[0].','.$p[1], $pointsB));

        $labelNodes = '';
        $dotNodes = '';
        foreach ($pointsA as $index => [$x, $y]) {
            $label = htmlspecialchars((string) $labels[$index], ENT_QUOTES | ENT_XML1);
            $labelNodes .= '<text x="'.$x.'" y="'.($height - 18).'" text-anchor="middle" font-size="11" fill="#475569">'.$label.'</text>';
            $dotNodes .= '<circle cx="'.$x.'" cy="'.$y.'" r="3.5" fill="#0369a1" />';
            $dotNodes .= '<circle cx="'.$pointsB[$index][0].'" cy="'.$pointsB[$index][1].'" r="3.5" fill="#b45309" />';
        }

        $grid = '';
        for ($i = 0; $i <= 4; $i++) {
            $ratio = $i / 4;
            $y = $paddingTop + ($plotHeight * $ratio);
            $tick = (int) round($maxValue * (1 - $ratio));
            $grid .= '<line x1="'.$paddingLeft.'" y1="'.$y.'" x2="'.$axisXEnd.'" y2="'.$y.'" stroke="#e2e8f0" stroke-width="1" />';
            $grid .= '<text x="6" y="'.($y + 4).'" font-size="10" fill="#475569">'.$tick.'</text>';
        }

        $safeTitle = htmlspecialchars($title, ENT_QUOTES | ENT_XML1);
        $safeA = htmlspecialchars($labelA, ENT_QUOTES | ENT_XML1);
        $safeB = htmlspecialchars($labelB, ENT_QUOTES | ENT_XML1);
        $legendX = $width - 220;
        $legendBX = $legendX + 100;

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$width}" height="{$height}" viewBox="0 0 {$width} {$height}">
  <rect width="100%" height="100%" fill="#ffffff"/>
  <text x="12" y="20" font-size="13" font-family="DejaVu Sans, sans-serif" fill="#0c4a6e" font-weight="bold">{$safeTitle}</text>
  <text x="{$legendX}" y="20" font-size="11" fill="#0ea5e9">{$safeA}</text>
  <text x="{$legendBX}" y="20" font-size="11" fill="#f59e0b">{$safeB}</text>
  {$grid}
  <line x1="{$paddingLeft}" y1="{$paddingTop}" x2="{$paddingLeft}" y2="{$axisY}" stroke="#94a3b8" stroke-width="1.5"/>
  <line x1="{$paddingLeft}" y1="{$axisY}" x2="{$axisXEnd}" y2="{$axisY}" stroke="#94a3b8" stroke-width="1.5"/>
  <polyline points="{$polylineA}" fill="none" stroke="#0ea5e9" stroke-width="3" stroke-linejoin="round" stroke-linecap="round"/>
  <polyline points="{$polylineB}" fill="none" stroke="#f59e0b" stroke-width="3" stroke-linejoin="round" stroke-linecap="round"/>
  {$dotNodes}
  {$labelNodes}
</svg>
SVG;
    }

    /**
     * @param  list<float>  $values
     * @return list<array{0: int, 1: int}>
     */
    private static function buildPoints(
        array $values,
        int $count,
        int $paddingLeft,
        int $paddingTop,
        int $plotWidth,
        int $plotHeight,
        float $maxValue,
    ): array {
        $points = [];
        for ($i = 0; $i < $count; $i++) {
            $xRatio = $count === 1 ? 0.5 : $i / ($count - 1);
            $x = (int) round($paddingLeft + ($plotWidth * $xRatio));
            $y = (int) round($paddingTop + $plotHeight - ($plotHeight * ($values[$i] / $maxValue)));
            $points[] = [$x, $y];
        }

        return $points;
    }

    /**
     * @param  \GdImage  $image
     * @param  list<array{0: int, 1: int}>  $points
     */
    private static function drawPolyline(mixed $image, array $points, int $color): void
    {
        for ($i = 1; $i < count($points); $i++) {
            imageline(
                $image,
                $points[$i - 1][0],
                $points[$i - 1][1],
                $points[$i][0],
                $points[$i][1],
                $color,
            );
        }
    }

    /**
     * @param  list<string>  $labels
     * @param  list<int|float>  $seriesA
     * @param  list<int|float>  $seriesB
     * @return array{0: list<string>, 1: list<float>, 2: list<float>}
     */
    private static function normalizeSeries(array $labels, array $seriesA, array $seriesB): array
    {
        $count = min(count($labels), count($seriesA), count($seriesB));
        $labels = array_map(
            static fn (mixed $label): string => (string) $label,
            array_slice(array_values($labels), 0, $count),
        );
        $seriesA = array_map(
            static fn (mixed $value): float => (float) $value,
            array_slice(array_values($seriesA), 0, $count),
        );
        $seriesB = array_map(
            static fn (mixed $value): float => (float) $value,
            array_slice(array_values($seriesB), 0, $count),
        );

        if ($count === 0) {
            return [['—'], [0.0], [0.0]];
        }

        return [$labels, $seriesA, $seriesB];
    }

    private static function gdSafeText(string $text): string
    {
        $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $text);

        return is_string($converted) && $converted !== ''
            ? $converted
            : (preg_replace('/[^\x20-\x7E]/', '', $text) ?? $text);
    }
}
