<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use Carbon\Carbon;
use DateTimeInterface;
use Throwable;

final class TelemedicinePatientBirthDate
{
    /**
     * @var list<string>
     */
    private const FORMATS = ['d/m/Y', 'Y-m-d', 'd-m-Y', 'd/m/y'];

    public static function parse(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy()->startOfDay();
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->startOfDay();
        }

        if (blank($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        foreach (self::FORMATS as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $trimmed);

                if ($parsed instanceof Carbon) {
                    return $parsed->startOfDay();
                }
            } catch (Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($trimmed)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    public static function age(mixed $value): ?int
    {
        $parsed = self::parse($value);

        return $parsed?->age;
    }
}
