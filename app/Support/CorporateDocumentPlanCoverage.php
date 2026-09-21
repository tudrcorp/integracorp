<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Coverage;

/**
 * Precio de cobertura para líneas de factura/aviso corporativo.
 *
 * Un plan en modo PAQUETE (como el Inicial) no tiene coberturas: no se consulta
 * `coverages` y el documento omite el monto. El fallback `coverage_id` nulo
 * cubre filas históricas o coberturas desvinculadas.
 */
final class CorporateDocumentPlanCoverage
{
    public static function priceForLine(mixed $planId, mixed $coverageId): mixed
    {
        $normalizedCoverageId = self::positiveInt($coverageId);

        if ($normalizedCoverageId === null) {
            return null;
        }

        $normalizedPlanId = self::positiveInt($planId);

        if ($normalizedPlanId !== null && app(AffiliationAffiliateFeeCalculator::class)->planHasNoCoverages($normalizedPlanId)) {
            return null;
        }

        return Coverage::query()->whereKey($normalizedCoverageId)->value('price');
    }

    private static function positiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }
}
