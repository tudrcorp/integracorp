<?php

declare(strict_types=1);

namespace App\Support\Telegram;

use App\Enums\TelegramRecipientStatus;
use App\Enums\TelegramRecipientType;
use App\Models\TelegramLinkToken;
use App\Models\TelegramRecipient;
use App\Models\User;
use App\Support\SecurityAudit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Vinculación de personas con el bot: enlace t.me de un solo uso que vence en
 * minutos. Al pulsar «Iniciar», Telegram nos manda el código y el chat_id, y
 * con eso queda vinculada la persona.
 */
final class TelegramLinkService
{
    /**
     * @return array{url: string, code: string, expires_at: Carbon, recipient: TelegramRecipient}
     */
    public static function issueForUser(User $user, ?string $actor = null): array
    {
        if (! TelegramRecipient::isInternalEmail((string) $user->email)) {
            throw new InvalidArgumentException('Solo el personal con correo @'.config('telegram.internal_email_domain').' puede vincular Telegram desde su perfil.');
        }

        if (strtoupper(trim((string) $user->status)) !== 'ACTIVO') {
            throw new InvalidArgumentException('El usuario no está activo.');
        }

        return DB::transaction(function () use ($user, $actor): array {
            $recipient = TelegramRecipient::query()->lockForUpdate()->firstWhere('user_id', $user->getKey());

            if (! $recipient instanceof TelegramRecipient) {
                $recipient = TelegramRecipient::query()->create([
                    'type' => TelegramRecipientType::User,
                    'status' => TelegramRecipientStatus::PendingLink,
                    'user_id' => $user->getKey(),
                    'name' => (string) $user->name,
                    'email' => mb_strtolower(trim((string) $user->email)),
                    'phone' => filled($user->phone) ? (string) $user->phone : null,
                    'created_by' => $actor ?? (string) $user->name,
                ]);
            } else {
                $recipient->fill([
                    'name' => (string) $user->name,
                    'email' => mb_strtolower(trim((string) $user->email)),
                    'phone' => filled($user->phone) ? (string) $user->phone : $recipient->phone,
                    'updated_by' => $actor ?? (string) $user->name,
                ])->save();
            }

            return self::issueToken($recipient, $actor ?? (string) $user->name);
        });
    }

    /**
     * @return array{url: string, code: string, expires_at: Carbon, recipient: TelegramRecipient}
     */
    public static function issueForExternal(TelegramRecipient $recipient, string $actor): array
    {
        if ($recipient->type !== TelegramRecipientType::External) {
            throw new InvalidArgumentException('Este enlace es solo para contactos externos.');
        }

        if ($recipient->isExpired()) {
            throw new InvalidArgumentException('El contacto está vencido. Cambie su fecha de vencimiento antes de generar el enlace.');
        }

        return DB::transaction(function () use ($recipient, $actor): array {
            $locked = TelegramRecipient::query()->lockForUpdate()->findOrFail($recipient->getKey());

            if ($locked->status === TelegramRecipientStatus::Disabled || $locked->status === TelegramRecipientStatus::Blocked) {
                $locked->forceFill(['status' => TelegramRecipientStatus::PendingLink, 'chat_id' => null, 'updated_by' => $actor])->save();
            }

            return self::issueToken($locked, $actor);
        });
    }

    /** Intentos fallidos de código corto que se toleran por chat antes de bloquear. */
    public const MAX_SHORT_CODE_ATTEMPTS = 5;

    /** Minutos que dura el bloqueo de un chat que falló demasiados códigos. */
    public const SHORT_CODE_LOCK_MINUTES = 15;

    /**
     * Procesa «/start <código>» recibido en un chat privado.
     */
    public static function consume(string $plainToken, string $chatId, ?string $telegramUsername = null): TelegramLinkResult
    {
        $plainToken = trim($plainToken);

        if ($plainToken === '' || ! preg_match('/^[A-Za-z0-9_-]{16,64}$/', $plainToken)) {
            return TelegramLinkResult::rejected('El enlace no es válido. Genere uno nuevo desde IntegraCorp.');
        }

        return self::linkWith(
            static fn () => TelegramLinkToken::query()->where('token_hash', hash('sha256', $plainToken))->lockForUpdate()->first(),
            $chatId,
            $telegramUsername,
            'enlace',
        );
    }

    /**
     * Procesa el código corto de 6 dígitos que la persona le escribe al bot.
     * Cada chat tiene un máximo de intentos fallidos; después queda bloqueado
     * unos minutos para que nadie pueda probar códigos al azar.
     */
    public static function consumeShortCode(string $code, string $chatId, ?string $telegramUsername = null): TelegramLinkResult
    {
        $digits = (string) preg_replace('/\D+/', '', $code);
        $attemptsKey = 'telegram:short-code-attempts:'.$chatId;

        if (self::shortCodeAttempts($attemptsKey) >= self::MAX_SHORT_CODE_ATTEMPTS) {
            return TelegramLinkResult::rejected('Demasiados códigos incorrectos. Espere '.self::SHORT_CODE_LOCK_MINUTES.' minutos y genere un código nuevo en IntegraCorp.');
        }

        if (strlen($digits) !== 6) {
            return TelegramLinkResult::rejected('El código tiene 6 números. Revíselo en IntegraCorp → «Conectar Telegram».');
        }

        $result = self::linkWith(
            static fn () => TelegramLinkToken::query()
                ->where('short_code_hash', self::shortCodeHash($digits))
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first(),
            $chatId,
            $telegramUsername,
            'código',
        );

        if (! $result->linked) {
            $attempts = self::registerShortCodeFailure($attemptsKey);

            if ($attempts >= self::MAX_SHORT_CODE_ATTEMPTS) {
                try {
                    SecurityAudit::log('AUDIT_TELEGRAM_SHORT_CODE_LOCKED', 'telegram.webhook', ['chat_id' => $chatId, 'attempts' => $attempts]);
                } catch (Throwable) {
                }
            }
        }

        return $result;
    }

    /**
     * @param  callable(): ?TelegramLinkToken  $findToken
     */
    private static function linkWith(callable $findToken, string $chatId, ?string $telegramUsername, string $what): TelegramLinkResult
    {
        $invalid = $what === 'código'
            ? 'Ese código no es válido o ya venció. Genere uno nuevo en IntegraCorp → «Conectar Telegram».'
            : 'El enlace no es válido. Genere uno nuevo desde IntegraCorp.';

        try {
            return DB::transaction(function () use ($findToken, $chatId, $telegramUsername, $invalid): TelegramLinkResult {
                $token = $findToken();

                if (! $token instanceof TelegramLinkToken) {
                    return TelegramLinkResult::rejected($invalid);
                }

                if ($token->used_at !== null) {
                    return TelegramLinkResult::rejected('Este enlace ya se usó. Si necesita vincular otra vez, genere uno nuevo desde IntegraCorp.');
                }

                if ($token->expires_at->isPast()) {
                    return TelegramLinkResult::rejected('El enlace venció. Genere uno nuevo desde IntegraCorp: dura '.self::tokenMinutes().' minutos.');
                }

                $recipient = TelegramRecipient::query()->lockForUpdate()->find($token->telegram_recipient_id);

                if (! $recipient instanceof TelegramRecipient || ! $recipient->type->isPerson()) {
                    return TelegramLinkResult::rejected($invalid);
                }

                if ($recipient->isExpired()) {
                    return TelegramLinkResult::rejected('Su acceso a las notificaciones venció. Pida al administrador que lo renueve.');
                }

                if ($recipient->type === TelegramRecipientType::User) {
                    $user = $recipient->user;

                    if (! $user instanceof User || strtoupper(trim((string) $user->status)) !== 'ACTIVO' || ! TelegramRecipient::isInternalEmail((string) $user->email)) {
                        return TelegramLinkResult::rejected('Su usuario de IntegraCorp no está activo. No se puede vincular.');
                    }
                }

                $takenBy = TelegramRecipient::query()
                    ->where('chat_id', $chatId)
                    ->whereKeyNot($recipient->getKey())
                    ->whereIn('type', [TelegramRecipientType::User->value, TelegramRecipientType::External->value])
                    ->whereIn('status', [TelegramRecipientStatus::Active->value, TelegramRecipientStatus::Blocked->value])
                    ->first();

                if ($takenBy instanceof TelegramRecipient) {
                    return TelegramLinkResult::rejected('Este Telegram ya está vinculado a otra persona en IntegraCorp ('.$takenBy->name.'). Pida al administrador que lo revise.');
                }

                $recipient->forceFill([
                    'chat_id' => $chatId,
                    'telegram_username' => $telegramUsername,
                    'status' => TelegramRecipientStatus::Active,
                    'linked_at' => now(),
                    'last_error' => null,
                    'last_error_at' => null,
                    'updated_by' => 'telegram:start',
                ])->save();

                $token->forceFill(['used_at' => now(), 'used_chat_id' => $chatId])->save();

                TelegramLinkToken::query()
                    ->where('telegram_recipient_id', $recipient->getKey())
                    ->whereNull('used_at')
                    ->whereKeyNot($token->getKey())
                    ->update(['expires_at' => now()]);

                self::audit('AUDIT_TELEGRAM_RECIPIENT_LINKED', $recipient, ['telegram_username' => $telegramUsername]);

                return TelegramLinkResult::linked($recipient, '✅ Listo, '.$recipient->name.'. Su Telegram quedó vinculado a IntegraCorp y desde ahora recibirá aquí las notificaciones internas.');
            });
        } catch (Throwable $exception) {
            report($exception);

            return TelegramLinkResult::rejected('No se pudo completar la vinculación. Intente de nuevo en unos minutos.');
        }
    }

    public static function shortCodeHash(string $digits): string
    {
        return hash_hmac('sha256', $digits, (string) config('app.key'));
    }

    /**
     * Formato para mostrar: «482 913».
     */
    public static function formatShortCode(string $digits): string
    {
        return substr($digits, 0, 3).' '.substr($digits, 3);
    }

    private static function shortCodeAttempts(string $key): int
    {
        try {
            return (int) Cache::get($key, 0);
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * Cache::increment no crea la clave con la caché de base de datos: primero add.
     */
    private static function registerShortCodeFailure(string $key): int
    {
        try {
            Cache::add($key, 0, now()->addMinutes(self::SHORT_CODE_LOCK_MINUTES));

            return (int) Cache::increment($key);
        } catch (Throwable) {
            return 0;
        }
    }

    public static function unlink(TelegramRecipient $recipient, string $actor): void
    {
        DB::transaction(function () use ($recipient, $actor): void {
            $locked = TelegramRecipient::query()->lockForUpdate()->findOrFail($recipient->getKey());

            $locked->forceFill([
                'status' => TelegramRecipientStatus::Disabled,
                'chat_id' => null,
                'telegram_username' => null,
                'updated_by' => $actor,
            ])->save();

            TelegramLinkToken::query()
                ->where('telegram_recipient_id', $locked->getKey())
                ->whereNull('used_at')
                ->update(['expires_at' => now()]);

            self::audit('AUDIT_TELEGRAM_RECIPIENT_UNLINKED', $locked, ['actor' => $actor]);
        });
    }

    public static function deepLink(string $plainToken): string
    {
        $username = (string) config('telegram.bot_username', '');

        if ($username === '') {
            throw new InvalidArgumentException('Falta TELEGRAM_BOT_USERNAME en la configuración: sin él no se puede armar el enlace.');
        }

        return 'https://t.me/'.$username.'?start='.$plainToken;
    }

    public static function tokenMinutes(): int
    {
        return max(1, (int) config('telegram.link_token_minutes', 15));
    }

    /**
     * @return array{url: string, code: string, expires_at: Carbon, recipient: TelegramRecipient}
     */
    private static function issueToken(TelegramRecipient $recipient, string $actor): array
    {
        TelegramLinkToken::query()
            ->where('telegram_recipient_id', $recipient->getKey())
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->update(['expires_at' => now()]);

        $plainToken = Str::random(40);
        $code = self::uniqueShortCode();
        $expiresAt = now()->addMinutes(self::tokenMinutes());

        TelegramLinkToken::query()->create([
            'telegram_recipient_id' => $recipient->getKey(),
            'token_hash' => hash('sha256', $plainToken),
            'short_code_hash' => self::shortCodeHash($code),
            'expires_at' => $expiresAt,
            'created_by' => $actor,
        ]);

        self::audit('AUDIT_TELEGRAM_LINK_ISSUED', $recipient, ['actor' => $actor]);

        return ['url' => self::deepLink($plainToken), 'code' => $code, 'expires_at' => $expiresAt, 'recipient' => $recipient];
    }

    /**
     * Seis dígitos que no choquen con otro código vigente.
     */
    private static function uniqueShortCode(): string
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);

            $taken = TelegramLinkToken::query()
                ->where('short_code_hash', self::shortCodeHash($code))
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->exists();

            if (! $taken) {
                return $code;
            }
        }

        throw new RuntimeException('No se pudo generar un código de vinculación único. Intente de nuevo.');
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private static function audit(string $action, TelegramRecipient $recipient, array $details = []): void
    {
        try {
            SecurityAudit::log($action, 'telegram.recipients', [
                'telegram_recipient_id' => $recipient->getKey(),
                'type' => $recipient->type->value,
                'name' => $recipient->name,
                ...$details,
            ]);
        } catch (Throwable) {
        }
    }
}
