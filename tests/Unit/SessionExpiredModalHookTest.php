<?php

declare(strict_types=1);

it('registra el modal personalizado de sesión expirada para errores 419 de livewire', function (): void {
    $providerPath = dirname(__DIR__, 2).'/app/Providers/AppServiceProvider.php';
    $modalPath = dirname(__DIR__, 2).'/resources/views/filament/hooks/session-expired-modal.blade.php';

    expect(file_get_contents($providerPath))
        ->toContain('PanelsRenderHook::BODY_END')
        ->toContain('filament.hooks.session-expired-modal');

    expect(file_get_contents($modalPath))
        ->toContain('integracorp-session-expired')
        ->toContain('integracorp-session-modal__hero')
        ->toContain('logoNewTDG.png')
        ->toContain('Tu sesión ha expirado')
        ->toContain('Recargar página')
        ->toContain("Livewire.hook('request'")
        ->toContain('preventDefault()')
        ->toContain('status !== 419');
});
