<?php

declare(strict_types=1);

namespace App\Support\Filament;

final class AffiliateStatusHeaderBadge
{
    public static function html(?string $status): string
    {
        $normalized = strtoupper(trim((string) $status));
        $label = $normalized !== '' ? $normalized : 'Sin estado';
        $style = self::styleFor($normalized);

        return '<span style="'
            .'background-color: '.$style['bg'].'; '
            .'color: #ffffff; '
            .'padding: 6px 16px; '
            .'border-radius: 50px; '
            .'font-size: 0.8rem; '
            .'font-weight: 700; '
            .'display: inline-flex; '
            .'align-items: center; '
            .'gap: 6px; '
            .'box-shadow: '.$style['shadow'].'; '
            .'border: 1px solid rgba(255, 255, 255, 0.2);'
            .'">'
            .'<span style="font-size: 10px;">●</span> '.e($label)
            .'</span>';
    }

    /**
     * @return array{bg: string, shadow: string}
     */
    private static function styleFor(string $status): array
    {
        return match ($status) {
            'ACTIVO', 'ACTIVA' => [
                'bg' => '#28cd41',
                'shadow' => '0 4px 12px rgba(40, 205, 65, 0.35)',
            ],
            'PENDIENTE' => [
                'bg' => '#ff9f0a',
                'shadow' => '0 4px 12px rgba(255, 159, 10, 0.35)',
            ],
            'INACTIVO', 'EXCLUIDO', 'EXCLUIDA' => [
                'bg' => '#ff3b30',
                'shadow' => '0 4px 12px rgba(255, 59, 48, 0.35)',
            ],
            default => [
                'bg' => '#8e8e93',
                'shadow' => '0 4px 12px rgba(142, 142, 147, 0.35)',
            ],
        };
    }
}
