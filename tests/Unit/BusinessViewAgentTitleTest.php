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

it('incluye acción de ficha pdf con vista previa y envío en view agent business', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Agents/Pages/ViewAgent.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('agentFichaPreview')
        ->toContain('Ficha PDF')
        ->toContain('agent-ficha-panel')
        ->toContain('QueuesAgentFichaPdfEmail')
        ->toContain('BusinessAgentFichaPdfAccess::userCanAccess');
});

it('expone panel reutilizable de ficha de agente con correo y whatsapp', function (): void {
    $path = dirname(__DIR__, 2).'/resources/views/filament/business/agents/agent-ficha-panel.blade.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('business.agents.ficha-pdf.preview')
        ->toContain('queueAgentFichaPdfEmail')
        ->toContain('queueAgentFichaPdfWhatsApp')
        ->toContain('Enviar por correo')
        ->toContain('Enviar por WhatsApp')
        ->toContain('Enviar WhatsApp');
});
