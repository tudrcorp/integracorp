<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Affiliate;
use App\Models\Affiliation;
use InvalidArgumentException;

final class AffiliationAffiliateTypeSynchronizer
{
    public function sync(Affiliation $affiliation, mixed $affiliationType): int
    {
        if (blank($affiliationType)) {
            throw new InvalidArgumentException('Debe definir el tipo de afiliación antes de sincronizar.');
        }

        return Affiliate::query()
            ->where('affiliation_id', $affiliation->id)
            ->update([
                'affiliation_type' => (string) $affiliationType,
            ]);
    }
}
