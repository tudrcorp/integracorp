<?php

declare(strict_types=1);

use App\Filament\Administration\Pages\InformeAdministrativo;
use App\Filament\Administration\Resources\AnnualCollections\AnnualCollectionResource;
use App\Models\User;
use App\Models\WhiteCompany;
use App\Support\Administration\Insights\AdministrativeReportBuilder;
use App\Support\Administration\Insights\AdministrativeReportFilters;
use App\Support\Administration\Insights\AdministrativeReportPdf;
use App\Support\Administration\Insights\SvgChart;
use App\Support\Administration\Reports\AdministrationReportGenerator;
use App\Support\Collections\CollectionReceivableReport;
use App\Support\Collections\DroppedAffiliationCollections;
use App\Support\Filament\DepartmentNavigationPermissionRegistry;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Informe administrativo: periodo y alcance, cifras que cuadran con las demás pantallas,
 * gráficos SVG, página con permiso propio y PDF al instante. Solo lee la base.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    Filament::setCurrentPanel('administration');
    Auth::setUser(User::factory()->make([
        'id' => 1,
        'email' => 'prueba@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['SUPERADMIN', 'ADMINISTRACION'],
        'is_superAdmin' => true,
    ]));
});

afterEach(fn () => DB::rollBack());

function adminReport(array $data = []): array
{
    return (new AdministrativeReportBuilder(AdministrativeReportFilters::fromArray(['period' => 'this_year', ...$data])))->build();
}

/**
 * @return array<string, mixed>
 */
function adminSection(array $report, string $key): array
{
    return collect($report['sections'])->firstWhere('key', $key);
}

it('resuelve los periodos y rechaza los inválidos', function (string $period, string $from, string $to): void {
    [$start, $end] = AdministrativeReportFilters::resolvePeriod($period, null, null, CarbonImmutable::parse('2026-10-23 10:00'));

    expect($start->toDateString())->toBe($from)->and($end->toDateString())->toBe($to);
})->with([
    'este mes' => ['this_month', '2026-10-01', '2026-10-31'],
    'mes anterior' => ['last_month', '2026-09-01', '2026-09-30'],
    'este año' => ['this_year', '2026-01-01', '2026-12-31'],
    'año anterior' => ['last_year', '2025-01-01', '2025-12-31'],
    'últimos 12 meses' => ['last_12_months', '2025-11-01', '2026-10-31'],
]);

it('exige el dato del alcance y acota el periodo', function (array $data, string $message): void {
    expect(fn () => AdministrativeReportFilters::fromArray($data))->toThrow(InvalidArgumentException::class, $message);
})->with([
    'agencia sin elegir' => [['scope' => 'agency'], 'Seleccione la agencia'],
    'agente sin elegir' => [['scope' => 'agent'], 'Seleccione el agente'],
    'empresa aliada sin elegir' => [['scope' => 'white_company'], 'Seleccione la empresa aliada'],
    'fechas invertidas' => [['period' => 'custom', 'from' => '2026-05-01', 'to' => '2026-01-01'], 'no puede ser posterior'],
    'más de tres años' => [['period' => 'custom', 'from' => '2020-01-01', 'to' => '2026-01-01'], 'como máximo'],
]);

it('el periodo anterior tiene la misma cantidad de meses', function (): void {
    [$from, $to] = AdministrativeReportFilters::fromArray(['period' => 'custom', 'from' => '2026-04-01', 'to' => '2026-06-30'])->previousPeriod();

    expect($from->toDateString())->toBe('2026-01-01')->and($to->toDateString())->toBe('2026-03-31');
});

it('trae las cuatro secciones y lo cobrado cuadra con las ventas registradas', function (): void {
    $report = adminReport();
    $year = CarbonImmutable::today();
    $sales = (float) DB::table('sales')->whereBetween('created_at', [$year->startOfYear(), $year->endOfYear()])->sum('total_amount');

    expect(array_column($report['sections'], 'key'))->toBe(['summary', 'sales', 'receivables', 'dropouts'])
        ->and(adminSection($report, 'summary')['kpis'][0]['value'])->toBe(AdministrativeReportBuilder::money($sales))
        ->and(adminSection($report, 'sales')['kpis'][0]['value'])->toBe(AdministrativeReportBuilder::money($sales));
});

it('la cobranza cuadra con Cuentas por cobrar y las bajas con su bandeja', function (): void {
    $report = adminReport();
    $receivable = CollectionReceivableReport::summary(AnnualCollectionResource::getEloquentQuery());
    $undecided = DroppedAffiliationCollections::applyReviewFilter(DroppedAffiliationCollections::reviewQuery(), DroppedAffiliationCollections::REVIEW_UNDECIDED);

    expect(adminSection($report, 'receivables')['kpis'][0]['value'])->toBe(AdministrativeReportBuilder::money($receivable['pending_amount']))
        ->and(adminSection($report, 'receivables')['kpis'][1]['value'])->toBe(AdministrativeReportBuilder::money($receivable['overdue_amount']))
        ->and(adminSection($report, 'dropouts')['kpis'][1]['value'])->toBe(AdministrativeReportBuilder::money((float) $undecided->sum('collections.total_amount')));
});

it('acotado a una agencia no supera a toda la empresa y uno sin red no trae nada', function (): void {
    $code = DB::table('sales')->whereNotNull('code_agency')->where('code_agency', '!=', '')->value('code_agency');

    if ($code === null) {
        $this->markTestSkipped('No hay ventas con agencia.');
    }

    $amount = static fn (array $report): float => (float) str_replace(['US$ ', '.', ','], ['', '', '.'], adminSection($report, 'sales')['kpis'][0]['value']);

    expect($amount(adminReport(['scope' => 'agency', 'agency_code' => $code])))->toBeLessThanOrEqual($amount(adminReport()))
        ->and($amount(adminReport(['scope' => 'agency', 'agency_code' => 'NO-EXISTE-QA'])))->toBe(0.0)
        ->and(adminReport(['scope' => 'agency', 'agency_code' => $code])['scope'])->toContain($code);
});

it('acota por empresa aliada con su descendencia', function (): void {
    $company = WhiteCompany::query()->first();

    if (! $company instanceof WhiteCompany) {
        $this->markTestSkipped('No hay empresas aliadas.');
    }

    $report = adminReport(['scope' => 'white_company', 'white_company_id' => $company->id]);

    expect($report['scope'])->toContain('Empresa aliada')
        ->and(array_column($report['sections'], 'key'))->toHaveCount(4);
});

it('las barras de bajas suman el total del periodo', function (): void {
    $section = adminSection(adminReport(), 'dropouts');
    $byStatusTotal = (int) str_replace('.', '', $section['blocks'][0]['table']['total'][1] ?? '0');

    expect((int) str_replace('.', '', $section['kpis'][0]['value']))->toBe($byStatusTotal);
});

it('dibuja barras, rankings y dona en SVG con su valor al pasar el mouse', function (): void {
    $bars = SvgChart::bars(['ene', 'feb'], [10, 30]);
    $donut = SvgChart::donut([1, 3], ['A', 'B']);

    expect($bars)->toStartWith('<svg')->toContain('<title>feb: 30 (75,0 %)</title>')
        ->and(SvgChart::horizontalBars(['TDG-1'], [5]))->toContain('<title>TDG-1: 5</title>')
        ->and($donut)->toContain('<path')->toContain('75,0 %')
        ->and(SvgChart::donut([0, 0]))->toContain('Sin datos')
        ->and(SvgChart::dataUri($bars))->toStartWith('data:image/svg+xml;base64,');
});

it('la página tiene permiso propio y solo genera el PDF, sin vista en pantalla', function (): void {
    expect(DepartmentNavigationPermissionRegistry::slugsFor(InformeAdministrativo::class))->toBe(['informe-administrativo'])
        ->and(DepartmentNavigationPermissionRegistry::moduleFor(InformeAdministrativo::class))->toBe('ADMINISTRACION');

    Livewire::test(InformeAdministrativo::class)
        ->assertSuccessful()
        ->assertSee('Generar PDF')
        ->assertSee('Mis reportes recientes')
        ->assertDontSee('Ver informe')
        ->assertDontSee('RESUMEN GENERAL')
        ->assertDontSee('<svg xmlns', false);
});

it('quien no tiene el permiso no entra', function (): void {
    Auth::setUser(User::factory()->make(['id' => 99999, 'email' => 'otro@tudrencasa.com', 'status' => 'ACTIVO', 'departament' => ['ADMINISTRACION'], 'is_superAdmin' => false]));

    expect(InformeAdministrativo::canAccess())->toBeFalse();
});

it('Generar PDF lo descarga al instante, sin cola, y deja la copia en recientes', function (): void {
    Storage::fake(AdministrationReportGenerator::DISK);
    Queue::fake();

    $started = microtime(true);

    Livewire::test(InformeAdministrativo::class)
        ->set('data.period', 'last_month')
        ->call('downloadPdf')
        ->assertFileDownloaded();

    $files = Storage::disk(AdministrationReportGenerator::DISK)->files(AdministrationReportGenerator::userDirectory(1));

    expect(microtime(true) - $started)->toBeLessThan(5.0)
        ->and($files)->toHaveCount(1)
        ->and(basename($files[0]))->toMatch(AdministrationReportGenerator::FILENAME_PATTERN)
        ->and(Storage::disk(AdministrationReportGenerator::DISK)->get($files[0]))->toStartWith('%PDF');

    Queue::assertNothingPushed();
});

it('con un alcance incompleto avisa y no descarga nada', function (): void {
    Storage::fake(AdministrationReportGenerator::DISK);

    Livewire::test(InformeAdministrativo::class)
        ->set('data.scope', 'agency')
        ->call('downloadPdf')
        ->assertNoFileDownloaded();

    expect(Storage::disk(AdministrationReportGenerator::DISK)->allFiles())->toBe([]);
});

it('el PDF se genera por su vía real y lleva todas las secciones', function (): void {
    $pdf = AdministrativeReportPdf::render(adminReport(['period' => 'this_month']));

    expect($pdf)->toStartWith('%PDF')
        ->and(strlen($pdf))->toBeGreaterThan(20000)
        ->and(AdministrationReportGenerator::labelFor(AdministrativeReportPdf::filename()))->toBe('Informe administrativo');
});

it('el PDF fluye sin saltos de página forzados ni fondo gris que deje huecos', function (): void {
    $root = dirname(__DIR__, 2).'/resources/views/documents';
    $styles = (string) file_get_contents($root.'/partials/administrative-report-styles.blade.php');
    $body = (string) file_get_contents($root.'/partials/administrative-report-body.blade.php');
    $table = (string) file_get_contents($root.'/partials/administrative-report-table.blade.php');

    expect($styles)->not->toContain('page-break-before')
        ->not->toContain('#EEF1F5')
        ->toContain('.adm-keep { page-break-inside: avoid; }')
        ->toContain('display: table-header-group')
        ->and($table)->not->toContain('adm-keep')
        ->and(substr_count($body, 'class="adm-keep"'))->toBe(2);
});

it('más de 12 meses se agrupa por trimestre', function (): void {
    $report = adminReport(['period' => 'custom', 'from' => '2024-01-01', 'to' => '2026-10-31']);
    $summary = adminSection($report, 'summary');

    expect($summary['blocks'][0]['title'])->toBe('Cobrado por trimestre')
        ->and($summary['blocks'][0]['table']['headings'])->toContain('T1 24', 'T4 26')
        ->and(count($summary['blocks'][0]['table']['headings']))->toBe(1 + 12 + 1)
        ->and(adminSection(adminReport(), 'summary')['blocks'][0]['title'])->toBe('Cobrado por mes');
});
