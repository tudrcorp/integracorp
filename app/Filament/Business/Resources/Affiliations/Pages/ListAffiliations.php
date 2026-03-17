<?php

namespace App\Filament\Business\Resources\Affiliations\Pages;

use App\Filament\Business\Resources\Affiliations\AffiliationResource;
use App\Filament\Business\Resources\Affiliations\Widgets\AffiliationChart;
use App\Filament\Business\Resources\Affiliations\Widgets\AffiliationPlanChart;
use App\Filament\Business\Resources\Affiliations\Widgets\AffiliationSupplierChart;
use App\Filament\Business\Resources\Affiliations\Widgets\StatsOverview;
use App\Filament\Business\Resources\Affiliations\Widgets\StatsOverviewPlan;
use App\Filament\Business\Resources\Affiliations\Widgets\TotalAfiliacionesPorEstado;
use Filament\Actions\Action;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Livewire\Attributes\On;

class ListAffiliations extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = AffiliationResource::class;

    protected static ?string $title = 'Afiliaciones Individuales';

    public function mount(): void
    {
        parent::mount();
        $this->dispatch('open-welcome-modal');
    }

    #[On('open-welcome-modal')]
    public function openWelcomeModal(): void
    {
        $this->mountAction('bienvenida');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('bienvenida')
                ->label('Bienvenida')
                ->extraAttributes(['class' => 'hidden'])
                ->modalWidth(Width::Large)
                ->modalHeading(false)
                ->modalDescription(false)
                ->modalContent(fn () => view('filament.business.affiliations.welcome-modal'))
                ->modalSubmitAction(false)
                ->modalCancelAction(false)
                ->closeModalByEscaping()
                ->closeModalByClickingAway()
                ->action(fn () => null),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            StatsOverview::class,
            StatsOverviewPlan::class,

            // ...
            AffiliationChart::class,
            TotalAfiliacionesPorEstado::class,

            // ...
            AffiliationPlanChart::class,
            AffiliationSupplierChart::class,
        ];
    }
}
