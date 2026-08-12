<?php

declare(strict_types=1);

it('formulario publico de agentes tdev expone livewire, layout y ruta', function (): void {
    $livewire = file_get_contents(dirname(__DIR__, 2).'/app/Livewire/TdevAgentRegistration.php');
    $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/tdev-agent-registration.blade.php');
    $layout = file_get_contents(dirname(__DIR__, 2).'/resources/views/layouts/tdev-agent-registration.blade.php');
    $routes = file_get_contents(dirname(__DIR__, 2).'/routes/web.php');

    expect($livewire)
        ->toContain('TdevAgentRegistration')
        ->toContain('registration_token')
        ->toContain('with(\'parentAgency\')')
        ->toContain('TdevAgencyRegistrar::registerAgent')
        ->toContain('startNewRegistration')
        ->toContain("'fullName' => ['required', 'string', 'max:255']")
        ->toContain("layout('layouts.tdev-agent-registration'")
        ->toContain('faviconUrl')
        ->toContain('faviconUrl()');

    expect($view)
        ->toContain('$agency->logoUrl()')
        ->toContain('asset(\'image/logo-tdev.png\')')
        ->toMatch('/logoUrl\(\).*logo-tdev\.png/s')
        ->toContain('isLevelThree()')
        ->toContain('parentAgency')
        ->toContain('wire:model="fullName"')
        ->toContain('wire:model="position"')
        ->toContain('wire:model="email"')
        ->toContain('wire:model="phone"')
        ->toContain('wire:model="birthDate"')
        ->toContain('glass-panel')
        ->toContain('btn-accent')
        ->toContain('Registro de agente');

    expect($layout)
        ->toContain('--accent: #2299A4')
        ->toContain('backdrop-filter: blur(40px)')
        ->toContain('liquid-orbs')
        ->toContain('SF Pro Display')
        ->toContain('rel="icon"')
        ->toContain('faviconUrl')
        ->toContain('apple-touch-icon');

    expect($routes)
        ->toContain('/tdev/{token}')
        ->toContain('TdevAgentRegistration::class')
        ->toContain("name('tdev-agents.register')");
});
