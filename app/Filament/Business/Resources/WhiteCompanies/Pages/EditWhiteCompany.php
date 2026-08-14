<?php

namespace App\Filament\Business\Resources\WhiteCompanies\Pages;

use App\Filament\Business\Resources\WhiteCompanies\Schemas\WhiteCompanyDocumentBrandForm;
use App\Filament\Business\Resources\WhiteCompanies\WhiteCompanyResource;
use App\Models\WhiteCompany;
use App\Support\Filament\BusinessFilamentActionAccess;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\SecurityAudit;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditWhiteCompany extends EditRecord
{
    protected static string $resource = WhiteCompanyResource::class;

    protected static ?string $title = 'Editar Información de Empresas Aliadas';

    /**
     * @var array<string, mixed>
     */
    private array $documentBrandUploads = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();

        if (! $record instanceof WhiteCompany) {
            return $data;
        }

        return array_merge($data, WhiteCompanyDocumentBrandForm::formStateFromRecord($record));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->documentBrandUploads = $data;
        $data = WhiteCompanyDocumentBrandForm::stripVirtualFields($data);
        $data['updated_by'] = Auth::user()?->name;

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->before(function (): void {
                    SecurityAudit::log('AUDIT_BUSINESS_WHITE_COMPANY_DELETED', 'business.white-companies.delete', [
                        'panel' => 'business',
                        'module' => 'white_companies',
                        'white_company_id' => $this->record->getKey(),
                        'name' => $this->record->name,
                        'rif' => $this->record->rif,
                    ]);
                }),
        ];
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();

        if (
            $record instanceof WhiteCompany
            && BusinessFilamentActionAccess::userCan(
                BusinessFilamentActionPermissionRegistry::MANAGE_WHITE_COMPANY_DOCUMENT_BRAND,
            )
        ) {
            WhiteCompanyDocumentBrandForm::syncPlanDocumentsFromState($record, $this->documentBrandUploads);
        }

        SecurityAudit::log('AUDIT_BUSINESS_WHITE_COMPANY_UPDATED', 'business.white-companies.update', [
            'panel' => 'business',
            'module' => 'white_companies',
            'white_company_id' => $this->record->getKey(),
            'name' => $this->record->name,
            'rif' => $this->record->rif,
            'email' => $this->record->email,
            'changed_fields' => array_values(array_diff(array_keys($this->record->getChanges()), ['updated_at'])),
        ]);
    }
}
