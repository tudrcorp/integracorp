<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\ResolvesFromMixedState;

/**
 * Cómo se cuenta el cupo clínico de un beneficio. Independiente del tope
 * comercial en USD (`benefit_coverages.limit`).
 */
enum ClinicalQuotaScope: string
{
    use ResolvesFromMixedState;

    case PerAffiliationYear = 'PER_AFFILIATION_YEAR';
    case PerContract = 'PER_CONTRACT';
    case DistinctCases = 'DISTINCT_CASES';
    case Unlimited = 'UNLIMITED';

    public function label(): string
    {
        return match ($this) {
            self::PerAffiliationYear => 'Por año de vigencia',
            self::PerContract => 'Por contrato',
            self::DistinctCases => 'En casos diferentes',
            self::Unlimited => 'Ilimitado',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::PerAffiliationYear => 'Se reinicia cada año de vigencia de la afiliación (desde la fecha de efecto).',
            self::PerContract => 'Cuenta todos los usos mientras el contrato esté vigente, sin recorte anual.',
            self::DistinctCases => 'Cuenta casos distintos del afiliado, no cada consulta del mismo caso.',
            self::Unlimited => 'No hay tope de usos. El médico puede asignarlo siempre que el plan lo incluya.',
        };
    }

    public function helperForAnalyst(): string
    {
        return match ($this) {
            self::PerAffiliationYear => 'Ejemplo: 4 telemedicinas en el año de vigencia.',
            self::PerContract => 'Ejemplo: 3 usos en total durante el contrato.',
            self::DistinctCases => 'Ejemplo: 2 estudios de imagen en casos diferentes.',
            self::Unlimited => 'Sin conteo. Sigue haciendo falta mapear el servicio.',
        };
    }

    public function requiresQuota(): bool
    {
        return $this !== self::Unlimited;
    }

    public function filamentColor(): string
    {
        return match ($this) {
            self::PerAffiliationYear => 'info',
            self::PerContract => 'warning',
            self::DistinctCases => 'success',
            self::Unlimited => 'gray',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $out = [];

        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }
}
