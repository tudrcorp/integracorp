<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\CompanyAssociates\NuevosNegociosAssociateResource;
use App\Support\Filament\DepartmentNavigationPermissionRegistry;
use App\Support\Filament\UserFormPermissionOptions;

it('registra Nuevos Negocios en AFILIADOS del panel operaciones', function (): void {
    $resourcePath = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/CompanyAssociates/NuevosNegociosAssociateResource.php';
    $contents = file_get_contents($resourcePath);

    expect($contents)->not->toBeFalse()
        ->and($contents)->toContain("'AFILIADOS'")
        ->and($contents)->toContain("'Nuevos Negocios'")
        ->and($contents)->toContain("\$slug = 'nuevos-negocios-associates'")
        ->and($contents)->toContain('CompanyAssociate::class')
        ->and($contents)->toContain('AuthorizesDepartmentNavigation')
        ->and($contents)->toContain('getGloballySearchableAttributes')
        ->and($contents)->toContain('getGlobalSearchResultDetails')
        ->and($contents)->toContain('getGlobalSearchEloquentQuery')
        ->and($contents)->toContain("'full_name'")
        ->and($contents)->toContain("'identity_card'")
        ->and($contents)->toContain("'company.name'")
        ->and(DepartmentNavigationPermissionRegistry::slugsFor(NuevosNegociosAssociateResource::class))
        ->toBe(['afiliados-nuevos-negocios'])
        ->and(UserFormPermissionOptions::navToLegacySlugAliases()['nuevosnegociosassociateresource'] ?? null)
        ->toBe(['afiliados-nuevos-negocios']);
});

it('ViewCompanyAssociate expone acción de asociar asociado como paciente con confirmación e estilos iOS', function (): void {
    $page = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/CompanyAssociates/Pages/ViewCompanyAssociate.php');
    expect($page)->not->toBeFalse();

    expect($page)
        ->toContain("Action::make('associate_as_patient')")
        ->toContain('->requiresConfirmation()')
        ->toContain('AssociateCompanyAssociateWithTelemedicinePatientService::run')
        ->toContain('Asociar a Pacientes')
        ->toContain('Sí, asociar')
        ->toContain('ticket-btn-ios')
        ->toContain('ticket-btn-ios-gray')
        ->toContain('modalSubmitAction')
        ->toContain('modalCancelAction');
});

it('AssociateCompanyAssociateWithTelemedicinePatientService valida empresa y usa tipo NUEVOS NEGOCIOS', function (): void {
    $service = file_get_contents(dirname(__DIR__, 2).'/app/Services/AssociateCompanyAssociateWithTelemedicinePatientService.php');
    expect($service)->not->toBeFalse();

    expect($service)
        ->toContain('if ($associate->company === null)')
        ->toContain('$associate->state?->country_id')
        ->toContain('$associate->state?->region_id')
        ->toContain('ubicación completa')
        ->toContain("TelemedicinePatient::updateOrCreate(['email' => \$emailKey], \$attributes)")
        ->toContain("'type_affiliation' => 'NUEVOS NEGOCIOS'")
        ->toContain("'supplier_id' => Auth::user()?->supplier_id")
        ->toContain("'status_affiliation' => 'ACTIVO'")
        ->toContain("'country_id' => \$countryId")
        ->toContain("'region' => \$regionId");
});
