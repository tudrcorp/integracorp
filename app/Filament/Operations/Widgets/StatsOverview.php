<?php

namespace App\Filament\Operations\Widgets;

use App\Models\Affiliation;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class StatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 10;

    protected ?string $pollingInterval = '10s';

    protected function getStats(): array
    {
        $now = Carbon::now();
        $mesActualNombre = $now->translatedFormat('F');

        $totalAfiliados = Affiliation::query()
            ->where('status', 'ACTIVA')
            ->count();

        $totalAfiliadosMes = Affiliation::query()
            ->where('status', 'ACTIVA')
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->count();

        $affiliationsToday = Affiliation::query()
            ->where('status', 'ACTIVA')
            ->whereDate('created_at', $now->toDateString())
            ->with([
                'state:id,definition',
                'city:id,definition',
            ])
            ->get(['id', 'state_id_ti', 'city_id_ti']);

        $newAffiliationsToday = $affiliationsToday->count();
        $hasNewAffiliationsToday = $newAffiliationsToday > 0;

        $todayLocations = $affiliationsToday
            ->map(function (Affiliation $affiliation): string {
                $state = trim((string) ($affiliation->state?->definition ?? 'Estado por definir'));
                $city = trim((string) ($affiliation->city?->definition ?? 'Ciudad por definir'));

                return $state.' / '.$city;
            })
            ->filter()
            ->unique()
            ->values();

        $todayLocationsList = $todayLocations->take(3)->implode(' · ');
        $todayLocationsSuffix = $todayLocations->count() > 3 ? ' +'.($todayLocations->count() - 3).' mas' : '';
        $todayLocationsLabel = trim($todayLocationsList.$todayLocationsSuffix);

        $alertDescription = match (true) {
            $newAffiliationsToday === 1 => 'Nueva afiliacion hoy: '.$todayLocationsLabel,
            $newAffiliationsToday > 1 => $newAffiliationsToday.' nuevas hoy en '.$todayLocations->count().' ubicaciones: '.$todayLocationsLabel,
            default => 'Con Planes Individuales',
        };
        $alertDescription = Str::limit($alertDescription, 145);

        $cardAfiliados = 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-info-200/60 dark:border-info-700/50 bg-gradient-to-br from-info-50/90 via-white to-info-50/50 dark:from-info-950/40 dark:via-gray-900/80 dark:to-info-900/20 hover:shadow-lg hover:shadow-info-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-info-400/50 hover:border-info-300 dark:hover:border-info-500';
        $cardAfiliados .= $hasNewAffiliationsToday
            ? ' ring-2 ring-emerald-400/80 border-emerald-300/80 dark:border-emerald-500/60 shadow-[0_0_0_1px_rgba(16,185,129,0.38),0_18px_38px_-16px_rgba(16,185,129,0.6)] animate-pulse'
            : '';

        $baseDescription = addslashes('Con Planes Individuales');
        $monthDescription = addslashes('Nuevos en '.$mesActualNombre);
        $activeDescription = addslashes($alertDescription);
        $baseValue = addslashes($totalAfiliados.' Afiliados');
        $monthValue = addslashes($totalAfiliadosMes.' Afiliados');

        return [
            Stat::make('Total Afiliados Individuales', $totalAfiliados.' Afiliados')
                ->icon('heroicon-m-user-group')
                ->description($alertDescription)
                ->descriptionIcon($hasNewAffiliationsToday ? 'heroicon-m-bell-alert' : 'heroicon-m-arrow-trending-down')
                ->color('planIncial')
                ->extraAttributes([
                    'class' => $cardAfiliados,
                    'style' => 'min-height: 130px;',
                    'title' => $hasNewAffiliationsToday ? $alertDescription : 'Con Planes Individuales',
                    'x-data' => "{ label: '{$baseValue}', desc: '{$activeDescription}' }",
                    '@mouseenter' => "label = '{$monthValue}'; desc = '{$monthDescription}'",
                    '@mouseleave' => "label = '{$baseValue}'; desc = '{$activeDescription}'",
                ])
                ->value(new HtmlString("<span x-text='label'>{$totalAfiliados} Afiliados</span>"))
                ->description(new HtmlString("<span x-text='desc'>{$baseDescription}</span>")),
        ];
    }
}
