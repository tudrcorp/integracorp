<?php

declare(strict_types=1);

namespace App\Support\CrmInbox;

/**
 * Normaliza el JSON que manda n8n. No toca base de datos ni Redis.
 */
final class CrmHandoffEnvelopeData
{
    public const MAX_MESSAGES = 30;

    public const MAX_TEXT = 2000;

    /**
     * @return array{ok: true, envelope: array<string, mixed>}|array{ok: false, reason: string}
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

        $handoffId = self::handoffId($decoded['handoff_id'] ?? null);

        if ($handoffId === null) {
            return ['ok' => false, 'reason' => 'missing_handoff_id'];
        }

        $phone = self::phone($decoded['phone'] ?? null);

        if ($phone === null) {
            return ['ok' => false, 'reason' => 'missing_phone'];
        }

        return [
            'ok' => true,
            'envelope' => [
                'handoff_id' => $handoffId,
                'phone' => $phone,
                'phone_number_id' => self::shortText($decoded['phone_number_id'] ?? null, 40),
                'area' => self::area($decoded['area'] ?? null),
                'motivo' => self::shortText($decoded['motivo'] ?? null, self::MAX_TEXT),
                'necesidad' => self::shortText($decoded['necesidad'] ?? null, self::MAX_TEXT),
                'etapa' => self::shortText($decoded['etapa'] ?? null, 40),
                'name' => self::shortText($decoded['name'] ?? null, 160),
                'objecion' => self::shortText($decoded['objecion'] ?? null, self::MAX_TEXT),
                'ultimo_mensaje' => self::shortText($decoded['ultimo_mensaje'] ?? null, self::MAX_TEXT),
                'cotizacion' => self::objectish($decoded['cotizacion'] ?? null),
                'lead' => self::objectish($decoded['lead'] ?? null),
                'sesion' => self::objectish($decoded['sesion'] ?? null),
                'analista' => self::objectish($decoded['analista'] ?? null),
                'mensajes' => self::messages($decoded['mensajes'] ?? null),
            ],
        ];
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

        return ltrim($value, '0') === '' ? null : ltrim($value, '0');
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

    private static function area(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $area = strtolower(trim($value));
        $area = preg_replace('/[^a-z0-9_]/', '', $area) ?? '';

        if ($area === '') {
            return null;
        }

        return substr($area, 0, 40);
    }

    private static function shortText(mixed $value, int $max): ?string
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }

        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }

        if (mb_strlen($text) > $max) {
            return mb_substr($text, 0, $max);
        }

        return $text;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function objectish(mixed $value): ?array
    {
        if (is_string($value)) {
            try {
                $value = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                return null;
            }
        }

        if (! is_array($value) || array_is_list($value)) {
            return null;
        }

        return $value;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function messages(mixed $value): array
    {
        if (is_string($value)) {
            try {
                $value = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                return [];
            }
        }

        if (! is_array($value)) {
            return [];
        }

        $messages = [];

        foreach ($value as $message) {
            if (! is_array($message)) {
                continue;
            }

            $messages[] = [
                'id' => self::shortText($message['id'] ?? null, 40),
                'direction' => self::shortText($message['direction'] ?? null, 8),
                'type' => self::shortText($message['type'] ?? null, 20),
                'text' => self::shortText($message['text'] ?? null, self::MAX_TEXT),
                'hora' => self::shortText($message['hora'] ?? null, 8),
            ];

            if (count($messages) >= self::MAX_MESSAGES) {
                break;
            }
        }

        return $messages;
    }
}
