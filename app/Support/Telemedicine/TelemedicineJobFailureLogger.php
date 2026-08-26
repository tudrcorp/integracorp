<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use Illuminate\Support\Facades\Log;
use Throwable;

final class TelemedicineJobFailureLogger
{
    public const ATTEMPT_MESSAGE = 'TELEMEDICINA: job falló en un intento';

    public const DEFINITIVE_MESSAGE = 'TELEMEDICINA: job falló definitivamente';

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
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function documentJobContext(array $data, mixed $user, ?string $typeDocument): array
    {
        return [
            'type_document' => $typeDocument,
            'telemedicine_consultation_id' => $data['telemedicine_consultation_id'] ?? null,
            'telemedicine_case_id' => $data['telemedicine_case_id'] ?? null,
            'telemedicine_patient_id' => $data['telemedicine_patient_id'] ?? null,
            'code_reference' => $data['code_reference'] ?? null,
            'ci_patient' => $data['ci_patient'] ?? $data['ci_patiente'] ?? null,
            'user_id' => self::resolveUserId($user),
        ];
    }

    public static function resolveUserId(mixed $user): mixed
    {
        if (is_object($user) && isset($user->id)) {
            return $user->id;
        }

        if (is_numeric($user)) {
            return (int) $user;
        }

        return null;
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
