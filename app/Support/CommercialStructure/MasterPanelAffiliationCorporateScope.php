<?php

declare(strict_types=1);

namespace App\Support\CommercialStructure;

use App\Models\Agency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Alcance de afiliaciones corporativas visibles para una agencia master en su panel Filament.
 *
 * Las ventas bajo una agencia general suelen persistir owner_code = código de la master.
 * Las ventas directas de una master bajo TDG persisten owner_code = TDG-100 (Agency.owner_code),
 * mientras code_agency sigue siendo el código de la master: por eso no basta con
 * where('owner_code', Auth::user()->code_agency).
 */
final class MasterPanelAffiliationCorporateScope
{
    /**
     * @return list<string>
     */
    public static function networkAgencyCodes(?string $masterAgencyCode = null): array
    {
        $master = trim((string) ($masterAgencyCode ?? Auth::user()?->code_agency));

        if ($master === '') {
            return [];
        }

        $codes = Agency::query()
            ->where('owner_code', $master)
            ->pluck('code')
            ->map(fn (?string $code): string => trim((string) $code))
            ->filter(fn (string $code): bool => $code !== '')
            ->all();

        $codes[] = $master;

        return array_values(array_unique($codes));
    }

    /**
     * @param  Builder<\App\Models\AffiliationCorporate>  $query
     * @return Builder<\App\Models\AffiliationCorporate>
     */
    public static function apply(Builder $query, ?string $masterAgencyCode = null): Builder
    {
        $agencyCodes = self::networkAgencyCodes($masterAgencyCode);

        if ($agencyCodes === []) {
            return $query->whereRaw('0 = 1');
        }

        return $query->where(function (Builder $scoped) use ($agencyCodes): void {
            $scoped
                ->whereIn('owner_code', $agencyCodes)
                ->orWhereIn('code_agency', $agencyCodes);
        });
    }

    /**
     * @param  list<string>  $networkAgencyCodes
     */
    public static function recordMatchesNetwork(string $ownerCode, string $codeAgency, array $networkAgencyCodes): bool
    {
        $ownerCode = trim($ownerCode);
        $codeAgency = trim($codeAgency);

        if ($ownerCode === '' && $codeAgency === '') {
            return false;
        }

        return in_array($ownerCode, $networkAgencyCodes, true)
            || in_array($codeAgency, $networkAgencyCodes, true);
    }
}
