<?php

declare(strict_types=1);

use App\Enums\SystemNotificationKey;
use App\Mail\AnulatedQuotesNotificationMail;

uses(Tests\TestCase::class);

it('anula cotizaciones y notifica a destinatarios del centro de notificaciones', function (): void {
    $job = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/AnulateAgentQuotes.php');
    $mail = file_get_contents(dirname(__DIR__, 2).'/app/Mail/AnulatedQuotesNotificationMail.php');
    $console = file_get_contents(dirname(__DIR__, 2).'/routes/console.php');

    expect($job)
        ->toContain('SystemNotificationKey::AgentQuoteAnulation')
        ->toContain('SystemNotificationRecipients::emails')
        ->toContain('SystemNotificationRecipients::phones')
        ->toContain('AnulatedQuotesNotificationMail')
        ->toContain('SendNotificacionWhatsApp::dispatch')
        ->toContain('Centro de notificaciones');

    expect($mail)
        ->toContain('recipientEmail')
        ->toContain('Cotizaciones individuales anuladas');

    expect($console)
        ->toContain('AnulateAgentQuotes')
        ->toContain('agent_quote_anulation');
});

it('mantiene el correo historico como destinatario por defecto', function (): void {
    expect(SystemNotificationKey::AgentQuoteAnulation->defaultEmails())
        ->toBe(['cotizaciones@tudrencasa.com'])
        ->and(SystemNotificationKey::AgentQuoteAnulation->label())
        ->toBe('Anulación de cotizaciones');

    $mail = new AnulatedQuotesNotificationMail(3, 'control@example.com');

    expect($mail->recipientEmail)->toBe('control@example.com')
        ->and($mail->anulatedCount)->toBe(3);
});
