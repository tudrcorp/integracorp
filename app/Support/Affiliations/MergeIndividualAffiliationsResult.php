<?php

declare(strict_types=1);

namespace App\Support\Affiliations;

final class MergeIndividualAffiliationsResult
{
    /**
     * @param  list<string>  $excludedCodes
     * @param  list<int>  $movedAffiliateIds
     */
    public function __construct(
        public int $targetAffiliationId,
        public string $targetCode,
        public array $excludedCodes,
        public array $movedAffiliateIds,
        public float $newFeeAnual,
        public float $newTotalAmount,
        public int $newFamilyMembers,
        public int $cancelledCollections,
        public int $recalculatedCollections,
        public int $cancelledRenovations,
        public int $updatedTelemedicinePatients,
    ) {}
}
