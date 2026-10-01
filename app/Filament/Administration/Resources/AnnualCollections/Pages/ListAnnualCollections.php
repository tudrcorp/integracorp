<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\AnnualCollections\Pages;

use App\Filament\Administration\Resources\AnnualCollections\AnnualCollectionResource;
use App\Support\Collections\CollectionReceivableReport;
use App\Support\Filament\SummaryCards;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListAnnualCollections extends ListRecords
{
    protected static string $resource = AnnualCollectionResource::class;

    protected static ?string $title = 'Cuentas por cobrar';

    /**
     * Resumen de la cobranza pendiente. Se calcula sobre la consulta filtrada de la
     * tabla, así que cambia con los filtros y la búsqueda.
     */
    public function getSubheading(): string|Htmlable|null
    {
        $summary = CollectionReceivableReport::summary($this->getFilteredTableQuery());

        return SummaryCards::render([
            [
                'label' => 'Total por cobrar',
                'value' => SummaryCards::money($summary['pending_amount']),
                'detail' => SummaryCards::count($summary['pending_count'], 'cuota', 'cuotas').' · '.SummaryCards::count($summary['rows_count'], 'afiliación', 'afiliaciones'),
                'color' => SummaryCards::BLUE,
            ],
            [
                'label' => 'Vencido',
                'value' => SummaryCards::money($summary['overdue_amount']),
                'detail' => SummaryCards::count($summary['overdue_count'], 'cuota', 'cuotas'),
                'color' => SummaryCards::RED,
            ],
            [
                'label' => 'Vence en 7 días',
                'value' => SummaryCards::money($summary['due_soon_amount']),
                'detail' => SummaryCards::count($summary['due_soon_count'], 'cuota', 'cuotas'),
                'color' => SummaryCards::AMBER,
            ],
        ]);
    }
}
