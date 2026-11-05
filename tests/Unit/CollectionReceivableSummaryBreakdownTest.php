<?php

declare(strict_types=1);

use App\Models\Collection;
use App\Support\Collections\CollectionReceivableReport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

/**
 * Escribe cuotas de prueba: todo ocurre dentro de una transacción que siempre se revierte.
 */
beforeEach(fn () => DB::beginTransaction());
afterEach(fn () => DB::rollBack());

/**
 * @param  array<string, mixed>  $attributes
 */
function breakdownReceivable(string $code, string $due, float $amount, string $type = 'AFILIACION INDIVIDUAL'): Collection
{
    return Collection::query()->forceCreate([
        'include_date' => '01/01/2026',
        'owner_code' => 'TDG-100',
        'code_agency' => 'TDG-100',
        'collection_invoice_number' => 'PEST-CXC-'.uniqid(),
        'quote_number' => 'PEST-Q',
        'persons' => '1',
        'type' => $type,
        'affiliation_code' => $code,
        'affiliate_status' => 'ACTIVA',
        'payment_frequency' => 'MENSUAL',
        'next_payment_date' => $due,
        'filter_next_payment_date' => $due,
        'total_amount' => $amount,
        'status' => CollectionReceivableReport::PENDING_STATUS,
    ]);
}

it('el vencido cuenta solo los últimos 30 días y aparte lo más viejo; ambos separan individuales y corporativas', function (): void {
    $suffix = uniqid();
    $codes = ['PEST-IND-A-'.$suffix, 'PEST-COR-B-'.$suffix, 'PEST-IND-C-'.$suffix, 'PEST-COR-D-'.$suffix, 'PEST-IND-E-'.$suffix];
    [$individualA, $corporateB, $individualC, $corporateD, $individualE] = $codes;

    breakdownReceivable($individualA, '2026-10-26', 100);
    breakdownReceivable($individualA, '2026-08-01', 50);
    breakdownReceivable($corporateB, '2026-10-06', 200, 'AFILIACIÓN CORPORATIVA');
    breakdownReceivable($corporateB, '2026-10-05', 30, 'AFILIACIÓN CORPORATIVA');
    breakdownReceivable($individualC, '2026-11-06', 10);
    breakdownReceivable($individualC, '2026-11-12', 5);
    breakdownReceivable($corporateD, '2026-11-07', 1000, 'AFILIACION CORPORATIVA');
    breakdownReceivable($individualE, '2026-11-13', 7);

    $summary = CollectionReceivableReport::summary(
        Collection::query()->whereIn('affiliation_code', $codes),
        CarbonImmutable::parse('2026-11-05'),
    );

    expect($summary['overdue_amount'])->toBe(380.0)
        ->and($summary['overdue_count'])->toBe(4)
        ->and($summary['overdue_recent_amount'])->toBe(300.0)
        ->and($summary['overdue_recent_count'])->toBe(2)
        ->and($summary['overdue_older_amount'])->toBe(80.0)
        ->and($summary['overdue_older_count'])->toBe(2)
        ->and($summary['overdue_recent_by_type'])->toBe([
            'individual' => ['affiliations' => 1, 'count' => 1, 'amount' => 100.0],
            'corporate' => ['affiliations' => 1, 'count' => 1, 'amount' => 200.0],
        ])
        ->and($summary['due_soon_amount'])->toBe(1015.0)
        ->and($summary['due_soon_count'])->toBe(3)
        ->and($summary['due_soon_by_type'])->toBe([
            'individual' => ['affiliations' => 1, 'count' => 2, 'amount' => 15.0],
            'corporate' => ['affiliations' => 1, 'count' => 1, 'amount' => 1000.0],
        ])
        ->and($summary['pending_by_type'])->toBe([
            'individual' => ['affiliations' => 3, 'count' => 5, 'amount' => 172.0],
            'corporate' => ['affiliations' => 2, 'count' => 3, 'amount' => 1230.0],
        ]);
});

it('sin cuotas el desglose queda en cero, sin errores', function (): void {
    $summary = CollectionReceivableReport::summary(
        Collection::query()->where('affiliation_code', 'PEST-NO-EXISTE-'.uniqid()),
        CarbonImmutable::parse('2026-11-05'),
    );

    expect($summary['overdue_recent_amount'])->toBe(0.0)
        ->and($summary['overdue_older_count'])->toBe(0)
        ->and($summary['due_soon_by_type']['corporate'])->toBe(['affiliations' => 0, 'count' => 0, 'amount' => 0.0])
        ->and($summary['overdue_recent_by_type']['individual'])->toBe(['affiliations' => 0, 'count' => 0, 'amount' => 0.0]);
});

it('los KPIs viven en el Dashboard de Cobranza con las tarjetas de los dashboards y llevan a la tabla filtrada', function (): void {
    $root = dirname(__DIR__, 2);
    $widget = (string) file_get_contents($root.'/app/Filament/Administration/Widgets/Collections/CollectionReceivablesKpis.php');
    $view = (string) file_get_contents($root.'/resources/views/filament/administration/collections/receivables-kpis.blade.php');
    $tile = (string) file_get_contents($root.'/resources/views/filament/administration/collections/partials/money-tile.blade.php');

    expect($widget)
        ->toContain('protected static bool $isDiscovered = false;')
        ->toContain('CollectionReceivableReport::summary(AnnualCollectionResource::getEloquentQuery())')
        ->toContain("'label' => 'Vencido · últimos '.\$days.' días'")
        ->toContain("'split' => \$summary['pending_by_type']")
        ->toContain("'split' => \$summary['overdue_recent_by_type']")
        ->toContain("'split' => \$summary['due_soon_by_type']")
        ->toContain('self::tableUrl(CollectionReceivableReport::AGING_OVERDUE_1_30)')
        ->toContain('self::tableUrl(CollectionReceivableReport::AGING_DUE_SOON)')
        ->not->toContain('Además, con más de')
        ->and($view)
        ->toContain('<x-filament-widgets::widget class="cxc-receivables-kpis">')
        ->toContain('Por vencer · desde hoy')
        ->toContain('sm:grid-cols-2 xl:grid-cols-4')
        ->toContain("'compact' => true")
        ->toContain('Recibos emitidos')
        ->not->toContain('wire:click')
        ->and($tile)
        ->toContain('rounded-2xl bg-white px-4 py-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10')
        ->toContain('x-intersect.once')
        ->toContain('href="{{ $tile[\'url\'] }}" wire:navigate')
        ->toContain("'afiliación', 'afiliaciones'");

    /** Primera fila: los plazos; después las cuentas por cobrar y los recibos emitidos. */
    expect(strpos($view, 'icon="heroicon-m-calendar-days"'))->toBeLessThan(strpos($view, 'icon="heroicon-m-banknotes"'))
        ->and(strpos($view, 'icon="heroicon-m-banknotes"'))->toBeLessThan(strpos($view, 'icon="heroicon-m-document-text"'));

    expect(CollectionReceivableReport::agingOptions())
        ->toHaveKeys([CollectionReceivableReport::AGING_OVERDUE_1_30, CollectionReceivableReport::AGING_DUE_SOON, ...CollectionReceivableReport::DUE_WINDOWS]);
});

it('el Dashboard de Cobranza usa el permiso de Cobranza Por Mes, va fuera del menú y es una foto a hoy', function (): void {
    $root = dirname(__DIR__, 2);
    $page = (string) file_get_contents($root.'/app/Filament/Administration/Pages/DashboardCobranza.php');
    $registry = (string) file_get_contents($root.'/app/Support/Filament/DepartmentNavigationPermissionRegistry.php');
    $provider = (string) file_get_contents($root.'/app/Providers/Filament/AdministrationPanelProvider.php');
    $header = (string) file_get_contents($root.'/resources/views/filament/operations/dashboard/page-header.blade.php');

    expect($page)
        ->toContain('use AuthorizesDepartmentNavigation;')
        ->toContain('use HasCompactDashboardHeader;')
        ->toContain("protected static string \$routePath = 'dashboard-cobranza';")
        ->toContain('return false;')
        ->toContain("'period' => null,")
        ->toContain('CollectionReceivablesKpis::class')
        ->toContain("->label('Ver cuentas por cobrar')")
        ->and($registry)->toContain("\\App\\Filament\\Administration\\Pages\\DashboardCobranza::class => ['cobranza-por-mes'],")
        ->and($provider)->toContain('scopes: \\App\\Filament\\Administration\\Pages\\DashboardCobranza::class')
        ->and($header)->toContain('@isset($period)')
        ->toContain("\$snapshotLabel ?? 'A hoy'");
});

it('Cuentas por cobrar ya no lleva el resumen encima: tiene el botón «Ver KPIs» hacia el dashboard', function (): void {
    $page = (string) file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/AnnualCollections/Pages/ListAnnualCollections.php');

    expect($page)
        ->toContain("Action::make('kpis')")
        ->toContain("->label('Ver KPIs')")
        ->toContain('->url(DashboardCobranza::getUrl())')
        ->toContain('DashboardCobranza::canAccess()')
        ->not->toContain('getSubheading')
        ->not->toContain('filterByDueWindow');
});

it('cada plazo de 15, 30, 45 y 60 días separa individuales y corporativas, y la suma da el total del plazo', function (): void {
    $suffix = uniqid();
    $codes = ['PEST-W-IND-'.$suffix, 'PEST-W-COR-'.$suffix];

    breakdownReceivable($codes[0], '2026-11-10', 10);
    breakdownReceivable($codes[0], '2026-11-30', 20);
    breakdownReceivable($codes[1], '2026-12-15', 300, 'AFILIACIÓN CORPORATIVA');
    breakdownReceivable($codes[1], '2027-01-10', 400, 'AFILIACIÓN CORPORATIVA');

    $windows = CollectionReceivableReport::summary(
        Collection::query()->whereIn('affiliation_code', $codes),
        CarbonImmutable::parse('2026-11-05'),
    )['due_windows'];

    expect($windows[15]['by_type'])->toBe([
        'individual' => ['affiliations' => 1, 'count' => 1, 'amount' => 10.0],
        'corporate' => ['affiliations' => 0, 'count' => 0, 'amount' => 0.0],
    ])
        ->and($windows[30]['by_type']['individual'])->toBe(['affiliations' => 1, 'count' => 2, 'amount' => 30.0])
        ->and($windows[45]['by_type']['corporate'])->toBe(['affiliations' => 1, 'count' => 1, 'amount' => 300.0])
        ->and($windows[60]['by_type']['corporate'])->toBe(['affiliations' => 1, 'count' => 1, 'amount' => 300.0])
        ->and($windows[60]['amount'])->toBe(330.0);

    foreach ($windows as $window) {
        expect(round($window['by_type']['individual']['amount'] + $window['by_type']['corporate']['amount'], 2))->toBe($window['amount']);
    }

    \Filament\Facades\Filament::setCurrentPanel('administration');
    $tiles = \App\Filament\Administration\Widgets\Collections\CollectionReceivablesKpis::windows($windows);

    expect(array_column($tiles, 'label'))->toBe(['Vence en 15 días', 'Vence en 30 días', 'Vence en 45 días', 'Vence en 60 días'])
        ->and($tiles[0]['split'])->toBe($windows[15]['by_type'])
        ->and($tiles[3]['amount'])->toBe(330.0)
        ->and($tiles[1]['url'])->toContain('vence_30');
});

it('los recibos emitidos cuentan los avisos por fecha de cobro del mes y del año, pagados o no, sin anulados', function (): void {
    $suffix = uniqid();
    $codes = ['PEST-R-IND-'.$suffix, 'PEST-R-COR-'.$suffix, 'PEST-R-IND2-'.$suffix];
    $base = CollectionReceivableReport::issuedNotices(CarbonImmutable::parse('2026-11-05'));

    breakdownReceivable($codes[0], '2026-11-03', 10);
    breakdownReceivable($codes[0], '2026-11-28', 20)->forceFill(['status' => 'PAGADO'])->save();
    breakdownReceivable($codes[0], '2026-11-15', 99)->forceFill(['status' => 'ANULADO'])->save();
    breakdownReceivable($codes[2], '2026-03-10', 5);
    breakdownReceivable($codes[1], '2026-11-20', 300, 'AFILIACIÓN CORPORATIVA');
    breakdownReceivable($codes[1], '2026-01-05', 400, 'AFILIACIÓN CORPORATIVA')->forceFill(['status' => 'CANCELADO'])->save();
    breakdownReceivable($codes[1], '2027-01-05', 700, 'AFILIACIÓN CORPORATIVA');

    $after = CollectionReceivableReport::issuedNotices(CarbonImmutable::parse('2026-11-05'));

    expect(CollectionReceivableReport::VOIDED_NOTICE_STATUSES)->toBe(['ANULADO', 'CANCELADO']);

    $only = static function (string $period, string $type) use ($codes): array {
        $today = CarbonImmutable::parse('2026-11-05');
        $range = $period === 'month'
            ? [$today->startOfMonth()->toDateString(), $today->endOfMonth()->toDateString()]
            : [$today->startOfYear()->toDateString(), $today->endOfYear()->toDateString()];

        $rows = Collection::query()
            ->whereIn('affiliation_code', $codes)
            ->whereNotIn('status', CollectionReceivableReport::VOIDED_NOTICE_STATUSES)
            ->whereBetween('filter_next_payment_date', $range)
            ->get()
            ->filter(fn (Collection $row): bool => CollectionReceivableReport::isCorporate($row) === ($type === 'corporate'));

        return ['affiliations' => $rows->pluck('affiliation_code')->unique()->count(), 'count' => $rows->count(), 'amount' => (float) $rows->sum('total_amount')];
    };

    /** Lo de prueba suma exactamente lo esperado sobre lo que ya había en la base. */
    expect($only('month', 'individual'))->toBe(['affiliations' => 1, 'count' => 2, 'amount' => 30.0])
        ->and($only('month', 'corporate'))->toBe(['affiliations' => 1, 'count' => 1, 'amount' => 300.0])
        ->and($only('year', 'individual'))->toBe(['affiliations' => 2, 'count' => 3, 'amount' => 35.0])
        ->and($only('year', 'corporate'))->toBe(['affiliations' => 1, 'count' => 1, 'amount' => 300.0]);

    expect($after['month']['individual']['count'] - $base['month']['individual']['count'])->toBe(2)
        ->and(round($after['month']['individual']['amount'] - $base['month']['individual']['amount'], 2))->toBe(30.0)
        ->and($after['month']['corporate']['count'] - $base['month']['corporate']['count'])->toBe(1)
        ->and($after['year']['individual']['count'] - $base['year']['individual']['count'])->toBe(3)
        ->and($after['year']['individual']['affiliations'] - $base['year']['individual']['affiliations'])->toBe(2)
        ->and($after['year']['corporate']['count'] - $base['year']['corporate']['count'])->toBe(1)
        ->and(round($after['year']['corporate']['amount'] - $base['year']['corporate']['amount'], 2))->toBe(300.0);
});

it('las tarjetas de recibos emitidos dicen el mes en español y separan avisos ind. y corp.', function (): void {
    $notices = [
        'month' => ['individual' => ['affiliations' => 4, 'count' => 41, 'amount' => 10.0], 'corporate' => ['affiliations' => 2, 'count' => 6, 'amount' => 20.0]],
        'year' => ['individual' => ['affiliations' => 90, 'count' => 150, 'amount' => 100.0], 'corporate' => ['affiliations' => 9, 'count' => 20, 'amount' => 200.0]],
    ];

    $tiles = \App\Filament\Administration\Widgets\Collections\CollectionReceivablesKpis::noticeTiles($notices, CarbonImmutable::parse('2026-11-05'));

    expect($tiles[0]['label'])->toBe('Recibos emitidos del mes')
        ->and($tiles[0]['count'])->toBe(47)
        ->and($tiles[0]['detail'])->toBe('Noviembre 2026 · US$ 30,00')
        ->and($tiles[1]['label'])->toBe('Recibos emitidos en el 2026')
        ->and($tiles[1]['count'])->toBe(170)
        ->and($tiles[1]['split']['corporate']['count'])->toBe(20);
});
