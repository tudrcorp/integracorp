<?php

declare(strict_types=1);

it('infolist del panel telemedicina replica tabs y hub documental del panel operations', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineCases/Schemas/TelemedicineCaseInfolist.php');

    expect($contents)
        ->toContain('Tabs::make(\'telemedicineCaseInfolistTabs\')')
        ->toContain("Tab::make('Paciente en el caso')")
        ->toContain("Tab::make('Caso de telemedicina')")
        ->toContain("Tab::make('Expediente documental')")
        ->toContain("Tab::make('Bitácora')")
        ->toContain('TelemedicineCaseDocumentsCatalog::entries')
        ->toContain('case-documents-hub')
        ->toContain("RepeatableEntry::make('observations')");
});
