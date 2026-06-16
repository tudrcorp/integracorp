<?php

declare(strict_types=1);

it('expone las rutas de vista previa y descarga de la ficha PDF del agente en negocios', function (): void {
    $path = dirname(__DIR__, 2).'/routes/web.php';
    $contents = file_get_contents($path);

    expect($contents)->toContain("->name('business.agents.ficha-pdf.preview')")
        ->toContain("->name('business.agents.ficha-pdf.download')")
        ->toContain('BusinessAgentFichaPdfController::class');
});

it('expone las rutas de vista previa y descarga de la ficha PDF de la agencia en negocios', function (): void {
    $path = dirname(__DIR__, 2).'/routes/web.php';
    $contents = file_get_contents($path);

    expect($contents)->toContain("->name('business.agencies.ficha-pdf.preview')")
        ->toContain("->name('business.agencies.ficha-pdf.download')")
        ->toContain('BusinessAgencyFichaPdfController::class');
});

it('expone las rutas de vista previa y descarga de la ficha PDF de agencia de viajes en negocios', function (): void {
    $path = dirname(__DIR__, 2).'/routes/web.php';
    $contents = file_get_contents($path);

    expect($contents)->toContain("->name('business.travel-agencies.ficha-pdf.preview')")
        ->toContain("->name('business.travel-agencies.ficha-pdf.download')")
        ->toContain('BusinessTravelAgencyFichaPdfController::class');
});
