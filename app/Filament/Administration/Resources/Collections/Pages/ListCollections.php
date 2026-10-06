<?php

namespace App\Filament\Administration\Resources\Collections\Pages;

use App\Filament\Administration\Resources\Collections\CollectionResource;
use App\Support\Filament\SummaryCards;
use Carbon\CarbonImmutable;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListCollections extends ListRecords
{
    protected static string $resource = CollectionResource::class;

    protected static ?string $title = 'Gestión de Cobranza';

    /**
     * Resumen de las cuotas de la tabla. Se calcula en la base sobre la consulta
     * filtrada, así que cambia con los filtros y la búsqueda.
     */
    public function getSubheading(): string|Htmlable|null
    {
        $summary = $this->summary();

        return SummaryCards::collapsible(
            label: 'Resumen de cobranza',
            description: 'Por cobrar, lo que vence en 7 días, lo vencido y lo cobrado, según los filtros de la tabla.',
            hint: 'Por cobrar: '.SummaryCards::money($summary['pending_amount']),
            cards: [
                [
                    'label' => 'Por cobrar',
                    'value' => SummaryCards::money($summary['pending_amount']),
                    'detail' => SummaryCards::count($summary['pending_count'], 'cuota', 'cuotas'),
                    'color' => SummaryCards::BLUE,
                ],
                [
                    'label' => 'Vence en 7 días',
                    'value' => SummaryCards::money($summary['due_soon_amount']),
                    'detail' => SummaryCards::count($summary['due_soon_count'], 'cuota', 'cuotas'),
                    'color' => SummaryCards::AMBER,
                ],
                [
                    'label' => 'Vencidos',
                    'value' => SummaryCards::money($summary['overdue_amount']),
                    'detail' => SummaryCards::count($summary['overdue_count'], 'cuota por pagar con fecha pasada', 'cuotas por pagar con fecha pasada'),
                    'color' => SummaryCards::RED,
                ],
                [
                    'label' => 'Cobrado',
                    'value' => SummaryCards::money($summary['paid_amount']),
                    'detail' => SummaryCards::count($summary['paid_count'], 'cuota pagada', 'cuotas pagadas'),
                    'color' => SummaryCards::GREEN,
                ],
            ],
        );
    }

    /**
     * @return array{pending_count: int, pending_amount: float, due_soon_count: int, due_soon_amount: float, overdue_count: int, overdue_amount: float, paid_count: int, paid_amount: float}
     */
    private function summary(): array
    {
        $query = clone $this->getFilteredTableQuery();
        $query->reorder()->setEagerLoads([]);
        $query->getQuery()->columns = null;
        $query->getQuery()->limit = null;
        $query->getQuery()->offset = null;

        $todayDate = CarbonImmutable::today();
        $today = $todayDate->toDateString();
        $soon = $todayDate->addDays(7)->toDateString();

        $row = $query
            ->selectRaw("SUM(CASE WHEN status = 'POR PAGAR' THEN 1 ELSE 0 END) as pending_count")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'POR PAGAR' THEN total_amount ELSE 0 END), 0) as pending_amount")
            ->selectRaw("SUM(CASE WHEN status = 'POR PAGAR' AND filter_next_payment_date BETWEEN ? AND ? THEN 1 ELSE 0 END) as due_soon_count", [$today, $soon])
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'POR PAGAR' AND filter_next_payment_date BETWEEN ? AND ? THEN total_amount ELSE 0 END), 0) as due_soon_amount", [$today, $soon])
            ->selectRaw("SUM(CASE WHEN status = 'POR PAGAR' AND filter_next_payment_date < ? THEN 1 ELSE 0 END) as overdue_count", [$today])
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'POR PAGAR' AND filter_next_payment_date < ? THEN total_amount ELSE 0 END), 0) as overdue_amount", [$today])
            ->selectRaw("SUM(CASE WHEN status = 'PAGADO' THEN 1 ELSE 0 END) as paid_count")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'PAGADO' THEN total_amount ELSE 0 END), 0) as paid_amount")
            ->toBase()
            ->first();

        return [
            'pending_count' => (int) ($row->pending_count ?? 0),
            'pending_amount' => (float) ($row->pending_amount ?? 0),
            'due_soon_count' => (int) ($row->due_soon_count ?? 0),
            'due_soon_amount' => (float) ($row->due_soon_amount ?? 0),
            'overdue_count' => (int) ($row->overdue_count ?? 0),
            'overdue_amount' => (float) ($row->overdue_amount ?? 0),
            'paid_count' => (int) ($row->paid_count ?? 0),
            'paid_amount' => (float) ($row->paid_amount ?? 0),
        ];
    }
}
