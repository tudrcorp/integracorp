<?php

declare(strict_types=1);

namespace App\Support\AffiliationCorporates\Population;

use App\Enums\SystemNotificationKey;
use App\Jobs\SendNotificacionWhatsApp;
use App\Mail\CorporatePopulationOtpMail;
use App\Models\CorporatePopulationOtpChallenge;
use App\Models\CorporatePopulationUpdate;
use App\Models\User;
use App\Services\HelpdeskTicketAssigneeWhatsAppService;
use App\Support\SecurityAudit;
use App\Support\SystemNotificationRecipients;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Clave OTP para aplicar o revertir una actualización de población corporativa.
 *
 * La clave va a los destinatarios de «Actualización de población corporativa»
 * en el centro de notificaciones (nunca al analista que la pide): ellos se la
 * dictan. Vence en minutos, es de un solo uso y solo sirve para esa carga, ese
 * propósito y ese usuario.
 */
final class CorporatePopulationOtp
{
    public const KEY = SystemNotificationKey::CorporatePopulationUpdate;

    /**
     * @return array{challenge: CorporatePopulationOtpChallenge, emails: int, phones: int}
     */
    public static function issue(User $user, CorporatePopulationUpdate $update, string $purpose): array
    {
        [$emails, $phones] = self::recipients();

        CorporatePopulationOtpChallenge::query()
            ->where('user_id', $user->getKey())
            ->where('corporate_population_update_id', $update->getKey())
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $code = self::generateCode();

        $challenge = CorporatePopulationOtpChallenge::query()->create([
            'public_id' => (string) Str::uuid(),
            'user_id' => $user->getKey(),
            'corporate_population_update_id' => $update->getKey(),
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::ttl()),
            'attempt_count' => 0,
            'max_attempts' => (int) config('clinical-entitlements.otp.max_attempts', 3),
            'last_sent_at' => now(),
        ]);

        return self::deliver($challenge, $update, $user, $code, $emails, $phones, 'AUDIT_CORPORATE_POPULATION_OTP_SENT');
    }

    /**
     * @return array{challenge: CorporatePopulationOtpChallenge, emails: int, phones: int}
     */
    public static function resend(CorporatePopulationOtpChallenge $challenge, User $user, CorporatePopulationUpdate $update): array
    {
        if ((int) $challenge->user_id !== (int) $user->getKey() || $challenge->isConsumed()) {
            throw new RuntimeException('Esta clave ya no se puede reenviar. Solicite una nueva.');
        }

        $wait = $challenge->secondsUntilResend();
        if ($wait > 0) {
            throw new RuntimeException('Espere '.$wait.' segundos para reenviar la clave.');
        }

        [$emails, $phones] = self::recipients();
        $code = self::generateCode();

        $challenge->forceFill([
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::ttl()),
            'attempt_count' => 0,
            'last_sent_at' => now(),
        ])->save();

        return self::deliver($challenge, $update, $user, $code, $emails, $phones, 'AUDIT_CORPORATE_POPULATION_OTP_RESENT');
    }

    /**
     * Valida la clave y la consume. Falla si no es del usuario, la carga o el
     * propósito, si venció o si se agotaron los intentos.
     */
    public static function verifyAndConsume(CorporatePopulationOtpChallenge $challenge, string $code, User $user, CorporatePopulationUpdate $update, string $purpose): bool
    {
        if ((int) $challenge->user_id !== (int) $user->getKey()
            || (int) $challenge->corporate_population_update_id !== (int) $update->getKey()
            || $challenge->purpose !== $purpose
            || ! $challenge->isActive()) {
            return false;
        }

        $challenge->increment('attempt_count');
        $challenge->refresh();

        if (! Hash::check(preg_replace('/\D+/', '', $code) ?? '', (string) $challenge->code_hash)) {
            SecurityAudit::log('AUDIT_CORPORATE_POPULATION_OTP_FAILED', 'business.corporate-population.otp', [
                'challenge_public_id' => $challenge->public_id,
                'update_id' => $update->getKey(),
                'purpose' => $purpose,
                'attempts' => $challenge->attempt_count,
            ], $user);

            return false;
        }

        $challenge->forceFill(['consumed_at' => now()])->save();

        SecurityAudit::log('AUDIT_CORPORATE_POPULATION_OTP_USED', 'business.corporate-population.otp', [
            'challenge_public_id' => $challenge->public_id,
            'update_id' => $update->getKey(),
            'purpose' => $purpose,
        ], $user);

        return true;
    }

    public static function ttl(): int
    {
        return (int) config('clinical-entitlements.otp.ttl_minutes', 5);
    }

    /**
     * @return array{0: list<string>, 1: list<string>}
     */
    private static function recipients(): array
    {
        if (! SystemNotificationRecipients::isActive(self::KEY)) {
            throw new RuntimeException('La clave OTP de actualización de población está pausada en el centro de notificaciones. Pida a un SUPERADMIN que la active.');
        }

        $emails = SystemNotificationRecipients::emails(self::KEY);
        $phones = SystemNotificationRecipients::phones(self::KEY);

        if ($emails === [] && $phones === []) {
            throw new RuntimeException('No hay destinatarios para la clave OTP de actualización de población. Configúrelos en el centro de notificaciones.');
        }

        return [$emails, $phones];
    }

    private static function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * @param  list<string>  $emails
     * @param  list<string>  $phones
     * @return array{challenge: CorporatePopulationOtpChallenge, emails: int, phones: int}
     */
    private static function deliver(CorporatePopulationOtpChallenge $challenge, CorporatePopulationUpdate $update, User $user, string $code, array $emails, array $phones, string $auditEvent): array
    {
        $update->loadMissing('affiliationCorporate');
        $summary = (array) ($update->summary ?? []);
        $isRollback = $challenge->purpose === CorporatePopulationOtpChallenge::PURPOSE_ROLLBACK;

        $payload = [
            'otp_code' => $code,
            'ttl_minutes' => self::ttl(),
            'generatedAt' => now()->timezone((string) config('app.timezone'))->format('d/m/Y H:i'),
            'purpose_label' => $isRollback ? 'Revertir una actualización de población' : 'Aplicar una actualización de población',
            'analyst_name' => (string) $user->name,
            'company' => (string) ($update->affiliationCorporate?->name_corporate ?? '—'),
            'affiliation_code' => (string) ($update->affiliationCorporate?->code ?? '—'),
            'file_name' => (string) $update->file_name,
            'counts' => [
                'Agregar' => (int) ($summary['selected']['ADD'] ?? 0),
                'Reactivar' => (int) ($summary['selected']['REACTIVATE'] ?? 0),
                'Dar de baja' => (int) ($summary['selected']['DEACTIVATE'] ?? 0),
                'Corregir cédula' => (int) ($summary['selected']['FIX_DOCUMENT'] ?? 0),
            ],
        ];

        $subject = ($isRollback ? 'Clave para REVERTIR' : 'Clave para APLICAR').' población · '.$payload['affiliation_code'];
        $whatsapp = '*INTEGRACORP · '.$payload['purpose_label']."*\n\n"
            .'Clave: *'.$code."*\n"
            .'Vence en '.$payload['ttl_minutes']." minutos. Un solo uso.\n\n"
            .'Empresa: '.$payload['company'].' ('.$payload['affiliation_code'].")\n"
            .'Analista: '.$payload['analyst_name']."\n"
            .implode("\n", array_map(static fn (string $label, int $count): string => $label.': '.$count, array_keys($payload['counts']), $payload['counts']))
            ."\n\nDicte la clave solo al analista. No la reenvíe.";

        $emailsSent = 0;
        $phonesSent = 0;

        foreach ($emails as $email) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            try {
                Mail::to($email)->send(new CorporatePopulationOtpMail($payload, $email, $subject));
                $emailsSent++;
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        foreach ($phones as $rawPhone) {
            $phone = HelpdeskTicketAssigneeWhatsAppService::normalizePhoneForWhatsApp($rawPhone);
            if ($phone === null) {
                continue;
            }

            try {
                SendNotificacionWhatsApp::dispatchSync(null, $whatsapp, $phone, null, [
                    'panel' => 'business',
                    'source' => 'business.corporate-population.otp',
                    'update_id' => $update->getKey(),
                ]);
                $phonesSent++;
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        $challenge->forceFill(['emails_sent' => $emailsSent, 'phones_sent' => $phonesSent])->save();

        SecurityAudit::log($auditEvent, 'business.corporate-population.otp', [
            'challenge_public_id' => $challenge->public_id,
            'update_id' => $update->getKey(),
            'purpose' => $challenge->purpose,
            'emails_sent' => $emailsSent,
            'phones_sent' => $phonesSent,
        ], $user);

        if ($emailsSent === 0 && $phonesSent === 0) {
            $challenge->forceFill(['consumed_at' => now()])->save();

            throw new RuntimeException('No se pudo entregar la clave por correo ni por WhatsApp. Revise los destinatarios en el centro de notificaciones.');
        }

        return ['challenge' => $challenge->fresh() ?? $challenge, 'emails' => $emailsSent, 'phones' => $phonesSent];
    }
}
