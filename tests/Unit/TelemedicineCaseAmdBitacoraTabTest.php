<?php

declare(strict_types=1);

it('infolist de telemedicina incluye la pestaña Bitácoras AMD', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineCases/Schemas/TelemedicineCaseInfolist.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("Tab::make('Bitácoras AMD')")
        ->toContain('TelemedicineAmdBitacoraCatalog::viewContext')
        ->toContain('filament.telemedicina.cases.amd-bitacora');
});

it('infolist de operaciones incluye la pestaña Bitácoras AMD', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicineCases/Schemas/TelemedicineCaseInfolist.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("Tab::make('Bitácoras AMD')")
        ->toContain('TelemedicineAmdBitacoraCatalog::viewContext')
        ->toContain('filament.telemedicina.cases.amd-bitacora');
});

it('TelemedicineAmdBitacoraCatalog arma el contexto de bitácoras AMD del caso', function (): void {
    $path = dirname(__DIR__, 2).'/app/Support/Telemedicine/TelemedicineAmdBitacoraCatalog.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('viewContext')
        ->toContain('amdInforms')
        ->toContain('document_exists')
        ->toContain('download_url');
});

it('la vista amd-bitacora muestra datos clínicos y enlace de descarga', function (): void {
    $path = dirname(__DIR__, 2).'/resources/views/filament/telemedicina/cases/amd-bitacora.blade.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('Informes AMD')
        ->toContain('Signos vitales')
        ->toContain('Información clínica')
        ->toContain('collapsible')
        ->toContain('reason_consultation')
        ->toContain('diagnostic_impression')
        ->toContain('Descargar');
});
