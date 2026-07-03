<?php

declare(strict_types=1);

namespace App\Support\AffiliationCorporates;

use App\Models\AffiliateCorporate;
use Carbon\Carbon;
use Illuminate\Support\Arr;

final class CorporateAffiliateVoucherIlsUpdater
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function save(AffiliateCorporate $affiliate, array $data): void
    {
        $dateInit = self::formatDateForStorage($data['dateInit']);
        $dateEnd = self::formatDateForStorage($data['dateEnd']);

        $affiliate->update([
            'vaucherIls' => $data['vaucherIls'],
            'dateInit' => $dateInit,
            'dateEnd' => $dateEnd,
            'numberDays' => self::calculateNumberDays($dateInit, $dateEnd) ?? 0,
            'document_ils' => self::resolveDocumentPath($data['document_ils'] ?? null, $affiliate->document_ils),
        ]);
    }

    public static function calculateNumberDays(mixed $dateInit, mixed $dateEnd): ?int
    {
        $start = self::parseDate($dateInit);
        $end = self::parseDate($dateEnd);

        if ($start === null || $end === null) {
            return null;
        }

        return (int) abs($end->diffInDays($start));
    }

    private static function resolveDocumentPath(mixed $uploaded, ?string $existing): ?string
    {
        if (is_array($uploaded)) {
            $uploaded = Arr::first($uploaded);
        }

        if (filled($uploaded)) {
            return (string) $uploaded;
        }

        return $existing;
    }

    private static function formatDateForStorage(mixed $value): string
    {
        $parsed = self::parseDate($value);

        if ($parsed === null) {
            return '';
        }

        return $parsed->format('d/m/Y');
    }

    private static function parseDate(mixed $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        $stringValue = (string) $value;

        try {
            if (str_contains($stringValue, '/')) {
                return Carbon::createFromFormat('d/m/Y', $stringValue);
            }

            return Carbon::parse($stringValue);
        } catch (\Throwable) {
            return null;
        }
    }
}
