<?php

namespace App\Support\Filament\Concerns;

use Filament\Facades\Filament;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Filament\View\PanelsRenderHook;

trait HasDownloadZoneSidebarLayout
{
    public string $documentsView = 'grid';

    public function setDocumentsView(string $view): void
    {
        if (! in_array($view, ['list', 'grid'], true)) {
            return;
        }

        $this->documentsView = $view;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Flex::make([
                    $this->getTabsContentComponent(),
                    EmbeddedTable::make()->grow(),
                ])
                    ->from('md')
                    ->verticallyAlignStart()
                    ->extraAttributes([
                        'class' => 'fi-download-zone-sidebar-layout',
                    ])
                    ->columnSpanFull(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
            ]);
    }

    public function getTabsContentComponent(): Component
    {
        $tabs = $this->getCachedTabs();

        return Tabs::make('Carpetas')
            ->livewireProperty('activeTab')
            ->contained(false)
            ->vertical()
            ->scrollable(false)
            ->extraAttributes([
                'class' => 'fi-download-zone-sidebar',
            ])
            ->tabs($tabs)
            ->hidden(empty($tabs))
            ->grow(false);
    }

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return [
            'fi-resource-list-records-page',
            'fi-resource-'.str_replace('/', '-', $this->getResource()::getSlug(Filament::getCurrentOrDefaultPanel())),
            'fi-download-zone-sidebar-page',
        ];
    }
}
