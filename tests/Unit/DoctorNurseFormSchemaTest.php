<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\DoctorNurses\Schemas\DoctorNurseForm;
use Filament\Schemas\Schema;

it('configura el schema del formulario de proveedores naturales sin error', function (): void {
    $schema = Schema::make();
    $configured = DoctorNurseForm::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);
});

it('incluye datos bancarios del beneficiario como en proveedores y agentes', function (): void {
    $formPath = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/DoctorNurses/Schemas/DoctorNurseForm.php';
    $modelPath = dirname(__DIR__, 2).'/app/Models/DoctorNurse.php';
    $migrationPath = dirname(__DIR__, 2).'/database/migrations/2026_05_28_211548_add_beneficiary_banking_fields_to_doctor_nurses_table.php';

    expect(file_get_contents($formPath))
        ->toContain("Tabs::make('doctorNurseFormTabs')")
        ->toContain("Tab::make('Datos principales')")
        ->toContain("Tab::make('Ubicación y operación')")
        ->toContain("Tab::make('Contacto y condiciones')")
        ->toContain("Tab::make('Equipamiento')")
        ->toContain('homeCareEquipmentFieldsets()')
        ->toContain("Section::make('Certificación de infraestructura domiciliaria')")
        ->toContain("->columns(['default' => 2, 'sm' => 3, 'lg' => 4, 'xl' => 6])")
        ->toContain('Instrumental de diagnóstico')
        ->toContain('SupplierBeneficiaryBankingForm::bankingTab');

    expect(file_get_contents($modelPath))
        ->toContain('extra_beneficiary_zelle')
        ->toContain('local_beneficiary_phone_pm');

    expect(file_get_contents($migrationPath))
        ->toContain('extra_beneficiary_swift');
});
