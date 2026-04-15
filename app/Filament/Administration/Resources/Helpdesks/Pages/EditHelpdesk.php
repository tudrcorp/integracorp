<?php

namespace App\Filament\Administration\Resources\Helpdesks\Pages;

use App\Filament\Administration\Resources\Helpdesks\Actions\HelpdeskTicketModalActions;
use App\Filament\Administration\Resources\Helpdesks\HelpdeskResource;
use App\Models\HelpDesk;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditHelpdesk extends EditRecord
{
    protected static string $resource = HelpdeskResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->getRecord();
        $fillable = $record->getFillable();
        $preserved = $record->only($fillable);

        $preserved['created_by'] = trim((string) ($data['created_by'] ?? $preserved['created_by'] ?? ''));
        $preserved['updated_by'] = Auth::user()->name;

        return $preserved;
    }

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
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
