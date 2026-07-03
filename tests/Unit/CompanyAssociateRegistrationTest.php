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

it('calcula dias entre fechas de contrato del responsable', function (): void {
    expect(CompanyAssociateRegistrar::calculateDaysBetween('2026-01-01', '2026-01-31'))->toBe(30);
    expect(CompanyAssociateRegistrar::calculateDaysBetween('2026-01-31', '2026-01-01'))->toBeNull();
});

it('normaliza telefono internacional con prefijo de pais', function (): void {
    expect(CompanyAssociateRegistrar::normalizeInternationalPhone(' +58 412 701 8390 '))
        ->toBe('+584127018390');
});

it('calcula dias restantes del responsable restando dias del periodo', function (): void {
    expect(CompanyAssociateRegistrar::remainingContractedDays(100, 30))->toBe(70);
    expect(CompanyAssociateRegistrar::remainingContractedDays(10, 12))->toBe(-2);
    expect(CompanyAssociateRegistrar::remainingContractedDays(50, null))->toBeNull();
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
        ->toContain('recalculateResponsibleDays')
        ->toContain('resolvedResponsibleStartDate')
        ->toContain('resolvedResponsibleContractedDays')
        ->toContain('resolvedResponsibleConsumedDays')
        ->toContain('responsibleDaysExhausted')
        ->toContain('remainingDaysAfterRegistration')
        ->toContain('consumedDaysByResponsible')
        ->toContain('registration_period_days')
        ->toContain('responsibleCalculatedDays')
        ->toContain('responsibleRemainingDays')
        ->toContain('registered_at')
        ->toContain('WithFileUploads')
        ->toContain('Rule::unique(\'company_associates\', \'email\')')
        ->toContain('normalizeInternationalPhone')
        ->toContain('regex:/^\+[1-9]\d{6,14}$/')
        ->toContain('flightDate')
        ->toContain('flightTime')
        ->toContain('flight_date')
        ->toContain('Ya existe un asociado registrado con este documento de identidad.')
        ->toContain('contactPhone\' => [\'required\'')
        ->toContain('contactEmail\' => [\'required\'');

    expect($view)
        ->toContain('startNewRegistration')
        ->toContain('Realizar nuevo registro')
        ->toContain('Fecha desde')
        ->toContain('Fecha hasta')
        ->toContain('wire:model.live="resolvedResponsibleStartDate"')
        ->toContain('wire:model.live="resolvedResponsibleEndDate"')
        ->toContain('type="date"')
        ->toContain('Número de días')
        ->toContain('Días restantes del responsable')
        ->toContain('Registro no disponible')
        ->toContain('responsibleDaysExhausted')
        ->toContain('logoNewTDG.png')
        ->toContain('logoTDG.png')
        ->toContain('toggleTheme')
        ->toContain('theme-toggle')
        ->toContain('responsibleIdentityCard')
        ->toContain('Documento de Identidad')
        ->toContain('Ej: 12345678')
        ->toContain('Fecha de vuelo')
        ->toContain('Hora de vuelo')
        ->toContain('Incluya el prefijo del país')
        ->toContain('+584127018390')
        ->toContain('identityDocuments')
        ->toContain('multiple')
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

    $responsibleMigration = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_07_03_114516_add_contract_dates_to_company_responsibles_table.php');

    expect($responsibleMigration)
        ->toContain('contract_start_date')
        ->toContain('contract_end_date');
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

    expect($infolist)->toContain('registered_at')->toContain('identity_document')
        ->toContain('identity-documents-gallery')
        ->toContain('identityDocumentPaths')
        ->toContain('Documento de identidad')
        ->toContain('Fecha de vuelo')
        ->toContain('Hora de vuelo');

    expect($companyInfolist)
        ->toContain('Enlace público')
        ->toContain('public_registration_url')
        ->toContain('CompanyAssociateRegistrar::publicRegistrationUrl')
        ->toContain('responsibles-associates-panel')
        ->toContain('días contratados, consumidos y restantes');

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
        ->toContain('isResponsibleExpanded')
        ->toContain('associates_consumed_days_sum');

    expect($view)
        ->toContain('Días consumidos:')
        ->toContain('Días restantes:')
        ->toContain('días contratados')
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
        ->toContain('voucherIlsDocumentUrl')
        ->toContain('registration_period_days')
        ->toContain('flight_date')
        ->toContain('flight_time')
        ->toContain('identity_documents');
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
        ->toContain('CompanyAssociatesTableActions::previewInclusionQrAction')
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
        ->toContain('tarjeta-afiliacion')
        ->toContain('INCLUSIÓN')
        ->toContain('LOCAL')
        ->toContain('CONTADO');

    expect($actions)
        ->toContain('uploadVoucherIls')
        ->toContain('generateCarnet')
        ->toContain('previewInclusionQr')
        ->toContain('openCarnet')
        ->toContain('hasVoucherIls()')
        ->toContain('CompanyAssociateVoucherIlsDocumentsNotifier::queueGenerationAfterVoucherSave');
});
