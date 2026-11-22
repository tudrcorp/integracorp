<?php

declare(strict_types=1);

namespace App\Support\Telegram;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Cliente mínimo de la Bot API de Telegram (sin paquetes externos).
 *
 * Todas las llamadas devuelven el «result» de Telegram o lanzan
 * TelegramApiException ya clasificada. El token nunca aparece en los mensajes
 * de error: la URL lo lleva dentro y los errores de conexión la repiten.
 */
final class TelegramBotClient
{
    public const MAX_MESSAGE_LENGTH = 4096;

    /** Límite de Telegram para el pie de un documento. */
    public const MAX_CAPTION_LENGTH = 1024;

    /** Límite de Telegram para subir un archivo desde un bot (50 MB). */
    public const MAX_UPLOAD_BYTES = 50 * 1024 * 1024;

    public function __construct(
        private readonly ?string $token = null,
        private readonly ?string $baseUrl = null,
        private readonly ?int $timeoutSeconds = null,
        private readonly bool $forceIpv4 = false,
    ) {}

    public static function make(): self
    {
        return new self(
            (string) config('telegram.bot_token', ''),
            (string) config('telegram.api_base_url', 'https://api.telegram.org'),
            (int) config('telegram.timeout_seconds', 10),
            (bool) config('telegram.force_ipv4', true),
        );
    }

    /**
     * Opciones de Guzzle comunes a todas las llamadas.
     *
     * @return array<string, mixed>
     */
    public function transportOptions(): array
    {
        return $this->forceIpv4 ? ['force_ip_resolve' => 'v4'] : [];
    }

    public function isConfigured(): bool
    {
        return filled($this->token);
    }

    /**
     * @return array<string, mixed>
     */
    public function getMe(): array
    {
        return $this->call('getMe');
    }

    /**
     * @return array<string, mixed>
     */
    public function getWebhookInfo(): array
    {
        return $this->call('getWebhookInfo');
    }

    /**
     * @param  list<string>  $allowedUpdates
     */
    public function setWebhook(string $url, string $secret, array $allowedUpdates, bool $dropPendingUpdates = false): bool
    {
        return (bool) $this->call('setWebhook', [
            'url' => $url,
            'secret_token' => $secret,
            'allowed_updates' => $allowedUpdates,
            'drop_pending_updates' => $dropPendingUpdates,
            'max_connections' => 10,
        ]);
    }

    public function deleteWebhook(bool $dropPendingUpdates = false): bool
    {
        return (bool) $this->call('deleteWebhook', ['drop_pending_updates' => $dropPendingUpdates]);
    }

    /**
     * @param  list<string>  $allowedUpdates
     * @return list<array<string, mixed>>
     */
    public function getUpdates(int $offset, int $timeoutSeconds, array $allowedUpdates): array
    {
        $result = $this->call('getUpdates', [
            'offset' => $offset,
            'timeout' => $timeoutSeconds,
            'allowed_updates' => $allowedUpdates,
        ], $timeoutSeconds + 5);

        return array_values(array_filter($result, 'is_array'));
    }

    /**
     * Envía un mensaje en HTML de Telegram. El texto debe venir ya escapado.
     *
     * @return array<string, mixed> el Message que devuelve Telegram (trae message_id)
     */
    public function sendMessage(string $chatId, string $html, bool $protectContent = false): array
    {
        return $this->call('sendMessage', [
            'chat_id' => $chatId,
            'text' => mb_substr($html, 0, self::MAX_MESSAGE_LENGTH),
            'parse_mode' => 'HTML',
            'link_preview_options' => ['is_disabled' => true],
            'protect_content' => $protectContent,
        ]);
    }

    /**
     * Imágenes como foto (se ven en el chat, hasta 10 MB), videos como video y el resto
     * (PDF) como documento.
     *
     * @return array{0: string, 1: string} método de la Bot API y nombre del campo del archivo
     */
    public static function uploadMethodFor(string $absolutePath): array
    {
        $extension = mb_strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));

        if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true) && (int) @filesize($absolutePath) <= 10 * 1024 * 1024) {
            return ['sendPhoto', 'photo'];
        }

        if ($extension === 'mp4') {
            return ['sendVideo', 'video'];
        }

        return ['sendDocument', 'document'];
    }

    /**
     * Sube un documento (PDF), una imagen o un video desde el disco. El pie va en HTML ya escapado; si no
     * cabe en el límite de Telegram, se envía antes como mensaje y el documento sale
     * sin pie.
     *
     * @return array<string, mixed> el Message que devuelve Telegram (trae message_id)
     */
    public function sendDocument(string $chatId, string $absolutePath, string $filename, string $captionHtml = '', bool $protectContent = true): array
    {
        if (! $this->isConfigured()) {
            throw TelegramApiException::notConfigured();
        }

        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            throw new TelegramApiException('El documento no existe o no se puede leer: '.basename($absolutePath), TelegramApiException::KIND_PERMANENT);
        }

        if ((int) filesize($absolutePath) > self::MAX_UPLOAD_BYTES) {
            throw new TelegramApiException('El documento supera los 50 MB que acepta Telegram: '.basename($absolutePath), TelegramApiException::KIND_PERMANENT);
        }

        if (mb_strlen(strip_tags($captionHtml)) > self::MAX_CAPTION_LENGTH - 24) {
            $this->sendMessage($chatId, $captionHtml, $protectContent);
            $captionHtml = '';
        }

        [$method, $field] = self::uploadMethodFor($absolutePath);
        $url = rtrim((string) ($this->baseUrl ?: 'https://api.telegram.org'), '/').'/bot'.$this->token.'/'.$method;
        $handle = fopen($absolutePath, 'rb');

        try {
            $response = $this->pendingRequest()
                ->timeout(max(30, (int) ($this->timeoutSeconds ?? 10) * 3))
                ->attach($field, $handle, $filename)
                ->post($url, array_filter([
                    'chat_id' => $chatId,
                    'caption' => $captionHtml !== '' ? $captionHtml : null,
                    'parse_mode' => $captionHtml !== '' ? 'HTML' : null,
                    'protect_content' => $protectContent ? 'true' : 'false',
                ], static fn (mixed $value): bool => $value !== null));
        } catch (ConnectionException $exception) {
            throw new TelegramApiException('Sin conexión con Telegram: '.$this->redact($exception->getMessage()), TelegramApiException::KIND_TRANSIENT, null, null, null, $exception);
        } catch (Throwable $exception) {
            throw new TelegramApiException('Error subiendo el documento a Telegram: '.$this->redact($exception->getMessage()), TelegramApiException::KIND_TRANSIENT, null, null, null, $exception);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new TelegramApiException('Telegram respondió algo que no es JSON (HTTP '.$response->status().').', $response->serverError() ? TelegramApiException::KIND_TRANSIENT : TelegramApiException::KIND_PERMANENT, $response->status());
        }

        if (($payload['ok'] ?? false) !== true) {
            throw TelegramApiException::fromResponse($response->status(), $payload);
        }

        return is_array($payload['result'] ?? null) ? $payload['result'] : [];
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>|list<mixed>|bool
     */
    private function call(string $method, array $parameters = [], ?int $timeout = null): array|bool
    {
        if (! $this->isConfigured()) {
            throw TelegramApiException::notConfigured();
        }

        $url = rtrim((string) ($this->baseUrl ?: 'https://api.telegram.org'), '/').'/bot'.$this->token.'/'.$method;

        try {
            $response = $this->pendingRequest()
                ->asJson()
                ->timeout(max(1, $timeout ?? $this->timeoutSeconds ?? 10))
                ->post($url, $parameters);
        } catch (ConnectionException $exception) {
            throw new TelegramApiException('Sin conexión con Telegram: '.$this->redact($exception->getMessage()), TelegramApiException::KIND_TRANSIENT, null, null, null, $exception);
        } catch (Throwable $exception) {
            throw new TelegramApiException('Error llamando a Telegram: '.$this->redact($exception->getMessage()), TelegramApiException::KIND_TRANSIENT, null, null, null, $exception);
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new TelegramApiException('Telegram respondió algo que no es JSON (HTTP '.$response->status().').', $response->serverError() ? TelegramApiException::KIND_TRANSIENT : TelegramApiException::KIND_PERMANENT, $response->status());
        }

        if (($payload['ok'] ?? false) !== true) {
            throw TelegramApiException::fromResponse($response->status(), $payload);
        }

        $result = $payload['result'] ?? null;

        return is_array($result) || is_bool($result) ? $result : [];
    }

    private function pendingRequest(): PendingRequest
    {
        return Http::acceptJson()
            ->connectTimeout(5)
            ->withOptions($this->transportOptions());
    }

    private function redact(string $text): string
    {
        return filled($this->token) ? str_replace((string) $this->token, '***', $text) : $text;
    }
}
