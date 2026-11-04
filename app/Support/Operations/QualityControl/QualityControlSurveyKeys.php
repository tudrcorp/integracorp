<?php

declare(strict_types=1);

namespace App\Support\Operations\QualityControl;

use App\Enums\QualityControlAffiliateType;
use App\Support\Affiliations\PersonIdentity\PersonIdentityNormalizer;
use DateTimeInterface;

/**
 * Claves de comparación de una encuesta de Quality Control.
 */
final class QualityControlSurveyKeys
{
    /**
     * Cédula comparable («V-14.582.677» → «14582677»); nula si no da para comparar.
     */
    public static function documentKey(mixed $document): ?string
    {
        return PersonIdentityNormalizer::documentKey($document);
    }

    /**
     * Huella única de una respuesta: tipo + marca temporal (al segundo) + cédula.
     * Es lo que impide que reimportar el mismo Excel duplique encuestas.
     */
    public static function dedupeHash(QualityControlAffiliateType $type, DateTimeInterface $respondedAt, mixed $document): string
    {
        $document = self::documentKey($document)
            ?? mb_strtoupper((string) preg_replace('/\s+/u', '', trim((string) $document)));

        return hash('sha256', implode('|', [$type->value, $respondedAt->format('Y-m-d H:i:s'), $document]));
    }
}
