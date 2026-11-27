<?php

declare(strict_types=1);

namespace App\Support\CrmInbox;

use finfo;

/**
 * Valida el archivo que n8n reenvía (POST /api/crm/handoff/media):
 * {message_id, phone, handoff_id?, mime_type?, file_name?, caption?, content_base64}.
 *
 * El tipo se decide por el contenido real (finfo), no por lo que declare el mensaje: un .exe renombrado
 * a .pdf no entra. Lo declarado solo desempata formatos que finfo ve como contenedor (docx, xlsx, m4a).
 */
final class CrmHandoffMediaData
{
    public const MAX_CAPTION = 1000;

    public const MAX_NAME = 150;

    /** Contenedores que finfo no distingue: se acepta lo declarado si es uno de estos. */
    private const CONTAINER_ALIASES = [
        'application/zip' => [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ],
        'application/octet-stream' => [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ],
        'application/cdfv2' => ['application/msword', 'application/vnd.ms-excel'],
        'application/x-ole-storage' => ['application/msword', 'application/vnd.ms-excel'],
        'video/mp4' => ['audio/mp4'],
        'audio/x-m4a' => ['audio/mp4'],
    ];

    /** Variantes del mismo tipo. */
    private const SYNONYMS = [
        'audio/x-m4a' => 'audio/mp4',
        'audio/m4a' => 'audio/mp4',
        'audio/opus' => 'audio/ogg',
        'audio/x-wav' => 'audio/wav',
        'audio/mp3' => 'audio/mpeg',
        'image/jpg' => 'image/jpeg',
    ];

    public static function maxBytes(): int
    {
        return max(1, (int) config('crm-inbox.media.max_mb', 16)) * 1024 * 1024;
    }

    /** Tope del cuerpo: el archivo en base64 (+33 %) y holgura para los campos. */
    public static function maxBodyBytes(): int
    {
        return (int) ceil(self::maxBytes() * 4 / 3) + 65536;
    }

    /**
     * @return array{ok: true, media: array{message_id: string, handoff_id: ?string, phone: string, media_type: string, mime_type: string, extension: string, original_name: string, caption: ?string, content: string, size: int, sha256: string}}|array{ok: false, reason: string}
     */
    public static function fromJson(string $raw): array
    {
        try {
            $decoded = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return ['ok' => false, 'reason' => 'invalid_json'];
        }

        if (! is_array($decoded) || array_is_list($decoded)) {
            return ['ok' => false, 'reason' => 'invalid_json'];
        }

        $base = CrmHandoffMessageData::fromJson(json_encode([
            'message_id' => $decoded['message_id'] ?? null,
            'phone' => $decoded['phone'] ?? null,
            'handoff_id' => $decoded['handoff_id'] ?? null,
            'text' => 'archivo',
        ]) ?: '{}');

        if ($base['ok'] !== true) {
            return ['ok' => false, 'reason' => $base['reason']];
        }

        $content = self::decode($decoded['content_base64'] ?? null);

        if ($content === null) {
            return ['ok' => false, 'reason' => 'invalid_content'];
        }

        $size = strlen($content);

        if ($size === 0) {
            return ['ok' => false, 'reason' => 'empty_file'];
        }

        if ($size > self::maxBytes()) {
            return ['ok' => false, 'reason' => 'file_too_large'];
        }

        $mime = self::resolveMime($content, is_string($decoded['mime_type'] ?? null) ? $decoded['mime_type'] : null);
        $allowed = (array) config('crm-inbox.media.allowed', []);

        if ($mime === null || ! isset($allowed[$mime])) {
            return ['ok' => false, 'reason' => 'unsupported_type'];
        }

        [$mediaType, $extension] = $allowed[$mime];

        return [
            'ok' => true,
            'media' => [
                'message_id' => $base['message']['message_id'],
                'handoff_id' => $base['message']['handoff_id'],
                'phone' => $base['message']['phone'],
                'media_type' => (string) $mediaType,
                'mime_type' => $mime,
                'extension' => (string) $extension,
                'original_name' => self::fileName($decoded['file_name'] ?? null, (string) $mediaType, (string) $extension),
                'caption' => self::caption($decoded['caption'] ?? null),
                'content' => $content,
                'size' => $size,
                'sha256' => hash('sha256', $content),
            ],
        ];
    }

    public static function normalizeMime(?string $mime): ?string
    {
        $mime = strtolower(trim(explode(';', (string) $mime)[0]));

        if ($mime === '') {
            return null;
        }

        return self::SYNONYMS[$mime] ?? $mime;
    }

    private static function decode(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        if (str_starts_with($value, 'data:')) {
            $comma = strpos($value, ',');
            $value = $comma === false ? '' : substr($value, $comma + 1);
        }

        $value = preg_replace('/\s+/', '', $value) ?? '';
        $decoded = base64_decode($value, true);

        return $decoded === false ? null : $decoded;
    }

    private static function resolveMime(string $content, ?string $declared): ?string
    {
        $detected = strtolower((string) (new finfo(FILEINFO_MIME_TYPE))->buffer($content));
        $declared = self::normalizeMime($declared);
        $allowed = (array) config('crm-inbox.media.allowed', []);

        if (isset(self::CONTAINER_ALIASES[$detected]) && $declared !== null && in_array($declared, self::CONTAINER_ALIASES[$detected], true)) {
            return $declared;
        }

        $detected = self::normalizeMime($detected);

        return $detected !== null && isset($allowed[$detected]) ? $detected : null;
    }

    private static function fileName(mixed $value, string $mediaType, string $extension): string
    {
        $name = is_string($value) ? $value : '';
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F"<>:|?*]+/u', '', $name) ?? '';
        $name = trim($name, " .\t");

        if ($name === '') {
            $name = match ($mediaType) {
                'image' => 'imagen',
                'audio' => 'nota-de-voz',
                default => 'documento',
            };
        }

        $stem = pathinfo($name, PATHINFO_FILENAME);
        $stem = mb_substr($stem !== '' ? $stem : $name, 0, self::MAX_NAME - strlen($extension) - 1);

        return $stem.'.'.$extension;
    }

    private static function caption(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : mb_substr($value, 0, self::MAX_CAPTION);
    }
}
