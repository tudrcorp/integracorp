<?php

declare(strict_types=1);

it('muestra información principal en el título de vista de agente business', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Agents/Pages/ViewAgent.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('public function getTitle(): string|Htmlable')
        ->toContain('Agente: ')
        ->toContain('code_agent')
        ->toContain('badgeStyleForStatus')
        ->toContain('email')
        ->toContain('phone');
});
