<?php

namespace App\Filament\Marketing\Resources\Helpdesks\Pages;

use App\Filament\Marketing\Resources\Helpdesks\HelpdeskResource;
use App\Filament\Marketing\Resources\Helpdesks\Widgets\HelpdeskStatusWeeklyChart;
use App\Filament\Marketing\Resources\Helpdesks\Widgets\StatsOverviewHelpdesk;
use Filament\Actions\CreateAction;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;

class ListHelpdesks extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = HelpdeskResource::class;

    protected static ?string $title = 'Gestión de Tickets';

    private const TICKET_BUTTON_CLASS = 'aviso-btn-ios-primary shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear Ticket')
                ->icon('heroicon-o-ticket')
                ->color('primary')
                ->extraAttributes([
                    'class' => self::TICKET_BUTTON_CLASS,
                ]),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            StatsOverviewHelpdesk::class,
            HelpdeskStatusWeeklyChart::class,
        ];
    }
}
