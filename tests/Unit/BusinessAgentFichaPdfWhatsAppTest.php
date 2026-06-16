<?php

declare(strict_types=1);

use App\Models\Agent;
use App\Services\AgentFichaPdfService;

it('define ruta pública de almacenamiento para whatsapp de ficha de agente', function (): void {
    $agent = new Agent;
    $agent->forceFill(['id' => 42, 'code_agent' => 'AGT-TEST']);

    expect(AgentFichaPdfService::whatsappStorageRelativePath($agent))
        ->toBe('business-fichas/agents/ficha-agente-AGT-00042.pdf');
});

it('genera caption de whatsapp para ficha de agente', function (): void {
    $agent = new Agent;
    $agent->forceFill(['id' => 7, 'name' => 'Juan Pérez', 'code_agent' => 'AGT-007']);

    $caption = AgentFichaPdfService::whatsappCaption($agent);

    expect($caption)
        ->toContain('Ficha de agente')
        ->toContain('Juan Pérez')
        ->toContain('AGT-007');
});

it('job de whatsapp de ficha de agente usa la api de documentos', function (): void {
    $path = dirname(__DIR__, 2).'/app/Jobs/SendBusinessAgentFichaPdfWhatsAppJob.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('NotificationController::sendWhatsAppDocument')
        ->toContain('AgentFichaPdfService::persistForWhatsApp');
});

it('trait BusinessAgentFichaPdfWhatsAppTest agente expone envío whatsapp por api', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Agents/Concerns/QueuesAgentFichaPdfEmail.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('queueAgentFichaPdfWhatsApp')
        ->toContain('SendBusinessAgentFichaPdfWhatsAppJob::dispatch');
});
