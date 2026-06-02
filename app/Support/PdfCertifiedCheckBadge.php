<?php

declare(strict_types=1);

namespace App\Support;

final class PdfCertifiedCheckBadge
{
    private const SIZE = 32;

    private static ?string $cachedDataUri = null;

    public static function dataUri(): string
    {
        if (self::$cachedDataUri !== null) {
            return self::$cachedDataUri;
        }

        if (! extension_loaded('gd')) {
            return self::$cachedDataUri = '';
        }

        $image = imagecreatetruecolor(self::SIZE, self::SIZE);

        if ($image === false) {
            return self::$cachedDataUri = '';
        }

        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 255, 255, 255, 127);
        imagefill($image, 0, 0, $transparent);

        $green = imagecolorallocate($image, 19, 192, 35);
        $white = imagecolorallocate($image, 255, 255, 255);
        $center = (int) (self::SIZE / 2);
        $diameter = self::SIZE - 1;

        imagefilledellipse($image, $center, $center, $diameter, $diameter, $green);

        self::drawRoundedStroke($image, $white, 7, 17, 13, 23, 4);
        self::drawRoundedStroke($image, $white, 13, 23, 24, 11, 4);

        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        if (! is_string($png) || $png === '') {
            return self::$cachedDataUri = '';
        }

        return self::$cachedDataUri = 'data:image/png;base64,'.base64_encode($png);
    }

    /**
     * Dibuja una línea de grosor uniforme con extremos redondeados.
     *
     * @param  \GdImage|resource  $image
     */
    private static function drawRoundedStroke($image, int $color, int $x1, int $y1, int $x2, int $y2, int $thickness): void
    {
        imagesetthickness($image, $thickness);
        imageline($image, $x1, $y1, $x2, $y2, $color);
        imagefilledellipse($image, $x1, $y1, $thickness, $thickness, $color);
        imagefilledellipse($image, $x2, $y2, $thickness, $thickness, $color);
    }
}
