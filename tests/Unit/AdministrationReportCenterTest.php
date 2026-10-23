<?php

declare(strict_types=1);

use App\Enums\AdministrationReportType;
use App\Enums\OperationReportFormat;
use App\Filament\Administration\Pages\CentroDeReportes;
use App\Jobs\GenerateAdministrationReportJob;
use App\Models\User;
use App\Support\Administration\Reports\AdministrationReportGenerator;
use App\Support\Administration\Reports\CollectionReportBuilder;
use App\Support\Administration\Reports\CollectionReportData;
use App\Support\Administration\Reports\CollectionReportFilters;
use App\Support\Administration\Reports\CollectionReportNetwork;
use App\Support\Administration\Reports\CollectionReportWriter;
use App\Support\Collections\CollectionReceivableReport;
use App\Support\Filament\AdministrationPanelNavigationGroups;
use App\Support\Filament\DepartmentNavigationPermissionRegistry;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/*
 * Red de prueba, toda dentro de una transacción que se revierte:
 *
 *   QA-RPT-M (master) ─┬─ QA-RPT-G1 (general) ── QA-RPT-G2 (general)
 *                      │        └─ agente A1 ── subagente S1 (registrado en QA-RPT-X)
 *                      └─ cuota directa sin agente
 *   QA-RPT-X (fuera de la red), QA-RPT-C1 ⇄ QA-RPT-C2 (ciclo)
 *
 * Las cuotas vencen en 2099 para no mezclarse con la cartera real.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    Storage::fake(AdministrationReportGenerator::DISK);

    $this->usuario = User::factory()->make([
        'id' => 999_999_201,
        'name' => 'QA CENTRO REPORTES',
        'email' => 'qa.centro.reportes@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['ADMINISTRACION'],
    ]);

    $this->actingAs($this->usuario);
    Filament::setCurrentPanel('administration');

    $agencia = fn (string $code, ?string $owner, int $type, string $name): bool => DB::table('agencies')->insert([
        'code' => $code,
        'owner_code' => $owner,
        'agency_type_id' => $type,
        'name_corporative' => $name,
        'email' => strtolower($code).'@qa.test',
    ]);

    $agencia('QA-RPT-M', 'TDG-100', CollectionReportNetwork::AGENCY_TYPE_MASTER, 'QA MASTER');
    $agencia('QA-RPT-G1', 'QA-RPT-M', CollectionReportNetwork::AGENCY_TYPE_GENERAL, 'QA GENERAL UNO');
    $agencia('QA-RPT-G2', ' qa-rpt-g1 ', CollectionReportNetwork::AGENCY_TYPE_GENERAL, 'QA GENERAL DOS');
    $agencia('QA-RPT-X', 'TDG-100', CollectionReportNetwork::AGENCY_TYPE_GENERAL, 'QA AJENA');
    $agencia('QA-RPT-C1', 'QA-RPT-C2', CollectionReportNetwork::AGENCY_TYPE_GENERAL, 'QA CICLO UNO');
    $agencia('QA-RPT-C2', 'QA-RPT-C1', CollectionReportNetwork::AGENCY_TYPE_GENERAL, 'QA CICLO DOS');

    $this->agenteId = DB::table('agents')->insertGetId([
        'name' => 'QA AGENTE UNO',
        'email' => 'qa.agente.uno@qa.test',
        'owner_code' => 'QA-RPT-G1',
        'agent_type_id' => 2,
    ]);

    $this->subagenteId = DB::table('agents')->insertGetId([
        'name' => 'QA SUBAGENTE',
        'email' => 'qa.subagente@qa.test',
        'owner_code' => 'QA-RPT-X',
        'owner_agent' => $this->agenteId,
        'agent_type_id' => CollectionReportNetwork::AGENT_TYPE_SUBAGENT,
    ]);

    $this->ajenoId = DB::table('agents')->insertGetId([
        'name' => 'QA AGENTE AJENO',
        'email' => 'qa.agente.ajeno@qa.test',
        'owner_code' => 'QA-RPT-X',
        'agent_type_id' => 2,
    ]);

    $cuota = fn (string $code, string $agencia, ?int $agente, string $vence, float $monto): bool => DB::table('collections')->insert([
        'affiliation_code' => $code,
        'affiliate_full_name' => 'TITULAR '.$code,
        'include_date' => '01/01/2099',
        'owner_code' => $agencia,
        'code_agency' => $agencia,
        'agent_id' => $agente,
        'collection_invoice_number' => 'QA-'.$code,
        'quote_number' => 'QA-'.$code,
        'persons' => 1,
        'type' => 'INDIVIDUAL',
        'status' => CollectionReceivableReport::PENDING_STATUS,
        'filter_next_payment_date' => $vence,
        'total_amount' => $monto,
    ]);

    $cuota('QA-RPT-AF-1', 'QA-RPT-M', null, '2099-01-10', 100.0);
    $cuota('QA-RPT-AF-2', 'QA-RPT-G1', $this->agenteId, '2099-01-11', 50.0);
    $cuota('QA-RPT-AF-3', 'QA-RPT-G2', null, '2099-01-12', 25.0);
    $cuota('QA-RPT-AF-4', 'QA-RPT-X', $this->subagenteId, '2099-01-13', 10.0);
    $cuota('QA-RPT-AF-5', 'QA-RPT-X', $this->ajenoId, '2099-01-14', 5.0);
    $cuota('QA-RPT-AF-6', 'QA-RPT-C1', null, '2099-02-20', 7.0);
});

afterEach(fn () => DB::rollBack());

/**
 * @param  array<string, mixed>  $extra
 */
function filtrosQa(array $extra = []): CollectionReportFilters
{
    return CollectionReportFilters::fromArray([
        'type' => AdministrationReportType::CollectionsByStructure->value,
        'period' => CollectionReportFilters::PERIOD_CUSTOM,
        'from' => '2099-01-01',
        'to' => '2099-12-31',
        ...$extra,
    ]);
}

/**
 * @return list<string>
 */
function codigosDelReporte(CollectionReportFilters $filtros): array
{
    return CollectionReportBuilder::query($filtros)->pluck('affiliation_code')->all();
}

/*
 * ---------------------------------------------------------------------------
 * Red comercial
 * ---------------------------------------------------------------------------
 */

it('la red de una agencia baja por todos los niveles y tolera códigos con espacios', function (): void {
    $red = CollectionReportNetwork::forAgency('QA-RPT-M');

    expect($red->agencyCodes)->toContain('QA-RPT-M', 'QA-RPT-G1', 'QA-RPT-G2')
        ->not->toContain('QA-RPT-X')
        ->and($red->agentIds)->toContain($this->agenteId, $this->subagenteId)
        ->not->toContain($this->ajenoId);
});

it('una agencia general solo trae su rama, no la de su master', function (): void {
    $red = CollectionReportNetwork::forAgency('QA-RPT-G2');

    expect($red->agencyCodes)->toBe(['QA-RPT-G2'])
        ->and($red->agentIds)->toBe([]);
});

it('no se cuelga con agencias que se apuntan entre sí', function (): void {
    expect(CollectionReportNetwork::forAgency('QA-RPT-C1')->agencyCodes)->toEqualCanonicalizing(['QA-RPT-C1', 'QA-RPT-C2']);
});

it('un agente trae a sus subagentes salvo que se excluyan', function (): void {
    expect(CollectionReportNetwork::forAgent($this->agenteId)->agentIds)->toBe([$this->agenteId, $this->subagenteId])
        ->and(CollectionReportNetwork::forAgent($this->agenteId, includeSubagents: false)->agentIds)->toBe([$this->agenteId])
        ->and(CollectionReportNetwork::forAgent(0)->isEmpty())->toBeTrue();
});

/*
 * ---------------------------------------------------------------------------
 * Alcance y filtros
 * ---------------------------------------------------------------------------
 */

it('por agencia trae la cobranza de toda su estructura, incluido el subagente registrado en otra agencia', function (): void {
    $codigos = codigosDelReporte(filtrosQa(['scope' => 'agency', 'agency_code' => 'QA-RPT-M']));

    expect($codigos)->toEqualCanonicalizing(['QA-RPT-AF-1', 'QA-RPT-AF-2', 'QA-RPT-AF-3', 'QA-RPT-AF-4']);
});

it('por agente trae lo suyo y lo de sus subagentes', function (): void {
    expect(codigosDelReporte(filtrosQa(['scope' => 'agent', 'agent_id' => $this->agenteId])))
        ->toEqualCanonicalizing(['QA-RPT-AF-2', 'QA-RPT-AF-4'])
        ->and(codigosDelReporte(filtrosQa(['scope' => 'agent', 'agent_id' => $this->agenteId, 'include_subagents' => false])))
        ->toBe(['QA-RPT-AF-2']);
});

it('el rango de vencimiento acota el reporte', function (): void {
    $filtros = filtrosQa(['scope' => 'agency', 'agency_code' => 'QA-RPT-C1', 'from' => '2099-02-01', 'to' => '2099-02-28']);

    expect(codigosDelReporte($filtros))->toBe(['QA-RPT-AF-6'])
        ->and(codigosDelReporte(filtrosQa(['scope' => 'agency', 'agency_code' => 'QA-RPT-C1', 'from' => '2099-03-01', 'to' => '2099-03-31'])))->toBe([]);
});

it('el reporte general trae todo y deja fuera las afiliaciones excluidas', function (): void {
    DB::table('collections')->where('affiliation_code', 'QA-RPT-AF-5')->update(['affiliate_status' => 'EXCLUIDO']);

    $codigos = codigosDelReporte(filtrosQa(['type' => AdministrationReportType::CollectionsGeneral->value]));

    expect($codigos)->toContain('QA-RPT-AF-1', 'QA-RPT-AF-6')->not->toContain('QA-RPT-AF-5');
});

it('resuelve los atajos de periodo', function (string $periodo, ?string $desde, ?string $hasta): void {
    [$from, $to] = CollectionReportFilters::resolvePeriod($periodo, null, null, CarbonImmutable::parse('2026-01-31 10:00'));

    expect($from?->toDateString())->toBe($desde)
        ->and($to?->toDateString())->toBe($hasta);
})->with([
    'este mes' => [CollectionReportFilters::PERIOD_THIS_MONTH, '2026-01-01', '2026-01-31'],
    'próximo mes sin desbordar' => [CollectionReportFilters::PERIOD_NEXT_MONTH, '2026-02-01', '2026-02-28'],
    'mes anterior' => [CollectionReportFilters::PERIOD_LAST_MONTH, '2025-12-01', '2025-12-31'],
    'toda la cartera' => [CollectionReportFilters::PERIOD_ALL, null, null],
    'próximos 15 días' => ['next_days_15', '2026-01-31', '2026-02-15'],
    'próximos 60 días' => ['next_days_60', '2026-01-31', '2026-04-01'],
    'plazo que no existe cae en este mes' => ['next_days_20', '2026-01-01', '2026-01-31'],
]);

it('rechaza datos incompletos o invertidos', function (array $datos, string $mensaje): void {
    expect(fn () => CollectionReportFilters::fromArray($datos))->toThrow(InvalidArgumentException::class, $mensaje);
})->with([
    'sin reporte' => [[], 'Seleccione el reporte'],
    'sin agencia' => [['type' => 'collections_by_structure', 'scope' => 'agency'], 'Seleccione la agencia'],
    'sin agente' => [['type' => 'collections_by_structure', 'scope' => 'agent'], 'Seleccione el agente'],
    'sin alcance' => [['type' => 'collections_by_structure'], 'por agencia o por agente'],
    'fechas invertidas' => [['type' => 'collections_general', 'period' => 'custom', 'from' => '2026-02-01', 'to' => '2026-01-01'], 'no puede ser posterior'],
    'sin desde' => [['type' => 'collections_general', 'period' => 'custom', 'to' => '2026-01-01'], 'Indique la fecha'],
]);

it('los filtros sobreviven el viaje a la cola', function (): void {
    $filtros = filtrosQa(['scope' => 'agent', 'agent_id' => $this->agenteId, 'include_subagents' => false, 'collection_status' => 'VENCIDO']);

    expect(CollectionReportFilters::fromArray($filtros->toArray()))->toEqual($filtros);
});

/*
 * ---------------------------------------------------------------------------
 * Contenido
 * ---------------------------------------------------------------------------
 */

it('por estructura agrupa por agencia y agente con subtotales y total', function (): void {
    $lineas = iterator_to_array(CollectionReportBuilder::build(filtrosQa(['scope' => 'agency', 'agency_code' => 'QA-RPT-M']))->lines, false);
    $tipos = array_column($lineas, 'kind');
    $etiquetas = implode(' | ', array_filter(array_column($lineas, 'label')));
    $total = end($lineas);

    expect(array_count_values($tipos)[CollectionReportData::LINE_ROW])->toBe(4)
        ->and($tipos[0])->toBe(CollectionReportData::LINE_AGENCY)
        ->and($etiquetas)->toContain('Agencia master: QA-RPT-M · QA MASTER')
        ->and($etiquetas)->toContain('Agencia general: QA-RPT-G1 · QA GENERAL UNO')
        ->and($etiquetas)->toContain('Agente: QA AGENTE UNO')
        ->and($etiquetas)->toContain('QA SUBAGENTE (AGT-000'.$this->subagenteId.') · Subagente')
        ->and($etiquetas)->toContain('Venta directa de la agencia (sin agente)')
        ->and($total['kind'])->toBe(CollectionReportData::LINE_TOTAL)
        ->and($total['label'])->toBe('TOTAL GENERAL · 4 afiliaciones')
        ->and($total['values'][CollectionReportBuilder::COL_INSTALLMENT_AMOUNT])->toBe(185.0);
});

it('las filas traen las mismas columnas que la tabla, con agencia y agente', function (): void {
    $lineas = iterator_to_array(CollectionReportBuilder::build(filtrosQa(['scope' => 'agent', 'agent_id' => $this->agenteId, 'include_subagents' => false]))->lines, false);
    $fila = collect($lineas)->firstWhere('kind', CollectionReportData::LINE_ROW)['values'];

    expect(CollectionReportBuilder::headings())->toHaveCount(22)
        ->and($fila)->toHaveCount(22)
        ->and($fila[CollectionReportBuilder::COL_AFFILIATION_CODE])->toBe('QA-RPT-AF-2')
        ->and($fila[CollectionReportBuilder::COL_AGENCY])->toBe('QA-RPT-G1 · QA GENERAL UNO')
        ->and($fila[CollectionReportBuilder::COL_AGENT])->toBe('QA AGENTE UNO')
        ->and($fila[CollectionReportBuilder::COL_DUE_DATE])->toBe('11/01/2099')
        ->and($fila[CollectionReportBuilder::COL_INSTALLMENT_AMOUNT])->toBe(50.0);
});

it('el reporte general es plano: filas y total, sin grupos', function (): void {
    $filtros = filtrosQa(['type' => AdministrationReportType::CollectionsGeneral->value, 'from' => '2099-01-01', 'to' => '2099-01-31']);
    $tipos = array_unique(array_column(iterator_to_array(CollectionReportBuilder::build($filtros)->lines, false), 'kind'));

    expect(array_values($tipos))->toBe([CollectionReportData::LINE_ROW, CollectionReportData::LINE_TOTAL]);
});

/*
 * ---------------------------------------------------------------------------
 * Archivos
 * ---------------------------------------------------------------------------
 */

it('el CSV trae solo filas de datos, con BOM y encabezados', function (): void {
    $ruta = Storage::disk(AdministrationReportGenerator::DISK)->path('qa.csv');
    CollectionReportWriter::write(CollectionReportBuilder::build(filtrosQa(['scope' => 'agency', 'agency_code' => 'QA-RPT-M'])), OperationReportFormat::Csv, $ruta);

    $contenido = (string) file_get_contents($ruta);
    $lineas = array_values(array_filter(explode("\n", $contenido)));

    expect($contenido)->toStartWith("\xEF\xBB\xBF")
        ->and($lineas)->toHaveCount(5)
        ->and($contenido)->not->toContain('Subtotal')
        ->and($contenido)->not->toContain('TOTAL GENERAL')
        ->and($lineas[0])->toContain('CODIGO DE AFILIACION');
});

it('escribe un Excel válido y un PDF válido', function (OperationReportFormat $formato, string $firma): void {
    $ruta = Storage::disk(AdministrationReportGenerator::DISK)->path('qa.'.$formato->extension());
    CollectionReportWriter::write(CollectionReportBuilder::build(filtrosQa(['scope' => 'agency', 'agency_code' => 'QA-RPT-M']), 1500), $formato, $ruta, 'Aviso QA');

    expect(file_get_contents($ruta, length: 4))->toBe($firma);
})->with([
    'excel' => [OperationReportFormat::Excel, "PK\x03\x04"],
    'pdf' => [OperationReportFormat::Pdf, '%PDF'],
]);

it('guarda el reporte en la carpeta privada del usuario y lo lista', function (): void {
    $archivo = AdministrationReportGenerator::filename(AdministrationReportType::CollectionsByStructure, OperationReportFormat::Excel);
    AdministrationReportGenerator::store(OperationReportFormat::Excel, filtrosQa(['scope' => 'agency', 'agency_code' => 'QA-RPT-M']), 77, $archivo);

    $recientes = AdministrationReportGenerator::recent(77);

    expect(AdministrationReportGenerator::exists(77, $archivo))->toBeTrue()
        ->and(AdministrationReportGenerator::exists(78, $archivo))->toBeFalse()
        ->and($recientes[0]['name'])->toBe($archivo)
        ->and($recientes[0]['label'])->toBe('Cobranza por mes · Por agencia o agente');
});

it('no resuelve nombres de archivo fuera del patrón', function (string $nombre): void {
    expect(AdministrationReportGenerator::absolutePathFor(1, $nombre))->toBeNull();
})->with(['../../.env', 'reporte-administracion-x.php', 'reporte-operaciones-x.csv']);

it('a la cola va el PDF de más de 300 afiliaciones y Excel o CSV de más de 2.000', function (): void {
    expect(AdministrationReportGenerator::shouldQueue(OperationReportFormat::Pdf, 300))->toBeFalse()
        ->and(AdministrationReportGenerator::shouldQueue(OperationReportFormat::Pdf, 301))->toBeTrue()
        ->and(AdministrationReportGenerator::shouldQueue(OperationReportFormat::Excel, 2000))->toBeFalse()
        ->and(AdministrationReportGenerator::shouldQueue(OperationReportFormat::Csv, 2001))->toBeTrue();
});

/*
 * ---------------------------------------------------------------------------
 * Job
 * ---------------------------------------------------------------------------
 */

it('el job genera el archivo y avisa en la campana con el botón de descarga', function (): void {
    $usuario = User::query()->first();

    if ($usuario === null) {
        $this->markTestSkipped('Faltan usuarios.');
    }

    $archivo = AdministrationReportGenerator::filename(AdministrationReportType::CollectionsByStructure, OperationReportFormat::Csv);
    Notification::fake();

    (new GenerateAdministrationReportJob('csv', filtrosQa(['scope' => 'agency', 'agency_code' => 'QA-RPT-M'])->toArray(), (int) $usuario->id, $archivo))->handle();

    expect(AdministrationReportGenerator::exists((int) $usuario->id, $archivo))->toBeTrue();

    Notification::assertSentTo($usuario, DatabaseNotification::class, function (DatabaseNotification $notificacion) use ($usuario, $archivo): bool {
        $datos = json_encode($notificacion->toDatabase($usuario));

        return str_contains($datos, 'Reporte listo') && str_contains($datos, $archivo);
    });
});

it('el job va a la cola system y reintenta sin duplicar archivos', function (): void {
    $job = new GenerateAdministrationReportJob('csv', [], 1, 'reporte-administracion-cobranza-general-x.csv');

    expect($job->queue)->toBe('system')
        ->and($job->tries)->toBe(2)
        ->and($job->filename)->toBe('reporte-administracion-cobranza-general-x.csv');
});

/*
 * ---------------------------------------------------------------------------
 * Pantalla
 * ---------------------------------------------------------------------------
 */

it('la página vive en REPORTES y la ven todos los analistas de Administración', function (): void {
    expect(AdministrationPanelNavigationGroups::labels())->toContain('REPORTES')
        ->and(CentroDeReportes::getNavigationGroup())->toBe('REPORTES')
        ->and(DepartmentNavigationPermissionRegistry::slugsFor(CentroDeReportes::class))->toBe([])
        ->and(CentroDeReportes::canAccess())->toBeTrue();

    $analistaRestringido = User::factory()->make([
        'id' => 999_999_203,
        'email' => 'qa.restringido@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['ADMINISTRACION'],
    ]);
    $analistaRestringido->setRelation('permissions', collect([
        new App\Models\Permission(['slug' => 'escritorio', 'module' => 'ADMINISTRACION', 'name' => 'Escritorio']),
    ]));
    $this->actingAs($analistaRestringido);

    expect(CentroDeReportes::canAccess())->toBeTrue()
        ->and(CentroDeReportes::shouldRegisterNavigation())->toBeTrue();
});

it('muestra los dos reportes y la lista de recientes', function (): void {
    Livewire::test(CentroDeReportes::class)
        ->assertSuccessful()
        ->assertSee('Cobranza por mes · General')
        ->assertSee('Cobranza por mes · Por agencia o agente')
        ->assertSee('Mis reportes recientes');
});

it('el buscador de agencias encuentra por código o nombre', function (): void {
    expect(CentroDeReportes::searchAgencies('QA-RPT-G'))->toHaveKeys(['QA-RPT-G1', 'QA-RPT-G2'])
        ->and(CentroDeReportes::searchAgencies('GENERAL DOS'))->toBe(['QA-RPT-G2' => 'QA-RPT-G2 · QA GENERAL DOS (General)'])
        ->and(CentroDeReportes::searchAgents('AGT-000'.$this->agenteId))->toHaveKey($this->agenteId);
});

it('pide la agencia antes de generar el reporte por estructura', function (): void {
    Livewire::test(CentroDeReportes::class)
        ->set('data.type', AdministrationReportType::CollectionsByStructure->value)
        ->set('data.scope', CollectionReportFilters::SCOPE_AGENCY)
        ->call('generate')
        ->assertHasErrors(['data.agency_code']);
});

it('genera y descarga al instante un reporte pequeño', function (): void {
    Livewire::test(CentroDeReportes::class)
        ->set('data.type', AdministrationReportType::CollectionsByStructure->value)
        ->set('data.scope', CollectionReportFilters::SCOPE_AGENCY)
        ->set('data.agency_code', 'QA-RPT-M')
        ->set('data.period', CollectionReportFilters::PERIOD_CUSTOM)
        ->set('data.from', '2099-01-01')
        ->set('data.to', '2099-12-31')
        ->set('data.format', OperationReportFormat::Csv->value)
        ->assertSee('Se incluirán')
        ->call('generate')
        ->assertFileDownloaded();

    expect(AdministrationReportGenerator::recent($this->usuario->id))->toHaveCount(1);
});

it('un PDF grande se encola y queda pendiente en la pantalla', function (): void {
    Queue::fake();

    DB::table('collections')->insert(array_map(fn (int $i): array => [
        'affiliation_code' => 'QA-RPT-MASIVA-'.$i,
        'include_date' => '01/01/2099',
        'owner_code' => 'QA-RPT-X',
        'code_agency' => 'QA-RPT-X',
        'collection_invoice_number' => 'QA-MASIVA-'.$i,
        'quote_number' => 'QA-MASIVA-'.$i,
        'persons' => 1,
        'type' => 'INDIVIDUAL',
        'status' => CollectionReceivableReport::PENDING_STATUS,
        'filter_next_payment_date' => '2099-03-15',
        'total_amount' => 1,
    ], range(1, AdministrationReportGenerator::PDF_SYNC_ROW_LIMIT + 1)));

    $componente = Livewire::test(CentroDeReportes::class)
        ->set('data.type', AdministrationReportType::CollectionsGeneral->value)
        ->set('data.period', CollectionReportFilters::PERIOD_CUSTOM)
        ->set('data.from', '2099-03-01')
        ->set('data.to', '2099-03-31')
        ->set('data.format', OperationReportFormat::Pdf->value)
        ->assertSee('se generará en segundo plano')
        ->call('generate')
        ->assertNoFileDownloaded();

    Queue::assertPushedOn('system', GenerateAdministrationReportJob::class, fn (GenerateAdministrationReportJob $job): bool => $job->format === 'pdf'
        && $job->requestedByUserId === $this->usuario->id);

    expect($componente->get('pendingReports'))->toHaveCount(1);
});

it('avisa y no descarga si no hay cuentas por cobrar', function (): void {
    Livewire::test(CentroDeReportes::class)
        ->set('data.type', AdministrationReportType::CollectionsByStructure->value)
        ->set('data.scope', CollectionReportFilters::SCOPE_AGENCY)
        ->set('data.agency_code', 'QA-RPT-G2')
        ->set('data.period', CollectionReportFilters::PERIOD_CUSTOM)
        ->set('data.from', '2099-06-01')
        ->set('data.to', '2099-06-30')
        ->call('generate')
        ->assertNotified('No hay datos')
        ->assertNoFileDownloaded();
});

/*
 * ---------------------------------------------------------------------------
 * Descarga
 * ---------------------------------------------------------------------------
 */

it('descarga solo los reportes propios', function (): void {
    $disco = Storage::disk(AdministrationReportGenerator::DISK);
    $propio = 'reporte-administracion-cobranza-general-propio.csv';
    $disco->put('administration-reports/'.$this->usuario->id.'/'.$propio, 'a,b');
    $disco->put('administration-reports/123/reporte-administracion-cobranza-general-ajeno.csv', 'a,b');

    $this->get(route('administration.reports.download', ['file' => $propio]))->assertSuccessful()->assertDownload($propio);
    $this->get(route('administration.reports.download', ['file' => 'reporte-administracion-cobranza-general-ajeno.csv']))->assertNotFound();
});

it('un usuario sin acceso a Administración no descarga', function (): void {
    $this->actingAs(User::factory()->make([
        'id' => 999_999_202,
        'email' => 'externo@example.com',
        'status' => 'ACTIVO',
        'departament' => ['NEGOCIOS'],
    ]));

    $this->get(route('administration.reports.download', ['file' => 'reporte-administracion-cobranza-general-x.csv']))->assertForbidden();
});

it('un invitado es enviado a iniciar sesión', function (): void {
    auth()->logout();

    $this->get(route('administration.reports.download', ['file' => 'reporte-administracion-cobranza-general-x.csv']))->assertRedirect();
});

it('ofrece los mismos plazos del resumen de Cuentas por cobrar y cuadra con ellos', function (): void {
    $summary = CollectionReceivableReport::summary(\App\Filament\Administration\Resources\AnnualCollections\AnnualCollectionResource::getEloquentQuery());

    foreach (array_keys(CollectionReceivableReport::DUE_WINDOWS) as $days) {
        $period = CollectionReportFilters::nextDaysPeriod($days);

        expect(CollectionReportFilters::periodOptions())->toHaveKey($period, 'Próximos '.$days.' días');

        $filters = CollectionReportFilters::fromArray([
            'type' => AdministrationReportType::CollectionsGeneral->value,
            'period' => $period,
            'collection_status' => CollectionReportFilters::STATUS_ALL,
        ]);

        // El reporte es una fila por afiliación (su próxima cuota): nunca más que las cuotas del plazo.
        expect(CollectionReportBuilder::count($filters))->toBeLessThanOrEqual($summary['due_windows'][$days]['count']);
    }

    expect(CollectionReportFilters::periodOptions())->toHaveKey('next_days_15');

    Livewire::test(CentroDeReportes::class)
        ->assertSee('Próximos 15 días')
        ->set('data.period', 'next_days_15')
        ->assertSee('Se incluirán');
});
