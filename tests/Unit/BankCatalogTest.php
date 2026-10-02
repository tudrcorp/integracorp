<?php

declare(strict_types=1);

use App\Support\BankCatalog;
use Filament\Forms\Components\Select;

it('normaliza nombres de banco en mayusculas', function (): void {
    expect(BankCatalog::normalizeStoredBank(' banesco '))->toBe('BANESCO')
        ->and(BankCatalog::normalizeStoredBank(''))->toBeNull()
        ->and(BankCatalog::normalizeStoredBank(null))->toBeNull();
});

it('permite agregar bancos manualmente en selects de cuentas por pagar', function (): void {
    $root = dirname(__DIR__, 2);
    $form = file_get_contents($root.'/app/Filament/Operations/Resources/OperationAccountsPayables/Schemas/OperationAccountsPayableForm.php');
    $acciones = file_get_contents($root.'/app/Filament/Operations/Resources/OperationAccountsPayables/Actions/AccountsPayablePaymentReceiptActions.php');
    $catalogo = file_get_contents($root.'/app/Support/BankCatalog.php');

    expect($form)
        ->toContain('BankCatalog::configureNationalSelect')
        ->toContain('BankCatalog::configureInternationalSelect')
        ->and($acciones)
        ->toContain('BankCatalog::configureNationalSelect')
        ->toContain('BankCatalog::configureInternationalSelect')
        ->and($catalogo)
        ->toContain('createOptionForm')
        ->toContain('createOptionUsing')
        ->toContain('Agregar banco');
});

it('configura el select nacional con opcion manual y deshidratacion en mayusculas', function (): void {
    $select = BankCatalog::configureNationalSelect(
        Select::make('national_bank')->label('Banco nacional'),
    );

    expect($select->getName())->toBe('national_bank')
        ->and($select->isSearchable())->toBeTrue();
});

it('usa bank catalog en formularios de agencia y agente de negocios master general y agents', function (): void {
    $root = dirname(__DIR__, 2);
    $paths = [
        '/app/Filament/Business/Resources/Agents/Schemas/AgentForm.php',
        '/app/Filament/Business/Resources/Agencies/Schemas/AgencyForm.php',
        '/app/Filament/Agents/Resources/Agents/Schemas/AgentForm.php',
        '/app/Filament/Master/Resources/Agents/Schemas/AgentForm.php',
        '/app/Filament/Master/Resources/Agencies/Schemas/AgencyForm.php',
        '/app/Filament/General/Resources/Agents/Schemas/AgentForm.php',
        '/app/Filament/General/Resources/Agencies/Schemas/AgencyForm.php',
    ];

    foreach ($paths as $relativePath) {
        $code = file_get_contents($root.$relativePath);

        expect($code)->not->toBeFalse()
            ->toContain('use App\Support\BankCatalog;')
            ->toContain('BankCatalog::configureNationalSelect')
            ->toContain('BankCatalog::configureInternationalSelect')
            ->toContain("Select::make('local_beneficiary_account_bank')")
            ->toContain("Select::make('extra_beneficiary_account_bank')")
            ->not->toMatch("/Select::make\('local_beneficiary_account_bank'\)[\s\S]{0,200}->options\(\[/");
    }
});
