<?php

declare(strict_types=1);

namespace App\Support\Companies;

use App\Models\Company;
use App\Models\CompanyResponsible;
use Carbon\Carbon;
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
