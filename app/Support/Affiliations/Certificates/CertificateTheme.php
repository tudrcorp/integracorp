<?php

declare(strict_types=1);

namespace App\Support\Affiliations\Certificates;

use Illuminate\Support\Str;

/**
 * Colores del certificado y del carnet según el plan, tomados del diseño
 * «Voucher Tu Dr en Casa»: celeste para Inicial, Ideal y Especial; oscuro para
 * Nivel 4; gris para el resto (corporativos, a la medida, escolar).
 *
 * El logo va como archivo según el fondo, no con filtros CSS: DomPDF no los aplica.
 */
final class CertificateTheme
{
    public const CELESTE = 'celeste';

    public const GRIS = 'gris';

    public const OSCURO = 'oscuro';

    /**
     * @var array<string, array{bg: string, ink: string, sub: string, title: string, value: string, divider: string, wave: string, pillBg: string, pillInk: string, logo: string}>
     */
    private const THEMES = [
        self::CELESTE => ['bg' => '#26B4E8', 'ink' => '#0B3D52', 'sub' => '#0B3D52', 'title' => '#FFFFFF', 'value' => '#FFFFFF', 'divider' => '#7FD0EF', 'wave' => '#45BFEB', 'pillBg' => '#FFFFFF', 'pillInk' => '#0B3D52', 'logo' => 'logo-tu-doctor-en-casa-blanco.png'],
        self::GRIS => ['bg' => '#EFEFEF', 'ink' => '#12293A', 'sub' => '#35657D', 'title' => '#12293A', 'value' => '#2D89CA', 'divider' => '#D9DEE2', 'wave' => '#E4E6E8', 'pillBg' => '#26B4E8', 'pillInk' => '#0B3D52', 'logo' => 'logo-tu-doctor-en-casa-oscuro.png'],
        self::OSCURO => ['bg' => '#12293A', 'ink' => '#FFFFFF', 'sub' => '#AEC2CB', 'title' => '#FFFFFF', 'value' => '#26B4E8', 'divider' => '#35657D', 'wave' => '#163448', 'pillBg' => '#26B4E8', 'pillInk' => '#0B3D52', 'logo' => 'logo-tu-doctor-en-casa-blanco.png'],
    ];

    public static function keyForPlan(?string $planName): string
    {
        $name = Str::upper(Str::ascii((string) $planName));

        return match (true) {
            str_contains($name, 'NIVEL 4') => self::OSCURO,
            str_contains($name, 'INICIAL'), str_contains($name, 'IDEAL'), str_contains($name, 'ESPECIAL') => self::CELESTE,
            default => self::GRIS,
        };
    }

    /**
     * @return array{bg: string, ink: string, sub: string, title: string, value: string, divider: string, wave: string, pillBg: string, pillInk: string, logo: string}
     */
    public static function forPlan(?string $planName): array
    {
        return self::THEMES[self::keyForPlan($planName)];
    }
}
