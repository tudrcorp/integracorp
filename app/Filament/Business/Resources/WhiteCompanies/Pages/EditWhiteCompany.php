<?php

namespace App\Filament\Business\Resources\WhiteCompanies\Pages;

use App\Filament\Business\Resources\WhiteCompanies\WhiteCompanyResource;
use App\Support\SecurityAudit;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditWhiteCompany extends EditRecord
{
    protected static string $resource = WhiteCompanyResource::class;

    protected static ?string $title = 'Editar Información de Empresas Aliadas';

    protected function mutateFormDataBeforeCreate(array $data): array
    {

        $data['updated_by'] = Auth::user()->name;

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
