<?php

declare(strict_types=1);

namespace App\Support\Viveplus;

use App\Enums\ViveplusAffiliationType;
use App\Enums\ViveplusDocumentType;

final class ViveplusDocumentWebhookPayload
{
    public function __construct(
        public ViveplusAffiliationType $affiliationType,
        public string $affiliationCode,
        public ViveplusDocumentType $documentType,
        public string $absolutePath,
        public string $generatedAt,
        public string $idempotencyKey,
        public string $affiliateIdentification = '',
    ) {}

    /**
     * @return array{
     *     affiliation_type: string,
     *     affiliation_code: string,
     *     document_type: string,
     *     affiliate_identification: string,
     *     checksum_sha256: string,
     *     generated_at: string,
     *     idempotency_key: string
     * }
     */
    public function fields(string $checksumSha256): array
    {
        return [
            'affiliation_type' => $this->affiliationType->value,
            'affiliation_code' => $this->affiliationCode,
            'document_type' => $this->documentType->value,
            'affiliate_identification' => $this->documentType === ViveplusDocumentType::Certificado
                ? ''
                : $this->affiliateIdentification,
            'checksum_sha256' => $checksumSha256,
            'generated_at' => $this->generatedAt,
            'idempotency_key' => $this->idempotencyKey,
        ];
    }
}
