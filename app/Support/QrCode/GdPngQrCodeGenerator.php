<?php

declare(strict_types=1);

namespace App\Support\QrCode;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use GdImage;
use RuntimeException;

final class GdPngQrCodeGenerator
{
    public static function generate(
        string $content,
        int $size = 300,
        string $errorCorrection = 'M',
        int $margin = 0,
    ): string {
        if (extension_loaded('gd')) {
            return self::generateWithGd($content, $size, $errorCorrection, $margin);
        }

        if (extension_loaded('imagick')) {
            return self::generateWithImagick($content, $size, $errorCorrection, $margin);
        }

        throw new RuntimeException('Se requiere la extensión GD o Imagick para generar códigos QR en formato PNG.');
    }

    private static function resolveErrorCorrectionLevel(string $errorCorrection): ErrorCorrectionLevel
    {
        return match (strtoupper($errorCorrection)) {
            'L' => ErrorCorrectionLevel::L(),
            'M' => ErrorCorrectionLevel::M(),
            'Q' => ErrorCorrectionLevel::Q(),
            'H' => ErrorCorrectionLevel::H(),
            default => throw new RuntimeException("Nivel de corrección QR inválido: {$errorCorrection}"),
        };
    }

    private static function generateWithImagick(
        string $content,
        int $size,
        string $errorCorrection,
        int $margin,
    ): string {
        $writer = new Writer(new ImageRenderer(
            new RendererStyle($size, $margin),
            new ImagickImageBackEnd('png'),
        ));

        return $writer->writeString(
            $content,
            Encoder::DEFAULT_BYTE_MODE_ECODING,
            self::resolveErrorCorrectionLevel($errorCorrection),
        );
    }

    private static function generateWithGd(
        string $content,
        int $size,
        string $errorCorrection,
        int $margin,
    ): string {
        $qrCode = Encoder::encode(
            $content,
            self::resolveErrorCorrectionLevel($errorCorrection),
            'UTF-8',
        );

        $matrix = $qrCode->getMatrix();
        $matrixSize = $matrix->getWidth();
        $totalSize = $matrixSize + ($margin * 2);
        $moduleSize = $size / $totalSize;

        $image = imagecreatetruecolor($size, $size);

        if (! $image instanceof GdImage) {
            throw new RuntimeException('No se pudo crear la imagen del código QR.');
        }

        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);

        imagefilledrectangle($image, 0, 0, $size - 1, $size - 1, $white);

        $offset = (int) round($margin * $moduleSize);

        for ($y = 0; $y < $matrixSize; $y++) {
            for ($x = 0; $x < $matrixSize; $x++) {
                if ($matrix->get($x, $y) !== 1) {
                    continue;
                }

                $left = $offset + (int) round($x * $moduleSize);
                $top = $offset + (int) round($y * $moduleSize);
                $right = $offset + (int) round(($x + 1) * $moduleSize) - 1;
                $bottom = $offset + (int) round(($y + 1) * $moduleSize) - 1;

                imagefilledrectangle($image, $left, $top, $right, $bottom, $black);
            }
        }

        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        if (! is_string($png) || $png === '') {
            throw new RuntimeException('No se pudo codificar el código QR en PNG.');
        }

        return $png;
    }
}
