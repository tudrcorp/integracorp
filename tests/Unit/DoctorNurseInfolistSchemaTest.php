<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\DoctorNurses\Schemas\DoctorNurseInfolist;
use Filament\Schemas\Schema;

it('configura el infolist de proveedores naturales sin error', function (): void {
    $schema = Schema::make();
    $configured = DoctorNurseInfolist::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);
});

it('usa tabs y estilos alineados con infolist de agentes y agencias', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/DoctorNurses/Schemas/DoctorNurseInfolist.php');

    expect($source)
        ->toContain('doctorNurseInfolistTabs')
        ->toContain('TABS_CONTAINER')
        ->toContain('SECTION_CARD')
        ->toContain('persistTab')
        ->toContain("Tab::make('Identidad')")
        ->toContain("Tab::make('Trazabilidad')")
        ->toContain('SupplierBeneficiaryBankingInfolist::bankingTab')
        ->toContain("Tab::make('Infraestructura domiciliaria')")
        ->toContain('OutlinedXCircle')
        ->toContain('homeCareEquipmentEntries()')
        ->not->toContain('homeCareEquipmentFieldsets()')
        ->not->toContain('hasCertifiedHomeCareEquipment');

    $bankingSource = file_get_contents(dirname(__DIR__, 2).'/app/Support/Filament/Operations/SupplierBeneficiaryBankingInfolist.php');

    expect($bankingSource)
        ->toContain('local_beneficiary_account_number_mon_inter')
        ->toContain('extra_beneficiary_zelle');
});
