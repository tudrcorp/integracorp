<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Agencies\Pages;

use App\Filament\Business\Resources\Agencies\AgencyResource;
use App\Filament\Shared\CommercialStructure\Concerns\SyncsReferidorAssignments;
use App\Models\Agency;
use App\Support\Filament\CommercialStructurePageHeader;
use App\Support\Filament\FilamentIosButton;
use App\Support\SecurityAudit;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

class EditAgency extends EditRecord
{
    use SyncsReferidorAssignments;

    protected static string $resource = AgencyResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var Agency $agency */
        $agency = $this->getRecord();

        return CommercialStructurePageHeader::forAgency($agency, context: 'edit');
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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Volver')
                ->icon('heroicon-o-arrow-left')
                ->color('warning')
                ->url(AgencyResource::getUrl('view', ['record' => $this->getRecord()]))
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('warning'),
                ]),
        ];
    }
}
