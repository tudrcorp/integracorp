<?php

declare(strict_types=1);

namespace App\Support\AffiliationCorporates\BillingAdjustments;

use App\Models\CorporateBillingAdjustment;
use App\Models\CorporatePopulationUpdate;
use App\Models\CorporatePopulationUpdateItem;
use Illuminate\Database\Eloquent\Builder;

/**
 * Bandeja «Ajustes por población» de Administración: una fila por carga de población aplicada
 * (o que tuvo ajustes), con su estado de facturación. La usan la tabla, el resumen de la página
 * y el contador del menú, así que los tres cuentan igual.
 */
final class PopulationBillingTray
{
    public const STATE_PENDING = 'pending';

    public const STATE_ADJUSTED = 'adjusted';

    public const STATE_ROLLED_BACK = 'rolled_back';

    /**
     * @return array<string, string>
     */
    public static function stateOptions(): array
    {
        return [
            self::STATE_PENDING => 'Por ajustar',
            self::STATE_ADJUSTED => 'Ajustada',
            self::STATE_ROLLED_BACK => 'Población revertida',
        ];
    }

    /**
     * @return Builder<CorporatePopulationUpdate>
     */
    public static function query(): Builder
    {
        $increase = [CorporatePopulationUpdateItem::ACTION_ADD, CorporatePopulationUpdateItem::ACTION_REACTIVATE];
        $decrease = [CorporatePopulationUpdateItem::ACTION_DEACTIVATE, CorporatePopulationUpdateItem::ACTION_DUPLICATE_BASE];

        return CorporatePopulationUpdate::query()
            ->where(fn (Builder $query): Builder => $query
                ->where('status', CorporatePopulationUpdate::STATUS_APPLIED)
                ->orWhereHas('billingAdjustments'))
            ->with([
                'affiliationCorporate:id,code,name_corporate,payment_frequency,status',
                'billingAdjustments' => fn ($query) => $query->orderByDesc('id'),
                'billingAdjustments.chargeCollection:id,collection_invoice_number,status,total_amount,next_payment_date',
            ])
            ->withCount([
                'items as persons_added_count' => fn (Builder $query): Builder => $query->where('apply_status', CorporatePopulationUpdateItem::APPLY_APPLIED)->whereIn('action', $increase),
                'items as persons_removed_count' => fn (Builder $query): Builder => $query->where('apply_status', CorporatePopulationUpdateItem::APPLY_APPLIED)->whereIn('action', $decrease),
            ]);
    }

    /**
     * @param  Builder<CorporatePopulationUpdate>  $query
     * @return Builder<CorporatePopulationUpdate>
     */
    public static function applyStateFilter(Builder $query, ?string $state): Builder
    {
        $applied = fn (Builder $adjustments): Builder => $adjustments->where('status', CorporateBillingAdjustment::STATUS_APPLIED);

        return match ($state) {
            self::STATE_PENDING => $query->where('status', CorporatePopulationUpdate::STATUS_APPLIED)->whereDoesntHave('billingAdjustments', $applied),
            self::STATE_ADJUSTED => $query->whereHas('billingAdjustments', $applied),
            self::STATE_ROLLED_BACK => $query->where('status', '!=', CorporatePopulationUpdate::STATUS_APPLIED)->whereDoesntHave('billingAdjustments', $applied),
            default => $query,
        };
    }

    public static function state(CorporatePopulationUpdate $update): string
    {
        if (self::appliedAdjustment($update) instanceof CorporateBillingAdjustment) {
            return self::STATE_ADJUSTED;
        }

        return $update->status === CorporatePopulationUpdate::STATUS_APPLIED ? self::STATE_PENDING : self::STATE_ROLLED_BACK;
    }

    /**
     * El ajuste aplicado de la carga, usando la relación ya cargada (sin consultas por fila).
     */
    public static function appliedAdjustment(CorporatePopulationUpdate $update): ?CorporateBillingAdjustment
    {
        $adjustments = $update->relationLoaded('billingAdjustments') ? $update->billingAdjustments : $update->billingAdjustments()->orderByDesc('id')->get();

        return $adjustments->first(fn (CorporateBillingAdjustment $adjustment): bool => $adjustment->isApplied());
    }

    /**
     * El ajuste que se muestra en la ficha: el aplicado o, si no hay, el último revertido.
     */
    public static function displayedAdjustment(CorporatePopulationUpdate $update): ?CorporateBillingAdjustment
    {
        $adjustments = $update->relationLoaded('billingAdjustments') ? $update->billingAdjustments : $update->billingAdjustments()->orderByDesc('id')->get();

        return self::appliedAdjustment($update) ?? $adjustments->sortByDesc('id')->first();
    }

    /**
     * @return array{pending_count: int, adjusted_count: int, charged_cents: int, pending_charge_cents: int}
     */
    public static function summary(): array
    {
        $pending = self::applyStateFilter(CorporatePopulationUpdate::query()->where(fn (Builder $query): Builder => $query->where('status', CorporatePopulationUpdate::STATUS_APPLIED)->orWhereHas('billingAdjustments')), self::STATE_PENDING)->count();
        $applied = CorporateBillingAdjustment::query()->where('status', CorporateBillingAdjustment::STATUS_APPLIED);

        return [
            'pending_count' => $pending,
            'adjusted_count' => (clone $applied)->count(),
            'charged_cents' => PopulationBillingAdjuster::cents((clone $applied)->sum('charge_amount')),
            'pending_charge_cents' => PopulationBillingAdjuster::cents(
                (clone $applied)->whereHas('chargeCollection', fn (Builder $query): Builder => $query->where('status', PopulationBillingAdjuster::STATUS_PENDING))->sum('charge_amount')
            ),
        ];
    }
}
