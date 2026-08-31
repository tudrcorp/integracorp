<?php

declare(strict_types=1);

namespace App\Support\ClinicalEntitlements;

use App\Enums\ClinicalQuotaScope;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\TelemedicinePatient;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Throwable;

final class ClinicalEntitlementWindow
{
    /**
     * @var list<string>
     */
    private const KNOWN_FORMATS = [
        'd/m/Y',
        'd/m/Y H:i:s',
        'd/m/Y H:i',
        'd-m-Y',
        'Y-m-d',
        'Y-m-d H:i:s',
        'Y-m-d H:i',
    ];

    /**
     * @return array{starts_at: CarbonImmutable, ends_at: CarbonImmutable}|null
     */
    public static function forPatient(TelemedicinePatient $patient, ClinicalQuotaScope $scope): ?array
    {
        return self::forEffectDate(self::effectDate($patient), $scope);
    }

    /**
     * @return array{starts_at: CarbonImmutable, ends_at: CarbonImmutable}|null
     */
    public static function forEffectDate(CarbonImmutable $effect, ClinicalQuotaScope $scope): ?array
    {
        if ($scope === ClinicalQuotaScope::Unlimited) {
            return null;
        }

        if ($scope === ClinicalQuotaScope::PerContract) {
            return [
                'starts_at' => $effect,
                'ends_at' => $effect->addYear(),
            ];
        }

        return self::currentAnniversaryWindow($effect);
    }

    public static function effectDateFromValues(mixed ...$values): CarbonImmutable
    {
        foreach ($values as $value) {
            $parsed = self::parseToStartOfDay($value);
            if ($parsed instanceof CarbonImmutable) {
                return $parsed;
            }
        }

        return CarbonImmutable::now()->startOfYear();
    }

    /**
     * @return array{starts_at: CarbonImmutable, ends_at: CarbonImmutable}
     */
    public static function currentAnniversaryWindow(CarbonImmutable $effect): array
    {
        $now = CarbonImmutable::now();
        $start = $effect;

        if ($start->greaterThan($now)) {
            return [
                'starts_at' => $start,
                'ends_at' => $start->addYear(),
            ];
        }

        while ($start->addYear()->lessThanOrEqualTo($now)) {
            $start = $start->addYear();
        }

        return [
            'starts_at' => $start,
            'ends_at' => $start->addYear(),
        ];
    }

    public static function effectDate(TelemedicinePatient $patient): CarbonImmutable
    {
        $affiliation = $patient->afilliation;
        if ($affiliation instanceof Affiliation && filled($affiliation->effective_date)) {
            $parsed = self::parseToStartOfDay($affiliation->effective_date);
            if ($parsed instanceof CarbonImmutable) {
                return $parsed;
            }
        }

        $corporate = $patient->afilliationCorporate;
        if ($corporate instanceof AffiliationCorporate && filled($corporate->effective_date)) {
            $parsed = self::parseToStartOfDay($corporate->effective_date);
            if ($parsed instanceof CarbonImmutable) {
                return $parsed;
            }
        }

        if (filled($patient->date_affiliation)) {
            $parsed = self::parseToStartOfDay($patient->date_affiliation);
            if ($parsed instanceof CarbonImmutable) {
                return $parsed;
            }
        }

        return CarbonImmutable::now()->startOfYear();
    }

    public static function parseToStartOfDay(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->startOfDay();
        }

        if (blank($value)) {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        foreach (self::KNOWN_FORMATS as $format) {
            try {
                $parsed = CarbonImmutable::createFromFormat($format, $raw);
                if ($parsed instanceof CarbonImmutable) {
                    return $parsed->startOfDay();
                }
            } catch (Throwable) {
                continue;
            }
        }

        try {
            return CarbonImmutable::parse($raw)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }
}
