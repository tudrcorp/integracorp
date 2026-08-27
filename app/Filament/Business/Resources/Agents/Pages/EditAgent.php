<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Agents\Pages;

use App\Filament\Business\Resources\Agents\AgentResource;
use App\Filament\Shared\CommercialStructure\Concerns\SyncsReferidorAssignments;
use App\Models\Agent;
use App\Support\Filament\CommercialStructurePageHeader;
use App\Support\Filament\FilamentIosButton;
use App\Support\SecurityAudit;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

class EditAgent extends EditRecord
{
    use SyncsReferidorAssignments;

    protected static string $resource = AgentResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var Agent $agent */
        $agent = $this->getRecord();

        return CommercialStructurePageHeader::forAgent($agent, context: 'edit');
    }

    /**
     * @var array<string, array{old:mixed,new:mixed}>
     */
    protected array $auditChanges = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->fillReferidorAssignmentState($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = $this->captureReferidorAssignments($data);

        /** @var Agent $record */
        $record = $this->getRecord();
        $data['updated_by'] = Auth::user()->name;

        if ($data['owner_code'] === null) {
            $data['owner_code'] = 'TDG-100';
        }
        if ($data['owner_code'] === '') {
            $data['owner_code'] = 'TDG-100';
        }

        $trackedFields = [
            'name',
            'email',
            'phone',
            'owner_code',
            'status',
            'commission_tdec',
            'commission_tdec_renewal',
            'commission_tdev',
            'commission_tdev_renewal',
            'is_referidor',
            'referidor_percentage',
            'ownerAccountManagers',
            'updated_by',
        ];
        $changes = [];

        foreach ($trackedFields as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $oldValue = $record->getAttribute($field);
            $newValue = $data[$field];

            if ((string) $oldValue === (string) $newValue) {
                continue;
            }

            $changes[$field] = [
                'old' => $oldValue,
                'new' => $newValue,
            ];
        }

        $this->auditChanges = $changes;

        return $data;
    }

    protected function afterSave(): void
    {
        $this->persistCapturedReferidorAssignments();

        /** @var Agent $record */
        $record = $this->getRecord();

        SecurityAudit::log('AUDIT_BUSINESS_AGENT_UPDATED', 'business.agents.edit', [
            'agent_id' => $record->id,
            'agent_name' => $record->name,
            'agent_email' => $record->email,
            'changed_fields' => $this->auditChanges,
            'changed_fields_count' => count($this->auditChanges),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Volver')
                ->icon('heroicon-o-arrow-left')
                ->color('warning')
                ->url(AgentResource::getUrl('view', ['record' => $this->getRecord()]))
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('warning'),
                ]),
        ];
    }
}
