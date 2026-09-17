<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Controllers\AnnualCollectionController;
use App\Http\Controllers\UtilsController;
use App\Models\Affiliation;
use App\Models\AnnualCollection;
use App\Models\Collection;
use App\Models\Sale;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Crea avisos de cobranza pendientes a partir de una venta YA registrada.
 * No crea ni duplica ventas, pagos ni comisiones.
 */
final class MissingCollectionsFromSaleGenerator
{
    /**
     * @return array{
     *     affiliation_code: string,
     *     sale_id: int,
     *     invoice_number: string,
     *     payment_frequency: string,
     *     created: int,
     *     skipped_existing: int,
     *     dates: list<string>,
     *     persisted: bool
     * }
     */
    public function generate(string $affiliationCode, ?string $invoiceNumber = null, bool $persist = false, ?string $actor = null): array
    {
        $code = trim($affiliationCode);
        $affiliation = Affiliation::query()->where('code', $code)->first();

        if (! $affiliation instanceof Affiliation) {
            throw new InvalidArgumentException('No existe la afiliación '.$code.'.');
        }

        $saleQuery = Sale::query()->where('affiliation_code', $affiliation->code);

        if (filled($invoiceNumber)) {
            $saleQuery->where('invoice_number', trim($invoiceNumber));
        }

        /** @var Sale|null $sale */
        $sale = $saleQuery->latest('id')->first();

        if (! $sale instanceof Sale) {
            throw new InvalidArgumentException(
                'La afiliación '.$affiliation->code.' no tiene una venta registrada'.
                (filled($invoiceNumber) ? ' con factura '.$invoiceNumber : '').'.'
            );
        }

        $frequency = mb_strtoupper(trim((string) ($affiliation->payment_frequency ?: $sale->payment_frequency ?: 'ANUAL')));
        $activation = self::parseFlexibleDate($affiliation->activated_at)
            ?? self::parseFlexibleDate($sale->date_activation);
        $paymentDate = self::parseFlexibleDate($sale->payment_date)
            ?? ($sale->created_at instanceof Carbon ? $sale->created_at->copy()->startOfDay() : Carbon::today());

        if (! $activation instanceof Carbon) {
            throw new InvalidArgumentException('La afiliación '.$affiliation->code.' no tiene fecha de activación.');
        }

        $cycleStart = self::cycleStart($activation, $paymentDate);
        $pendingDates = self::pendingInstallmentDates($cycleStart, $frequency, $paymentDate);

        $existingDates = Collection::query()
            ->where('affiliation_code', $affiliation->code)
            ->pluck('next_payment_date')
            ->map(fn (mixed $value): ?string => self::parseFlexibleDate($value)?->format('d/m/Y'))
            ->filter()
            ->all();
        $existingLookup = array_fill_keys($existingDates, true);

        $datesToCreate = [];
        $skippedExisting = 0;

        foreach ($pendingDates as $date) {
            $label = $date->format('d/m/Y');
            if (isset($existingLookup[$label])) {
                $skippedExisting++;

                continue;
            }

            $datesToCreate[] = $date;
        }

        $payload = [
            'affiliation_code' => (string) $affiliation->code,
            'sale_id' => (int) $sale->id,
            'invoice_number' => (string) $sale->invoice_number,
            'payment_frequency' => $frequency,
            'created' => 0,
            'skipped_existing' => $skippedExisting,
            'dates' => array_map(static fn (Carbon $date): string => $date->format('d/m/Y'), $datesToCreate),
            'persisted' => false,
        ];

        if (! $persist || $datesToCreate === []) {
            return $payload;
        }

        $actor ??= 'SISTEMA';
        $created = 0;
        $lastCollection = null;

        DB::transaction(function () use ($affiliation, $sale, $frequency, $cycleStart, $datesToCreate, $actor, &$created, &$lastCollection): void {
            $affiliation->loadMissing(['individual_quote']);
            $lastInvoiceNumber = $this->resolveLastCollectionInvoiceNumber();
            $expirationDays = $frequency === 'MENSUAL' ? 30 : 5;

            foreach ($datesToCreate as $paymentDate) {
                $nextPaymentDate = $paymentDate->format('d/m/Y');
                $collection = new Collection;
                $collection->sale_id = $sale->id;
                $collection->include_date = $cycleStart->format('d/m/Y');
                $collection->owner_code = $affiliation->owner_code;
                $collection->code_agency = $affiliation->code_agency;
                $collection->plan_id = $affiliation->plan_id;
                $collection->coverage_id = $affiliation->coverage_id;
                $collection->agent_id = $affiliation->agent_id;
                $collection->collection_invoice_number = UtilsController::generateCorrelativeCollection($lastInvoiceNumber);
                $collection->quote_number = (string) ($affiliation->individual_quote?->code ?? $affiliation->code_individual_quote ?? 'N/A');
                $collection->affiliation_code = $affiliation->code;
                $collection->affiliate_full_name = $affiliation->full_name_ti;
                $collection->affiliate_contact = $affiliation->full_name_con ?: $affiliation->full_name_ti;
                $collection->affiliate_ci_rif = $affiliation->nro_identificacion_ti;
                $collection->affiliate_phone = $affiliation->phone_ti;
                $collection->affiliate_email = $affiliation->email_ti;
                $collection->affiliate_status = $affiliation->status;
                $collection->type = 'AFILIACION INDIVIDUAL';
                $collection->service = 'servicio';
                $collection->persons = (string) ($affiliation->family_members ?? $sale->persons ?? 0);
                $collection->total_amount = $sale->total_amount;
                $collection->payment_method = null;
                $collection->pay_amount_usd = 0.00;
                $collection->pay_amount_ves = 0.00;
                $collection->bank_usd = 'N/A';
                $collection->bank_ves = 'N/A';
                $collection->payment_frequency = $frequency;
                $collection->reference = $sale->reference_payment;
                $collection->next_payment_date = $nextPaymentDate;
                $collection->filter_next_payment_date = $paymentDate->format('Y-m-d');
                $collection->expiration_date = $paymentDate->copy()->addDays($expirationDays)->format('d/m/Y');
                $collection->status = 'POR PAGAR';
                $collection->days = 0;
                $collection->created_by = $actor;
                $collection->save();

                $lastInvoiceNumber = (string) $collection->collection_invoice_number;
                $lastCollection = $collection;
                $created++;
            }

            if ($lastCollection instanceof Collection) {
                $this->syncAnnualCollection($lastCollection, $datesToCreate);
            }
        });

        $payload['created'] = $created;
        $payload['persisted'] = true;

        return $payload;
    }

    /**
     * @return list<Carbon>
     */
    public static function pendingInstallmentDates(Carbon $cycleStart, string $frequency, Carbon $paymentDate): array
    {
        $dates = AffiliationRenewalCollectionGenerator::upcomingPaymentDates(
            $cycleStart->copy()->startOfDay(),
            $frequency,
        );
        $paidUntil = $paymentDate->copy()->startOfDay()->addDays(7);
        $pending = array_values(array_filter(
            $dates,
            static fn (Carbon $date): bool => $date->copy()->startOfDay()->gt($paidUntil),
        ));

        if ($pending !== []) {
            return $pending;
        }

        $intervalMonths = match (mb_strtoupper(trim($frequency))) {
            'MENSUAL' => 1,
            'TRIMESTRAL' => 3,
            'SEMESTRAL' => 6,
            default => 12,
        };

        return [$cycleStart->copy()->startOfDay()->addMonthsNoOverflow($intervalMonths)];
    }

    public static function cycleStart(Carbon $activation, Carbon $paymentDate): Carbon
    {
        $start = $activation->copy()->startOfDay()->year($paymentDate->year);

        if ($start->gt($paymentDate->copy()->startOfDay()->addDays(15))) {
            $start->subYear();
        }

        return $start;
    }

    public static function parseFlexibleDate(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy()->startOfDay();
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $text = trim($value);

        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d'] as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $text);
                if ($parsed instanceof Carbon) {
                    return $parsed->startOfDay();
                }
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($text)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveLastCollectionInvoiceNumber(): string
    {
        $lastCollection = Collection::query()->latest('id')->first();

        if ($lastCollection === null || blank($lastCollection->collection_invoice_number)) {
            return '';
        }

        return (string) $lastCollection->collection_invoice_number;
    }

    /**
     * @param  list<Carbon>  $dates
     */
    private function syncAnnualCollection(Collection $collection, array $dates): void
    {
        $annual = AnnualCollection::query()
            ->where('affiliation_code', $collection->affiliation_code)
            ->latest('id')
            ->first();

        $attributes = [
            'sale_id' => $collection->sale_id,
            'include_date' => $collection->include_date,
            'owner_code' => $collection->owner_code,
            'code_agency' => $collection->code_agency,
            'agent_id' => $collection->agent_id,
            'coverage_id' => $collection->coverage_id,
            'collection_invoice_number' => $collection->collection_invoice_number,
            'quote_number' => $collection->quote_number,
            'affiliation_code' => $collection->affiliation_code,
            'affiliate_full_name' => $collection->affiliate_full_name,
            'affiliate_contact' => $collection->affiliate_contact,
            'affiliate_ci_rif' => $collection->affiliate_ci_rif,
            'affiliate_phone' => $collection->affiliate_phone,
            'affiliate_email' => $collection->affiliate_email,
            'affiliate_status' => $collection->affiliate_status,
            'plan_id' => $collection->plan_id,
            'service' => $collection->service,
            'persons' => $collection->persons,
            'type' => $collection->type,
            'next_payment_date' => $collection->next_payment_date,
            'expiration_date' => $collection->expiration_date,
            'filter_next_payment_date' => $collection->filter_next_payment_date,
            'status' => 'POR PAGAR',
        ];

        if ($annual instanceof AnnualCollection) {
            $annual->fill($attributes);
            $annual->save();
        } else {
            $annual = new AnnualCollection($attributes);
            $annual->save();
        }

        foreach ($dates as $date) {
            $month = AnnualCollectionController::extractMonth($date->format('d/m/Y'));
            $annual->update([
                'month_'.$month => true,
            ]);
        }
    }
}
