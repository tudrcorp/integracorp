<?php

declare(strict_types=1);

use App\Models\Plan;
use App\Models\TelemedicinePatient;
use App\Support\Filament\TelemedicinePatientPageHeader;

it('arma el encabezado de la ficha con nombre, estado y contacto', function (): void {
    $patient = new TelemedicinePatient([
        'full_name' => 'ANA MARIA PEREZ GARCIA',
        'status_affiliation' => 'ACTIVO',
        'type_affiliation' => 'INDIVIDUAL',
        'nro_identificacion' => '12345678',
        'age' => 42,
        'phone' => '+584121112233',
        'email' => 'ana@example.com',
        'code_affiliation' => 'TDC-1001',
    ]);
    $patient->setRelation('plan', new Plan(['description' => 'PLAN INTEGRAL']));

    $html = (string) TelemedicinePatientPageHeader::forPatient($patient);

    expect($html)
        ->toContain('Ficha del paciente')
        ->toContain('ANA MARIA PEREZ GARCIA')
        ->toContain('ACTIVO')
        ->toContain('INDIVIDUAL')
        ->toContain('PLAN INTEGRAL')
        ->toContain('C.I.: 12345678')
        ->toContain('42 años')
        ->toContain('+584121112233')
        ->toContain('ana@example.com')
        ->toContain('Afiliación: TDC-1001')
        ->not->toContain('Informacion principal del paciente');
});

it('arma el encabezado de edición', function (): void {
    $patient = new TelemedicinePatient([
        'full_name' => 'ANA MARIA PEREZ GARCIA',
        'status_affiliation' => 'ACTIVO',
    ]);

    $html = (string) TelemedicinePatientPageHeader::forPatient($patient, context: 'edit');

    expect($html)
        ->toContain('Editar paciente')
        ->toContain('ANA MARIA PEREZ GARCIA')
        ->not->toContain('Ficha del paciente');
});

it('omite datos vacíos y usa fallbacks claros', function (): void {
    $patient = new TelemedicinePatient([
        'full_name' => null,
        'status_affiliation' => null,
        'type_affiliation' => null,
        'nro_identificacion' => '',
        'age' => null,
        'phone' => '',
        'email' => null,
        'code_affiliation' => null,
    ]);

    $html = (string) TelemedicinePatientPageHeader::forPatient($patient);

    expect($html)
        ->toContain('Ficha del paciente')
        ->toContain('PACIENTE SIN NOMBRE')
        ->toContain('SIN AFILIACIÓN')
        ->not->toContain('C.I.:')
        ->not->toContain('Afiliación:')
        ->not->toContain('años');
});

it('la ficha de operaciones usa el encabezado en vista y edición', function (): void {
    $view = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicinePatients/Pages/ViewTelemedicinePatient.php');
    $edit = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicinePatients/Pages/EditTelemedicinePatient.php');
    $resource = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicinePatients/TelemedicinePatientResource.php');

    expect($view)
        ->toContain('TelemedicinePatientPageHeader::forPatient')
        ->not->toContain("protected static ?string \$title = 'Ficha del Paciente'");

    expect($edit)
        ->toContain("TelemedicinePatientPageHeader::forPatient(\$patient, context: 'edit')")
        ->toContain('Volver a la ficha')
        ->toContain('Eliminar paciente');

    expect($resource)
        ->toContain("protected static ?string \$recordTitleAttribute = 'full_name'")
        ->toContain("protected static ?string \$modelLabel = 'paciente'");
});

it('la ficha de telemedicina usa el encabezado y el uso clínico en beneficios', function (): void {
    $page = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicinePatients/Pages/ViewTelemedicinePatient.php');
    $resource = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicinePatients/TelemedicinePatientResource.php');
    $infolist = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicinePatients/Schemas/TelemedicinePatientInfolist.php');

    expect($page)
        ->toContain('TelemedicinePatientPageHeader::forPatient')
        ->toContain('plan.benefitPlans.limit:id,description')
        ->not->toContain('Informacion principal del paciente');

    expect($resource)
        ->toContain("protected static ?string \$recordTitleAttribute = 'full_name'")
        ->toContain("protected static ?string \$modelLabel = 'paciente'");

    expect($infolist)
        ->toContain('OperationsAffiliatePlanBenefitsCard::viewDataForPatient')
        ->toContain("View::make('filament.operations.affiliates.plan-benefits-clinical')")
        ->not->toContain('benefit_descriptions')
        ->not->toContain('benefit_limits');
});
