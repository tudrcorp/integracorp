<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\OperationAccountsPayables\Pages\ListOperationAccountsPayables;
use App\Filament\Operations\Resources\OperationAccountsPayables\Widgets\AccountsPayableOverview;
use App\Models\OperationAccountsPayable;
use App\Models\User;
use App\Support\Operations\AccountsPayableMonthlyLoad;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(Tests\TestCase::class);

beforeEach(fn () => DB::beginTransaction());
afterEach(fn () => DB::rollBack());

/**
 * Facturas en 2031: ningún dato real cae en esas fechas.
 */
function cargarFacturasDePrueba(): void
{
    foreach ([
        ['2031-02-01', '100.00', 'USD'],
        ['2031-02-15', '50.00', 'USD'],
        ['2031-02-20', '30.00', 'USD'],
        ['2031-02-28', '999.00', 'VES'],
        ['2031-03-01', '40.00', 'USD'],
        ['2031-03-10', '80.00', 'USD'],
        ['2031-03-16', '500.00', 'USD'],
    ] as [$date, $amount, $currency]) {
        OperationAccountsPayable::factory()->create([
            'invoice_registration_date' => $date,
            'invoice_amount' => $amount,
            'invoice_currency' => $currency,
        ]);
    }
}

it('compara el mes en curso contra el mismo tramo del mes anterior, no contra el mes completo', function (): void {
    cargarFacturasDePrueba();
    $today = CarbonImmutable::create(2031, 3, 15);

    $summary = AccountsPayableMonthlyLoad::summary(CarbonImmutable::create(2031, 3, 1), $today);

    expect($summary['is_current'])->toBeTrue()
        ->and($summary['label'])->toBe('Marzo 2031')
        ->and($summary['until']->toDateString())->toBe('2031-03-15')
        ->and($summary['totals'])->toBe(['count' => 2, 'usd' => 120.0, 'ves' => 0.0])
        ->and($summary['previous']['label'])->toBe('Febrero 2031')
        ->and($summary['previous']['until']->toDateString())->toBe('2031-02-15')
        ->and($summary['previous']['is_partial'])->toBeTrue()
        ->and($summary['previous']['totals'])->toBe(['count' => 2, 'usd' => 150.0, 'ves' => 0.0])
        ->and($summary['previous']['full_totals'])->toBe(['count' => 4, 'usd' => 180.0, 'ves' => 999.0])
        ->and($summary['delta'])->toBe(['count' => 0, 'usd' => -30.0, 'usd_percent' => -20.0]);
});

it('en un mes cerrado compara contra el mes anterior completo', function (): void {
    cargarFacturasDePrueba();

    $summary = AccountsPayableMonthlyLoad::summary(CarbonImmutable::create(2031, 3, 1), CarbonImmutable::create(2031, 5, 2));

    expect($summary['is_current'])->toBeFalse()
        ->and($summary['until']->toDateString())->toBe('2031-03-31')
        ->and($summary['totals'])->toBe(['count' => 3, 'usd' => 620.0, 'ves' => 0.0])
        ->and($summary['previous']['is_partial'])->toBeFalse()
        ->and($summary['previous']['totals'])->toBe(['count' => 4, 'usd' => 180.0, 'ves' => 999.0])
        ->and($summary['delta']['usd_percent'])->toBe(244.4);
});

it('no inventa un porcentaje cuando el mes anterior no tuvo facturas', function (): void {
    $summary = AccountsPayableMonthlyLoad::summary(CarbonImmutable::create(2031, 8, 1), CarbonImmutable::create(2031, 9, 10));

    expect($summary['totals']['count'])->toBe(0)
        ->and($summary['delta'])->toBe(['count' => 0, 'usd' => 0.0, 'usd_percent' => null]);
});

it('del 31 de marzo compara hasta el último día de febrero sin desbordar', function (): void {
    $summary = AccountsPayableMonthlyLoad::summary(CarbonImmutable::create(2031, 3, 1), CarbonImmutable::create(2031, 3, 31));

    expect($summary['previous']['until']->toDateString())->toBe('2031-02-28')
        ->and($summary['previous']['is_partial'])->toBeFalse();
});

it('acepta solo meses válidos y nunca futuros', function (mixed $value, string $expected): void {
    expect(AccountsPayableMonthlyLoad::resolveMonth($value, CarbonImmutable::create(2031, 3, 15))->format('Y-m'))->toBe($expected);
})->with([
    'mes válido' => ['2031-01', '2031-01'],
    'futuro' => ['2031-04', '2031-03'],
    'mes 13' => ['2031-13', '2031-03'],
    'formato ajeno' => ['03/2031', '2031-03'],
    'nulo' => [null, '2031-03'],
    'inyección' => ["2031-01' OR 1=1", '2031-03'],
]);

it('ofrece como mucho 24 meses, del más reciente al más antiguo', function (): void {
    $options = AccountsPayableMonthlyLoad::monthOptions(CarbonImmutable::create(2031, 3, 15));

    expect($options)->toHaveCount(AccountsPayableMonthlyLoad::MAX_MONTHS)
        ->and(array_key_first($options))->toBe('2031-03')
        ->and($options['2031-03'])->toBe('Marzo 2031')
        ->and(array_key_last($options))->toBe('2029-04');
});

it('muestra la tarjeta, cambia de mes y descarta un mes manipulado', function (): void {
    $widget = Livewire::test(AccountsPayableOverview::class)
        ->assertSee('Facturas cargadas')
        ->assertSee('Comparar con el mes anterior')
        ->assertSee('Ver estas facturas en la tabla')
        ->assertSet('loadedMonth', now()->format('Y-m'));

    $previous = now()->subMonthNoOverflow()->format('Y-m');

    if (array_key_exists($previous, AccountsPayableMonthlyLoad::monthOptions())) {
        $widget->set('loadedMonth', $previous)->assertSet('loadedMonth', $previous);
    }

    $widget->set('loadedMonth', '1999-01')->assertSet('loadedMonth', now()->format('Y-m'))
        ->set('loadedMonth', 'basura')->assertSet('loadedMonth', now()->format('Y-m'))
        ->set('compareLoadedMonth', false)->assertDontSee('Frente a')
        ->set('compareLoadedMonth', true)->assertSee('Frente a');
});

it('pone «Facturas cargadas» primero y deja todo el resumen plegado por defecto', function (): void {
    $html = Livewire::test(AccountsPayableOverview::class)->html();

    $cargadas = strpos($html, 'Facturas cargadas');
    $porServicio = strpos($html, 'Por pagar según el servicio');

    expect($html)
        ->toContain('Resumen de cuentas por pagar')
        ->toContain('open: false,')
        ->toContain('x-show="open" x-collapse x-cloak')
        ->toContain("x-bind:aria-expanded=\"open ? 'true' : 'false'\"")
        ->toContain('window.localStorage')
        ->toContain('Por pagar: US$')
        ->not->toContain('Frente a del')
        ->and($cargadas)->toBeInt()
        ->and($cargadas)->toBeLessThan(strpos($html, 'Pagadas sin comprobante'))
        ->and($porServicio)->toBeGreaterThan(strpos($html, 'Ver todas'));
});

it('al pulsar la tarjeta filtra la tabla por la fecha de registro del mes, y «Ver todas» lo quita', function (): void {
    $usuario = User::factory()->create([
        'email' => 'qa.cxp.mes.cargado@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['SUPERADMIN', 'OPERACIONES'],
    ]);
    $this->actingAs($usuario);
    Filament::setCurrentPanel('operations');

    $month = now()->subMonthNoOverflow();
    $page = Livewire::test(ListOperationAccountsPayables::class)
        ->call('applyAccountsPayableShortcut', 'loaded:'.$month->format('Y-m'));

    expect($page->get('tableFilters.invoice_registration_range.range'))->toBe([
        'from' => $month->startOfMonth()->toDateString(),
        'until' => $month->endOfMonth()->toDateString(),
    ]);

    $page->call('applyAccountsPayableShortcut', 'all');

    expect($page->get('tableFilters.invoice_registration_range.range'))->toBe(['from' => null, 'until' => null]);

    $page->call('applyAccountsPayableShortcut', 'loaded:no-es-mes');

    expect($page->get('tableFilters.invoice_registration_range.range'))->toBe(['from' => null, 'until' => null]);
});
