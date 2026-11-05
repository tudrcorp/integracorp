<?php

declare(strict_types=1);

use App\Filament\Administration\Resources\DataChangeAudits\Pages\ListDataChangeAudits;
use App\Models\Affiliate;
use App\Models\Affiliation;
use App\Models\DataChangeAudit;
use App\Models\User;
use App\Observers\DataChangeAuditObserver;
use App\Support\Audit\DataChangeAuditContext;
use App\Support\Audit\DataChangeAuditPresenter;
use App\Support\Audit\DataChangeAuditRegistry;
use App\Support\Audit\DataChangeReverter;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Crea afiliaciones y movimientos de prueba: todo dentro de una transacción que siempre se revierte.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    DataChangeAuditObserver::flush();
    DataChangeAuditContext::reset();
    Filament::setCurrentPanel('administration');
    test()->actingAs(auditSuperadmin());
});

afterEach(fn () => DB::rollBack());

function auditSuperadmin(): User
{
    return User::factory()->make(['id' => 999_999_301, 'name' => 'QA AUDITORIA', 'email' => 'qa.auditoria@tudrencasa.com', 'status' => 'ACTIVO', 'departament' => ['SUPERADMIN', 'ADMINISTRACION']]);
}

function auditAffiliation(): Affiliation
{
    return Affiliation::query()->forceCreate([
        'code' => 'PEST-AUD-'.Str::upper(Str::random(8)),
        'code_agency' => 'TDG-100',
        'owner_code' => 'TDG-100',
        'status' => 'ACTIVA',
        'agent_id' => 557,
        'plan_id' => 1,
    ]);
}

it('registra cada modificación con el valor de antes y de después, quién la hizo y si afecta cálculos', function (): void {
    $affiliation = auditAffiliation();
    $affiliation->update(['agent_id' => null, 'code_agency' => 'TDG-135', 'observations' => 'nota']);

    $created = DataChangeAudit::query()->where('entity', 'affiliation')->where('entity_id', $affiliation->getKey())->where('event', 'created')->first();
    $updated = DataChangeAudit::query()->where('entity', 'affiliation')->where('entity_id', $affiliation->getKey())->where('event', 'updated')->first();

    expect($created)->not->toBeNull()
        ->and($created->changes['code'])->toEqual(['old' => null, 'new' => $affiliation->code])
        ->and($updated->changes)->toEqual([
            'code_agency' => ['old' => 'TDG-100', 'new' => 'TDG-135'],
            'agent_id' => ['old' => '557', 'new' => null],
            'observations' => ['old' => null, 'new' => 'nota'],
        ])
        ->and($updated->affects_calculations)->toBeTrue()
        ->and($updated->user_name)->toBe('QA AUDITORIA')
        ->and($updated->entity_label)->toBe($affiliation->code)
        ->and($updated->batch_id)->toBe($created->batch_id)
        ->and(DataChangeAuditPresenter::fieldsSummary($updated->changes))->toContain('Agencia', 'Agente', 'Observations')
        ->and(DataChangeAuditPresenter::fieldsSummary(['a' => 1, 'b' => 1, 'c' => 1, 'd' => 1, 'e' => 1]))->toBe('A, B, C y 2 más');
});

it('un cambio que no toca cálculos queda sin la marca, y el afiliado queda ligado a su afiliación', function (): void {
    $affiliation = auditAffiliation();
    $affiliate = Affiliate::query()->forceCreate([
        'affiliation_id' => $affiliation->getKey(), 'full_name' => 'ANA PRUEBA', 'nro_identificacion' => '12345678', 'relationship' => 'TITULAR', 'sex' => 'F', 'status' => 'ACTIVO',
    ]);
    $affiliate->update(['phone' => '04141234567']);

    $audit = DataChangeAudit::query()->where('entity', 'affiliate')->where('entity_id', $affiliate->getKey())->latest('id')->first();

    expect($audit->event)->toBe('updated')
        ->and($audit->affects_calculations)->toBeFalse()
        ->and($audit->parent_label)->toBe($affiliation->code)
        ->and(DataChangeAudit::query()->where('parent_label', $affiliation->code)->count())->toBe(2);
});

it('nunca guarda contraseñas ni tokens, y recorta los textos larguísimos con su huella', function (): void {
    expect(DataChangeAuditObserver::normalize('registration_token', 'abc123'))->toBe('••••')
        ->and(DataChangeAuditObserver::normalize('observations', str_repeat('x', 3000)))->toContain('[recortado · sha1 '.sha1(str_repeat('x', 3000)).']')
        ->and(DataChangeAuditObserver::normalize('status', true))->toBe('1')
        ->and(DataChangeAuditObserver::normalize('plan_id', 5))->toBe('5');

    expect(DataChangeAuditRegistry::MASKED_FIELDS)->toContain('password', 'registration_token', 'token');
});

it('revierte un cambio, lo deja registrado con su razón y no permite revertirlo dos veces', function (): void {
    $affiliation = auditAffiliation();
    $affiliation->update(['agent_id' => null]);
    $audit = DataChangeAudit::query()->where('entity_id', $affiliation->getKey())->where('entity', 'affiliation')->latest('id')->first();

    $revert = DataChangeReverter::revert($audit, 'El agente se quitó por error', auditSuperadmin());

    expect((int) $affiliation->fresh()->agent_id)->toBe(557)
        ->and($revert->event)->toBe(DataChangeAudit::EVENT_REVERTED)
        ->and($revert->reverts_audit_id)->toBe($audit->getKey())
        ->and($revert->revert_reason)->toBe('El agente se quitó por error')
        ->and($audit->fresh()->reverted_by_audit_id)->toBe($revert->getKey())
        ->and(fn () => DataChangeReverter::revert($audit->fresh(), 'Otra vez por error', auditSuperadmin()))->toThrow(InvalidArgumentException::class, 'ya fue revertido')
        ->and(fn () => DataChangeReverter::revert($revert, 'corta', auditSuperadmin()))->toThrow(InvalidArgumentException::class, 'razón');
});

it('no revierte si el campo volvió a cambiar después: dice quién y cuándo', function (): void {
    $affiliation = auditAffiliation();
    $affiliation->update(['code_agency' => 'TDG-200']);
    $first = DataChangeAudit::query()->where('entity_id', $affiliation->getKey())->where('entity', 'affiliation')->latest('id')->first();
    $affiliation->update(['code_agency' => 'TDG-300']);

    $preview = DataChangeReverter::preview($first);

    expect($preview['revertible'])->toBeFalse()
        ->and($preview['conflicts'][0])->toContain('Agencia cambió después: QA AUDITORIA')
        ->and(fn () => DataChangeReverter::revert($first, 'Intento con conflicto', auditSuperadmin()))->toThrow(InvalidArgumentException::class, 'pisar cambios posteriores')
        ->and($affiliation->fresh()->code_agency)->toBe('TDG-300');
});

it('restaura un registro con eliminación lógica y bloquea las creaciones', function (): void {
    $affiliation = auditAffiliation();
    $created = DataChangeAudit::query()->where('entity_id', $affiliation->getKey())->where('entity', 'affiliation')->where('event', 'created')->first();
    $affiliation->delete();
    $deleted = DataChangeAudit::query()->where('entity_id', $affiliation->getKey())->where('entity', 'affiliation')->where('event', 'deleted')->first();

    expect(DataChangeReverter::preview($created)['blocker'])->toContain('Revertir una creación sería borrar')
        ->and(DataChangeReverter::preview($deleted)['mode'])->toBe('restore');

    DataChangeReverter::revert($deleted, 'Se eliminó la afiliación equivocada', auditSuperadmin());

    expect(Affiliation::query()->find($affiliation->getKey()))->not->toBeNull()
        ->and(DataChangeAudit::query()->where('reverts_audit_id', $deleted->getKey())->value('event'))->toBe(DataChangeAudit::EVENT_REVERTED);
});

it('la pantalla lista los movimientos, busca por código, muestra el detalle y revierte con razón', function (): void {
    $affiliation = auditAffiliation();
    $affiliation->update(['agent_id' => null]);
    $audit = DataChangeAudit::query()->where('entity_id', $affiliation->getKey())->where('entity', 'affiliation')->latest('id')->first();

    Livewire::test(ListDataChangeAudits::class)
        ->assertOk()
        ->searchTable($affiliation->code)
        ->assertCanSeeTableRecords([$audit])
        ->mountTableAction('detail', $audit)
        ->assertMountedActionModalSee(['Qué cambió', 'Historia de este registro', 'Agente']);

    Livewire::test(ListDataChangeAudits::class)
        ->mountTableAction('revert', $audit)
        ->set('mountedActions.0.data.reason', 'Prueba desde la pantalla de auditoría')
        ->callMountedTableAction()
        ->assertNotified('Cambio revertido');

    expect((int) $affiliation->fresh()->agent_id)->toBe(557);
});

it('ver y revertir tienen permisos propios, y la retención es de 24 meses', function (): void {
    expect(BusinessFilamentActionPermissionRegistry::REVERT_DATA_CHANGES)->toBe('revertir-cambios-auditados')
        ->and(BusinessFilamentActionPermissionRegistry::all())->toHaveKey('revertir-cambios-auditados')
        ->and((string) file_get_contents(dirname(__DIR__, 2).'/app/Support/Filament/DepartmentNavigationPermissionRegistry.php'))
        ->toContain("DataChangeAuditResource::class => ['auditoria-de-cambios']")
        ->and(DataChangeAudit::RETENTION_MONTHS)->toBe(24)
        ->and((string) file_get_contents(dirname(__DIR__, 2).'/routes/console.php'))->toContain("Schedule::command('audit:purge-data-changes')")
        ->and(count(DataChangeAuditRegistry::entities()))->toBe(18);

    test()->actingAs(User::factory()->make(['id' => 999_999_302, 'name' => 'QA SIN PERMISO', 'email' => 'qa.sin@tudrencasa.com', 'status' => 'ACTIVO', 'departament' => ['ADMINISTRACION']]));

    expect(App\Filament\Administration\Resources\DataChangeAudits\Tables\DataChangeAuditsTable::canRevert())->toBeFalse();
});

it('los cambios masivos por query builder también quedan auditados, fila por fila, con el mismo resultado', function (): void {
    $affiliation = auditAffiliation();
    $ids = collect(['ANA', 'LUIS'])->map(fn (string $name): int => (int) Affiliate::query()->forceCreate([
        'affiliation_id' => $affiliation->getKey(), 'full_name' => $name.' MASIVO', 'nro_identificacion' => (string) random_int(1_000_000, 9_999_999),
        'relationship' => 'TITULAR', 'sex' => 'F', 'status' => 'ACTIVO', 'fee' => 100,
    ])->getKey());

    $affected = App\Support\Audit\DataChangeAuditor::update('affiliate', Affiliate::query()->whereIn('id', $ids), [
        'status' => 'EXCLUIDO',
        'total_amount' => DB::raw('ROUND(COALESCE(fee, 0) / 4.0, 2)'),
    ]);

    $audits = DataChangeAudit::query()->where('entity', 'affiliate')->whereIn('entity_id', $ids)->where('event', 'updated')->get();

    expect($affected)->toBe(2)
        ->and($audits)->toHaveCount(2)
        ->and($audits->first()->changes['status'])->toEqual(['old' => 'ACTIVO', 'new' => 'EXCLUIDO'])
        ->and((float) $audits->first()->changes['total_amount']['new'])->toBe(25.0)
        ->and($audits->first()->affects_calculations)->toBeTrue()
        ->and($audits->first()->entity_label)->toContain('MASIVO');

    $deleted = App\Support\Audit\DataChangeAuditor::delete('affiliate', DB::table('affiliates')->where('id', $ids->first()));
    $snapshot = DataChangeAudit::query()->where('entity', 'affiliate')->where('entity_id', $ids->first())->where('event', 'deleted')->first();

    expect($deleted)->toBe(1)
        ->and($snapshot->changes['full_name']['old'])->toBe('ANA MASIVO')
        ->and(DataChangeReverter::preview($snapshot)['blocker'])->toContain('ya no existe');
});
