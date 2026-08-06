<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Sales\Widgets\Concerns;

use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;

trait HasCollapsibleSalesStatsPanel
{
    public bool $sectionExpanded = false;

    public function toggleSection(): void
    {
        $this->sectionExpanded = ! $this->sectionExpanded;

        if ($this->sectionExpanded) {
            $this->cachedStats = null;
        }
    }

    public function salesStatsPanelVariant(): string
    {
        return 'default';
    }

    public function salesStatsPanelIcon(): Heroicon
    {
        return Heroicon::OutlinedChartBarSquare;
    }

    public function getSectionContentComponent(): Component
    {
        if (! $this->sectionExpanded) {
            return Section::make()
                ->heading(null)
                ->schema([])
                ->contained(false);
        }

        return Section::make()
            ->heading(null)
            ->schema($this->getCachedStats())
            ->columns($this->getColumns())
            ->contained(false)
            ->gridContainer()
            ->extraAttributes([
                'class' => 'fi-admin-sales-stats-section-body',
            ]);
    }
}
