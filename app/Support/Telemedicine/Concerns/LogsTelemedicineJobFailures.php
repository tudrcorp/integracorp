<?php

declare(strict_types=1);

namespace App\Support\Telemedicine\Concerns;

use App\Support\Telemedicine\TelemedicineJobFailureLogger;
use Throwable;

trait LogsTelemedicineJobFailures
{
    /**
     * @param  callable(): void  $callback
     * @param  array<string, mixed>  $context
     */
    protected function runWithTelemedicineFailureLogging(callable $callback, array $context = []): void
    {
        try {
            $callback();
        } catch (Throwable $exception) {
            TelemedicineJobFailureLogger::attempt(
                static::class,
                $exception,
                $this->withTelemedicineQueueAttemptContext($context),
            );

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function logTelemedicineJobFailure(?Throwable $exception, array $context = []): void
    {
        TelemedicineJobFailureLogger::definitive(
            static::class,
            $exception,
            $this->withTelemedicineQueueAttemptContext($context),
        );
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function withTelemedicineQueueAttemptContext(array $context): array
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

        return $context;
    }
}
