<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

it('genera la URL de creación de consulta con el id del paciente', function (): void {
    $url = route('filament.telemedicina.resources.telemedicine-consultation-patients.create', ['id' => 42]);

    expect($url)->toContain('telemedicine-consultation-patients')
        ->and($url)->toContain('create')
        ->and($url)->toMatch('/[?&]id=42\b/');
});
