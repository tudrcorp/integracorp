<?php

declare(strict_types=1);

it('TelemedicinePatientInfolist expone mapa en dirección del paciente', function (): void {
    $infolist = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicinePatients/Schemas/TelemedicinePatientInfolist.php');
    $page = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicinePatients/Pages/ViewTelemedicinePatient.php');

    expect($infolist)
        ->toContain('OperationsLocationMapAction::forTelemedicinePatient()')
        ->toContain('IOS_ADDRESS_CARD')
        ->toContain('copyMessage(\'Dirección copiada\')')
        ->toContain('Dirección del paciente')
        ->not->toContain('google.com/maps/search');

    expect($page)
        ->toContain('AppliesOperationsAddressFromMaps')
        ->toContain('location-maps-loader');

    expect(file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Support/OperationsLocationMapAction.php'))
        ->toContain('OperationsMapSearchAddress::forTelemedicinePatient');

    expect(file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Concerns/AppliesOperationsAddressFromMaps.php'))
        ->toContain('applyTelemedicinePatientLocationFromMaps');
});
