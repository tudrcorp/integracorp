<?php

declare(strict_types=1);

namespace App\Support\Affiliation;

use App\Models\Affiliation;
use App\Models\AffiliationCorporate;

final class AffiliationDocumentAffiliatesCount
{
    public static function forIndividual(?Affiliation $affiliation): int
    {
        if ($affiliation === null) {
            return 0;
        }

        if (isset($affiliation->affiliates_count)) {
            return (int) $affiliation->affiliates_count;
        }

        return (int) ($affiliation->affiliates()->count() ?: $affiliation->family_members ?? 0);
    }

    public static function forCorporate(?AffiliationCorporate $affiliation): int
    {
        if ($affiliation === null) {
            return 0;
        }

        if (isset($affiliation->corporate_affiliates_count)) {
            return (int) $affiliation->corporate_affiliates_count;
        }

        $familyMembers = $affiliation->getAttribute('family_members');

        return (int) ($affiliation->corporateAffiliates()->count() ?: $familyMembers ?? 0);
    }

    public static function forAffiliationCode(string $code, bool $isCorporate): int
    {
        if ($isCorporate) {
            $affiliation = AffiliationCorporate::query()
                ->where('code', $code)
                ->withCount('corporateAffiliates')
                ->first();

            return self::forCorporate($affiliation);
        }

        $affiliation = Affiliation::query()
            ->where('code', $code)
            ->withCount('affiliates')
            ->first();

        return self::forIndividual($affiliation);
    }
}
