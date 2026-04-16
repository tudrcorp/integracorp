<?php

namespace App\Services;

use App\Http\Controllers\AffiliationController;
use App\Http\Controllers\TarjetaAfiliacionController;
use App\Jobs\GenerateCorporateAffiliateTarjetasChunkJob;
use App\Models\AffiliationCorporate;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Bus\Batch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

class AffiliationCorporateBusinessDocumentsService
{
    /**
     * @return array{
     *   queued: bool,
     *   task_id?: string,
     *   progress_percentage?: int,
     *   eta_seconds?: int|null,
     *   documents?: array<int, array{label: string, kind: string, filename: string, preview_url: string}>
     * }
     */
    public static function regenerateCertificateAndTarjetas(AffiliationCorporate $record, ?int $userId): array
    {
        $record->loadMissing(['corporateAffiliates', 'plan.benefitPlans', 'coverage', 'agent', 'agency']);

        self::ensureDirectories();
        self::generateCorporateCertificate($record);

        $affiliates = $record->corporateAffiliates;

        if ($affiliates->count() <= 3) {
            self::generateTarjetasChunk($record, self::toTarjetaPayloadChunk($record, $affiliates));

            return [
                'queued' => false,
                'documents' => self::documentsForAffiliation($record),
            ];
        }

        $taskId = (string) Str::uuid();
        $chunks = self::toTarjetaPayloadChunk($record, $affiliates, 3);
        $jobs = [];

        foreach ($chunks as $chunk) {
            $jobs[] = new GenerateCorporateAffiliateTarjetasChunkJob($record->code, $chunk);
        }

        $batch = Bus::batch($jobs)
            ->name('corporate-documents-'.$record->code)
            ->then(function (Batch $batch) use ($record, $taskId): void {
                if ($batch->cancelled()) {
                    return;
                }

                $existingPayload = self::status($taskId);
                $record->refresh()->loadMissing('corporateAffiliates');
                self::cacheStatus($taskId, [
                    'status' => 'completed',
                    'message' => 'Documentos generados correctamente.',
                    'affiliation_code' => (string) $record->code,
                    'batch_id' => $batch->id,
                    'started_at' => $existingPayload['started_at'] ?? time(),
                    'total_jobs' => $batch->totalJobs,
                    'processed_jobs' => $batch->totalJobs,
                    'progress_percentage' => 100,
                    'eta_seconds' => 0,
                    'documents' => self::documentsForAffiliation($record),
                ]);
            })
            ->catch(function (Batch $batch, \Throwable $throwable) use ($taskId): void {
                $payload = self::status($taskId);
                self::cacheStatus($taskId, [
                    'status' => 'failed',
                    'message' => $throwable->getMessage(),
                    'batch_id' => $batch->id,
                    'started_at' => $payload['started_at'] ?? time(),
                    'total_jobs' => $batch->totalJobs,
                    'processed_jobs' => max(0, $batch->totalJobs - $batch->pendingJobs),
                    'progress_percentage' => (int) $batch->progress(),
                    'eta_seconds' => null,
                    'documents' => [],
                ]);
            })
            ->dispatch();

        self::cacheStatus($taskId, [
            'status' => 'processing',
            'message' => 'Generando tarjetas por lotes. Esto puede tardar unos segundos.',
            'affiliation_code' => (string) $record->code,
            'batch_id' => $batch->id,
            'started_at' => time(),
            'total_jobs' => count($jobs),
            'processed_jobs' => 0,
            'progress_percentage' => 0,
            'eta_seconds' => null,
            'documents' => [],
        ]);

        return [
            'queued' => true,
            'task_id' => $taskId,
            'progress_percentage' => 0,
            'eta_seconds' => null,
        ];
    }

    /**
     * @return array{
     *   status: string,
     *   message: string,
     *   batch_id?: string|null,
     *   affiliation_code?: string,
     *   started_at?: int,
     *   total_jobs?: int,
     *   processed_jobs?: int,
     *   progress_percentage?: int,
     *   eta_seconds?: int|null,
     *   documents: array<int, array{label: string, kind: string, filename: string, preview_url: string}>
     * }
     */
    public static function status(string $taskId): array
    {
        /** @var array{status: string, message: string, batch_id?: string|null, affiliation_code?: string, started_at?: int, total_jobs?: int, processed_jobs?: int, progress_percentage?: int, eta_seconds?: int|null, documents: array<int, array{label: string, kind: string, filename: string, preview_url: string}>}|null $payload */
        $payload = Cache::get(self::cacheKey($taskId));

        if ($payload === null) {
            return [
                'status' => 'failed',
                'message' => 'No se encontró el proceso de generación de documentos.',
                'progress_percentage' => 0,
                'eta_seconds' => null,
                'documents' => [],
            ];
        }

        $payload['started_at'] ??= time();

        if (($payload['status'] ?? '') === 'processing' && filled($payload['batch_id'] ?? null)) {
            $batch = Bus::findBatch((string) $payload['batch_id']);

            if ($batch !== null) {
                $processedJobs = max(0, $batch->totalJobs - $batch->pendingJobs);
                $progress = (int) $batch->progress();
                $payload['total_jobs'] = $batch->totalJobs;
                $payload['processed_jobs'] = $processedJobs;
                $payload['progress_percentage'] = $progress;
                $payload['message'] = "Procesando lotes de tarjetas: {$processedJobs}/{$batch->totalJobs}";
                $payload['eta_seconds'] = self::estimateEtaSeconds(
                    processedJobs: $processedJobs,
                    totalJobs: $batch->totalJobs,
                    startedAt: (int) $payload['started_at'],
                );

                if ($batch->finished()) {
                    if ($batch->failedJobs > 0) {
                        $payload['status'] = 'failed';
                        $payload['message'] = 'La generación finalizó con errores en uno o más lotes.';
                        $payload['eta_seconds'] = null;
                    } else {
                        $payload['status'] = 'completed';
                        $payload['progress_percentage'] = 100;
                        $payload['processed_jobs'] = $batch->totalJobs;
                        $payload['message'] = 'Documentos generados correctamente.';
                        $payload['eta_seconds'] = 0;

                        $affiliationCode = (string) ($payload['affiliation_code'] ?? '');
                        if ($affiliationCode !== '') {
                            $record = AffiliationCorporate::query()
                                ->where('code', $affiliationCode)
                                ->with('corporateAffiliates')
                                ->first();

                            if ($record !== null) {
                                $payload['documents'] = self::documentsForAffiliation($record);
                            }
                        }
                    }
                }

                self::cacheStatus($taskId, $payload);
            }
        }

        return $payload;
    }

    private static function estimateEtaSeconds(int $processedJobs, int $totalJobs, int $startedAt): ?int
    {
        if ($totalJobs <= 0) {
            return null;
        }

        $remainingJobs = max(0, $totalJobs - $processedJobs);
        if ($remainingJobs === 0) {
            return 0;
        }

        $elapsedSeconds = max(1, time() - $startedAt);
        if ($processedJobs <= 0) {
            return null;
        }

        $jobsPerSecond = $processedJobs / $elapsedSeconds;
        if ($jobsPerSecond <= 0) {
            return null;
        }

        return (int) ceil($remainingJobs / $jobsPerSecond);
    }

    /**
     * @return array<int, string>
     */
    public static function absolutePdfPathsForAffiliation(AffiliationCorporate $record): array
    {
        $record->loadMissing('corporateAffiliates');

        $paths = [
            public_path('storage/certificados-doc/CER-'.$record->code.'.pdf'),
        ];

        foreach ($record->corporateAffiliates as $affiliate) {
            $paths[] = public_path('storage/tarjeta-afiliacion/TAR-'.$record->code.'-'.$affiliate->id.'.pdf');
        }

        return array_values(array_filter($paths, fn (string $path): bool => is_file($path)));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function toTarjetaPayloadChunk(
        AffiliationCorporate $record,
        Collection $affiliates,
        int $chunkSize = 0,
    ): array {
        $hasta = self::vigenciaHasta($record->effective_date);
        $planDesc = (string) ($record->plan?->description ?? '');
        $cobertura = (string) ($record->coverage?->price ?? '');
        $frecuencia = (string) $record->payment_frequency;
        $desde = (string) ($record->effective_date ?? '');

        $payload = $affiliates->map(function ($affiliate) use ($record, $planDesc, $cobertura, $frecuencia, $desde, $hasta): array {
            return [
                'name' => trim((string) $affiliate->first_name.' '.(string) $affiliate->last_name),
                'ci' => (string) $affiliate->nro_identificacion,
                'code' => (string) $record->code,
                'plan' => $planDesc,
                'frecuencia' => $frecuencia,
                'cobertura' => $cobertura,
                'desde' => $desde,
                'hasta' => $hasta,
                'output_filename' => 'TAR-'.$record->code.'-'.$affiliate->id.'.pdf',
            ];
        })->values();

        if ($chunkSize <= 0) {
            return [$payload->all()];
        }

        return $payload->chunk($chunkSize)->map(fn (Collection $chunk): array => $chunk->values()->all())->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $chunk
     */
    public static function generateTarjetasChunk(AffiliationCorporate $record, array $chunk): void
    {
        self::ensureDirectories();

        foreach ($chunk as $data) {
            $ok = TarjetaAfiliacionController::generateTarjetaAfiliacion(
                $data,
                silent: true,
                ensureOutputDirectory: false,
                applyResourceLimits: false,
            );

            if ($ok !== true) {
                throw new RuntimeException(is_string($ok) ? $ok : 'Error al generar una tarjeta corporativa.');
            }
        }
    }

    private static function generateCorporateCertificate(AffiliationCorporate $record): void
    {
        $effectiveDate = (string) ($record->effective_date ?? '');

        $pagador = [
            'name' => (string) $record->name_corporate,
            'code' => (string) $record->code,
            'tarifa_anual' => (float) ($record->fee_anual ?? 0),
            'plan' => (string) ($record->plan?->description ?? 'Plan Estándar'),
            'plan_id' => $record->plan_id,
            'frecuencia_pago' => (string) ($record->payment_frequency ?? ''),
            'cobertura' => (float) ($record->coverage?->price ?? 0),
            'fecha_afiliacion' => (string) ($record->activated_at ?? ''),
            'tarifa_periodo' => (float) ($record->total_amount ?? 0),
            'fecha_vigencia' => $effectiveDate,
            'fecha_vigencia_final' => self::vigenciaHasta($effectiveDate),
            'agente_agencia' => (string) ($record->agent?->name ?? $record->agency?->name_corporative ?? 'TuDrEnCasa'),
        ];

        $beneficios = $record->plan?->benefitPlans?->pluck('description')->filter()->values()->all() ?? [];
        $affiliates = $record->corporateAffiliates->map(function ($affiliate): array {
            return [
                'full_name' => trim((string) $affiliate->first_name.' '.(string) $affiliate->last_name),
                'nro_identificacion' => (string) $affiliate->nro_identificacion,
                'birth_date' => (string) ($affiliate->birth_date ?? ''),
                'relationship' => (string) ($affiliate->position_company ?? 'COLABORADOR'),
            ];
        });

        $pdf = Pdf::loadView(
            'documents.certificate',
            AffiliationController::dataForCertificatePdfView($pagador, $beneficios, $affiliates),
        );

        $pdf->save(public_path('storage/certificados-doc/CER-'.$record->code.'.pdf'));
    }

    private static function ensureDirectories(): void
    {
        $dirs = [
            public_path('storage/certificados-doc/'),
            public_path('storage/tarjeta-afiliacion/'),
        ];

        foreach ($dirs as $dir) {
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    /**
     * @return array<int, array{label: string, kind: string, filename: string, preview_url: string}>
     */
    private static function documentsForAffiliation(AffiliationCorporate $record): array
    {
        $record->loadMissing('corporateAffiliates');
        $version = (string) time();
        $documents = [
            [
                'label' => 'Certificado de afiliación corporativa',
                'kind' => 'certificate',
                'filename' => 'CER-'.$record->code.'.pdf',
                'preview_url' => asset('storage/certificados-doc/CER-'.$record->code.'.pdf').'?t='.$version,
            ],
        ];

        foreach ($record->corporateAffiliates as $affiliate) {
            $fullName = trim((string) $affiliate->first_name.' '.(string) $affiliate->last_name);
            $filename = 'TAR-'.$record->code.'-'.$affiliate->id.'.pdf';
            $documents[] = [
                'label' => 'Tarjeta — '.($fullName !== '' ? $fullName : 'Afiliado corporativo'),
                'kind' => 'tarjeta',
                'filename' => $filename,
                'preview_url' => asset('storage/tarjeta-afiliacion/'.$filename).'?t='.$version,
            ];
        }

        return $documents;
    }

    private static function vigenciaHasta(?string $effectiveDate): string
    {
        if (blank($effectiveDate)) {
            return '';
        }

        try {
            return Carbon::createFromFormat('d/m/Y', (string) $effectiveDate)->addYear()->format('d/m/Y');
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * @param  array{status: string, message: string, batch_id?: string|null, affiliation_code?: string, started_at?: int, total_jobs?: int, processed_jobs?: int, progress_percentage?: int, eta_seconds?: int|null, documents: array<int, array{label: string, kind: string, filename: string, preview_url: string}>}  $payload
     */
    private static function cacheStatus(string $taskId, array $payload): void
    {
        Cache::put(self::cacheKey($taskId), $payload, now()->addMinutes(20));
    }

    private static function cacheKey(string $taskId): string
    {
        return 'business.corporate-documents.'.$taskId;
    }
}
