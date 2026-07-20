<?php

declare(strict_types=1);

it('CreateAgent provisiona usuario con clave por defecto e is_agent', function (): void {
    $src = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Agents/Resources/Agents/Pages/CreateAgent.php');
    expect($src)->not->toBeFalse();

    expect($src)
        ->toContain('afterCreate')
        ->toContain("Hash::make('12345678')")
        ->toContain('is_agent = true')
        ->toContain('agent_id = $record->id')
        ->toContain('code_agent = \'AGT-000\'.$record->id')
        ->toContain('sendCartaBienvenida')
        ->toContain('NotificationController::agent_activated')
        ->toContain('PATH_SUBAGENT')
        ->toContain("'12345678'");
});

it('el job de carta de bienvenida envía copia oculta a solrodriguez', function (): void {
    $job = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/SendCartaBienvenidaAgenteAgencia.php');
    expect($job)->toContain("->bcc('solrodriguez@tudrencasa.com')");
});
