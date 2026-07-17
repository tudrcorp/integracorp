<?php

namespace App\Listeners;

use App\Support\Imports\ImportActivityLogger;
use Filament\Actions\Imports\Events\ImportChunkProcessed;
use Filament\Actions\Imports\Events\ImportCompleted;
use Filament\Actions\Imports\Events\ImportStarted;
use Filament\Actions\Imports\Jobs\ImportCsv;
use Illuminate\Queue\Events\JobFailed;

class LogFilamentImportActivity
{
    public function __construct(private ImportActivityLogger $logger) {}

    public function handleStarted(ImportStarted $event): void
    {
        $this->logger->logStarted(
            $event->getImport(),
            $event->getColumnMap(),
            $event->getOptions(),
        );
    }

    public function handleChunkProcessed(ImportChunkProcessed $event): void
    {
        $this->logger->logChunkProcessed(
            $event->getImport(),
            $event->getProcessedRows(),
            $event->getSuccessfulRows(),
        );
    }

    public function handleCompleted(ImportCompleted $event): void
    {
        $this->logger->logCompleted(
            $event->getImport(),
            $event->getOptions(),
        );
    }

    public function handleJobFailed(JobFailed $event): void
    {
        $payload = $event->job->payload();
        $displayName = $payload['displayName'] ?? null;

        if ($displayName !== ImportCsv::class && ! str_contains((string) $displayName, 'ImportCsv')) {
            return;
        }

        $this->logger->error('Job ImportCsv falló en cola', [
            'connection' => $event->connectionName,
            'queue' => $event->job->getQueue(),
            'job_name' => $displayName,
            'exception' => $event->exception::class,
            'message' => $event->exception->getMessage(),
            'job_uuid' => $payload['uuid'] ?? null,
        ]);
    }
}
