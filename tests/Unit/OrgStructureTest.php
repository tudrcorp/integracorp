<?php

declare(strict_types=1);

use App\Enums\OrgAssignmentRole;
use App\Enums\OrgPositionKind;
use App\Enums\OrgStructureVersionStatus;
use App\Jobs\NotifyOrgStructureReviewJob;
use App\Models\OrgPosition;
use App\Models\OrgPositionAssignment;
use App\Models\OrgStructureEvent;
use App\Models\OrgStructureVersion;
use App\Models\Permission;
use App\Models\RrhhCargo;
use App\Models\RrhhColaborador;
use App\Models\RrhhDepartamento;
use App\Models\User;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\OrgStructure\OrgChartPayload;
use App\Support\OrgStructure\OrgChartPdfExporter;
use App\Support\OrgStructure\OrgStructureAccess;
use App\Support\OrgStructure\OrgStructureComparator;
use App\Support\OrgStructure\OrgStructureConflictException;
use App\Support\OrgStructure\OrgStructureDrafts;
use App\Support\OrgStructure\OrgStructureEditLock;
use App\Support\OrgStructure\OrgStructureEditor;
use App\Support\OrgStructure\OrgStructureException;
use App\Support\OrgStructure\OrgStructureHierarchy;
use App\Support\OrgStructure\OrgStructurePublisher;
use App\Support\OrgStructure\OrgStructureValidator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

uses(Tests\TestCase::class);

/**
 * Escriben en la base (RRHH y organigrama): transacción que siempre se revierte y
 * cola simulada. No queda nada en la base.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    Queue::fake();
});

afterEach(function (): void {
    DB::rollBack();
});

function orgSuperAdmin(int $id = 990001): User
{
    $user = User::factory()->make(['id' => $id, 'name' => 'Super Pest '.$id, 'departament' => ['SUPERADMIN', 'ADMINISTRACION'], 'status' => 'ACTIVO']);
    $user->setRelation('permissions', new Collection);

    return $user;
}

function orgEditor(int $id = 990002): User
{
    $user = User::factory()->make(['id' => $id, 'name' => 'Analista RRHH Pest', 'departament' => ['NEGOCIOS'], 'status' => 'ACTIVO']);
    $user->setRelation('permissions', new Collection([
        new Permission(['slug' => BusinessFilamentActionPermissionRegistry::EDIT_ORG_STRUCTURE, 'module' => 'NEGOCIOS']),
    ]));

    return $user;
}

function orgViewerOnly(): User
{
    $user = User::factory()->make(['id' => 990003, 'name' => 'Colaborador Pest', 'departament' => ['ADMINISTRACION'], 'status' => 'ACTIVO']);
    $user->setRelation('permissions', new Collection);

    return $user;
}

function orgColaborador(string $name, ?RrhhCargo $cargo = null, string $status = 'activo'): RrhhColaborador
{
    return RrhhColaborador::query()->create([
        'fullName' => $name,
        'cargo_id' => $cargo?->id,
        'departmento_id' => $cargo?->departamento_id,
        'status' => $status,
        'created_by' => 'pest',
    ]);
}

/**
 * Vigente mínima: Presidencia → Operaciones, con titulares. Devuelve ids útiles.
 *
 * @return array{version: OrgStructureVersion, root: OrgPosition, ops: OrgPosition, boss: RrhhColaborador, manager: RrhhColaborador}
 */
function orgCurrentStructure(): array
{
    $version = OrgStructureVersion::factory()->current()->create(['name' => 'Vigente Pest']);
    $root = OrgPosition::factory()->create(['version_id' => $version->id, 'title' => 'Presidencia', 'parent_id' => null]);
    $ops = OrgPosition::factory()->create(['version_id' => $version->id, 'title' => 'Gerencia de Operaciones', 'parent_id' => $root->id]);
    $boss = orgColaborador('Laura Pérez Pest');
    $manager = orgColaborador('Ana Colmenares Pest');

    OrgPositionAssignment::factory()->create(['version_id' => $version->id, 'position_id' => $root->id, 'rrhh_colaborador_id' => $boss->id]);
    OrgPositionAssignment::factory()->create(['version_id' => $version->id, 'position_id' => $ops->id, 'rrhh_colaborador_id' => $manager->id]);

    return compact('version', 'root', 'ops', 'boss', 'manager');
}

function orgDraftFor(User $user, string $name = 'Borrador Pest'): OrgStructureVersion
{
    $draft = OrgStructureDrafts::createFromCurrent($user, $name);
    OrgStructureEditLock::acquire($draft->id, $user);

    return $draft->refresh();
}

function orgPositionIn(OrgStructureVersion $version, string $title): OrgPosition
{
    return OrgPosition::query()->where('version_id', $version->id)->where('title', $title)->firstOrFail();
}

// ------------------------------------------------------------------ permisos

it('deja ver el organigrama a colaboradores internos y no a usuarios de proveedores', function (): void {
    expect(OrgStructureAccess::canView(orgViewerOnly()))->toBeTrue()
        ->and(OrgStructureAccess::canView(null))->toBeFalse();

    $supplier = User::factory()->make(['id' => 990010, 'supplier_id' => 15, 'departament' => ['OPERACIONES']]);
    $amd = User::factory()->make(['id' => 990011, 'is_proveedor_amd' => true, 'departament' => ['OPERACIONES']]);

    expect(OrgStructureAccess::canView($supplier))->toBeFalse()
        ->and(OrgStructureAccess::canView($amd))->toBeFalse();
});

it('exige el permiso de edición o SUPERADMIN para editar, y solo SUPERADMIN publica y aprueba', function (): void {
    expect(OrgStructureAccess::canEdit(orgViewerOnly()))->toBeFalse()
        ->and(OrgStructureAccess::canEdit(orgEditor()))->toBeTrue()
        ->and(OrgStructureAccess::canEdit(orgSuperAdmin()))->toBeTrue()
        ->and(OrgStructureAccess::canPublishDirectly(orgEditor()))->toBeFalse()
        ->and(OrgStructureAccess::canPublishDirectly(orgSuperAdmin()))->toBeTrue()
        ->and(OrgStructureAccess::canReview(orgEditor()))->toBeFalse();
});

it('registra el organigrama solo en paneles internos, nunca en agentes, master ni general', function (): void {
    foreach (['Business', 'Administration', 'Operations', 'Marketing', 'Projects', 'Metrics'] as $panel) {
        expect(file_exists(app_path("Filament/{$panel}/Pages/Organigrama.php")))->toBeTrue($panel);
    }

    expect(file_exists(app_path('Filament/Telemedicina/Pages/Organigrama.php')))->toBeFalse()
        ->and(file_exists(app_path('Filament/Pages/Organigrama.php')))->toBeFalse();

    foreach (['Agents', 'Master', 'General'] as $panel) {
        expect(glob(app_path("Filament/{$panel}").'/**/Organigrama*.php') ?: [])->toBeEmpty($panel);
    }

    expect(\App\Support\Filament\DepartmentNavigationPermissionRegistry::isManagedOutsideNavigationPermissions(\App\Filament\Business\Pages\Organigrama::class))->toBeTrue()
        ->and(\App\Support\Filament\DepartmentNavigationPermissionRegistry::isManagedOutsideNavigationPermissions(\App\Filament\Business\Pages\EstructuraOrganizacional::class))->toBeTrue()
        ->and(\App\Support\Filament\DepartmentNavigationPermissionRegistry::isManagedOutsideNavigationPermissions(\App\Filament\Administration\Pages\CentroDeReportes::class))->toBeFalse()
        ->and(BusinessFilamentActionPermissionRegistry::modulesForSlug(BusinessFilamentActionPermissionRegistry::EDIT_ORG_STRUCTURE))->toBe(['NEGOCIOS'])
        ->and(BusinessFilamentActionPermissionRegistry::modulesForSlug(BusinessFilamentActionPermissionRegistry::EXPORT_ORG_CHART))->toBe(['NEGOCIOS']);
});

// ----------------------------------------------------------------- borradores

it('el organigrama nuevo empieza sin puestos y el primero que se agrega es el principal', function (): void {
    $editor = orgEditor();
    $draft = OrgStructureDrafts::createEmpty($editor, 'Organigrama Pest');
    OrgStructureEditLock::acquire($draft->id, $editor);

    expect(OrgPosition::query()->where('version_id', $draft->id)->count())->toBe(0)
        ->and(OrgChartPayload::compute($draft)['issues'][0]['code'])->toBe('empty');

    $root = OrgStructureEditor::addPosition($draft->id, $editor, 0, null, 'Presidencia');

    expect(OrgPosition::query()->findOrFail($root['position_id'])->parent_id)->toBeNull();

    expect(fn () => OrgStructureEditor::addPosition($draft->id, $editor, $root['lock_version'], null, 'Otro principal'))
        ->toThrow(OrgStructureException::class, 'ya tiene un puesto principal');
});

it('crea el lienzo vacío y, si hay vigente, deja el borrador basado en ella para poder reemplazarla', function (): void {
    $current = orgCurrentStructure();

    $empty = OrgStructureDrafts::createEmpty(orgEditor(), 'Lienzo Pest');

    expect(OrgPosition::query()->where('version_id', $empty->id)->count())->toBe(0)
        ->and($empty->based_on_version_id)->toBe($current['version']->id);
});

it('crea un puesto desde un cargo de la paleta y trae a su gente sin puesto', function (): void {
    orgCurrentStructure();
    $editor = orgEditor();
    $draft = orgDraftFor($editor);
    $root = orgPositionIn($draft, 'Presidencia');
    $depto = RrhhDepartamento::query()->create(['description' => 'Compras Pest']);
    $cargo = RrhhCargo::query()->create(['departamento_id' => $depto->id, 'description' => 'Comprador Pest', 'created_by' => 'pest']);
    $first = orgColaborador('Comprador Uno Pest', $cargo);
    $second = orgColaborador('Comprador Dos Pest', $cargo);
    orgColaborador('Comprador Inactivo Pest', $cargo, 'inactivo');

    $result = OrgStructureEditor::addFromCargo($draft->id, $editor, 0, $root->id, $cargo->id);
    $position = OrgPosition::query()->findOrFail($result['position_id']);

    expect($result['assigned'])->toBe(2)
        ->and($position->title)->toBe('Comprador Pest')
        ->and($position->seats)->toBe(2)
        ->and($position->rrhh_departamento_id)->toBe($depto->id)
        ->and(OrgPositionAssignment::query()->where('position_id', $position->id)->pluck('rrhh_colaborador_id')->sort()->values()->all())->toBe(collect([$first->id, $second->id])->sort()->values()->all());

    $again = OrgStructureEditor::addFromCargo($draft->id, $editor, $result['lock_version'], $root->id, $cargo->id);

    expect($again['assigned'])->toBe(0);
});

it('inserta un puesto nuevo en la posición pedida entre sus hermanos', function (): void {
    orgCurrentStructure();
    $editor = orgEditor();
    $draft = orgDraftFor($editor);
    $root = orgPositionIn($draft, 'Presidencia');
    $last = OrgStructureEditor::addPosition($draft->id, $editor, 0, $root->id, 'Al final');
    $first = OrgStructureEditor::addPosition($draft->id, $editor, $last['lock_version'], $root->id, 'Al principio', 'LINEA', null, 0);

    $order = OrgPosition::query()->where('version_id', $draft->id)->where('parent_id', $root->id)->orderBy('sort_order')->pluck('title')->all();

    expect($order)->toBe(['Al principio', 'Gerencia de Operaciones', 'Al final'])
        ->and($first['position_id'])->toBeInt();
});

it('quita un puesto con gente solo si se pide retirar a sus ocupantes en la misma operación', function (): void {
    $current = orgCurrentStructure();
    $editor = orgEditor();
    $draft = orgDraftFor($editor);
    $ops = orgPositionIn($draft, 'Gerencia de Operaciones');

    expect(fn () => OrgStructureEditor::removePosition($draft->id, $editor, 0, $ops->id))
        ->toThrow(OrgStructureException::class, 'tiene ocupantes');

    OrgStructureEditor::removePosition($draft->id, $editor, 0, $ops->id, true);

    $payload = OrgChartPayload::compute($draft->refresh());

    expect(OrgPosition::query()->find($ops->id))->toBeNull()
        ->and(collect($payload['unassigned'])->pluck('id')->all())->toContain($current['manager']->id)
        ->and(RrhhColaborador::query()->find($current['manager']->id))->not->toBeNull();
});

it('el área de un puesto con cargo de RRHH la define el cargo', function (): void {
    orgCurrentStructure();
    $editor = orgEditor();
    $draft = orgDraftFor($editor);
    $ops = orgPositionIn($draft, 'Gerencia de Operaciones');
    $depto = RrhhDepartamento::query()->create(['description' => 'Gerencias Pest']);
    $other = RrhhDepartamento::query()->create(['description' => 'Otra Pest']);
    $cargo = RrhhCargo::query()->create(['departamento_id' => $depto->id, 'description' => 'Gerente Pest', 'created_by' => 'pest']);

    $lock = OrgStructureEditor::updatePosition($draft->id, $editor, 0, $ops->id, ['rrhh_cargo_id' => $cargo->id]);

    expect($ops->refresh()->rrhh_departamento_id)->toBe($depto->id);

    expect(fn () => OrgStructureEditor::updatePosition($draft->id, $editor, $lock, $ops->id, ['rrhh_departamento_id' => $other->id]))
        ->toThrow(OrgStructureException::class, 'la define el cargo');

    $lock = OrgStructureEditor::updatePosition($draft->id, $editor, $lock, $ops->id, ['rrhh_cargo_id' => null]);
    OrgStructureEditor::updatePosition($draft->id, $editor, $lock, $ops->id, ['rrhh_departamento_id' => $other->id]);

    expect($ops->refresh()->rrhh_departamento_id)->toBe($other->id);
});

it('el ejemplo del estudio se arma en memoria sin tocar la base', function (): void {
    $before = OrgStructureVersion::query()->count();
    $config = \App\Support\OrgStructure\OrgStructureDemo::config();

    expect($config['demo'])->toBeTrue()
        ->and($config['payload']['nodes'])->not->toBeEmpty()
        ->and($config['publishAction'])->toBeNull()
        ->and(OrgStructureVersion::query()->count())->toBe($before);
});

it('copia la vigente al borrador conservando claves, jerarquía y ocupantes', function (): void {
    $current = orgCurrentStructure();
    $draft = OrgStructureDrafts::createFromCurrent(orgEditor(), 'Reestructuración Pest');

    $copiedOps = orgPositionIn($draft, 'Gerencia de Operaciones');
    $copiedRoot = orgPositionIn($draft, 'Presidencia');

    expect($draft->based_on_version_id)->toBe($current['version']->id)
        ->and($copiedOps->position_key)->toBe($current['ops']->position_key)
        ->and($copiedOps->parent_id)->toBe($copiedRoot->id)
        ->and($copiedOps->id)->not->toBe($current['ops']->id)
        ->and(OrgPositionAssignment::query()->where('position_id', $copiedOps->id)->value('rrhh_colaborador_id'))->toBe($current['manager']->id);
});

it('rechaza crear borradores a quien no tiene el permiso de edición', function (): void {
    orgCurrentStructure();

    OrgStructureDrafts::createFromCurrent(orgViewerOnly(), 'Intento');
})->throws(OrgStructureException::class, 'No tienes permiso');

// ------------------------------------------------------------------- editor

it('agrega, mueve y reordena puestos subiendo el lock_version y dejando bitácora', function (): void {
    orgCurrentStructure();
    $editor = orgEditor();
    $draft = orgDraftFor($editor);
    $root = orgPositionIn($draft, 'Presidencia');
    $ops = orgPositionIn($draft, 'Gerencia de Operaciones');

    $added = OrgStructureEditor::addPosition($draft->id, $editor, 0, $root->id, '  Gerencia   de Negocios ');
    $negocios = OrgPosition::query()->findOrFail($added['position_id']);

    expect($added['lock_version'])->toBe(1)
        ->and($negocios->title)->toBe('Gerencia de Negocios')
        ->and($negocios->sort_order)->toBe(1);

    $lock = OrgStructureEditor::movePosition($draft->id, $editor, 1, $negocios->id, $root->id, 0);

    expect($lock)->toBe(2)
        ->and($negocios->refresh()->sort_order)->toBe(0)
        ->and($ops->refresh()->sort_order)->toBe(1);

    $lock = OrgStructureEditor::movePosition($draft->id, $editor, 2, $negocios->id, $ops->id, 0);

    expect($lock)->toBe(3)
        ->and($negocios->refresh()->parent_id)->toBe($ops->id)
        ->and(OrgStructureEvent::query()->where('version_id', $draft->id)->where('action', 'position_moved')->count())->toBe(2);
});

it('impide que un puesto quede bajo su propio subordinado', function (): void {
    orgCurrentStructure();
    $editor = orgEditor();
    $draft = orgDraftFor($editor);
    $ops = orgPositionIn($draft, 'Gerencia de Operaciones');
    $child = OrgStructureEditor::addPosition($draft->id, $editor, 0, $ops->id, 'Coordinación de Proveedores');

    OrgStructureEditor::movePosition($draft->id, $editor, 1, $ops->id, $child['position_id'], 0);
})->throws(OrgStructureException::class, 'depende de él');

it('no deja que un puesto staff tenga subordinados', function (): void {
    orgCurrentStructure();
    $editor = orgEditor();
    $draft = orgDraftFor($editor);
    $root = orgPositionIn($draft, 'Presidencia');
    $staff = OrgStructureEditor::addPosition($draft->id, $editor, 0, $root->id, 'Asesoría Legal', OrgPositionKind::Staff->value);

    OrgStructureEditor::addPosition($draft->id, $editor, 1, $staff['position_id'], 'Abogado');
})->throws(OrgStructureException::class, 'staff');

it('rechaza un cambio con lock_version viejo en lugar de pisar el cambio ajeno', function (): void {
    orgCurrentStructure();
    $editor = orgEditor();
    $draft = orgDraftFor($editor);
    $root = orgPositionIn($draft, 'Presidencia');

    OrgStructureEditor::addPosition($draft->id, $editor, 0, $root->id, 'Primero');
    OrgStructureEditor::addPosition($draft->id, $editor, 0, $root->id, 'Con lock viejo');
})->throws(OrgStructureConflictException::class);

it('bloquea la edición mientras otro usuario tiene el candado del borrador', function (): void {
    orgCurrentStructure();
    $editor = orgEditor();
    $draft = orgDraftFor($editor);
    $other = orgEditor(990099);

    expect(OrgStructureEditLock::acquire($draft->id, $other))->not->toBeNull();

    OrgStructureEditor::addPosition($draft->id, $other, 0, orgPositionIn($draft, 'Presidencia')->id, 'Intruso');
})->throws(OrgStructureException::class, 'lo está editando');

it('no quita un puesto con gente y, si está vacío, sube a sus subordinados', function (): void {
    orgCurrentStructure();
    $editor = orgEditor();
    $draft = orgDraftFor($editor);
    $root = orgPositionIn($draft, 'Presidencia');
    $ops = orgPositionIn($draft, 'Gerencia de Operaciones');

    expect(fn () => OrgStructureEditor::removePosition($draft->id, $editor, 0, $ops->id))
        ->toThrow(OrgStructureException::class, 'tiene ocupantes');

    $child = OrgStructureEditor::addPosition($draft->id, $editor, 0, $ops->id, 'Coordinación Diagnomóvil');
    $assignmentId = OrgPositionAssignment::query()->where('position_id', $ops->id)->value('id');
    $lock = OrgStructureEditor::unassign($draft->id, $editor, $child['lock_version'], $assignmentId);
    OrgStructureEditor::removePosition($draft->id, $editor, $lock, $ops->id);

    expect(OrgPosition::query()->find($ops->id))->toBeNull()
        ->and(OrgPosition::query()->findOrFail($child['position_id'])->parent_id)->toBe($root->id);
});

it('valida plazas, colaboradores inactivos y avisa cuando alguien queda titular de dos puestos', function (): void {
    $current = orgCurrentStructure();
    $editor = orgEditor();
    $draft = orgDraftFor($editor);
    $root = orgPositionIn($draft, 'Presidencia');
    $ops = orgPositionIn($draft, 'Gerencia de Operaciones');
    $newcomer = orgColaborador('Nuevo Pest');
    $inactive = orgColaborador('Egresado Pest', null, 'inactivo');

    expect(fn () => OrgStructureEditor::assign($draft->id, $editor, 0, $ops->id, $newcomer->id))
        ->toThrow(OrgStructureException::class, 'no tiene plazas libres');

    expect(fn () => OrgStructureEditor::assign($draft->id, $editor, 0, $ops->id, $inactive->id))
        ->toThrow(OrgStructureException::class, 'no está activo');

    $lock = OrgStructureEditor::updatePosition($draft->id, $editor, 0, $ops->id, ['seats' => 2]);
    $result = OrgStructureEditor::assign($draft->id, $editor, $lock, $ops->id, $current['boss']->id);

    expect($result['dual'])->toBeTrue();

    expect(fn () => OrgStructureEditor::updatePosition($draft->id, $editor, $result['lock_version'], $ops->id, ['seats' => 1]))
        ->toThrow(OrgStructureException::class, 'no puede quedar con 1');

    $issues = OrgChartPayload::compute($draft->refresh())['issues'];

    expect(collect($issues)->pluck('code')->all())->toContain('dual_holder')
        ->and(OrgStructureValidator::errorMessages($issues))->toBe([])
        ->and($root->id)->toBeInt();
});

// -------------------------------------------------------------- validador

it('marca como errores varias raíces, staff con subordinados y ocupantes inactivos', function (): void {
    $occupant = fn (bool $active): array => [
        'assignment_id' => 1, 'id' => 7, 'name' => 'Pedro', 'initials' => 'P', 'avatar' => null, 'role' => 'TITULAR', 'role_label' => 'Titular',
        'phone' => null, 'email' => null, 'since' => null, 'active' => $active, 'dual' => false, 'rrhh_cargo_id' => null, 'rrhh_cargo' => null,
    ];
    $node = fn (int $id, ?int $parent, string $kind = 'LINEA', array $occupants = []): array => [
        'id' => $id, 'key' => 'k'.$id, 'parent' => $parent, 'title' => 'P'.$id, 'kind' => $kind, 'seats' => 1, 'color' => null, 'sort' => 0,
        'cargo_id' => null, 'cargo' => null, 'depto_id' => null, 'depto' => null, 'occupants' => $occupants, 'vacancies' => 0,
    ];

    $codes = collect(OrgStructureValidator::issues([
        $node(1, null),
        $node(2, null),
        $node(3, 1, 'STAFF'),
        $node(4, 3, 'LINEA', [$occupant(false)]),
    ]))->where('level', 'error')->pluck('code')->all();

    expect($codes)->toContain('multiple_roots')
        ->toContain('staff_children')
        ->toContain('inactive_occupant');

    expect(OrgStructureValidator::issues([]))->toHaveCount(1)
        ->and(OrgStructureValidator::issues([])[0]['code'])->toBe('empty');
});

// -------------------------------------------------------------- publicación

it('un SUPERADMIN publica directo: la anterior pasa al historial y queda una sola vigente', function (): void {
    $current = orgCurrentStructure();
    $super = orgSuperAdmin();
    $draft = orgDraftFor($super);

    $result = OrgStructurePublisher::publishDirectly($draft->id, $super, false);

    expect($result['version']->status)->toBe(OrgStructureVersionStatus::Current)
        ->and($current['version']->refresh()->status)->toBe(OrgStructureVersionStatus::Archived)
        ->and($current['version']->current_marker)->toBeNull()
        ->and(OrgStructureVersion::query()->current()->count())->toBe(1)
        ->and(OrgStructureVersion::query()->current()->value('id'))->toBe($draft->id);
});

it('guarda el árbol en una sola entrada de caché por versión y la renueva al publicar o cambiar RRHH', function (): void {
    $current = orgCurrentStructure();
    $before = OrgChartPayload::build($current['version']);
    $key = \App\Support\OrgStructure\OrgStructureCache::payloadKey($current['version']);

    expect($key)->toBe('org-structure:payload:'.$current['version']->id)
        ->and($before['version']['status'])->toBe('VIGENTE');

    $current['manager']->update(['fullName' => 'Ana Renombrada Pest']);
    $afterRrhh = OrgChartPayload::build($current['version']->refresh());

    expect(collect($afterRrhh['nodes'])->pluck('occupants')->flatten(1)->pluck('name')->all())->toContain('Ana Renombrada Pest');

    $super = orgSuperAdmin();
    $draft = orgDraftFor($super);
    OrgStructurePublisher::publishDirectly($draft->id, $super, false);

    expect(OrgChartPayload::build($current['version']->refresh())['version']['status'])->toBe('ARCHIVADA');
});

it('un editor no publica directo: envía a aprobación y avisa a los SUPERADMIN', function (): void {
    orgCurrentStructure();
    $editor = orgEditor();
    $draft = orgDraftFor($editor);

    expect(fn () => OrgStructurePublisher::publishDirectly($draft->id, $editor, false))
        ->toThrow(OrgStructureException::class, 'Solo un SUPERADMIN');

    $submitted = OrgStructurePublisher::submitForReview($draft->id, $editor);

    expect($submitted->status)->toBe(OrgStructureVersionStatus::InReview)
        ->and((int) $submitted->submitted_by)->toBe(990002);

    Queue::assertPushed(NotifyOrgStructureReviewJob::class, fn (NotifyOrgStructureReviewJob $job): bool => $job->event === NotifyOrgStructureReviewJob::SUBMITTED);

    expect(fn () => OrgStructureEditor::addPosition($draft->id, $editor, $submitted->lock_version, null, 'Nuevo puesto'))
        ->toThrow(OrgStructureException::class, 'ya no se puede editar');
});

it('la aprobación la hace un SUPERADMIN distinto de quien envió; devolver exige motivo', function (): void {
    orgCurrentStructure();
    $editor = orgEditor();
    $draft = orgDraftFor($editor);
    OrgStructurePublisher::submitForReview($draft->id, $editor);

    expect(fn () => OrgStructurePublisher::approve($draft->id, $editor, false))
        ->toThrow(OrgStructureException::class, 'Solo un SUPERADMIN');

    expect(fn () => OrgStructurePublisher::reject($draft->id, orgSuperAdmin(), 'corto'))
        ->toThrow(OrgStructureException::class, '10 caracteres');

    $returned = OrgStructurePublisher::reject($draft->id, orgSuperAdmin(), 'Falta ubicar la Coordinación de Proveedores.');

    expect($returned->status)->toBe(OrgStructureVersionStatus::Returned)
        ->and($returned->review_notes)->toContain('Proveedores');

    OrgStructureEditLock::acquire($draft->id, $editor);
    OrgStructurePublisher::submitForReview($draft->id, $editor);
    $approved = OrgStructurePublisher::approve($draft->id, orgSuperAdmin(), false);

    expect($approved['version']->status)->toBe(OrgStructureVersionStatus::Current)
        ->and((int) $approved['version']->reviewed_by)->toBe(990001);
});

it('quien envía un borrador no puede aprobarlo aunque sea SUPERADMIN', function (): void {
    orgCurrentStructure();
    $super = orgSuperAdmin();
    $draft = orgDraftFor($super);
    $draft->forceFill(['status' => OrgStructureVersionStatus::InReview, 'submitted_by' => $super->id])->save();

    OrgStructurePublisher::approve($draft->id, $super, false);
})->throws(OrgStructureException::class, 'no puede aprobarlo');

it('no publica un borrador cuya base ya no es la vigente', function (): void {
    orgCurrentStructure();
    $super = orgSuperAdmin();
    $first = orgDraftFor($super, 'Primero');
    $second = orgDraftFor($super, 'Segundo');

    OrgStructurePublisher::publishDirectly($first->id, $super, false);
    OrgStructurePublisher::publishDirectly($second->id, $super, false);
})->throws(OrgStructureException::class, 'cambió desde que se creó');

it('no publica con errores, como un ocupante que ya no está activo en RRHH', function (): void {
    $current = orgCurrentStructure();
    $super = orgSuperAdmin();
    $draft = orgDraftFor($super);
    RrhhColaborador::query()->whereKey($current['manager']->id)->update(['status' => 'inactivo']);

    expect(OrgStructurePublisher::preview($draft)['errors'])->not->toBeEmpty();

    OrgStructurePublisher::publishDirectly($draft->id, $super, false);
})->throws(OrgStructureException::class, 'Corrige antes de publicar');

it('al publicar con la opción marcada actualiza en RRHH el cargo de quien cambió de puesto', function (): void {
    $current = orgCurrentStructure();
    $super = orgSuperAdmin();
    $depto = RrhhDepartamento::query()->create(['description' => 'Gerencia Pest']);
    $cargo = RrhhCargo::query()->create(['departamento_id' => $depto->id, 'description' => 'Gerente de Operaciones Pest', 'created_by' => 'pest']);
    $draft = orgDraftFor($super);
    $ops = orgPositionIn($draft, 'Gerencia de Operaciones');

    OrgStructureEditor::updatePosition($draft->id, $super, 0, $ops->id, ['rrhh_cargo_id' => $cargo->id]);

    $preview = OrgStructurePublisher::preview($draft->refresh());

    expect($preview['rrhh']['changes'])->toHaveCount(1)
        ->and($preview['rrhh']['changes'][0]['colaborador_id'])->toBe($current['manager']->id);

    $result = OrgStructurePublisher::publishDirectly($draft->id, $super, true);
    $manager = $current['manager']->refresh();

    expect($result['rrhh_updated'])->toBe(1)
        ->and((int) $manager->cargo_id)->toBe($cargo->id)
        ->and((int) $manager->departmento_id)->toBe($depto->id);
});

// -------------------------------------------------------- RRHH en vivo y lecturas

it('cuando RRHH inactiva a un colaborador su puesto en la vigente queda vacante', function (): void {
    $current = orgCurrentStructure();

    $current['manager']->update(['status' => 'inactivo']);

    $assignment = OrgPositionAssignment::query()->where('position_id', $current['ops']->id)->sole();
    $payload = OrgChartPayload::build($current['version']->refresh());
    $opsNode = collect($payload['nodes'])->firstWhere('id', $current['ops']->id);

    expect($assignment->ends_on)->not->toBeNull()
        ->and($opsNode['occupants'])->toBe([])
        ->and($opsNode['vacancies'])->toBe(1)
        ->and(OrgStructureEvent::query()->where('version_id', $current['version']->id)->where('action', 'occupant_left')->exists())->toBeTrue();
});

it('el jefe de alguien es quien ocupa el puesto superior, saltando vacantes', function (): void {
    $current = orgCurrentStructure();
    $vacant = OrgPosition::factory()->create(['version_id' => $current['version']->id, 'title' => 'Coordinación vacante', 'parent_id' => $current['ops']->id]);
    $analystPosition = OrgPosition::factory()->create(['version_id' => $current['version']->id, 'title' => 'Analista', 'parent_id' => $vacant->id]);
    $analyst = orgColaborador('Analista Pest');
    OrgPositionAssignment::factory()->create(['version_id' => $current['version']->id, 'position_id' => $analystPosition->id, 'rrhh_colaborador_id' => $analyst->id]);

    expect(OrgStructureHierarchy::managerOf($analyst->id)['id'])->toBe($current['manager']->id)
        ->and(OrgStructureHierarchy::managerOf($current['manager']->id)['id'])->toBe($current['boss']->id)
        ->and(OrgStructureHierarchy::managerOf($current['boss']->id))->toBeNull()
        ->and(collect(OrgStructureHierarchy::directReportsOf($current['boss']->id))->pluck('id')->all())->toBe([$current['manager']->id]);
});

it('compara versiones por clave de puesto: nuevos, movidos, renombrados y quitados', function (): void {
    $occupant = ['id' => 5, 'name' => 'Ana', 'role' => 'TITULAR'];
    $base = [
        ['id' => 1, 'key' => 'root', 'parent' => null, 'title' => 'Presidencia', 'occupants' => []],
        ['id' => 2, 'key' => 'ops', 'parent' => 1, 'title' => 'Operaciones', 'occupants' => [$occupant]],
        ['id' => 3, 'key' => 'tdev', 'parent' => 1, 'title' => 'Analista TDEV', 'occupants' => []],
        ['id' => 4, 'key' => 'prov', 'parent' => 1, 'title' => 'Proveedores', 'occupants' => []],
    ];
    $draft = [
        ['id' => 11, 'key' => 'root', 'parent' => null, 'title' => 'Presidencia', 'occupants' => []],
        ['id' => 12, 'key' => 'ops', 'parent' => 11, 'title' => 'Gerencia de Operaciones', 'occupants' => [$occupant]],
        ['id' => 14, 'key' => 'prov', 'parent' => 12, 'title' => 'Proveedores', 'occupants' => []],
        ['id' => 15, 'key' => 'aliadas', 'parent' => 11, 'title' => 'Empresas aliadas', 'occupants' => []],
    ];

    $diff = OrgStructureComparator::compare($base, $draft);

    expect($diff['summary'])->toBe(['added' => 1, 'removed' => 1, 'moved' => 1, 'renamed' => 1, 'occupants' => 0])
        ->and($diff['status_by_key'])->toBe(['ops' => 'changed', 'prov' => 'moved', 'aliadas' => 'added'])
        ->and($diff['removed'][0]['title'])->toBe('Analista TDEV');
});

it('el árbol para quien consulta no expone sueldo, cédula ni la lista interna de avisos', function (): void {
    $current = orgCurrentStructure();
    $payload = OrgChartPayload::forViewer(OrgChartPayload::build($current['version']));
    $json = json_encode($payload);

    expect($payload)->not->toHaveKeys(['issues', 'unassigned'])
        ->and($json)->not->toContain('sueldo')
        ->and($json)->not->toContain('cedula')
        ->and($json)->not->toContain('nroCta');
});

// ------------------------------------------------------------------- PDF

it('arma el PDF por láminas y produce un documento válido', function (): void {
    $current = orgCurrentStructure();

    $data = OrgChartPdfExporter::viewData($current['version'], ['show_contact' => true]);

    expect($data['sheets'])->toHaveCount(1)
        ->and($data['sheets'][0]['head']['title'])->toBe('Presidencia')
        ->and($data['sheets'][0]['rows'][0][0]['node']['title'])->toBe('Gerencia de Operaciones')
        ->and($data['index'])->toHaveCount(2);

    expect(substr(OrgChartPdfExporter::render($current['version'], []), 0, 4))->toBe('%PDF');
});

it('solo acepta nombres de exportación con el patrón fijo', function (): void {
    expect(OrgChartPdfExporter::isValidFilename(OrgChartPdfExporter::newFilename()))->toBeTrue()
        ->and(OrgChartPdfExporter::isValidFilename('../../.env'))->toBeFalse()
        ->and(OrgChartPdfExporter::isValidFilename('organigrama-20261021-120000-abc123.pdf.php'))->toBeFalse()
        ->and(OrgChartPdfExporter::absolutePathFor(1, '../x.pdf'))->toBeNull();
});

it('la asignación de un interino se guarda con su rol', function (): void {
    orgCurrentStructure();
    $editor = orgEditor();
    $draft = orgDraftFor($editor);
    $root = orgPositionIn($draft, 'Presidencia');
    $added = OrgStructureEditor::addPosition($draft->id, $editor, 0, $root->id, 'Coordinación interina');
    $person = orgColaborador('Diana Toro Pest');

    OrgStructureEditor::assign($draft->id, $editor, $added['lock_version'], $added['position_id'], $person->id, OrgAssignmentRole::Interim->value);

    expect(OrgPositionAssignment::query()->where('position_id', $added['position_id'])->sole()->role)->toBe(OrgAssignmentRole::Interim);
});
