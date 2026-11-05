<?php

namespace App\Filament\Administration\Resources\Collections\Pages;

use App\Filament\Administration\Pages\DashboardGestionCobranza;
use App\Filament\Administration\Resources\Collections\CollectionResource;
use App\Filament\Concerns\ReviewsTableSelection;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListCollections extends ListRecords
{
    use ReviewsTableSelection;

    protected static string $resource = CollectionResource::class;

    protected static ?string $title = 'Gestión de Cobranza';

    /**
     * «Ver KPIs» abre el Dashboard de Gestión de Cobranza, donde viven los indicadores que
     * antes iban sobre esta tabla.
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Action::make('kpis')
                ->label('Ver KPIs')
                ->icon('heroicon-o-presentation-chart-bar')
                ->color('gray')
                ->url(DashboardGestionCobranza::getUrl())
                ->visible(fn (): bool => DashboardGestionCobranza::canAccess()),
        ];
    }

    /**
     * @return array{singular: string, plural: string}
     */
    protected function selectionReviewNouns(): array
    {
        return ['singular' => 'cuota', 'plural' => 'cuotas'];
    }
}
