<?php

declare(strict_types=1);

use App\Support\Metrics\System\SystemEvolutionComparison;
use App\Support\Metrics\System\SystemEvolutionReport;
use App\Support\Metrics\System\SystemGitHistory;
use App\Support\Metrics\System\SystemMigrationAnalyzer;
use App\Support\Metrics\System\SystemModuleCatalog;
use Carbon\CarbonImmutable;

/**
 * Pruebas puras (sin aplicación ni base de datos) de la sección Sistema del
 * panel de Métricas: clasificación, lectura de git, migraciones y comparación.
 */
function commitSistemaQa(string $date, array $files, string $author = 'QA', string $subject = 'Cambio QA'): array
{
    return [
        'hash' => str_pad(dechex(crc32($date.$subject.json_encode($files))), 40, 'a'),
        'date' => $date,
        'author' => $author,
        'subject' => $subject,
        'files' => array_map(static fn (array $file): array => $file + ['binary' => false, 'status' => 'modified'], $files),
    ];
}

/**
 * @return array<string, mixed>
 */
function historialSistemaQa(): array
{
    $migrations = [
        'database/migrations/2031_03_05_120000_create_qa_operation_invoices_table.php' => <<<'PHP'
            <?php
            return new class SystemEvolutionMetricsTest Migration {
                public function up(): void
                {
                    Schema::create('qa_operation_invoices', function (Blueprint $table) {
                        $table->id();
                        $table->string('code')->unique();
                        $table->foreignId('supplier_id')->constrained();
                        $table->timestamps();
                    });
                }
                public function down(): void
                {
                    Schema::dropIfExists('qa_operation_invoices');
                }
            };
            PHP,
    ];

    $commits = [
        commitSistemaQa('2031-01-10T10:00:00-04:00', [['path' => 'app/Filament/Business/Resources/Affiliations/AffiliationResource.php', 'added' => 100, 'deleted' => 10, 'status' => 'created']]),
        commitSistemaQa('2031-02-03T10:00:00-04:00', [['path' => 'app/Support/Telemedicine/CaseGuard.php', 'added' => 40, 'deleted' => 0, 'status' => 'created']]),
        commitSistemaQa('2031-02-20T10:00:00-04:00', [['path' => 'app/Support/Telemedicine/CaseGuard.php', 'added' => 10, 'deleted' => 5]]),
        commitSistemaQa('2031-03-02T09:00:00-04:00', [
            ['path' => 'app/Filament/Operations/Resources/Suppliers/SupplierResource.php', 'added' => 200, 'deleted' => 20, 'status' => 'created'],
            ['path' => 'tests/Unit/SupplierQaTest.php', 'added' => 30, 'deleted' => 0, 'status' => 'created'],
            ['path' => 'vendor/acme/lib/Big.php', 'added' => 9000, 'deleted' => 0, 'status' => 'created'],
            ['path' => 'package-lock.json', 'added' => 500, 'deleted' => 500],
        ], 'Ana', 'Proveedores'),
        commitSistemaQa('2031-03-05T15:00:00-04:00', [
            ['path' => 'database/migrations/2031_03_05_120000_create_qa_operation_invoices_table.php', 'added' => 20, 'deleted' => 0, 'status' => 'created'],
        ], 'Luis', 'Facturas'),
        commitSistemaQa('2031-03-25T15:00:00-04:00', [
            ['path' => 'app/Filament/Operations/Resources/Suppliers/SupplierResource.php', 'added' => 5, 'deleted' => 50],
            ['path' => 'app/Support/Telemedicine/CaseGuard.php', 'added' => 0, 'deleted' => 55, 'status' => 'deleted'],
        ], 'Ana', 'Limpieza'),
    ];

    return SystemEvolutionReport::aggregate(
        $commits,
        [
            'app/Filament/Business/Resources/Affiliations/AffiliationResource.php',
            'app/Filament/Operations/Resources/Suppliers/SupplierResource.php',
            'tests/Unit/SupplierQaTest.php',
            'database/migrations/2031_03_05_120000_create_qa_operation_invoices_table.php',
            'vendor/acme/lib/Big.php',
        ],
        static fn (string $path): ?string => $migrations[$path] ?? null,
    );
}

it('clasifica rutas en módulos de negocio y capas técnicas', function (string $path, string $module, string $layer): void {
    expect(SystemModuleCatalog::moduleFor($path))->toBe($module)
        ->and(SystemModuleCatalog::layerFor($path))->toBe($layer);
})->with([
    'telemedicina' => ['app/Support/Telemedicine/TelemedicineCaseDischargeGuard.php', 'telemedicina', 'reglas'],
    'operaciones por palabra' => ['app/Models/OperationServiceOrder.php', 'operaciones', 'datos'],
    'panel mixto no decide si otra palabra coincide' => ['app/Filament/Operations/Resources/Affiliates/AffiliateResource.php', 'afiliaciones', 'pantallas'],
    'panel mixto decide al final' => ['app/Filament/Administration/Pages/Escritorio.php', 'administracion', 'pantallas'],
    'cotizaciones antes que red comercial' => ['app/Filament/Agents/Resources/IndividualQuotes/IndividualQuoteResource.php', 'cotizaciones', 'pantallas'],
    'planes por palabra completa' => ['app/Support/Plans/PlanStructurePersistence.php', 'planes', 'reglas'],
    'fee no confunde feedback' => ['app/Models/GuiaChatFeedback.php', 'chat', 'datos'],
    'migración en snake_case' => ['database/migrations/2026_11_05_create_operation_service_invoices_table.php', 'operaciones', 'base_de_datos'],
    'pruebas' => ['tests/Unit/ClinicalEntitlementsTest.php', 'telemedicina', 'pruebas'],
    'métricas' => ['app/Filament/Metrics/Pages/Sistema.php', 'metricas', 'pantallas'],
    'sin módulo' => ['app/Support/UrlPathEncoder.php', 'plataforma', 'reglas'],
    'vistas' => ['resources/views/documents/propuesta-economica.blade.php', 'cotizaciones', 'vistas'],
]);

it('descarta archivos generados y de terceros', function (string $path, bool $generated): void {
    expect(SystemModuleCatalog::isGenerated($path))->toBe($generated);
})->with([
    ['vendor/laravel/framework/src/Foo.php', true],
    ['public/build/assets/app.js', true],
    ['composer.lock', true],
    ['package-lock.json', true],
    ['resources/js/app.min.js', true],
    ['app/Models/User.php', false],
    ['composer.json', false],
]);

it('clasifica tablas de base de datos por módulo', function (): void {
    expect(SystemModuleCatalog::moduleForTable('telemedicine_cases'))->toBe('telemedicina')
        ->and(SystemModuleCatalog::moduleForTable('affiliation_corporates'))->toBe('afiliaciones')
        ->and(SystemModuleCatalog::moduleForTable('users'))->toBe('usuarios')
        ->and(SystemModuleCatalog::moduleForTable('jobs'))->toBe('plataforma');
});

it('interpreta la salida de git log con numstat, binarios, creaciones y eliminaciones', function (): void {
    $hashA = str_repeat('a', 40);
    $hashB = str_repeat('b', 40);
    $output = "@@{$hashA}\x1f2031-03-02T09:00:00-04:00\x1fAna\x1fPrimer cambio\n\n"
        ."10\t2\tapp/Models/Foo.php\n"
        ."-\t-\tpublic/image/logo.png\n"
        ."3\t0\tapp/Models/Bar.php\n"
        ." create mode 100644 app/Models/Bar.php\n"
        ." create mode 100644 public/image/logo.png\n"
        ."@@{$hashB}\x1f2031-03-03T09:00:00-04:00\x1fLuis\x1fSegundo | con separador\n\n"
        ."0\t8\tapp/Models/Old.php\n"
        ." delete mode 100644 app/Models/Old.php\n"
        ."@@no-es-un-hash\x1fx\x1fy\x1fz\n"
        ."1\t1\tapp/Ignorar.php\n";

    $commits = SystemGitHistory::parseLog($output);

    expect($commits)->toHaveCount(2)
        ->and($commits[0]['author'])->toBe('Ana')
        ->and($commits[0]['files'])->toHaveCount(3)
        ->and($commits[0]['files'][0])->toMatchArray(['path' => 'app/Models/Foo.php', 'added' => 10, 'deleted' => 2, 'status' => 'modified'])
        ->and($commits[0]['files'][1])->toMatchArray(['binary' => true, 'added' => 0, 'status' => 'created'])
        ->and($commits[0]['files'][2]['status'])->toBe('created')
        ->and($commits[1]['subject'])->toBe('Segundo | con separador')
        ->and($commits[1]['files'][0]['status'])->toBe('deleted');
});

it('resume una migración: tablas nuevas, modificadas, columnas e índices solo de up()', function (): void {
    $code = <<<'PHP'
        public function up(): void
        {
            Schema::create('qa_items', function (Blueprint $table) {
                $table->id();
                $table->string('name')->index();
                $table->foreignId('plan_id')->constrained();
                $table->timestamps();
            });
            Schema::table('plans', function (Blueprint $table) {
                $table->boolean('is_qa')->default(false);
            });
            if (! Schema::hasTable('qa_other')) {}
        }
        public function down(): void
        {
            Schema::dropIfExists('qa_items');
            Schema::table('plans', fn ($table) => $table->dropColumn('is_qa'));
        }
        PHP;

    $result = SystemMigrationAnalyzer::analyze('2031_03_01_000000_create_qa_items_table.php', $code);

    expect($result['created'])->toBe(['qa_items'])
        ->and($result['altered'])->toBe(['plans'])
        ->and($result['dropped'])->toBe([])
        ->and($result['columns'])->toBe(6)
        ->and($result['indexes'])->toBe(2)
        ->and(SystemMigrationAnalyzer::label('2031_03_01_000000_add_color_to_plans_table.php'))->toBe('Agrega color a plans')
        ->and(SystemMigrationAnalyzer::label('2031_03_01_000000_fix_something_weird.php'))->toBe('Fix something weird');
});

it('agrega el historial por mes sin contar archivos generados', function (): void {
    $history = historialSistemaQa();
    $march = $history['months']['2031-03'];

    expect(array_keys($history['months']))->toBe(['2031-01', '2031-02', '2031-03'])
        ->and($march['commits'])->toBe(3)
        ->and($march['added'])->toBe(255)
        ->and($march['deleted'])->toBe(125)
        ->and($march['files_touched'])->toBe(4)
        ->and($march['files_created'])->toBe(3)
        ->and($march['files_deleted'])->toBe(1)
        ->and($march['active_days'])->toBe(3)
        ->and($march['tests_created'])->toBe(1)
        ->and($march['tables_created'])->toBe(1)
        ->and($march['columns'])->toBe(5)
        ->and($march['indexes'])->toBe(2)
        ->and($march['migrations'][0]['module'])->toBe('operaciones')
        ->and($march['modules']['operaciones']['commits'])->toBe(3)
        ->and($march['modules']['telemedicina']['deleted'])->toBe(55)
        ->and($march['authors'])->toBe(['Ana' => 2, 'Luis' => 1])
        ->and($march['top_commits'][0]['subject'])->toBe('Proveedores')
        ->and($history['inventory']['resources'])->toBe(2)
        ->and($history['inventory']['tests'])->toBe(1)
        ->and($history['total_files'])->toBe(4)
        ->and($history['total_lines'])->toBe(100 - 10 + 40 + 10 - 5 + 255 - 125);
});

it('compara el mes elegido con los anteriores y reconstruye el crecimiento', function (): void {
    $report = SystemEvolutionComparison::build(historialSistemaQa(), '2031-03', 3, [], CarbonImmutable::parse('2031-05-10'));

    $commits = collect($report['kpis'])->firstWhere('key', 'commits');
    $resources = collect($report['inventory'])->firstWhere('key', 'resources');

    expect($report['inProgress'])->toBeFalse()
        ->and($report['monthLabel'])->toBe('Marzo 2031')
        ->and($report['previousLabel'])->toBe('Febrero 2031')
        ->and($commits['value'])->toBe(3)
        ->and($commits['previous'])->toBe(2)
        ->and($commits['average'])->toBe(1.0)
        ->and($commits['vsPrevious']['text'])->toBe('+50 %')
        ->and($commits['vsAverage']['text'])->toBe('+200 %')
        ->and($commits['projection'])->toBeNull()
        ->and(array_column($report['series'], 'commits'))->toBe([0, 1, 2, 3])
        ->and(array_column($report['series'], 'lines_total'))->toBe([0, 90, 135, 265])
        ->and($resources['end_of_month'])->toBe(2)
        ->and($resources['created'])->toBe(1)
        ->and($resources['series'])->toBe([0, 1, 1, 2])
        ->and($report['modules']['rows'][0]['key'])->toBe('operaciones')
        ->and($report['table']['columns'])->toHaveCount(4)
        ->and($report['insights'][0]['text'])->toContain('3 entregas de código')
        ->and($report['migrations'][0]['created'])->toBe(['qa_operation_invoices']);
});

it('en un mes en curso compara a la misma fecha y proyecta el cierre', function (): void {
    $report = SystemEvolutionComparison::build(historialSistemaQa(), '2031-03', 3, [], CarbonImmutable::parse('2031-03-05 18:00'));
    $commits = collect($report['kpis'])->firstWhere('key', 'commits');

    expect($report['inProgress'])->toBeTrue()
        ->and($report['cutDay'])->toBe(5)
        ->and($commits['value'])->toBe(2)
        ->and($commits['previous'])->toBe(1)
        ->and($commits['projection'])->toBe(12)
        ->and($report['insights'][0]['text'])->toContain('del 1 al 5')
        ->and($report['series'][3]['partial'])->toBeTrue();
});

it('normaliza mes y ventana inválidos y no admite meses futuros', function (): void {
    $today = CarbonImmutable::parse('2031-03-15');

    expect(SystemEvolutionComparison::normalizeMonth('2031-13', $today))->toBe('2031-03')
        ->and(SystemEvolutionComparison::normalizeMonth('2032-01', $today))->toBe('2031-03')
        ->and(SystemEvolutionComparison::normalizeMonth("2031-01'; DROP", $today))->toBe('2031-03')
        ->and(SystemEvolutionComparison::normalizeMonth('2030-11', $today))->toBe('2030-11')
        ->and(SystemEvolutionComparison::normalizeWindow(7))->toBe(SystemEvolutionComparison::DEFAULT_WINDOW)
        ->and(SystemEvolutionComparison::normalizeWindow('12'))->toBe(12)
        ->and(SystemEvolutionComparison::monthOptions(historialSistemaQa(), $today))->toBe([
            '2031-03' => 'Marzo 2031 (en curso)',
            '2031-02' => 'Febrero 2031',
            '2031-01' => 'Enero 2031',
        ]);
});

it('un mes sin entregas no falla y lo dice en el resumen', function (): void {
    $report = SystemEvolutionComparison::build(historialSistemaQa(), '2030-06', 6, [], CarbonImmutable::parse('2031-05-01'));

    expect($report['hasData'])->toBeFalse()
        ->and($report['modules']['rows'])->toBe([])
        ->and($report['insights'][0]['text'])->toContain('todavía no hay entregas');
});

it('compara de frente con el mes anterior: indicadores, diferencia y módulos', function (): void {
    $comparison = SystemEvolutionComparison::build(historialSistemaQa(), '2031-03', 3, [], CarbonImmutable::parse('2031-05-10'))['previousMonth'];
    $rows = collect($comparison['rows'])->keyBy('key');
    $modules = collect($comparison['modules'])->keyBy('key');

    expect($comparison['currentLabel'])->toBe('Marzo 2031')
        ->and($comparison['previousLabel'])->toBe('Febrero 2031')
        ->and($rows['commits'])->toMatchArray(['current' => 3, 'previous' => 2, 'difference' => 1, 'currentBar' => 100, 'previousBar' => 67])
        ->and($rows['commits']['delta']['text'])->toBe('+50 %')
        ->and($rows['added'])->toMatchArray(['current' => 255, 'previous' => 50])
        ->and($rows['net'])->toMatchArray(['current' => 130, 'previous' => 45])
        ->and($rows['files_deleted'])->toMatchArray(['current' => 1, 'previous' => 0])
        ->and($rows['files_deleted']['delta']['text'])->toBe('Nuevo')
        ->and($rows['tables_created'])->toMatchArray(['current' => 1, 'previous' => 0])
        ->and($comparison['modules'][0]['key'])->toBe('operaciones')
        ->and($modules['operaciones'])->toMatchArray(['current' => 325, 'previous' => 0, 'currentBar' => 100])
        ->and($modules['telemedicina'])->toMatchArray(['current' => 55, 'previous' => 55])
        ->and($modules['telemedicina']['delta']['direction'])->toBe('flat')
        ->and($modules->has('afiliaciones'))->toBeFalse()
        ->and($comparison['newModules'])->toBe(['Operaciones y proveedores'])
        ->and($comparison['pausedModules'])->toBe([])
        ->and($comparison['improved'] + $comparison['declined'] + $comparison['same'])->toBe(count($comparison['rows']));
});

it('con el mes en curso compara con el mes anterior cortado el mismo día', function (): void {
    $comparison = SystemEvolutionComparison::build(historialSistemaQa(), '2031-03', 3, [], CarbonImmutable::parse('2031-03-05 18:00'))['previousMonth'];
    $rows = collect($comparison['rows'])->keyBy('key');
    $modules = collect($comparison['modules'])->keyBy('key');

    expect($comparison['inProgress'])->toBeTrue()
        ->and($comparison['currentLabel'])->toBe('Marzo 2031 (al día 5)')
        ->and($rows['commits'])->toMatchArray(['current' => 2, 'previous' => 1, 'previousFull' => 2])
        ->and($rows['added'])->toMatchArray(['current' => 250, 'previous' => 40, 'previousFull' => 50])
        ->and($rows['modules'])->toMatchArray(['current' => 1, 'previous' => 1, 'previousFull' => 1])
        ->and($rows['columns'])->toMatchArray(['current' => 5, 'previous' => 0])
        ->and($modules['operaciones'])->toMatchArray(['current' => 270, 'previous' => 0])
        ->and($modules['telemedicina'])->toMatchArray(['current' => 0, 'previous' => 40])
        ->and($comparison['pausedModules'])->toBe(['Telemedicina y cupos clínicos']);
});

it('sin actividad en ninguno de los dos meses la comparativa queda vacía y en cero', function (): void {
    $comparison = SystemEvolutionComparison::build(historialSistemaQa(), '2030-06', 3, [], CarbonImmutable::parse('2031-05-01'))['previousMonth'];

    expect($comparison['modules'])->toBe([])
        ->and(collect($comparison['rows'])->every(fn (array $row): bool => $row['current'] === 0 && $row['previous'] === 0 && $row['delta']['direction'] === 'flat'))->toBeTrue()
        ->and($comparison['same'])->toBe(count($comparison['rows']));
});
