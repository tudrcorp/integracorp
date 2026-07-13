<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\Filament\Operations\OperationsSupplierScope;
use Illuminate\Support\Facades\Auth;

uses(Tests\TestCase::class);

it('expone el supplier_id del usuario autenticado', function (): void {
    expect(file_get_contents(dirname(__DIR__, 2).'/app/Support/Filament/Operations/OperationsSupplierScope.php'))
        ->toContain('function currentSupplierId()')
        ->toContain('Auth::user()?->supplier_id')
        ->toContain('function applyToQuery(');
});

it('filtra pacientes por supplier_id cuando el usuario tiene proveedor asignado', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicinePatients/Tables/TelemedicinePatientsTable.php';

    expect(file_get_contents($path))
        ->toContain('OperationsSupplierScope::applyToQuery($query)')
        ->toContain("->where('managed_by', 'ATENMEDI')");
});

it('asigna supplier_id al asociar afiliados como pacientes', function (): void {
    expect(file_get_contents(dirname(__DIR__, 2).'/app/Services/AssociateAffiliateWithTelemedicinePatientService.php'))
        ->toContain("'supplier_id' => Auth::user()?->supplier_id");

    expect(file_get_contents(dirname(__DIR__, 2).'/app/Services/AssociateAffiliateCorporateWithTelemedicinePatientService.php'))
        ->toContain("'supplier_id' => Auth::user()?->supplier_id");
});

it('persiste supplier_id al crear paciente manualmente', function (): void {
    expect(file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicinePatients/Pages/CreateTelemedicinePatient.php'))
        ->toContain('OperationsSupplierScope::currentSupplierId()')
        ->toContain("\$data['supplier_id'] = \$supplierId");
});

it('filtra doctores por supplier_id al asignar caso desde pacientes', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicinePatients/Tables/TelemedicinePatientsTable.php');

    expect($contents)
        ->toContain('Select::make(\'doctor_id\')')
        ->toContain('OperationsSupplierScope::applyToQuery($doctorQuery)')
        ->toContain('->where(\'supplier_id\', $record->supplier_id)');
});

it('asigna managed_by del médico al crear el caso', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicinePatients/Tables/TelemedicinePatientsTable.php');

    expect($contents)
        ->toContain('TelemedicineDoctor::query()->findOrFail($data[\'doctor_id\'])')
        ->toContain("'managed_by' => \$doctor->managed_by");
});

it('persiste supplier_id en casos e historias clínicas', function (): void {
    expect(file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicinePatients/Tables/TelemedicinePatientsTable.php'))
        ->toContain("'supplier_id' => OperationsSupplierScope::resolveFromPatient(\$record)");

    expect(file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicineHistoryPatients/Pages/CreateTelemedicineHistoryPatient.php'))
        ->toContain('OperationsSupplierScope::resolveFromPatientAndDoctor')
        ->toContain("\$data['supplier_id'] = \$supplierId");

    expect(file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_06_03_005032_add_supplier_id_to_telemedicine_cases_and_history_patients_tables.php'))
        ->toContain('telemedicine_cases')
        ->toContain('telemedicine_history_patients');
});

it('identifica analistas TDG sin supplier_id ni flag Proveedor AMD', function (): void {
    Auth::setUser(new User([
        'supplier_id' => null,
        'departament' => ['OPERACIONES', 'TELEMEDICINA'],
        'is_proveedor_amd' => false,
    ]));

    expect(OperationsSupplierScope::authenticatedUserIsTdgAnalyst())->toBeTrue();

    Auth::setUser(new User([
        'supplier_id' => 12,
        'departament' => ['OPERACIONES'],
        'is_proveedor_amd' => true,
    ]));

    expect(OperationsSupplierScope::authenticatedUserIsTdgAnalyst())->toBeFalse();

    Auth::setUser(new User([
        'supplier_id' => null,
        'departament' => ['OPERACIONES'],
        'is_proveedor_amd' => true,
    ]));

    expect(OperationsSupplierScope::authenticatedUserIsTdgAnalyst())->toBeFalse();
});

it('oculta columnas de afiliacion a usuarios que no son analistas TDG', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicinePatients/Tables/TelemedicinePatientsTable.php';

    expect(file_get_contents($path))
        ->toContain("TextColumn::make('businessUnit.definition')")
        ->toContain("TextColumn::make('businessLine.definition')")
        ->toContain("TextColumn::make('type_affiliation')")
        ->toContain('OperationsSupplierScope::authenticatedUserIsTdgAnalyst()');
});
