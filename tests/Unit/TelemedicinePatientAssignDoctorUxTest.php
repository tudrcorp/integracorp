<?php

declare(strict_types=1);

it('la tabla y la ficha del paciente usan la acción reutilizable AssignDoctorAction', function (): void {
    $root = dirname(__DIR__, 2);

    $table = file_get_contents(
        $root.'/app/Filament/Operations/Resources/TelemedicinePatients/Tables/TelemedicinePatientsTable.php'
    );
    $view = file_get_contents(
        $root.'/app/Filament/Operations/Resources/TelemedicinePatients/Pages/ViewTelemedicinePatient.php'
    );

    expect($table)->toContain('AssignDoctorAction::make()')
        ->and($view)->toContain('AssignDoctorAction::make()');
});

it('el select de doctores es buscable y el analista TDG no se limita al proveedor del paciente', function (): void {
    $contents = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicinePatients/Actions/AssignDoctorAction.php'
    );

    expect($contents)
        ->toContain('->searchable()')
        ->toContain('authenticatedUserIsTdgAnalyst()')
        ->toContain('if (! $isTdgAnalyst)')
        ->toContain("->with('supplier:id,name')");
});

it('la creación y asociación de pacientes redirige a la ficha del paciente', function (): void {
    $root = dirname(__DIR__, 2);

    $create = file_get_contents(
        $root.'/app/Filament/Operations/Resources/TelemedicinePatients/Pages/CreateTelemedicinePatient.php'
    );
    $affiliate = file_get_contents(
        $root.'/app/Filament/Operations/Resources/Affiliates/Pages/ViewAffiliate.php'
    );
    $affiliateCorporate = file_get_contents(
        $root.'/app/Filament/Operations/Resources/AffiliateCorporates/Pages/ViewAffiliateCorporate.php'
    );

    expect($create)->toContain("TelemedicinePatientResource::getUrl('view'");

    expect($affiliate)
        ->toContain("TelemedicinePatientResource::getUrl('view', ['record' => \$result['patient']])");

    expect($affiliateCorporate)
        ->toContain("TelemedicinePatientResource::getUrl('view', ['record' => \$result['patient']])");
});
