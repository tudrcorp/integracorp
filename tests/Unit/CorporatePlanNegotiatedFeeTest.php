<?php

declare(strict_types=1);

use App\Models\AffiliationCorporate;
use App\Models\AfilliationCorporatePlan;
use App\Models\Fee;
use App\Models\Log;
use App\Models\User;
use App\Services\AssociateAffiliatesWithCorporatePlanService;
use App\Support\AffiliationCorporates\CorporatePlanRowCreator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(Tests\TestCase::class);

/**
 * «Asociar Nuevo Plan» en una afiliación corporativa de Negocios: la tarifa
 * anual es la estándar del sistema o un monto negociado con motivo y traza.
 * Todo lo que escribe va dentro de una transacción que siempre se revierte.
 */
beforeEach(function (): void {
    DB::beginTransaction();

    $this->tarifa = Fee::query()
        ->whereNotNull('coverage_id')
        ->where('coverage_id', '<>', 0)
        ->whereHas('ageRange')
        ->with('ageRange')
        ->first();

    $this->analista = User::query()->where('email', 'like', '%@tudrencasa.com')->first();

    if ($this->tarifa === null || $this->analista === null) {
        $this->markTestSkipped('No hay tarifas con cobertura o analistas en la base.');
    }

    $this->afiliacion = AffiliationCorporate::query()
        ->whereDoesntHave('affiliationCorporatePlans', fn ($query) => $query
            ->where('age_range_id', $this->tarifa->age_range_id)
            ->where('coverage_id', $this->tarifa->coverage_id))
        ->first();

    if ($this->afiliacion === null) {
        $this->markTestSkipped('No hay una afiliación corporativa libre para el plan de prueba.');
    }

    $this->datos = [
        'plan_id' => $this->tarifa->ageRange->plan_id,
        'age_range_id' => $this->tarifa->age_range_id,
        'coverage_id' => $this->tarifa->coverage_id,
        'payment_frequency' => 'TRIMESTRAL',
    ];
});

afterEach(function (): void {
    DB::rollBack();
});

function errorDeCreacion(callable $crear): array
{
    try {
        $crear();
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    return [];
}

it('crea la fila con la tarifa estándar y no la marca como negociada', function (): void {
    $fila = CorporatePlanRowCreator::create($this->afiliacion, [...$this->datos, 'fee_source' => 'ESTANDAR', 'fee' => (string) $this->tarifa->price], $this->analista);

    expect((float) $fila->fee)->toBe((float) $this->tarifa->price)
        ->and($fila->fee_source)->toBe('ESTANDAR')
        ->and($fila->fee_negotiation_reason)->toBeNull()
        ->and($fila->fee_negotiated_by)->toBeNull()
        ->and((float) $fila->subtotal_quarterly)->toBe(round((float) $this->tarifa->price / 4, 2));
});

it('rechaza una tarifa estándar que no existe para el rango y la cobertura', function (): void {
    $errores = errorDeCreacion(fn () => CorporatePlanRowCreator::create($this->afiliacion, [...$this->datos, 'fee_source' => 'ESTANDAR', 'fee' => '1.23'], $this->analista));

    expect($errores)->toHaveKey('fee')
        ->and($this->afiliacion->affiliationCorporatePlans()->where('age_range_id', $this->datos['age_range_id'])->where('coverage_id', $this->datos['coverage_id'])->exists())->toBeFalse();
});

it('guarda el monto negociado con céntimos, el motivo, el autor y la auditoría', function (): void {
    $fila = CorporatePlanRowCreator::create($this->afiliacion, [
        ...$this->datos,
        'fee_source' => 'NEGOCIADA',
        'negotiated_fee' => '245.50',
        'fee_negotiation_reason' => '  Acordado con RRHH de la empresa el 02/10/2026.  ',
    ], $this->analista);

    $fila->refresh();

    expect((float) $fila->fee)->toBe(245.5)
        ->and($fila->fee_source)->toBe('NEGOCIADA')
        ->and($fila->fee_negotiation_reason)->toBe('Acordado con RRHH de la empresa el 02/10/2026.')
        ->and($fila->fee_negotiated_by)->toBe($this->analista->getKey())
        ->and($fila->fee_negotiated_at)->not->toBeNull()
        ->and($fila->feeNegotiatedBy?->is($this->analista))->toBeTrue();

    $auditoria = Log::query()->where('action', 'AUDIT_BUSINESS_CORPORATE_PLAN_NEGOTIATED_FEE')->latest('id')->first();

    expect($auditoria)->not->toBeNull()
        ->and((string) $auditoria->response)->toContain('"plan_row_id":'.$fila->getKey())
        ->toContain('245.5')
        ->toContain('Acordado con RRHH');
});

it('exige monto válido y motivo para la tarifa negociada', function (array $cambios, string $campo): void {
    $errores = errorDeCreacion(fn () => CorporatePlanRowCreator::create($this->afiliacion, [
        ...$this->datos,
        'fee_source' => 'NEGOCIADA',
        'negotiated_fee' => '300',
        'fee_negotiation_reason' => 'Motivo suficientemente largo.',
        ...$cambios,
    ], $this->analista));

    expect($errores)->toHaveKey($campo);
})->with([
    'sin monto' => [['negotiated_fee' => ''], 'negotiated_fee'],
    'monto cero' => [['negotiated_fee' => '0'], 'negotiated_fee'],
    'monto negativo' => [['negotiated_fee' => '-10'], 'negotiated_fee'],
    'monto con texto' => [['negotiated_fee' => 'abc'], 'negotiated_fee'],
    'sin motivo' => [['fee_negotiation_reason' => ''], 'fee_negotiation_reason'],
    'motivo muy corto' => [['fee_negotiation_reason' => '   corto   '], 'fee_negotiation_reason'],
]);

it('no duplica plan, cobertura y rango de edad en la misma afiliación', function (): void {
    CorporatePlanRowCreator::create($this->afiliacion, [...$this->datos, 'fee_source' => 'ESTANDAR', 'fee' => (string) $this->tarifa->price], $this->analista);

    $errores = errorDeCreacion(fn () => CorporatePlanRowCreator::create($this->afiliacion, [
        ...$this->datos,
        'fee_source' => 'NEGOCIADA',
        'negotiated_fee' => '200',
        'fee_negotiation_reason' => 'Segundo intento con otro monto.',
    ], $this->analista));

    expect($errores)->toHaveKey('plan_id');
});

it('al asociar afiliados acepta la tarifa negociada de la fila y sigue validando las estándar', function (): void {
    $negociada = CorporatePlanRowCreator::create($this->afiliacion, [
        ...$this->datos,
        'fee_source' => 'NEGOCIADA',
        'negotiated_fee' => '245.50',
        'fee_negotiation_reason' => 'Acordado con RRHH de la empresa.',
    ], $this->analista);

    $datosAsociacion = ['plan_id' => $negociada->plan_id, 'age_range_id' => $negociada->age_range_id, 'coverage_id' => $negociada->coverage_id, 'fee' => $negociada->fee];

    // Pasa la validación de tarifa: lo siguiente que pide es elegir afiliados.
    expect(errorDeCreacion(fn () => AssociateAffiliatesWithCorporatePlanService::run($this->afiliacion, $negociada, [], $datosAsociacion)))
        ->toHaveKey('affiliate_ids')->not->toHaveKey('fee')
        ->and(CorporatePlanRowCreator::isNegotiatedFeeOfRow($negociada, 245.5))->toBeTrue()
        ->and(CorporatePlanRowCreator::isNegotiatedFeeOfRow($negociada, 999.0))->toBeFalse();

    $estandar = new AfilliationCorporatePlan(['fee' => 245.5, 'fee_source' => 'ESTANDAR']);

    expect(CorporatePlanRowCreator::isNegotiatedFeeOfRow($estandar, 245.5))->toBeFalse();
});

it('la modal de Negocios ofrece monto negociado con motivo y la tabla lo señala', function (): void {
    $relationManager = (string) file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/RelationManagers/AffiliationCorporatePlansRelationManager.php');

    expect($relationManager)
        ->toContain("ToggleButtons::make('fee_source')")
        ->toContain("TextInput::make('negotiated_fee')")
        ->toContain("Textarea::make('fee_negotiation_reason')")
        ->toContain('CorporatePlanRowCreator::create(')
        ->toContain("'Negociada'")
        ->toContain("'feeNegotiatedBy:id,name'");
});

it('la modal real asocia el plan con monto negociado y avisa si falta el motivo', function (): void {
    \Filament\Facades\Filament::setCurrentPanel('business');

    $componente = \Livewire\Livewire::actingAs($this->analista)
        ->test(\App\Filament\Business\Resources\AffiliationCorporates\RelationManagers\AffiliationCorporatePlansRelationManager::class, [
            'ownerRecord' => $this->afiliacion,
            'pageClass' => \App\Filament\Business\Resources\AffiliationCorporates\Pages\EditAffiliationCorporate::class,
        ])
        ->mountAction(\Filament\Actions\Testing\TestAction::make('create')->table())
        ->set('mountedActions.0.data.plan_id', $this->datos['plan_id'])
        ->set('mountedActions.0.data.age_range_id', $this->datos['age_range_id'])
        ->set('mountedActions.0.data.coverage_id', $this->datos['coverage_id'])
        ->set('mountedActions.0.data.fee_source', 'NEGOCIADA')
        ->set('mountedActions.0.data.negotiated_fee', '310.75')
        ->set('mountedActions.0.data.fee_negotiation_reason', '')
        ->callMountedAction()
        ->assertHasFormErrors(['fee_negotiation_reason' => 'required']);

    $componente
        ->set('mountedActions.0.data.fee_negotiation_reason', 'Tarifa especial aprobada por gerencia comercial.')
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertNotified('Plan asociado con tarifa negociada');

    $fila = $this->afiliacion->affiliationCorporatePlans()
        ->where('age_range_id', $this->datos['age_range_id'])
        ->where('coverage_id', $this->datos['coverage_id'])
        ->firstOrFail();

    expect((float) $fila->fee)->toBe(310.75)
        ->and($fila->fee_source)->toBe('NEGOCIADA')
        ->and($fila->fee_negotiated_by)->toBe($this->analista->getKey());
});
