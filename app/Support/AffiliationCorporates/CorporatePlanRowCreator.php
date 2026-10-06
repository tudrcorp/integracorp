<?php

declare(strict_types=1);

namespace App\Support\AffiliationCorporates;

use App\Models\AffiliationCorporate;
use App\Models\AfilliationCorporatePlan;
use App\Models\AgeRange;
use App\Models\Fee;
use App\Models\Plan;
use App\Models\User;
use App\Support\SecurityAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Crea una fila de plan en una afiliación corporativa («Asociar Nuevo Plan»).
 *
 * La tarifa anual sale de la tabla `fees` (estándar) o la escribe el analista
 * cuando la negociación con la empresa fue distinta. Una tarifa negociada exige
 * motivo y deja traza en la fila (motivo, autor y fecha) y en SecurityAudit.
 */
final class CorporatePlanRowCreator
{
    public const SOURCE_STANDARD = 'ESTANDAR';

    public const SOURCE_NEGOTIATED = 'NEGOCIADA';

    public const REASON_MIN_LENGTH = 10;

    public const REASON_MAX_LENGTH = 1000;

    public const MAX_FEE = 999999.99;

    /**
     * Precios estándar del rango de edad y la cobertura, como opciones del select.
     *
     * @return array<string, string>
     */
    public static function standardFeeOptions(mixed $ageRangeId, mixed $coverageId): array
    {
        if (blank($ageRangeId)) {
            return [];
        }

        $coverageId = self::normalizeCoverageId($coverageId);

        return Fee::query()
            ->where('age_range_id', (int) $ageRangeId)
            // Sin cobertura (planes de tarifa plana) la tabla guarda NULL o 0.
            ->when(
                $coverageId === null,
                fn ($query) => $query->where(fn ($inner) => $inner->whereNull('coverage_id')->orWhere('coverage_id', 0)),
                fn ($query) => $query->where('coverage_id', $coverageId),
            )
            ->orderBy('price')
            ->pluck('price')
            ->mapWithKeys(fn (mixed $price): array => [self::formatAmount((float) $price) => self::formatAmount((float) $price)])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data  estado de la modal
     */
    public static function create(AffiliationCorporate $owner, array $data, ?User $user): AfilliationCorporatePlan
    {
        $planId = (int) ($data['plan_id'] ?? 0);
        $ageRangeId = (int) ($data['age_range_id'] ?? 0);
        $coverageId = self::normalizeCoverageId($data['coverage_id'] ?? null);
        $source = ($data['fee_source'] ?? self::SOURCE_STANDARD) === self::SOURCE_NEGOTIATED
            ? self::SOURCE_NEGOTIATED
            : self::SOURCE_STANDARD;

        if (! Plan::query()->whereKey($planId)->exists()) {
            throw ValidationException::withMessages(['plan_id' => 'Seleccione un plan válido.']);
        }

        if (! AgeRange::query()->whereKey($ageRangeId)->where('plan_id', $planId)->exists()) {
            throw ValidationException::withMessages(['age_range_id' => 'El rango de edad no pertenece al plan seleccionado.']);
        }

        $standardOptions = self::standardFeeOptions($ageRangeId, $coverageId);
        $reason = null;

        if ($source === self::SOURCE_STANDARD) {
            $fee = self::parseAmount($data['fee'] ?? null);

            if ($fee === null || ! array_key_exists(self::formatAmount($fee), $standardOptions)) {
                throw ValidationException::withMessages(['fee' => 'Seleccione una tarifa estándar del rango de edad y la cobertura elegidos.']);
            }
        } else {
            $fee = self::parseAmount($data['negotiated_fee'] ?? null);

            if ($fee === null || $fee <= 0 || $fee > self::MAX_FEE) {
                throw ValidationException::withMessages(['negotiated_fee' => 'Escriba un monto anual mayor que 0 (hasta 2 decimales).']);
            }

            $reason = trim((string) ($data['fee_negotiation_reason'] ?? ''));

            if (mb_strlen($reason) < self::REASON_MIN_LENGTH || mb_strlen($reason) > self::REASON_MAX_LENGTH) {
                throw ValidationException::withMessages(['fee_negotiation_reason' => 'Explique el motivo de la negociación (entre '.self::REASON_MIN_LENGTH.' y '.self::REASON_MAX_LENGTH.' caracteres).']);
            }
        }

        $duplicated = $owner->affiliationCorporatePlans()
            ->where('plan_id', $planId)
            ->where('age_range_id', $ageRangeId)
            ->where('coverage_id', $coverageId)
            ->exists();

        if ($duplicated) {
            throw ValidationException::withMessages(['plan_id' => 'El plan, la cobertura y el rango de edad seleccionados ya están en la lista de planes afiliados.']);
        }

        $planRow = DB::transaction(function () use ($owner, $planId, $ageRangeId, $coverageId, $fee, $source, $reason, $user, $data): AfilliationCorporatePlan {
            $negotiated = $source === self::SOURCE_NEGOTIATED;

            return $owner->affiliationCorporatePlans()->create([
                'affiliation_corporate_id' => $owner->id,
                'code_affiliation' => $owner->code,
                'plan_id' => $planId,
                'coverage_id' => $coverageId,
                'age_range_id' => $ageRangeId,
                'fee' => $fee,
                'fee_source' => $source,
                'fee_negotiation_reason' => $reason,
                'fee_negotiated_by' => $negotiated ? $user?->getKey() : null,
                'fee_negotiated_at' => $negotiated ? now() : null,
                'payment_frequency' => $data['payment_frequency'] ?? $owner->payment_frequency,
                'total_persons' => 0,
                'subtotal_anual' => $fee,
                'subtotal_biannual' => $fee / 2,
                'subtotal_quarterly' => $fee / 4,
                'status' => 'ACTIVA',
                'created_by' => $user?->getKey(),
            ]);
        });

        if ($source === self::SOURCE_NEGOTIATED) {
            SecurityAudit::log('AUDIT_BUSINESS_CORPORATE_PLAN_NEGOTIATED_FEE', 'business.affiliation-corporates.plans.create', [
                'affiliation_corporate_id' => $owner->id,
                'affiliation_code' => $owner->code,
                'plan_row_id' => $planRow->getKey(),
                'plan_id' => $planId,
                'age_range_id' => $ageRangeId,
                'coverage_id' => $coverageId,
                'negotiated_fee' => $fee,
                'standard_fees' => array_values($standardOptions),
                'reason' => $reason,
            ], $user);
        }

        return $planRow;
    }

    /**
     * Si la tarifa recibida es la negociada de esta fila. Asociar afiliados a una
     * fila negociada no debe exigir que el monto exista en la tabla `fees`.
     */
    public static function isNegotiatedFeeOfRow(AfilliationCorporatePlan $planRow, float $fee): bool
    {
        return $planRow->fee_source === self::SOURCE_NEGOTIATED
            && abs((float) $planRow->fee - $fee) < 0.01;
    }

    private static function normalizeCoverageId(mixed $value): ?int
    {
        if ($value === null || $value === '' || (int) $value === 0) {
            return null;
        }

        return (int) $value;
    }

    private static function parseAmount(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = str_replace([' ', '$'], '', (string) $value);

        if (! is_numeric($normalized)) {
            return null;
        }

        return round((float) $normalized, 2);
    }

    private static function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
