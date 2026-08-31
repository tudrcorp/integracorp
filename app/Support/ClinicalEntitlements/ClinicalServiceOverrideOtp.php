<?php

declare(strict_types=1);

namespace App\Support\ClinicalEntitlements;

use App\Enums\SystemNotificationKey;
use App\Jobs\SendNotificacionWhatsApp;
use App\Mail\ClinicalServiceOverrideOtpMail;
use App\Models\ClinicalServiceOverrideChallenge;
use App\Models\TelemedicinePatient;
use App\Models\User;
use App\Services\HelpdeskTicketAssigneeWhatsAppService;
use App\Support\SecurityAudit;
use App\Support\SystemNotificationRecipients;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

final class ClinicalServiceOverrideOtp
{
    public static function userMayOverride(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        $departments = is_array($user->departament) ? $user->departament : [];
        if (in_array('ATENMEDI', $departments, true)) {
            return false;
        }

        if ($user->supplier_id !== null || $user->isProveedorAmd()) {
            return false;
        }

        $isTelemedicineStaff = in_array('TELEMEDICINA', $departments, true);
        if (! $isTelemedicineStaff && ! (bool) $user->is_doctor) {
            return false;
        }

        return true;
    }

    /**
     * @return array{challenge: ClinicalServiceOverrideChallenge, delivered: bool, emails: int, phones: int}
     */
    public static function issue(
        User $user,
        TelemedicinePatient $patient,
        ClinicalEntitlement $entitlement,
        string $reason,
        ?int $caseId = null,
    ): array {
        if (! self::userMayOverride($user)) {
            throw ClinicalEntitlementException::unauthorized(
                'Solo un médico de TDG puede solicitar autorización fuera de límite. Escale el caso a TDG.'
            );
        }

        $reason = trim($reason);
        $min = (int) config('clinical-entitlements.otp.reason_min_length', 10);
        if (mb_strlen($reason) < $min) {
            throw ClinicalEntitlementException::unauthorized(
                'El motivo es obligatorio (mínimo '.$min.' caracteres) para pedir un servicio extra.'
            );
        }

        $key = SystemNotificationKey::TelemedicineServiceLimitOverride;
        if (! SystemNotificationRecipients::isActive($key)) {
            throw ClinicalEntitlementException::unauthorized(
                'No se puede autorizar fuera de límite: la alerta está pausada en el centro de notificaciones.'
            );
        }

        $emails = SystemNotificationRecipients::emails($key);
        $phones = SystemNotificationRecipients::phones($key);
        if ($emails === [] && $phones === []) {
            throw ClinicalEntitlementException::unauthorized(
                'No se puede autorizar fuera de límite: configure destinatarios en el centro de notificaciones.'
            );
        }

        $patient->loadMissing(['plan']);

        ClinicalServiceOverrideChallenge::query()
            ->where('user_id', $user->id)
            ->where('telemedicine_patient_id', $patient->id)
            ->where('channel', $entitlement->channel->value)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $digits = (int) config('clinical-entitlements.otp.digits', 6);
        $ttl = (int) config('clinical-entitlements.otp.ttl_minutes', 5);
        $code = self::generateNumericCode($digits);

        $challenge = ClinicalServiceOverrideChallenge::query()->create([
            'public_id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'telemedicine_patient_id' => $patient->id,
            'telemedicine_case_id' => $caseId,
            'plan_id' => $patient->plan_id,
            'benefit_id' => $entitlement->benefitId,
            'channel' => $entitlement->channel->value,
            'telemedicine_service_list_id' => $entitlement->telemedicineServiceListId,
            'reason' => $reason,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes($ttl),
            'attempt_count' => 0,
            'max_attempts' => (int) config('clinical-entitlements.otp.max_attempts', 3),
            'last_sent_at' => now(),
        ]);

        $payload = ClinicalServiceOverrideNotificationMessage::payload(
            $user,
            $patient,
            $entitlement,
            $code,
            $reason,
            $ttl,
        );

        return self::deliver($challenge, $payload, $emails, $phones, 'AUDIT_CLINICAL_LIMIT_OVERRIDE_OTP_SENT');
    }

    /**
     * @return array{challenge: ClinicalServiceOverrideChallenge, delivered: bool, emails: int, phones: int}
     */
    public static function resend(
        ClinicalServiceOverrideChallenge $challenge,
        User $user,
        TelemedicinePatient $patient,
        ClinicalEntitlement $entitlement,
    ): array {
        if (! self::userMayOverride($user) || (int) $challenge->user_id !== (int) $user->id) {
            throw ClinicalEntitlementException::unauthorized(
                'Solo el médico que pidió la clave puede reenviarla.'
            );
        }

        if ($challenge->isConsumed()) {
            throw ClinicalEntitlementException::unauthorized(
                'Esta clave ya se usó. Solicite una autorización nueva.'
            );
        }

        $wait = $challenge->secondsUntilResend();
        if ($wait > 0) {
            throw ClinicalEntitlementException::unauthorized(
                'Espere '.$wait.' segundos para reenviar la clave.'
            );
        }

        $key = SystemNotificationKey::TelemedicineServiceLimitOverride;
        if (! SystemNotificationRecipients::isActive($key)) {
            throw ClinicalEntitlementException::unauthorized(
                'No se puede reenviar: la alerta está pausada en el centro de notificaciones.'
            );
        }

        $emails = SystemNotificationRecipients::emails($key);
        $phones = SystemNotificationRecipients::phones($key);
        if ($emails === [] && $phones === []) {
            throw ClinicalEntitlementException::unauthorized(
                'No hay destinatarios en el centro de notificaciones.'
            );
        }

        $digits = (int) config('clinical-entitlements.otp.digits', 6);
        $ttl = (int) config('clinical-entitlements.otp.ttl_minutes', 5);
        $code = self::generateNumericCode($digits);

        $challenge->forceFill([
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes($ttl),
            'attempt_count' => 0,
            'last_sent_at' => now(),
        ])->save();

        $payload = ClinicalServiceOverrideNotificationMessage::payload(
            $user,
            $patient,
            $entitlement,
            $code,
            (string) $challenge->reason,
            $ttl,
        );

        return self::deliver($challenge, $payload, $emails, $phones, 'AUDIT_CLINICAL_LIMIT_OVERRIDE_OTP_RESENT');
    }

    public static function verify(ClinicalServiceOverrideChallenge $challenge, string $code, int $userId): bool
    {
        if ((int) $challenge->user_id !== $userId) {
            return false;
        }

        if (! $challenge->isActive()) {
            return false;
        }

        $challenge->increment('attempt_count');
        $challenge->refresh();

        $digits = preg_replace('/\D+/', '', $code) ?? '';
        if (! Hash::check($digits, $challenge->code_hash)) {
            SecurityAudit::log('AUDIT_CLINICAL_LIMIT_OVERRIDE_OTP_FAILED', 'telemedicina.clinical-limit.otp', [
                'challenge_public_id' => $challenge->public_id,
                'attempts' => $challenge->attempt_count,
            ]);

            return false;
        }

        return true;
    }

    public static function assertValidFor(
        ClinicalServiceOverrideChallenge $challenge,
        TelemedicinePatient $patient,
        ClinicalEntitlement $entitlement,
        int $userId,
    ): void {
        if ((int) $challenge->user_id !== $userId
            || (int) $challenge->telemedicine_patient_id !== (int) $patient->id
            || (int) $challenge->benefit_id !== $entitlement->benefitId
            || $challenge->channel !== $entitlement->channel
            || $challenge->isConsumed()
            || $challenge->isExpired()) {
            throw ClinicalEntitlementException::unauthorized(
                'La clave de autorización no es válida para este servicio. Solicite una nueva.'
            );
        }
    }

    public static function markConsumed(ClinicalServiceOverrideChallenge $challenge): void
    {
        $challenge->forceFill(['consumed_at' => now()])->save();

        SecurityAudit::log('AUDIT_CLINICAL_LIMIT_OVERRIDE_OTP_USED', 'telemedicina.clinical-limit.otp', [
            'challenge_public_id' => $challenge->public_id,
            'patient_id' => $challenge->telemedicine_patient_id,
            'benefit_id' => $challenge->benefit_id,
            'channel' => $challenge->channel instanceof \App\Enums\ClinicalServiceChannel
                ? $challenge->channel->value
                : $challenge->channel,
        ]);
    }

    public static function generateNumericCode(int $digits): string
    {
        $max = (10 ** $digits) - 1;

        return str_pad((string) random_int(0, $max), $digits, '0', STR_PAD_LEFT);
    }

    /**
     * @param  list<string>  $emails
     * @param  list<string>  $phones
     * @param  array<string, mixed>  $payload
     * @return array{challenge: ClinicalServiceOverrideChallenge, delivered: bool, emails: int, phones: int}
     */
    private static function deliver(
        ClinicalServiceOverrideChallenge $challenge,
        array $payload,
        array $emails,
        array $phones,
        string $auditEvent,
    ): array {
        $emailsSent = 0;
        $phonesSent = 0;

        foreach ($emails as $email) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            try {
                Mail::to($email)->send(new ClinicalServiceOverrideOtpMail(
                    emailPayload: $payload,
                    recipientEmail: $email,
                    subjectLine: ClinicalServiceOverrideNotificationMessage::emailSubject($payload),
                ));
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
                SendNotificacionWhatsApp::dispatchSync(null, ClinicalServiceOverrideNotificationMessage::whatsappBody($payload), $phone, null, [
                    'panel' => 'telemedicina',
                    'source' => 'telemedicine.clinical-limit-override',
                    'patient_id' => $challenge->telemedicine_patient_id,
                ]);
                $phonesSent++;
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        $challenge->forceFill([
            'emails_sent' => $emailsSent,
            'phones_sent' => $phonesSent,
        ])->save();

        SecurityAudit::log($auditEvent, 'telemedicina.clinical-limit.otp', [
            'challenge_public_id' => $challenge->public_id,
            'patient_id' => $challenge->telemedicine_patient_id,
            'benefit_id' => $challenge->benefit_id,
            'channel' => $challenge->channel instanceof \App\Enums\ClinicalServiceChannel
                ? $challenge->channel->value
                : $challenge->channel,
            'emails_sent' => $emailsSent,
            'phones_sent' => $phonesSent,
        ]);

        if ($emailsSent === 0 && $phonesSent === 0) {
            $challenge->forceFill(['consumed_at' => now()])->save();
            throw ClinicalEntitlementException::unauthorized(
                'No se pudo entregar la clave por correo ni WhatsApp. Intente de nuevo o revise los destinatarios.'
            );
        }

        return [
            'challenge' => $challenge->fresh() ?? $challenge,
            'delivered' => true,
            'emails' => $emailsSent,
            'phones' => $phonesSent,
        ];
    }
}
