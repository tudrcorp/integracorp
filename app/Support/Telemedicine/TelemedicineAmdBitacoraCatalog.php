<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\TelemedicineAmdInform;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineConsultationPatient;
use App\Support\UrlPathEncoder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

final class TelemedicineAmdBitacoraCatalog
{
    /**
     * @return array<string, mixed>
     */
    public static function viewContext(TelemedicineCase $case): array
    {
        $case->loadMissing([
            'amdInforms.telemedicineDoctor',
            'amdInforms.telemedicineConsultationPatient',
            'amdInforms.createdBy',
            'amdInforms.supplier',
            'consultations',
        ]);

        $entries = self::entries($case);
        $files = self::files($case);

        return [
            'entries' => $entries,
            'files' => $files,
            'caseCode' => filled($case->code) ? (string) $case->code : 'Caso #'.$case->id,
            'total' => count($entries),
            'filesTotal' => count($files),
            'withDocument' => collect($entries)->where('document_exists', true)->count()
                + collect($files)->where('document_exists', true)->count(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function entries(TelemedicineCase $case): array
    {
        return $case->amdInforms
            ->sortByDesc(fn (TelemedicineAmdInform $inform): int => $inform->created_at?->timestamp ?? 0)
            ->values()
            ->map(fn (TelemedicineAmdInform $inform): array => self::mapInform($inform))
            ->all();
    }

    /**
     * Archivos cargados con «Cargar Archivo AMD», del más reciente al más
     * antiguo. Se reconocen por su etiqueta en los documentos de cada consulta:
     * la fila de `telemedicine_documents` no guarda el tipo.
     *
     * @return list<array{name: string, extension: string, consultation_reference: string, uploaded_at_label: string, uploaded_at_relative: string, is_image: bool, document_exists: bool, download_url: string|null}>
     */
    public static function files(TelemedicineCase $case): array
    {
        $files = [];

        foreach ($case->consultations as $consultation) {
            $documents = is_array($consultation->uploaded_documents) ? $consultation->uploaded_documents : [];

            foreach ($documents as $document) {
                if (! TelemedicineAmdFileRegistrar::isAmdUploadedDocument($document)) {
                    continue;
                }

                $files[] = self::mapFile($document, $consultation);
            }
        }

        usort($files, static fn (array $a, array $b): int => $b['sort_key'] <=> $a['sort_key']);

        return array_map(static function (array $file): array {
            unset($file['sort_key']);

            return $file;
        }, $files);
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    private static function mapFile(array $document, TelemedicineConsultationPatient $consultation): array
    {
        $name = (string) $document['document_name'];
        $filePath = ltrim((string) ($document['file_path'] ?? 'telemedicina-doc/'.$name), '/');
        $exists = $filePath !== '' && Storage::disk('public')->exists($filePath);
        $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));

        try {
            $uploadedAt = filled($document['uploaded_at'] ?? null) ? Carbon::parse((string) $document['uploaded_at']) : null;
        } catch (\Throwable) {
            $uploadedAt = null;
        }

        return [
            'name' => $name,
            'extension' => $extension !== '' ? strtoupper($extension) : '—',
            'consultation_reference' => filled($consultation->code_reference)
                ? (string) $consultation->code_reference
                : 'CONS-'.$consultation->id,
            'uploaded_at_label' => $uploadedAt?->format('d/m/Y H:i') ?? '—',
            'uploaded_at_relative' => $uploadedAt?->diffForHumans() ?? '',
            'is_image' => in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true),
            'document_exists' => $exists,
            'download_url' => $exists ? Storage::disk('public')->url(UrlPathEncoder::encode($filePath)) : null,
            'sort_key' => $uploadedAt?->timestamp ?? 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function mapInform(TelemedicineAmdInform $inform): array
    {
        $filePath = ltrim((string) ($inform->pdf_file_path ?? ''), '/');
        $documentName = (string) ($inform->pdf_document_name ?? '');

        if ($filePath === '' && $documentName !== '') {
            $filePath = 'telemedicina-doc/'.$documentName;
        }

        $exists = $filePath !== '' && Storage::disk('public')->exists($filePath);
        $downloadUrl = $exists ? Storage::disk('public')->url(UrlPathEncoder::encode($filePath)) : null;
        $extension = $documentName !== ''
            ? strtoupper((string) pathinfo($documentName, PATHINFO_EXTENSION))
            : '—';

        $createdAt = $inform->created_at instanceof Carbon ? $inform->created_at : null;

        return [
            'id' => $inform->id,
            'created_at_label' => $createdAt?->format('d/m/Y H:i') ?? '—',
            'created_at_relative' => $createdAt?->diffForHumans() ?? '',
            'consultation_reference' => filled($inform->telemedicineConsultationPatient?->code_reference)
                ? (string) $inform->telemedicineConsultationPatient->code_reference
                : ($inform->telemedicine_consultation_patient_id
                    ? 'CONS-'.$inform->telemedicine_consultation_patient_id
                    : 'Pendiente de consulta'),
            'doctor_name' => (string) ($inform->telemedicineDoctor?->full_name ?? '—'),
            'supplier_name' => (string) ($inform->supplier?->name ?? '—'),
            'created_by_name' => (string) ($inform->createdBy?->name ?? ($inform->created_by ? 'Usuario #'.$inform->created_by : '—')),
            'reason_consultation' => self::text($inform->reason_consultation),
            'actual_phatology' => self::text($inform->actual_phatology),
            'background' => self::text($inform->background),
            'diagnostic_impression' => self::text($inform->diagnostic_impression),
            'pa' => self::text($inform->pa),
            'fc' => self::text($inform->fc),
            'fr' => self::text($inform->fr),
            'temp' => self::text($inform->temp),
            'saturacion' => self::text($inform->saturacion),
            'peso' => filled($inform->peso) ? (string) $inform->peso.' kg' : '—',
            'estatura' => filled($inform->estatura) ? (string) $inform->estatura.' m' : '—',
            'imc' => filled($inform->imc) ? (string) $inform->imc : '—',
            'document_name' => $documentName !== '' ? $documentName : '—',
            'document_extension' => $extension,
            'document_exists' => $exists,
            'download_url' => $downloadUrl,
        ];
    }

    private static function text(mixed $value): string
    {
        return filled($value) ? trim((string) $value) : '—';
    }
}
