<?php

namespace App\Filament\Business\Widgets;

use App\Models\Affiliation;
use App\Models\CorporateQuote;
use App\Models\IndividualQuote;
use App\Models\AffiliationCorporate;
use Illuminate\Support\Facades\Auth;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{

    protected ?string $heading = 'Totales de Ejecución de Serviciossssss';

    protected ?string $description = 'Totales de cotizaciones y afiliaciones en tiempo real.';

    
    protected function getStats(): array
    {
        //Si la session en de un administrador de cuentas
        //los stats muestran las estadisticas del administrador, sino muestras las estadisticas del generales
        if(Auth::user()->is_accountManagers == 1) {
            $individualQuotes       = IndividualQuote::where('ownerAccountManagers', Auth::user()->id)->count();
            $corporateQuotes        = CorporateQuote::where('ownerAccountManagers', Auth::user()->id)->count();
            $affiliations           = Affiliation::where('ownerAccountManagers', Auth::user()->id)->count();
            $affiliationsCorporate  = AffiliationCorporate::where('ownerAccountManagers', Auth::user()->id)->count();
        }else{
            $individualQuotes       = IndividualQuote::count();
            $corporateQuotes        = CorporateQuote::count();
            $affiliations           = Affiliation::count();
            $affiliationsCorporate  = AffiliationCorporate::count();
        }
        
        return [
            Stat::make('COTIZACIONES INDIVIDUALES', $individualQuotes)
                ->icon('fontisto-person')
                ->description('Incremento')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->extraAttributes([
                    'class' => 'border-2 border-[#00a7d1] font-bold text-white',
                ]),
            Stat::make('COTIZACIONES CORPORATIVAS', $corporateQuotes)
                ->icon('fontisto-person')
                ->description('Incremento')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->extraAttributes([
                    'class' => 'border-2 border-[#00a7d1] font-bold text-white',
                ]),
            Stat::make('AFILIACIONES INDIVIDUALES', $affiliations)
                ->icon('fontisto-person')
                ->description('Incremento')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->extraAttributes([
                    'class' => 'border-2 border-[#00a7d1] font-bold text-white',
                ]),
            Stat::make('AFILIACIONES CORPORATIVAS', $affiliationsCorporate)
                ->icon('fontisto-person')
                ->description('Incremento')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->extraAttributes([
                    'class' => 'border-2 border-[#00a7d1] font-bold text-white',
                ]),
        ];
    }
}