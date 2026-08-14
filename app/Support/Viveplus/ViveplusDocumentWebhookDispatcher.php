<?php

declare(strict_types=1);

namespace App\Support\Viveplus;

use App\Enums\ViveplusAffiliationType;
use App\Enums\ViveplusDocumentType;
use App\Jobs\PushAffiliationDocumentToViveplusJob;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Services\AffiliationBusinessDocumentsService;
use App\Services\AffiliationCorporateBusinessDocumentsService;
use App\Support\AffiliationWhiteCompany;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class ViveplusDocumentWebhookDispatcher
{
    public static function dispatchForIndividual(Affiliation $affiliation, ?int $notifiedUserId = null): void
    {
        if (! AffiliationWhiteCompany::belongsToAlliedCompany($affiliation)) {
            Log::info('Viveplus document webhook: omitido; la afiliación individual no pertenece a una empresa aliada', [
                'affiliation_code' => $affiliation->code,
            ]);

            return;
        }

        $documents = [];

        $certificatePath = AffiliationBusinessDocumentsService::resolveCertificateAbsolutePath($affiliation);
        if (is_string($certificatePath)) {
            $documents[] = [
                'type' => ViveplusDocumentType::Certificado,
                'path' => $certificatePath,
                'affiliate_identification' => '',
            ];
        }

        foreach (AffiliationBusinessDocumentsService::resolveAffiliateCarnetDocuments($affiliation) as $carnet) {
            $documents[] = [
                'type' => ViveplusDocumentType::Carnet,
                'path' => $carnet['path'],
                'affiliate_identification' => $carnet['identification'],
            ];
        }

        self::dispatchDocuments(
            ViveplusAffiliationType::Individual,
            (string) $affiliation->code,
            $documents,
            $notifiedUserId,
        );
    }

    public static function dispatchForCorporate(AffiliationCorporate $affiliation, ?int $notifiedUserId = null): void
    {
        $documents = [];

        $certificatePath = AffiliationCorporateBusinessDocumentsService::resolveCertificateAbsolutePath($affiliation);
        if (is_string($certificatePath)) {
            $documents[] = [
                'type' => ViveplusDocumentType::Certificado,
                'path' => $certificatePath,
                'affiliate_identification' => '',
            ];
        }

        foreach (AffiliationCorporateBusinessDocumentsService::resolveAffiliateCarnetDocuments($affiliation) as $carnet) {
            $documents[] = [
                'type' => ViveplusDocumentType::Carnet,
                'path' => $carnet['path'],
                'affiliate_identification' => $carnet['identification'],
            ];
        }

        self::dispatchDocuments(
            ViveplusAffiliationType::Corporate,
            (string) $affiliation->code,
            $documents,
            $notifiedUserId,
        );
    }

    /**
     * @param  array<int, array{type: ViveplusDocumentType, path: string, affiliate_identification?: string}>  $documents
     */
    public static function dispatchDocuments(
        ViveplusAffiliationType $affiliationType,
        string $affiliationCode,
        array $documents,
        ?int $notifiedUserId = null,
    ): void {
        if ($documents === []) {
            return;
        }

        if (! ViveplusDocumentWebhookClient::isConfigured()) {
            Log::error('Viveplus document webhook: no configurado; los documentos no se enviaron', [
                'affiliation_type' => $affiliationType->value,
                'affiliation_code' => $affiliationCode,
                'documents_count' => count($documents),
            ]);

            app(ViveplusDocumentWebhookAnalystNotifier::class)->notifyDeliveryFailed(
                $notifiedUserId,
                $affiliationCode,
                $documents[0]['type']->value,
                'El webhook de ViVEplus no está configurado (URL, token o secreto vacíos).',
            );

            return;
        }

        $generatedAt = now()->toIso8601String();

        foreach ($documents as $document) {
            $pending = PushAffiliationDocumentToViveplusJob::dispatch(
                $affiliationType->value,
                $affiliationCode,
                $document['type']->value,
                $document['path'],
                $generatedAt,
                (string) Str::uuid(),
                $notifiedUserId,
                0,
                $document['affiliate_identification'] ?? '',
            );

            if (! app()->runningUnitTests()) {
                $pending->afterResponse();
            }
        }
    }
}
