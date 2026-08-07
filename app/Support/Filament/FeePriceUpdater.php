<?php

declare(strict_types=1);

namespace App\Support\Filament;

use App\Models\Fee;
use App\Support\SecurityAudit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class FeePriceUpdater
{
    /**
     * @return array{
     *     fee_id: int,
     *     price_from: float,
     *     price_to: float,
     *     reason: string
     * }
     */
    public static function update(Fee $fee, float|int|string $newPrice, string $reason): array
    {
        $reason = trim($reason);
        $previousPrice = round((float) $fee->price, 2);
        $priceTo = round((float) $newPrice, 2);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'Debe indicar el motivo del cambio de tarifa.',
            ]);
        }

        if (mb_strlen($reason) < 10) {
            throw ValidationException::withMessages([
                'reason' => 'El motivo debe tener al menos 10 caracteres.',
            ]);
        }

        if ($priceTo < 0) {
            throw ValidationException::withMessages([
                'price' => 'El monto de la tarifa no puede ser negativo.',
            ]);
        }

        if (abs($priceTo - $previousPrice) < 0.00001) {
            throw ValidationException::withMessages([
                'price' => 'El nuevo monto debe ser distinto al actual.',
            ]);
        }

        $fee->price = $priceTo;
        $fee->save();

        SecurityAudit::log(
            'AUDIT_BUSINESS_FEE_PRICE_UPDATED',
            'business.fees.price.update',
            [
                'fee_id' => $fee->getKey(),
                'fee_code' => $fee->code,
                'age_range_id' => $fee->age_range_id,
                'coverage_id' => $fee->coverage_id,
                'plan_id' => $fee->ageRange?->plan_id,
                'plan_description' => $fee->ageRange?->plan?->description,
                'price_from' => $previousPrice,
                'price_to' => $priceTo,
                'reason' => $reason,
                'updated_by' => Auth::id(),
                'updated_by_name' => Auth::user()?->name,
            ],
        );

        return [
            'fee_id' => (int) $fee->getKey(),
            'price_from' => $previousPrice,
            'price_to' => $priceTo,
            'reason' => $reason,
        ];
    }
}
