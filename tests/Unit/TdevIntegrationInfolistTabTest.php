<?php

declare(strict_types=1);

it('muestra la integración TDEV con el logo en una pestaña de la ficha', function (): void {
    $tab = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Shared/CommercialStructure/TdevIntegrationInfolistTab.php');

    expect($tab)
        ->toContain("public const LABEL = 'Integración TuDrEnViajes'")
        ->toContain("asset('image/logo-tdev.png')")
        ->toContain('alt="Tu Doctor En Viajes"')
        ->toContain('->heading(fn (): HtmlString => new HtmlString(self::logoMarkup()))')
        ->not->toContain('bg-slate-950')
        ->not->toContain("TextEntry::make('tdev_integration_logo')")
        ->toContain("IconEntry::make('tdev')")
        ->toContain("TextEntry::make('user_tdev')")
        ->toContain("TextEntry::make('commission_tdev')")
        ->toContain("TextEntry::make('commission_tdev_renewal')")
        ->toContain("TextEntry::make('amount_asign_credit_tdev')")
        ->toContain('TextEntry::make($identifier)');

    $agentInfolists = [
        'app/Filament/Shared/CommercialStructure/AgentInfolist.php',
        'app/Filament/Agents/Resources/Agents/Schemas/AgentInfolist.php',
        'app/Filament/Resources/Agents/Schemas/AgentInfolist.php',
        'app/Filament/Marketing/Resources/Agents/Schemas/AgentInfolist.php',
        'app/Filament/Master/Resources/Agents/Schemas/AgentInfolist.php',
        'app/Filament/General/Resources/Agents/Schemas/AgentInfolist.php',
    ];

    foreach ($agentInfolists as $path) {
        expect(file_get_contents(dirname(__DIR__, 2).'/'.$path))->toContain('TdevIntegrationInfolistTab::agent()');
    }

    $agencyInfolists = [
        'app/Filament/Shared/CommercialStructure/AgencyInfolist.php',
        'app/Filament/Resources/Agencies/Schemas/AgencyInfolist.php',
        'app/Filament/Marketing/Resources/Agencies/Schemas/AgencyInfolist.php',
        'app/Filament/Master/Resources/Agencies/Schemas/AgencyInfolist.php',
        'app/Filament/General/Resources/Agencies/Schemas/AgencyInfolist.php',
    ];

    foreach ($agencyInfolists as $path) {
        expect(file_get_contents(dirname(__DIR__, 2).'/'.$path))->toContain('TdevIntegrationInfolistTab::agency()');
    }

    $delegates = [
        'app/Filament/Business/Resources/Agents/Schemas/AgentInfolist.php' => 'SharedAgentInfolist::configure($schema)',
        'app/Filament/Administration/Resources/Agents/Schemas/AgentInfolist.php' => 'SharedAgentInfolist::configure($schema)',
        'app/Filament/Business/Resources/Agencies/Schemas/AgencyInfolist.php' => 'SharedAgencyInfolist::configure($schema)',
        'app/Filament/Administration/Resources/Agencies/Schemas/AgencyInfolist.php' => 'SharedAgencyInfolist::configure($schema)',
    ];

    foreach ($delegates as $path => $call) {
        expect(file_get_contents(dirname(__DIR__, 2).'/'.$path))->toContain($call);
    }
});
