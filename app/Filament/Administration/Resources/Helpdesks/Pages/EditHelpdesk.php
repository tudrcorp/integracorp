<?php

namespace App\Filament\Administration\Resources\Helpdesks\Pages;

use App\Filament\Administration\Resources\Helpdesks\Actions\HelpdeskTicketModalActions;
use App\Filament\Administration\Resources\Helpdesks\HelpdeskResource;
use App\Models\HelpDesk;
use App\Support\SecurityAudit;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

class EditHelpdesk extends EditRecord
{
    protected static string $resource = HelpdeskResource::class;

    public function getTitle(): string|Htmlable
    {
        $record = $this->getRecord();

        return sprintf('Editar Help Desk #%s', $record->getKey());
    }

    /**
     * @var array<string, array{old:mixed,new:mixed}>
     */
    protected array $auditChanges = [];

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->getRecord();
        $fillable = $record->getFillable();
        $preserved = $record->only($fillable);

        $preserved['created_by'] = trim((string) ($data['created_by'] ?? $preserved['created_by'] ?? ''));
        $preserved['updated_by'] = Auth::user()->name;
        $changes = [];

        foreach ($preserved as $field => $newValue) {
            $oldValue = $record->getAttribute($field);

            if ((string) $oldValue === (string) $newValue) {
                continue;
            }

            $changes[(string) $field] = [
                'old' => $oldValue,
                'new' => $newValue,
            ];
        }
        $this->auditChanges = $changes;

        return $preserved;
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();

        SecurityAudit::log('AUDIT_HELPDESK_TICKET_UPDATED', 'administration.helpdesks.edit', [
            'panel' => 'administration',
            'helpdesk_id' => $record->getKey(),
            'updated_by' => Auth::user()->name,
            'changed_fields' => $this->auditChanges,
            'changed_fields_count' => count($this->auditChanges),
        ]);
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
            HelpdeskTicketModalActions::makeUpdatePriorityAction()
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
