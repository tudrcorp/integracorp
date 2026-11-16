<?php

declare(strict_types=1);

namespace App\Support\Telegram;

use RuntimeException;
use Throwable;

/**
 * Error de la Bot API clasificado por lo que hay que hacer con él: reintentar,
 * esperar lo que pide Telegram, pasar al respaldo o corregir la configuración.
 */
final class TelegramApiException extends RuntimeException
{
    public const KIND_TRANSIENT = 'transient';

    public const KIND_RATE_LIMITED = 'rate_limited';

    public const KIND_BLOCKED = 'blocked';

    public const KIND_CHAT_NOT_FOUND = 'chat_not_found';

    public const KIND_MIGRATED = 'migrated';

    public const KIND_UNAUTHORIZED = 'unauthorized';

    public const KIND_NOT_CONFIGURED = 'not_configured';

    public const KIND_PERMANENT = 'permanent';

    public function __construct(
        string $message,
        public readonly string $kind,
        public readonly ?int $errorCode = null,
        public readonly ?int $retryAfter = null,
        public readonly ?string $migrateToChatId = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $errorCode ?? 0, $previous);
    }

    /**
     * Respuesta de error de Telegram ({"ok":false,"error_code":…,"description":…,"parameters":{…}}).
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromResponse(int $httpStatus, array $payload): self
    {
        $code = (int) ($payload['error_code'] ?? $httpStatus);
        $description = (string) ($payload['description'] ?? 'Respuesta inesperada de Telegram');
        $parameters = is_array($payload['parameters'] ?? null) ? $payload['parameters'] : [];
        $lower = mb_strtolower($description);

        if (isset($parameters['migrate_to_chat_id'])) {
            return new self($description, self::KIND_MIGRATED, $code, null, (string) $parameters['migrate_to_chat_id']);
        }

        if ($code === 429) {
            return new self($description, self::KIND_RATE_LIMITED, $code, max(1, (int) ($parameters['retry_after'] ?? 5)));
        }

        if ($code === 401 || $code === 404) {
            return new self($description, self::KIND_UNAUTHORIZED, $code);
        }

        if ($code === 403) {
            return new self($description, self::KIND_BLOCKED, $code);
        }

        if ($code === 400 && (str_contains($lower, 'chat not found') || str_contains($lower, 'user not found') || str_contains($lower, 'chat_id is empty'))) {
            return new self($description, self::KIND_CHAT_NOT_FOUND, $code);
        }

        if ($code >= 500 || $code === 0) {
            return new self($description, self::KIND_TRANSIENT, $code);
        }

        return new self($description, self::KIND_PERMANENT, $code);
    }

    public static function notConfigured(string $message = 'Telegram no está configurado: falta TELEGRAM_BOT_TOKEN.'): self
    {
        return new self($message, self::KIND_NOT_CONFIGURED);
    }

    /** ¿Tiene sentido volver a intentar por Telegram? */
    public function isRetryable(): bool
    {
        return in_array($this->kind, [self::KIND_TRANSIENT, self::KIND_RATE_LIMITED, self::KIND_MIGRATED], true);
    }

    /** ¿El destino ya no puede recibir (bloqueó el bot, chat inexistente)? */
    public function disablesRecipient(): bool
    {
        return in_array($this->kind, [self::KIND_BLOCKED, self::KIND_CHAT_NOT_FOUND], true);
    }
}
