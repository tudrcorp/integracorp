<?php

declare(strict_types=1);

namespace App\Support\Filament;

final class BusinessFilamentActionPermissionRegistry
{
    public const CREATE_CORPORATE_AFFILIATE = 'crear-afiliado-corporativo';

    /**
     * @return array<string, array{name: string, group: string}>
     */
    public static function all(): array
    {
        return [
            self::CREATE_CORPORATE_AFFILIATE => [
                'name' => 'Crear afiliado corporativo',
                'group' => 'AFILIACIONES',
            ],
        ];
    }

    public static function navigationGroupForSlug(string $slug): ?string
    {
        return self::all()[$slug]['group'] ?? null;
    }
}
