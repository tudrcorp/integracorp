<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Enums\TelemedicineCaseAttachmentStage;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineCaseAttachment;
use App\Models\TelemedicineConsultationPatient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

/**
 * Registra los documentos que el médico carga desde el asistente de consulta.
 *
 * La carga es independiente de guardar la consulta: el archivo queda en la
 * bitácora del caso en cuanto se sube, marcado con el momento en que se cargó
 * (consulta inicial o seguimiento N), aunque el médico abandone el asistente.
 */
final class TelemedicineCaseAttachmentRegistrar
{
    public const DISK = 'public';

    public const BASE_DIRECTORY = 'telemedicina-doc/adjuntos';

    public const MAX_FILES = 5;

    public const MAX_SIZE_KB = 10240;

    /**
     * @var list<string>
     */
    public const ACCEPTED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/heic',
        'image/heif',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    public static function directoryForCase(int $caseId): string
    {
        return self::BASE_DIRECTORY.'/'.$caseId;
    }

    /**
     * Momento del caso que corresponde a la pantalla abierta.
     *
     * Sin consultas previas, o editando la consulta inicial, es la consulta
     * inicial. Si no, es un seguimiento: el número es el de los seguimientos ya
     * registrados más el que se está llenando, salvo cuando se reabre el último
     * para editarlo, que conserva su número.
     *
     * @return array{stage: TelemedicineCaseAttachmentStage, follow_up_number: int|null}
     */
    public static function resolveStage(int $caseId, bool $isEditingInitialConsultation, bool $isEditingExistingConsultation): array
    {
        $consultations = TelemedicineConsultationPatient::query()
            ->where('telemedicine_case_id', $caseId)
            ->count();

        if ($consultations < 1 || $isEditingInitialConsultation) {
            return [
                'stage' => TelemedicineCaseAttachmentStage::InitialConsultation,
                'follow_up_number' => null,
            ];
        }

        $followUps = TelemedicineConsultationPatient::query()
            ->where('telemedicine_case_id', $caseId)
            ->where('status', '!=', 'CONSULTA INICIAL')
            ->count();

        return [
            'stage' => TelemedicineCaseAttachmentStage::FollowUp,
            'follow_up_number' => max(1, $isEditingExistingConsultation ? $followUps : $followUps + 1),
        ];
    }

    /**
     * @param  array<int|string, mixed>  $storedPaths  rutas en el disco público que devolvió el FileUpload
     * @param  array<string, mixed>  $originalNames  ruta => nombre original del archivo
     * @return list<TelemedicineCaseAttachment>
     */
    public static function register(
        TelemedicineCase $case,
        array $storedPaths,
        array $originalNames,
        ?string $description,
        TelemedicineCaseAttachmentStage $stage,
        ?int $followUpNumber,
        ?int $consultationId,
        ?int $doctorId,
        ?int $userId,
    ): array {
        $directory = self::directoryForCase((int) $case->id).'/';
        $disk = Storage::disk(self::DISK);
        $paths = [];

        foreach ($storedPaths as $path) {
            $path = ltrim(trim((string) $path), '/');

            if ($path === '' || ! str_starts_with($path, $directory) || str_contains($path, '..')) {
                continue;
            }

            if ($disk->exists($path)) {
                $paths[] = $path;
            }
        }

        $paths = array_values(array_unique($paths));

        if ($paths === []) {
            throw new InvalidArgumentException('No se recibió ningún archivo válido para este caso.');
        }

        if (count($paths) > self::MAX_FILES) {
            self::deleteFiles($paths);

            throw new InvalidArgumentException('Puede cargar hasta '.self::MAX_FILES.' archivos a la vez.');
        }

        $description = trim((string) $description);

        try {
            return DB::transaction(function () use ($case, $paths, $originalNames, $description, $stage, $followUpNumber, $consultationId, $doctorId, $userId, $disk): array {
                $attachments = [];

                foreach ($paths as $path) {
                    $originalName = trim((string) ($originalNames[$path] ?? ''));

                    $attachments[] = TelemedicineCaseAttachment::query()->create([
                        'telemedicine_case_id' => (int) $case->id,
                        'telemedicine_patient_id' => $case->telemedicine_patient_id !== null ? (int) $case->telemedicine_patient_id : null,
                        'telemedicine_consultation_patient_id' => $consultationId !== null && $consultationId > 0 ? $consultationId : null,
                        'telemedicine_doctor_id' => $doctorId !== null && $doctorId > 0 ? $doctorId : null,
                        'stage' => $stage,
                        'follow_up_number' => $stage === TelemedicineCaseAttachmentStage::FollowUp ? $followUpNumber : null,
                        'file_path' => $path,
                        'original_name' => mb_substr($originalName !== '' ? $originalName : basename($path), 0, 255),
                        'mime_type' => self::safely(fn (): ?string => $disk->mimeType($path) ?: null),
                        'size' => self::safely(fn (): ?int => $disk->size($path)),
                        'description' => $description !== '' ? mb_substr($description, 0, 500) : null,
                        'uploaded_by' => $userId,
                    ]);
                }

                return $attachments;
            });
        } catch (Throwable $exception) {
            self::deleteFiles($paths);

            throw $exception;
        }
    }

    /**
     * @param  list<string>  $paths
     */
    public static function deleteFiles(array $paths): void
    {
        foreach ($paths as $path) {
            self::safely(fn (): bool => Storage::disk(self::DISK)->delete($path));
        }
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T|null
     */
    private static function safely(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (Throwable) {
            return null;
        }
    }
}
