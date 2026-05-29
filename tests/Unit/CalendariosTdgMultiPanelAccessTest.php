<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

it('registra calendarios tdg en administration, operations y marketing extendiendo la pagina de business', function (): void {
    $businessPage = dirname(__DIR__, 2).'/app/Filament/Business/Pages/CalendariosTdg.php';
    $adminPage = dirname(__DIR__, 2).'/app/Filament/Administration/Pages/CalendariosTdg.php';
    $operationsPage = dirname(__DIR__, 2).'/app/Filament/Operations/Pages/CalendariosTdg.php';
    $marketingPage = dirname(__DIR__, 2).'/app/Filament/Marketing/Pages/CalendariosTdg.php';

    expect(file_exists($businessPage))->toBeTrue()
        ->and(file_exists($adminPage))->toBeTrue()
        ->and(file_exists($operationsPage))->toBeTrue()
        ->and(file_exists($marketingPage))->toBeTrue();

    expect(file_get_contents($adminPage))
        ->toContain('extends BusinessCalendariosTdg')
        ->toContain('namespace App\\Filament\\Administration\\Pages;');

    expect(file_get_contents($operationsPage))
        ->toContain('extends BusinessCalendariosTdg')
        ->toContain('namespace App\\Filament\\Operations\\Pages;');

    expect(file_get_contents($marketingPage))
        ->toContain('extends BusinessCalendariosTdg')
        ->toContain('namespace App\\Filament\\Marketing\\Pages;');

    expect(file_get_contents($businessPage))
        ->toContain("protected string \$view = 'filament.business.pages.calendarios-tdg';")
        ->toContain('InteractsWithTdgHybridCalendar');
});

it('expone rutas de calendarios tdg en cada panel interno', function (): void {
    expect(route('filament.business.pages.calendarios-tdg'))->toContain('/business/')
        ->and(route('filament.administration.pages.calendarios-tdg'))->toContain('/administration/')
        ->and(route('filament.operations.pages.calendarios-tdg'))->toContain('/operations/')
        ->and(route('filament.marketing.pages.calendarios-tdg'))->toContain('/marketing/');
});
