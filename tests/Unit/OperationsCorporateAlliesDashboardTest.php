<?php

declare(strict_types=1);

use App\Models\CorporateAlly;

it('define dashboard de aliados corporativos con estructura de resource', function () {
    $resourcePath = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/CorporateAllies/CorporateAllyResource.php';
    $resourceContents = file_get_contents($resourcePath);

    expect($resourceContents)->toContain('namespace App\Filament\Operations\Resources\CorporateAllies;')
        ->toContain("protected static ?string \$navigationLabel = 'Aliados corporativos';")
        ->toContain('protected static ?int $navigationSort = 5;')
        ->toContain('protected static string|UnitEnum|null $navigationGroup = null;')
        ->toContain('CorporateAlly::class')
        ->toContain("'index' => ListCorporateAllies::route('/')")
        ->toContain("'create' => CreateCorporateAlly::route('/create')");

    $formPath = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/CorporateAllies/Schemas/CorporateAllyForm.php';
    $formContents = file_get_contents($formPath);

    expect($formContents)->toContain("Select::make('state_id')")
        ->toContain("Select::make('city_id')")
        ->toContain("Tabs::make('corporateAllyFormTabs')")
        ->toContain('persistTabInQueryString()')
        ->toContain("Tab::make('Datos principales')")
        ->toContain("Tab::make('Ubicación')")
        ->toContain("Tab::make('Contacto')")
        ->toContain("Tab::make('Servicios y condiciones')")
        ->toContain("Tab::make('Datos bancarios')")
        ->toContain("Tab::make('Notas')")
        ->toContain("Repeater::make('corporateAllyObservacions')")
        ->not->toContain('Wizard::make');

    $infolistPath = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/CorporateAllies/Schemas/CorporateAllyInfolist.php';
    $infolistContents = file_get_contents($infolistPath);

    expect($infolistContents)->toContain("Tabs::make('corporateAllyInfolistTabs')")
        ->toContain("Tab::make('Bitácora')")
        ->toContain("RepeatableEntry::make('corporateAllyObservacions')")
        ->toContain('IOS_INNER_CLASS')
        ->toContain('Heroicon::OutlinedBuildingStorefront')
        ->toContain('agreementStatusColor');

    $listPath = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/CorporateAllies/Pages/ListCorporateAllies.php';
    $listContents = file_get_contents($listPath);

    expect($listContents)->toContain('CreateAction::make()')
        ->toContain('Crear aliado corporativo');

    $pagePath = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/CorporateAllies/Pages/ListCorporateAllies.php';
    $pageContents = file_get_contents($pagePath);

    expect($pageContents)->toContain("protected static ?string \$title = 'Aliados corporativos';");

    $tablePath = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/CorporateAllies/Tables/CorporateAlliesTable.php';
    $tableContents = file_get_contents($tablePath);

    expect($tableContents)->toContain("->heading('Aliados corporativos')")
        ->toContain("TextColumn::make('company_name')")
        ->toContain("TextColumn::make('type_agreement')")
        ->toContain("TextColumn::make('status_agreement')")
        ->toContain("TextColumn::make('extra_beneficiary_zelle')")
        ->not->toContain("whereRaw('1 = 0')")
        ->toContain("->emptyStateHeading('Sin aliados corporativos')");

    $doctorNursePath = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/DoctorNurses/DoctorNurseResource.php';
    $doctorNurseContents = file_get_contents($doctorNursePath);

    expect($doctorNurseContents)->toContain('protected static ?int $navigationSort = 6;');

    $supplierPath = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Suppliers/SupplierResource.php';
    $supplierContents = file_get_contents($supplierPath);

    expect($supplierContents)->toContain('protected static ?int $navigationSort = 7;');
});

it('modelo corporate ally define tabla y atributos fillable', function () {
    $ally = new CorporateAlly;

    expect($ally->getTable())->toBe('corporate_allies')
        ->and($ally->getFillable())->toContain(
            'country_id',
            'state_id',
            'city_id',
            'supplier_category',
            'type_agreement',
            'status_agreement',
            'rif',
            'company_name',
            'phone',
            'people_contact',
            'email',
            'social_networks',
            'address',
            'services',
            'payment_term',
            'supplier_payment',
            'local_beneficiary_name',
            'local_beneficiary_rif',
            'local_beneficiary_account_number',
            'local_beneficiary_account_bank',
            'local_beneficiary_account_type',
            'local_beneficiary_phone_pm',
            'local_beneficiary_account_number_mon_inter',
            'local_beneficiary_account_bank_mon_inter',
            'local_beneficiary_account_type_mon_inter',
            'extra_beneficiary_name',
            'extra_beneficiary_ci_rif',
            'extra_beneficiary_account_number',
            'extra_beneficiary_account_bank',
            'extra_beneficiary_account_type',
            'extra_beneficiary_route',
            'extra_beneficiary_zelle',
            'extra_beneficiary_ach',
            'extra_beneficiary_swift',
            'extra_beneficiary_aba',
            'extra_beneficiary_address',
            'status',
        );
});
