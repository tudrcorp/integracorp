<?php

declare(strict_types=1);

namespace App\Support\CrmInbox;

/**
 * Firma del handoff: HMAC-SHA256 de «timestamp.cuerpo», en hexadecimal.
 */
final class CrmHandoffSignature
{
    public const HEADER_TIMESTAMP = 'X-Crm-Timestamp';

    public const HEADER_SIGNATURE = 'X-Crm-Signature';

    public static function sign(string $timestamp, string $body, string $secret): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }

    public static function matches(string $timestamp, string $body, string $secret, string $provided): bool
    {
        $provided = trim($provided);

        if (str_starts_with(strtolower($provided), 'sha256=')) {
            $provided = substr($provided, 7);
        }

        $expected = self::sign($timestamp, $body, $secret);

        return hash_equals($expected, strtolower($provided));
    }
}
