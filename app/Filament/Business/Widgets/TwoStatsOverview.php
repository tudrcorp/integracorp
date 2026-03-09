<?php

namespace App\Filament\Business\Widgets;

use App\Models\Agency;
use App\Models\Agent;
use App\Models\User;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class TwoStatsOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'Estructura Cuantificadas';

    protected ?string $description = 'Total de registros y nuevos registros en el mes en curso.';

    protected function getStats(): array
    {
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();
        $nombreMes = ucfirst($now->translatedFormat('F'));

        $scope = fn ($q) => Auth::user()->is_accountManagers == 1
            ? $q->where('ownerAccountManagers', Auth::user()->id)
            : $q;

        $agenciesMaster = $scope(Agency::query()->where('agency_type_id', 1))->count();
        $agenciesMasterMes = (clone $scope(Agency::query()->where('agency_type_id', 1)))->whereBetween('created_at', [$startOfMonth, $endOfMonth])->count();
        $agenciesGeneral = $scope(Agency::query()->where('agency_type_id', 3))->count();
        $agenciesGeneralMes = (clone $scope(Agency::query()->where('agency_type_id', 3)))->whereBetween('created_at', [$startOfMonth, $endOfMonth])->count();
        $agents = $scope(Agent::query())->count();
        $agentsMes = (clone $scope(Agent::query()))->whereBetween('created_at', [$startOfMonth, $endOfMonth])->count();

        $statConfigs = [
            [
                'label' => 'AGENCIAS MASTER',
                'value' => $agenciesMaster,
                'mes' => $agenciesMasterMes,
                'color' => 'planIncial',
                'cardClass' => 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-info-200/60 dark:border-info-700/50 bg-gradient-to-br from-info-50/90 via-white to-info-50/50 dark:from-info-950/40 dark:via-gray-900/80 dark:to-info-900/20 hover:shadow-lg hover:shadow-info-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-info-400/50 hover:border-info-300 dark:hover:border-info-500',
                'labelClass' => 'text-info-600 dark:text-info-400',
                'badgeClass' => 'bg-info-100/90 text-info-700 dark:bg-info-900/40 dark:text-info-300',
            ],
            [
                'label' => 'AGENCIAS GENERALES',
                'value' => $agenciesGeneral,
                'mes' => $agenciesGeneralMes,
                'color' => 'planIdeal',
                'cardClass' => 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-primary-200/60 dark:border-primary-700/50 bg-gradient-to-br from-primary-50/90 via-white to-primary-50/50 dark:from-primary-950/40 dark:via-gray-900/80 dark:to-primary-900/20 hover:shadow-lg hover:shadow-primary-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-primary-400/50 hover:border-primary-300 dark:hover:border-primary-500',
                'labelClass' => 'text-primary-600 dark:text-primary-400',
                'badgeClass' => 'bg-primary-100/90 text-primary-700 dark:bg-primary-900/40 dark:text-primary-300',
            ],
            [
                'label' => 'AGENTES',
                'value' => $agents,
                'mes' => $agentsMes,
                'color' => 'planEspecial',
                'cardClass' => 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-warning-200/60 dark:border-warning-700/50 bg-gradient-to-br from-warning-50/90 via-white to-warning-50/50 dark:from-warning-950/40 dark:via-gray-900/80 dark:to-warning-900/20 hover:shadow-lg hover:shadow-warning-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-warning-400/50 hover:border-warning-300 dark:hover:border-warning-500',
                'labelClass' => 'text-warning-600 dark:text-warning-400',
                'badgeClass' => 'bg-warning-100/90 text-warning-700 dark:bg-warning-900/40 dark:text-warning-300',
            ],
        ];

        $stats = [];
        foreach ($statConfigs as $config) {
            $stats[] = Stat::make($config['label'], (string) $config['value'])
                ->icon('fontisto-person')
                ->description(self::descriptionHtml($config['value'], $config['mes'], $nombreMes, $config['labelClass'], $config['badgeClass']))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color($config['color'])
                ->extraAttributes([
                    'class' => $config['cardClass'],
                    'style' => 'min-height: 130px;',
                ]);
        }

        if (Auth::user()->is_business_admin == 1) {
            $accountManagers = User::query()->where('is_accountManagers', 1)->count();
            $accountManagersMes = User::query()->where('is_accountManagers', 1)->whereBetween('created_at', [$startOfMonth, $endOfMonth])->count();
            $stats[] = Stat::make('ACCOUNT MANAGERS', (string) $accountManagers)
                ->icon('fontisto-person')
                ->description(self::descriptionHtml($accountManagers, $accountManagersMes, $nombreMes, 'text-success-600 dark:text-success-400', 'bg-success-100/90 text-success-700 dark:bg-success-900/40 dark:text-success-300'))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('planCorp')
                ->extraAttributes([
                    'class' => 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-success-200/60 dark:border-success-700/50 bg-gradient-to-br from-success-50/90 via-white to-success-50/50 dark:from-success-950/40 dark:via-gray-900/80 dark:to-success-900/20 hover:shadow-lg hover:shadow-success-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-success-400/50 hover:border-success-300 dark:hover:border-success-500',
                    'style' => 'min-height: 130px;',
                ]);
        }

        return $stats;
    }

    protected static function descriptionHtml(int $totalRegistros, int $nuevosMes, string $nombreMes, string $labelClass, string $badgeClass): HtmlString
    {
        $html = <<<HTML
        <div class="flex flex-col mt-1">
            <span class="text-xs font-semibold uppercase tracking-wide {$labelClass}">
                Total registros
            </span>
            <div class="flex items-center gap-2.5 mt-1.5">
                <span class="px-2.5 py-1 text-xs font-bold rounded-lg {$badgeClass} shadow-sm">
                    Mes actual ({$nombreMes}):
                </span>
                <span class="text-sm font-bold text-gray-900 dark:text-white">
                    {$nuevosMes}
                </span>
            </div>
        </div>
        HTML;

        return new HtmlString($html);
    }
}
