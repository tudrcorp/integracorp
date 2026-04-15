<?php

namespace App\Filament\Business\Resources\Helpdesks\Pages;

use App\Filament\Business\Resources\Helpdesks\Actions\HelpdeskTicketModalActions;
use App\Filament\Business\Resources\Helpdesks\HelpdeskResource;
use App\Models\HelpDesk;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewHelpdesk extends ViewRecord
{
    protected static string $resource = HelpdeskResource::class;

    protected static ?string $title = 'Ver ticket';

    protected function getHeaderActions(): array
    {
        return [
            HelpdeskTicketModalActions::makeAddNoteAction()
                ->record(fn (): HelpDesk => $this->getRecord())
                ->after(function (): void {
                    $this->getRecord()->refresh();
                }),
            HelpdeskTicketModalActions::makeUpdateStatusAction()
                ->record(fn (): HelpDesk => $this->getRecord())
                ->after(function (): void {
                    $this->getRecord()->refresh();
                }),
            EditAction::make()
                ->visible(fn (): bool => HelpdeskResource::canEdit($this->getRecord())),
        ];
    }
}
