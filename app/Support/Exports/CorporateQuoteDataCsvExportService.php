<?php

declare(strict_types=1);

namespace App\Support\Exports;

use App\Models\CorporateQuote;
use App\Models\CorporateQuoteData;
use App\Models\DetailCorporateQuote;
use App\Support\CsvExportStream;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CorporateQuoteDataCsvExportService
{
    /**
     * @return list<string>
     */
    public static function headers(): array
    {
        return [
            'Tipo registro',
            'ID',
            'Código cotización',
            'Cliente corporativo',
            'Estatus cotización',
            'Plan',
            'Rango edad',
            'Cobertura (USD)',
            'Tarifa (USD)',
            'Población (personas)',
            'Subtotal anual (USD)',
            'Nombres',
            'Apellidos',
            'Identificación',
            'Teléfono',
            'Correo',
            'Fecha de nacimiento',
            'Edad',
            'Sexo',
            'Dirección',
            'Condición médica',
            'Fecha de ingreso',
            'Cargo',
            'Contacto de emergencia',
            'Teléfono emergencia',
            'Fecha de registro',
        ];
    }

    /**
     * @param  array{corporate_quote_ids?: list<int|string>|null, corporate_quote_data_ids?: list<int|string>|null}  $filters
     */
    public function streamCsv(array $filters, string $panel = 'business'): StreamedResponse
    {
        $filename = 'cotizados_corporativos_'.now()->format('Y-m-d_His').'.csv';

        return new StreamedResponse(function () use ($filters, $panel): void {
            $handle = CsvExportStream::openOutput();

            if ($handle === false) {
                return;
            }

            fputcsv($handle, self::headers());

            if (self::hasCorporateQuoteDataIdsFilter($filters)) {
                self::individualsQuery($filters, $panel)
                    ->orderBy('id')
                    ->lazyById(200)
                    ->each(function (CorporateQuoteData $record) use ($handle): void {
                        fputcsv($handle, self::individualRow($record));
                    });
            } else {
                self::quotesQuery($filters, $panel)
                    ->orderBy('id')
                    ->lazyById(50)
                    ->each(function (CorporateQuote $quote) use ($handle): void {
                        self::writeQuotePopulation($handle, $quote);
                    });
            }

            fclose($handle);
        }, 200, CorporateQuoteCsvExportService::downloadHeaders($filename));
    }

    /**
     * @param  array{corporate_quote_ids?: list<int|string>|null, corporate_quote_data_ids?: list<int|string>|null}  $filters
     */
    public static function query(array $filters, string $panel = 'business'): Builder
    {
        if (self::hasCorporateQuoteDataIdsFilter($filters)) {
            return self::individualsQuery($filters, $panel);
        }

        return CorporateQuoteData::query()
            ->whereRaw('1 = 0');
    }

    /**
     * @param  array{corporate_quote_ids?: list<int|string>|null, corporate_quote_data_ids?: list<int|string>|null}  $filters
     */
    public static function quotesQuery(array $filters, string $panel = 'business'): Builder
    {
        $query = CorporateQuote::query();

        if ($panel === 'business' && Auth::user()?->is_accountManagers) {
            $query->where('ownerAccountManagers', Auth::id());
        }

        $corporateQuoteIds = self::normalizedCorporateQuoteIds($filters);

        if ($corporateQuoteIds !== []) {
            $query->whereIn('id', $corporateQuoteIds);
        }

        return $query;
    }

    /**
     * @param  array{corporate_quote_ids?: list<int|string>|null, corporate_quote_data_ids?: list<int|string>|null}  $filters
     */
    private static function individualsQuery(array $filters, string $panel = 'business'): Builder
    {
        $query = CorporateQuoteData::query()->with([
            'corporateQuote:id,code,full_name,status,ownerAccountManagers',
        ]);

        if ($panel === 'business' && Auth::user()?->is_accountManagers) {
            $query->whereHas(
                'corporateQuote',
                fn (Builder $quoteQuery): Builder => $quoteQuery->where('ownerAccountManagers', Auth::id()),
            );
        }

        $corporateQuoteDataIds = array_values(array_filter(
            array_map('intval', (array) ($filters['corporate_quote_data_ids'] ?? [])),
            fn (int $id): bool => $id > 0,
        ));

        if ($corporateQuoteDataIds !== []) {
            $query->whereIn('id', $corporateQuoteDataIds);

            return $query;
        }

        $corporateQuoteIds = self::normalizedCorporateQuoteIds($filters);

        if ($corporateQuoteIds !== []) {
            $query->whereIn('corporate_quote_id', $corporateQuoteIds);
        }

        return $query;
    }

    /**
     * @param  array{corporate_quote_ids?: list<int|string>|null, corporate_quote_data_ids?: list<int|string>|null}  $filters
     * @return list<int>
     */
    private static function normalizedCorporateQuoteIds(array $filters): array
    {
        return array_values(array_filter(
            array_map('intval', (array) ($filters['corporate_quote_ids'] ?? [])),
            fn (int $id): bool => $id > 0,
        ));
    }

    /**
     * @param  array{corporate_quote_ids?: list<int|string>|null, corporate_quote_data_ids?: list<int|string>|null}  $filters
     */
    private static function hasCorporateQuoteDataIdsFilter(array $filters): bool
    {
        return array_values(array_filter(
            array_map('intval', (array) ($filters['corporate_quote_data_ids'] ?? [])),
            fn (int $id): bool => $id > 0,
        )) !== [];
    }

    /**
     * @param  resource  $handle
     */
    private static function writeQuotePopulation($handle, CorporateQuote $quote): void
    {
        $individuals = self::orderedIndividuals($quote);

        if ($individuals->isNotEmpty()) {
            $individuals->each(function (CorporateQuoteData $record) use ($handle): void {
                fputcsv($handle, self::individualRow($record));
            });

            return;
        }

        $details = self::orderedDetails($quote);

        if ($details->isEmpty()) {
            fputcsv($handle, self::emptyQuoteRow($quote));

            return;
        }

        $details->each(function (DetailCorporateQuote $detail) use ($handle, $quote): void {
            fputcsv($handle, self::detailRow($quote, $detail));
        });
    }

    /**
     * @return Collection<int, CorporateQuoteData>
     */
    private static function orderedIndividuals(CorporateQuote $quote): Collection
    {
        return CorporateQuoteData::query()
            ->where('corporate_quote_id', $quote->id)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, DetailCorporateQuote>
     */
    private static function orderedDetails(CorporateQuote $quote): Collection
    {
        return DetailCorporateQuote::query()
            ->with([
                'plan:id,description',
                'ageRange:id,range',
                'coverage:id,price',
            ])
            ->where('corporate_quote_id', $quote->id)
            ->orderBy('plan_id')
            ->orderBy('age_range_id')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return list<string>
     */
    private static function individualRow(CorporateQuoteData $record): array
    {
        return [
            'Persona',
            (string) $record->id,
            (string) ($record->corporateQuote?->code ?? ''),
            (string) ($record->corporateQuote?->full_name ?? ''),
            (string) ($record->corporateQuote?->status ?? ''),
            '',
            '',
            '',
            '',
            '1',
            '',
            (string) ($record->first_name ?? ''),
            (string) ($record->last_name ?? ''),
            (string) ($record->nro_identificacion ?? ''),
            (string) ($record->phone ?? ''),
            (string) ($record->email ?? ''),
            (string) ($record->birth_date ?? ''),
            (string) ($record->age ?? ''),
            (string) ($record->sex ?? ''),
            (string) ($record->address ?? ''),
            (string) ($record->condition_medical ?? ''),
            (string) ($record->initial_date ?? ''),
            (string) ($record->position_company ?? ''),
            (string) ($record->full_name_emergency ?? ''),
            (string) ($record->phone_emergency ?? ''),
            (string) ($record->created_at ?? ''),
        ];
    }

    /**
     * @return list<string>
     */
    private static function detailRow(CorporateQuote $quote, DetailCorporateQuote $detail): array
    {
        return [
            'Rango etario',
            (string) $detail->id,
            (string) ($quote->code ?? ''),
            (string) ($quote->full_name ?? ''),
            (string) ($quote->status ?? ''),
            (string) ($detail->plan?->description ?? ''),
            (string) ($detail->ageRange?->range ?? ''),
            $detail->coverage?->price !== null ? (string) $detail->coverage->price : '',
            $detail->fee !== null ? (string) $detail->fee : '',
            (string) ($detail->total_persons ?? ''),
            $detail->subtotal_anual !== null ? (string) $detail->subtotal_anual : '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            (string) ($detail->created_at ?? ''),
        ];
    }

    /**
     * @return list<string>
     */
    private static function emptyQuoteRow(CorporateQuote $quote): array
    {
        return [
            'Sin población',
            (string) $quote->id,
            (string) ($quote->code ?? ''),
            (string) ($quote->full_name ?? ''),
            (string) ($quote->status ?? ''),
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            (string) ($quote->created_at ?? ''),
        ];
    }
}
