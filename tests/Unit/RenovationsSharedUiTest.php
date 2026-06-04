<?php

declare(strict_types=1);

it('muestra cédula, agencia y agente en la tabla compartida de renovaciones', function (): void {
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Shared/Renovations/RenovationsTable.php');
    $infolist = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Shared/Renovations/RenovationInfolist.php');

    expect($table)
        ->toContain('affiliation.agency')
        ->toContain('affiliation.agent')
        ->toContain('affiliation.plan')
        ->toContain('affiliation.coverage')
        ->toContain('affiliation.nro_identificacion_ti')
        ->toContain('affiliation.agency.name_corporative')
        ->toContain('affiliation.agent.name')
        ->toContain('Expediente vigente (antes)')
        ->toContain('Si acepta renovación')
        ->toContain('affiliation.effective_date')
        ->toContain('affiliation.fee_anual')
        ->toContain('renewal_delta_summary')
        ->toContain('remaining_days_range')
        ->toContain('resolveRemainingDaysRange');

    expect($infolist)
        ->toContain('affiliation.nro_identificacion_ti')
        ->toContain('affiliation.agency.name_corporative')
        ->toContain('affiliation.agent.name');
});

it('usa helperText en el historial de renovaciones del infolist de afiliación', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Shared/Affiliations/AffiliationRenovationHistoryInfolist.php');

    expect($source)
        ->toContain('->helperText(')
        ->toContain('Heroicon::CheckCircle')
        ->toContain('acceptance_badge')
        ->not->toMatch('/TextEntry::make\([^)]+\)[\s\S]*?->description\(/');
});

it('precarga afiliación, agencia y agente en el recurso de renovaciones', function (): void {
    expect(file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Renovations/RenovationResource.php'))
        ->toContain('affiliation.agency')
        ->toContain('affiliation.agent');

    expect(file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Renovations/RenovationResource.php'))
        ->toContain('affiliation.agency')
        ->toContain('affiliation.agent');
});
