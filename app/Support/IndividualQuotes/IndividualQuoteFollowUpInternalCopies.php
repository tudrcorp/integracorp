<?php

declare(strict_types=1);

namespace App\Support\IndividualQuotes;

use App\Enums\SystemNotificationKey;
use App\Jobs\SendNotificacionWhatsApp;
use App\Mail\IndividualQuoteFollowUpInternalCopyMail;
use App\Services\HelpdeskTicketAssigneeWhatsAppService;
use App\Support\ScheduledTaskRunReport;
use App\Support\SystemNotificationRecipients;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class IndividualQuoteFollowUpInternalCopies
{
    /**
     * @return array{emails: int, whatsapps: int}
     */
    public static function dispatch(
        string $whatsappBody,
        string $allyName,
        string $source,
        string $followUpLabel,
        int $quoteCount,
    ): array {
        $emails = SystemNotificationRecipients::emails(SystemNotificationKey::IndividualQuoteFollowUp);
        $phones = SystemNotificationRecipients::phones(SystemNotificationKey::IndividualQuoteFollowUp);

        if ($emails === [] && $phones === []) {
            return ['emails' => 0, 'whatsapps' => 0];
        }

        $copyBody = self::whatsappCopyBody($whatsappBody);
        $emailsSent = 0;
        $whatsappsQueued = 0;

        foreach ($emails as $email) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                ScheduledTaskRunReport::recordFailure('Correo interno inválido para copia: '.$email);

                continue;
            }

            try {
                Mail::to($email)->send(new IndividualQuoteFollowUpInternalCopyMail(
                    recipientEmail: $email,
                    subjectLine: 'Copia interna · '.$followUpLabel.' · '.$allyName,
                    allyName: $allyName,
                    followUpLabel: $followUpLabel,
                    messageBody: $whatsappBody,
                    quoteCount: $quoteCount,
                ));

                $emailsSent++;
            } catch (Throwable $exception) {
                ScheduledTaskRunReport::recordFailure('Error al enviar copia email a '.$email);
                Log::error('IndividualQuoteFollowUpInternalCopies: error enviando email', [
                    'email' => $email,
                    'source' => $source,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        foreach ($phones as $rawPhone) {
            $phone = HelpdeskTicketAssigneeWhatsAppService::normalizePhoneForWhatsApp($rawPhone);

            if ($phone === null) {
                ScheduledTaskRunReport::recordFailure('Teléfono interno inválido para copia: '.$rawPhone);

                continue;
            }

            try {
                SendNotificacionWhatsApp::dispatch(null, $copyBody, $phone, null, [
                    'panel' => 'system',
                    'source' => $source.'.internal-copy',
                    'ally' => $allyName,
                    'quote_count' => $quoteCount,
                    'internal_copy' => true,
                ]);

                $whatsappsQueued++;
            } catch (Throwable $exception) {
                ScheduledTaskRunReport::recordFailure('Error al despachar copia WhatsApp a '.$phone);
                Log::error('IndividualQuoteFollowUpInternalCopies: error despachando WhatsApp', [
                    'phone' => $phone,
                    'source' => $source,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return [
            'emails' => $emailsSent,
            'whatsapps' => $whatsappsQueued,
        ];
    }

    private static function whatsappCopyBody(string $whatsappBody): string
    {
        return <<<TEXT
        *INTEGRACORP · Copia interna de seguimiento*

        {$whatsappBody}
        TEXT;
    }
}
