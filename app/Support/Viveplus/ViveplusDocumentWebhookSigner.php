<?php

declare(strict_types=1);

namespace App\Support\Viveplus;

final class ViveplusDocumentWebhookSigner
{
    /**
     * @param  array{
     *     affiliation_type: string,
     *     affiliation_code: string,
     *     document_type: string,
     *     affiliate_identification: string,
     *     checksum_sha256: string,
     *     generated_at: string,
     *     idempotency_key: string
     * }  $fields
     */
    public static function canonicalString(array $fields): string
    {
        return implode('&', [
            'affiliation_type='.$fields['affiliation_type'],
            'affiliation_code='.$fields['affiliation_code'],
            'document_type='.$fields['document_type'],
            'affiliate_identification='.$fields['affiliate_identification'],
            'checksum_sha256='.$fields['checksum_sha256'],
            'generated_at='.$fields['generated_at'],
            'idempotency_key='.$fields['idempotency_key'],
        ]);
    }

    public static function signature(string $canonical, string $secret): string
    {
        return hash_hmac('sha256', $canonical, $secret);
    }
}
