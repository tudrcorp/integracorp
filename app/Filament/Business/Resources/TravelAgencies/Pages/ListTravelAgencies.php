<?php

namespace App\Filament\Business\Resources\TravelAgencies\Pages;

use App\Filament\Business\Resources\TravelAgencies\TravelAgencyResource;
use App\Filament\Business\Resources\TravelAgencies\Widgets\TotalTravelAgencyStatOverview;
use App\Filament\Business\Resources\TravelAgencies\Widgets\TravelAgencyForStateChart;
use Filament\Actions\CreateAction;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class ListTravelAgencies extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = TravelAgencyResource::class;

    public function getTitle(): string|Htmlable
    {
        return new HtmlString(
            <<<'HTML'
            <div class="flex flex-col items-start gap-3 py-1">
                <img src="/image/logo-tdev.png" alt="TDEV" class="h-20 w-auto max-w-[14rem] object-contain drop-shadow-md sm:h-24 sm:max-w-[16rem]">
                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase tracking-[0.28em] text-cyan-700 dark:text-cyan-300">
                        Tu Doctor En Viajes
                    </p>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white sm:text-3xl">
                        Agencias de viajes
                    </h1>
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                        Estructura comercial · agencias, asociadas y agentes
                    </p>
                </div>
            </div>
            HTML
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-plus-circle')
                ->color('primary')
                ->label('Crear Agencia de Viajes'),

        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            TotalTravelAgencyStatOverview::class,
            TravelAgencyForStateChart::class,
        ];
    }
}
