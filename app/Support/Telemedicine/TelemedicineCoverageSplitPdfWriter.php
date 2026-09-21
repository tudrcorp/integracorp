<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\OperationDocumentList;
use App\Models\TelemedicineConsultationPatient;
use Barryvdh\DomPDF\Facade\Pdf;

final class TelemedicineCoverageSplitPdfWriter
{
    /**
     * @param  list<array{group: string, payload: array<string, mixed>}>  $groups
     * @param  array<string, mixed>  $sourceData
     * @return list<string>
     */
    public static function write(
        string $baseType,
        string $view,
        string $orientation,
        int $documentTypeId,
        string $fallbackTypeName,
        array $groups,
        array $sourceData,
    ): array {
        $typeName = self::documentTypeName($documentTypeId, $fallbackTypeName);
        $familyFilenames = TelemedicineCoverageDocumentSplit::familyFilenames($sourceData, $baseType);

        self::deleteFamilyFiles($familyFilenames);

        $filenames = [];
        $documents = [];

        foreach ($groups as $group) {
            $payload = is_array($group['payload'] ?? null) ? $group['payload'] : [];
            $groupKey = (string) ($group['group'] ?? '');
            if ($payload === [] || $groupKey === '') {
                continue;
            }

            $label = TelemedicineCoverageDocumentSplit::label($groupKey);
            $payload['coverage_group'] = $label;
            $namePdf = TelemedicineCoverageDocumentSplit::filename(
                $payload,
                TelemedicineCoverageDocumentSplit::fileKey($baseType, $groupKey),
            );

            $directory = public_path('storage/telemedicina-doc');
            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            Pdf::loadView($view, ['data' => $payload])
                ->setPaper('a4', $orientation)
                ->save($directory.'/'.$namePdf);

            $filenames[] = $namePdf;
            $documents[] = [
                'document_name' => $namePdf,
                'file_path' => 'telemedicina-doc/'.$namePdf,
                'document_type_ids' => [$documentTypeId],
                'document_types' => [$typeName.' ('.$label.')'],
                'uploaded_at' => now()->toDateTimeString(),
            ];
        }

        self::syncConsultation($sourceData, $documentTypeId, $documents, $familyFilenames);

        return $filenames;
    }

    /**
     * @param  list<string>  $filenames
     */
    private static function deleteFamilyFiles(array $filenames): void
    {
        $directory = public_path('storage/telemedicina-doc');

        foreach ($filenames as $filename) {
            $path = $directory.'/'.$filename;
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $sourceData
     * @param  list<array<string, mixed>>  $documents
     * @param  list<string>  $familyFilenames
     */
    private static function syncConsultation(
        array $sourceData,
        int $documentTypeId,
        array $documents,
        array $familyFilenames,
    ): void {
        $consultationId = (int) ($sourceData['telemedicine_consultation_id'] ?? 0);
        if ($consultationId <= 0) {
            return;
        }

        $consultation = TelemedicineConsultationPatient::query()->find($consultationId);
        if (! $consultation) {
            return;
        }

        TelemedicineConsultationUploadedDocuments::replaceFamily(
            $consultation,
            $documentTypeId,
            $documents,
            $familyFilenames,
        );
    }

    private static function documentTypeName(int $documentTypeId, string $fallback): string
    {
        $name = trim((string) OperationDocumentList::query()
            ->whereKey($documentTypeId)
            ->value('name'));

        return $name !== '' ? $name : $fallback;
    }
}
