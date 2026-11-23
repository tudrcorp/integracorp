<?php

declare(strict_types=1);

use App\Models\CorporatePopulationUpdate;
use App\Models\User;
use App\Support\AffiliationCorporates\Population\PopulationUpdateApplier;
use App\Support\AffiliationCorporates\Population\PopulationUpdateStuckRelease;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

/**
 * «Liberar carga trabada» en la población corporativa. Escribe dentro de una
 * transacción que siempre se revierte.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    config(['cache.default' => 'array']);

    $this->update = CorporatePopulationUpdate::query()->orderBy('id')->first();
    $this->admin = User::query()->where('is_superAdmin', 1)->where('status', 'ACTIVO')->first();

    if ($this->update === null || $this->admin === null) {
        $this->markTestSkipped('Faltan una carga de población o un superadmin en la base.');
    }
});

afterEach(fn () => DB::rollBack());

function stuckPopulationUpdate(CorporatePopulationUpdate $update, string $status, int $minutesAgo, string $itemsStatus = 'PENDING'): CorporatePopulationUpdate
{
    $update->forceFill(['status' => $status, 'approved_by' => 1, 'approved_by_name' => 'QA', 'approved_at' => now()->subMinutes($minutesAgo)])->save();
    DB::table('corporate_population_updates')->where('id', $update->getKey())->update(['updated_at' => now()->subMinutes($minutesAgo)]);
    DB::table('corporate_population_update_items')->where('corporate_population_update_id', $update->getKey())->update(['apply_status' => $itemsStatus]);

    return $update->fresh();
}

function populationAnalyst(): User
{
    return (new User)->forceFill(['id' => 999_999_405, 'name' => 'QA NEGOCIOS', 'email' => 'qa.poblacion@tudrencasa.com', 'status' => 'ACTIVO', 'departament' => ['NEGOCIOS'], 'is_superAdmin' => 0]);
}

it('no se libera antes de 30 minutos: puede seguir procesándose', function (): void {
    $update = stuckPopulationUpdate($this->update, CorporatePopulationUpdate::STATUS_APPLYING, 10);

    expect(PopulationUpdateStuckRelease::blockingReason($update))->toContain('Espere al menos '.PopulationUpdateStuckRelease::STALE_MINUTES);
});

it('libera una carga trabada en Aplicando: vuelve a Analizada, sin aprobador y con nota', function (): void {
    $update = stuckPopulationUpdate($this->update, CorporatePopulationUpdate::STATUS_APPLYING, 600);

    $released = PopulationUpdateStuckRelease::release($update, $this->admin, 'El trabajo en cola se perdió sin ejecutarse');

    expect($released->status)->toBe(CorporatePopulationUpdate::STATUS_ANALYZED)
        ->and($released->approved_by)->toBeNull()
        ->and($released->error)->toContain('Liberada por')
        ->and(DB::table('corporate_population_update_items')->where('corporate_population_update_id', $update->getKey())->where('apply_status', '<>', 'PENDING')->count())->toBe(0);
});

it('libera una carga trabada en Revirtiendo: vuelve a Aplicada', function (): void {
    $update = stuckPopulationUpdate($this->update, CorporatePopulationUpdate::STATUS_ROLLING_BACK, 120, 'APPLIED');

    expect(PopulationUpdateStuckRelease::release($update, $this->admin, 'La reversión en cola se perdió sin ejecutarse')->status)
        ->toBe(CorporatePopulationUpdate::STATUS_APPLIED);
});

it('no libera si alguna fila ya cambió en los afiliados', function (): void {
    $update = stuckPopulationUpdate($this->update, CorporatePopulationUpdate::STATUS_APPLYING, 600);
    $itemId = DB::table('corporate_population_update_items')->where('corporate_population_update_id', $update->getKey())->value('id');

    if ($itemId === null) {
        $this->markTestSkipped('La carga no tiene filas.');
    }

    DB::table('corporate_population_update_items')->where('id', $itemId)->update(['apply_status' => 'APPLIED']);

    expect(PopulationUpdateStuckRelease::blockingReason($update->fresh()))->toContain('ya cambió');
});

it('no libera mientras el proceso corre (tiene el candado de la afiliación)', function (): void {
    $update = stuckPopulationUpdate($this->update, CorporatePopulationUpdate::STATUS_APPLYING, 600);
    $lock = Cache::lock(PopulationUpdateApplier::lockKey((int) $update->affiliation_corporate_id), 60);
    $lock->get();

    try {
        expect(fn () => PopulationUpdateStuckRelease::release($update, $this->admin, 'Prueba con el proceso corriendo'))
            ->toThrow(RuntimeException::class, 'procesando en este momento');
    } finally {
        $lock->release();
    }
});

it('solo un superadmin la libera, con razón obligatoria', function (): void {
    $update = stuckPopulationUpdate($this->update, CorporatePopulationUpdate::STATUS_APPLYING, 600);

    expect(PopulationUpdateStuckRelease::canRelease(populationAnalyst()))->toBeFalse()
        ->and(PopulationUpdateStuckRelease::canRelease(null))->toBeFalse()
        ->and(fn () => PopulationUpdateStuckRelease::release($update, populationAnalyst(), 'Intento de un analista de Negocios'))
        ->toThrow(RuntimeException::class, 'superadmin')
        ->and(fn () => PopulationUpdateStuckRelease::release($update, $this->admin, 'corta'))
        ->toThrow(RuntimeException::class, 'mínimo');
});

it('la pantalla de la carga ofrece la acción y la lista avisa de la carga trabada', function (): void {
    $detail = (string) file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Pages/CorporatePopulationUpdateDetail.php');
    $list = (string) file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Pages/CorporatePopulationUpdates.php');

    expect($detail)->toContain("Action::make('releaseStuck')")
        ->toContain('PopulationUpdateStuckRelease::release(')
        ->and($list)->toContain('Parece trabada: ábrala para liberarla.');
});
