<?php

declare(strict_types=1);

namespace App\Support\Affiliations;

final class MergeIndividualAffiliationsRequest
{
    /**
     * @param  list<int>  $sourceAffiliationIds
     * @param  array<int, string>  $relationships  affiliate_id => parentesco
     * @param  string|null  $effectiveDate  vigencia del grupo unificado (d/m/Y o Y-m-d); null = la de la póliza destino
     * @param  string|null  $paymentFrequency  frecuencia del grupo unificado; null = la de la póliza destino
     */
    public function __construct(
        public int $targetAffiliationId,
        public array $sourceAffiliationIds,
        public int $titularAffiliateId,
        public array $relationships,
        public string $reason,
        public string $actorName,
        public ?int $actorUserId = null,
        public ?string $effectiveDate = null,
        public ?string $paymentFrequency = null,
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
        $this->effectiveDate = filled($effectiveDate) ? trim((string) $effectiveDate) : null;
        $this->paymentFrequency = filled($paymentFrequency) ? mb_strtoupper(trim((string) $paymentFrequency), 'UTF-8') : null;
    }
}
