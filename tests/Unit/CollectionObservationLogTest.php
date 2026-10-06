<?php

declare(strict_types=1);

use App\Models\Collection;
use App\Models\CollectionObservation;
use App\Models\User;
use App\Support\Collections\CollectionObservationLog;
use App\Support\Collections\CollectionReceivableReport;
use App\Support\Filament\SummaryCards;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

/**
 * Escribe en `collection_observations`: todo va dentro de una transacción que
 * siempre se revierte, para no dejar basura en la base de desarrollo.
 */
beforeEach(fn () => DB::beginTransaction());
afterEach(fn () => DB::rollBack());

/**
 * Cuota en memoria: la bitácora solo necesita su código, tipo, vencimiento y monto.
 *
 * @param  array<string, mixed>  $attributes
 */
function observationLogCollection(array $attributes = []): Collection
{
    $collection = new Collection([
        'affiliation_code' => 'TEST-OBS-0001',
        'type' => 'AFILIACION INDIVIDUAL',
        'next_payment_date' => '15/10/2026',
        'total_amount' => 147.25,
        'status' => 'POR PAGAR',
        ...$attributes,
    ]);

    $collection->setRelation('affiliationByCode', null);
    $collection->setRelation('affiliationCorporateByCode', null);

    return $collection;
}

function observationLogUser(): User
{
    return User::factory()->make(['name' => 'ANALISTA COBRANZA']);
}

it('registra la observación con autor, fecha y la cuota a la que se refería', function (): void {
    $observation = CollectionObservationLog::record(
        observationLogCollection(),
        "  Se llamó al pagador.\nPromete pagar el viernes.  ",
        observationLogUser(),
    );

    $stored = CollectionObservation::query()->findOrFail($observation->id);

    expect($stored->affiliation_code)->toBe('TEST-OBS-0001')
        ->and($stored->affiliation_type)->toBe(CollectionObservation::TYPE_INDIVIDUAL)
        ->and($stored->observation)->toBe("Se llamó al pagador.\nPromete pagar el viernes.")
        ->and($stored->due_date?->toDateString())->toBe('2026-10-15')
        ->and((float) $stored->amount)->toBe(147.25)
        ->and($stored->created_by_name)->toBe('ANALISTA COBRANZA')
        ->and($stored->created_at)->not->toBeNull();
});

it('distingue las afiliaciones corporativas', function (): void {
    $observation = CollectionObservationLog::record(
        observationLogCollection(['affiliation_code' => 'TEST-OBS-CORP', 'type' => 'AFILIACION CORPORATIVA']),
        'Empresa solicita estado de cuenta.',
        observationLogUser(),
    );

    expect($observation->affiliation_type)->toBe(CollectionObservation::TYPE_CORPORATE);
});

it('rechaza observaciones vacías, demasiado largas o sin afiliación', function (Collection $collection, string $text, string $message): void {
    expect(fn () => CollectionObservationLog::record($collection, $text, observationLogUser()))
        ->toThrow(InvalidArgumentException::class, $message);

    expect(CollectionObservation::query()->where('affiliation_code', 'TEST-OBS-0001')->exists())->toBeFalse();
})->with([
    'vacía' => fn () => [observationLogCollection(), '   ', 'Escriba la observación'],
    'demasiado larga' => fn () => [observationLogCollection(), str_repeat('a', CollectionObservation::MAX_LENGTH + 1), 'no puede superar'],
    'sin código de afiliación' => fn () => [observationLogCollection(['affiliation_code' => '  ']), 'Llamada', 'no tiene código de afiliación'],
]);

it('la bitácora es de la afiliación: la conservan todas sus cuotas, de la más reciente a la más antigua', function (): void {
    $user = observationLogUser();

    $first = CollectionObservationLog::record(observationLogCollection(['next_payment_date' => '15/07/2026']), 'Primera gestión', $user);
    $first->forceFill(['created_at' => now()->subDays(90)])->save();

    // La cuota siguiente de la misma afiliación ve también la nota anterior.
    CollectionObservationLog::record(observationLogCollection(['next_payment_date' => '15/10/2026']), 'Segunda gestión', $user);
    CollectionObservationLog::record(observationLogCollection(['affiliation_code' => 'TEST-OBS-OTRA']), 'De otra afiliación', $user);

    $log = CollectionObservationLog::forAffiliation('TEST-OBS-0001');

    expect($log->pluck('observation')->all())->toBe(['Segunda gestión', 'Primera gestión'])
        ->and(CollectionObservationLog::forAffiliation('  ')->all())->toBe([])
        ->and(observationLogCollection()->receivableObservations()->count())->toBe(2);
});

it('la bitácora se muestra vacía con una guía y con cada nota cuando las hay', function (): void {
    $empty = view('filament.administration.annual-collections.observation-log', [
        'observations' => collect(),
        'currentCollectionId' => 0,
    ])->render();

    $observation = CollectionObservationLog::record(observationLogCollection(), '<b>Llamada</b> sin respuesta', observationLogUser());

    $filled = view('filament.administration.annual-collections.observation-log', [
        'observations' => CollectionObservationLog::forAffiliation('TEST-OBS-0001'),
        'currentCollectionId' => (int) $observation->collection_id,
    ])->render();

    expect($empty)->toContain('todavía no tiene observaciones de cobranza')
        ->and($filled)
        ->toContain('ANALISTA COBRANZA')
        ->toContain('&lt;b&gt;Llamada&lt;/b&gt; sin respuesta')
        ->toContain('Cuota con vencimiento 15/10/2026')
        ->toContain('US$ 147,25')
        ->not->toContain('<b>Llamada</b>');
});

it('el resumen trae los plazos de 30, 45 y 60 días acumulados', function (): void {
    $summary = CollectionReceivableReport::summary();

    expect(array_keys($summary['due_windows']))->toBe([30, 45, 60])
        ->and($summary['due_windows'][45]['amount'])->toBeGreaterThanOrEqual($summary['due_windows'][30]['amount'])
        ->and($summary['due_windows'][60]['amount'])->toBeGreaterThanOrEqual($summary['due_windows'][45]['amount'])
        ->and($summary['due_windows'][60]['count'])->toBeGreaterThanOrEqual($summary['due_windows'][30]['count'])
        ->and($summary['due_windows'][30]['count'])->toBeGreaterThanOrEqual($summary['due_soon_count'])
        ->and($summary['due_windows'][60]['amount'])->toBeLessThanOrEqual($summary['pending_amount']);
});

it('los plazos del total son botones que aplican el filtro y marcan el activo', function (): void {
    $html = (string) SummaryCards::render([[
        'label' => 'Total por cobrar',
        'value' => 'US$ 1,00',
        'color' => SummaryCards::BLUE,
        'breakdown' => [
            ['label' => '30 días', 'value' => 'US$ 1,00', 'action' => "filterByDueWindow('vence_30')", 'active' => true, 'title' => 'Quitar'],
            ['label' => '45 días', 'value' => 'US$ 2,00', 'action' => "filterByDueWindow('vence_45')"],
            ['label' => '60 días', 'value' => 'US$ 3,00'],
        ],
    ]]);

    expect($html)
        ->toContain('wire:click="filterByDueWindow(&#039;vence_30&#039;)"')
        ->toContain('aria-pressed="true"')
        ->toContain('aria-pressed="false"')
        ->and(substr_count($html, '<button type="button"'))->toBe(2);
});

it('la página solo acepta los plazos del resumen y la acción queda en la tabla', function (): void {
    $root = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/AnnualCollections';
    $page = file_get_contents($root.'/Pages/ListAnnualCollections.php');
    $action = file_get_contents($root.'/Actions/CollectionObservationsAction.php');

    expect(CollectionReceivableReport::DUE_WINDOWS)->toBe([
        30 => CollectionReceivableReport::AGING_DUE_30,
        45 => CollectionReceivableReport::AGING_DUE_45,
        60 => CollectionReceivableReport::AGING_DUE_60,
    ])
        ->and(CollectionReceivableReport::agingOptions())->toHaveKeys(CollectionReceivableReport::DUE_WINDOWS);

    expect($page)
        ->toContain('in_array($bucket, CollectionReceivableReport::DUE_WINDOWS, true)')
        ->toContain('$this->updatedTableFilters()');

    expect($action)
        ->toContain("Textarea::make('observation')")
        ->toContain('->maxLength(CollectionObservation::MAX_LENGTH)')
        ->toContain('CollectionObservationLog::record(')
        ->toContain('SecurityAudit::log(')
        ->toContain("'receivable_observations_count'")
        ->not->toContain('->delete(')
        ->not->toContain('->update(');
});

it('el resumen se puede colapsar, empieza cerrado y usa el diseño de los paneles de Ventas', function (): void {
    $html = (string) SummaryCards::collapsible(
        [['label' => 'Vencido', 'value' => 'US$ 5,00', 'color' => SummaryCards::RED]],
        'Resumen de cuentas por cobrar',
        'Total, vencido y por vencer.',
        hint: 'Total por cobrar: US$ 9,00',
    );

    expect($html)
        ->toContain('x-data="{ open: false }"')
        ->toContain('class="fi-admin-sales-stats-panel" data-expanded="false"')
        ->toContain('class="fi-admin-sales-stats-panel__trigger"')
        ->toContain('aria-expanded="false"')
        ->toContain('x-on:click="open = ! open"')
        ->toContain('x-show="open" x-collapse x-cloak')
        ->toContain('RESUMEN DE CUENTAS POR COBRAR')
        ->toContain('Colapsado · haz clic para ver las métricas')
        ->toContain('>Mostrar</span>')
        ->toContain('x-show="! open" style="font-size:.8rem;font-weight:700;font-variant-numeric:tabular-nums;white-space:nowrap;">Total por cobrar: US$ 9,00</span>')
        ->toContain('fi-admin-sales-stats-panel__icon-svg')
        ->toContain('US$ 5,00')
        // El giro de la flecha lo da el CSS por data-expanded: sin estilos enlazados.
        ->not->toContain('x-bind:style');

    expect(file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/AnnualCollections/Pages/ListAnnualCollections.php'))
        ->toContain('SummaryCards::collapsible(');
});

it('Gestión de Cobranza usa el resumen colapsable con el orden por cobrar, vence en 7 días, vencidos', function (): void {
    $page = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Collections/Pages/ListCollections.php');

    expect($page)->toContain('SummaryCards::collapsible(')
        ->toContain('as due_soon_amount", [$today, $soon]');

    $positions = array_map(
        fn (string $label): int|false => strpos($page, "'label' => '{$label}'"),
        ['Por cobrar', 'Vence en 7 días', 'Vencidos', 'Cobrado'],
    );

    expect($positions)->each->toBeInt()
        ->and($positions)->toBe(collect($positions)->sort()->values()->all());
});
