<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

it('redirige al dashboard de telemedicina tras crear la historia clínica', function (): void {
    $contents = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineHistoryPatients/Pages/CreateTelemedicineHistoryPatient.php'
    );

    expect($contents)
        ->toContain('function getRedirectUrl()')
        ->toContain("URL::route('filament.telemedicina.pages.dashboard')")
        ->not->toContain('filament.telemedicina.resources.telemedicine-consultation-patients.create');
});
