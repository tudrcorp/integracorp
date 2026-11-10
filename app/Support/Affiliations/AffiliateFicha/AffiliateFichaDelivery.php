<?php

declare(strict_types=1);

namespace App\Support\Affiliations\AffiliateFicha;

use App\Enums\AffiliateFichaScope;
use App\Jobs\SendAffiliateFichaEmailJob;
use App\Jobs\SendAffiliateFichaWhatsAppJob;
use App\Models\User;
use App\Services\HelpdeskTicketAssigneeWhatsAppService;
use App\Support\Affiliations\Certificates\CertificateDeliveryRecipients;
use App\Support\SecurityAudit;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Envío de la ficha del afiliado por correo (Para, CC y CCO, uno o varios en
 * cada uno) o por WhatsApp. Valida aquí, en el request, y encola: el PDF se
 * arma en el job ({@see SendAffiliateFichaEmailJob}, {@see SendAffiliateFichaWhatsAppJob}).
 *
 * Cada método devuelve null si encoló, o el mensaje que el analista debe ver.
 */
final class AffiliateFichaDelivery
{
    public const MAX_RECIPIENTS = CertificateDeliveryRecipients::MAX_RECIPIENTS;

    public const MAX_COPIES = CertificateDeliveryRecipients::MAX_COPIES;

    /** Envíos por analista en la ventana: frena un clic repetido o un uso masivo. */
    public const RATE_LIMIT = 20;

    public const RATE_WINDOW_SECONDS = 600;

    /**
     * Reparte y depura los correos: inválidos aparte, sin repetir a nadie entre
     * Para, CC y CCO (gana el campo más visible).
     *
     * @param  iterable<mixed>  $to
     * @param  iterable<mixed>  $cc
     * @param  iterable<mixed>  $bcc
     * @return array{to: list<string>, cc: list<string>, bcc: list<string>, invalid: list<string>}
     */
    public static function splitEmails(iterable $to, iterable $cc, iterable $bcc): array
    {
        $invalid = [];
        $taken = [];
        $result = [];

        foreach (['to' => $to, 'cc' => $cc, 'bcc' => $bcc] as $field => $values) {
            $result[$field] = [];

            foreach ($values as $value) {
                $raw = is_scalar($value) ? trim((string) $value) : '';

                if ($raw === '') {
                    continue;
                }

                $email = CertificateDeliveryRecipients::normalize($raw);

                if ($email === null) {
                    $invalid[] = $raw;

                    continue;
                }

                if (isset($taken[$email])) {
                    continue;
                }

                $taken[$email] = true;
                $result[$field][] = $email;
            }
        }

        return [...$result, 'invalid' => array_values(array_unique($invalid))];
    }

    /**
     * @param  iterable<mixed>  $to
     * @param  iterable<mixed>  $cc
     * @param  iterable<mixed>  $bcc
     */
    public static function queueEmail(
        User $user,
        string $kind,
        int $affiliateId,
        AffiliateFichaScope $scope,
        string $panelId,
        iterable $to,
        iterable $cc,
        iterable $bcc,
        ?string $message,
    ): ?string {
        if ($error = self::authorize($user, $panelId, $scope)) {
            return $error;
        }

        $emails = self::splitEmails($to, $cc, $bcc);

        if ($emails['invalid'] !== []) {
            return 'Revise estos correos, no son válidos: '.implode(', ', $emails['invalid']).'.';
        }

        if ($emails['to'] === []) {
            return 'Indique al menos un destinatario en «Para».';
        }

        if (count($emails['to']) > self::MAX_RECIPIENTS) {
            return 'Puede enviar a un máximo de '.self::MAX_RECIPIENTS.' destinatarios en «Para».';
        }

        if (count($emails['cc']) > self::MAX_COPIES || count($emails['bcc']) > self::MAX_COPIES) {
            return 'Puede agregar hasta '.self::MAX_COPIES.' correos en CC y '.self::MAX_COPIES.' en CCO.';
        }

        if ($error = self::throttle($user)) {
            return $error;
        }

        $message = self::cleanMessage($message);

        SendAffiliateFichaEmailJob::dispatch($kind, $affiliateId, $scope->value, $panelId, (int) $user->getKey(), $emails['to'], $emails['cc'], $emails['bcc'], $message);

        SecurityAudit::log('AUDIT_AFFILIATE_FICHA_EMAIL_QUEUED', 'affiliate-ficha.email', [
            'kind' => $kind,
            'affiliate_id' => $affiliateId,
            'scope' => $scope->value,
            'panel' => $panelId,
            'to' => $emails['to'],
            'cc' => $emails['cc'],
            'bcc' => $emails['bcc'],
        ], $user);

        return null;
    }

    public static function queueWhatsApp(
        User $user,
        string $kind,
        int $affiliateId,
        AffiliateFichaScope $scope,
        string $panelId,
        ?string $phone,
        ?string $message,
    ): ?string {
        if ($error = self::authorize($user, $panelId, $scope)) {
            return $error;
        }

        $phone = trim((string) $phone);

        if ($phone === '') {
            return 'Indique el número de WhatsApp del destinatario.';
        }

        if (! self::isValidWhatsAppPhone($phone)) {
            return 'El número no es válido para WhatsApp. Use, por ejemplo, 04121234567 o +584121234567.';
        }

        if ($error = self::throttle($user)) {
            return $error;
        }

        SendAffiliateFichaWhatsAppJob::dispatch($kind, $affiliateId, $scope->value, $panelId, (int) $user->getKey(), $phone, self::cleanMessage($message));

        SecurityAudit::log('AUDIT_AFFILIATE_FICHA_WHATSAPP_QUEUED', 'affiliate-ficha.whatsapp', [
            'kind' => $kind,
            'affiliate_id' => $affiliateId,
            'scope' => $scope->value,
            'panel' => $panelId,
            'phone' => $phone,
        ], $user);

        return null;
    }

    /**
     * El normalizador compartido acepta cualquier cadena de dígitos («123» → «+123»);
     * aquí además se exige un número completo con código de país: 10 a 15 dígitos.
     */
    public static function isValidWhatsAppPhone(string $phone): bool
    {
        $normalized = HelpdeskTicketAssigneeWhatsAppService::normalizePhoneForWhatsApp($phone);

        return $normalized !== null && preg_match('/^\+\d{10,15}$/', $normalized) === 1;
    }

    private static function authorize(User $user, string $panelId, AffiliateFichaScope $scope): ?string
    {
        if (AffiliateFichaAccess::canUse($user, $panelId, $scope)) {
            return null;
        }

        SecurityAudit::log('AUDIT_AFFILIATE_FICHA_ACCESS_DENIED', 'affiliate-ficha.send', [
            'scope' => $scope->value,
            'panel' => $panelId,
        ], $user);

        return $scope->includesClinical()
            ? 'No tiene permiso para enviar la información de operaciones y telemedicina.'
            : 'No tiene permiso para enviar la ficha de este afiliado.';
    }

    private static function throttle(User $user): ?string
    {
        $allowed = RateLimiter::attempt(
            'affiliate-ficha-send:'.$user->getKey(),
            self::RATE_LIMIT,
            static fn (): bool => true,
            self::RATE_WINDOW_SECONDS,
        );

        return $allowed ? null : 'Envió muchas fichas en pocos minutos. Espere un momento e intente de nuevo.';
    }

    private static function cleanMessage(?string $message): ?string
    {
        $message = trim(strip_tags((string) $message));

        return $message === '' ? null : mb_substr($message, 0, 1000);
    }
}
