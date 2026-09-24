<?php

declare(strict_types=1);

use App\Support\CommercialStructure\TdevExternalId;

it('exige el ID TDEV solo cuando la comisión es mayor a cero', function (mixed $commission, bool $required): void {
    expect(TdevExternalId::isRequired($commission))->toBe($required);
})->with([
    'vacío' => [null, false],
    'cadena vacía' => ['', false],
    'cero' => [0, false],
    'cero decimal' => ['0.00', false],
    'positivo' => [12.5, true],
    'texto con coma' => ['1,5', true],
    'negativo' => [-1, false],
    'no numérico' => ['abc', false],
]);

it('declara los ID TDEV como enteros sin signo y obligatorios en los formularios', function (): void {
    $migration = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_09_24_093149_add_tdev_external_ids_to_agencies_and_agents_tables.php');
    $field = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Shared/CommercialStructure/TdevExternalIdField.php');

    $tab = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Shared/CommercialStructure/TdevIntegrationTab.php');

    expect($migration)
        ->toContain("unsignedInteger('id_agency_tdev')")
        ->toContain("unsignedInteger('id_agent_tdev')")
        ->and($field)
        ->toContain('TdevExternalId::isRequired($get(\'commission_tdev\'))')
        ->and($tab)
        ->toContain("public const LABEL = 'Integración TuDrEnViajes'")
        ->toContain("'commission_tdev'")
        ->toContain("'commission_tdev_renewal'")
        ->toContain("TextInput::make('user_tdev')")
        ->toContain("TextInput::make('amount_asign_credit_tdev')")
        ->toContain('TdevExternalIdField::agent()')
        ->toContain('TdevExternalIdField::agency()')
        ->not->toContain('$identifier->disabled()');

    $agentForms = [
        'app/Filament/Business/Resources/Agents/Schemas/AgentForm.php',
        'app/Filament/Administration/Resources/Agents/Schemas/AgentForm.php',
        'app/Filament/Resources/Agents/Schemas/AgentForm.php',
        'app/Filament/Marketing/Resources/Agents/Schemas/AgentForm.php',
        'app/Filament/Agents/Resources/Agents/Schemas/AgentForm.php',
        'app/Filament/Master/Resources/Agents/Schemas/AgentForm.php',
        'app/Filament/General/Resources/Agents/Schemas/AgentForm.php',
    ];

    foreach ($agentForms as $path) {
        expect(file_get_contents(dirname(__DIR__, 2).'/'.$path))->toContain('TdevIntegrationTab::agent(');
    }

    $agencyForms = [
        'app/Filament/Business/Resources/Agencies/Schemas/AgencyForm.php',
        'app/Filament/Administration/Resources/Agencies/Schemas/AgencyForm.php',
        'app/Filament/Resources/Agencies/Schemas/AgencyForm.php',
        'app/Filament/Marketing/Resources/Agencies/Schemas/AgencyForm.php',
        'app/Filament/Master/Resources/Agencies/Schemas/AgencyForm.php',
        'app/Filament/General/Resources/Agencies/Schemas/AgencyForm.php',
    ];

    foreach ($agencyForms as $path) {
        expect(file_get_contents(dirname(__DIR__, 2).'/'.$path))->toContain('TdevIntegrationTab::agency(');
    }
});
