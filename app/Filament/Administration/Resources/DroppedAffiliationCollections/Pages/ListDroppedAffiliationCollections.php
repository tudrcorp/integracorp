<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\DroppedAffiliationCollections\Pages;

use App\Enums\AdministrationReportType;
use App\Filament\Administration\Pages\CentroDeReportes;
use App\Filament\Administration\Resources\DroppedAffiliationCollections\DroppedAffiliationCollectionResource;
use App\Models\CollectionDropoutReview;
use App\Support\Collections\DroppedAffiliationCollections;
use App\Support\Filament\SummaryCards;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class ListDroppedAffiliationCollections extends ListRecords
{
    protected static string $resource = DroppedAffiliationCollectionResource::class;

    protected static ?string $title = 'Cuotas de afiliaciones dadas de baja';

    /**
     * Resumen de la bandeja: por decidir, mantenidas como deuda y anuladas que todavía se
     * pueden revertir.
     */
    public function getSubheading(): string|Htmlable|null
    {
        $summary = $this->summary();

        return SummaryCards::collapsible(
            label: 'Resumen de cuotas de afiliaciones dadas de baja',
            description: 'Estudie el reporte y decida aquí: anular la cuota o mantenerla como deuda. Toda decisión lleva nota y se puede revertir.',
            icon: 'heroicon-o-user-minus',
            hint: 'Por decidir: '.SummaryCards::money($summary['undecided_amount']),
            cards: [
                [
                    'label' => 'Por decidir',
                    'value' => SummaryCards::money($summary['undecided_amount']),
                    'detail' => SummaryCards::count($summary['undecided_count'], 'cuota', 'cuotas').' · '.SummaryCards::count($summary['undecided_overdue'], 'vencida', 'vencidas'),
                    'color' => SummaryCards::AMBER,
                ],
                [
                    'label' => 'Mantenidas como deuda',
                    'value' => SummaryCards::money($summary['kept_amount']),
                    'detail' => SummaryCards::count($summary['kept_count'], 'cuota', 'cuotas'),
                    'color' => SummaryCards::BLUE,
                ],
                [
                    'label' => 'Anuladas (reversibles)',
                    'value' => SummaryCards::money($summary['cancelled_amount']),
                    'detail' => SummaryCards::count($summary['cancelled_count'], 'cuota', 'cuotas'),
                    'color' => SummaryCards::RED,
                ],
            ],
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('report')
                ->label('Descargar reporte')
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->color('gray')
                ->url(fn (): string => CentroDeReportes::getUrl(['reporte' => AdministrationReportType::DroppedAffiliationCollections->value])),
        ];
    }

    /**
     * @return array{undecided_count: int, undecided_amount: float, undecided_overdue: int, kept_count: int, kept_amount: float, cancelled_count: int, cancelled_amount: float}
     */
    private function summary(): array
    {
        // Totales de toda la bandeja, no de la vista: el filtro «Revisión» dejaría en cero las otras tarjetas.
        $base = DroppedAffiliationCollections::reviewQuery();

        $bucket = function (string $review) use ($base): array {
            $query = DroppedAffiliationCollections::applyReviewFilter(clone $base, $review);

            return [
                (clone $query)->count(),
                round((float) (clone $query)->sum('collections.total_amount'), 2),
                $review === DroppedAffiliationCollections::REVIEW_UNDECIDED
                    ? (clone $query)->whereDate('collections.filter_next_payment_date', '<', CarbonImmutable::today()->toDateString())->count()
                    : 0,
            ];
        };

        [$undecidedCount, $undecidedAmount, $undecidedOverdue] = $bucket(DroppedAffiliationCollections::REVIEW_UNDECIDED);
        [$keptCount, $keptAmount] = $bucket(CollectionDropoutReview::DECISION_KEPT);
        [$cancelledCount, $cancelledAmount] = $bucket(CollectionDropoutReview::DECISION_CANCELLED);

        return [
            'undecided_count' => $undecidedCount,
            'undecided_amount' => $undecidedAmount,
            'undecided_overdue' => $undecidedOverdue,
            'kept_count' => $keptCount,
            'kept_amount' => $keptAmount,
            'cancelled_count' => $cancelledCount,
            'cancelled_amount' => $cancelledAmount,
        ];
    }
}
