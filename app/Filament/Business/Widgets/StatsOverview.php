<?php

namespace App\Filament\Business\Widgets;

use App\Models\Affiliation;
use App\Models\CorporateQuote;
use App\Models\IndividualQuote;
use App\Models\AffiliationCorporate;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{

    protected ?string $heading = 'Totales de Ejecución de Servicios';

    protected ?string $description = 'Totales de cotizaciones y afiliaciones en tiempo real.';

    
    protected function getStats(): array
    {
        return [
            Stat::make('COTIZACIONES INDIVIDUALES', IndividualQuote::count())
                ->icon('fontisto-person')
                ->description('Incremento')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
            Stat::make('COTIZACIONES CORPORATIVAS', CorporateQuote::count())
                ->icon('fontisto-person')
                ->description('Incremento')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
            Stat::make('AFILIACIONES INDIVIDUALES', Affiliation::count())
                ->icon('fontisto-person')
                ->description('Incremento')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
            Stat::make('AFILIACIONES CORPORATIVAS', AffiliationCorporate::count())
                ->icon('fontisto-person')
                ->description('Incremento')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
        ];
    }
}