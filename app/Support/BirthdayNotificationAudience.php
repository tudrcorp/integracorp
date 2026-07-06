<?php

declare(strict_types=1);

namespace App\Support;

class BirthdayNotificationAudience
{
    public const AFFILIATES = 'afiliados';

    public const COLLABORATORS = 'colaboradores';

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return [
            self::AFFILIATES,
            self::COLLABORATORS,
        ];
    }

    /**
     * @return list<string>
     */
    public static function dataTypesFor(string $audience): array
    {
        return match ($audience) {
            self::COLLABORATORS => [
                'rrhh_colaboradors',
                'users',
                'suppliers',
                'capemiacs',
            ],
            default => [
                'affiliates',
                'affiliate_corporates',
                'agents',
                'affiliations',
            ],
        };
    }

    public static function forDataType(?string $dataType): string
    {
        if ($dataType !== null && in_array($dataType, self::dataTypesFor(self::COLLABORATORS), true)) {
            return self::COLLABORATORS;
        }

        return self::AFFILIATES;
    }

    /**
     * @return array<string, string>
     */
    public static function recipientOptionsFor(?string $audience): array
    {
        $allOptions = self::recipientOptions();

        if ($audience === null || ! in_array($audience, self::keys(), true)) {
            return $allOptions;
        }

        return array_intersect_key(
            $allOptions,
            array_flip(self::dataTypesFor($audience)),
        );
    }

    /**
     * @return array<string, string>
     */
    public static function recipientOptions(): array
    {
        return [
            'rrhh_colaboradors' => 'COLABORADORES/EMPLEADOS',
            'suppliers' => 'PROVEEDORES',
            'affiliates' => 'AFILIADOS INDIVIDUALES',
            'affiliate_corporates' => 'AFILIADOS CORPORATIVOS',
            'users' => 'USUARIOS',
            'capemiacs' => 'CAPEMIAC',
            'agents' => 'AGENTES',
            'affiliations' => 'AFILIADOS/CLIENTES',
        ];
    }

    public static function labelForDataType(?string $dataType): ?string
    {
        if ($dataType === null) {
            return null;
        }

        return self::recipientOptions()[$dataType] ?? null;
    }

    public static function listHeadingFor(string $audience): string
    {
        return match ($audience) {
            self::COLLABORATORS => 'Listado de Notificaciones de Cumpleaños — Colaboradores',
            default => 'Listado de Notificaciones de Cumpleaños — Afiliados',
        };
    }

    public static function listDescriptionFor(string $audience): string
    {
        return match ($audience) {
            self::COLLABORATORS => 'Notificaciones de cumpleaños para colaboradores, proveedores y grupos internos.',
            default => 'Notificaciones de cumpleaños para afiliados individuales, corporativos y agentes.',
        };
    }
}
