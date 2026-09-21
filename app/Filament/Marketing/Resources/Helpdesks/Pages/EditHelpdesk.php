<?php

namespace App\Filament\Marketing\Resources\Helpdesks\Pages;

use App\Filament\Marketing\Resources\Helpdesks\Actions\HelpdeskTicketModalActions;
use App\Filament\Marketing\Resources\Helpdesks\HelpdeskResource;
use App\Models\HelpDesk;
use App\Models\User;
use App\Support\HelpdeskBusinessScrumWorkflow;
use App\Support\SecurityAudit;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

class EditHelpdesk extends EditRecord
{
    protected static string $resource = HelpdeskResource::class;

    protected bool $helpdeskWasResubmitted = false;

    public function getTitle(): string|Htmlable
    {
        $record = $this->getRecord();

        if (HelpdeskBusinessScrumWorkflow::ticketAllowsContentResubmit($record)) {
            return sprintf('Corregir y reenviar ticket #%s', $record->getKey());
        }

        return sprintf('Editar Help Desk #%s', $record->getKey());
    }

    /**
     * @var array<string, array{old:mixed,new:mixed}>
     */
    protected array $auditChanges = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->getRecord();
        $user = Auth::user();

        if ($user instanceof User && HelpdeskBusinessScrumWorkflow::canCreatorResubmit($record, $user)) {
            $this->helpdeskWasResubmitted = true;
            $prepared = HelpdeskBusinessScrumWorkflow::prepareCreatorResubmitData($record, $data, $user);
            $this->auditChanges = $this->diffAuditChanges($record, $prepared);

            return $prepared;
        }

        $fillable = $record->getFillable();
        $preserved = $record->only($fillable);

        $preserved['created_by'] = trim((string) ($data['created_by'] ?? $preserved['created_by'] ?? ''));
        $preserved['updated_by'] = Auth::user()->name;
        $this->auditChanges = $this->diffAuditChanges($record, $preserved);

        return $preserved;
    }

    protected function beforeSave(): void
    {
        if (! $this->helpdeskWasResubmitted) {
            return;
        }

        $error = HelpdeskBusinessScrumWorkflow::productOwnerReadinessError();
        if ($error === null) {
            return;
        }

        Notification::make()
            ->title('No se puede reenviar el ticket')
            ->body($error)
            ->icon('heroicon-m-no-symbol')
            ->iconColor('danger')
            ->danger()
            ->send();
        $this->halt();
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();
        $user = Auth::user();

        if ($this->helpdeskWasResubmitted && $user instanceof User) {
            HelpdeskBusinessScrumWorkflow::finalizeCreatorResubmit($record, $user, 'marketing');
        }

        SecurityAudit::log('AUDIT_HELPDESK_TICKET_UPDATED', 'marketing.helpdesks.edit', [
            'panel' => 'marketing',
            'helpdesk_id' => $record->getKey(),
            'updated_by' => Auth::user()->name,
            'changed_fields' => $this->auditChanges,
            'changed_fields_count' => count($this->auditChanges),
            'scrum_resubmitted' => $this->helpdeskWasResubmitted,
        ]);
    }

    protected function getSaveFormAction(): Action
    {
        $action = parent::getSaveFormAction();

        if (HelpdeskBusinessScrumWorkflow::ticketAllowsContentResubmit($this->getRecord())) {
            return $action->label(HelpdeskBusinessScrumWorkflow::creatorResubmitSaveLabel());
        }

        return $action;
    }

    protected function getSavedNotification(): ?Notification
    {
        if (! $this->helpdeskWasResubmitted) {
            return parent::getSavedNotification();
        }

        $record = $this->getRecord();

        return Notification::make()
            ->success()
            ->title('Ticket reenviado')
            ->body('El ticket #'.$record->getKey().' volvió al backlog de Becky Acosta para una nueva evaluación.');
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
            HelpdeskTicketModalActions::makeRevertToAnalystAction()
                ->record(fn (): HelpDesk => $this->getRecord())
                ->after(function (): void {
                    $this->getRecord()->refresh();
                }),
            HelpdeskTicketModalActions::makeAssignToSprintAction()
                ->record(fn (): HelpDesk => $this->getRecord())
                ->after(function (): void {
                    $this->getRecord()->refresh();
                }),
            HelpdeskTicketModalActions::makeUpdatePriorityAction()
                ->record(fn (): HelpDesk => $this->getRecord())
                ->after(function (): void {
                    $this->getRecord()->refresh();
                }),
            HelpdeskTicketModalActions::makeReassignAction()
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

    /**
     * @param  array<string, mixed>  $newValues
     * @return array<string, array{old:mixed,new:mixed}>
     */
    private function diffAuditChanges(HelpDesk $record, array $newValues): array
    {
        $changes = [];

        foreach ($newValues as $field => $newValue) {
            $oldValue = $record->getAttribute($field);

            if ($this->auditValuesAreEquivalent($oldValue, $newValue)) {
                continue;
            }

            $changes[(string) $field] = [
                'old' => $oldValue,
                'new' => $newValue,
            ];
        }

        return $changes;
    }

    private function auditValuesAreEquivalent(mixed $oldValue, mixed $newValue): bool
    {
        return $this->comparableAuditValue($oldValue) === $this->comparableAuditValue($newValue);
    }

    private function comparableAuditValue(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if (is_array($value) || is_object($value)) {
            $encoded = json_encode($value);

            return $encoded === false ? '' : $encoded;
        }

        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }
}
