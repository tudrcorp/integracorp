<?php

namespace App\Filament\Marketing\Widgets;

use App\Models\Affiliate;
use App\Models\AffiliateCorporate;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Redirect;

class StatsOverviewBusiness extends StatsOverviewWidget
{
    protected ?string $heading = 'DEL NEGOCIO';

    protected ?string $description = 'Resumen de Afiliaciones';
    protected function getStats(): array
    {
        return [
            Stat::make('AFILIACIONES INDIVIDUALES', Affiliation::count())
                ->description('Total de Afiliados a Planes Individuales: '.Affiliate::where('status', 'activo')->count(). ' personas')
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color('success')
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:scale-[1.02] transition-all duration-300 rounded-xl border-b-4 border-[#9ce1ff] dark:border-[#9ce1ff]',
                    'wire:click' => "\$dispatch('goToListAffiliations')",
                    // 'style' => 'background: linear-gradient(135deg, var(--bg-start, #f0fdf4) 0%, var(--bg-end, #ffffff) 100%) !important;',
                ]),
            Stat::make('CORPORATIVOS AFILIADOS', AffiliationCorporate::count())
                ->description('Total de Afiliados a Planes Corporativos: '. AffiliateCorporate::where('status', 'activo')->count(). ' personas')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('success')
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:scale-[1.02] transition-all duration-300 rounded-xl border-b-4 border-[#9ce1ff] dark:border-[#9ce1ff]',
                    'wire:click' => "\$dispatch('goToListAffiliationsCorporates')",
                    // 'style' => 'background: linear-gradient(135deg, var(--bg-start, #f0fdf4) 0%, var(--bg-end, #ffffff) 100%) !important;',
                ]),
        ];
    }

    public function getListeners()
    {
        return [
            'goToListAffiliations' => 'goToListAffiliations',
            'goToListAffiliationsCorporates' => 'goToListAffiliationsCorporates',
        ];
    }

    public function goToListAffiliations()
    {
        return Redirect::to('/marketing/affiliations');
    }

    public function goToListAffiliationsCorporates()
    {
        return Redirect::to('/marketing/affiliation-corporates');
    }
}
