<?php

declare(strict_types=1);

use App\Models\Fee;
use App\Models\Plan;
use App\Models\WhiteCompany;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\WhiteCompanies\WhiteCompanyCatalogFeeOptions;
use App\Support\WhiteCompanies\WhiteCompanyPlanAssignment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(Tests\TestCase::class);

/**
 * Habilitación de planes para una empresa aliada: el paso previo a pactar netas.
 *
 * Los tests que escriben abren transacción y la revierten: corren contra la base
 * real, no contra sqlite.
 */
function planConBeneficios(): ?Plan
{
    return Plan::query()->whereHas('benefitPlans')->first();
}

function planSinBeneficios(): ?Plan
{
    return Plan::query()->whereDoesntHave('benefitPlans')->first();
}

it('solo ofrece para asignar los planes que tienen beneficios cargados', function (): void {
    $company = WhiteCompany::query()->first();

    if ($company === null) {
        expect(true)->toBeTrue();

        return;
    }

    $ofrecidos = array_keys(WhiteCompanyPlanAssignment::assignablePlans($company));

    expect($ofrecidos)->not->toBeEmpty();

    foreach ($ofrecidos as $planId) {
        expect(Plan::query()->whereKey($planId)->whereHas('benefitPlans')->exists())->toBeTrue();
    }

    $sinBeneficios = planSinBeneficios();

    if ($sinBeneficios !== null) {
        expect($ofrecidos)->not->toContain((int) $sinBeneficios->id);
    }
});

it('explica por qué un plan sin beneficios no se puede asignar', function (): void {
    $sinBeneficios = planSinBeneficios();
    $conBeneficios = planConBeneficios();

    if ($sinBeneficios === null || $conBeneficios === null) {
        expect(true)->toBeTrue();

        return;
    }

    expect(WhiteCompanyPlanAssignment::blockingReason($sinBeneficios))
        ->toContain('no tiene beneficios')
        ->and(WhiteCompanyPlanAssignment::blockingReason($conBeneficios))->toBeNull();
});

it('rechaza asignar un plan sin beneficios', function (): void {
    $company = WhiteCompany::query()->first();
    $sinBeneficios = planSinBeneficios();

    if ($company === null || $sinBeneficios === null) {
        expect(true)->toBeTrue();

        return;
    }

    expect(fn () => WhiteCompanyPlanAssignment::assign($company, [(int) $sinBeneficios->id], 'test'))
        ->toThrow(ValidationException::class);
});

it('asigna planes y no los duplica al repetir la operación', function (): void {
    $company = WhiteCompany::query()->first();
    $plan = planConBeneficios();

    if ($company === null || $plan === null) {
        expect(true)->toBeTrue();

        return;
    }

    DB::beginTransaction();

    try {
        $primera = WhiteCompanyPlanAssignment::assign($company, [(int) $plan->id], 'test');
        $segunda = WhiteCompanyPlanAssignment::assign($company, [(int) $plan->id], 'test');

        expect($primera['asignados'])->toBe(1)
            ->and($segunda['asignados'])->toBe(0)
            ->and($segunda['ya_estaban'])->toBe(1)
            ->and($company->assignedPlans()->where('plan_id', $plan->id)->count())->toBe(1);
    } finally {
        DB::rollBack();
    }
});

it('la matriz de negociación solo ofrece tarifas de los planes asignados', function (): void {
    $company = WhiteCompany::query()->first();

    $plan = Plan::query()
        ->whereHas('benefitPlans')
        ->whereIn('id', Fee::query()->whereNotNull('plan_id')->select('plan_id'))
        ->first();

    if ($company === null || $plan === null) {
        expect(true)->toBeTrue();

        return;
    }

    DB::beginTransaction();

    try {
        // Sin planes asignados no hay nada que pactar.
        $company->assignedPlans()->delete();
        expect(WhiteCompanyCatalogFeeOptions::catalogFeesForCompany($company))->toHaveCount(0);

        WhiteCompanyPlanAssignment::assign($company, [(int) $plan->id], 'test');

        $ofrecidas = WhiteCompanyCatalogFeeOptions::catalogFeesForCompany($company);

        expect($ofrecidas)->not->toHaveCount(0);

        foreach ($ofrecidas as $fee) {
            expect((int) $fee->plan_id)->toBe((int) $plan->id);
        }
    } finally {
        DB::rollBack();
    }
});

it('al quitar un plan retira también sus netas pactadas', function (): void {
    $company = WhiteCompany::query()->first();

    $plan = Plan::query()
        ->whereHas('benefitPlans')
        ->whereIn('id', Fee::query()->whereNotNull('plan_id')->select('plan_id'))
        ->first();

    if ($company === null || $plan === null) {
        expect(true)->toBeTrue();

        return;
    }

    DB::beginTransaction();

    try {
        WhiteCompanyPlanAssignment::assign($company, [(int) $plan->id], 'test');

        $fee = Fee::query()->forPlan((int) $plan->id)->first();
        $company->negotiatedFees()->firstOrCreate(
            ['fee_id' => $fee->id],
            ['sale_price' => 100, 'neta' => 80, 'status' => 'ACTIVO', 'created_by' => 'test'],
        );

        $resumen = WhiteCompanyPlanAssignment::unassign($company, (int) $plan->id);

        expect($resumen['netas_retiradas'])->toBeGreaterThan(0)
            ->and($company->assignedPlans()->where('plan_id', $plan->id)->exists())->toBeFalse()
            ->and($company->negotiatedFees()->where('fee_id', $fee->id)->exists())->toBeFalse();
    } finally {
        DB::rollBack();
    }
});

it('mantiene la asignación de planes separada de la matriz de negociación', function (): void {
    $base = dirname(__DIR__, 2).'/app/Filament/Business/Resources/WhiteCompanies/RelationManagers/';

    $matriz = file_get_contents($base.'NegotiatedFeesRelationManager.php');
    $planes = file_get_contents($base.'AssignedPlansRelationManager.php');

    // La matriz sigue siendo la carga de netas tarifa por tarifa, sin asignar planes.
    expect($matriz)
        ->toContain("->label('Agregar netas')")
        ->not->toContain('assignPlan')
        ->and($planes)
        ->toContain("Action::make('assignPlans')")
        ->toContain("->label('Asignar plan')")
        ->toContain('WhiteCompanyPlanAssignment::assign')
        ->toContain('BusinessFilamentActionPermissionRegistry::ASSIGN_WHITE_COMPANY_PLAN');
});

it('registra los dos relation managers en el recurso, primero la asignación', function (): void {
    $source = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Business/Resources/WhiteCompanies/WhiteCompanyResource.php',
    );

    expect($source)
        ->toContain('AssignedPlansRelationManager::class')
        ->toContain('NegotiatedFeesRelationManager::class');

    expect(strpos($source, 'AssignedPlansRelationManager::class,'))
        ->toBeLessThan(strpos($source, 'NegotiatedFeesRelationManager::class,'));
});

it('usa un permiso propio para asignar planes, distinto del de la matriz', function (): void {
    $permisos = BusinessFilamentActionPermissionRegistry::all();

    expect(BusinessFilamentActionPermissionRegistry::ASSIGN_WHITE_COMPANY_PLAN)
        ->not->toBe(BusinessFilamentActionPermissionRegistry::MANAGE_WHITE_COMPANY_NEGOTIATED_FEES)
        ->and($permisos)->toHaveKey(BusinessFilamentActionPermissionRegistry::ASSIGN_WHITE_COMPANY_PLAN);
});
