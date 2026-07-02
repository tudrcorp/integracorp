<?php

declare(strict_types=1);

use App\Support\Companies\CompanyAssociateRegistrar;

it('normaliza cedulas para busqueda de responsables', function (): void {
    expect(CompanyAssociateRegistrar::normalizeIdentityCard(' v-12.345.678 '))
        ->toBe('V12345678');
});

it('calcula edad desde fecha de nacimiento', function (): void {
    expect(CompanyAssociateRegistrar::calculateAge('1990-01-15'))->toBeInt();
});

it('formulario publico de asociados expone componente livewire y ruta', function (): void {
    $livewire = file_get_contents(dirname(__DIR__, 2).'/app/Livewire/CompanyAssociateRegistration.php');
    $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/company-associate-registration.blade.php');
    $layout = file_get_contents(dirname(__DIR__, 2).'/resources/views/layouts/company-associate-registration.blade.php');
    $routes = file_get_contents(dirname(__DIR__, 2).'/routes/web.php');
    $migration = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_06_30_154811_create_company_associates_table.php');

    expect($livewire)
        ->toContain('CompanyAssociateRegistration')
        ->toContain('startNewRegistration')
        ->toContain('resolveResponsible')
        ->toContain('registered_at')
        ->toContain('WithFileUploads')
        ->toContain('Rule::unique(\'company_associates\', \'email\')')
        ->toContain('Ya existe un asociado registrado con esta cédula de identidad.')
        ->toContain('contactPhone\' => [\'required\'')
        ->toContain('contactEmail\' => [\'required\'');

    expect($view)
        ->toContain('startNewRegistration')
        ->toContain('Realizar nuevo registro')
        ->toContain('logoNewTDG.png')
        ->toContain('logoTDG.png')
        ->toContain('toggleTheme')
        ->toContain('theme-toggle')
        ->toContain('responsibleIdentityCard')
        ->toContain('identityDocument')
        ->toContain('contactFullName')
        ->toContain('Documento de identidad <span class="text-[color:var(--accent)]">*</span>');

    expect($layout)->toContain('data-theme')
        ->toContain('tdg-associate-theme')
        ->toContain('theme-toggle');

    expect($routes)->toContain("->name('company-associates.register')");

    expect($migration)
        ->toContain('registered_at')
        ->toContain('identity_document')
        ->toContain('company_responsible_id');
});

it('recurso filament de asociados lista relaciones con empresa y responsable', function (): void {
    $resource = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/CompanyAssociates/CompanyAssociateResource.php');
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/CompanyAssociates/Tables/CompanyAssociatesTable.php');
    $infolist = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/CompanyAssociates/Schemas/CompanyAssociateInfolist.php');
    $companyInfolist = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Companies/Schemas/CompanyInfolist.php');

    expect($resource)
        ->toContain('Asociados')
        ->toContain('NuevosNegociosCluster::class')
        ->toContain('canCreate(): bool');

    expect($table)
        ->toContain('company.name')
        ->toContain('responsible.full_name')
        ->toContain('registered_at');

    expect($infolist)->toContain('registered_at')->toContain('identity_document');

    expect($companyInfolist)
        ->toContain('Enlace público')
        ->toContain('public_registration_url')
        ->toContain('CompanyAssociateRegistrar::publicRegistrationUrl')
        ->toContain('responsibles-associates-panel');

    expect($infolist)
        ->toContain('Tab::make(\'Voucher ILS\')')
        ->toContain('vaucher_ils')
        ->toContain('document_ils');
});

it('panel de responsables incluye modal de voucher ils', function (): void {
    $livewire = file_get_contents(dirname(__DIR__, 2).'/app/Livewire/Business/CompanyResponsiblesAssociatesPanel.php');
    $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/business/company-responsibles-associates-panel.blade.php');
    $migration = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_06_30_174113_add_vaucher_ils_fields_to_company_associates_table.php');
    $model = file_get_contents(dirname(__DIR__, 2).'/app/Models/CompanyAssociate.php');

    expect($livewire)
        ->toContain('InteractsWithActions')
        ->toContain('InteractsWithSchemas')
        ->toContain('CompanyAssociateVoucherIlsUpdater')
        ->toContain('voucherIlsAction')
        ->toContain('expandedResponsibles')
        ->toContain('toggleResponsible')
        ->toContain('isResponsibleExpanded');

    expect($view)
        ->toContain('mountAction(\'voucherIls\'')
        ->toContain('x-filament-actions::modals')
        ->toContain('Voucher ILS')
        ->toContain('Ver en Tabla')
        ->toContain('Ver lista')
        ->toContain('Ocultar lista')
        ->toContain('Correo Electrónico:')
        ->toContain('sexBadgeClass')
        ->toContain('associate-sex-badge--femenino')
        ->toContain('dark:text-rose-300')
        ->toContain('toggleResponsible')
        ->toContain('CompanyAssociatesTableContext::forResponsible');

    expect($migration)
        ->toContain('vaucher_ils')
        ->toContain('date_init')
        ->toContain('date_end')
        ->toContain('document_ils');

    expect($model)
        ->toContain('hasVoucherIls')
        ->toContain('voucherIlsDocumentUrl');
});

it('tabla de asociados soporta vista agrupada y filtrada por responsable', function (): void {
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/CompanyAssociates/Tables/CompanyAssociatesTable.php');
    $listPage = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/CompanyAssociates/Pages/ListCompanyAssociates.php');
    $context = file_get_contents(dirname(__DIR__, 2).'/app/Support/Companies/CompanyAssociatesTableContext.php');
    $cluster = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Clusters/NuevosNegocios/NuevosNegociosCluster.php');
    $provider = file_get_contents(dirname(__DIR__, 2).'/app/Providers/Filament/BusinessPanelProvider.php');

    expect($cluster)
        ->toContain('Nuevos Negocios')
        ->toContain('protected static string|UnitEnum|null $navigationGroup = null;')
        ->toContain('protected static ?int $navigationSort = 4;')
        ->toContain('SubNavigationPosition::Top');

    expect($provider)->toContain('discoverClusters');

    expect($context)
        ->toContain('forResponsible')
        ->toContain('contextResponsible')
        ->toContain('company_responsible_id');

    expect($listPage)
        ->toContain('contextResponsible')
        ->toContain('contextCompany')
        ->toContain('Volver al negocio')
        ->toContain('Ver todos los asociados')
        ->toContain('CompanyAssociatesTable::configure');

    expect($table)
        ->toContain('Group::make(\'company_responsible_id\')')
        ->toContain('defaultGroup(\'company_responsible_id\')')
        ->toContain('CompanyAssociatesGroupPalette::groupTitleLabel')
        ->toContain('CompanyAssociatesGroupPalette::recordRowClasses')
        ->toContain('CompanyAssociatesGroupPalette::groupDescriptionLabel')
        ->toContain('has_voucher_ils')
        ->toContain('ils_status')
        ->toContain('scopedResponsible')
        ->toContain('CompanyAssociatesTableActions::uploadVoucherIlsAction')
        ->toContain('CompanyAssociatesTableActions::generateCarnetAction')
        ->toContain('CompanyAssociatesTableActions::openCarnetAction');
});

it('soportes de voucher ils y carnet centralizan la logica de negocio', function (): void {
    $voucher = file_get_contents(dirname(__DIR__, 2).'/app/Support/Companies/CompanyAssociateVoucherIlsUpdater.php');
    $carnet = file_get_contents(dirname(__DIR__, 2).'/app/Support/Companies/CompanyAssociateCarnetGenerator.php');
    $actions = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/CompanyAssociates/Actions/CompanyAssociatesTableActions.php');

    expect($voucher)
        ->toContain('formDefaults')
        ->toContain('formComponents')
        ->toContain('document_ils');

    expect($carnet)
        ->toContain('TAR-NB-')
        ->toContain('generateTarjetaAfiliacion')
        ->toContain('tarjeta-afiliacion');

    expect($actions)
        ->toContain('uploadVoucherIls')
        ->toContain('generateCarnet')
        ->toContain('openCarnet');
});
