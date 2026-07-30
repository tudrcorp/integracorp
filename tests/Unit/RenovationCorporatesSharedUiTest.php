<?php

declare(strict_types=1);

it('muestra empresa, rif, agencia y agente en la tabla compartida corporativa', function (): void {
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Shared/RenovationCorporates/RenovationsCorporateTable.php');
    $infolist = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Shared/RenovationCorporates/RenovationCorporateInfolist.php');

    expect($table)
        ->toContain('affiliationCorporate.agency')
        ->toContain('affiliationCorporate.agent')
        ->toContain('affiliationCorporate.name_corporate')
        ->toContain('affiliationCorporate.rif')
        ->toContain('affiliationCorporate.agency.name_corporative')
        ->toContain('affiliationCorporate.agent.name')
        ->toContain('Expediente vigente (antes)')
        ->toContain('Si acepta renovación')
        ->toContain('affiliationCorporate.effective_date')
        ->toContain('affiliationCorporate.fee_anual')
        ->toContain('remaining_days_range');

    expect($infolist)
        ->toContain('affiliationCorporate.rif')
        ->toContain('affiliationCorporate.agency.name_corporative')
        ->toContain('affiliationCorporate.agent.name');
});

it('precarga afiliación corporativa, agencia y agente en los recursos', function (): void {
    expect(file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/RenovationCorporates/RenovationCorporateResource.php'))
        ->toContain('affiliationCorporate.agency')
        ->toContain('affiliationCorporate.agent');

    expect(file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/RenovationCorporates/RenovationCorporateResource.php'))
        ->toContain('affiliationCorporate.agency')
        ->toContain('affiliationCorporate.agent');
});

it('amplía renovation_corporates con campos tipados de renovations', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_07_30_083714_add_renewal_fields_to_renovation_corporates_table.php');

    expect($source)
        ->toContain('remaining_days')
        ->toContain('plan_id')
        ->toContain('coverage_id')
        ->toContain('age_range_id')
        ->toContain('subtotal_anual')
        ->toContain('is_negotiation_candidate')
        ->toContain('previous_plan_id');
});
