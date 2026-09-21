<?php

declare(strict_types=1);

namespace App\Support\Affiliations\Concerns;

use App\Support\Affiliations\AffiliationJobFailureLogger;
use Throwable;

trait LogsAffiliationJobFailures
{
    /**
     * @param  callable(): void  $callback
     * @param  array<string, mixed>  $context
     */
    protected function runWithAffiliationFailureLogging(callable $callback, array $context = []): void
    {
        try {
            $callback();
        } catch (Throwable $exception) {
            AffiliationJobFailureLogger::attempt(
                static::class,
                $exception,
                $this->withAffiliationQueueAttemptContext($context),
            );

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function logAffiliationJobFailure(?Throwable $exception, array $context = []): void
    {
        AffiliationJobFailureLogger::definitive(
            static::class,
            $exception,
            $this->withAffiliationQueueAttemptContext($context),
        );
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function withAffiliationQueueAttemptContext(array $context): array
    {
        if (method_exists($this, 'attempts')) {
            try {
                $context['attempts'] = $this->attempts();
            } catch (Throwable) {
            }
        }

        if (property_exists($this, 'tries')) {
            $context['tries'] = $this->tries;
        }

        if (method_exists($this, 'batchId')) {
            try {
                $batchId = $this->batchId();
                if (is_string($batchId) && $batchId !== '') {
                    $context['batch_id'] = $batchId;
                }
            } catch (Throwable) {
            }
        }

        return $context;
    }
}
