<?php

declare(strict_types=1);

namespace App\Support\TravelAgencies;

use App\Models\TravelAgency;
use App\Models\TravelAgent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class TravelAgencyPublicRegistrar
{
    public const MAX_LEVEL = 5;

    public static function agentRegistrationUrl(TravelAgency $agency): string
    {
        $token = self::ensureToken($agency, 'registration_token');

        return route('travel-agents.register', ['token' => $token]);
    }

    public static function agencyRegistrationUrl(TravelAgency $agency): string
    {
        $token = self::ensureToken($agency, 'agency_registration_token');

        return route('travel-agencies.register', ['token' => $token]);
    }

    /**
     * Jerarquía que hereda una agencia registrada con el enlace de otra.
     * La principal se conserva (TDEV u otra) y el nombre del padre ocupa el peldaño que le corresponde.
     *
     * @return array{
     *     parent_id: int,
     *     nivel: string,
     *     agenciaPpalNivel1: string|null,
     *     agenciaSuperiorNivel2: string|null,
     *     agenteSuperiorNivel3: string|null,
     * }
     */
    public static function hierarchyFromParent(TravelAgency $parent): array
    {
        $parentLevel = self::numericLevel($parent->nivel);
        $childLevel = min(self::MAX_LEVEL, $parentLevel + 1);
        $parentName = self::upper($parent->name);

        $principal = self::upper($parent->agenciaPpalNivel1);
        $superiorAgency = self::upper($parent->agenciaSuperiorNivel2);
        $superiorAgent = self::upper($parent->agenteSuperiorNivel3);

        if ($principal === null) {
            $principal = 'TDEV';
        }

        if ($parentLevel === 2) {
            $superiorAgency = $parentName ?? $superiorAgency;
        } elseif ($parentLevel >= 3) {
            $superiorAgency = $superiorAgency ?? $parentName;
            $superiorAgent = $superiorAgent ?? $parentName;
        }

        return [
            'parent_id' => (int) $parent->getKey(),
            'nivel' => (string) $childLevel,
            'agenciaPpalNivel1' => $principal,
            'agenciaSuperiorNivel2' => $superiorAgency,
            'agenteSuperiorNivel3' => $superiorAgent,
        ];
    }

    /**
     * @param  array{
     *     name: string,
     *     numberIdentification?: string|null,
     *     email?: string|null,
     *     representante?: string|null,
     *     phone?: string|null,
     *     phoneAdditional?: string|null,
     *     address?: string|null,
     *     userInstagram?: string|null,
     * }  $data
     */
    public static function registerAgency(TravelAgency $parent, array $data): TravelAgency
    {
        $name = self::upper($data['name']);

        if ($name === null) {
            throw new \InvalidArgumentException('El nombre de la agencia es obligatorio.');
        }

        return DB::transaction(function () use ($parent, $data, $name): TravelAgency {
            $hierarchy = self::hierarchyFromParent($parent);

            return TravelAgency::query()->create([
                ...$hierarchy,
                'name' => $name,
                'numberIdentification' => self::plain($data['numberIdentification'] ?? null),
                'email' => self::email($data['email'] ?? null),
                'representante' => self::upper($data['representante'] ?? null),
                'phone' => self::plain($data['phone'] ?? null),
                'phoneAdditional' => self::plain($data['phoneAdditional'] ?? null),
                'address' => self::plain($data['address'] ?? null),
                'userInstagram' => self::instagram($data['userInstagram'] ?? null),
                'classification' => 'AGENCIA DE VIAJES',
                'status' => 'Activo',
                'fechaIngreso' => now()->format('d/m/Y'),
                'created_by' => 'Formulario público',
            ]);
        });
    }

    /**
     * El agente queda en la agencia del enlace. Los campos de jerarquía de esa agencia no se modifican.
     *
     * @param  array{
     *     name: string,
     *     cargo?: string|null,
     *     email?: string|null,
     *     phone?: string|null,
     *     fechaNacimiento?: string|null,
     * }  $data
     */
    public static function registerAgent(TravelAgency $agency, array $data): TravelAgent
    {
        $name = self::upper($data['name']);

        if ($name === null) {
            throw new \InvalidArgumentException('El nombre del agente es obligatorio.');
        }

        return DB::transaction(function () use ($agency, $data, $name): TravelAgent {
            $birthDate = self::plain($data['fechaNacimiento'] ?? null);

            return $agency->travelAgents()->create([
                'name' => $name,
                'cargo' => self::upper($data['cargo'] ?? null),
                'email' => self::email($data['email'] ?? null),
                'phone' => self::plain($data['phone'] ?? null),
                'fechaNacimiento' => $birthDate,
                'created_by' => 'Formulario público',
            ]);
        });
    }

    public static function numericLevel(mixed $nivel): int
    {
        if (preg_match('/(\d+)/', trim((string) $nivel), $matches) !== 1) {
            return 2;
        }

        return max(1, min(self::MAX_LEVEL, (int) $matches[1]));
    }

    private static function ensureToken(TravelAgency $agency, string $column): string
    {
        $token = $agency->getAttribute($column);

        if (filled($token)) {
            return (string) $token;
        }

        $token = (string) Str::uuid();
        $agency->update([$column => $token]);

        return $token;
    }

    private static function upper(mixed $value): ?string
    {
        $value = self::plain($value);

        return $value === null ? null : mb_strtoupper($value);
    }

    private static function email(mixed $value): ?string
    {
        $value = self::plain($value);

        return $value === null ? null : mb_strtolower($value);
    }

    private static function instagram(mixed $value): ?string
    {
        $value = self::plain($value);

        if ($value === null) {
            return null;
        }

        return ltrim($value, '@');
    }

    private static function plain(mixed $value): ?string
    {
        $value = trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');

        return $value === '' ? null : $value;
    }
}
