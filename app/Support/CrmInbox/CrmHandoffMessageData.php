<?php

declare(strict_types=1);

namespace App\Support\CrmInbox;

/**
 * Normaliza el mensaje que n8n reenvía cuando el cliente escribe durante la atención.
 */
final class CrmHandoffMessageData
{
    public const MAX_TEXT = 2000;

    /**
     * @return array{ok: true, message: array<string, mixed>}|array{ok: false, reason: string}
     */
    public static function fromJson(string $raw): array
    {
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return ['ok' => false, 'reason' => 'invalid_json'];
        }

        if (! is_array($decoded) || array_is_list($decoded)) {
            return ['ok' => false, 'reason' => 'invalid_json'];
        }

        $messageId = self::messageId($decoded['message_id'] ?? null);

        if ($messageId === null) {
            return ['ok' => false, 'reason' => 'missing_message_id'];
        }

        $phone = self::phone($decoded['phone'] ?? null);

        if ($phone === null) {
            return ['ok' => false, 'reason' => 'missing_phone'];
        }

        $text = self::text($decoded['text'] ?? null);

        if ($text === null) {
            return ['ok' => false, 'reason' => 'missing_text'];
        }

        return [
            'ok' => true,
            'message' => [
                'message_id' => $messageId,
                'handoff_id' => self::handoffId($decoded['handoff_id'] ?? null),
                'phone' => $phone,
                'text' => $text,
            ],
        ];
    }

    private static function messageId(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '' || strlen($value) > 191 || preg_match('/^[A-Za-z0-9._:-]+$/', $value) !== 1) {
            return null;
        }

        return $value;
    }

    private static function handoffId(mixed $value): ?string
    {
        if (is_int($value)) {
            $value = (string) $value;
        }

        if (! is_string($value) && ! is_float($value)) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '' || ! ctype_digit($value) || strlen($value) > 18 || $value === '0') {
            return null;
        }

        $value = ltrim($value, '0');

        return $value === '' ? null : $value;
    }

    private static function phone(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        if (strlen($digits) < 8 || strlen($digits) > 15) {
            return null;
        }

        return $digits;
    }

    private static function text(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }

        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }

        if (mb_strlen($text) > self::MAX_TEXT) {
            return mb_substr($text, 0, self::MAX_TEXT);
        }

        return $text;
    }
}
