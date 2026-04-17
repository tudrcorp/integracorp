<?php

namespace App\Filament\Business\Resources\Agencies\Pages;

use App\Filament\Business\Resources\Agencies\AgencyResource;
use App\Models\Agency;
use App\Support\SecurityAudit;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditAgency extends EditRecord
{
    protected static string $resource = AgencyResource::class;

    protected static ?string $title = 'Formularios de edición de agencias';

    /**
     * @var array<string, array{old:mixed,new:mixed}>
     */
    protected array $auditChanges = [];

    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var Agency $record */
        $record = $this->getRecord();
        $data['updated_by'] = Auth::user()->name;
        $trackedFields = [
            'name_corporative',
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
        /** @var Agency $record */
        $record = $this->getRecord();

        SecurityAudit::log('AUDIT_BUSINESS_AGENCY_UPDATED', 'business.agencies.edit', [
            'agency_id' => $record->id,
            'agency_code' => $record->code,
            'agency_name' => $record->name_corporative,
            'agency_email' => $record->email,
            'changed_fields' => $this->auditChanges,
            'changed_fields_count' => count($this->auditChanges),
        ]);
    }
}
