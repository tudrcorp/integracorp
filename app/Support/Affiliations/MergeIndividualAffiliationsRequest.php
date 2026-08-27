<?php

declare(strict_types=1);

namespace App\Support\Affiliations;

final class MergeIndividualAffiliationsRequest
{
    /**
     * @param  list<int>  $sourceAffiliationIds
     * @param  array<int, string>  $relationships  affiliate_id => parentesco
     */
    public function __construct(
        public int $targetAffiliationId,
        public array $sourceAffiliationIds,
        public int $titularAffiliateId,
        public array $relationships,
        public string $reason,
        public string $actorName,
        public ?int $actorUserId = null,
    ) {
        $this->sourceAffiliationIds = array_values(array_unique(array_map(
            static fn (mixed $id): int => (int) $id,
            $sourceAffiliationIds,
        )));

        $normalized = [];
        foreach ($relationships as $affiliateId => $relationship) {
            $normalized[(int) $affiliateId] = MergeIndividualAffiliationsService::normalizeRelationship((string) $relationship);
        }
        $this->relationships = $normalized;
        $this->reason = trim($reason);
        $this->actorName = trim($actorName) !== '' ? trim($actorName) : 'SISTEMA';
    }
}
