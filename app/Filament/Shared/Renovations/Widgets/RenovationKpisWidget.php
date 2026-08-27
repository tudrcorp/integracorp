<?php

declare(strict_types=1);

namespace App\Filament\Shared\Renovations\Widgets;

use App\Support\Renovations\RenovationKpiCalculator;
use App\Support\Renovations\RenovationKpiSnapshot;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

abstract class RenovationKpisWidget extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    /**
     * @var int | string | array<string, int | string | null>
     */
    protected int|string|array $columnSpan = 'full';

    abstract protected function isCorporate(): bool;

    private function scopeHeading(string $section): string
    {
        $scope = $this->isCorporate() ? 'Renovaciones corporativas' : 'Renovaciones individuales';

        return $scope.' · '.$section;
    }

    public function content(Schema $schema): Schema
    {
        $snapshot = $this->snapshot();

        return $schema
            ->components([
                $this->retentionSection($snapshot),
                $this->efficiencySection($snapshot),
            ]);
    }

    protected function snapshot(): RenovationKpiSnapshot
    {
        return $this->isCorporate()
            ? RenovationKpiCalculator::corporate()
            : RenovationKpiCalculator::individual();
    }

    private function retentionSection(RenovationKpiSnapshot $snapshot): Component
    {
        return Section::make($this->scopeHeading('Retención y negocio'))
            ->description('Aceptadas en '.$snapshot->periodLabel.' · éxito = aceptación en Filament. Retención = aceptadas / (aceptadas + con retraso). Las que aún están en plazo no entran.')
            ->icon(Heroicon::OutlinedArrowPath)
            ->collapsible()
            ->collapsed()
            ->schema($this->retentionStats($snapshot))
            ->columns([
                'default' => 1,
                'md' => 2,
                'xl' => 4,
            ])
            ->columnSpanFull();
    }

    private function efficiencySection(RenovationKpiSnapshot $snapshot): Component
    {
        $components = $this->efficiencyStats($snapshot);

        if ($snapshot->acceptors !== []) {
            $components[] = View::make('filament.shared.renovations.kpi-acceptors-table')
                ->viewData([
                    'acceptors' => $snapshot->acceptors,
                    'unitLabel' => $snapshot->unitLabel(),
                ])
                ->columnSpanFull();
        }

        return Section::make($this->scopeHeading('Eficiencia operativa'))
            ->description('Anticipación al aceptar (días que faltaban al vencimiento) y cierres por empleado en '.$snapshot->periodLabel.'. Banda de referencia: 30 a 60 días.')
            ->icon(Heroicon::OutlinedClock)
            ->collapsible()
            ->collapsed()
            ->schema($components)
            ->columns([
                'default' => 1,
                'md' => 3,
            ])
            ->columnSpanFull();
    }

    /**
     * @return list<Stat>
     */
    private function retentionStats(RenovationKpiSnapshot $snapshot): array
    {
        $retentionColor = $snapshot->retentionRate === null
            ? 'gray'
            : ($snapshot->retentionRate >= 0.5 ? 'success' : 'warning');
        $churnColor = $snapshot->churnRate === null
            ? 'gray'
            : ($snapshot->churnRate > 0.5 ? 'danger' : 'warning');

        return [
            Stat::make($snapshot->acceptedLabel(), $snapshot->formattedAcceptedCount())
                ->icon(Heroicon::CheckBadge)
                ->description('Aceptadas en '.$snapshot->periodLabel)
                ->color('success'),
            Stat::make('Tasa de retención', $snapshot->formattedRetention())
                ->icon(Heroicon::ArrowPath)
                ->description('Aceptadas / (aceptadas + con retraso)')
                ->color($retentionColor),
            Stat::make('Tasa de abandono', $snapshot->formattedChurn())
                ->icon(Heroicon::ArrowTrendingDown)
                ->description('Con retraso / (aceptadas + con retraso)')
                ->color($churnColor),
            Stat::make('Prima retenida', $snapshot->formattedPremium())
                ->icon(Heroicon::Banknotes)
                ->description('Prima anual de las aceptadas')
                ->color('success'),
        ];
    }

    /**
     * @return list<Stat>
     */
    private function efficiencyStats(RenovationKpiSnapshot $snapshot): array
    {
        $anticipationColor = 'gray';

        if ($snapshot->avgAnticipationDays !== null) {
            $days = $snapshot->avgAnticipationDays;
            $anticipationColor = ($days >= 30 && $days <= 60) ? 'success' : 'warning';
        }

        return [
            Stat::make('Anticipación al aceptar', $snapshot->formattedAnticipation())
                ->icon(Heroicon::Clock)
                ->description('Días que faltaban al vencimiento cuando se aceptó')
                ->color($anticipationColor),
            Stat::make('En plazo (cola)', $snapshot->formattedInWindow())
                ->icon(Heroicon::QueueList)
                ->description('Período de renovación, aún no vencidas')
                ->color('info'),
            Stat::make('Con retraso (cola)', $snapshot->formattedOverdue())
                ->icon(Heroicon::ExclamationTriangle)
                ->description('Vencidas sin aceptar al corte')
                ->color($snapshot->overdueOpenCount > 0 ? 'danger' : 'gray'),
        ];
    }

    protected function getStats(): array
    {
        return [];
    }
}
