<?php

declare(strict_types=1);

use App\Jobs\GeneratePdfMedicamentos;
use App\Jobs\SendTelemedicinaDocument;
use App\Support\Telemedicine\TelemedicineJobFailureLogger;

it('incluye la excepción real, archivo, línea, traza y la causa anterior', function (): void {
    $previous = new RuntimeException('disco lleno');
    $exception = new InvalidArgumentException('No se pudo guardar el PDF', 422, $previous);

    $payload = TelemedicineJobFailureLogger::payload(GeneratePdfMedicamentos::class, $exception, [
        'telemedicine_consultation_id' => 17,
        'type_document' => 'medicamentos',
    ]);

    expect($payload['job'])->toBe(GeneratePdfMedicamentos::class)
        ->and($payload['exception_class'])->toBe(InvalidArgumentException::class)
        ->and($payload['exception_message'])->toBe('No se pudo guardar el PDF')
        ->and($payload['exception_code'])->toBe(422)
        ->and($payload['exception_file'])->toBe($exception->getFile())
        ->and($payload['exception_line'])->toBe($exception->getLine())
        ->and($payload['exception_trace'])->toContain('TelemedicineJobFailureLoggerTest.php')
        ->and($payload['telemedicine_consultation_id'])->toBe(17)
        ->and($payload['type_document'])->toBe('medicamentos')
        ->and($payload['previous_exceptions'])->toHaveCount(1)
        ->and($payload['previous_exceptions'][0]['class'])->toBe(RuntimeException::class)
        ->and($payload['previous_exceptions'][0]['message'])->toBe('disco lleno');
});

it('no explota si la excepción es nula', function (): void {
    $payload = TelemedicineJobFailureLogger::payload(SendTelemedicinaDocument::class, null);

    expect($payload['job'])->toBe(SendTelemedicinaDocument::class)
        ->and($payload['exception_class'])->toBeNull()
        ->and($payload['exception_message'])->toBeNull()
        ->and($payload['exception_file'])->toBeNull()
        ->and($payload['exception_line'])->toBeNull()
        ->and($payload['exception_trace'])->toBeNull()
        ->and($payload['previous_exceptions'])->toBe([]);
});

it('extrae el contexto de un payload de documento de telemedicina', function (): void {
    $user = (object) ['id' => 44];

    $context = TelemedicineJobFailureLogger::documentJobContext([
        'telemedicine_consultation_id' => 9,
        'telemedicine_case_id' => 3,
        'telemedicine_patient_id' => 12,
        'code_reference' => 'TM-100',
        'ci_patiente' => 'V123',
    ], $user, 'laboratorios');

    expect($context)->toMatchArray([
        'type_document' => 'laboratorios',
        'telemedicine_consultation_id' => 9,
        'telemedicine_case_id' => 3,
        'telemedicine_patient_id' => 12,
        'code_reference' => 'TM-100',
        'ci_patient' => 'V123',
        'user_id' => 44,
    ]);
});

it('los jobs del panel de telemedicina registran el fallo real y lo relanzan', function (string $jobFile): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/'.$jobFile);

    expect($contents)
        ->toContain('LogsTelemedicineJobFailures')
        ->toContain('runWithTelemedicineFailureLogging')
        ->toContain('logTelemedicineJobFailure')
        ->toContain('telemedicineJobFailureContext')
        ->not->toContain('Log::info(\'')
        ->not->toContain('$exception->getMessage()');
})->with([
    'GeneratePdfMedicamentos.php',
    'GeneratePdfLaboratorio.php',
    'GeneratePdfImagenologia.php',
    'GeneratePdfEspecialista.php',
    'GeneratePdfInformeMedicoCorto.php',
    'GeneratePdfInformeMedicoLargo.php',
    'SendTelemedicinaDocument.php',
    'SendTelemedicineConsultationDocuments.php',
]);

it('el trait registra el intento y relanza la excepción original', function (): void {
    $traitFile = dirname(__DIR__, 2).'/app/Support/Telemedicine/Concerns/LogsTelemedicineJobFailures.php';
    $loggerFile = dirname(__DIR__, 2).'/app/Support/Telemedicine/TelemedicineJobFailureLogger.php';

    expect(file_get_contents($traitFile))
        ->toContain('TelemedicineJobFailureLogger::attempt')
        ->toContain('throw $exception')
        ->toContain('TelemedicineJobFailureLogger::definitive')
        ->toContain('trait LogsTelemedicineJobFailures');

    expect(file_get_contents($loggerFile))
        ->toContain(TelemedicineJobFailureLogger::ATTEMPT_MESSAGE)
        ->toContain(TelemedicineJobFailureLogger::DEFINITIVE_MESSAGE)
        ->toContain('Log::error($message, $payload)')
        ->toContain("\$payload['exception'] = \$exception")
        ->toContain('exception_class')
        ->toContain('exception_message')
        ->toContain('exception_trace')
        ->toContain('previous_exceptions');
});

it('SendTelemedicinaDocument y GeneratePdfEspecialista ya no loguean el nombre de otro job', function (): void {
    $sendDocument = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/SendTelemedicinaDocument.php');
    $especialista = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/GeneratePdfEspecialista.php');

    expect($sendDocument)
        ->not->toContain('GeneratePdfImagenologia: FAILED')
        ->and($especialista)->not->toContain('GeneratePdfImagenologia: FAILED');
});
