<?php

declare(strict_types=1);

namespace App\Support\Otp;

use App\Enums\SystemOtpCodeKind;
use App\Jobs\SendOtpSelfAuthorizationNoticeJob;
use App\Models\SystemOtpCode;
use App\Models\User;
use App\Support\ClinicalEntitlements\ClinicalUsageAccessOtp;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry as Registry;
use App\Support\Filament\UserNavigationAccess;
use App\Support\SecurityAudit;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use Throwable;

/**
 * «Usar la clave que yo solicité»: quien tiene el permiso de su tipo de clave
 * puede seguir sin esperar a que el autorizador le dicte la OTP.
 *
 * No es un atajo sin control:
 *  - Es la misma clave de la solicitud: mismo vencimiento, mismo límite de
 *    intentos, un solo uso. El servidor la toma del registro de «Claves OTP»;
 *    nunca viaja al navegador.
 *  - Exige un motivo y queda marcada como autoautorizada en el registro y en
 *    la bitácora de seguridad.
 *  - Se avisa después a quienes habrían recibido la clave (o a los SUPERADMIN
 *    si no hay destinatarios).
 *  - Si la clave no se pudo entregar, el proceso no se cae para quien tiene el
 *    permiso: la solicitud queda viva para que la use él mismo.
 */
final class SelfAuthorizedOtp
{
    public const REASON_MIN_LENGTH = 15;

    public static function permissionFor(SystemOtpCodeKind $kind): string
    {
        return match ($kind) {
            SystemOtpCodeKind::PopulationApply => Registry::SELF_OTP_POPULATION_APPLY,
            SystemOtpCodeKind::PopulationRollback => Registry::SELF_OTP_POPULATION_ROLLBACK,
            SystemOtpCodeKind::ClinicalUsageAccess => Registry::SELF_OTP_CLINICAL_USAGE_ACCESS,
            SystemOtpCodeKind::ClinicalLimitOverride => Registry::SELF_OTP_CLINICAL_LIMIT_OVERRIDE,
        };
    }

    /**
     * Módulo donde se concede el permiso (el panel donde se usa la clave).
     */
    public static function moduleFor(SystemOtpCodeKind $kind): string
    {
        return $kind === SystemOtpCodeKind::ClinicalLimitOverride ? 'TELEMEDICINA' : Registry::OWNER_MODULE;
    }

    public static function userCan(?User $user, SystemOtpCodeKind $kind): bool
    {
        return $user instanceof User
            && UserNavigationAccess::canPerformModuleAction($user, self::moduleFor($kind), self::permissionFor($kind));
    }

    /**
     * Clave vigente de la solicitud, solo para su dueño con permiso. Null si no
     * hay una clave registrada sin usar (venció, se usó o se pidió otra).
     */
    public static function pendingCode(Model $challenge, User $user, SystemOtpCodeKind $kind): ?string
    {
        if (! self::userCan($user, $kind) || (int) $challenge->getAttribute('user_id') !== (int) $user->getKey()) {
            return null;
        }

        if ($challenge->getAttribute('consumed_at') !== null || now()->greaterThan($challenge->getAttribute('expires_at'))) {
            return null;
        }

        $record = SystemOtpCode::query()
            ->where('challenge_type', $challenge->getMorphClass())
            ->where('challenge_id', $challenge->getKey())
            ->orderByDesc('id')
            ->first();

        if (! $record instanceof SystemOtpCode || $record->used_at !== null) {
            return null;
        }

        try {
            $code = (string) $record->code;
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        return $code !== '' ? $code : null;
    }

    /**
     * Código para continuar: el de la solicitud si el usuario marcó «usar mi clave»,
     * o el que escribió. Lanza con un mensaje claro si no procede.
     */
    public static function resolveCode(Model $challenge, User $user, SystemOtpCodeKind $kind, bool $useOwn, ?string $reason, ?string $typed): string
    {
        if (! $useOwn) {
            return (string) $typed;
        }

        if (! self::userCan($user, $kind)) {
            throw new RuntimeException('No tiene permiso para usar su propia clave en esta operación. Pídasela al autorizador.');
        }

        self::assertReason((string) $reason);

        $code = self::pendingCode($challenge, $user, $kind);
        if ($code === null) {
            throw new RuntimeException('La clave de esta solicitud ya venció o se usó. Solicite una nueva.');
        }

        return $code;
    }

    public static function assertReason(string $reason): string
    {
        $reason = trim((string) preg_replace('/\s+/u', ' ', $reason));
        if (mb_strlen($reason) < self::REASON_MIN_LENGTH) {
            throw new RuntimeException('Explique por qué usa su propia clave (mínimo '.self::REASON_MIN_LENGTH.' caracteres).');
        }

        return mb_substr($reason, 0, 1000);
    }

    /**
     * Deja la traza de una clave autoautorizada (ya validada y consumida) y avisa
     * a quienes habrían dado la clave.
     *
     * @param  list<string>  $emails
     * @param  list<string>  $phones
     */
    public static function recordUse(SystemOtpCodeKind $kind, Model $challenge, User $user, string $reason, string $subject, array $emails, array $phones): void
    {
        $reason = self::assertReason($reason);

        try {
            $record = SystemOtpCode::query()
                ->where('challenge_type', $challenge->getMorphClass())
                ->where('challenge_id', $challenge->getKey())
                ->orderByDesc('id')
                ->first();

            if ($record instanceof SystemOtpCode) {
                $record->forceFill(['details' => [
                    ...(array) $record->details,
                    'Autoautorizada por' => (string) $user->name,
                    'Motivo de autoautorización' => $reason,
                ]])->save();
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        SecurityAudit::log('AUDIT_OTP_SELF_AUTHORIZED', 'otp.self-authorized', [
            'kind' => $kind->value,
            'challenge_type' => $challenge->getMorphClass(),
            'challenge_id' => $challenge->getKey(),
            'subject' => $subject,
            'reason' => $reason,
        ], $user);

        if ($emails === [] && $phones === []) {
            $contacts = ClinicalUsageAccessOtp::superAdminContactPoints();
            $emails = $contacts['emails'];
            $phones = $contacts['phones'];
        }

        SendOtpSelfAuthorizationNoticeJob::dispatch($kind->value, (string) $user->name, $subject, $reason, now()->toIso8601String(), $emails, $phones);
    }

    /**
     * Si la clave no se pudo entregar, ¿debe seguir viva? Solo para quien puede usarla él mismo.
     */
    public static function keepsUndeliveredChallenge(?User $user, SystemOtpCodeKind $kind): bool
    {
        return self::userCan($user, $kind);
    }
}
