<?php

declare(strict_types=1);

it('formulario publico de agencias tdev nivel 3 expone livewire, vista y ruta', function (): void {
    $livewire = file_get_contents(dirname(__DIR__, 2).'/app/Livewire/TdevAgencyRegistration.php');
    $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/tdev-agency-registration.blade.php');
    $routes = file_get_contents(dirname(__DIR__, 2).'/routes/web.php');

    expect($livewire)
        ->toContain('TdevAgencyRegistration')
        ->toContain('agency_registration_token')
        ->toContain('LEVEL_TWO')
        ->toContain('TdevAgencyRegistrar::registerLevelThreeAgency')
        ->toContain('publicAgentRegistrationUrl')
        ->toContain('createdAgencyAgentRegistrationUrl')
        ->toContain('askAssociateAgents')
        ->toContain('associateAgentsNow')
        ->toContain('skipAssociateAgents')
        ->toContain('WithFileUploads')
        ->toContain("'name' => ['required', 'string', 'max:255']")
        ->toContain('identificationNumber')
        ->toContain('representativeName')
        ->toContain('instagramUsername')
        ->toContain('countryId')
        ->toContain('stateId')
        ->toContain('cityId')
        ->toContain("layout('layouts.tdev-agent-registration')");

    expect($view)
        ->toContain('$parentAgency->logoUrl()')
        ->toContain('asset(\'image/logo-tdev.png\')')
        ->toMatch('/logoUrl\(\).*logo-tdev\.png/s')
        ->toContain('wire:model="name"')
        ->toContain('wire:model="identificationNumber"')
        ->toContain('wire:model="email"')
        ->toContain('wire:model="anniversaryDate"')
        ->toContain('wire:model="representativeName"')
        ->toContain('wire:model="representativeBirthDate"')
        ->toContain('wire:model="phone"')
        ->toContain('wire:model="phoneAdditional"')
        ->toContain('wire:model="instagramUsername"')
        ->toContain('wire:model.live="countryId"')
        ->toContain('wire:model.live="stateId"')
        ->toContain('wire:model="cityId"')
        ->toContain('wire:model="address"')
        ->toContain('wire:model="url"')
        ->toContain('wire:model="logo"')
        ->toContain('Registro de agencia')
        ->toContain('Nivel 3')
        ->toContain('¿Deseas asociar agentes a esta agencia?')
        ->toContain('associateAgentsNow')
        ->toContain('skipAssociateAgents')
        ->toContain('createdAgencyAgentRegistrationUrl');

    expect($routes)
        ->toContain('/tdev/agencia/{token}')
        ->toContain('TdevAgencyRegistration::class')
        ->toContain("name('tdev-agencies.register')")
        ->toMatch('/tdev\/agencia\/\{token\}[\s\S]*tdev\/\{token\}/');
});
