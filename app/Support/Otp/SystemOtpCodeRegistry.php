<?php

declare(strict_types=1);

namespace App\Support\Otp;

use App\Enums\SystemOtpCodeKind;
use App\Models\SystemOtpCode;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Deja una copia consultable de cada clave OTP emitida (ver {@see SystemOtpCode}).
 *
 * Nunca interrumpe el flujo de la clave: si guardar la copia falla, se reporta
 * y la clave sigue saliendo por correo y WhatsApp como siempre.
 */
final class SystemOtpCodeRegistry
{
    /**
     * @param  array<string, scalar|null>  $details
     */
    public static function record(
        SystemOtpCodeKind $kind,
        Model $challenge,
        string $code,
        ?int $requestedById,
        ?string $requestedByName,
        ?string $subject,
        array $details,
        int $emailsSent,
        int $phonesSent,
        ?DateTimeInterface $expiresAt,
    ): void {
        if ($code === '' || $challenge->getKey() === null) {
            return;
        }

        try {
            SystemOtpCode::query()->create([
                'kind' => $kind,
                'challenge_type' => $challenge->getMorphClass(),
                'challenge_id' => $challenge->getKey(),
                'code' => $code,
                'requested_by_user_id' => $requestedById,
                'requested_by_name' => $requestedByName !== null ? mb_substr($requestedByName, 0, 150) : null,
                'subject' => $subject !== null ? mb_substr($subject, 0, 255) : null,
                'details' => array_filter($details, static fn (mixed $value): bool => $value !== null && $value !== ''),
                'emails_sent' => max(0, $emailsSent),
                'phones_sent' => max(0, $phonesSent),
                'expires_at' => $expiresAt ?? now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Marca como usada la clave más reciente del reto (la que se acaba de validar).
     */
    public static function markUsed(Model $challenge): void
    {
        try {
            $latestId = SystemOtpCode::query()
                ->where('challenge_type', $challenge->getMorphClass())
                ->where('challenge_id', $challenge->getKey())
                ->max('id');

            if ($latestId !== null) {
                SystemOtpCode::query()->whereKey($latestId)->whereNull('used_at')->update(['used_at' => now()]);
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
