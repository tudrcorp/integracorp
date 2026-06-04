<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\Suppliers\Schemas\SupplierForm;
use Filament\Schemas\Schema;

uses(Tests\TestCase::class);

it('configura el formulario de proveedor operations sin error', function (): void {
    $schema = Schema::make();
    $configured = SupplierForm::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);
});

it('incluye tab de datos bancarios alineado al formulario de agentes', function (): void {
    $formPath = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Suppliers/Schemas/SupplierForm.php';
    $bankingPath = dirname(__DIR__, 2).'/app/Support/Filament/Operations/SupplierBeneficiaryBankingForm.php';
    $migrationPath = dirname(__DIR__, 2).'/database/migrations/2026_05_28_211258_add_beneficiary_banking_fields_to_suppliers_table.php';
    $modelPath = dirname(__DIR__, 2).'/app/Models/Supplier.php';

    expect(file_get_contents($formPath))
        ->toContain('SupplierBeneficiaryBankingForm::bankingTab')
        ->toContain('SupplierIntegracorpManagementForm::formTab');

    expect(file_get_contents($bankingPath))
        ->toContain("Tab::make('Datos bancarios')")
        ->toContain('local_beneficiary_name')
        ->toContain('local_beneficiary_account_number_mon_inter')
        ->toContain('extra_beneficiary_swift')
        ->toContain('extra_beneficiary_zelle')
        ->toContain('Información bancaria local (VES)')
        ->toContain('Información bancaria extranjera (US$)');

    expect(file_get_contents($migrationPath))
        ->toContain('local_beneficiary_account_bank')
        ->toContain('extra_beneficiary_address');

    expect(file_get_contents($modelPath))
        ->toContain('local_beneficiary_phone_pm')
        ->toContain('extra_beneficiary_account_bank');
});
