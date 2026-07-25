<?php

declare(strict_types=1);

it('el detalle del caso en telemedicina tiene header action iOS para volver al dashboard', function (): void {
    $contents = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineCases/Pages/ViewTelemedicineCase.php'
    );

    expect($contents)
        ->toContain("Action::make('back_to_cases_dashboard')")
        ->toContain('Volver al dashboard de casos')
        ->toContain("FilamentIosButton::extraClassForFilamentColor('estandar')")
        ->toContain("route('filament.telemedicina.pages.dashboard')");
});
