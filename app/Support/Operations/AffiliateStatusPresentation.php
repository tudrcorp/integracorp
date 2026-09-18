<?php

declare(strict_types=1);

namespace App\Support\Operations;

/**
 * Fuente única de verdad para presentar el estatus de un afiliado (individual o
 * corporativo) en el panel de Operaciones: el badge del encabezado de la ficha y
 * el badge «Estatus afiliado» del infolist deben mostrar siempre lo mismo.
 */
final class AffiliateStatusPresentation
{
    public const FALLBACK_LABEL = 'SIN ESTATUS';

    /**
     * Etiqueta normalizada del estatus tal como se muestra al usuario.
     */
    public static function label(?string $status): string
    {
        $normalized = self::normalize($status);

        return $normalized !== '' ? $normalized : self::FALLBACK_LABEL;
    }

    /**
     * Color Filament del badge (infolist, tablas, infolists hermanos).
     */
    public static function filamentColor(?string $status): string
    {
        return match (self::normalize($status)) {
            'ACTIVO', 'ACTIVA' => 'success',
            'PENDIENTE' => 'warning',
            'EXCLUIDO', 'INACTIVO' => 'danger',
            default => 'gray',
        };
    }

    /**
     * Estilo iOS del badge del encabezado, equivalente al color Filament.
     *
     * @return array{bg: string, shadow: string}
     */
    public static function headerBadgeStyle(?string $status): array
    {
        return match (self::filamentColor($status)) {
            'success' => [
                'bg' => '#28cd41',
                'shadow' => '0 4px 12px rgba(40, 205, 65, 0.35)',
            ],
            'warning' => [
                'bg' => '#ffcc00',
                'shadow' => '0 4px 12px rgba(255, 204, 0, 0.35)',
            ],
            'danger' => [
                'bg' => '#ff3b30',
                'shadow' => '0 4px 12px rgba(255, 59, 48, 0.35)',
            ],
            default => [
                'bg' => '#8e8e93',
                'shadow' => '0 4px 12px rgba(142, 142, 147, 0.35)',
            ],
        };
    }

    private static function normalize(?string $status): string
    {
        return strtoupper(trim((string) $status));
    }
}
