<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Controllers\UtilsController;
use App\Models\Affiliation;
use App\Models\Collection;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

final class AffiliationRenewalCollectionGenerator
{
    /**
     * @return list<Carbon>
     */
    public static function upcomingPaymentDates(Carbon $effectiveDate, string $paymentFrequency): array
    {
        $frequency = mb_strtoupper(trim($paymentFrequency));

        $intervalMonths = match ($frequency) {
            'MENSUAL' => 1,
            'TRIMESTRAL' => 3,
            'SEMESTRAL' => 6,
            'ANUAL' => 12,
            default => 12,
        };

        $installments = match ($frequency) {
            'MENSUAL' => 12,
            'TRIMESTRAL' => 4,
            'SEMESTRAL' => 2,
            'ANUAL' => 1,
            default => 1,
        };

        $dates = [];

        for ($installment = 0; $installment < $installments; $installment++) {
            $dates[] = $effectiveDate->copy()->addMonthsNoOverflow($intervalMonths * $installment);
        }

        return $dates;
    }

    public function createPendingCollectionsForRenewal(
        Affiliation $affiliation,
        Carbon $effectiveDate,
        ?string $createdBy = null,
    ): int {
        $affiliation->loadMissing(['individual_quote']);

        $paymentFrequency = (string) ($affiliation->payment_frequency ?? 'ANUAL');
        $paymentDates = self::upcomingPaymentDates($effectiveDate->copy()->startOfDay(), $paymentFrequency);

        if ($paymentDates === []) {
            return 0;
        }

        $createdBy ??= Auth::user()?->name ?? 'SISTEMA';
        $periodAmount = (float) ($affiliation->total_amount ?? 0);
        $includeDate = $effectiveDate->format('d/m/Y');
        $lastInvoiceNumber = $this->resolveLastCollectionInvoiceNumber();
        $created = 0;

        foreach ($paymentDates as $paymentDate) {
            $nextPaymentDate = $paymentDate->format('d/m/Y');
            $expirationDays = $paymentFrequency === 'MENSUAL' ? 30 : 5;

            $collection = new Collection;
            $collection->sale_id = $this->resolveSaleIdForAffiliation($affiliation);
            $collection->include_date = $includeDate;
            $collection->owner_code = $affiliation->owner_code;
            $collection->code_agency = $affiliation->code_agency;
            $collection->plan_id = $affiliation->plan_id;
            $collection->coverage_id = $affiliation->coverage_id;
            $collection->agent_id = $affiliation->agent_id;
            $collection->collection_invoice_number = UtilsController::generateCorrelativeCollection($lastInvoiceNumber);
            $collection->quote_number = (string) ($affiliation->individual_quote?->code ?? $affiliation->code_individual_quote ?? 'N/A');
            $collection->affiliation_code = $affiliation->code;
            $collection->affiliate_full_name = $affiliation->full_name_ti;
            $collection->affiliate_contact = $affiliation->full_name_ti;
            $collection->affiliate_ci_rif = $affiliation->nro_identificacion_ti;
            $collection->affiliate_phone = $affiliation->phone_ti;
            $collection->affiliate_email = $affiliation->email_ti;
            $collection->affiliate_status = $affiliation->status;
            $collection->type = 'AFILIACION INDIVIDUAL';
            $collection->service = 'servicio';
            $collection->persons = (string) ($affiliation->family_members ?? 0);
            $collection->total_amount = $periodAmount;
            $collection->payment_method = null;
            $collection->payment_frequency = $paymentFrequency;
            $collection->next_payment_date = $nextPaymentDate;
            $collection->filter_next_payment_date = $paymentDate->format('Y-m-d');
            $collection->expiration_date = $paymentDate->copy()->addDays($expirationDays)->format('d/m/Y');
            $collection->status = 'POR PAGAR';
            $collection->days = 0;
            $collection->created_by = $createdBy;
            $collection->save();

            $lastInvoiceNumber = $collection->collection_invoice_number;
            $created++;
        }

        return $created;
    }

    private function resolveLastCollectionInvoiceNumber(): string
    {
        $lastCollection = Collection::query()->latest('id')->first();

        if ($lastCollection === null || blank($lastCollection->collection_invoice_number)) {
            return '';
        }

        return (string) $lastCollection->collection_invoice_number;
    }

    private function resolveSaleIdForAffiliation(Affiliation $affiliation): ?int
    {
        $saleId = Collection::query()
            ->where('affiliation_code', $affiliation->code)
            ->whereNotNull('sale_id')
            ->latest('id')
            ->value('sale_id');

        return $saleId !== null ? (int) $saleId : null;
    }
}
