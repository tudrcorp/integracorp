<?php

namespace App\Filament\Business\Resources\Agents\Pages;

use App\Filament\Business\Resources\Agents\AgentResource;
use App\Models\Agent;
use App\Support\SecurityAudit;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditAgent extends EditRecord
{
    protected static string $resource = AgentResource::class;

    protected static ?string $title = 'Formularios de edición de agentes';

    /**
     * @var array<string, array{old:mixed,new:mixed}>
     */
    protected array $auditChanges = [];

    protected function mutateFormDataBeforeSave(array $data): array
    {
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
}
