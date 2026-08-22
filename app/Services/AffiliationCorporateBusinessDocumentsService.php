<?php

namespace App\Services;

use App\Http\Controllers\AffiliationController;
use App\Http\Controllers\TarjetaAfiliacionController;
use App\Jobs\GenerateCorporateAffiliateTarjetasChunkJob;
use App\Jobs\GenerateCorporateCertificateJob;
use App\Jobs\GenerateCorporateCombinedCardsJob;
use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use App\Models\User;
use App\Support\AffiliateCard\AffiliateCardPageLayout;
use App\Support\DomPdfBatchRenderOptions;
use App\Support\Viveplus\ViveplusDocumentWebhookDispatcher;
use App\Support\WhiteCompanies\WhiteCompanyDocumentBrand;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Bus\Batch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class AffiliationCorporateBusinessDocumentsService
{
    /**
     * Máximo de afiliados que se generan dentro del request HTTP. Por encima de
     * este número el trabajo se despacha a la cola de documentos.
     */
    public const INLINE_AFFILIATE_THRESHOLD = 10;

    /**
     * Ventana de vida del estado en cache. Una corporativa de miles de afiliados
     * puede tardar bastante más que los 20 minutos que se usaban antes.
     */
    private const TASK_TTL_MINUTES = 180;

    private static function documentsQueue(): string
    {
        return (string) config('affiliate-card.documents_queue', 'documents');
    }

    /**
     * Sin worker (entorno de pruebas o `QUEUE_CONNECTION=sync`) no tiene sentido
     * encolar: el batch nunca se procesaría y el usuario esperaría para siempre.
     */
    private static function shouldRunInline(int $affiliateCount): bool
    {
        return $affiliateCount <= self::INLINE_AFFILIATE_THRESHOLD
            || config('queue.default') === 'sync';
    }

    public static function combinedCardsFilename(AffiliationCorporate $record): string
    {
        return 'TAR-'.$record->code.'-carnets.pdf';
    }

    public static function combinedCardsAbsolutePath(AffiliationCorporate $record): string
    {
        return public_path('storage/tarjeta-afiliacion/'.self::combinedCardsFilename($record));
    }

    /**
     * @return array{
     *   queued: bool,
     *   task_id?: string,
     *   reused?: bool,
     *   affiliates_count?: int,
     *   progress_percentage?: int,
     *   eta_seconds?: int|null,
     *   documents?: array<int, array{label: string, kind: string, filename: string, preview_url: string}>
     * }
     */
    public static function regenerateCertificateAndTarjetas(AffiliationCorporate $record, ?int $userId): array
    {
        $record->loadMissing(['corporateAffiliates.plan', 'corporateAffiliates.coverage', 'plan.benefitPlans', 'coverage', 'agent', 'agency']);

        $affiliationCode = (string) $record->code;

        $activeTask = self::activeTaskFor($affiliationCode);

        if ($activeTask !== null) {
            return [
                'queued' => true,
                'task_id' => $activeTask['task_id'],
                'reused' => true,
                'affiliates_count' => $record->corporateAffiliates->count(),
                'progress_percentage' => (int) ($activeTask['payload']['progress_percentage'] ?? 0),
                'eta_seconds' => $activeTask['payload']['eta_seconds'] ?? null,
            ];
        }

        self::purgeExistingGeneratedDocuments($record);
        self::ensureDirectories();

        $affiliates = $record->corporateAffiliates;
        $affiliateCount = $affiliates->count();

        if (self::shouldRunInline($affiliateCount)) {
            $memoryMb = min(1024, 384 + (48 * max(1, $affiliateCount + 1)));
            ini_set('memory_limit', $memoryMb.'M');
            set_time_limit(min(900, 120 + (45 * max(1, $affiliateCount + 1))));

            self::generateCorporateCertificate($record);
            self::generateCombinedCards($record, $affiliates);
            self::generateTarjetasChunk(
                self::normalizeTarjetaPayloads(self::toTarjetaPayloadChunk($record, $affiliates)),
            );

            self::queueViveplusDocuments($record, $userId);

            return [
                'queued' => false,
                'affiliates_count' => $affiliateCount,
                'documents' => self::previewDocumentsForAffiliation($record),
            ];
        }

        $taskId = (string) Str::uuid();
        $chunks = self::toTarjetaPayloadChunk($record, $affiliates, self::recommendedChunkSize($affiliateCount));

        /**
         * El certificado y el PDF único de carnets van primero para que la vista
         * previa esté lista mucho antes de que terminen los carnets uno a uno.
         */
        $jobs = [
            new GenerateCorporateCertificateJob($affiliationCode),
            new GenerateCorporateCombinedCardsJob($affiliationCode),
        ];

        foreach ($chunks as $chunk) {
            $jobs[] = new GenerateCorporateAffiliateTarjetasChunkJob(
                self::normalizeTarjetaPayloads($chunk),
            );
        }

        $activeTaskCacheKey = self::activeTaskCacheKey($affiliationCode);

        /**
         * El estado se cachea ANTES de despachar: con cola real el worker puede
         * terminar antes de que vuelva el `dispatch()` y pisaríamos el resultado.
         */
        self::cacheStatus($taskId, [
            'status' => 'processing',
            'message' => 'Generando certificado y carnets. Puede cerrar esta ventana: el proceso continúa.',
            'affiliation_code' => $affiliationCode,
            'batch_id' => null,
            'started_at' => time(),
            'total_jobs' => count($jobs),
            'processed_jobs' => 0,
            'progress_percentage' => 0,
            'eta_seconds' => null,
            'affiliates_count' => $affiliateCount,
            'documents' => [],
        ]);
        Cache::put($activeTaskCacheKey, $taskId, now()->addMinutes(self::TASK_TTL_MINUTES));

        try {
            $batch = Bus::batch($jobs)
                ->name('corporate-documents-'.$affiliationCode)
                ->onQueue(self::documentsQueue())
                ->then(function (Batch $batch) use ($taskId, $userId, $activeTaskCacheKey, $affiliationCode): void {
                    if ($batch->cancelled()) {
                        Cache::forget($activeTaskCacheKey);

                        return;
                    }

                    $record = self::findByCode($affiliationCode);

                    self::mergeStatus($taskId, [
                        'status' => 'completed',
                        'message' => 'Documentos generados correctamente.',
                        'batch_id' => $batch->id,
                        'total_jobs' => $batch->totalJobs,
                        'processed_jobs' => $batch->totalJobs,
                        'progress_percentage' => 100,
                        'eta_seconds' => 0,
                        'documents' => $record !== null ? self::previewDocumentsForAffiliation($record) : [],
                    ]);
                    Cache::forget($activeTaskCacheKey);

                    if ($record !== null) {
                        self::queueViveplusDocuments($record, $userId);
                    }

                    self::notifyUser(
                        $userId,
                        'Documentos corporativos listos',
                        'Ya están disponibles el certificado y los carnets de '.$affiliationCode.'.',
                        'success',
                    );
                })
                ->catch(function (Batch $batch, Throwable $throwable) use ($taskId, $activeTaskCacheKey, $affiliationCode, $userId): void {
                    self::mergeStatus($taskId, [
                        'status' => 'failed',
                        'message' => $throwable->getMessage(),
                        'batch_id' => $batch->id,
                        'total_jobs' => $batch->totalJobs,
                        'processed_jobs' => max(0, $batch->totalJobs - $batch->pendingJobs),
                        'progress_percentage' => (int) $batch->progress(),
                        'eta_seconds' => null,
                    ]);
                    Cache::forget($activeTaskCacheKey);

                    self::notifyUser(
                        $userId,
                        'Falló la generación de documentos',
                        'No se pudieron generar todos los documentos de '.$affiliationCode.'. Intente regenerarlos nuevamente.',
                        'danger',
                    );
                })
                ->dispatch();
        } catch (Throwable $exception) {
            Cache::forget($activeTaskCacheKey);
            Cache::forget(self::cacheKey($taskId));

            throw $exception;
        }

        self::mergeStatus($taskId, ['batch_id' => $batch->id]);

        return [
            'queued' => true,
            'task_id' => $taskId,
            'affiliates_count' => $affiliateCount,
            'progress_percentage' => 0,
            'eta_seconds' => null,
        ];
    }

    /**
     * Tarea de generación viva para esa afiliación, si la hay. Evita que dos
     * clics disparen dos veces la generación completa sobre los mismos archivos.
     *
     * @return array{task_id: string, payload: array<string, mixed>}|null
     */
    private static function activeTaskFor(string $affiliationCode): ?array
    {
        $taskId = Cache::get(self::activeTaskCacheKey($affiliationCode));

        if (! is_string($taskId) || $taskId === '') {
            return null;
        }

        $payload = self::status($taskId);

        if (($payload['status'] ?? '') !== 'processing') {
            Cache::forget(self::activeTaskCacheKey($affiliationCode));

            return null;
        }

        return ['task_id' => $taskId, 'payload' => $payload];
    }

    /**
     * Aviso en la campana del panel: el usuario puede cerrar el modal y enterarse
     * igual de que terminó (o falló) la generación.
     */
    private static function notifyUser(?int $userId, string $title, string $body, string $status): void
    {
        if ($userId === null) {
            return;
        }

        try {
            $user = User::query()->find($userId);

            if ($user === null) {
                return;
            }

            Notification::make()
                ->title($title)
                ->body($body)
                ->{$status}()
                ->sendToDatabase($user);
        } catch (Throwable $exception) {
            Log::warning('AffiliationCorporateBusinessDocumentsService: no se pudo notificar al usuario', [
                'user_id' => $userId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private static function findByCode(string $affiliationCode): ?AffiliationCorporate
    {
        try {
            return AffiliationCorporate::query()
                ->where('code', $affiliationCode)
                ->with(['corporateAffiliates', 'plan'])
                ->first();
        } catch (Throwable $exception) {
            Log::warning('AffiliationCorporateBusinessDocumentsService: no se pudo recuperar la afiliación corporativa', [
                'affiliation_code' => $affiliationCode,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    public static function recommendedChunkSize(int $affiliatesCount): int
    {
        if ($affiliatesCount <= 20) {
            return 5;
        }

        if ($affiliatesCount <= 80) {
            return 10;
        }

        if ($affiliatesCount <= 250) {
            return 25;
        }

        if ($affiliatesCount <= 1000) {
            return 50;
        }

        return 100;
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
        $affiliationCode = (string) ($payload['affiliation_code'] ?? '');

        if (($payload['status'] ?? '') === 'processing' && filled($payload['batch_id'] ?? null)) {
            $batch = Bus::findBatch((string) $payload['batch_id']);

            if ($batch !== null) {
                $processedJobs = max(0, $batch->totalJobs - $batch->pendingJobs);
                $progress = (int) $batch->progress();
                $payload['total_jobs'] = $batch->totalJobs;
                $payload['processed_jobs'] = $processedJobs;
                $payload['progress_percentage'] = $progress;
                $payload['message'] = "Generando carnets: lote {$processedJobs} de {$batch->totalJobs}";
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
                    }
                }
            }
        }

        /**
         * La vista previa no espera al lote completo: en cuanto el certificado y
         * el PDF de carnets están en disco, el usuario ya puede verlos mientras
         * los carnets individuales que exige ViVEplus se siguen generando.
         */
        if ($affiliationCode !== '' && in_array($payload['status'] ?? '', ['processing', 'completed'], true)) {
            $record = self::findByCode($affiliationCode);

            if ($record !== null) {
                $documents = self::previewDocumentsForAffiliation($record);

                if ($documents !== []) {
                    $payload['documents'] = $documents;
                }

                $payload['affiliates_count'] = $record->corporateAffiliates->count();
            }
        }

        $payload['preview_ready'] = ($payload['documents'] ?? []) !== [];

        self::cacheStatus($taskId, $payload);

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

    public static function resolveCertificateAbsolutePath(AffiliationCorporate $record): ?string
    {
        $path = public_path('storage/certificados-doc/CER-'.$record->code.'.pdf');

        return is_file($path) ? $path : null;
    }

    /**
     * @return array<int, string>
     */
    public static function tarjetaCandidateFilenames(AffiliationCorporate $record): array
    {
        if (! $record->relationLoaded('corporateAffiliates')) {
            $record->loadMissing('corporateAffiliates');
        }

        return $record->corporateAffiliates
            ->map(fn ($affiliate): string => 'TAR-'.$record->code.'-'.$affiliate->id.'.pdf')
            ->values()
            ->all();
    }

    public static function resolvePrimaryTarjetaAbsolutePath(AffiliationCorporate $record): ?string
    {
        $directory = public_path('storage/tarjeta-afiliacion/');

        foreach (self::tarjetaCandidateFilenames($record) as $filename) {
            $path = $directory.$filename;

            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @return array<int, array{path: string, identification: string}>
     */
    public static function resolveAffiliateCarnetDocuments(AffiliationCorporate $record): array
    {
        $record->loadMissing('corporateAffiliates');

        $directory = public_path('storage/tarjeta-afiliacion/');
        $documents = [];
        $seen = [];

        foreach ($record->corporateAffiliates as $affiliate) {
            $identification = (string) $affiliate->nro_identificacion;
            if (trim($identification) === '') {
                continue;
            }

            $path = $directory.'TAR-'.$record->code.'-'.$affiliate->id.'.pdf';
            if (! is_file($path) || isset($seen[$identification])) {
                continue;
            }

            $seen[$identification] = true;
            $documents[] = [
                'path' => $path,
                'identification' => $identification,
            ];
        }

        return $documents;
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

        $combinedPath = self::combinedCardsAbsolutePath($record);

        /**
         * Con el PDF combinado basta un adjunto: una corporativa grande generaría
         * miles de adjuntos y ningún servidor de correo lo aceptaría.
         */
        if (is_file($combinedPath)) {
            $paths[] = $combinedPath;
        } else {
            foreach ($record->corporateAffiliates as $affiliate) {
                $paths[] = public_path('storage/tarjeta-afiliacion/TAR-'.$record->code.'-'.$affiliate->id.'.pdf');
            }
        }

        $condicionado = self::condicionadoAbsolutePathForAffiliation($record);

        if ($condicionado !== null) {
            $paths[] = $condicionado;
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
        $desde = (string) ($record->effective_date ?? '');
        $brand = WhiteCompanyDocumentBrand::forCorporate($record);
        $alliedTemplatePath = $brand->carnetCompiledPdfAbsolutePath;

        $payload = $affiliates->map(function ($affiliate) use ($record, $desde, $hasta, $brand, $alliedTemplatePath): array {
            $planId = $affiliate->plan_id !== null ? (int) $affiliate->plan_id : null;
            $planDescription = $brand->planDisplayName($planId, self::affiliatePlanDescription($affiliate));

            $data = [
                'name' => trim((string) $affiliate->first_name.' '.(string) $affiliate->last_name),
                'ci' => (string) $affiliate->nro_identificacion,
                'code' => (string) $record->code,
                'plan_id' => $planId,
                'plan' => $planDescription,
                'plan_tarjeta_etiqueta' => $brand->planShortLabel($planId, $planDescription),
                'frecuencia' => (string) ($affiliate->payment_frequency ?? $record->payment_frequency ?? ''),
                'cobertura' => self::affiliateCoveragePrice($affiliate, $record),
                'desde' => $desde,
                'hasta' => $hasta,
                'output_filename' => 'TAR-'.$record->code.'-'.$affiliate->id.'.pdf',
                'card_layout' => AffiliateCardPageLayout::TEMPLATE_INDIVIDUAL_AFFILIATION,
                'template_key' => AffiliateCardPageLayout::TEMPLATE_INDIVIDUAL_AFFILIATION,
            ];

            if (is_string($alliedTemplatePath) && $alliedTemplatePath !== '') {
                $data['template_path'] = $alliedTemplatePath;
                $data['template_key'] = AffiliateCardPageLayout::TEMPLATE_INDIVIDUAL_AFFILIATION_ALLIED;
            }

            return $data;
        })->values();

        if ($chunkSize <= 0) {
            return [$payload->all()];
        }

        return $payload->chunk($chunkSize)->map(fn (Collection $chunk): array => $chunk->values()->all())->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $chunk
     */
    /**
     * @param  array<int, array<string, mixed>|array<int, array<string, mixed>>>  $chunkOrChunks
     * @return array<int, array<string, mixed>>
     */
    public static function normalizeTarjetaPayloads(array $chunkOrChunks): array
    {
        if ($chunkOrChunks === []) {
            return [];
        }

        $first = $chunkOrChunks[0] ?? null;

        if (is_array($first) && array_key_exists('code', $first)) {
            return $chunkOrChunks;
        }

        $normalized = [];

        foreach ($chunkOrChunks as $nested) {
            if (! is_array($nested)) {
                continue;
            }

            foreach ($nested as $payload) {
                if (is_array($payload) && array_key_exists('code', $payload)) {
                    $normalized[] = $payload;
                }
            }
        }

        return $normalized;
    }

    /**
     * @param  array<int, array<string, mixed>|array<int, array<string, mixed>>>  $chunk
     */
    public static function generateTarjetasChunk(array $chunk): void
    {
        self::ensureDirectories();

        foreach (self::normalizeTarjetaPayloads($chunk) as $data) {
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

    public static function generateCorporateCertificate(AffiliationCorporate $record): void
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
        DomPdfBatchRenderOptions::apply($pdf);

        $certificatePath = public_path('storage/certificados-doc/CER-'.$record->code.'.pdf');
        $pdf->save($certificatePath);

        if (! is_file($certificatePath)) {
            throw new RuntimeException('No se pudo guardar el certificado corporativo en disco.');
        }
    }

    private static function queueViveplusDocuments(AffiliationCorporate $record, ?int $userId): void
    {
        try {
            ViveplusDocumentWebhookDispatcher::dispatchForCorporate($record, $userId);
        } catch (Throwable $exception) {
            Log::error('AffiliationCorporateBusinessDocumentsService: no se pudo encolar el webhook de ViVEplus', [
                'affiliation_code' => $record->code,
                'error' => $exception->getMessage(),
            ]);
        }
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
     * PDF único con todos los carnets corporativos (columna de 4, uno debajo
     * del otro, mismo layout que afiliaciones individuales). Es lo que se
     * muestra y se envía por correo; los carnets uno a uno se siguen escribiendo
     * aparte porque ViVEplus los exige.
     *
     * Devuelve el nombre del archivo generado, o null si no hay plantilla de
     * estampado disponible (en ese caso la regeneración continúa sin combinado).
     */
    public static function generateCombinedCards(AffiliationCorporate $record, ?Collection $affiliates = null): ?string
    {
        $affiliates ??= $record->loadMissing('corporateAffiliates')->corporateAffiliates;

        if ($affiliates->isEmpty()) {
            return null;
        }

        self::ensureDirectories();

        $cards = self::normalizeTarjetaPayloads(self::toTarjetaPayloadChunk($record, $affiliates));

        if ($cards === []) {
            return null;
        }

        $filename = self::combinedCardsFilename($record);

        $result = TarjetaAfiliacionController::generateTarjetaAfiliacionBatch(
            $cards,
            $filename,
            silent: true,
            ensureOutputDirectory: false,
        );

        if ($result !== true) {
            Log::warning('AffiliationCorporateBusinessDocumentsService: no se pudo generar el PDF combinado de carnets', [
                'affiliation_code' => $record->code,
                'error' => is_string($result) ? $result : 'desconocido',
            ]);

            return null;
        }

        return $filename;
    }

    /**
     * Documentos de cabecera del modal: certificado, PDF combinado de carnets y
     * condicionado. Nunca incluye las tarjetas una por una — una corporativa de
     * miles de afiliados haría inmanejable el JSON y el navegador.
     *
     * @return array<int, array{label: string, kind: string, filename: string, preview_url: string}>
     */
    public static function previewDocumentsForAffiliation(AffiliationCorporate $record): array
    {
        $record->loadMissing('corporateAffiliates');
        $version = (string) time();
        $documents = [];

        $certificateFilename = 'CER-'.$record->code.'.pdf';

        if (is_file(public_path('storage/certificados-doc/'.$certificateFilename))) {
            $documents[] = [
                'label' => 'Certificado de afiliación corporativa',
                'kind' => 'certificate',
                'filename' => $certificateFilename,
                'preview_url' => asset('storage/certificados-doc/'.$certificateFilename).'?t='.$version,
            ];
        }

        if (is_file(self::combinedCardsAbsolutePath($record))) {
            $affiliatesCount = $record->corporateAffiliates->count();

            $documents[] = [
                'label' => 'Carnets de afiliación ('.$affiliatesCount.' '.($affiliatesCount === 1 ? 'afiliado' : 'afiliados').')',
                'kind' => 'tarjeta',
                'filename' => self::combinedCardsFilename($record),
                'preview_url' => asset('storage/tarjeta-afiliacion/'.self::combinedCardsFilename($record)).'?t='.$version,
            ];
        }

        $condicionadoPath = self::condicionadoAbsolutePathForAffiliation($record);

        if ($condicionadoPath !== null) {
            $documents[] = [
                'label' => 'Condiciones del plan',
                'kind' => 'condicionado',
                'filename' => basename($condicionadoPath),
                'preview_url' => asset('storage/condicionados/'.basename($condicionadoPath)).'?t='.$version,
            ];
        }

        return $documents;
    }

    public static function condicionadoAbsolutePathForAffiliation(AffiliationCorporate $record): ?string
    {
        return WhiteCompanyDocumentBrand::forCorporate($record)
            ->condicionadoAbsolutePath(self::resolvePlanId($record));
    }

    /**
     * `affiliation_corporates` no tiene `plan_id`: el plan vive en cada afiliado.
     * Se usa el plan predominante de la población para elegir el condicionado.
     */
    public static function resolvePlanId(AffiliationCorporate $record): ?int
    {
        if ($record->plan_id !== null) {
            return (int) $record->plan_id;
        }

        $record->loadMissing('corporateAffiliates');

        $planId = $record->corporateAffiliates
            ->pluck('plan_id')
            ->filter(fn ($value): bool => filled($value))
            ->countBy()
            ->sortDesc()
            ->keys()
            ->first();

        return $planId !== null ? (int) $planId : null;
    }

    /**
     * Carnets individuales para el buscador del modal, paginados en servidor.
     *
     * @return array{
     *   documents: array<int, array{label: string, kind: string, filename: string, preview_url: string}>,
     *   total: int,
     *   page: int,
     *   per_page: int,
     *   last_page: int
     * }
     */
    public static function paginatedTarjetaDocuments(
        AffiliationCorporate $record,
        string $search = '',
        int $page = 1,
        int $perPage = 20,
    ): array {
        $perPage = max(1, min(50, $perPage));
        $page = max(1, $page);
        $search = trim($search);

        $query = AffiliateCorporate::query()
            ->where('affiliation_corporate_id', $record->getKey())
            ->orderBy('id');

        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(function ($builder) use ($term): void {
                $builder->where('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhere('nro_identificacion', 'like', $term);
            });
        }

        $total = (clone $query)->count();
        $version = (string) time();

        $documents = $query
            ->forPage($page, $perPage)
            ->get(['id', 'first_name', 'last_name', 'nro_identificacion'])
            ->map(function (AffiliateCorporate $affiliate) use ($record, $version): ?array {
                $filename = 'TAR-'.$record->code.'-'.$affiliate->id.'.pdf';

                if (! is_file(public_path('storage/tarjeta-afiliacion/'.$filename))) {
                    return null;
                }

                $fullName = trim((string) $affiliate->first_name.' '.(string) $affiliate->last_name);

                return [
                    'label' => 'Carnet — '.($fullName !== '' ? $fullName : 'Afiliado corporativo'),
                    'kind' => 'tarjeta',
                    'filename' => $filename,
                    'identification' => (string) $affiliate->nro_identificacion,
                    'preview_url' => asset('storage/tarjeta-afiliacion/'.$filename).'?t='.$version,
                ];
            })
            ->filter()
            ->values()
            ->all();

        return [
            'documents' => $documents,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => (int) max(1, ceil($total / $perPage)),
        ];
    }

    private static function purgeExistingGeneratedDocuments(AffiliationCorporate $record): void
    {
        $certificatePath = public_path('storage/certificados-doc/CER-'.$record->code.'.pdf');

        if (is_file($certificatePath)) {
            unlink($certificatePath);
        }

        $tarjetaDirectory = public_path('storage/tarjeta-afiliacion/');
        $pattern = $tarjetaDirectory.'TAR-'.$record->code.'*.pdf';

        foreach (glob($pattern) ?: [] as $tarjetaPath) {
            if (is_file($tarjetaPath)) {
                unlink($tarjetaPath);
            }
        }
    }

    private static function affiliatePlanDescription(AffiliateCorporate $affiliate): string
    {
        if ($affiliate->relationLoaded('plan') && $affiliate->plan !== null) {
            return (string) ($affiliate->plan->description ?? '');
        }

        return '';
    }

    private static function affiliateCoveragePrice(AffiliateCorporate $affiliate, AffiliationCorporate $record): string
    {
        if ($affiliate->relationLoaded('coverage') && $affiliate->coverage !== null) {
            return (string) ($affiliate->coverage->price ?? '');
        }

        if ($record->relationLoaded('coverage') && $record->coverage !== null) {
            return (string) ($record->coverage->price ?? '');
        }

        return '';
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
     * @param  array<string, mixed>  $payload
     */
    private static function cacheStatus(string $taskId, array $payload): void
    {
        Cache::put(self::cacheKey($taskId), $payload, now()->addMinutes(self::TASK_TTL_MINUTES));
    }

    /**
     * Actualiza solo las claves indicadas sobre el estado ya cacheado.
     *
     * Se usa desde los callbacks del batch: escribir el payload completo pisaría
     * lo que haya escrito el polling o el propio worker en paralelo.
     *
     * @param  array<string, mixed>  $patch
     */
    private static function mergeStatus(string $taskId, array $patch): void
    {
        $key = self::cacheKey($taskId);
        $lock = Cache::lock($key.'.lock', 5);

        try {
            $lock->block(3);

            $payload = Cache::get($key);

            if (! is_array($payload)) {
                return;
            }

            Cache::put($key, array_replace($payload, $patch), now()->addMinutes(self::TASK_TTL_MINUTES));
        } catch (Throwable $exception) {
            Log::warning('AffiliationCorporateBusinessDocumentsService: no se pudo actualizar el estado de la tarea', [
                'task_id' => $taskId,
                'error' => $exception->getMessage(),
            ]);
        } finally {
            optional($lock)->release();
        }
    }

    private static function cacheKey(string $taskId): string
    {
        return 'business.corporate-documents.'.$taskId;
    }

    private static function activeTaskCacheKey(string $affiliationCode): string
    {
        return 'business.corporate-documents.active-task.'.$affiliationCode;
    }
}
