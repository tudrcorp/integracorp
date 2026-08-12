<?php

declare(strict_types=1);

it('pagina web publica de agencia tdev nivel 2 expone landing, botones y ruta', function (): void {
    $livewire = file_get_contents(dirname(__DIR__, 2).'/app/Livewire/TdevAgencyLanding.php');
    $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/tdev-agency-landing.blade.php');
    $registrar = file_get_contents(dirname(__DIR__, 2).'/app/Support/Tdev/TdevAgencyRegistrar.php');
    $routes = file_get_contents(dirname(__DIR__, 2).'/routes/web.php');
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/TdevAgencies/Tables/TdevAgenciesTable.php');

    expect($livewire)
        ->toContain('TdevAgencyLanding')
        ->toContain('LEVEL_TWO')
        ->toContain('registration_token')
        ->toContain('publicLevelThreeAgencyRegistrationUrl')
        ->toContain('publicAgentRegistrationUrl')
        ->toContain("layout('layouts.tdev-agent-registration'")
        ->toContain('faviconUrl')
        ->toContain('faviconUrl()');

    expect($view)
        ->toContain('h-dvh')
        ->toContain('$agency->logoUrl()')
        ->toContain('$agency->name')
        ->toContain('resolvedLandingSloganLine1')
        ->toContain('resolvedLandingSloganLine2')
        ->not->toContain('Representante:')
        ->not->toContain('Instagram:')
        ->toContain('Registrar agencia')
        ->toContain('Agente freelance')
        ->toContain('$agencyRegistrationUrl')
        ->toContain('$freelanceAgentRegistrationUrl')
        ->toContain('asset(\'image/logo-tdev.png\')');

    expect($registrar)
        ->toContain('publicLandingUrl')
        ->toContain("route('tdev-agencies.landing'");

    expect($routes)
        ->toContain('/tdev/web/{token}')
        ->toContain('TdevAgencyLanding::class')
        ->toContain("name('tdev-agencies.landing')")
        ->toMatch('/tdev\/web\/\{token\}[\s\S]*tdev\/agencia\/\{token\}[\s\S]*tdev\/\{token\}/');

    expect($table)
        ->toContain('openLandingLink')
        ->toContain('publicLandingUrl');
});
