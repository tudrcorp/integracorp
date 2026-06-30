<?php

declare(strict_types=1);

use App\Support\GuiaChat\GuiaChatPublicUrl;

uses(Tests\TestCase::class);

it('expone acción de header para enviar guia-chat por whatsapp en negocios agentes', function (): void {
    $listPath = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Agents/Pages/ListAgents.php';
    $contents = file_get_contents($listPath);

    expect($contents)
        ->toContain('send_guia_chat_link')
        ->toContain('Enviar GUIA-CHAT')
        ->toContain('heroicon-m-chat-bubble-left-right')
        ->toContain('Enviar enlace de GUIA-CHAT por WhatsApp')
        ->toContain('GuiaChatPublicUrl::url()')
        ->toContain('NotificationController::send_guia_chat_link_wp')
        ->toContain('AUDIT_BUSINESS_AGENT_GUIA_CHAT_LINK_WHATSAPP_SENT')
        ->toContain('business.agents.send-guia-chat-link');
});

it('expone acción de header para enviar guia-chat por whatsapp en negocios agencias', function (): void {
    $listPath = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Agencies/Pages/ListAgencies.php';
    $contents = file_get_contents($listPath);

    expect($contents)
        ->toContain('send_guia_chat_link')
        ->toContain('Enviar GUIA-CHAT')
        ->toContain('GuiaChatPublicUrl::url()')
        ->toContain('NotificationController::send_guia_chat_link_wp')
        ->toContain('AUDIT_BUSINESS_AGENCY_GUIA_CHAT_LINK_WHATSAPP_SENT')
        ->toContain('business.agencies.send-guia-chat-link');
});

it('construye la url publica de guia-chat desde integracorp', function (): void {
    config()->set('parameters.INTEGRACORP_URL', 'https://integracorp.example');

    expect(GuiaChatPublicUrl::url())->toBe('https://integracorp.example/chat/publico');
});

it('notifica guia-chat por whatsapp con formato de imagen', function (): void {
    $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/NotificationController.php');

    expect($controller)
        ->toContain('public static function send_guia_chat_link_wp')
        ->toContain('CURLOPT_URL_IMAGE')
        ->toContain('GUIA-CHAT');
});
