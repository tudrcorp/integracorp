<?php

namespace App\Http\Controllers;

use App\Models\Agency;

class AgencyController extends Controller
{
    public static function generate_code_agency(): string
    {
        return 'TDG-'.self::codeSuffixForAgencyId(self::nextAgencyId());
    }

    /**
     * Reserva el siguiente id secuencial y su código TDG alineado (TDG-{100 + id}).
     * Debe invocarse dentro de una transacción para que lockForUpdate evite duplicados.
     *
     * @return array{id: int, code: string}
     */
    public static function reserveNextAgencyIdentity(): array
    {
        $nextId = self::nextAgencyId(lockForUpdate: true);

        return [
            'id' => $nextId,
            'code' => 'TDG-'.self::codeSuffixForAgencyId($nextId),
        ];
    }

    public static function nextAgencyId(bool $lockForUpdate = false): int
    {
        $inferredMaxId = self::maxAgencyIdInferredFromCodes($lockForUpdate);

        if ($inferredMaxId > 0) {
            return $inferredMaxId + 1;
        }

        $query = Agency::query();

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return ((int) $query->max('id')) + 1;
    }

    public static function maxAgencyIdInferredFromCodes(bool $lockForUpdate = false): int
    {
        $query = Agency::query();

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $maxSuffix = $query
            ->whereNotNull('code')
            ->where('code', 'like', 'TDG-%')
            ->pluck('code')
            ->map(function (?string $code): int {
                if ($code === null || preg_match('/^TDG-(\d+)$/i', $code, $matches) !== 1) {
                    return 0;
                }

                return (int) $matches[1];
            })
            ->max();

        if ($maxSuffix === null || $maxSuffix <= 100) {
            return 0;
        }

        return $maxSuffix - 100;
    }

    public static function codeSuffixForAgencyId(int $agencyId): int
    {
        return 100 + $agencyId;
    }

    public static function isTdgAgencyReference(?string $code): bool
    {
        $normalized = mb_strtolower(trim((string) $code));

        return in_array($normalized, ['tdg', 'tdg-100', 'tdg100'], true);
    }

    /**
     * Agencia master bajo TDG: owner_code = TDG-100.
     * Agencia master independiente: owner_code = code (ej. TDG-120 / TDG-120).
     * Agencia general bajo TDG: owner_code = TDG-100.
     * Agencia general bajo otra master: owner_code = código de la agencia master.
     */
    public static function resolveOwnerCodeForAgency(int $agencyTypeId, string $agencyCode, ?string $masterOrParentCode = null): string
    {
        $parentCode = trim((string) ($masterOrParentCode ?? ''));

        if ($agencyTypeId === 3) {
            if ($parentCode === '' || self::isTdgAgencyReference($parentCode)) {
                return 'TDG-100';
            }

            return $parentCode;
        }

        if ($parentCode !== '' && self::isTdgAgencyReference($parentCode)) {
            return 'TDG-100';
        }

        return $agencyCode;
    }
}
