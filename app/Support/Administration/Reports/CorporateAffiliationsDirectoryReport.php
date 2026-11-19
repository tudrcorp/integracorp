<?php

declare(strict_types=1);

namespace App\Support\Administration\Reports;

use App\Models\AffiliationCorporate;
use Generator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Directorio de afiliaciones corporativas del Centro de reportes.
 *
 * Una fila por empresa afiliada, con la razón social, el RIF y la dirección
 * completa (calle, ciudad, estado y país). No usa periodo ni estatus de cobro:
 * incluye todas las afiliaciones que no están eliminadas.
 */
final class CorporateAffiliationsDirectoryReport
{
    public const COL_CODE = 0;

    public const COL_NAME = 1;

    public const COL_RIF = 2;

    public const COL_ADDRESS = 3;

    private const CHUNK_SIZE = 300;

    /**
     * @return list<string>
     */
    public static function headings(): array
    {
        return [
            'CODIGO',
            'RAZON SOCIAL',
            'RIF',
            'DIRECCION',
        ];
    }

    /**
     * @return list<int>
     */
    public static function columnWidths(): array
    {
        return [22, 42, 18, 64];
    }

    /**
     * @return Builder<AffiliationCorporate>
     */
    public static function query(): Builder
    {
        return AffiliationCorporate::query()
            ->select(['id', 'code', 'name_corporate', 'document_type_rif', 'rif', 'address', 'city_id', 'state_id', 'country_id'])
            ->with([
                'city:id,definition',
                'state:id,definition',
                'country:id,name',
            ])
            ->orderBy('name_corporate')
            ->orderBy('id');
    }

    public static function count(): int
    {
        return AffiliationCorporate::query()->count();
    }

    public static function build(CollectionReportFilters $filters, ?int $rowLimit = null): CollectionReportData
    {
        $query = self::query();

        if ($rowLimit !== null) {
            $query->limit($rowLimit);
        }

        return new CollectionReportData(
            title: $filters->type->label(),
            headings: self::headings(),
            lines: self::lines($query, $rowLimit),
            numericColumns: [],
            criteria: $filters->describe(),
            pdfColumns: [self::COL_CODE, self::COL_NAME, self::COL_RIF, self::COL_ADDRESS],
            moneyColumns: [],
            columnWidths: self::columnWidths(),
        );
    }

    /**
     * @param  Builder<AffiliationCorporate>  $query
     * @return Generator<int, array{kind: string, values: list<scalar|null>, label?: string}>
     */
    private static function lines(Builder $query, ?int $rowLimit): Generator
    {
        $count = 0;
        $records = $rowLimit !== null ? $query->get()->lazy() : $query->lazy(self::CHUNK_SIZE);

        foreach ($records as $record) {
            $count++;

            yield ['kind' => CollectionReportData::LINE_ROW, 'values' => self::row($record)];
        }

        yield [
            'kind' => CollectionReportData::LINE_TOTAL,
            'label' => 'TOTAL GENERAL: '.number_format($count, 0, ',', '.').' '.($count === 1 ? 'afiliación' : 'afiliaciones'),
            'values' => array_fill(0, count(self::headings()), null),
        ];
    }

    /**
     * @return list<scalar|null>
     */
    public static function row(AffiliationCorporate $record): array
    {
        return [
            self::text($record->code),
            self::text($record->name_corporate),
            self::rif($record),
            self::address($record),
        ];
    }

    public static function rif(AffiliationCorporate $record): ?string
    {
        $type = strtoupper(trim((string) $record->document_type_rif));
        $rif = strtoupper(trim((string) $record->rif));

        if ($type === '' && $rif === '') {
            return null;
        }

        if ($type === '' || str_starts_with($rif, $type)) {
            return $rif !== '' ? $rif : $type;
        }

        return $type.'-'.$rif;
    }

    public static function address(AffiliationCorporate $record): ?string
    {
        $parts = array_values(array_filter(
            [
                self::text($record->address),
                self::text($record->city?->definition),
                self::text($record->state?->definition),
                self::text($record->country?->name),
            ],
            static fn (?string $part): bool => $part !== null,
        ));

        return $parts === [] ? null : implode(', ', $parts);
    }

    private static function text(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
