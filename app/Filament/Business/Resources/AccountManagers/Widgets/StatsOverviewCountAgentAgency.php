<?php

namespace App\Filament\Business\Resources\AccountManagers\Widgets;

use App\Models\AccountManager;
use App\Models\Agency;
use App\Models\Agent;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class StatsOverviewCountAgentAgency extends StatsOverviewWidget
{
    public ?Model $record = null;

    protected function getHeading(): ?string
    {
        return 'Indicadores de cartera';
    }

    protected function getDescription(): ?string
    {
        if (! $this->record instanceof AccountManager) {
            return 'Resumen de agencias y agentes asignados al ejecutivo.';
        }

        return sprintf(
            '%s · ID IntegraCorp %s',
            $this->record->full_name,
            $this->record->user_id
        );
    }

    protected function getStats(): array
    {
        if (! $this->record instanceof AccountManager) {
            return [];
        }

        $userId = $this->record->user_id;
        $agencias = Agency::query()->where('ownerAccountManagers', $userId)->count();
        $agentes = Agent::query()->where('ownerAccountManagers', $userId)->count();

        $agenciasCard = 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-info-200/60 dark:border-info-700/50 bg-gradient-to-br from-info-50/90 via-white to-info-50/50 dark:from-info-950/40 dark:via-gray-900/80 dark:to-info-900/20 hover:shadow-lg hover:shadow-info-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-info-400/50 hover:border-info-300 dark:hover:border-info-500';
        $agenciasLabel = 'text-info-600 dark:text-info-400';
        $agenciasBadge = 'bg-info-100/90 text-info-700 dark:bg-info-900/40 dark:text-info-300';

        $agentesCard = 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-primary-200/60 dark:border-primary-700/50 bg-gradient-to-br from-primary-50/90 via-white to-primary-50/50 dark:from-primary-950/40 dark:via-gray-900/80 dark:to-primary-900/20 hover:shadow-lg hover:shadow-primary-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-primary-400/50 hover:border-primary-300 dark:hover:border-primary-500';
        $agentesLabel = 'text-primary-600 dark:text-primary-400';
        $agentesBadge = 'bg-primary-100/90 text-primary-700 dark:bg-primary-900/40 dark:text-primary-300';

        return [
            Stat::make('AGENCIAS', (string) $agencias)
                ->icon('heroicon-m-building-office-2')
                ->description($this->statDescription(
                    'Estructuras comerciales vinculadas',
                    'Activas en cartera',
                    $agenciasLabel,
                    $agenciasBadge
                ))
                ->descriptionIcon('heroicon-m-map-pin')
                ->color('planIncial')
                ->extraAttributes([
                    'class' => $agenciasCard,
                    'style' => 'min-height: 130px;',
                ]),
            Stat::make('AGENTES', (string) $agentes)
                ->icon('heroicon-m-user-group')
                ->description($this->statDescription(
                    'Agentes bajo este account manager',
                    'Red asignada',
                    $agentesLabel,
                    $agentesBadge
                ))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('planIdeal')
                ->extraAttributes([
                    'class' => $agentesCard,
                    'style' => 'min-height: 130px;',
                ]),
        ];
    }

    private function statDescription(
        string $titulo,
        string $etiqueta,
        string $labelClass,
        string $badgeClass
    ): HtmlString {
        $html = <<<HTML
        <div class="flex flex-col mt-1">
            <span class="text-xs font-semibold uppercase tracking-wide {$labelClass}">
                {$titulo}
            </span>
            <div class="flex items-center gap-2.5 mt-1.5">
                <span class="px-2.5 py-1 text-xs font-bold rounded-lg {$badgeClass} shadow-sm">
                    {$etiqueta}
                </span>
            </div>
        </div>
        HTML;

        return new HtmlString($html);
    }
}
