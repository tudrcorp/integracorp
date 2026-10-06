<?php

namespace App\Filament\Business\Resources\WhiteCompanies\Pages;

use App\Filament\Business\Resources\WhiteCompanies\Schemas\WhiteCompanyDocumentBrandForm;
use App\Filament\Business\Resources\WhiteCompanies\WhiteCompanyResource;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\WhiteCompany;
use App\Support\Filament\BusinessFilamentActionAccess;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Filament\RecordPageHeader;
use App\Support\SecurityAudit;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class EditWhiteCompany extends EditRecord
{
    protected static string $resource = WhiteCompanyResource::class;

    /**
     * @var array<string, mixed>
     */
    private array $documentBrandUploads = [];

    public function getTitle(): string|Htmlable
    {
        return 'Editar '.(trim((string) $this->getRecord()->name) ?: 'empresa aliada');
    }

    /**
     * Encabezado del sistema ({@see RecordPageHeader}) con el logo de la empresa
     * aliada: el analista ve de entrada en qué marca está trabajando.
     */
    public function getHeading(): string|Htmlable
    {
        /** @var WhiteCompany $company */
        $company = $this->getRecord();

        $plans = $company->assignedPlans()->count();
        $affiliations = Affiliation::query()->where('white_company_id', $company->id)->where('status', 'ACTIVA')->count()
            + AffiliationCorporate::query()->where('white_company_id', $company->id)->where('status', 'ACTIVA')->count();

        return RecordPageHeader::render(
            eyebrow: 'Empresa aliada · Editar información',
            title: trim((string) $company->name) ?: 'Sin nombre',
            chips: [
                RecordPageHeader::tag($plans === 1 ? '1 plan asignado' : $plans.' planes asignados', RecordPageHeader::TONE_VIOLET),
                RecordPageHeader::tag($affiliations === 1 ? '1 afiliación activa' : number_format($affiliations, 0, ',', '.').' afiliaciones activas', RecordPageHeader::TONE_SUCCESS),
                blank($company->logo) ? RecordPageHeader::tag('Sin logo: súbalo en la marca de documentos', RecordPageHeader::TONE_WARNING) : null,
            ],
            facts: [
                'RIF' => $company->rif,
                'Correo' => $company->email,
                'Teléfono' => $company->phone,
                'Crédito asignado' => RecordPageHeader::money($company->assigned_credit, hideZero: true),
            ],
            imageUrl: filled($company->logo) && Storage::disk('public')->exists((string) $company->logo)
                ? Storage::disk('public')->url((string) $company->logo)
                : null,
        );
    }

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
