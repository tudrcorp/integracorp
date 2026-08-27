<?php

declare(strict_types=1);

namespace App\Support\Affiliations;

use Illuminate\Support\Facades\Log;
use Throwable;

final class AffiliationJobFailureLogger
{
    public const ATTEMPT_MESSAGE = 'AFILIACIONES: job falló en un intento';

    public const DEFINITIVE_MESSAGE = 'AFILIACIONES: job falló de forma definitiva';

    public const BATCH_MESSAGE = 'AFILIACIONES: lote de jobs falló';

    public const DISPATCH_MESSAGE = 'AFILIACIONES: no se pudo encolar el trabajo';

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public static function payload(string $job, ?Throwable $exception, array $context = []): array
    {
        return array_merge([
            'job' => $job,
            'exception_class' => $exception === null ? null : $exception::class,
            'exception_message' => $exception?->getMessage(),
            'exception_code' => $exception?->getCode(),
            'exception_file' => $exception?->getFile(),
            'exception_line' => $exception?->getLine(),
            'exception_trace' => $exception?->getTraceAsString(),
            'previous_exceptions' => self::previousChain($exception),
            'cause' => self::humanCause($exception),
        ], $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function attempt(string $job, Throwable $exception, array $context = []): void
    {
        self::write(self::ATTEMPT_MESSAGE, $job, $exception, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function definitive(string $job, ?Throwable $exception, array $context = []): void
    {
        self::write(self::DEFINITIVE_MESSAGE, $job, $exception, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function batch(string $job, ?Throwable $exception, array $context = []): void
    {
        self::write(self::BATCH_MESSAGE, $job, $exception, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function dispatchFailed(string $job, Throwable $exception, array $context = []): void
    {
        self::write(self::DISPATCH_MESSAGE, $job, $exception, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function write(string $message, string $job, ?Throwable $exception, array $context): void
    {
        $payload = self::payload($job, $exception, $context);

        if ($exception !== null) {
            $payload['exception'] = $exception;
        }

        Log::error($message, $payload);
    }

    public static function humanCause(?Throwable $exception): string
    {
        if ($exception === null) {
            return 'Causa desconocida: el worker no entregó una excepción.';
        }

        $message = trim($exception->getMessage());

        if ($message === '') {
            return 'Excepción '.$exception::class.' sin mensaje. Archivo '.$exception->getFile().' línea '.$exception->getLine().'.';
        }

        $previous = $exception->getPrevious();

        if ($previous instanceof Throwable) {
            $previousMessage = trim($previous->getMessage());

            if ($previousMessage !== '' && $previousMessage !== $message) {
                return $message.' | Causa anterior: '.$previousMessage;
            }
        }

        return $message;
    }

    /**
     * @return list<array{class: string, message: string, file: string, line: int, code: int|string}>
     */
    private static function previousChain(?Throwable $exception): array
    {
        $chain = [];
        $previous = $exception?->getPrevious();

        while ($previous instanceof Throwable) {
            $chain[] = [
                'class' => $previous::class,
                'message' => $previous->getMessage(),
                'file' => $previous->getFile(),
                'line' => $previous->getLine(),
                'code' => $previous->getCode(),
            ];

            $previous = $previous->getPrevious();
        }

        return $chain;
    }
}
