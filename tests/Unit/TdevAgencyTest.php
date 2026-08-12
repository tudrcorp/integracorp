<?php

declare(strict_types=1);

use App\Filament\Business\Resources\TdevAgencies\TdevAgencyResource;
use App\Models\TdevAgency;
use App\Support\Filament\DepartmentNavigationPermissionRegistry;
use App\Support\Tdev\TdevAgencyRegistrar;

it('registra el recurso AGENCIAS TDEV en estructura comercial', function (): void {
    $resource = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/TdevAgencies/TdevAgencyResource.php');
    $form = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/TdevAgencies/Schemas/TdevAgencyForm.php');
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/TdevAgencies/Tables/TdevAgenciesTable.php');
    $relation = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/TdevAgencies/RelationManagers/AgentsRelationManager.php');
    $childRelation = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/TdevAgencies/RelationManagers/ChildAgenciesRelationManager.php');
    $createPage = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/TdevAgencies/Pages/CreateTdevAgency.php');
    $migration = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_08_11_215027_create_tdev_agencies_table.php');
    $levelMigration = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_08_11_221325_add_level_and_parent_to_tdev_agencies_table.php');
    $agentsMigration = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_08_11_215028_create_tdev_agents_table.php');

    expect($resource)
        ->toContain("navigationLabel = 'AGENCIAS TDEV'")
        ->toContain("navigationGroup = 'ESTRUCTURA COMERCIAL'")
        ->toContain('AuthorizesDepartmentNavigation')
        ->toContain('AgentsRelationManager::class')
        ->toContain('ChildAgenciesRelationManager::class');

    expect($form)
        ->toContain('Tabs::make')
        ->toContain('tdevAgencyFormTabs')
        ->toContain("Tab::make('Agencia')")
        ->toContain("Tab::make('Contacto')")
        ->toContain("Tab::make('Ubicación')")
        ->toContain("label('Nombre de agencia')")
        ->toContain("label('Número de identificación')")
        ->toContain("label('Correo')")
        ->toContain("label('Fecha de aniversario de la agencia')")
        ->toContain("label('Nombre del representante')")
        ->toContain("label('Fecha de nacimiento del representante')")
        ->toContain("label('Número de teléfono')")
        ->toContain("label('Número de teléfono adicional')")
        ->toContain("label('Usuario Instagram')")
        ->toContain("label('País')")
        ->toContain("label('Estado')")
        ->toContain("label('Ciudad')")
        ->toContain("label('Dirección')")
        ->toContain("label('Imagen del logo')")
        ->toContain("label('URL')")
        ->toContain('logos-agencias-tdev')
        ->toContain('public_agency_registration_url')
        ->toContain('landing_slogan_line_1')
        ->toContain('landing_slogan_line_2')
        ->toContain('LEVEL_TWO');

    expect($createPage)
        ->toContain('LEVEL_TWO')
        ->toContain("\$data['parent_id'] = null")
        ->toContain('logo-tdev.png')
        ->toContain('getTitle');

    expect($table)
        ->toContain('openLandingLink')
        ->toContain('openRegistrationLink')
        ->toContain('openAgencyRegistrationLink')
        ->toContain('TdevAgencyRegistrar::publicLandingUrl')
        ->toContain('TdevAgencyRegistrar::publicAgentRegistrationUrl')
        ->toContain('TdevAgencyRegistrar::publicLevelThreeAgencyRegistrationUrl')
        ->toContain('Directorio TDEV')
        ->toContain('ActionGroup::make');

    expect($relation)
        ->toContain("label('Nombre y apellido')")
        ->toContain("label('Cargo')")
        ->toContain("label('Correo')")
        ->toContain("label('Teléfono')")
        ->toContain("label('Fecha de nacimiento')");

    expect($childRelation)
        ->toContain('childAgencies')
        ->toContain('LEVEL_THREE')
        ->toContain("label('Nombre de agencia')")
        ->toContain('openAgentRegistrationLink')
        ->toContain('publicAgentRegistrationUrl')
        ->toContain('ActionGroup::make')
        ->toContain('Ver ficha / agentes');

    expect($relation)
        ->toContain('ActionGroup::make')
        ->toContain('Agentes TDEV')
        ->toContain('registration_source');

    expect($migration)
        ->toContain("Schema::create('tdev_agencies'")
        ->toContain('registration_token')
        ->toContain('anniversary_date')
        ->toContain('representative_name')
        ->toContain('instagram_username');

    expect($levelMigration)
        ->toContain("'level'")
        ->toContain('parent_id')
        ->toContain('agency_registration_token');

    expect($agentsMigration)
        ->toContain("Schema::create('tdev_agents'")
        ->toContain('tdev_agency_id')
        ->toContain('full_name')
        ->toContain('registration_source');
});

it('expone permiso de navegacion para agencias tdev', function (): void {
    expect(DepartmentNavigationPermissionRegistry::slugsFor(TdevAgencyResource::class))
        ->toContain('agencias-tdev');
});

it('genera tokens y nivel 2 por defecto al crear agencia', function (): void {
    $model = file_get_contents(dirname(__DIR__, 2).'/app/Models/TdevAgency.php');

    expect($model)
        ->toContain('registration_token')
        ->toContain('agency_registration_token')
        ->toContain('landing_slogan_line_1')
        ->toContain('landing_slogan_line_2')
        ->toContain('DEFAULT_LANDING_SLOGAN_LINE_1')
        ->toContain('resolvedLandingSloganLine1')
        ->toContain('LEVEL_TWO')
        ->toContain('LEVEL_THREE')
        ->toContain('Str::uuid()')
        ->toContain('function agents()')
        ->toContain('function childAgencies()')
        ->toContain('logoUrl')
        ->toContain('faviconUrl');
});

it('construye urls publicas de agentes y agencias nivel 3', function (): void {
    $registrar = file_get_contents(dirname(__DIR__, 2).'/app/Support/Tdev/TdevAgencyRegistrar.php');

    expect($registrar)
        ->toContain('publicLandingUrl')
        ->toContain('publicAgentRegistrationUrl')
        ->toContain('publicLevelThreeAgencyRegistrationUrl')
        ->toContain("route('tdev-agencies.landing'")
        ->toContain("route('tdev-agents.register'")
        ->toContain("route('tdev-agencies.register'")
        ->toContain('registerAgent')
        ->toContain('registerLevelThreeAgency')
        ->toContain('REGISTRATION_SOURCE_PUBLIC');

    expect(method_exists(TdevAgencyRegistrar::class, 'publicLandingUrl'))->toBeTrue();
    expect(method_exists(TdevAgencyRegistrar::class, 'publicAgentRegistrationUrl'))->toBeTrue();
    expect(method_exists(TdevAgencyRegistrar::class, 'publicLevelThreeAgencyRegistrationUrl'))->toBeTrue();
    expect(method_exists(TdevAgencyRegistrar::class, 'registerLevelThreeAgency'))->toBeTrue();
    expect(class_exists(TdevAgency::class))->toBeTrue();
    expect(TdevAgency::LEVEL_TWO)->toBe(2);
    expect(TdevAgency::LEVEL_THREE)->toBe(3);
});

it('listado de agencias tdev tiene header y stats overview', function (): void {
    $list = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/TdevAgencies/Pages/ListTdevAgencies.php');
    $widget = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/TdevAgencies/Widgets/TdevAgencyStatsOverview.php');
    $resource = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/TdevAgencies/TdevAgencyResource.php');

    expect($list)
        ->toContain('getTitle')
        ->toContain('Tu Doctor En Viajes')
        ->toContain('Agencias TDEV')
        ->toContain('TdevAgencyStatsOverview')
        ->toContain('Nueva agencia TDEV')
        ->toContain('FilamentIosButton');

    expect($widget)
        ->toContain('Agencias nivel 2')
        ->toContain('Agencias nivel 3')
        ->toContain('Agentes registrados')
        ->toContain('levelTwo()')
        ->toContain('levelThree()');

    expect($resource)
        ->toContain('TdevAgencyStatsOverview::class');
});
