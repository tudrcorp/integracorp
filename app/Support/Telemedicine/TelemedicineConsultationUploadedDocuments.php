<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\TelemedicineConsultationPatient;

final class TelemedicineConsultationUploadedDocuments
{
    /**
     * @param  array<string, mixed>  $newDocument
     */
    public static function sync(
        TelemedicineConsultationPatient $consultation,
        array $newDocument,
        ?int $replaceDocumentTypeId = null,
    ): void {
        $existingDocuments = is_array($consultation->uploaded_documents)
            ? $consultation->uploaded_documents
            : [];

        $documentName = (string) ($newDocument['document_name'] ?? '');

        $filtered = array_values(array_filter(
            $existingDocuments,
            static function (mixed $document) use ($documentName, $replaceDocumentTypeId): bool {
                if (! is_array($document)) {
                    return false;
                }

                if ($documentName !== '' && ($document['document_name'] ?? null) === $documentName) {
                    return false;
                }

                if ($replaceDocumentTypeId === null) {
                    return true;
                }

                $typeIds = is_array($document['document_type_ids'] ?? null)
                    ? $document['document_type_ids']
                    : [];

                return ! in_array($replaceDocumentTypeId, $typeIds, true);
            },
        ));

        $consultation->update([
            'uploaded_documents' => array_values(array_merge($filtered, [$newDocument])),
        ]);
    }
}
