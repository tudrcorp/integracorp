<?php

declare(strict_types=1);

namespace App\Support\Filament;

final class BusinessFilamentActionPermissionRegistry
{
    public const CREATE_CORPORATE_AFFILIATE = 'crear-afiliado-corporativo';

    public const MANAGE_WHITE_COMPANY_NEGOTIATED_FEES = 'matriz-negociacion-empresas-aliadas';

    public const MANAGE_WHITE_COMPANY_DOCUMENT_BRAND = 'documentos-marca-empresas-aliadas';

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
            self::MANAGE_WHITE_COMPANY_NEGOTIATED_FEES => [
                'name' => 'Matriz de negociación',
                'group' => 'ESTRUCTURA COMERCIAL',
            ],
            self::MANAGE_WHITE_COMPANY_DOCUMENT_BRAND => [
                'name' => 'Documentos de marca',
                'group' => 'ESTRUCTURA COMERCIAL',
            ],
        ];
    }

    public static function navigationGroupForSlug(string $slug): ?string
    {
        return self::all()[$slug]['group'] ?? null;
    }
}
