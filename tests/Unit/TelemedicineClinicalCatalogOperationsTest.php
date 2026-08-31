<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\TelemedicineListLaboratories\TelemedicineListLaboratoryResource;
use App\Filament\Operations\Resources\TelemedicineListSpecialists\TelemedicineListSpecialistResource;
use App\Filament\Operations\Resources\TelemedicineListStudies\TelemedicineListStudyResource;
use App\Support\Filament\DepartmentNavigationPermissionRegistry;
use App\Support\Filament\InternalPanelDepartmentMap;
use App\Support\Filament\Operations\TelemedicineClinicalCatalogForm;
use App\Support\Filament\UserFormPermissionOptions;

it('registra los catálogos clínicos en permisos de operaciones', function (): void {
    expect(DepartmentNavigationPermissionRegistry::slugsFor(TelemedicineListLaboratoryResource::class))
        ->toBe(['lista-laboratorios'])
        ->and(DepartmentNavigationPermissionRegistry::slugsFor(TelemedicineListStudyResource::class))
        ->toBe(['lista-estudios'])
        ->and(DepartmentNavigationPermissionRegistry::slugsFor(TelemedicineListSpecialistResource::class))
        ->toBe(['lista-especialistas'])
        ->and(InternalPanelDepartmentMap::moduleForClass(TelemedicineListLaboratoryResource::class))
        ->toBe('OPERACIONES');

    $aliases = UserFormPermissionOptions::navToLegacySlugAliases();

    expect($aliases['telemedicinelistlaboratoryresource'] ?? null)->toBe(['lista-laboratorios'])
        ->and($aliases['telemedicineliststudyresource'] ?? null)->toBe(['lista-estudios'])
        ->and($aliases['telemedicinelistspecialistresource'] ?? null)->toBe(['lista-especialistas']);
});

it('los recursos de catálogo clínico viven en CONFIGURACION de Operaciones', function (): void {
    expect(TelemedicineListLaboratoryResource::getNavigationGroup())->toBe('CONFIGURACION')
        ->and(TelemedicineListLaboratoryResource::getNavigationLabel())->toBe('Lista de Laboratorios')
        ->and(TelemedicineListStudyResource::getNavigationGroup())->toBe('CONFIGURACION')
        ->and(TelemedicineListStudyResource::getNavigationLabel())->toBe('Lista de Estudios')
        ->and(TelemedicineListSpecialistResource::getNavigationGroup())->toBe('CONFIGURACION')
        ->and(TelemedicineListSpecialistResource::getNavigationLabel())->toBe('Lista de Especialistas');
});

it('normaliza nombre y tipo del catálogo clínico', function (): void {
    expect(TelemedicineClinicalCatalogForm::normalize([
        'name' => '  hemograma completo  ',
        'type' => 'cubierto',
    ]))->toBe([
        'name' => 'HEMOGRAMA COMPLETO',
        'type' => 'CUBIERTO',
    ]);
});

it('las tablas y formularios de catálogo clínico tienen UI de operaciones y vuelven al listado', function (): void {
    $root = dirname(__DIR__, 2);
    $form = file_get_contents($root.'/app/Support/Filament/Operations/TelemedicineClinicalCatalogForm.php');
    $table = file_get_contents($root.'/app/Support/Filament/Operations/TelemedicineClinicalCatalogTable.php');
    $pages = file_get_contents($root.'/app/Support/Filament/Operations/TelemedicineClinicalCatalogPages.php');
    $editLab = file_get_contents($root.'/app/Filament/Operations/Resources/TelemedicineListLaboratories/Pages/EditTelemedicineListLaboratory.php');
    $createLab = file_get_contents($root.'/app/Filament/Operations/Resources/TelemedicineListLaboratories/Pages/CreateTelemedicineListLaboratory.php');

    expect($form)
        ->toContain('ToggleButtons::make(\'type\')')
        ->toContain('Section::make($sectionTitle)')
        ->toContain('autofocus()');

    expect($table)
        ->toContain('emptyStateHeading')
        ->toContain('searchPlaceholder')
        ->toContain('striped()')
        ->toContain('Filtros');

    expect($pages)
        ->toContain('Volver al listado')
        ->toContain('Volvió al listado')
        ->toContain('createdNotification')
        ->toContain('savedNotification');

    expect($editLab)
        ->toContain('getRedirectUrl')
        ->toContain('getSavedNotification')
        ->toContain("::getUrl('index')");

    expect($createLab)
        ->toContain('$canCreateAnother = false')
        ->toContain('getCreatedNotification')
        ->toContain("::getUrl('index')");
});

it('exige motivo y auditoría al eliminar un ítem del catálogo clínico', function (): void {
    $contents = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicineClinicalCatalogs/Actions/DeleteTelemedicineClinicalCatalogAction.php'
    );

    expect($contents)
        ->toContain('deletion_reason')
        ->toContain('SecurityAudit::log')
        ->toContain('minLength(10)');
});
