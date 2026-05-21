<?php

declare(strict_types=1);

it('infolist del caso de telemedicina usa pestañas para paciente y caso', function (): void {
    $c = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicineCases/Schemas/TelemedicineCaseInfolist.php');

    expect($c)
        ->toContain('Tabs::make')
        ->toContain('Tab::make')
        ->toContain('Paciente en el caso')
        ->toContain('Caso de telemedicina')
        ->toContain("Tab::make('Expediente documental')")
        ->toContain("Tab::make('Bitácora')")
        ->toContain("RepeatableEntry::make('observations')")
        ->toContain('Bitácora de observaciones');
});
