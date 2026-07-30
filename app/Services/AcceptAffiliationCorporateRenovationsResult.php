<?php

declare(strict_types=1);

namespace App\Services;

final class AcceptAffiliationCorporateRenovationsResult
{
    /**
     * @param  list<string>  $messages
     */
    public function __construct(
        public readonly int $accepted,
        public readonly int $skipped,
        public readonly array $messages,
    ) {}
}
