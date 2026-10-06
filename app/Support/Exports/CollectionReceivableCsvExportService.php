<?php

declare(strict_types=1);

namespace App\Support\Exports;

use App\Models\Collection;
use App\Support\Collections\CollectionReceivableReport;
use App\Support\CsvExportStream;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV del reporte de cuentas por cobrar de «Cobranza Por Mes», con las columnas y
 * el orden del «Reporte global de cuentas por cobrar» (REPORTE CXC.xlsx): una fila
 * por afiliación con su próxima cuota pendiente.
 *
 * Se descarga directo, sin modal ni cola, a partir de la consulta de la tabla, así
 * que respeta filtros, búsqueda y orden. Recorre por lotes y escribe mientras lee.
 */
final class CollectionReceivableCsvExportService
{
    /**
     * @return list<string>
     */
    public static function headers(): array
    {
        return [
            'FECHA DE INCLUSION O EMISION',
            'AFILIADO O TITULAR',
            'C.I./R.I.F.',
            'TOMADOR',
            'C.I./R.I.F. TOMADOR',
            'TELEFONO',
            'EMAIL',
            'PLAN',
            'POBLACION',
            'AGENCIA',
            'AGENTE',
            'TARIFA ANUAL',
            'FECHA DE VIGENCIA',
            'FRACCIONAMIENTO DE CUOTAS PARA PAGO',
            'PERIODOS DE PAGO',
            'FECHA DE VENCIMIENTO',
            'ESTATUS DE COBRO',
            'DIAS',
            'ESTATUS DEL AFILIADO O TITULAR',
        ];
    }

    /**
     * @param  Builder<Collection>  $query  Consulta filtrada y ordenada de la tabla.
     */
    public function streamCsv(Builder $query): StreamedResponse
    {
        $query = (clone $query)->with(CollectionReceivableReport::eagerLoads());
        $filename = 'reporte_cxc_'.now()->format('Y-m-d_His').'.csv';

        return new StreamedResponse(function () use ($query): void {
            $handle = CsvExportStream::openOutput();

            if ($handle === false) {
                return;
            }

            fputcsv($handle, self::headers());

            // `lazy()` respeta el orden de la tabla (vencimiento); por lotes de 200.
            $query->lazy(200)->each(function (Collection $record) use ($handle): void {
                fputcsv($handle, self::row($record));
            });

            fclose($handle);
        }, 200, AffiliationCsvExportService::downloadHeaders($filename));
    }

    /**
     * @return list<string>
     */
    public static function row(Collection $record): array
    {
        $annualFee = CollectionReceivableReport::annualFee($record);
        $days = CollectionReceivableReport::daysCount($record);

        return [
            (string) ($record->include_date ?? ''),
            (string) ($record->affiliate_full_name ?? ''),
            (string) ($record->affiliate_ci_rif ?? ''),
            (string) (CollectionReceivableReport::payerName($record) ?? ''),
            (string) (CollectionReceivableReport::payerDocument($record) ?? ''),
            (string) ($record->affiliate_phone ?? ''),
            (string) ($record->affiliate_email ?? ''),
            (string) (CollectionReceivableReport::planLabel($record) ?? ''),
            (string) ($record->persons ?? ''),
            (string) (CollectionReceivableReport::agencyLabel($record) ?? ''),
            (string) ($record->agent?->name ?? ''),
            $annualFee !== null ? number_format($annualFee, 2, '.', '') : '',
            (string) (CollectionReceivableReport::effectiveDate($record) ?? ''),
            (string) (CollectionReceivableReport::paymentFrequency($record) ?? ''),
            (string) (CollectionReceivableReport::installmentLabel($record) ?? ''),
            CollectionReceivableReport::dueDate($record)?->format('d/m/Y') ?? '',
            CollectionReceivableReport::collectionStatus($record),
            $days !== null ? (string) $days : '',
            (string) (CollectionReceivableReport::affiliateStatus($record) ?? ''),
        ];
    }
}
