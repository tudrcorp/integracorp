<?php

namespace App\Filament\Business\Resources\Helpdesks\Pages;

use App\Filament\Business\Resources\Helpdesks\HelpdeskResource;
use App\Filament\Business\Resources\Helpdesks\Widgets\HelpdeskStatusWeeklyChart;
use App\Filament\Business\Resources\Helpdesks\Widgets\StatsOverviewHelpdesk;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;

class ListHelpdesks extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = HelpdeskResource::class;

    protected static ?string $title = 'Gestión de Tickets';

    /**
     * Idéntico a Crear Ticket / Crear Nuevo Paciente: .ticket-btn-ios en theme.css (verde, sombras iOS, hover).
     */
    private const TICKET_BUTTON_CLASS = 'aviso-btn-ios-primary shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    /**
     * Mismo borde/sombra iOS del botón principal, sin pisar colores.
     */
    private const TOUR_BUTTON_CLASS = 'ticket-btn-ios-shell shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('helpdeskTour')
                ->label('Tutorial de uso')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->extraAttributes([
                    'id' => 'helpdesk-tour-btn',
                    'data-helpdesk-tour-trigger' => 'true',
                    'class' => self::TOUR_BUTTON_CLASS,
                ])
                ->action(fn (): null => null),
            CreateAction::make()
                ->label('Crear ticket de soporte')
                ->icon('heroicon-s-plus')
                ->color('primary')
                ->extraAttributes([
                    'id' => 'helpdesk-create-ticket-btn',
                    'data-tour-shape' => 'pill',
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
