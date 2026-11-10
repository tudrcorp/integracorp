<?php

declare(strict_types=1);

namespace App\Support\DocumentEmails;

use App\Enums\SystemNotificationKey;
use App\Jobs\SendNotificacionWhatsAppDocument;
use App\Services\HelpdeskTicketAssigneeWhatsAppService;
use App\Support\SystemNotificationRecipients;

/**
 * Testigos de un envío de documentos, configurados en el Centro de notificaciones (Negocios):
 * correos en copia visible (CC) u oculta (CCO) y teléfonos que reciben por WhatsApp el PDF.
 *
 * Con la entrada inactiva no hay testigos, pero el envío al afiliado y al agente sale igual.
 */
final class DocumentEmailWitnesses
{
    /**
     * @return array{active: bool, cc: list<string>, bcc: list<string>, phones: list<string>}
     */
    public static function for(SystemNotificationKey $key): array
    {
        $setting = SystemNotificationRecipients::setting($key);

        if (! $setting->isActive()) {
            return ['active' => false, 'cc' => [], 'bcc' => [], 'phones' => []];
        }

        $byMode = $setting->emailsByMode();

        return [
            'active' => true,
            'cc' => self::cleanEmails($byMode['cc']),
            'bcc' => self::cleanEmails($byMode['bcc']),
            'phones' => array_values(array_unique(array_filter(array_map(
                static fn (string $phone): ?string => HelpdeskTicketAssigneeWhatsAppService::normalizePhoneForWhatsApp($phone),
                $setting->phones(),
            )))),
        ];
    }

    /**
     * Encola un WhatsApp con el PDF a cada teléfono testigo. Va dentro de la transacción del envío:
     * con la cola `database` entra junto con los correos o no entra.
     *
     * @param  list<string>  $phones
     * @param  array<string, mixed>  $auditContext
     * @return int teléfonos encolados
     */
    public static function queueWhatsAppCopies(array $phones, string $caption, string $documentUrl, string $filename, array $auditContext = []): int
    {
        $queued = 0;

        foreach ($phones as $phone) {
            SendNotificacionWhatsAppDocument::dispatch(null, $caption, $phone, $documentUrl, $filename, $auditContext)
                ->onQueue('default');

            $queued++;
        }

        return $queued;
    }

    /**
     * URL pública del PDF con la versión del archivo: el documento se regenera con el mismo nombre
     * y así el servicio de WhatsApp no toma una copia vieja.
     */
    public static function publicDocumentUrl(string $directory, string $filename, string $absolutePath): string
    {
        $version = is_file($absolutePath) ? (string) filemtime($absolutePath) : (string) time();

        return rtrim((string) config('parameters.PUBLIC_URL'), '/').'/'.$directory.'/'.rawurlencode($filename).'?v='.$version;
    }

    /**
     * Texto del WhatsApp testigo: qué documento, a quién se envió y con qué copy.
     *
     * @param  list<array{recipient: string, to: list<string>, template_name: string}>  $deliveries
     */
    public static function caption(string $documentTitle, string $holderName, array $deliveries, ?string $sentBy): string
    {
        $lines = [
            '*Copia testigo · '.$documentTitle.'*',
            '',
            'Titular: '.($holderName !== '' ? $holderName : 'sin nombre'),
        ];

        foreach ($deliveries as $delivery) {
            $who = $delivery['recipient'] === 'AFILIADO' ? 'Afiliado' : 'Agente / agencia';
            $lines[] = '• '.$who.': '.implode(', ', $delivery['to']).' («'.$delivery['template_name'].'»)';
        }

        if (filled($sentBy)) {
            $lines[] = '';
            $lines[] = 'Enviado por: '.$sentBy;
        }

        return implode("\n", $lines);
    }

    /**
     * @param  list<string>  $emails
     * @return list<string>
     */
    private static function cleanEmails(array $emails): array
    {
        $clean = [];

        foreach ($emails as $email) {
            $normalized = strtolower(trim($email));

            if ($normalized !== '' && filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
                $clean[] = $normalized;
            }
        }

        return array_values(array_unique($clean));
    }
}
