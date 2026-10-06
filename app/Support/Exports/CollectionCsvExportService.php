<?php

declare(strict_types=1);

namespace App\Support\Exports;

use App\Models\Collection;
use App\Support\Collections\CollectionDueDate;
use App\Support\CsvExportStream;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV de cuotas de «Gestión de Cobranza»: una fila por cuota, con el mismo estado
 * (VENCIDO incluido) y días que muestra la tabla ({@see CollectionDueDate}).
 *
 * Se descarga directo, sin modal ni cola: recorre las cuotas por lotes
 * (`lazyById`) y escribe mientras lee, así que no carga todo en memoria.
 */
final class CollectionCsvExportService
{
    /**
     * @return list<string>
     */
    public static function headers(): array
    {
        return [
            'Nro. de aviso',
            'Fecha de emisión',
            'Afiliado o titular',
            'C.I./R.I.F.',
            'Afiliación',
            'Tipo',
            'Agencia',
            'Agente',
            'Plan',
            'Frecuencia de pago',
            'Cobertura (USD)',
            'Población',
            'Monto (USD)',
            'Próximo pago',
            'Estado',
            'Días',
            'Teléfono',
            'Correo',
            'Persona de contacto',
            'Estatus afiliación',
            'Cotización',
            'Método de pago',
            'Referencia',
        ];
    }

    /**
     * @param  iterable<int|string>  $collectionIds
     */
    public function streamCsv(iterable $collectionIds): StreamedResponse
    {
        $ids = collect($collectionIds)
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        $filename = 'cuotas_cobranza_'.now()->format('Y-m-d_His').'.csv';

        return new StreamedResponse(function () use ($ids): void {
            $handle = CsvExportStream::openOutput();

            if ($handle === false) {
                return;
            }

            fputcsv($handle, self::headers());

            foreach (array_chunk($ids, 1000) as $chunk) {
                Collection::query()
                    ->with(['plan:id,description', 'agent:id,name', 'coverage:id,price'])
                    ->whereKey($chunk)
                    ->lazyById(200)
                    ->each(function (Collection $record) use ($handle): void {
                        fputcsv($handle, self::row($record));
                    });
            }

            fclose($handle);
        }, 200, AffiliationCsvExportService::downloadHeaders($filename));
    }

    /**
     * @return list<string>
     */
    public static function row(Collection $record): array
    {
        $amount = is_numeric($record->total_amount) ? number_format((float) $record->total_amount, 2, '.', '') : '';
        $coverage = is_numeric($record->coverage?->price) ? number_format((float) $record->coverage->price, 2, '.', '') : '';

        return [
            (string) ($record->collection_invoice_number ?? ''),
            (string) ($record->include_date ?? ''),
            (string) ($record->affiliate_full_name ?? ''),
            (string) ($record->affiliate_ci_rif ?? ''),
            (string) ($record->affiliation_code ?? ''),
            str_contains(Str::upper(Str::ascii((string) $record->type)), 'CORPORATIVA') ? 'CORPORATIVA' : 'INDIVIDUAL',
            (string) ($record->code_agency ?? ''),
            (string) ($record->agent?->name ?? ''),
            (string) ($record->plan?->description ?? ''),
            (string) ($record->payment_frequency ?? ''),
            $coverage,
            (string) ($record->persons ?? ''),
            $amount,
            CollectionDueDate::of($record)?->format('d/m/Y') ?? '',
            CollectionDueDate::displayStatus($record),
            CollectionDueDate::daysLabel($record) ?? '',
            (string) ($record->affiliate_phone ?? ''),
            (string) ($record->affiliate_email ?? ''),
            (string) ($record->affiliate_contact ?? ''),
            (string) ($record->affiliate_status ?? ''),
            (string) ($record->quote_number ?? ''),
            (string) ($record->payment_method ?? ''),
            (string) ($record->reference ?? ''),
        ];
    }
}
