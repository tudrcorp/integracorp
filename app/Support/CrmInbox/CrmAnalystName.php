<?php

declare(strict_types=1);

namespace App\Support\CrmInbox;

/**
 * El primer nombre que el cliente lee en WhatsApp. SolIA es quien retoma al devolver el caso.
 */
final class CrmAnalystName
{
    public const BOT = 'SolIA';

    public static function given(string $fullName): string
    {
        $fullName = trim($fullName);

        if ($fullName === '') {
            return '';
        }

        $first = preg_split('/\s+/u', $fullName, 2);
        $given = trim((string) ($first[0] ?? ''));

        if ($given === '' || str_contains($given, '@') || preg_match('/\d/u', $given) === 1 || mb_strlen($given) > 40) {
            return '';
        }

        return $given;
    }

    public static function introduction(string $given): string
    {
        if ($given === '') {
            return 'Hola, ya estoy aquí para ayudarte.';
        }

        return 'Hola, soy '.$given.'. Ya estoy aquí para ayudarte.';
    }

    public static function closing(string $given): string
    {
        $who = $given !== ''
            ? $given.' ya cerró esta parte.'
            : 'Quien te estaba atendiendo ya cerró esta parte.';

        return $who.' Soy '.self::BOT.' y retomo la conversación. Escríbeme cuando quieras y seguimos con lo que necesitas.';
    }

    /**
     * Mensaje al cliente cuando el analista finaliza la conversación. Con el primer nombre del cliente
     * si se conoce (en mayúsculas sostenidas se pasa a nombre propio).
     */
    public static function finishing(string $clientName): string
    {
        $given = self::given($clientName);

        if ($given !== '' && in_array(mb_strtolower($given), ['sin', 'cliente', 'desconocido'], true)) {
            $given = '';
        }

        if ($given !== '' && mb_strtoupper($given) === $given) {
            $given = mb_convert_case(mb_strtolower($given), MB_CASE_TITLE);
        }

        return ($given !== '' ? 'Gracias por escribirnos, '.$given.'.' : 'Gracias por escribirnos.')
            .' Damos por finalizada esta conversación; si necesitas algo más, escríbenos cuando quieras.';
    }
}
