<?php

declare(strict_types=1);

namespace App\Support;

final class AffiliationCorporateRifLabel
{
    /**
     * RIF jurídico con prefijo "J-" para presentación (PDF, tablas, infolist).
     * Si el valor ya incluye J opcionalmente seguido de guion, se normaliza a "J-" + número.
     */
    public static function withJPrefix(?string $rif): string
    {
        $t = trim((string) ($rif ?? ''));
        if ($t === '') {
            return '';
        }

        $rest = preg_replace('/^J\s*-?\s*/i', '', $t);
        $rest = is_string($rest) ? trim($rest) : '';

        if ($rest === '') {
            return 'J-'.$t;
        }

        return 'J-'.$rest;
    }
}
