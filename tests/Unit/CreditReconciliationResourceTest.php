<?php

declare(strict_types=1);

use App\Filament\Administration\Resources\CreditReconciliations\CreditReconciliationResource as AdministrationCreditReconciliationResource;
use App\Filament\Business\Resources\CreditReconciliations\CreditReconciliationResource;
use App\Filament\Business\Resources\CreditReconciliations\Schemas\CreditReconciliationInfolist;
use App\Filament\Business\Resources\CreditReconciliations\Tables\CreditReconciliationsTable;
use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\CreditReconciliation;
use App\Models\WhiteCompany;
use App\Support\Filament\DepartmentNavigationPermissionRegistry;
use App\Support\Filament\UserFormPermissionOptions;

it('registra conciliacion de credito en negocios y administracion como consulta de movimientos', function (): void {
    $business = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/CreditReconciliations/CreditReconciliationResource.php');
    $administration = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/CreditReconciliations/CreditReconciliationResource.php');
    $infolist = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/CreditReconciliations/Schemas/CreditReconciliationInfolist.php');
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/CreditReconciliations/Tables/CreditReconciliationsTable.php');
    $migration = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_08_12_214400_create_credit_reconciliations_table.php');
    $creditMigration = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_08_12_214359_add_assigned_credit_to_white_companies_agencies_and_agents_tables.php');

    expect($business)
        ->toContain("navigationLabel = 'Conciliación de crédito'")
        ->toContain("navigationGroup = 'ESTRUCTURA COMERCIAL'")
        ->toContain('AuthorizesDepartmentNavigation')
        ->toContain('canCreate(): bool')
        ->toContain('ViewCreditReconciliation::route')
        ->not->toContain('CreateCreditReconciliation');

    expect($administration)
        ->toContain("navigationLabel = 'Conciliación de crédito'")
        ->toContain("navigationGroup = 'ADMINISTRACIÓN'")
        ->toContain('canCreate(): bool')
        ->not->toContain('CreateCreditReconciliation');

    expect($infolist)
        ->toContain("label('Empresa aliada')")
        ->toContain("label('Código de afiliación')")
        ->toContain("label('Información de la afiliación')")
        ->toContain("label('Cantidad de afiliados')")
        ->toContain("label('Monto anual')")
        ->toContain("label('Monto descontado')")
        ->toContain("label('Frecuencia de pago')")
        ->toContain("label('Nro. de aviso de cobro')")
        ->toContain("label('Tipo de plan')")
        ->toContain("label('Comprobante')")
        ->toContain('PaidMembershipDocumentUrl::fromReconciliation');

    expect($table)
        ->toContain("heading('Conciliación de crédito')")
        ->toContain('ViewAction::make()')
        ->toContain("Action::make('view_credit_note')")
        ->toContain("label('Ver y descargar')")
        ->toContain('forWhiteCompanies()')
        ->toContain("label('Comprobante')")
        ->toContain('PaidMembershipDocumentUrl::fromReconciliation')
        ->toContain('paidMembership')
        ->not->toContain('EditAction');

    expect($migration)
        ->toContain("Schema::create('credit_reconciliations'")
        ->toContain('affiliation_code')
        ->toContain('collection_invoice_number')
        ->toContain('total_to_pay');

    expect($creditMigration)
        ->toContain("Schema::table('white_companies'")
        ->toContain("Schema::table('agencies'")
        ->toContain("Schema::table('agents'")
        ->toContain("decimal('assigned_credit', 14, 2)");
});

it('expone assigned_credit en empresas aliadas agencias y agentes', function (): void {
    expect((new WhiteCompany)->getFillable())->toContain('assigned_credit')
        ->and((new Agency)->getFillable())->toContain('assigned_credit')
        ->and((new Agent)->getFillable())->toContain('assigned_credit');

    expect((new CreditReconciliation)->getFillable())
        ->toContain('affiliation_code')
        ->toContain('collection_invoice_number')
        ->toContain('plan_type')
        ->toContain('total_to_pay')
        ->toContain('paid_membership_id')
        ->toContain('collection_id');
});

it('calcula el credito restante a partir de los movimientos cargados', function (): void {
    $company = new WhiteCompany;
    $company->forceFill(['assigned_credit' => 1000]);
    $company->setRelation('creditReconciliations', collect([
        (new CreditReconciliation)->forceFill(['id' => 1, 'total_to_pay' => 200]),
        (new CreditReconciliation)->forceFill(['id' => 2, 'total_to_pay' => 150]),
    ]));

    expect($company->consumedAssignedCredit())->toBe(350.0)
        ->and($company->remainingAssignedCredit())->toBe(650.0)
        ->and($company->remainingAssignedCredit(1))->toBe(850.0);
});

it('autoriza el menu de conciliacion de credito en negocios y administracion', function (): void {
    expect(DepartmentNavigationPermissionRegistry::slugsFor(CreditReconciliationResource::class))
        ->toBe(['conciliacion-de-credito'])
        ->and(DepartmentNavigationPermissionRegistry::slugsFor(AdministrationCreditReconciliationResource::class))
        ->toBe(['conciliacion-de-credito'])
        ->and(DepartmentNavigationPermissionRegistry::moduleFor(CreditReconciliationResource::class))
        ->toBe('NEGOCIOS')
        ->and(DepartmentNavigationPermissionRegistry::moduleFor(AdministrationCreditReconciliationResource::class))
        ->toBe('ADMINISTRACION')
        ->and(UserFormPermissionOptions::navToLegacySlugAliases()['creditreconciliationresource'] ?? null)
        ->toBe(['conciliacion-de-credito']);
});

it('consulta movimientos sin alta manual', function (): void {
    expect(method_exists(CreditReconciliationInfolist::class, 'configure'))->toBeTrue()
        ->and(method_exists(CreditReconciliationsTable::class, 'configure'))->toBeTrue()
        ->and(CreditReconciliationResource::canCreate())->toBeFalse()
        ->and(AdministrationCreditReconciliationResource::canCreate())->toBeFalse()
        ->and(in_array(AuthorizesDepartmentNavigation::class, class_uses_recursive(CreditReconciliationResource::class), true))->toBeTrue();
});

it('asigna credito en formularios de agencia y agente', function (): void {
    $businessAgency = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Agencies/Schemas/AgencyForm.php');
    $businessAgent = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Agents/Schemas/AgentForm.php');
    $adminAgency = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Agencies/Schemas/AgencyForm.php');
    $adminAgent = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Agents/Schemas/AgentForm.php');

    expect($businessAgency)->toContain("TextInput::make('assigned_credit')")
        ->and($businessAgent)->toContain("TextInput::make('assigned_credit')")
        ->and($adminAgency)->toContain("TextInput::make('assigned_credit')")
        ->and($adminAgent)->toContain("TextInput::make('assigned_credit')");
});

it('registra el movimiento al aprobar cuotas de afiliacion', function (): void {
    $individual = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/PaidMembershipController.php');
    $corporate = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/PaidMembershipCorporateController.php');

    expect($individual)
        ->toContain('WhiteCompanyCreditMovementRecorder::recordIndividualInstallment')
        ->toContain('WhiteCompanyCreditMovementRecorder::recordIndividualCollections')
        ->and($corporate)
        ->toContain('WhiteCompanyCreditMovementRecorder::recordCorporateInstallment')
        ->toContain('WhiteCompanyCreditMovementRecorder::recordCorporateCollections');
});
