<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\Affiliate;
use App\Models\AffiliateCorporate;

final class AffiliatePersonalDataUpdateResult
{
    /**
     * @param  array<string, array{label: string, before: mixed, after: mixed}>  $changes
     */
    public function __construct(
        public readonly Affiliate|AffiliateCorporate $affiliate,
        public readonly array $changes,
        public readonly bool $telemedicineSynced,
        public readonly ?string $ageRangeWarning,
    ) {}

    public function hasChanges(): bool
    {
        return $this->changes !== [];
    }
}
