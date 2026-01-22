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

    protected ?string $heading = 'Dashboard de Indicadores de gestión';

    protected ?string $description = 'Vista consolidada del desempeño estratégico.';

    
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
                ->color('planIncial')
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:scale-[1.02] transition-all duration-300 rounded-xl border-b-4 border-[#9ce1ff] dark:border-[#9ce1ff]',
                    // 'style' => 'background: linear-gradient(135deg, var(--bg-start, #f0fdf4) 0%, var(--bg-end, #ffffff) 100%) !important;',
                ]),
            Stat::make('COTIZACIONES CORPORATIVAS', $corporateQuotes)
                ->icon('fontisto-person')
                ->description('Incremento')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('planIdeal')
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:scale-[1.02] transition-all duration-300 rounded-xl border-b-4 border-[#9ce1ff] dark:border-[#9ce1ff]',
                    // 'style' => 'background: linear-gradient(135deg, var(--bg-start, #f0fdf4) 0%, var(--bg-end, #ffffff) 100%) !important;',
                ]),
            Stat::make('AFILIACIONES INDIVIDUALES', $affiliations)
                ->icon('fontisto-person')
                ->description('Incremento')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('planEspecial')
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:scale-[1.02] transition-all duration-300 rounded-xl border-b-4 border-[#9ce1ff] dark:border-[#9ce1ff]',
                    // 'style' => 'background: linear-gradient(135deg, var(--bg-start, #f0fdf4) 0%, var(--bg-end, #ffffff) 100%) !important;',
                ]),
            Stat::make('AFILIACIONES CORPORATIVAS', $affiliationsCorporate)
                ->icon('fontisto-person')
                ->description('Incremento')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('planCorp')
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:scale-[1.02] transition-all duration-300 rounded-xl border-b-4 border-[#9ce1ff] dark:border-[#9ce1ff]',
                    // 'style' => 'background: linear-gradient(135deg, var(--bg-start, #f0fdf4) 0%, var(--bg-end, #ffffff) 100%) !important;',
                ]),
        ];
    }
}