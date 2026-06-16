<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\TelemedicineAmdInform;
use App\Models\TelemedicineCase;
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
        ]);

        $entries = self::entries($case);

        return [
            'entries' => $entries,
            'caseCode' => filled($case->code) ? (string) $case->code : 'Caso #'.$case->id,
            'total' => count($entries),
            'withDocument' => collect($entries)->where('document_exists', true)->count(),
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
        $downloadUrl = $exists ? Storage::disk('public')->url($filePath) : null;
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
