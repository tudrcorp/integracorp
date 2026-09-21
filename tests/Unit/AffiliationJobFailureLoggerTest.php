<?php

declare(strict_types=1);

use App\Jobs\GenerateCorporateCertificateJob;
use App\Jobs\SendAffiliateCarnetEmailJob;
use App\Support\Affiliations\AffiliationJobFailureLogger;

it('incluye la excepción real, archivo, línea, traza, causa y la cadena anterior', function (): void {
    $previous = new RuntimeException('disco lleno');
    $exception = new InvalidArgumentException('No se pudo guardar el PDF', 422, $previous);

    $payload = AffiliationJobFailureLogger::payload(GenerateCorporateCertificateJob::class, $exception, [
        'affiliation_code' => 'TDEC-COR-0001',
        'action' => 'regenerate-documents',
    ]);

    expect($payload['job'])->toBe(GenerateCorporateCertificateJob::class)
        ->and($payload['exception_class'])->toBe(InvalidArgumentException::class)
        ->and($payload['exception_message'])->toBe('No se pudo guardar el PDF')
        ->and($payload['exception_code'])->toBe(422)
        ->and($payload['exception_file'])->toBe($exception->getFile())
        ->and($payload['exception_line'])->toBe($exception->getLine())
        ->and($payload['exception_trace'])->toContain('AffiliationJobFailureLoggerTest.php')
        ->and($payload['affiliation_code'])->toBe('TDEC-COR-0001')
        ->and($payload['action'])->toBe('regenerate-documents')
        ->and($payload['cause'])->toContain('No se pudo guardar el PDF')
        ->and($payload['cause'])->toContain('disco lleno')
        ->and($payload['previous_exceptions'])->toHaveCount(1)
        ->and($payload['previous_exceptions'][0]['class'])->toBe(RuntimeException::class)
        ->and($payload['previous_exceptions'][0]['message'])->toBe('disco lleno');
});

it('no explota si la excepción es nula y describe la causa', function (): void {
    $payload = AffiliationJobFailureLogger::payload(SendAffiliateCarnetEmailJob::class, null);

    expect($payload['job'])->toBe(SendAffiliateCarnetEmailJob::class)
        ->and($payload['exception_class'])->toBeNull()
        ->and($payload['exception_message'])->toBeNull()
        ->and($payload['exception_file'])->toBeNull()
        ->and($payload['exception_line'])->toBeNull()
        ->and($payload['exception_trace'])->toBeNull()
        ->and($payload['previous_exceptions'])->toBe([])
        ->and($payload['cause'])->toContain('Causa desconocida');
});

it('describe una excepción sin mensaje con archivo y línea', function (): void {
    $exception = new RuntimeException('');

    expect(AffiliationJobFailureLogger::humanCause($exception))
        ->toContain(RuntimeException::class)
        ->toContain('sin mensaje')
        ->toContain((string) $exception->getLine());
});

it('los jobs de documentos de afiliación registran el fallo de cada intento y el definitivo', function (string $jobFile): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/'.$jobFile);

    expect($contents)
        ->toContain('LogsAffiliationJobFailures')
        ->toContain('runWithAffiliationFailureLogging')
        ->toContain('logAffiliationJobFailure')
        ->toContain('affiliationJobFailureContext')
        ->toContain('function failed(?Throwable $exception): void');
})->with([
    'ResendMailNotificacionAfiliacionIndividual.php',
    'SendAffiliateCarnetEmailJob.php',
    'GenerateCorporateCertificateJob.php',
    'GenerateCorporateCombinedCardsJob.php',
    'GenerateCorporateAffiliateTarjetasChunkJob.php',
    'PushAffiliationDocumentToViveplusJob.php',
]);

it('el concern de logging registra el intento y relanza la excepción original', function (): void {
    $traitFile = dirname(__DIR__, 2).'/app/Support/Affiliations/Concerns/LogsAffiliationJobFailures.php';
    $loggerFile = dirname(__DIR__, 2).'/app/Support/Affiliations/AffiliationJobFailureLogger.php';

    expect(file_get_contents($traitFile))
        ->toContain('AffiliationJobFailureLogger::attempt')
        ->toContain('throw $exception')
        ->toContain('AffiliationJobFailureLogger::definitive')
        ->toContain('LogsAffiliationJobFailures');

    expect(file_get_contents($loggerFile))
        ->toContain(AffiliationJobFailureLogger::ATTEMPT_MESSAGE)
        ->toContain(AffiliationJobFailureLogger::DEFINITIVE_MESSAGE)
        ->toContain(AffiliationJobFailureLogger::BATCH_MESSAGE)
        ->toContain(AffiliationJobFailureLogger::DISPATCH_MESSAGE)
        ->toContain('Log::error($message, $payload)')
        ->toContain("\$payload['exception'] = \$exception")
        ->toContain('exception_class')
        ->toContain('exception_message')
        ->toContain('exception_trace')
        ->toContain('previous_exceptions')
        ->toContain('cause');
});

it('el reenvío de certificado incluye destinatario y si el PDF existe', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/ResendMailNotificacionAfiliacionIndividual.php');

    expect($contents)
        ->toContain("'email'")
        ->toContain("'phone'")
        ->toContain("'name_pdf'")
        ->toContain("'pdf_exists'");
});

it('el job de carnet incluye código, correo y existencia de adjuntos', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/SendAffiliateCarnetEmailJob.php');

    expect($contents)
        ->toContain("'affiliation_code'")
        ->toContain("'carnet_exists'")
        ->toContain("'condicionado_exists'");
});

it('el lote corporativo y el correo de documentos registran la falla del batch y del mailable', function (): void {
    $service = file_get_contents(dirname(__DIR__, 2).'/app/Services/AffiliationCorporateBusinessDocumentsService.php');
    $carnetService = file_get_contents(dirname(__DIR__, 2).'/app/Support/AffiliateCard/AffiliateCarnetEmailDispatchService.php');
    $mail = file_get_contents(dirname(__DIR__, 2).'/app/Mail/AffiliationDocumentsGeneratedMail.php');
    $individualController = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/AffiliationBusinessDocumentsController.php');
    $corporateController = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/AffiliationCorporateBusinessDocumentsController.php');

    expect($service)
        ->toContain('AffiliationJobFailureLogger::batch')
        ->toContain('AffiliationJobFailureLogger::dispatchFailed')
        ->and($carnetService)->toContain('AffiliationJobFailureLogger::batch')
        ->and($carnetService)->toContain('AffiliationJobFailureLogger::dispatchFailed')
        ->and($mail)->toContain('AffiliationJobFailureLogger::definitive')
        ->and($mail)->toContain('missing_attachments')
        ->and($individualController)->toContain('AffiliationJobFailureLogger::dispatchFailed')
        ->and($corporateController)->toContain('AffiliationJobFailureLogger::dispatchFailed');
});

it('ViVEplus deja constancia de si reintentará después y si el PDF sigue en disco', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/PushAffiliationDocumentToViveplusJob.php');

    expect($contents)
        ->toContain("'will_retry_later'")
        ->toContain("'later_retries_exhausted'")
        ->toContain("'document_exists'")
        ->toContain("'idempotency_key'");
});

it('el chunk de tarjetas corporativas reporta código, tamaño y archivos de salida', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/GenerateCorporateAffiliateTarjetasChunkJob.php');

    expect($contents)
        ->toContain("'chunk_size'")
        ->toContain("'output_filenames'");
});

it('el certificado y el PDF combinado corporativos identifican el código de afiliación', function (): void {
    $certificate = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/GenerateCorporateCertificateJob.php');
    $combined = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/GenerateCorporateCombinedCardsJob.php');

    expect($certificate)
        ->toContain("'affiliation_code'")
        ->toContain("'document_kind' => 'certificate'")
        ->and($combined)->toContain("'document_kind' => 'combined-cards'");
});
