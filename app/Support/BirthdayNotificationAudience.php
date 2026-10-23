<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

class BirthdayNotificationAudience
{
    public const AFFILIATES = 'afiliados';

    public const COLLABORATORS = 'colaboradores';

    /**
     * Destinatarios de la tarjeta «CUMPLEAÑOS ANIMADO».
     *
     * @return list<string>
     */
    public static function celebrationRecipientDataTypeKeys(): array
    {
        return [
            'rrhh_colaboradors',
            'agents',
            'affiliates',
            'affiliate_corporates',
            'tdev_clients',
            'travel_agents',
        ];
    }

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
                'rrhh_colaboradors',
                'travel_agents',
                'tdev_clients',
            ],
        };
    }

    /**
     * Normaliza data_type (string legado o lista) a una lista de slugs únicos.
     *
     * @return list<string>
     */
    public static function normalizeDataTypes(mixed $dataType): array
    {
        if ($dataType === null || $dataType === '') {
            return [];
        }

        if (is_string($dataType)) {
            $trimmed = trim($dataType);

            if ($trimmed === '') {
                return [];
            }

            $decoded = json_decode($trimmed, true);

            if (is_array($decoded)) {
                return self::normalizeDataTypes($decoded);
            }

            return [$trimmed];
        }

        if (! is_array($dataType)) {
            return [];
        }

        $types = [];

        foreach ($dataType as $value) {
            if (! is_string($value)) {
                continue;
            }

            $trimmed = trim($value);

            if ($trimmed === '' || in_array($trimmed, $types, true)) {
                continue;
            }

            $types[] = $trimmed;
        }

        return $types;
    }

    public static function forDataType(mixed $dataType): string
    {
        $types = self::normalizeDataTypes($dataType);

        if ($types === []) {
            return self::AFFILIATES;
        }

        $hasAffiliateExclusive = collect($types)->contains(
            fn (string $type): bool => in_array($type, [
                'affiliates',
                'affiliate_corporates',
                'agents',
                'affiliations',
                'travel_agents',
                'tdev_clients',
            ], true)
        );

        if ($hasAffiliateExclusive) {
            return self::AFFILIATES;
        }

        $hasCollaboratorGroup = collect($types)->contains(
            fn (string $type): bool => in_array($type, self::dataTypesFor(self::COLLABORATORS), true)
        );

        return $hasCollaboratorGroup ? self::COLLABORATORS : self::AFFILIATES;
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
     * @return list<string>
     */
    public static function allRecipientDataTypeKeys(): array
    {
        return array_keys(self::recipientOptions());
    }

    /**
     * @return array<string, string>
     */
    public static function recipientOptions(): array
    {
        return [
            'rrhh_colaboradors' => 'COLABORADORES TDG',
            'agents' => 'AGENTES DE CORRETAJE (TDEC/TDEV)',
            'affiliates' => 'AFILIADOS INDIVIDUALES TDEC',
            'affiliate_corporates' => 'AFILIADOS CORPORATIVOS TDEC',
            'tdev_clients' => 'CLIENTES TU DR. EN VIAJES (TDEV)',
            'travel_agents' => 'AGENTES DE VIAJES (TDEV)',
            'suppliers' => 'PROVEEDORES',
            'users' => 'USUARIOS',
            'capemiacs' => 'CAPEMIAC',
            'affiliations' => 'AFILIADOS/CLIENTES',
        ];
    }

    public static function labelForDataType(?string $dataType): ?string
    {
        if ($dataType === null || $dataType === '') {
            return null;
        }

        return self::recipientOptions()[$dataType] ?? null;
    }

    /**
     * @param  list<string>|string|null  $dataTypes
     * @return list<string>
     */
    public static function labelsForDataTypes(mixed $dataTypes): array
    {
        $labels = [];

        foreach (self::normalizeDataTypes($dataTypes) as $type) {
            $labels[] = self::labelForDataType($type) ?? $type;
        }

        return $labels;
    }

    public static function applyAudienceConstraint(Builder $query, string $audience): Builder
    {
        $types = self::dataTypesFor($audience);

        return $query->where(function (Builder $nested) use ($types): void {
            foreach ($types as $type) {
                $nested->orWhereJsonContains('data_type', $type);
            }
        });
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
            default => 'Notificaciones de cumpleaños para afiliados individuales, corporativos, agentes y colaboradores.',
        };
    }
}
