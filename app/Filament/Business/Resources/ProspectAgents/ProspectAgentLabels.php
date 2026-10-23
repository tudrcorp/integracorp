<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\ProspectAgents;

final class ProspectAgentLabels
{
    public const STATUS_CAPTACION_PROSPECCION = 'captación-prospección';

    public const STATUS_CONTACTO_INICIAL = 'contacto-inicial';

    public const STATUS_EN_PROCESO_NEGOCIACION = 'en-proceso-negociación';

    public const STATUS_ALIADO_ACTIVO = 'aliado-activo';

    public const STATUS_INACTIVO = 'inactivo';

    public static function defaultStatus(): string
    {
        return self::STATUS_CAPTACION_PROSPECCION;
    }

    /**
     * @return array<string, string>
     */
    public static function typeOptions(): array
    {
        return [
            'agencia-corretaje' => 'Agencia (corretaje)',
            'agente-corretaje' => 'Agente (Corretaje)',
            'agencia-viajes' => 'Agencia de Viajes',
            'mayorista-viajes' => 'Mayorista de Viajes',
            'freelance' => 'Freelance',
            'asesor-exclusivo' => 'Asesor exclusivo',
            'cliente-individual' => 'Cliente Individual',
            'cliente-corporativo' => 'Cliente Corporativo',
            'ejecutivo' => 'Ejecutivo',
            'otro' => 'Otro',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_CAPTACION_PROSPECCION => 'Captación/prospección',
            self::STATUS_CONTACTO_INICIAL => 'Contacto inicial',
            self::STATUS_EN_PROCESO_NEGOCIACION => 'En proceso/En negociación',
            self::STATUS_ALIADO_ACTIVO => 'Aliado activo',
            self::STATUS_INACTIVO => 'Inactivo',
        ];
    }

    /**
     * Claves legacy del embudo anterior → estatus canónico actual.
     *
     * @return array<string, string>
     */
    public static function legacyStatusMap(): array
    {
        return [
            'captación' => self::STATUS_CAPTACION_PROSPECCION,
            'prospecto' => self::STATUS_CAPTACION_PROSPECCION,
            'en-proceso' => self::STATUS_EN_PROCESO_NEGOCIACION,
            'en-negociación' => self::STATUS_EN_PROCESO_NEGOCIACION,
        ];
    }

    /**
     * @return list<string>
     */
    public static function statusKeysIncludingLegacy(string $canonical): array
    {
        $keys = [$canonical];

        foreach (self::legacyStatusMap() as $legacy => $mapped) {
            if ($mapped === $canonical) {
                $keys[] = $legacy;
            }
        }

        return array_values(array_unique($keys));
    }

    public static function normalizeStatus(?string $key): ?string
    {
        if ($key === null || $key === '') {
            return null;
        }

        if (array_key_exists($key, self::statusOptions())) {
            return $key;
        }

        return self::legacyStatusMap()[$key] ?? $key;
    }

    /**
     * @return array<string, string>
     */
    public static function referenceOptions(): array
    {
        return [
            'directiva-TDG' => 'Directiva TDG',
            'gerencia-de-negocios' => 'Gerencia de Negocios',
            'whatsapp-comercial' => 'Whatsapp Comercial',
            'redes-sociales' => 'Redes sociales',
            'tercero' => 'Tercero',
            'otro' => 'Otro',
        ];
    }

    public static function typeLabel(?string $key): string
    {
        if ($key === null || $key === '') {
            return '—';
        }

        return self::typeOptions()[$key] ?? $key;
    }

    public static function statusLabel(?string $key): string
    {
        if ($key === null || $key === '') {
            return '—';
        }

        $normalized = self::normalizeStatus($key) ?? $key;

        return self::statusOptions()[$normalized] ?? $key;
    }

    public static function referenceLabel(?string $key): string
    {
        if ($key === null || $key === '') {
            return '—';
        }

        return self::referenceOptions()[$key] ?? $key;
    }

    public static function statusColor(?string $state): string
    {
        $normalized = self::normalizeStatus($state);

        return match ($normalized) {
            self::STATUS_CAPTACION_PROSPECCION => 'gray',
            self::STATUS_CONTACTO_INICIAL => 'info',
            self::STATUS_EN_PROCESO_NEGOCIACION => 'primary',
            self::STATUS_ALIADO_ACTIVO => 'success',
            self::STATUS_INACTIVO => 'danger',
            default => 'gray',
        };
    }
}
