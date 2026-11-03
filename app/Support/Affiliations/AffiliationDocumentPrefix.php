<?php

declare(strict_types=1);

namespace App\Support\Affiliations;

use App\Enums\IdentityDocumentType;

/**
 * Letra (V, E, J, G) de los documentos de una afiliación y cómo se imprime.
 *
 * Si el analista eligió el tipo en el formulario, manda esa elección. Si no (afiliaciones
 * viejas o altas por otros caminos), se deduce:
 * - Empresa contratante de una corporativa: J.
 * - Persona: E si la cédula es de 80.000.000 en adelante (rango de extranjeros), si no V.
 * - Pagador de una individual cuyo nombre es de empresa (C.A., S.A., Clínica…): J.
 */
final class AffiliationDocumentPrefix
{
    /** Desde este número las cédulas venezolanas son de extranjeros. */
    public const int FOREIGNER_THRESHOLD = 80_000_000;

    private const string COMPANY_NAME_PATTERN = '/(?:[\s,.]C\.?\s?A\.?|[\s,.]S\.?\s?A\.?|S\.?\s?R\.?\s?L\.?)\s*$|\b(?:COMPA[ÑN][IÍ]A|INVERSIONES|CORPORACI[OÓ]N|UNIDAD|CL[IÍ]NICA|FUNDACI[OÓ]N|ASOCIACI[OÓ]N|GRUPO|COLEGIO|LICEO|INSTITUTO|ALCALD[IÍ]A|GOBERNACI[OÓ]N|MINISTERIO)\b/iu';

    /**
     * Número con su letra: «V-12345678», «J-075363191». Respeta un documento que ya trae
     * letra y deja intacto lo que no es un número (vacío, «...», «000000000»).
     */
    public static function format(?string $number, ?IdentityDocumentType $type): string
    {
        $number = trim((string) $number);

        if ($number === '') {
            return '';
        }

        if (preg_match('/^[VEJGP]\s*-?\s*\d/i', $number) === 1) {
            return strtoupper($number[0]).'-'.ltrim(substr($number, 1), " -\t");
        }

        if (preg_match('/^\d+$/', $number) !== 1 || ltrim($number, '0') === '' || $type === null) {
            return $number;
        }

        return $type->value.'-'.$number;
    }

    /**
     * Tipo de una persona: el elegido o, si no hay, el que dice la cédula.
     */
    public static function forPerson(mixed $stored, ?string $number): IdentityDocumentType
    {
        return self::stored($stored) ?? self::inferPerson($number);
    }

    /**
     * Tipo del pagador de una individual: el elegido, o J si el nombre es de empresa, o el de persona.
     */
    public static function forPayer(mixed $stored, ?string $number, ?string $name): IdentityDocumentType
    {
        $type = self::stored($stored);

        if ($type !== null) {
            return $type;
        }

        return self::looksLikeCompany($name) ? IdentityDocumentType::J : self::inferPerson($number);
    }

    /**
     * Tipo del RIF de una empresa: el elegido o J.
     */
    public static function forCompany(mixed $stored): IdentityDocumentType
    {
        return self::stored($stored) ?? IdentityDocumentType::J;
    }

    public static function inferPerson(?string $number): IdentityDocumentType
    {
        $digits = trim((string) $number);

        if (preg_match('/^\d+$/', $digits) === 1 && strlen(ltrim($digits, '0')) <= 8 && (int) $digits >= self::FOREIGNER_THRESHOLD) {
            return IdentityDocumentType::E;
        }

        return IdentityDocumentType::V;
    }

    public static function looksLikeCompany(?string $name): bool
    {
        $name = trim((string) $name);

        return $name !== '' && preg_match(self::COMPANY_NAME_PATTERN, $name) === 1;
    }

    private static function stored(mixed $stored): ?IdentityDocumentType
    {
        if ($stored instanceof IdentityDocumentType) {
            return $stored;
        }

        return IdentityDocumentType::tryFrom(strtoupper(trim((string) $stored)));
    }
}
