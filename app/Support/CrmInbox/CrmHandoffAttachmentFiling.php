<?php

declare(strict_types=1);

namespace App\Support\CrmInbox;

use App\Enums\TelemedicineCaseAttachmentStage;
use App\Models\CrmHandoffAttachment;
use App\Models\CrmHandoffEnvelope;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineCaseAttachment;
use App\Support\SecurityAudit;
use App\Support\Telemedicine\TelemedicineCaseAttachmentRegistrar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Al cerrar una conversación, los archivos que envió el cliente van al caso de telemedicina que eligió el
 * analista (nunca se adivina el caso), o se marcan como «no corresponden a un caso».
 *
 * La copia vive en la carpeta de adjuntos del caso, como los documentos del médico, y aparece en la bitácora
 * con la etapa «WhatsApp del cliente». El original queda en el disco privado del CRM hasta su purga.
 */
final class CrmHandoffAttachmentFiling
{
    public const NO_CASE = 'none';

    public const SEARCH_MIN = 3;

    public const SEARCH_LIMIT = 8;

    private const COLUMNS = ['id', 'code', 'patient_name', 'status', 'created_at'];

    /**
     * @return array{ok: true, filed: int, skipped: int, case_code: ?string}|array{ok: false, reason: 'choose'|'case'|'copy'}
     */
    public function file(CrmHandoffEnvelope $envelope, string $choice, ?int $userId): array
    {
        $choice = trim($choice);

        if (! CrmHandoffMedia::forEnvelope($envelope)->pendingFiling()->exists()) {
            return ['ok' => true, 'filed' => 0, 'skipped' => 0, 'case_code' => null];
        }

        if ($choice === self::NO_CASE) {
            return $this->skip($envelope, $userId);
        }

        if ($choice === '' || ! ctype_digit($choice)) {
            return ['ok' => false, 'reason' => 'choose'];
        }

        $case = self::caseQuery()->whereKey((int) $choice)->first();

        if ($case === null) {
            return ['ok' => false, 'reason' => 'case'];
        }

        return $this->copyToCase($envelope, $case, $userId);
    }

    /**
     * Casos que probablemente corresponden: los del mismo teléfono y los que ya recibieron archivos de este
     * número. Solo sugerencias: el analista elige.
     *
     * @return list<array{id: int, label: string, detail: string, hint: ?string}>
     */
    public static function suggestions(CrmHandoffEnvelope $envelope): array
    {
        $tail = self::phoneTail((string) $envelope->phone);
        $previous = CrmHandoffAttachment::query()
            ->where('phone', $envelope->phone)
            ->whereNotNull('telemedicine_case_id')
            ->orderByDesc('filed_at')
            ->limit(20)
            ->pluck('telemedicine_case_id')
            ->unique()
            ->values()
            ->all();

        if ($tail === null && $previous === []) {
            return [];
        }

        return self::caseQuery()
            ->where(function ($query) use ($tail, $previous): void {
                if ($tail !== null) {
                    $query->where('patient_phone', 'like', '%'.$tail)
                        ->orWhere('patient_phone_2', 'like', '%'.$tail);
                }

                if ($previous !== []) {
                    $query->orWhereIn('id', $previous);
                }
            })
            ->orderByDesc('id')
            ->limit(5)
            ->get(self::COLUMNS)
            ->map(fn (TelemedicineCase $case): array => self::option($case, in_array($case->id, $previous, true) ? 'Ya recibió archivos de este número' : 'Mismo teléfono'))
            ->all();
    }

    /**
     * Búsqueda por número de caso, nombre del paciente o teléfono.
     *
     * @return list<array{id: int, label: string, detail: string, hint: ?string}>
     */
    public static function search(string $term): array
    {
        $term = trim(preg_replace('/\s+/u', ' ', $term) ?? '');

        if (mb_strlen($term) < self::SEARCH_MIN) {
            return [];
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], mb_substr($term, 0, 60)).'%';
        $tail = self::phoneTail($term);

        return self::caseQuery()
            ->where(function ($query) use ($like, $tail): void {
                $query->where('code', 'like', $like)->orWhere('patient_name', 'like', $like);

                if ($tail !== null) {
                    $query->orWhere('patient_phone', 'like', '%'.$tail)->orWhere('patient_phone_2', 'like', '%'.$tail);
                }
            })
            ->orderByDesc('id')
            ->limit(self::SEARCH_LIMIT)
            ->get(self::COLUMNS)
            ->map(fn (TelemedicineCase $case): array => self::option($case, null))
            ->all();
    }

    /**
     * @return Builder<TelemedicineCase>
     */
    private static function caseQuery(): Builder
    {
        return TelemedicineCase::query()->whereNull('logically_deleted_at');
    }

    /**
     * @return array{id: int, label: string, detail: string, hint: ?string}
     */
    private static function option(TelemedicineCase $case, ?string $hint): array
    {
        return [
            'id' => (int) $case->id,
            'label' => 'Caso '.((string) $case->code !== '' ? $case->code : '#'.$case->id),
            'detail' => trim(implode(' · ', array_filter([
                mb_substr(trim((string) $case->patient_name), 0, 60),
                ucfirst(mb_strtolower((string) $case->status)),
                $case->created_at?->timezone('America/Caracas')->format('d/m/Y'),
            ]))),
            'hint' => $hint,
        ];
    }

    /**
     * Últimos 10 dígitos: 584141234567, 04141234567 y 4141234567 son el mismo número.
     */
    private static function phoneTail(string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return strlen($digits) >= 10 ? substr($digits, -10) : null;
    }

    /**
     * @return array{ok: true, filed: int, skipped: int, case_code: ?string}
     */
    private function skip(CrmHandoffEnvelope $envelope, ?int $userId): array
    {
        $count = DB::transaction(function () use ($envelope, $userId): int {
            $pending = CrmHandoffMedia::forEnvelope($envelope)->pendingFiling()->lockForUpdate()->get(['id']);

            if ($pending->isEmpty()) {
                return 0;
            }

            return CrmHandoffAttachment::query()->whereKey($pending->modelKeys())->update([
                'handoff_id' => $envelope->handoff_id,
                'filing_skipped' => true,
                'filed_by' => $userId,
                'updated_at' => now(),
            ]);
        });

        return ['ok' => true, 'filed' => 0, 'skipped' => $count, 'case_code' => null];
    }

    /**
     * @return array{ok: true, filed: int, skipped: int, case_code: ?string}|array{ok: false, reason: 'copy'}
     */
    private function copyToCase(CrmHandoffEnvelope $envelope, TelemedicineCase $case, ?int $userId): array
    {
        $public = Storage::disk(TelemedicineCaseAttachmentRegistrar::DISK);
        $copied = [];

        try {
            $filed = DB::transaction(function () use ($envelope, $case, $userId, $public, &$copied): int {
                $pending = CrmHandoffMedia::forEnvelope($envelope)->pendingFiling()->lockForUpdate()->orderBy('id')->get();

                foreach ($pending as $attachment) {
                    $target = TelemedicineCaseAttachmentRegistrar::directoryForCase((int) $case->id)
                        .'/whatsapp-'.Str::uuid()->toString().'.'.$attachment->extension;
                    $stream = Storage::disk((string) $attachment->disk)->readStream((string) $attachment->path);

                    if (! is_resource($stream)) {
                        throw new RuntimeException('No se encontró el archivo '.$attachment->id.' en el disco del CRM.');
                    }

                    try {
                        if (! $public->writeStream($target, $stream)) {
                            throw new RuntimeException('No se pudo copiar el archivo '.$attachment->id.' al caso.');
                        }
                    } finally {
                        if (is_resource($stream)) {
                            fclose($stream);
                        }
                    }

                    $copied[] = $target;
                    $caption = trim((string) $attachment->caption);

                    $caseAttachment = TelemedicineCaseAttachment::query()->create([
                        'telemedicine_case_id' => (int) $case->id,
                        'telemedicine_patient_id' => $case->telemedicine_patient_id !== null ? (int) $case->telemedicine_patient_id : null,
                        'stage' => TelemedicineCaseAttachmentStage::WhatsApp,
                        'file_path' => $target,
                        'original_name' => mb_substr((string) $attachment->original_name, 0, 255),
                        'mime_type' => (string) $attachment->mime_type,
                        'size' => (int) $attachment->size_bytes,
                        'description' => mb_substr('Atención WhatsApp · '.$attachment->created_at?->timezone('America/Caracas')->format('d/m/Y H:i').($caption !== '' ? ' · '.$caption : ''), 0, 500),
                        'uploaded_by' => $userId,
                    ]);

                    $attachment->forceFill([
                        'handoff_id' => $envelope->handoff_id,
                        'telemedicine_case_id' => (int) $case->id,
                        'telemedicine_case_attachment_id' => $caseAttachment->id,
                        'filed_at' => now(),
                        'filed_by' => $userId,
                    ])->save();
                }

                return $pending->count();
            });
        } catch (Throwable $exception) {
            foreach ($copied as $path) {
                try {
                    $public->delete($path);
                } catch (Throwable) {
                }
            }

            report($exception);

            return ['ok' => false, 'reason' => 'copy'];
        }

        if ($filed > 0) {
            SecurityAudit::log('AUDIT_CRM_ATTACHMENTS_FILED', 'crm.attachments.file', [
                'handoff_id' => $envelope->handoff_id,
                'telemedicine_case_id' => $case->id,
                'files' => $filed,
            ]);
        }

        return ['ok' => true, 'filed' => $filed, 'skipped' => 0, 'case_code' => (string) $case->code];
    }
}
