<?php

declare(strict_types=1);

namespace App\Filament\Telemedicina\Resources\Helpdesks\Pages;

use App\Filament\Shared\Helpdesks\Actions\HelpdeskTicketModalActions;
use App\Filament\Telemedicina\Resources\Helpdesks\HelpdeskResource;
use App\Models\HelpDesk;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewHelpdesk extends ViewRecord
{
    protected static string $resource = HelpdeskResource::class;

    protected static ?string $title = 'Detalles del ticket';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Volver')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(HelpdeskResource::getUrl())
                ->extraAttributes(['class' => 'ticket-btn-ios-shell']),
            HelpdeskTicketModalActions::makeAddNoteAction(CreateHelpdesk::PANEL)
                ->record(fn (): HelpDesk => $this->getRecord())
                ->hidden(fn (): bool => HelpdeskTicketModalActions::shouldHideAddNoteAction($this->getRecord()))
                ->after(function (): void {
                    $this->getRecord()->refresh();
                })
                ->extraAttributes(['class' => 'ticket-btn-ios-shell']),
        ];
    }
}
