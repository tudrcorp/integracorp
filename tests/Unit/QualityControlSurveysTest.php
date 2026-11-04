<?php

declare(strict_types=1);

use App\Enums\QualityControlAffiliateType;
use App\Enums\QualityControlRating;
use App\Enums\QualityControlService;
use App\Jobs\ImportQualityControlSurveysJob;
use App\Jobs\SendQualityControlPdfJob;
use App\Mail\QualityControlPdfMail;
use App\Models\QualityControlSurvey;
use App\Models\User;
use App\Services\QualityControlPdfService;
use App\Support\Operations\QualityControl\QualityControlAffiliateLookup;
use App\Support\Operations\QualityControl\QualityControlReportRequest;
use App\Support\Operations\QualityControl\QualityControlSurveyFileReader;
use App\Support\Operations\QualityControl\QualityControlSurveyImporter;
use App\Support\Operations\QualityControl\QualityControlSurveyNotes;
use App\Support\Operations\QualityControl\QualityControlSurveyWriter;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

uses(Tests\TestCase::class);

/**
 * Conexión sqlite `:memory:` propia: estos tests escriben y nunca deben tocar la base real,
 * con o sin config cacheada. La tabla de encuestas se crea con la migración real.
 */
beforeEach(function (): void {
    config()->set('database.connections.quality_control_testing', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => false,
    ]);

    $this->previousConnection = config('database.default');
    config()->set('database.default', 'quality_control_testing');
    DB::purge('quality_control_testing');
    DB::setDefaultConnection('quality_control_testing');

    expect(DB::connection()->getDriverName())->toBe('sqlite')
        ->and(DB::connection()->getDatabaseName())->toBe(':memory:');

    /** La config cacheada usa caché y sesión en base de datos: aquí van en memoria. */
    config()->set('cache.default', 'array');
    config()->set('session.driver', 'array');
    Cache::setDefaultDriver('array');

    (require __DIR__.'/../../database/migrations/2026_11_04_090000_create_quality_control_surveys_table.php')->up();
    (require __DIR__.'/../../database/migrations/2026_11_04_180000_create_quality_control_survey_notes_table.php')->up();

    Schema::create('affiliations', function (Blueprint $table): void {
        $table->id();
        $table->string('code')->nullable();
        $table->timestamps();
        $table->timestamp('deleted_at')->nullable();
    });

    Schema::create('affiliates', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('affiliation_id')->nullable();
        $table->string('full_name')->nullable();
        $table->string('phone')->nullable();
        $table->string('status')->nullable();
        $table->string('nro_identificacion')->nullable();
        $table->string('identity_document_key')->nullable();
        $table->timestamps();
        $table->timestamp('deleted_at')->nullable();
    });

    Schema::create('affiliation_corporates', function (Blueprint $table): void {
        $table->id();
        $table->string('code')->nullable();
        $table->timestamps();
        $table->timestamp('deleted_at')->nullable();
    });

    Schema::create('affiliate_corporates', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('affiliation_corporate_id')->nullable();
        $table->string('first_name')->nullable();
        $table->string('last_name')->nullable();
        $table->string('phone')->nullable();
        $table->string('status')->nullable();
        $table->string('nro_identificacion')->nullable();
        $table->string('identity_document_key')->nullable();
        $table->timestamps();
        $table->timestamp('deleted_at')->nullable();
    });

    Schema::create('logs', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('user_id')->nullable();
        $table->string('action')->nullable();
        $table->string('route')->nullable();
        $table->text('response')->nullable();
        $table->string('method')->nullable();
        $table->string('ip')->nullable();
        $table->text('user_agent')->nullable();
        $table->timestamps();
    });

    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
        $table->string('email')->nullable();
        $table->timestamps();
    });

    DB::table('affiliation_corporates')->insert(['id' => 10, 'code' => 'TDEC-COR-00010']);
    DB::table('affiliate_corporates')->insert([
        ['id' => 86, 'affiliation_corporate_id' => 10, 'first_name' => 'SUSAN KAROL', 'last_name' => 'SIRITT SAAVEDRA', 'phone' => '04246677192', 'status' => 'ACTIVO', 'nro_identificacion' => '14582677', 'identity_document_key' => '14582677'],
        /** Misma persona en una afiliación vieja: debe ganar la ACTIVA. */
        ['id' => 40, 'affiliation_corporate_id' => 10, 'first_name' => 'SUSAN', 'last_name' => 'SIRITT', 'phone' => null, 'status' => 'EXCLUIDO', 'nro_identificacion' => '14582677', 'identity_document_key' => '14582677'],
    ]);

    DB::table('affiliations')->insert(['id' => 5, 'code' => 'TDEC-IND-0005']);
    DB::table('affiliates')->insert([
        'id' => 7, 'affiliation_id' => 5, 'full_name' => 'RIXIO JESUS BRICEÑO', 'phone' => '0414-6924655',
        'status' => 'ACTIVO', 'nro_identificacion' => '7822717', 'identity_document_key' => '7822717',
    ]);

    $this->dir = sys_get_temp_dir().'/qc-test-'.bin2hex(random_bytes(4));
    mkdir($this->dir);
});

afterEach(function (): void {
    DB::purge('quality_control_testing');
    config()->set('database.default', $this->previousConnection);
    DB::setDefaultConnection($this->previousConnection);

    foreach (glob($this->dir.'/*') ?: [] as $file) {
        unlink($file);
    }
    @rmdir($this->dir);
});

/**
 * Réplica del Excel del formulario: título arriba, encabezados en la fila 5, fechas como
 * número de serie (con hora en la marca temporal) y dos columnas «Servicio Recibido».
 *
 * @param  list<list<mixed>>  $rows
 */
function qcWorkbook(string $path, array $rows): string
{
    $writer = new OpenSpout\Writer\XLSX\Writer;
    $writer->openToFile($path);
    $writer->addRow(OpenSpout\Common\Entity\Row::fromValues([]));
    $writer->addRow(OpenSpout\Common\Entity\Row::fromValues([]));
    $writer->addRow(OpenSpout\Common\Entity\Row::fromValues(['', 'CONTROL DE CALIDAD DE SERVICIOS']));
    $writer->addRow(OpenSpout\Common\Entity\Row::fromValues([]));
    $writer->addRow(OpenSpout\Common\Entity\Row::fromValues([
        '', 'Marca temporal', 'Fecha de Servicio', 'Cédula de Identidad del Paciente', 'Nombre Completo del Paciente',
        'Número de contacto', 'Como calificaría los canales de atención', 'Como calificaría el tiempo de respuesta',
        'Como calificaría el servicio médico', 'Quedó satisfecho con el servicio?', 'TIENE ALGUNA SUGERENCIA U OBSERVACIÓN?',
        'Servicio Recibido [CEFECEC]', 'Servicio Recibido',
    ]));

    foreach ($rows as $row) {
        $writer->addRow(OpenSpout\Common\Entity\Row::fromValues($row));
    }

    $writer->close();

    return $path;
}

/**
 * @return list<list<mixed>>
 */
function qcSampleRows(): array
{
    return [
        ['', 46311.615694444445, 46236, 14582677, 'Susan Karol Siritt Saavedra', '58 424-6677192', 'Satisfecho', 'Satisfecho', 'Satisfecho', 'Satisfecho', 'AGRADECIDA POR LA ATENCION', '', 'TELEMEDICINA'],
        ['', 46314.635462962964, 46262, 15708392, 'EVERT LENIN CARRASCO GONZALEZ', '4246494213', 'Totalmente satisfecho', 'Satisfecho', 'Totalmente satisfecho', 'Satisfecho', 'MEJORAR EL TIEMPO DE RESPUESTA', '', 'ATENCIÓN MÉDICA DOMICILIARIA, LABORATORIOS, ENTREGA DE MEDICAMENTOS'],
        /** Calificación inválida y servicio desconocido: no entra. */
        ['', 46314.648946759262, 46260, 32738215, 'ESTEFANIA MARIN PINO', '0426-5941842', 'Excelente', 'Neutro', 'Satisfecho', 'Satisfecho', '', '', 'PEDIATRIA'],
        /** La misma respuesta repetida dentro del archivo: cuenta una vez. */
        ['', 46311.615694444445, 46236, 14582677, 'Susan Karol Siritt Saavedra', '58 424-6677192', 'Satisfecho', 'Satisfecho', 'Satisfecho', 'Satisfecho', 'AGRADECIDA POR LA ATENCION', '', 'TELEMEDICINA'],
        /** Cédula alfanumérica de menor, servicio en la columna [CEFECEC]. */
        ['', 46325.747499999998, 46287, 'CIM015159173-21', 'DASHA ALEJANDRA VINAJA VASQUEZ', '4121237519', 'Totalmente satisfecho', 'Totalmente satisfecho', 'Totalmente satisfecho', 'Totalmente satisfecho', 'NINGUNA', 'ESTUDIOS DE IMAGEN', 'ENTREGA DE MEDICAMENTOS'],
    ];
}

it('reconoce las calificaciones y los servicios del Excel aunque cambien mayúsculas y tildes', function (): void {
    expect(QualityControlRating::fromText('Totalmente satisfecho'))->toBe(QualityControlRating::TotallySatisfied)
        ->and(QualityControlRating::fromText(' NEUTRO '))->toBe(QualityControlRating::Neutral)
        ->and(QualityControlRating::fromText('insatisfecho'))->toBe(QualityControlRating::Dissatisfied)
        ->and(QualityControlRating::fromText('Excelente'))->toBeNull()
        ->and(QualityControlRating::fromText(''))->toBeNull();

    expect(QualityControlService::parseList('ATENCIÓN MÉDICA DOMICILIARIA, LABORATORIOS, ENTREGA DE MEDICAMENTOS'))
        ->toBe(['services' => ['ATENCION_MEDICA_DOMICILIARIA', 'LABORATORIOS', 'ENTREGA_DE_MEDICAMENTOS'], 'unknown' => []])
        ->and(QualityControlService::parseList('Telemedicina, telemedicina; PEDIATRIA')['services'])->toBe(['TELEMEDICINA'])
        ->and(QualityControlService::parseList('PEDIATRIA')['unknown'])->toBe(['PEDIATRIA']);
});

it('ubica cada encabezado del formulario en su campo', function (string $header, ?string $field): void {
    expect(QualityControlSurveyFileReader::fieldForHeader($header))->toBe($field);
})->with([
    ['Marca temporal', 'responded_at'],
    ['Fecha de Servicio', 'service_date'],
    ['Cédula de Identidad del Paciente', 'patient_document'],
    ['Nombre Completo del Paciente', 'patient_name'],
    ['Número de contacto', 'contact_phone'],
    ['Como calificaría los canales de atención', 'rating_channels'],
    ['Como calificaría el tiempo de respuesta', 'rating_response_time'],
    ['Como calificaría el servicio médico', 'rating_medical_service'],
    ['Quedó satisfecho con el servicio?', 'rating_overall'],
    ['TIENE ALGUNA SUGERENCIA U OBSERVACIÓN?', 'observations'],
    ['Servicio Recibido [CEFECEC]', 'services'],
    ['Servicio Recibido', 'services'],
    ['CONTROL DE CALIDAD DE SERVICIOS', null],
]);

it('convierte las fechas del Excel conservando la hora de la marca temporal', function (): void {
    expect(QualityControlSurveyImporter::dateTime(46311.615694444445)?->format('Y-m-d H:i:s'))->toBe('2026-10-16 14:46:36')
        ->and(QualityControlSurveyImporter::dateTime(46236)?->format('Y-m-d'))->toBe('2026-08-02')
        ->and(QualityControlSurveyImporter::dateTime('16/10/2026 14:46:36')?->format('Y-m-d H:i:s'))->toBe('2026-10-16 14:46:36')
        ->and(QualityControlSurveyImporter::dateTime('2026-08-02')?->format('d/m/Y'))->toBe('02/08/2026')
        ->and(QualityControlSurveyImporter::dateTime(new DateTimeImmutable('2026-08-02 09:30:00'))?->format('Y-m-d H:i'))->toBe('2026-08-02 09:30')
        ->and(QualityControlSurveyImporter::dateTime('32/13/2026'))->toBeNull()
        ->and(QualityControlSurveyImporter::dateTime('ayer'))->toBeNull()
        ->and(QualityControlSurveyImporter::dateTime(15))->toBeNull()
        ->and(QualityControlSurveyImporter::dateTime(''))->toBeNull();
});

it('rechaza la fila cuya fecha de servicio es posterior a la respuesta', function (): void {
    $columns = ['responded_at' => [0], 'service_date' => [1], 'patient_document' => [2], 'patient_name' => [3]];

    $parsed = QualityControlSurveyImporter::parseRow([46311.5, 46320, '1234567', 'ANA PEREZ'], $columns, QualityControlAffiliateType::Corporate);

    expect($parsed['data'])->toBeNull()
        ->and($parsed['errors'][0])->toContain('posterior a la respuesta');
});

it('analiza sin escribir: cuenta nuevas, enlazadas, repetidas y filas con error', function (): void {
    $path = qcWorkbook($this->dir.'/respuestas.xlsx', qcSampleRows());

    $summary = QualityControlSurveyImporter::analyze($path, QualityControlAffiliateType::Corporate);

    expect($summary['total'])->toBe(5)
        ->and($summary['valid'])->toBe(3)
        ->and($summary['new'])->toBe(3)
        ->and($summary['repeated_in_file'])->toBe(1)
        ->and($summary['linked'])->toBe(1)
        ->and($summary['error_count'])->toBe(1)
        ->and($summary['errors'][0]['row'])->toBe(8)
        ->and($summary['errors'][0]['message'])->toContain('«Excelente» no es una calificación válida')
        ->and($summary['errors'][0]['message'])->toContain('PEDIATRIA')
        ->and($summary['missing_columns'])->toBe([])
        ->and(QualityControlSurvey::withTrashed()->count())->toBe(0);
});

it('avisa cuando las cédulas pertenecen a la otra población', function (): void {
    $path = qcWorkbook($this->dir.'/respuestas.xlsx', qcSampleRows());

    $summary = QualityControlSurveyImporter::analyze($path, QualityControlAffiliateType::Individual);

    expect($summary['linked'])->toBe(0)
        ->and($summary['other_type_matches'])->toBe(1);
});

it('importa enlazando al afiliado activo y no duplica al reimportar', function (): void {
    $path = qcWorkbook($this->dir.'/respuestas.xlsx', qcSampleRows());

    $first = QualityControlSurveyImporter::import($path, QualityControlAffiliateType::Corporate, 99, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa');
    $second = QualityControlSurveyImporter::import($path, QualityControlAffiliateType::Corporate, 99, 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb');

    $susan = QualityControlSurvey::query()->where('patient_document', '14582677')->sole();
    $dasha = QualityControlSurvey::query()->where('patient_document', 'CIM015159173-21')->sole();

    expect($first)->toBe(['inserted' => 3, 'skipped' => 0, 'errors' => 1, 'linked' => 1])
        ->and($second['inserted'])->toBe(0)
        ->and($second['skipped'])->toBe(3)
        ->and(QualityControlSurvey::query()->count())->toBe(3)
        ->and($susan->affiliate_id)->toBe(86)
        ->and($susan->affiliate_type)->toBe(QualityControlAffiliateType::Corporate)
        ->and($susan->responded_at->format('Y-m-d H:i:s'))->toBe('2026-10-16 14:46:36')
        ->and($susan->service_date->format('Y-m-d'))->toBe('2026-08-02')
        ->and($susan->patient_name)->toBe('SUSAN KAROL SIRITT SAAVEDRA')
        ->and($susan->rating_overall)->toBe(QualityControlRating::Satisfied)
        ->and($susan->services)->toBe(['TELEMEDICINA'])
        ->and($susan->source)->toBe(QualityControlSurvey::SOURCE_IMPORT)
        ->and($susan->created_by)->toBe(99)
        ->and($dasha->affiliate_id)->toBeNull()
        ->and($dasha->services)->toBe(['ESTUDIOS_DE_IMAGEN', 'ENTREGA_DE_MEDICAMENTOS']);
});

it('una encuesta eliminada no vuelve a entrar con la siguiente importación', function (): void {
    $path = qcWorkbook($this->dir.'/respuestas.xlsx', qcSampleRows());
    QualityControlSurveyImporter::import($path, QualityControlAffiliateType::Corporate, null, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa');

    QualityControlSurveyWriter::delete(QualityControlSurvey::query()->where('patient_document', '14582677')->sole());

    $summary = QualityControlSurveyImporter::analyze($path, QualityControlAffiliateType::Corporate);
    $again = QualityControlSurveyImporter::import($path, QualityControlAffiliateType::Corporate, null, 'cccccccc-cccc-4ccc-8ccc-cccccccccccc');

    expect($summary['new'])->toBe(0)
        ->and($summary['duplicates'])->toBe(3)
        ->and($again['inserted'])->toBe(0)
        ->and(QualityControlSurvey::query()->count())->toBe(2)
        ->and(QualityControlSurvey::onlyTrashed()->count())->toBe(1)
        ->and(DB::table('logs')->where('action', 'AUDIT_QUALITY_CONTROL_SURVEY_DELETED')->count())->toBe(1);
});

it('una importación del mismo archivo en Individuales no choca con la de Corporativos', function (): void {
    $path = qcWorkbook($this->dir.'/respuestas.xlsx', qcSampleRows());

    QualityControlSurveyImporter::import($path, QualityControlAffiliateType::Corporate, null, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa');
    $individual = QualityControlSurveyImporter::import($path, QualityControlAffiliateType::Individual, null, 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb');

    expect($individual['inserted'])->toBe(3)
        ->and(QualityControlSurvey::query()->ofType(QualityControlAffiliateType::Individual)->count())->toBe(3)
        ->and(QualityControlSurvey::query()->ofType(QualityControlAffiliateType::Corporate)->count())->toBe(3);
});

it('falla con un mensaje claro si el archivo no trae los encabezados', function (): void {
    $writer = new OpenSpout\Writer\XLSX\Writer;
    $writer->openToFile($this->dir.'/otro.xlsx');
    $writer->addRow(OpenSpout\Common\Entity\Row::fromValues(['CODIGO', 'MONTO']));
    $writer->addRow(OpenSpout\Common\Entity\Row::fromValues(['A1', 10]));
    $writer->close();

    expect(fn () => QualityControlSurveyImporter::analyze($this->dir.'/otro.xlsx', QualityControlAffiliateType::Corporate))
        ->toThrow(RuntimeException::class, 'No se encontró la fila de encabezados');

    file_put_contents($this->dir.'/x.pdf', '%PDF');
    expect(fn () => QualityControlSurveyImporter::analyze($this->dir.'/x.pdf', QualityControlAffiliateType::Corporate))
        ->toThrow(RuntimeException::class, 'Formato no soportado');
});

it('lee también el CSV exportado del formulario', function (): void {
    file_put_contents($this->dir.'/r.csv', implode("\n", [
        'CONTROL DE CALIDAD DE SERVICIOS;;;;;;;;;;',
        'Marca temporal;Fecha de Servicio;Cédula de Identidad del Paciente;Nombre Completo del Paciente;Número de contacto;Como calificaría los canales de atención;Como calificaría el tiempo de respuesta;Como calificaría el servicio médico;Quedó satisfecho con el servicio?;TIENE ALGUNA SUGERENCIA U OBSERVACIÓN?;Servicio Recibido',
        '16/10/2026 14:46:36;02/08/2026;7822717;RIXIO JESUS BRICEÑO;414-6924655;Satisfecho;Neutro;Satisfecho;Insatisfecho;TARDARON;TELEMEDICINA',
    ]));

    $result = QualityControlSurveyImporter::import($this->dir.'/r.csv', QualityControlAffiliateType::Individual, null, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa');
    $survey = QualityControlSurvey::query()->sole();

    expect($result['inserted'])->toBe(1)
        ->and($survey->affiliate_id)->toBe(7)
        ->and($survey->patient_name)->toBe('RIXIO JESUS BRICEÑO')
        ->and($survey->rating_overall)->toBe(QualityControlRating::Dissatisfied);
});

it('busca al afiliado por cédula normalizada y prefiere el activo', function (): void {
    expect(QualityControlAffiliateLookup::find(QualityControlAffiliateType::Corporate, 'V-14.582.677'))
        ->toBe(['id' => 86, 'name' => 'SUSAN KAROL SIRITT SAAVEDRA', 'phone' => '04246677192', 'status' => 'ACTIVO', 'affiliation_code' => 'TDEC-COR-00010'])
        ->and(QualityControlAffiliateLookup::find(QualityControlAffiliateType::Individual, '14582677'))->toBeNull()
        ->and(QualityControlAffiliateLookup::find(QualityControlAffiliateType::Individual, ' 7822717 ')['id'])->toBe(7)
        ->and(QualityControlAffiliateLookup::find(QualityControlAffiliateType::Corporate, ''))->toBeNull();
});

/**
 * @return array<string, mixed>
 */
function qcFormData(array $overrides = []): array
{
    return [
        'patient_document' => 'v-14582677',
        'patient_name' => 'susan siritt',
        'contact_phone' => '0424 667 7192',
        'service_date' => CarbonImmutable::yesterday()->format('Y-m-d'),
        'rating_channels' => 'TOTALMENTE_SATISFECHO',
        'rating_response_time' => 'SATISFECHO',
        'rating_medical_service' => 'NEUTRO',
        'rating_overall' => 'INSATISFECHO',
        'observations' => '  Tardaron en llegar  ',
        'services' => ['TELEMEDICINA', 'LABORATORIOS', 'NO_EXISTE'],
        'affiliate_id' => 999,
        ...$overrides,
    ];
}

it('registra desde el formulario enlazando en el servidor, sin confiar en el affiliate_id del navegador', function (): void {
    $survey = QualityControlSurveyWriter::create(QualityControlAffiliateType::Corporate, qcFormData(), 5);

    expect($survey->affiliate_id)->toBe(86)
        ->and($survey->patient_document)->toBe('V-14582677')
        ->and($survey->patient_name)->toBe('SUSAN SIRITT')
        ->and($survey->services)->toBe(['TELEMEDICINA', 'LABORATORIOS'])
        ->and($survey->observations)->toBe('Tardaron en llegar')
        ->and($survey->source)->toBe(QualityControlSurvey::SOURCE_FORM)
        ->and($survey->responded_at)->not->toBeNull()
        ->and(DB::table('logs')->where('action', 'AUDIT_QUALITY_CONTROL_SURVEY_CREATED')->count())->toBe(1);
});

it('el formulario exige calificaciones y servicio, y no acepta fecha futura', function (array $overrides, string $field): void {
    expect(fn () => QualityControlSurveyWriter::create(QualityControlAffiliateType::Corporate, qcFormData($overrides), 5))
        ->toThrow(function (ValidationException $exception) use ($field): void {
            expect($exception->errors())->toHaveKey($field);
        });

    expect(QualityControlSurvey::query()->count())->toBe(0);
})->with([
    'sin servicio' => [['services' => []], 'data.services'],
    'servicio inventado' => [['services' => ['NO_EXISTE']], 'data.services'],
    'calificación vacía' => [['rating_overall' => null], 'data.rating_overall'],
    'calificación inventada' => [['rating_channels' => 'EXCELENTE'], 'data.rating_channels'],
    'fecha futura' => [['service_date' => CarbonImmutable::tomorrow()->format('Y-m-d')], 'data.service_date'],
    'sin cédula' => [['patient_document' => '  '], 'data.patient_document'],
    'sin nombre' => [['patient_name' => ''], 'data.patient_name'],
]);

it('al editar recalcula el enlace, conserva la marca temporal y deja el cambio en la auditoría', function (): void {
    $survey = QualityControlSurveyWriter::create(QualityControlAffiliateType::Corporate, qcFormData(), 5);
    $respondedAt = $survey->responded_at->format('Y-m-d H:i:s');

    QualityControlSurveyWriter::update($survey, qcFormData(['patient_document' => '99999999', 'rating_overall' => 'SATISFECHO']), 6);
    $survey->refresh();

    $log = DB::table('logs')->where('action', 'AUDIT_QUALITY_CONTROL_SURVEY_UPDATED')->sole();

    expect($survey->affiliate_id)->toBeNull()
        ->and($survey->rating_overall)->toBe(QualityControlRating::Satisfied)
        ->and($survey->responded_at->format('Y-m-d H:i:s'))->toBe($respondedAt)
        ->and($survey->updated_by)->toBe(6)
        ->and($log->response)->toContain('"rating_overall":{"from":"INSATISFECHO","to":"SATISFECHO"}');
});

it('el job importa, borra el archivo y es seguro de reintentar', function (): void {
    Storage::fake(ImportQualityControlSurveysJob::DISK);
    $disk = Storage::disk(ImportQualityControlSurveysJob::DISK);
    $disk->put('quality-control-imports/corporativo/r.xlsx', file_get_contents(qcWorkbook($this->dir.'/respuestas.xlsx', qcSampleRows())));

    $job = new ImportQualityControlSurveysJob('quality-control-imports/corporativo/r.xlsx', 'CORPORATIVO', 12345, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'respuestas.xlsx');
    $job->handle();
    $job->handle();

    expect(QualityControlSurvey::query()->count())->toBe(3)
        ->and($disk->exists('quality-control-imports/corporativo/r.xlsx'))->toBeFalse()
        ->and(DB::table('logs')->where('action', 'AUDIT_QUALITY_CONTROL_SURVEYS_IMPORTED')->count())->toBe(1);
});

it('registra los permisos de menú y de gestión en Operaciones, bajo AFILIADOS', function (): void {
    $navigation = App\Support\Filament\DepartmentNavigationPermissionRegistry::class;
    $actions = App\Support\Filament\BusinessFilamentActionPermissionRegistry::all();

    expect($navigation::slugsFor(App\Filament\Operations\Resources\QualityControlIndividuals\QualityControlIndividualResource::class))->toBe(['quality-control-individuales'])
        ->and($navigation::slugsFor(App\Filament\Operations\Resources\QualityControlCorporates\QualityControlCorporateResource::class))->toBe(['quality-control-corporativos'])
        ->and($navigation::moduleFor(App\Filament\Operations\Resources\QualityControlCorporates\QualityControlCorporateResource::class))->toBe('OPERACIONES')
        ->and($actions['gestionar-quality-control-individuales']['modules'])->toBe(['OPERACIONES'])
        ->and($actions['gestionar-quality-control-corporativos']['modules'])->toBe(['OPERACIONES'])
        ->and($actions['gestionar-quality-control-corporativos']['group'])->toBe('AFILIADOS');
});

it('ordena el grupo AFILIADOS con cada Quality Control debajo de su población', function (): void {
    $sort = fn (string $class): int => (new ReflectionProperty($class, 'navigationSort'))->getValue();
    $group = fn (string $class): string => (string) (new ReflectionProperty($class, 'navigationGroup'))->getValue();

    $order = [
        App\Filament\Operations\Resources\Affiliates\AffiliateResource::class,
        App\Filament\Operations\Resources\QualityControlIndividuals\QualityControlIndividualResource::class,
        App\Filament\Operations\Resources\AffiliateCorporates\AffiliateCorporateResource::class,
        App\Filament\Operations\Resources\QualityControlCorporates\QualityControlCorporateResource::class,
        App\Filament\Operations\Resources\CompanyAssociates\NuevosNegociosAssociateResource::class,
    ];

    expect(array_map($sort, $order))->toBe([1, 2, 3, 4, 5])
        ->and(array_unique(array_map($group, $order)))->toBe(['AFILIADOS'])
        ->and(App\Filament\Operations\Resources\QualityControlIndividuals\QualityControlIndividualResource::getNavigationLabel())->toBe('Quality Control Individuales')
        ->and(App\Filament\Operations\Resources\QualityControlCorporates\QualityControlCorporateResource::getNavigationLabel())->toBe('Quality Control Corporativos');
});

it('editar y eliminar exigen el permiso de gestión, además del de menú', function (): void {
    $source = file_get_contents(__DIR__.'/../../app/Filament/Operations/Resources/QualityControlSurveys/QualityControlSurveyResource.php');

    expect($source)
        ->toContain('use AuthorizesDepartmentNavigation;')
        ->toContain('return static::canManage() && static::belongsHere($record);')
        ->toContain('BusinessFilamentActionAccess::userCan($slug)')
        ->toContain('->ofType(static::affiliateType())')
        ->toMatch('/function canForceDelete\(Model \$record\): bool\s*\{\s*return false;/');
});

it('agrega notas de seguimiento como bitácora y las valida', function (): void {
    $survey = QualityControlSurveyWriter::create(QualityControlAffiliateType::Corporate, qcFormData(), 5);
    $user = new User;
    $user->forceFill(['id' => 5, 'name' => 'Analista QA']);

    QualityControlSurveyNotes::add($survey, "  Se llamó a la paciente.\r\nConfirma la demora.  ", $user);
    QualityControlSurveyNotes::add($survey, 'Se escaló a coordinación de medicamentos.', $user);

    $notes = $survey->fresh()->notes;

    expect($notes)->toHaveCount(2)
        ->and($notes->last()->note)->toBe("Se llamó a la paciente.\nConfirma la demora.")
        ->and($notes->first()->created_by_name)->toBe('Analista QA')
        ->and(DB::table('logs')->where('action', 'AUDIT_QUALITY_CONTROL_SURVEY_NOTE_ADDED')->count())->toBe(2)
        ->and(fn () => QualityControlSurveyNotes::add($survey, 'corta', $user))->toThrow(ValidationException::class);

    $survey->delete();

    expect(fn () => QualityControlSurveyNotes::add($survey, 'Nota sobre una encuesta eliminada', $user))
        ->toThrow(ValidationException::class);
});

it('genera la ficha en PDF una sola vez por contenido y la regenera al agregar una nota', function (): void {
    Storage::fake(QualityControlPdfService::DISK);
    $survey = QualityControlSurveyWriter::create(QualityControlAffiliateType::Corporate, qcFormData(), 5);

    $first = QualityControlPdfService::surveyDocument($survey->fresh());
    $again = QualityControlPdfService::surveyDocument($survey->fresh());

    QualityControlSurveyNotes::add($survey, 'Se llamó a la paciente y se escaló.', null);
    $afterNote = QualityControlPdfService::surveyDocument($survey->fresh());

    expect(str_starts_with($first['contents'], '%PDF'))->toBeTrue()
        ->and($again['path'])->toBe($first['path'])
        ->and($afterNote['fingerprint'])->not->toBe($first['fingerprint'])
        ->and(Storage::disk(QualityControlPdfService::DISK)->exists($first['path']))->toBeFalse()
        ->and(Storage::disk(QualityControlPdfService::DISK)->exists($afterNote['path']))->toBeTrue()
        ->and(QualityControlPdfService::surveyHtml($survey->fresh()))->toContain('Se llamó a la paciente y se escaló.')
        ->and($first['filename'])->toEndWith('.pdf');
});

it('el informe resume calificaciones por pregunta, servicios y neutros sin seguimiento', function (): void {
    $low = QualityControlSurveyWriter::create(QualityControlAffiliateType::Corporate, qcFormData(), 5);
    $good = QualityControlSurveyWriter::create(QualityControlAffiliateType::Corporate, qcFormData([
        'patient_document' => '7822717',
        'rating_channels' => 'SATISFECHO', 'rating_response_time' => 'SATISFECHO', 'rating_medical_service' => 'SATISFECHO', 'rating_overall' => 'SATISFECHO',
    ]), 5);
    $otherType = QualityControlSurveyWriter::create(QualityControlAffiliateType::Individual, qcFormData(['patient_document' => '7822717']), 5);

    $html = QualityControlPdfService::reportHtml(QualityControlAffiliateType::Corporate, [$low->id, $good->id, $otherType->id], 'Servicio: Telemedicina');

    expect($html)->toContain('Informe de Quality Control Corporativos')
        ->and($html)->toContain('Filtros: Servicio: Telemedicina')
        ->and($html)->toContain('Neutros e insatisfechos (1)')
        ->and($html)->toContain('Sin seguimiento')
        ->and($html)->toContain('Encuestas (2)')
        ->and($html)->toContain('50 %');
});

it('envía el PDF por correo con el adjunto, en el job', function (): void {
    Storage::fake(QualityControlPdfService::DISK);
    Mail::fake();
    $survey = QualityControlSurveyWriter::create(QualityControlAffiliateType::Corporate, qcFormData(), 5);

    (new SendQualityControlPdfJob(SendQualityControlPdfJob::CHANNEL_EMAIL, ['coordinacion@example.com'], 12345, 'CORPORATIVO', surveyId: $survey->id))->handle();

    Mail::assertSent(QualityControlPdfMail::class, fn (QualityControlPdfMail $mail): bool => $mail->hasTo('coordinacion@example.com')
        && Storage::disk(QualityControlPdfService::DISK)->exists($mail->storagePath)
        && str_ends_with($mail->attachmentName, '.pdf'));

    expect(DB::table('logs')->where('action', 'AUDIT_QUALITY_CONTROL_PDF_SENT')->count())->toBe(1);
});

it('la copia para WhatsApp solo se abre con la firma vigente', function (): void {
    Storage::fake(QualityControlPdfService::DISK);
    $share = QualityControlPdfService::shareCopy('%PDF-1.4 contenido de prueba suficientemente largo para validar');

    $this->get(URL::temporarySignedRoute('quality-control.pdf.shared', now()->addHours(48), ['share' => $share]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    $this->get(URL::temporarySignedRoute('quality-control.pdf.shared', now()->subMinute(), ['share' => $share]))->assertForbidden();
    $this->get(route('quality-control.pdf.shared', ['share' => $share]))->assertForbidden();
    $this->get(URL::temporarySignedRoute('quality-control.pdf.shared', now()->addHour(), ['share' => 'no-es-un-uuid']))->assertNotFound();
});

it('el token del informe solo vale para quien lo creó', function (): void {
    $owner = new User;
    $owner->forceFill(['id' => 71]);
    $other = new User;
    $other->forceFill(['id' => 72]);

    Auth::setUser($owner);
    $token = QualityControlReportRequest::store(QualityControlAffiliateType::Individual, [1, 2, 3], 'Filtro');

    expect(QualityControlReportRequest::find($token))->toMatchArray(['type' => 'INDIVIDUAL', 'ids' => [1, 2, 3], 'label' => 'Filtro']);

    Auth::setUser($other);

    expect(QualityControlReportRequest::find($token))->toBeNull()
        ->and(QualityControlReportRequest::find('inventado'))->toBeNull();
});

it('registra un permiso de notas por ítem y lo exige en la acción', function (): void {
    $actions = App\Support\Filament\BusinessFilamentActionPermissionRegistry::all();
    $source = file_get_contents(__DIR__.'/../../app/Filament/Operations/Resources/QualityControlSurveys/Actions/QualityControlSurveyActions.php');

    expect($actions['notas-quality-control-individuales']['modules'])->toBe(['OPERACIONES'])
        ->and($actions['notas-quality-control-corporativos']['modules'])->toBe(['OPERACIONES'])
        ->and(App\Filament\Operations\Resources\QualityControlIndividuals\QualityControlIndividualResource::noteActionSlug())->toBe('notas-quality-control-individuales')
        ->and(App\Filament\Operations\Resources\QualityControlCorporates\QualityControlCorporateResource::noteActionSlug())->toBe('notas-quality-control-corporativos')
        ->and($source)->toContain('abort_unless($resource::canAddNotes() && $resource::canViewSurvey($record), 403);')
        ->and($source)->toContain('->afterCommit()');
});
