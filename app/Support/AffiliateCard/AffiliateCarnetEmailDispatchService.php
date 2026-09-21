<?php

declare(strict_types=1);

namespace App\Support\AffiliateCard;

use App\Jobs\SendAffiliateCarnetEmailJob;
use App\Models\Affiliate;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Services\AffiliationBusinessDocumentsService;
use App\Services\AffiliationCorporateBusinessDocumentsService;
use App\Support\Affiliations\AffiliationJobFailureLogger;
use App\Support\WhiteCompanies\WhiteCompanyDocumentBrand;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class AffiliateCarnetEmailDispatchService
{
    /**
     * @return array{ok: bool, message: string, queued: int, skipped: int}
     */
    public static function queueForIndividual(Affiliation $record, int $userId): array
    {
        $collected = self::collectForIndividual($record);

        return self::dispatchBatch(
            $collected['sendable'],
            $collected['skipped'],
            $userId,
            (string) $record->code,
            'individual:'.$record->id,
        );
    }

    /**
     * @return array{ok: bool, message: string, queued: int, skipped: int}
     */
    public static function queueForCorporate(AffiliationCorporate $record, int $userId): array
    {
        $collected = self::collectForCorporate($record);

        return self::dispatchBatch(
            $collected['sendable'],
            $collected['skipped'],
            $userId,
            (string) $record->code,
            'corporate:'.$record->id,
        );
    }

    /**
     * @return array{sendable: list<array{email: string, name: string, carnet_path: string, condicionado_path: string}>, skipped: int}
     */
    public static function collectForIndividual(Affiliation $record): array
    {
        $record->loadMissing('affiliates');

        $condicionadoPath = WhiteCompanyDocumentBrand::forAffiliation($record)
            ->condicionadoAbsolutePath($record->plan_id !== null ? (int) $record->plan_id : null);

        $directory = public_path('storage/tarjeta-afiliacion/');
        $seenEmails = [];
        $sendable = [];
        $skipped = 0;

        foreach ($record->affiliates as $affiliate) {
            $email = self::resolveIndividualEmail($record, $affiliate);
            $name = trim((string) $affiliate->full_name);
            $carnetPath = $directory.'TAR-'.$record->code.'-'.$affiliate->id.'.pdf';

            $result = self::qualifyRecipient($email, $name, $carnetPath, $condicionadoPath, $seenEmails);
            if ($result === null) {
                $skipped++;

                continue;
            }

            $sendable[] = $result;
        }

        if (AffiliationBusinessDocumentsService::shouldGenerateLegacyTitularTarjeta($record)) {
            $email = self::normalizedEmail((string) ($record->email_ti ?? ''));
            $name = trim((string) ($record->full_name_ti ?? 'Titular'));
            $carnetPath = $directory.'TAR-'.$record->code.'.pdf';

            $result = self::qualifyRecipient($email, $name, $carnetPath, $condicionadoPath, $seenEmails);
            if ($result === null) {
                $skipped++;
            } else {
                $sendable[] = $result;
            }
        }

        return ['sendable' => $sendable, 'skipped' => $skipped];
    }

    /**
     * @return array{sendable: list<array{email: string, name: string, carnet_path: string, condicionado_path: string}>, skipped: int}
     */
    public static function collectForCorporate(AffiliationCorporate $record): array
    {
        $record->loadMissing('corporateAffiliates');

        $brand = WhiteCompanyDocumentBrand::forCorporate($record);
        $fallbackCondicionado = $brand->condicionadoAbsolutePath(
            AffiliationCorporateBusinessDocumentsService::resolvePlanId($record),
        );
        $directory = public_path('storage/tarjeta-afiliacion/');
        $seenEmails = [];
        $sendable = [];
        $skipped = 0;

        foreach ($record->corporateAffiliates as $affiliate) {
            $email = self::normalizedEmail((string) ($affiliate->email ?? ''))
                ?? self::normalizedEmail((string) ($record->email ?? ''))
                ?? self::normalizedEmail((string) ($record->email_contact ?? ''));
            $name = trim((string) $affiliate->first_name.' '.(string) $affiliate->last_name);
            $carnetPath = $directory.'TAR-'.$record->code.'-'.$affiliate->id.'.pdf';
            $planId = $affiliate->plan_id !== null ? (int) $affiliate->plan_id : null;
            $condicionadoPath = $brand->condicionadoAbsolutePath($planId) ?? $fallbackCondicionado;

            $result = self::qualifyRecipient($email, $name !== '' ? $name : 'Afiliado', $carnetPath, $condicionadoPath, $seenEmails);
            if ($result === null) {
                $skipped++;

                continue;
            }

            $sendable[] = $result;
        }

        return ['sendable' => $sendable, 'skipped' => $skipped];
    }

    /**
     * @param  list<array{email: string, name: string, carnet_path: string, condicionado_path: string}>  $sendable
     * @return array{ok: bool, message: string, queued: int, skipped: int}
     */
    private static function dispatchBatch(
        array $sendable,
        int $skipped,
        int $userId,
        string $affiliationCode,
        string $lockSuffix,
    ): array {
        if ($sendable === []) {
            $message = $skipped > 0
                ? 'No se pudo encolar ningún correo. Falta el correo del afiliado (o del titular), el carnet individual o el condicionado del plan. Regenere los documentos e intente de nuevo.'
                : 'No hay afiliados para enviar.';

            return [
                'ok' => false,
                'message' => $message,
                'queued' => 0,
                'skipped' => $skipped,
            ];
        }

        $lockKey = 'affiliate-carnet-emails.'.$lockSuffix;

        if (! Cache::add($lockKey, 1, now()->addMinutes(30))) {
            return [
                'ok' => false,
                'message' => 'Ya hay un envío de carnets en curso para esta afiliación. Espere el aviso en la campanita.',
                'queued' => 0,
                'skipped' => $skipped,
            ];
        }

        $queued = count($sendable);
        $jobs = array_map(
            fn (array $recipient): SendAffiliateCarnetEmailJob => new SendAffiliateCarnetEmailJob(
                email: $recipient['email'],
                recipientName: $recipient['name'],
                affiliationCode: $affiliationCode,
                carnetPath: $recipient['carnet_path'],
                condicionadoPath: $recipient['condicionado_path'],
            ),
            $sendable,
        );

        try {
            Bus::batch($jobs)
                ->name('affiliate-carnet-emails-'.$affiliationCode)
                ->allowFailures()
                ->onQueue('default')
                ->finally(function (Batch $batch) use ($userId, $affiliationCode, $queued, $skipped, $lockKey): void {
                    $failed = (int) $batch->failedJobs;
                    $sent = max(0, $queued - $failed);

                    if ($failed > 0) {
                        AffiliationJobFailureLogger::batch(SendAffiliateCarnetEmailJob::class, null, [
                            'action' => 'send-carnet-emails',
                            'affiliation_code' => $affiliationCode,
                            'batch_id' => $batch->id,
                            'queued' => $queued,
                            'failed_jobs' => $failed,
                            'skipped' => $skipped,
                            'cause' => 'El lote de carnets de '.$affiliationCode.' terminó con '.$failed.' envío(s) fallido(s) de '.$queued.'. Revise los logs individuales de SendAffiliateCarnetEmailJob.',
                        ]);
                    }

                    AffiliateCarnetEmailNotifier::notifyCompletion(
                        $userId,
                        $affiliationCode,
                        $sent,
                        $failed,
                        $skipped,
                    );

                    Cache::forget($lockKey);
                })
                ->dispatch();
        } catch (Throwable $exception) {
            Cache::forget($lockKey);

            AffiliationJobFailureLogger::dispatchFailed(SendAffiliateCarnetEmailJob::class, $exception, [
                'action' => 'send-carnet-emails',
                'affiliation_code' => $affiliationCode,
                'queued_attempted' => $queued,
                'skipped' => $skipped,
                'lock_key' => $lockKey,
            ]);

            AffiliateCarnetEmailNotifier::notifyImmediateFailure(
                $userId,
                'Error al enviar carnets',
                'No se pudo encolar el envío de carnets de la afiliación '.$affiliationCode.'. Intente de nuevo.',
            );

            return [
                'ok' => false,
                'message' => 'No se pudo encolar el envío. Intente de nuevo.',
                'queued' => 0,
                'skipped' => $skipped,
            ];
        }

        AffiliateCarnetEmailNotifier::notifyQueued($userId, $affiliationCode, $queued, $skipped);

        return [
            'ok' => true,
            'message' => AffiliateCarnetEmailNotifier::queuedBody($affiliationCode, $queued, $skipped),
            'queued' => $queued,
            'skipped' => $skipped,
        ];
    }

    /**
     * @param  array<string, true>  $seenEmails
     * @return array{email: string, name: string, carnet_path: string, condicionado_path: string}|null
     */
    private static function qualifyRecipient(
        ?string $email,
        string $name,
        string $carnetPath,
        ?string $condicionadoPath,
        array &$seenEmails,
    ): ?array {
        if ($email === null) {
            return null;
        }

        if (isset($seenEmails[$email])) {
            return null;
        }

        if (! is_file($carnetPath) || $condicionadoPath === null || ! is_file($condicionadoPath)) {
            return null;
        }

        $seenEmails[$email] = true;

        return [
            'email' => $email,
            'name' => $name !== '' ? $name : 'Afiliado',
            'carnet_path' => $carnetPath,
            'condicionado_path' => $condicionadoPath,
        ];
    }

    private static function resolveIndividualEmail(Affiliation $record, Affiliate $affiliate): ?string
    {
        return self::normalizedEmail((string) ($affiliate->email ?? ''))
            ?? self::normalizedEmail((string) ($record->email_ti ?? ''))
            ?? self::normalizedEmail((string) ($record->email_payer ?? ''));
    }

    private static function normalizedEmail(string $email): ?string
    {
        $email = strtolower(trim($email));

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return $email;
    }
}
