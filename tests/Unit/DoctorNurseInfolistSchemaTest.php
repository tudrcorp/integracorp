<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\DoctorNurses\Schemas\DoctorNurseInfolist;
use App\Models\DoctorNurse;
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
        ->toContain('homeCareEquipmentFieldsets()')
        ->toContain('Fieldset::make($groupLabel)')
        ->toContain("->columns(['default' => 2, 'sm' => 3, 'lg' => 4, 'xl' => 6])")
        ->toContain('->default(false)')
        ->toContain('Instrumental de diagnóstico')
        ->toContain('Material descartable de cura')
        ->toContain('Equipamiento de apoyo y seguridad')
        ->toContain('Elementos avanzados o de urgencia')
        ->not->toContain('Sin descripción registrada.')
        ->not->toContain('hasCertifiedHomeCareEquipment');

    $bankingSource = file_get_contents(dirname(__DIR__, 2).'/app/Support/Filament/Operations/SupplierBeneficiaryBankingInfolist.php');

    expect($bankingSource)
        ->toContain('local_beneficiary_account_number_mon_inter')
        ->toContain('extra_beneficiary_zelle');
});

it('en infolist no muestra descripcion de equipamiento si no esta certificado o esta vacia', function (): void {
    $method = new ReflectionMethod(
        DoctorNurseInfolist::class,
        'homeCareEquipmentDescription',
    );
    $method->setAccessible(true);

    $conDescripcionHuerfana = new DoctorNurse([
        'equip_diag_oximeter' => false,
        'equip_desc_diag_oximeter' => 'Texto que no debe verse',
    ]);

    $sinDescripcion = new DoctorNurse([
        'equip_diag_oximeter' => true,
        'equip_desc_diag_oximeter' => '   ',
    ]);

    $conDescripcion = new DoctorNurse([
        'equip_diag_oximeter' => true,
        'equip_desc_diag_oximeter' => 'Equipo calibrado.',
    ]);

    expect($method->invoke(null, $conDescripcionHuerfana, 'equip_diag_oximeter', 'equip_desc_diag_oximeter'))
        ->toBeNull()
        ->and($method->invoke(null, $sinDescripcion, 'equip_diag_oximeter', 'equip_desc_diag_oximeter'))
        ->toBeNull()
        ->and($method->invoke(null, $conDescripcion, 'equip_diag_oximeter', 'equip_desc_diag_oximeter'))
        ->toBe('Descripción: Equipo calibrado.');
});
