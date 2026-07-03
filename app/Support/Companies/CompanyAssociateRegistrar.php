<?php

declare(strict_types=1);

namespace App\Support\Companies;

use App\Models\Company;
use App\Models\CompanyResponsible;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

final class CompanyAssociateRegistrar
{
    public static function normalizeIdentityCard(?string $value): string
    {
        return Str::upper(trim(str_replace([' ', '.', '-'], '', (string) $value)));
    }

    public static function calculateAge(?string $birthDate): ?int
    {
        if (blank($birthDate)) {
            return null;
        }

        try {
            return Carbon::parse($birthDate)->age;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function calculateDaysBetween(mixed $startDate, mixed $endDate): ?int
    {
        $start = self::parseToStartOfDay($startDate);
        $end = self::parseToStartOfDay($endDate);

        if ($start === null || $end === null || $end->lt($start)) {
            return null;
        }

        return (int) $start->diffInDays($end);
    }

    public static function remainingContractedDays(int $contractedDays, ?int $calculatedDays): ?int
    {
        if ($calculatedDays === null) {
            return null;
        }

        return $contractedDays - $calculatedDays;
    }

    public static function consumedDaysByResponsible(CompanyResponsible $responsible): int
    {
        return (int) $responsible->associates()->sum('registration_period_days');
    }

    public static function availableDaysForResponsible(CompanyResponsible $responsible): int
    {
        return max(0, (int) $responsible->contracted_days - self::consumedDaysByResponsible($responsible));
    }

    public static function hasExhaustedRegistrationDays(CompanyResponsible $responsible): bool
    {
        return self::availableDaysForResponsible($responsible) <= 0;
    }

    public static function remainingDaysAfterRegistration(CompanyResponsible $responsible, ?int $calculatedDays): ?int
    {
        $available = self::availableDaysForResponsible($responsible);

        if ($calculatedDays === null) {
            return $available;
        }

        return $available - $calculatedDays;
    }

    public static function parseToStartOfDay(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return Carbon::instance($value)->startOfDay();
        }

        $value = trim((string) $value);

        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->startOfDay();
            } catch (\Throwable) {
            }
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    public static function normalizeInternationalPhone(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        $hasPlus = str_starts_with($value, '+');
        $digits = preg_replace('/\D/', '', $value) ?? '';

        return $hasPlus ? '+'.$digits : $digits;
    }

    public static function findResponsibleForCompany(Company $company, string $identityCard): ?CompanyResponsible
    {
        $normalized = self::normalizeIdentityCard($identityCard);

        if ($normalized === '') {
            return null;
        }

        return $company->responsibles()
            ->get()
            ->first(fn (CompanyResponsible $responsible): bool => self::normalizeIdentityCard($responsible->identity_card) === $normalized);
    }

    public static function publicRegistrationUrl(Company $company): string
    {
        $token = $company->registration_token;

        if (blank($token)) {
            $company->update(['registration_token' => (string) Str::uuid()]);
            $token = $company->fresh()?->registration_token;
        }

        return route('company-associates.register', ['token' => $token]);
    }
}
