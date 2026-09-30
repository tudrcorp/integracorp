<?php

declare(strict_types=1);

use App\Enums\OperationReportFormat;
use App\Enums\OperationReportType;
use App\Filament\Operations\Pages\GeneradorDeReportes;
use App\Jobs\GenerateOperationReportJob;
use App\Models\OperationServiceStatistic;
use App\Models\User;
use App\Support\Filament\DepartmentNavigationPermissionRegistry;
use App\Support\Filament\OperationsPanelNavigationGroups;
use App\Support\Operations\OperationsDashboardMetrics;
use App\Support\Operations\Reports\OperationReportBuilder;
use App\Support\Operations\Reports\OperationReportFilters;
use App\Support\Operations\Reports\OperationReportGenerator;
use App\Support\Operations\Reports\OperationReportWriter;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    DB::beginTransaction();
    Storage::fake(OperationReportGenerator::DISK);

    $this->usuario = User::factory()->make([
        'id' => 999_999_101,
        'name' => 'QA REPORTES',
        'email' => 'qa.reportes@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['SUPERADMIN', 'OPERACIONES'],
    ]);

    $this->actingAs($this->usuario);
    Filament::setCurrentPanel('operations');
});

afterEach(fn () => DB::rollBack());

/**
 * Periodo que cubre los datos de desarrollo.
 */
function periodoAmplio(array $extra = []): OperationReportFilters
{
    return OperationReportFilters::fromArray([
        'period' => OperationReportFilters::PERIOD_CUSTOM,
        'from' => '2020-01-01',
        'to' => now()->toDateString(),
        ...$extra,
    ]);
}

function hayEstadisticas(): bool
{
    return OperationServiceStatistic::query()->whereNotNull('started_on')->exists();
}

/*
 * ---------------------------------------------------------------------------
 * Tipos y formatos
 * ---------------------------------------------------------------------------
 */

it('la tabla completa sólo se ofrece en CSV y sin filtros', function (): void {
    expect(OperationReportType::FullTable->formats())->toBe([OperationReportFormat::Csv])
        ->and(OperationReportType::FullTable->usesFilters())->toBeFalse()
        ->and(OperationReportType::FullTable->supportsFormat(OperationReportFormat::Pdf))->toBeFalse();
});

it('los demás reportes se ofrecen en Excel, CSV y PDF', function (OperationReportType $type): void {
    expect($type->formats())->toBe([OperationReportFormat::Excel, OperationReportFormat::Csv, OperationReportFormat::Pdf]);
})->with(array_values(array_filter(OperationReportType::cases(), fn (OperationReportType $type): bool => $type !== OperationReportType::FullTable)));

/*
 * ---------------------------------------------------------------------------
 * Periodo y filtros
 * ---------------------------------------------------------------------------
 */

it('resuelve los atajos de periodo', function (string $period, string $from, string $to): void {
    $now = CarbonImmutable::parse('2026-09-17 15:30:00');
    [$desde, $hasta] = OperationReportFilters::resolvePeriod($period, null, null, $now);

    expect($desde->toDateString())->toBe($from)
        ->and($hasta->toDateString())->toBe($to);
})->with([
    'hoy' => [OperationReportFilters::PERIOD_TODAY, '2026-09-17', '2026-09-17'],
    'esta semana' => [OperationReportFilters::PERIOD_THIS_WEEK, '2026-09-14', '2026-09-17'],
    'este mes' => [OperationReportFilters::PERIOD_THIS_MONTH, '2026-09-01', '2026-09-17'],
    'mes anterior' => [OperationReportFilters::PERIOD_LAST_MONTH, '2026-08-01', '2026-08-31'],
]);

it('el mes anterior no se desborda en meses cortos', function (): void {
    [$desde, $hasta] = OperationReportFilters::resolvePeriod(
        OperationReportFilters::PERIOD_LAST_MONTH, null, null, CarbonImmutable::parse('2026-03-31'),
    );

    expect($desde->toDateString())->toBe('2026-02-01')
        ->and($hasta->toDateString())->toBe('2026-02-28');
});

it('rechaza un periodo personalizado incompleto o invertido', function (array $data, string $mensaje): void {
    expect(fn () => OperationReportFilters::fromArray(['period' => OperationReportFilters::PERIOD_CUSTOM, ...$data]))
        ->toThrow(InvalidArgumentException::class, $mensaje);
})->with([
    'sin desde' => [['to' => '2026-09-01'], 'Indique la fecha inicial'],
    'sin hasta' => [['from' => '2026-09-01'], 'Indique la fecha final'],
    'invertido' => [['from' => '2026-09-10', 'to' => '2026-09-01'], 'no puede ser posterior'],
    'fecha inválida' => [['from' => 'no-es-fecha', 'to' => '2026-09-01'], 'no es válida'],
]);

it('normaliza los filtros que llegan del formulario', function (): void {
    $filtros = periodoAmplio([
        'statuses' => ['PENDIENTE', ' PENDIENTE ', '', null, ['x']],
        'coverages' => 'Cubierto',
    ]);

    expect($filtros->statuses)->toBe(['PENDIENTE'])
        ->and($filtros->coverages)->toBe(['Cubierto'])
        ->and(OperationReportFilters::fromArray($filtros->toArray()))->toEqual($filtros);
});

/*
 * ---------------------------------------------------------------------------
 * Contenido de los reportes (datos reales, sólo lectura)
 * ---------------------------------------------------------------------------
 */

it('el conteo respeta periodo y filtros', function (): void {
    if (! hayEstadisticas()) {
        $this->markTestSkipped('No hay estadísticas de operaciones.');
    }

    $esperado = OperationsDashboardMetrics::statisticsQuery()
        ->whereNotNull('started_on')
        ->where('coverage', 'Cubierto')
        ->count();

    expect(OperationReportBuilder::count(OperationReportType::ServiceDetail, periodoAmplio(['coverages' => ['Cubierto']])))
        ->toBe($esperado);
});

it('los resúmenes suman lo mismo que el conteo de servicios', function (OperationReportType $type): void {
    if (! hayEstadisticas()) {
        $this->markTestSkipped('No hay estadísticas de operaciones.');
    }

    $filtros = periodoAmplio();
    $reporte = OperationReportBuilder::build($type, $filtros);
    $columnaServicios = array_search('Servicios', $reporte->headings, true);

    expect($reporte->totals)->not->toBeNull()
        ->and($reporte->totals[0])->toBe('TOTAL')
        ->and($reporte->totals[$columnaServicios])->toBe(OperationReportBuilder::count($type, $filtros))
        ->and(collect($reporte->rows)->sum(fn (array $row): int => (int) $row[$columnaServicios]))
        ->toBe(OperationReportBuilder::count($type, $filtros));
})->with([
    OperationReportType::ByStatus,
    OperationReportType::ByProvider,
    OperationReportType::Coverage,
    OperationReportType::Billing,
    OperationReportType::BusinessLine,
    OperationReportType::CasesByBusinessLine,
    OperationReportType::CasesByPatient,
    OperationReportType::Patients,
]);

it('los casos y pacientes del total no se duplican entre grupos', function (OperationReportType $type): void {
    if (! hayEstadisticas()) {
        $this->markTestSkipped('No hay estadísticas de operaciones.');
    }

    $filtros = periodoAmplio();
    $reporte = OperationReportBuilder::build($type, $filtros);
    $base = OperationReportBuilder::query($type, $filtros)->toBase();

    expect($reporte->totals[array_search('Casos', $reporte->headings, true)])
        ->toBe((clone $base)->distinct()->count('telemedicine_case_id'));

    if (in_array('Pacientes', $reporte->headings, true)) {
        expect($reporte->totals[array_search('Pacientes', $reporte->headings, true)])
            ->toBe((int) (clone $base)->selectRaw("COUNT(DISTINCT NULLIF(TRIM(patient_document), '')) AS total")->value('total'));
    }
})->with([
    OperationReportType::CasesByBusinessLine,
    OperationReportType::CasesByPatient,
    OperationReportType::Patients,
]);

it('pacientes trae una fila por paciente con sus datos de contacto', function (): void {
    if (! hayEstadisticas()) {
        $this->markTestSkipped('No hay estadísticas de operaciones.');
    }

    $filtros = periodoAmplio();
    $reporte = OperationReportBuilder::build(OperationReportType::Patients, $filtros);
    $pacientes = OperationReportBuilder::query(OperationReportType::Patients, $filtros)
        ->toBase()
        ->selectRaw("COALESCE(NULLIF(TRIM(patient_name), ''), 'Sin dato') AS n, COALESCE(NULLIF(TRIM(patient_document), ''), 'Sin dato') AS d")
        ->groupBy('n', 'd')
        ->get()
        ->count();

    expect($reporte->rows)->toHaveCount($pacientes)
        ->and($reporte->totalRows)->toBe($pacientes)
        ->and($reporte->headings)->toContain('Paciente', 'Cédula', 'Titular', 'Teléfono', 'Correo', 'Último servicio', 'Casos')
        ->and($reporte->pdfColumns)->toHaveCount(11)
        ->and($reporte->rows[0])->toHaveCount(count($reporte->headings));
});

it('los reportes por paciente respetan el tope del PDF', function (): void {
    if (! hayEstadisticas()) {
        $this->markTestSkipped('No hay estadísticas de operaciones.');
    }

    $reporte = OperationReportBuilder::build(OperationReportType::CasesByPatient, periodoAmplio(), rowLimit: 2);

    expect($reporte->rows)->toHaveCount(min(2, (int) $reporte->totalRows))
        ->and($reporte->totals)->not->toBeNull();
});

it('el PDF de los reportes por paciente va a la cola; los resúmenes cortos no', function (): void {
    expect(OperationReportGenerator::shouldQueue(OperationReportType::Patients, OperationReportFormat::Pdf, 1))->toBeTrue()
        ->and(OperationReportGenerator::shouldQueue(OperationReportType::CasesByPatient, OperationReportFormat::Pdf, 1))->toBeTrue()
        ->and(OperationReportGenerator::shouldQueue(OperationReportType::Patients, OperationReportFormat::Excel, 100_000))->toBeFalse()
        ->and(OperationReportGenerator::shouldQueue(OperationReportType::CasesByBusinessLine, OperationReportFormat::Pdf, 100_000))->toBeFalse();
});

it('el detalle trae una fila por servicio con sus encabezados', function (): void {
    if (! hayEstadisticas()) {
        $this->markTestSkipped('No hay estadísticas de operaciones.');
    }

    $filtros = periodoAmplio();
    $reporte = OperationReportBuilder::build(OperationReportType::ServiceDetail, $filtros);
    $filas = iterator_to_array($reporte->rows, false);

    expect(count($filas))->toBe(OperationReportBuilder::count(OperationReportType::ServiceDetail, $filtros))
        ->and($reporte->headings)->toContain('Código del caso', 'Cobertura', 'Facturado (USD)')
        ->and(count($filas[0]))->toBe(count($reporte->headings))
        ->and($filas[0][1])->toMatch('/^\d{2}\/\d{2}\/\d{4}$/');
});

it('el detalle acotado respeta el tope de filas', function (): void {
    if (! hayEstadisticas()) {
        $this->markTestSkipped('No hay estadísticas de operaciones.');
    }

    $reporte = OperationReportBuilder::build(OperationReportType::ServiceDetail, periodoAmplio(), rowLimit: 3);

    expect(iterator_to_array($reporte->rows, false))->toHaveCount(min(3, OperationReportBuilder::count(OperationReportType::ServiceDetail, periodoAmplio())));
});

it('la tabla completa trae todas las columnas y todos los registros, sin periodo', function (): void {
    $reporte = OperationReportBuilder::build(OperationReportType::FullTable, periodoAmplio(['coverages' => ['No existe']]));

    expect($reporte->headings)->toBe(OperationReportBuilder::FULL_TABLE_COLUMNS)
        ->and(iterator_to_array($reporte->rows, false))->toHaveCount(OperationsDashboardMetrics::statisticsQuery()->count());
});

it('las columnas de la tabla completa existen en la base', function (): void {
    $columnas = DB::getSchemaBuilder()->getColumnListing('operation_service_statistics');

    expect(array_diff(OperationReportBuilder::FULL_TABLE_COLUMNS, $columnas))->toBe([])
        ->and(array_diff($columnas, OperationReportBuilder::FULL_TABLE_COLUMNS))->toBe([]);
});

it('casos negados sólo trae servicios con caso negado', function (): void {
    $query = OperationReportBuilder::query(OperationReportType::DeniedCases, periodoAmplio());

    expect($query->toBase()->wheres)->toContain([
        'type' => 'Basic', 'column' => 'case_denied', 'operator' => '=', 'value' => 'SI', 'boolean' => 'and',
    ]);
});

/*
 * ---------------------------------------------------------------------------
 * Archivos
 * ---------------------------------------------------------------------------
 */

it('escribe CSV con BOM, encabezados, filas y totales', function (): void {
    $ruta = tempnam(sys_get_temp_dir(), 'op_report_').'.csv';
    $datos = new App\Support\Operations\Reports\OperationReportData(
        title: 'Prueba',
        headings: ['Estatus', 'Servicios'],
        rows: [['PENDIENTE', 3], ['FINALIZADO', 2]],
        totals: ['TOTAL', 5],
    );

    OperationReportWriter::write($datos, OperationReportFormat::Csv, $ruta);
    $contenido = (string) file_get_contents($ruta);
    @unlink($ruta);

    expect($contenido)->toStartWith("\xEF\xBB\xBF")
        ->toContain("Estatus,Servicios\nPENDIENTE,3\nFINALIZADO,2\nTOTAL,5");
});

it('escribe un Excel válido y un PDF válido', function (OperationReportFormat $format, string $firma): void {
    $ruta = tempnam(sys_get_temp_dir(), 'op_report_').'.'.$format->extension();
    $datos = new App\Support\Operations\Reports\OperationReportData(
        title: 'Prueba',
        headings: ['Servicio', 'Neto (USD)'],
        rows: [['LABORATORIOS', 10.5], ['MEDICAMENTOS', 3]],
        totals: ['TOTAL', 13.5],
        numericColumns: [1],
        criteria: ['Periodo: 01/09/2026 al 30/09/2026'],
    );

    OperationReportWriter::write($datos, $format, $ruta, 'Aviso de prueba');
    $inicio = (string) file_get_contents($ruta, length: 4);
    @unlink($ruta);

    expect($inicio)->toStartWith($firma);
})->with([
    'excel' => [OperationReportFormat::Excel, 'PK'],
    'pdf' => [OperationReportFormat::Pdf, '%PDF'],
]);

it('guarda el reporte en la carpeta privada del usuario y lo lista', function (): void {
    if (! hayEstadisticas()) {
        $this->markTestSkipped('No hay estadísticas de operaciones.');
    }

    $archivo = OperationReportGenerator::filename(OperationReportType::ByStatus, OperationReportFormat::Csv);
    $ruta = OperationReportGenerator::store(OperationReportType::ByStatus, OperationReportFormat::Csv, periodoAmplio(), 7, $archivo);

    expect($ruta)->toBe('operation-reports/7/'.$archivo)
        ->and(Storage::disk(OperationReportGenerator::DISK)->exists($ruta))->toBeTrue()
        ->and(OperationReportGenerator::exists(7, $archivo))->toBeTrue()
        ->and(OperationReportGenerator::exists(8, $archivo))->toBeFalse()
        ->and(OperationReportGenerator::recent(7)[0]['label'])->toBe('Servicios por estatus');
});

it('rechaza un formato que el reporte no admite', function (): void {
    OperationReportGenerator::store(
        OperationReportType::FullTable,
        OperationReportFormat::Pdf,
        periodoAmplio(),
        7,
        'reporte-operaciones-full-table-x.pdf',
    );
})->throws(InvalidArgumentException::class);

it('no resuelve nombres de archivo fuera del patrón', function (string $nombre): void {
    Storage::disk(OperationReportGenerator::DISK)->put('operation-reports/7/secreto.txt', 'x');

    expect(OperationReportGenerator::absolutePathFor(7, $nombre))->toBeNull();
})->with(['secreto.txt', '../7/secreto.txt', 'reporte-operaciones-a.exe', 'reporte-operaciones-../../x.csv']);

it('borra los reportes de más de 7 días', function (): void {
    $disco = Storage::disk(OperationReportGenerator::DISK);
    $viejo = 'operation-reports/7/reporte-operaciones-by-status-viejo.csv';
    $nuevo = 'operation-reports/7/reporte-operaciones-by-status-nuevo.csv';
    $disco->put($viejo, 'x');
    $disco->put($nuevo, 'x');
    touch($disco->path($viejo), now()->subDays(8)->getTimestamp());

    OperationReportGenerator::purgeExpired(7);

    expect($disco->exists($viejo))->toBeFalse()
        ->and($disco->exists($nuevo))->toBeTrue();
});

it('a la cola va el detalle grande y todo detalle en PDF; los resúmenes nunca', function (): void {
    $limite = OperationReportGenerator::SYNC_ROW_LIMIT;

    expect(OperationReportGenerator::shouldQueue(OperationReportType::ServiceDetail, OperationReportFormat::Excel, $limite + 1))->toBeTrue()
        ->and(OperationReportGenerator::shouldQueue(OperationReportType::FullTable, OperationReportFormat::Csv, $limite + 1))->toBeTrue()
        ->and(OperationReportGenerator::shouldQueue(OperationReportType::ServiceDetail, OperationReportFormat::Excel, $limite))->toBeFalse()
        ->and(OperationReportGenerator::shouldQueue(OperationReportType::ServiceDetail, OperationReportFormat::Pdf, 5))->toBeTrue()
        ->and(OperationReportGenerator::shouldQueue(OperationReportType::DeniedCases, OperationReportFormat::Pdf, 5))->toBeTrue()
        ->and(OperationReportGenerator::shouldQueue(OperationReportType::ByStatus, OperationReportFormat::Pdf, $limite * 10))->toBeFalse()
        ->and(OperationReportGenerator::shouldQueue(OperationReportType::ByStatus, OperationReportFormat::Excel, $limite * 10))->toBeFalse();
});

it('el detalle en PDF se encola y queda pendiente en la pantalla', function (): void {
    if (! hayEstadisticas()) {
        $this->markTestSkipped('No hay estadísticas de operaciones.');
    }

    Queue::fake();

    $componente = Livewire::test(GeneradorDeReportes::class)
        ->set('data.type', OperationReportType::ServiceDetail->value)
        ->set('data.period', OperationReportFilters::PERIOD_CUSTOM)
        ->set('data.from', '2020-01-01')
        ->set('data.to', now()->toDateString())
        ->set('data.format', OperationReportFormat::Pdf->value)
        ->call('generate')
        ->assertNoFileDownloaded()
        ->assertNotified('Estamos generando su reporte');

    Queue::assertPushedOn('system', GenerateOperationReportJob::class, fn (GenerateOperationReportJob $job): bool => $job->format === 'pdf'
        && $job->requestedByUserId === $this->usuario->id);

    expect($componente->get('pendingReports'))->toHaveCount(1);
});

/*
 * ---------------------------------------------------------------------------
 * Job en cola
 * ---------------------------------------------------------------------------
 */

it('el job genera el archivo y avisa en la campana con el botón de descarga', function (): void {
    $usuario = User::query()->first();

    if ($usuario === null || ! hayEstadisticas()) {
        $this->markTestSkipped('Faltan datos.');
    }

    $archivo = OperationReportGenerator::filename(OperationReportType::ServiceDetail, OperationReportFormat::Excel);
    Notification::fake();

    (new GenerateOperationReportJob(
        OperationReportType::ServiceDetail->value,
        OperationReportFormat::Excel->value,
        periodoAmplio()->toArray(),
        (int) $usuario->id,
        $archivo,
    ))->handle();

    expect(OperationReportGenerator::exists((int) $usuario->id, $archivo))->toBeTrue();

    Notification::assertSentTo(
        $usuario,
        DatabaseNotification::class,
        function (DatabaseNotification $notificacion) use ($usuario, $archivo): bool {
            $datos = json_encode($notificacion->toDatabase($usuario));

            return str_contains($datos, 'Reporte listo') && str_contains($datos, $archivo);
        },
    );
});

it('el job va a la cola system y reintenta sin duplicar archivos', function (): void {
    $job = new GenerateOperationReportJob('by_status', 'csv', [], 1, 'reporte-operaciones-by-status-x.csv');

    expect($job->queue)->toBe('system')
        ->and($job->tries)->toBe(2)
        ->and($job->filename)->toBe('reporte-operaciones-by-status-x.csv');
});

/*
 * ---------------------------------------------------------------------------
 * Pantalla
 * ---------------------------------------------------------------------------
 */

it('la página vive en el grupo REPORTES con permiso propio', function (): void {
    expect(OperationsPanelNavigationGroups::labels())->toContain('REPORTES')
        ->and(DepartmentNavigationPermissionRegistry::slugsFor(GeneradorDeReportes::class))->toBe(['generador-de-reportes'])
        ->and(GeneradorDeReportes::getNavigationGroup())->toBe('REPORTES');
});

it('muestra los reportes y el conteo de servicios', function (): void {
    Livewire::test(GeneradorDeReportes::class)
        ->assertSuccessful()
        ->assertSee('Detalle de servicios')
        ->assertSee('Tabla completa')
        ->assertSee('Mis reportes recientes');
});

it('al elegir la tabla completa el formato pasa a CSV', function (): void {
    Livewire::test(GeneradorDeReportes::class)
        ->set('data.format', OperationReportFormat::Pdf->value)
        ->set('data.type', OperationReportType::FullTable->value)
        ->assertSet('data.format', OperationReportFormat::Csv->value);
});

it('genera y descarga al instante un resumen', function (): void {
    if (! hayEstadisticas()) {
        $this->markTestSkipped('No hay estadísticas de operaciones.');
    }

    Livewire::test(GeneradorDeReportes::class)
        ->set('data.type', OperationReportType::ByStatus->value)
        ->set('data.period', OperationReportFilters::PERIOD_CUSTOM)
        ->set('data.from', '2020-01-01')
        ->set('data.to', now()->toDateString())
        ->set('data.format', OperationReportFormat::Excel->value)
        ->call('generate')
        ->assertHasNoFormErrors()
        ->assertFileDownloaded();
});

it('pide las fechas del periodo personalizado', function (): void {
    Livewire::test(GeneradorDeReportes::class)
        ->set('data.period', OperationReportFilters::PERIOD_CUSTOM)
        ->call('generate')
        ->assertHasFormErrors(['from' => 'required', 'to' => 'required']);
});

it('avisa y no descarga si no hay servicios', function (): void {
    Queue::fake();

    Livewire::test(GeneradorDeReportes::class)
        ->set('data.period', OperationReportFilters::PERIOD_CUSTOM)
        ->set('data.from', '2001-01-01')
        ->set('data.to', '2001-01-31')
        ->call('generate')
        ->assertNotified('No hay datos')
        ->assertNoFileDownloaded();

    Queue::assertNothingPushed();
});

/*
 * ---------------------------------------------------------------------------
 * Descarga
 * ---------------------------------------------------------------------------
 */

it('descarga sólo los reportes propios', function (): void {
    $disco = Storage::disk(OperationReportGenerator::DISK);
    $propio = 'reporte-operaciones-by-status-propio.csv';
    $disco->put('operation-reports/'.$this->usuario->id.'/'.$propio, 'a,b');
    $disco->put('operation-reports/123/reporte-operaciones-by-status-ajeno.csv', 'a,b');

    $this->get(route('operations.reports.download', ['file' => $propio]))->assertSuccessful()->assertDownload($propio);
    $this->get(route('operations.reports.download', ['file' => 'reporte-operaciones-by-status-ajeno.csv']))->assertNotFound();
});

it('un usuario sin acceso a Operaciones no descarga', function (): void {
    $this->actingAs(User::factory()->make([
        'id' => 999_999_102,
        'email' => 'externo@example.com',
        'status' => 'ACTIVO',
        'departament' => ['NEGOCIOS'],
    ]));

    $this->get(route('operations.reports.download', ['file' => 'reporte-operaciones-by-status-x.csv']))->assertForbidden();
});

it('un invitado es enviado a iniciar sesión', function (): void {
    auth()->logout();

    $this->get(route('operations.reports.download', ['file' => 'reporte-operaciones-by-status-x.csv']))->assertRedirect();
});
