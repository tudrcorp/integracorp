<?php

declare(strict_types=1);

it('muestra columnas clave en la tabla compartida del histórico de renovaciones', function (): void {
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Shared/RenovationHistories/RenovationHistoriesTable.php');
    $infolist = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Shared/RenovationHistories/RenovationHistoryInfolist.php');

    expect($table)
        ->toContain('Histórico de renovaciones')
        ->toContain('affiliation.agency')
        ->toContain('affiliation.agent')
        ->toContain('accepted_at')
        ->toContain('accepted_by')
        ->toContain('vigencia_resumen')
        ->toContain('is_negotiation_candidate')
        ->toContain('Negociación Plan Especial');

    expect($infolist)
        ->toContain('Renovación aceptada')
        ->toContain('accepted_by')
        ->toContain('affiliation.nro_identificacion_ti')
        ->toContain('affiliation.agency.name_corporative')
        ->toContain('affiliation.agent.name');
});

it('precarga relaciones en el recurso de histórico de renovaciones', function (): void {
    expect(file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/AffiliationRenovationHistories/AffiliationRenovationHistoryResource.php'))
        ->toContain('affiliation.agency')
        ->toContain('affiliation.agent')
        ->toContain('ageRange');

    expect(file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationRenovationHistories/AffiliationRenovationHistoryResource.php'))
        ->toContain('affiliation.agency')
        ->toContain('affiliation.agent')
        ->toContain('ageRange');
});
