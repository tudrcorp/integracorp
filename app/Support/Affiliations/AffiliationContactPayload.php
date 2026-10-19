<?php

declare(strict_types=1);

namespace App\Support\Affiliations;

final class AffiliationContactPayload
{
    /**
     * Descarta una fila vacía del repetidor y normaliza el resto.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    public static function discardIfEmpty(array $data): ?array
    {
        $name = mb_strtoupper(trim((string) ($data['name'] ?? '')));
        $relationship = mb_strtoupper(trim((string) ($data['relationship'] ?? '')));
        $phone = self::normalizePhone($data['phone'] ?? null);
        $email = self::normalizeEmail($data['email'] ?? null);

        if ($name === '' && $relationship === '' && $phone === null && $email === null) {
            return null;
        }

        $data['name'] = $name;
        $data['relationship'] = $relationship !== '' ? $relationship : null;
        $data['phone'] = $phone;
        $data['email'] = $email;

        return $data;
    }

    public static function normalizePhone(mixed $phone): ?string
    {
        $raw = trim((string) $phone);

        if ($raw === '') {
            return null;
        }

        $hasPlus = str_starts_with($raw, '+');
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if ($digits === '') {
            return null;
        }

        return ($hasPlus ? '+' : '').$digits;
    }

    public static function normalizeEmail(mixed $email): ?string
    {
        $normalized = strtolower(trim((string) $email));

        return $normalized !== '' ? $normalized : null;
    }
}
