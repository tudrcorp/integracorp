<?php

namespace App\Support\Filament\Concerns;

use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Tabs;

trait HasDownloadZoneTabsGridLayout
{
    public function getTabsContentComponent(): Component
    {
        $tabs = $this->getCachedTabs();

        return Tabs::make('Zona de descarga')
            ->livewireProperty('activeTab')
            ->contained(false)
            ->extraAttributes([
                'class' => 'fi-download-zone-tabs-grid',
            ])
            ->tabs($tabs)
            ->hidden(empty($tabs));
    }
}
