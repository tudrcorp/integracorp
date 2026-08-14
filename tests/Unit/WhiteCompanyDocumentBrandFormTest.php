<?php

declare(strict_types=1);

use App\Filament\Business\Resources\WhiteCompanies\Pages\CreateWhiteCompany;
use App\Filament\Business\Resources\WhiteCompanies\Pages\EditWhiteCompany;
use App\Filament\Business\Resources\WhiteCompanies\Schemas\WhiteCompanyDocumentBrandForm;
use App\Filament\Business\Resources\WhiteCompanies\Schemas\WhiteCompanyForm;
use App\Filament\Business\Resources\WhiteCompanies\Tables\WhiteCompaniesTable;

it('expone los campos de documentos de marca para empresas aliadas', function (): void {
    expect(method_exists(WhiteCompanyDocumentBrandForm::class, 'components'))->toBeTrue()
        ->and(method_exists(WhiteCompanyDocumentBrandForm::class, 'persist'))->toBeTrue()
        ->and(method_exists(WhiteCompanyDocumentBrandForm::class, 'formStateFromRecord'))->toBeTrue();

    $form = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/WhiteCompanies/Schemas/WhiteCompanyDocumentBrandForm.php');

    expect($form)
        ->toContain("FileUpload::make('carnet_template_image')")
        ->toContain("ColorPicker::make('brand_primary_color')")
        ->toContain("FileUpload::make('certificate_signature')")
        ->toContain("condicionadoUpload('condicionado_inicial'")
        ->toContain("condicionadoUpload('condicionado_ideal'")
        ->toContain("condicionadoUpload('condicionado_especial'")
        ->toContain('plan_label_inicial')
        ->toContain('plan_label_especial')
        ->toContain('plan_short_especial')
        ->toContain('WhiteCompanyPlanLabel::syncForPlan');
});

it('incluye la pestana de documentos de marca en el formulario de empresas aliadas', function (): void {
    $form = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/WhiteCompanies/Schemas/WhiteCompanyForm.php');

    expect($form)
        ->toContain("Tab::make('Documentos de marca')")
        ->toContain('WhiteCompanyDocumentBrandForm::components()')
        ->toContain('MANAGE_WHITE_COMPANY_DOCUMENT_BRAND');
});

it('permite cargar documentos de marca desde la tabla de empresas aliadas', function (): void {
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/WhiteCompanies/Tables/WhiteCompaniesTable.php');

    expect($table)
        ->toContain("Action::make('documentBrand')")
        ->toContain("label('Documentos de marca')")
        ->toContain('WhiteCompanyDocumentBrandForm::persist')
        ->toContain('AUDIT_BUSINESS_WHITE_COMPANY_DOCUMENT_BRAND_UPDATED')
        ->toContain('MANAGE_WHITE_COMPANY_DOCUMENT_BRAND')
        ->toContain('BusinessFilamentActionAccess::userCan');
});

it('sincroniza condicionados al crear y editar la empresa aliada', function (): void {
    $create = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/WhiteCompanies/Pages/CreateWhiteCompany.php');
    $edit = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/WhiteCompanies/Pages/EditWhiteCompany.php');

    expect($create)
        ->toContain('mutateFormDataBeforeCreate')
        ->toContain('WhiteCompanyDocumentBrandForm::syncPlanDocumentsFromState')
        ->and($edit)
        ->toContain('mutateFormDataBeforeFill')
        ->toContain('mutateFormDataBeforeSave')
        ->toContain('WhiteCompanyDocumentBrandForm::syncPlanDocumentsFromState');

    expect(class_exists(CreateWhiteCompany::class))->toBeTrue()
        ->and(class_exists(EditWhiteCompany::class))->toBeTrue()
        ->and(class_exists(WhiteCompanyForm::class))->toBeTrue()
        ->and(class_exists(WhiteCompaniesTable::class))->toBeTrue();
});
