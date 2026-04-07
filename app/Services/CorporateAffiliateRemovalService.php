<?php

namespace App\Services;

use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use App\Models\AfilliationCorporatePlan;
use App\Models\AgeRange;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class CorporateAffiliateRemovalService
{
    /**
     * Convierte el monto anual al monto por periodo según la frecuencia de pago de la afiliación.
     */
    public static function annualFeeToPerPeriodAmount(float $annualFee, ?string $paymentFrequency): float
    {
        $f = strtoupper(trim((string) $paymentFrequency));

        return match ($f) {
            'ANUAL' => $annualFee,
            'SEMESTRAL' => $annualFee / 2,
            'TRIMESTRAL' => $annualFee / 4,
            'MENSUAL' => $annualFee / 12,
            default => $annualFee,
        };
    }

    /**
     * Recalcula subtotales de una fila de `afilliation_corporate_plans` a partir de tarifa por persona y cantidad.
     */
    public static function recalculateCorporatePlanRowTotals(AfilliationCorporatePlan $planRow): void
    {
        $persons = max(0, (int) $planRow->total_persons);
        $feePerPerson = (float) $planRow->fee;
        $annual = $feePerPerson * $persons;

        $planRow->subtotal_anual = $annual;
        $planRow->subtotal_quarterly = $annual / 4;
        $planRow->subtotal_biannual = $annual / 2;
        $planRow->subtotal_monthly = $annual / 12;
    }

    /**
     * Baja lógica de un afiliado corporativo: actualiza plan, montos de la afiliación y estado del afiliado.
     */
    public static function deactivate(AffiliateCorporate $affiliate, AffiliationCorporate $owner): void
    {
        DB::transaction(function () use ($affiliate, $owner): void {
            /** @var AffiliationCorporate $ownerLocked */
            $ownerLocked = AffiliationCorporate::query()
                ->whereKey($owner->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            /** @var AffiliateCorporate $affiliateLocked */
            $affiliateLocked = AffiliateCorporate::query()
                ->whereKey($affiliate->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $affiliateLocked->affiliation_corporate_id !== (int) $ownerLocked->getKey()) {
                throw new RuntimeException('El afiliado no pertenece a esta afiliación corporativa.');
            }

            if (in_array($affiliateLocked->status, ['INACTIVO', 'EXCLUIDO'], true)) {
                return;
            }

            $ageRangeIds = AgeRange::query()
                ->where('plan_id', $affiliateLocked->plan_id)
                ->where('age_init', '<=', (int) $affiliateLocked->age)
                ->where('age_end', '>=', (int) $affiliateLocked->age)
                ->pluck('id');

            if ($ageRangeIds->isEmpty()) {
                throw new RuntimeException('No se encontró un rango de edad válido para el plan y la edad del afiliado.');
            }

            /** @var AfilliationCorporatePlan|null $planRow */
            $planRow = AfilliationCorporatePlan::query()
                ->where('affiliation_corporate_id', $ownerLocked->getKey())
                ->where('plan_id', $affiliateLocked->plan_id)
                ->where('coverage_id', $affiliateLocked->coverage_id)
                ->whereIn('age_range_id', $ageRangeIds)
                ->lockForUpdate()
                ->orderByDesc('total_persons')
                ->first();

            if ($planRow === null) {
                throw new RuntimeException('No se encontró la fila de plan corporativo (plan, cobertura y rango de edad).');
            }

            if ((int) $planRow->total_persons < 1) {
                throw new RuntimeException('La población del plan ya está en cero; no se puede dar de baja nuevamente.');
            }

            $planRow->total_persons = max(0, (int) $planRow->total_persons - 1);
            self::recalculateCorporatePlanRowTotals($planRow);
            $planRow->save();

            $feeToSubtract = (float) $affiliateLocked->fee;
            $newFeeAnual = max(0, (float) $ownerLocked->fee_anual - $feeToSubtract);
            $ownerLocked->fee_anual = $newFeeAnual;
            $ownerLocked->total_amount = self::annualFeeToPerPeriodAmount(
                $newFeeAnual,
                $ownerLocked->payment_frequency
            );
            $ownerLocked->poblation = max(0, (int) $ownerLocked->poblation - 1);
            $ownerLocked->save();

            $affiliateLocked->update([
                'status' => 'INACTIVO',
            ]);
        });
    }
}
