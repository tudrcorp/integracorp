<?php

declare(strict_types=1);

namespace App\Support\ClinicalEntitlements;

use App\Enums\ClinicalUsageAccessContext;
use App\Jobs\SendNotificacionWhatsApp;
use App\Mail\ClinicalUsageAccessOtpMail;
use App\Models\ClinicalUsageAccessChallenge;
use App\Models\User;
use App\Services\HelpdeskTicketAssigneeWhatsAppService;
use App\Support\Filament\UserNavigationAccess;
use App\Support\SecurityAudit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Throwable;

final class ClinicalUsageAccessOtp
{
    public static function userMayBypass(?User $user): bool
    {
        return $user instanceof User && UserNavigationAccess::isSuperAdmin($user);
    }

    public static function allowsEditingOnCurrentPage(): bool
    {
        $livewire = Livewire::current();
        if ($livewire === null) {
            return true;
        }

        if (! method_exists($livewire, 'clinicalUsageIsUnlocked')) {
            return true;
        }

        return (bool) $livewire->clinicalUsageIsUnlocked();
    }

    /**
     * @return Collection<int, User>
     */
    public static function superAdminRecipients(): Collection
    {
        return User::query()
            ->where('status', 'ACTIVO')
            ->where('departament', 'like', '%SUPERADMIN%')
            ->orderBy('id')
            ->get()
            ->filter(static fn (User $user): bool => UserNavigationAccess::isSuperAdmin($user))
            ->values();
    }

    /**
     * @return array{emails: list<string>, phones: list<string>}
     */
    public static function superAdminContactPoints(): array
    {
        $emails = [];
        $phones = [];

        foreach (self::superAdminRecipients() as $user) {
            $email = strtolower(trim((string) $user->email));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[$email] = $email;
            }

            $phone = HelpdeskTicketAssigneeWhatsAppService::normalizePhoneForWhatsApp($user->phone);
            if ($phone !== null) {
                $phones[$phone] = $phone;
            }
        }

        return [
            'emails' => array_values($emails),
            'phones' => array_values($phones),
        ];
    }

    /**
     * @return array{challenge: ClinicalUsageAccessChallenge, delivered: bool, emails: int, phones: int}
     */
    public static function issue(
        User $analyst,
        ClinicalUsageAccessContext $context,
        ?int $recordId = null,
        ?string $subjectLabel = null,
    ): array {
        if (self::userMayBypass($analyst)) {
            throw ClinicalEntitlementException::unauthorized(
                'Un SUPERADMIN no necesita clave OTP para esta vista.'
            );
        }

        $contacts = self::superAdminContactPoints();
        if ($contacts['emails'] === [] && $contacts['phones'] === []) {
            throw ClinicalEntitlementException::unauthorized(
                'No hay usuarios SUPERADMIN activos con correo o teléfono para entregar la clave. Avise a un SUPERADMIN.'
            );
        }

        self::invalidateOpenChallenges($analyst->id, $context, $recordId);

        $digits = (int) config('clinical-entitlements.otp.digits', 6);
        $ttl = (int) config('clinical-entitlements.otp.ttl_minutes', 5);
        $code = ClinicalServiceOverrideOtp::generateNumericCode($digits);

        $challenge = ClinicalUsageAccessChallenge::query()->create([
            'public_id' => (string) Str::uuid(),
            'user_id' => $analyst->id,
            'context' => $context->value,
            'context_record_id' => $recordId,
            'subject_label' => $subjectLabel,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes($ttl),
            'attempt_count' => 0,
            'max_attempts' => (int) config('clinical-entitlements.otp.max_attempts', 3),
            'last_sent_at' => now(),
        ]);

        $payload = ClinicalUsageAccessNotificationMessage::payload(
            $analyst,
            $context,
            $code,
            $ttl,
            $subjectLabel,
        );

        return self::deliver($challenge, $payload, $contacts['emails'], $contacts['phones'], 'AUDIT_CLINICAL_USAGE_ACCESS_OTP_SENT');
    }

    /**
     * @return array{challenge: ClinicalUsageAccessChallenge, delivered: bool, emails: int, phones: int}
     */
    public static function resend(
        ClinicalUsageAccessChallenge $challenge,
        User $analyst,
        ClinicalUsageAccessContext $context,
        ?int $recordId = null,
        ?string $subjectLabel = null,
    ): array {
        if (self::userMayBypass($analyst) || (int) $challenge->user_id !== (int) $analyst->id) {
            throw ClinicalEntitlementException::unauthorized(
                'Solo el analista que pidió la clave puede reenviarla.'
            );
        }

        if ($challenge->context !== $context
            || (int) ($challenge->context_record_id ?? 0) !== (int) ($recordId ?? 0)) {
            throw ClinicalEntitlementException::unauthorized(
                'Esta clave no corresponde a esta vista. Solicite una nueva.'
            );
        }

        if ($challenge->isConsumed()) {
            throw ClinicalEntitlementException::unauthorized(
                'Esta clave ya se usó. Solicite una nueva para esta visita.'
            );
        }

        $wait = $challenge->secondsUntilResend();
        if ($wait > 0) {
            throw ClinicalEntitlementException::unauthorized(
                'Espere '.$wait.' segundos para reenviar la clave.'
            );
        }

        $contacts = self::superAdminContactPoints();
        if ($contacts['emails'] === [] && $contacts['phones'] === []) {
            throw ClinicalEntitlementException::unauthorized(
                'No hay usuarios SUPERADMIN activos con correo o teléfono para reenviar la clave.'
            );
        }

        $digits = (int) config('clinical-entitlements.otp.digits', 6);
        $ttl = (int) config('clinical-entitlements.otp.ttl_minutes', 5);
        $code = ClinicalServiceOverrideOtp::generateNumericCode($digits);

        $challenge->forceFill([
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes($ttl),
            'attempt_count' => 0,
            'last_sent_at' => now(),
            'subject_label' => $subjectLabel ?? $challenge->subject_label,
        ])->save();

        $payload = ClinicalUsageAccessNotificationMessage::payload(
            $analyst,
            $context,
            $code,
            $ttl,
            $subjectLabel ?? $challenge->subject_label,
        );

        return self::deliver($challenge, $payload, $contacts['emails'], $contacts['phones'], 'AUDIT_CLINICAL_USAGE_ACCESS_OTP_RESENT');
    }

    public static function verify(ClinicalUsageAccessChallenge $challenge, string $code, int $userId): bool
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
            SecurityAudit::log('AUDIT_CLINICAL_USAGE_ACCESS_OTP_FAILED', 'business.clinical-usage.otp', [
                'challenge_public_id' => $challenge->public_id,
                'attempts' => $challenge->attempt_count,
                'context' => $challenge->context instanceof ClinicalUsageAccessContext
                    ? $challenge->context->value
                    : $challenge->context,
            ]);

            return false;
        }

        return true;
    }

    public static function markConsumed(ClinicalUsageAccessChallenge $challenge): void
    {
        $challenge->forceFill(['consumed_at' => now()])->save();

        SecurityAudit::log('AUDIT_CLINICAL_USAGE_ACCESS_OTP_USED', 'business.clinical-usage.otp', [
            'challenge_public_id' => $challenge->public_id,
            'context' => $challenge->context instanceof ClinicalUsageAccessContext
                ? $challenge->context->value
                : $challenge->context,
            'context_record_id' => $challenge->context_record_id,
        ]);
    }

    private static function invalidateOpenChallenges(int $userId, ClinicalUsageAccessContext $context, ?int $recordId): void
    {
        $query = ClinicalUsageAccessChallenge::query()
            ->where('user_id', $userId)
            ->where('context', $context->value)
            ->whereNull('consumed_at');

        if ($recordId === null) {
            $query->whereNull('context_record_id');
        } else {
            $query->where('context_record_id', $recordId);
        }

        $query->update(['consumed_at' => now()]);
    }

    /**
     * @param  list<string>  $emails
     * @param  list<string>  $phones
     * @param  array<string, mixed>  $payload
     * @return array{challenge: ClinicalUsageAccessChallenge, delivered: bool, emails: int, phones: int}
     */
    private static function deliver(
        ClinicalUsageAccessChallenge $challenge,
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
                Mail::to($email)->send(new ClinicalUsageAccessOtpMail(
                    emailPayload: $payload,
                    recipientEmail: $email,
                    subjectLine: ClinicalUsageAccessNotificationMessage::emailSubject($payload),
                ));
                $emailsSent++;
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        foreach ($phones as $phone) {
            try {
                SendNotificacionWhatsApp::dispatchSync(null, ClinicalUsageAccessNotificationMessage::whatsappBody($payload), $phone, null, [
                    'panel' => 'business',
                    'source' => 'business.clinical-usage-access',
                    'challenge_public_id' => $challenge->public_id,
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

        SecurityAudit::log($auditEvent, 'business.clinical-usage.otp', [
            'challenge_public_id' => $challenge->public_id,
            'context' => $challenge->context instanceof ClinicalUsageAccessContext
                ? $challenge->context->value
                : $challenge->context,
            'emails_sent' => $emailsSent,
            'phones_sent' => $phonesSent,
        ]);

        if ($emailsSent === 0 && $phonesSent === 0) {
            $challenge->forceFill(['consumed_at' => now()])->save();
            throw ClinicalEntitlementException::unauthorized(
                'No se pudo entregar la clave por correo ni WhatsApp a los SUPERADMIN. Intente de nuevo.'
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
