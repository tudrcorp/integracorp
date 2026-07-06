<?php

namespace App\Filament\Marketing\Resources\BirthdayNotifications\Pages;

use App\Filament\Marketing\Resources\BirthdayNotifications\BirthdayNotificationResource;
use App\Filament\Marketing\Resources\BirthdayNotifications\Tables\BirthdayNotificationsTable;
use App\Models\BirthdayNotification;
use App\Support\BirthdayNotificationAudience;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListBirthdayNotifications extends ListRecords
{
    protected static string $resource = BirthdayNotificationResource::class;

    protected static ?string $title = 'Notificaciones de Cumpleaños';

    #[Url]
    public ?string $activeTab = null;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear Notificación')
                ->icon('heroicon-s-plus')
                ->url(fn (): string => BirthdayNotificationResource::getUrl('create', [
                    'audience' => $this->getActiveAudience(),
                ])),
        ];
    }

    public function table(Table $table): Table
    {
        return BirthdayNotificationsTable::configure($table)
            ->heading(fn (): string => BirthdayNotificationAudience::listHeadingFor($this->getActiveAudience()))
            ->description(fn (): string => BirthdayNotificationAudience::listDescriptionFor($this->getActiveAudience()));
    }

    protected function getActiveAudience(): string
    {
        $tab = $this->activeTab;

        if (filled($tab) && in_array($tab, BirthdayNotificationAudience::keys(), true)) {
            return $tab;
        }

        return array_key_first($this->getTabs()) ?? BirthdayNotificationAudience::AFFILIATES;
    }

    public function getTabsContentComponent(): Component
    {
        $tabs = $this->getCachedTabs();

        return Tabs::make('Filtrar por audiencia')
            ->livewireProperty('activeTab')
            ->contained(false)
            ->extraAttributes([
                'class' => 'fi-supplier-convenio-tabs-ios fi-supplier-status-tabs-ios',
            ])
            ->tabs($tabs)
            ->hidden(empty($tabs));
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            BirthdayNotificationAudience::AFFILIATES => Tab::make('Afiliados')
                ->badge((string) $this->countForAudience(BirthdayNotificationAudience::AFFILIATES))
                ->badgeColor('primary')
                ->extraAttributes([
                    'class' => 'fi-supplier-status-tab-pill',
                ])
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn(
                    'data_type',
                    BirthdayNotificationAudience::dataTypesFor(BirthdayNotificationAudience::AFFILIATES),
                )),
            BirthdayNotificationAudience::COLLABORATORS => Tab::make('Colaboradores')
                ->badge((string) $this->countForAudience(BirthdayNotificationAudience::COLLABORATORS))
                ->badgeColor('primary')
                ->extraAttributes([
                    'class' => 'fi-supplier-status-tab-pill',
                ])
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn(
                    'data_type',
                    BirthdayNotificationAudience::dataTypesFor(BirthdayNotificationAudience::COLLABORATORS),
                )),
        ];
    }

    protected function countForAudience(string $audience): int
    {
        return BirthdayNotification::query()
            ->whereIn('data_type', BirthdayNotificationAudience::dataTypesFor($audience))
            ->count();
    }
}
