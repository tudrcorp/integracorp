<?php

declare(strict_types=1);

it('navega al view al hacer click en una fila de agencias en administracion', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Agencies/Tables/AgenciesTable.php';
    $source = file_get_contents($path);

    expect($source)
        ->toContain("->recordUrl(fn (Agency \$record): string => AgencyResource::getUrl('view', ['record' => \$record]))")
        ->not->toContain("Action::make('view_agency_profile')");
});
