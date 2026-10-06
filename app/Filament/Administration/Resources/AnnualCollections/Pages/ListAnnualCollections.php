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

        return SummaryCards::collapsible(label: 'Resumen de cuentas por cobrar', description: 'Total, vencido y por vencer. Use 30, 45 o 60 días para filtrar la tabla.', hint: 'Total por cobrar: '.SummaryCards::money($summary['pending_amount']), cards: [
            [
                'label' => 'Total por cobrar',
                'value' => SummaryCards::money($summary['pending_amount']),
                'detail' => SummaryCards::count($summary['pending_count'], 'cuota', 'cuotas').' · '.SummaryCards::count($summary['rows_count'], 'afiliación', 'afiliaciones'),
                'color' => SummaryCards::BLUE,
                'breakdown' => $this->dueWindowsBreakdown($summary['due_windows']),
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

    /**
     * Aplica (o quita, si ya está aplicado) el filtro «Vencimiento» de un plazo de
     * «Total por cobrar». Solo acepta los plazos del resumen.
     */
    public function filterByDueWindow(string $bucket): void
    {
        if (! in_array($bucket, CollectionReceivableReport::DUE_WINDOWS, true)) {
            return;
        }

        $value = $this->activeAgingFilter() === $bucket ? null : $bucket;

        $filters = $this->tableFilters ?? [];
        data_set($filters, 'aging.value', $value);
        $this->tableFilters = $filters;

        $this->updatedTableFilters();
    }

    /**
     * @param  array<int, array{count: int, amount: float}>  $dueWindows
     * @return list<array{label: string, value: string, detail: string, action: string, active: bool, title: string}>
     */
    private function dueWindowsBreakdown(array $dueWindows): array
    {
        $active = $this->activeAgingFilter();
        $items = [];

        foreach (CollectionReceivableReport::DUE_WINDOWS as $days => $bucket) {
            $window = $dueWindows[$days] ?? ['count' => 0, 'amount' => 0.0];
            $isActive = $active === $bucket;

            $items[] = [
                'label' => $days.' días',
                'value' => SummaryCards::money($window['amount']),
                'detail' => SummaryCards::count($window['count'], 'cuota', 'cuotas'),
                'action' => "filterByDueWindow('{$bucket}')",
                'active' => $isActive,
                'title' => $isActive
                    ? 'Quitar el filtro de los próximos '.$days.' días'
                    : 'Ver en la tabla lo que vence en los próximos '.$days.' días',
            ];
        }

        return $items;
    }

    private function activeAgingFilter(): ?string
    {
        $value = data_get($this->tableFilters, 'aging.value');

        return is_string($value) && $value !== '' ? $value : null;
    }
}
