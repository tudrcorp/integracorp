<?php

declare(strict_types=1);

use App\Models\DetailIndividualQuote;
use App\Models\IndividualQuote;
use App\Models\User;
use App\Support\IndividualQuotes\IndividualQuotePurchase;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * «Comprar plan» desde la tabla de montos de la cotización individual (agentes, master y general):
 * aprueba, cuenta bien el grupo familiar y abre la pre-afiliación con plan y cobertura elegidos.
 * Escribe dentro de una transacción que siempre se revierte.
 */
beforeEach(function (): void {
    DB::beginTransaction();

    $source = IndividualQuote::query()->whereHas('detailsQuote')->orderByDesc('id')->first();

    if (! $source instanceof IndividualQuote) {
        $this->markTestSkipped('No hay cotizaciones individuales con detalle.');
    }

    $this->quote = $source->replicate();
    $this->quote->forceFill(['code' => 'COT-IND-QA'.random_int(100000, 999999), 'status' => 'PRE-APROBADA'])->save();

    $rows = [
        // Especial: 1 persona de 0-30 y 2 de 31-65, en dos coberturas.
        [3, 5, 11, 1, 311], [3, 6, 11, 2, 339],
        [3, 5, 12, 1, 339], [3, 6, 12, 2, 377],
        // Ideal en la misma cotización (multiplan): 3 personas en un solo rango.
        [2, 1, 3, 3, 189],
    ];

    foreach ($rows as [$plan, $range, $coverage, $persons, $fee]) {
        DetailIndividualQuote::query()->create([
            'individual_quote_id' => $this->quote->getKey(),
            'plan_id' => $plan,
            'age_range_id' => $range,
            'coverage_id' => $coverage,
            'total_persons' => $persons,
            'fee' => $fee,
            'subtotal_anual' => $fee * $persons,
            'subtotal_quarterly' => $fee * $persons / 4,
            'subtotal_biannual' => $fee * $persons / 2,
            'subtotal_monthly' => round($fee * $persons / 12, 2),
            'status' => 'PRE-APROBADA',
            'created_by' => 'QA',
        ]);
    }

    $this->detail = DetailIndividualQuote::query()->where('individual_quote_id', $this->quote->getKey())->where('plan_id', 3)->where('coverage_id', 12)->where('age_range_id', 6)->firstOrFail();
});

afterEach(function (): void {
    DB::rollBack();
});

it('suma las personas de todos los rangos de edad, no una sola fila', function (): void {
    expect(IndividualQuotePurchase::persons($this->quote, 3, 11))->toBe(3)
        ->and(IndividualQuotePurchase::persons($this->quote, 3, 12))->toBe(3)
        ->and(IndividualQuotePurchase::persons($this->quote, 3))->toBe(3)
        ->and(IndividualQuotePurchase::persons($this->quote, 2))->toBe(3)
        ->and(IndividualQuotePurchase::persons($this->quote, 3, 999))->toBe(3);
});

it('aprueba igual que el listado, deja la sesión limpia y abre la pre-afiliación', function (string $panel): void {
    session()->put('data_records', [['individual_quote_id' => 1, 'coverage_id' => 77]]);
    DetailIndividualQuote::query()->where('individual_quote_id', $this->quote->getKey())->where('plan_id', 2)->delete();

    $url = IndividualQuotePurchase::start($this->quote, $panel);

    expect($this->quote->fresh()->status)->toBe('APROBADA')
        ->and(session('persons'))->toBe(3)
        ->and(session()->has('data_records'))->toBeFalse()
        ->and($url)->toContain('/'.$panel.'/affiliations/create')
        ->and($url)->toContain('id='.$this->quote->getKey())
        ->and($url)->toContain('plan_id=3');
})->with(['agents', 'master', 'general']);

it('con varios planes no preselecciona ninguno, como el listado', function (): void {
    $url = IndividualQuotePurchase::start($this->quote, 'agents');

    expect($url)->not->toContain('plan_id=')
        ->and(session('persons'))->toBe(3);
});

it('no se ofrece en una cotización aprobada o ya convertida en afiliación', function (string $status): void {
    $this->quote->forceFill(['status' => $status])->save();

    expect(IndividualQuotePurchase::canPurchase($this->quote->fresh()))->toBeFalse()
        ->and(fn () => IndividualQuotePurchase::start($this->quote, 'agents'))->toThrow(RuntimeException::class)
        ->and($this->quote->fresh()->status)->toBe($status);
})->with(['APROBADA', 'EJECUTADA']);

it('la tabla de montos tiene un solo «Comprar plan» en el encabezado y sin acciones masivas, en los tres paneles', function (string $panel): void {
    $root = dirname(__DIR__, 2);
    $relation = (string) file_get_contents($root.'/app/Filament/'.$panel.'/Resources/IndividualQuotes/RelationManagers/DetailsQuoteRelationManager.php');
    $list = (string) file_get_contents($root.'/app/Filament/'.$panel.'/Resources/IndividualQuotes/Tables/IndividualQuotesTable.php');

    expect($relation)->not->toContain('BulkAction')
        ->and($relation)->not->toContain('toolbarActions')
        ->and($relation)->not->toContain('recordActions')
        ->and(substr_count($relation, "Action::make('buy_plan')"))->toBe(1)
        ->and($relation)->toContain("->headerActions([\n                Action::make('buy_plan')")
        ->and($relation)->toContain('IndividualQuotePurchase::start($quote')
        ->and($list)->toContain('IndividualQuotePurchase::prepareSession($record)')
        ->and($list)->not->toContain("->detailsQuote()->first()->total_persons");
})->with(['Agents', 'Master', 'General']);

it('un agente pulsa «Comprar plan» en el encabezado y llega a la pre-afiliación', function (): void {
    $agent = User::query()->where('is_agent', 1)->where('status', 'ACTIVO')->whereNotNull('agent_id')->first();

    if (! $agent instanceof User) {
        $this->markTestSkipped('No hay agentes activos.');
    }

    Auth::login($agent);
    Filament::setCurrentPanel(Filament::getPanel('agents'));

    $component = Livewire::test(App\Filament\Agents\Resources\IndividualQuotes\RelationManagers\DetailsQuoteRelationManager::class, [
        'ownerRecord' => $this->quote,
        'pageClass' => App\Filament\Agents\Resources\IndividualQuotes\Pages\ViewIndividualQuote::class,
    ]);

    expect(substr_count($component->html(), 'Comprar plan'))->toBe(1);

    $component->call('mountTableAction', 'buy_plan')->call('callMountedTableAction');

    expect($this->quote->fresh()->status)->toBe('APROBADA')
        ->and(session('persons'))->toBe(3);
});
