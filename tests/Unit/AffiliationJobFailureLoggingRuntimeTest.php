<?php

declare(strict_types=1);

use App\Jobs\SendAffiliateCarnetEmailJob;
use App\Support\Affiliations\AffiliationJobFailureLogger;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;

uses(Tests\TestCase::class);

it('el failed() de un job de carnet escribe causa, archivo, línea y traza en el log', function (): void {
    $entries = [];

    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$entries): void {
        $entries[] = $event;
    });

    $job = new SendAffiliateCarnetEmailJob(
        email: 'ana@example.com',
        recipientName: 'Ana Perez',
        affiliationCode: 'TDEC-IND-LOG',
        carnetPath: '/tmp/missing-carnet-log.pdf',
        condicionadoPath: '/tmp/missing-cond-log.pdf',
    );

    $previous = new RuntimeException('SMTP timeout');
    $exception = new RuntimeException('Connection refused', 0, $previous);

    $job->failed($exception);

    $error = collect($entries)->first(
        fn (MessageLogged $event): bool => $event->level === 'error'
            && $event->message === AffiliationJobFailureLogger::DEFINITIVE_MESSAGE,
    );

    expect($error)->not->toBeNull()
        ->and($error->context['job'])->toBe(SendAffiliateCarnetEmailJob::class)
        ->and($error->context['affiliation_code'])->toBe('TDEC-IND-LOG')
        ->and($error->context['email'])->toBe('ana@example.com')
        ->and($error->context['exception_class'])->toBe(RuntimeException::class)
        ->and($error->context['exception_message'])->toBe('Connection refused')
        ->and($error->context['exception_file'])->toBe($exception->getFile())
        ->and($error->context['exception_line'])->toBe($exception->getLine())
        ->and($error->context['exception_trace'])->toContain('AffiliationJobFailureLoggingRuntimeTest.php')
        ->and($error->context['cause'])->toContain('Connection refused')
        ->and($error->context['cause'])->toContain('SMTP timeout')
        ->and($error->context['previous_exceptions'][0]['message'])->toBe('SMTP timeout')
        ->and($error->context['carnet_exists'])->toBeFalse()
        ->and($error->context['condicionado_exists'])->toBeFalse();
});

it('handle() registra el intento cuando falta el carnet y relanza la excepción', function (): void {
    $entries = [];

    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$entries): void {
        $entries[] = $event;
    });

    $job = new SendAffiliateCarnetEmailJob(
        email: 'ana@example.com',
        recipientName: 'Ana Perez',
        affiliationCode: 'TDEC-IND-LOG-HANDLE',
        carnetPath: '/tmp/missing-carnet-handle.pdf',
        condicionadoPath: '/tmp/missing-cond-handle.pdf',
    );

    expect(fn () => $job->handle())
        ->toThrow(RuntimeException::class, 'No se encontró el carnet para enviar a ana@example.com');

    $error = collect($entries)->first(
        fn (MessageLogged $event): bool => $event->level === 'error'
            && $event->message === AffiliationJobFailureLogger::ATTEMPT_MESSAGE,
    );

    expect($error)->not->toBeNull()
        ->and($error->context['affiliation_code'])->toBe('TDEC-IND-LOG-HANDLE')
        ->and($error->context['carnet_exists'])->toBeFalse()
        ->and($error->context['exception_message'])->toContain('No se encontró el carnet');
});
