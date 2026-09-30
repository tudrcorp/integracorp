<?php

declare(strict_types=1);

use App\Filament\Telemedicina\Resources\Helpdesks\HelpdeskResource as TelemedicineHelpdeskResource;
use App\Filament\Telemedicina\Resources\Helpdesks\Pages\ListHelpdesks as ListTelemedicineHelpdesks;
use App\Livewire\Operations\CaseFollowUpChatPanel;
use App\Models\HelpDesk;
use App\Models\RrhhColaborador;
use App\Models\TelemedicineCase;
use App\Models\User;
use App\Support\Filament\InternalPanelsQuickNavigation;
use App\Support\HelpdeskFormSchema;
use App\Support\HelpdeskTaskStatusOptions;
use App\Support\Operations\CaseFollowUpChatManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    DB::beginTransaction();

    $now = now();
    $insertCase = fn (?int $supplierId, string $code, string $status = CaseFollowUpChatManager::FOLLOW_UP_STATUS): int => DB::table('telemedicine_cases')->insertGetId([
        'telemedicine_patient_id' => 0, 'code' => $code, 'status' => $status, 'supplier_id' => $supplierId,
        'managed_by' => 'TDG', 'created_at' => $now, 'updated_at' => $now,
    ]);

    $this->supplierCase = $insertCase(15, 'ZZCHAT-PROV');
    $this->otherSupplierCase = $insertCase(428, 'ZZCHAT-OTRO');
    $this->tdgCase = $insertCase(null, 'ZZCHAT-TDG');
    $this->closedSupplierCase = $insertCase(15, 'ZZCHAT-CERRADO', 'CERRADO');
    $this->caseIds = [$this->supplierCase, $this->otherSupplierCase, $this->tdgCase, $this->closedSupplierCase];
});

afterEach(fn () => DB::rollBack());

function telemedicineUser(array $attributes): User
{
    return User::factory()->make(['id' => 999998, 'departament' => ['TELEMEDICINA'], ...$attributes]);
}

function visibleChatCaseIds(User $user, string $context, array $scope): array
{
    Auth::setUser($user);

    return CaseFollowUpChatManager::followUpCasesQuery($user, $context)
        ->whereKey($scope)
        ->orderBy('id')
        ->pluck('id')
        ->all();
}

it('el doctor de un proveedor solo ve los casos en seguimiento de su proveedor', function (): void {
    $doctor = telemedicineUser(['supplier_id' => 15, 'doctor_id' => 1]);

    expect(visibleChatCaseIds($doctor, CaseFollowUpChatManager::CONTEXT_TELEMEDICINE, $this->caseIds))
        ->toBe([$this->supplierCase])
        ->and(CaseFollowUpChatManager::canAccessCase($doctor, TelemedicineCase::query()->findOrFail($this->otherSupplierCase), CaseFollowUpChatManager::CONTEXT_TELEMEDICINE))->toBeFalse()
        ->and(CaseFollowUpChatManager::canAccessCase($doctor, TelemedicineCase::query()->findOrFail($this->tdgCase), CaseFollowUpChatManager::CONTEXT_TELEMEDICINE))->toBeFalse()
        ->and(CaseFollowUpChatManager::canAccessCase($doctor, TelemedicineCase::query()->findOrFail($this->closedSupplierCase), CaseFollowUpChatManager::CONTEXT_TELEMEDICINE))->toBeFalse();
});

it('el doctor de TDG solo ve los casos en seguimiento sin proveedor', function (): void {
    $doctor = telemedicineUser(['supplier_id' => null, 'doctor_id' => 2]);

    expect(visibleChatCaseIds($doctor, CaseFollowUpChatManager::CONTEXT_TELEMEDICINE, $this->caseIds))
        ->toBe([$this->tdgCase])
        ->and(CaseFollowUpChatManager::canAccessCase($doctor, TelemedicineCase::query()->findOrFail($this->supplierCase), CaseFollowUpChatManager::CONTEXT_TELEMEDICINE))->toBeFalse();
});

it('el SUPERADMIN ve todos los casos en seguimiento desde Telemedicina', function (): void {
    $admin = telemedicineUser(['supplier_id' => null, 'doctor_id' => 3, 'departament' => ['SUPERADMIN', 'TELEMEDICINA']]);

    expect(visibleChatCaseIds($admin, CaseFollowUpChatManager::CONTEXT_TELEMEDICINE, $this->caseIds))
        ->toBe([$this->supplierCase, $this->otherSupplierCase, $this->tdgCase]);
});

it('no cambia el alcance del chat en Operaciones', function (): void {
    $analyst = telemedicineUser(['supplier_id' => null, 'doctor_id' => 4, 'departament' => ['OPERACIONES']]);

    expect(visibleChatCaseIds($analyst, CaseFollowUpChatManager::CONTEXT_OPERATIONS, $this->caseIds))
        ->toBe([$this->supplierCase, $this->otherSupplierCase, $this->tdgCase]);
});

it('bloquea enviar un mensaje a un caso fuera del alcance del doctor', function (): void {
    $doctor = telemedicineUser(['supplier_id' => 15, 'doctor_id' => 1]);

    CaseFollowUpChatManager::sendMessage(
        TelemedicineCase::query()->findOrFail($this->tdgCase),
        $doctor,
        'Hola',
        CaseFollowUpChatManager::CONTEXT_TELEMEDICINE,
    );
})->throws(Symfony\Component\HttpKernel\Exception\HttpException::class);

it('normaliza contextos desconocidos al comportamiento de Operaciones', function (): void {
    expect(CaseFollowUpChatManager::normalizeContext('telemedicina'))->toBe('telemedicina')
        ->and(CaseFollowUpChatManager::normalizeContext('admin'))->toBe('operations')
        ->and(CaseFollowUpChatManager::normalizeContext(null))->toBe('operations');
});

it('el contexto del componente de chat está bloqueado y el caso seleccionado se valida al renderizar', function (): void {
    $source = file_get_contents((new ReflectionClass(CaseFollowUpChatPanel::class))->getFileName());
    $hook = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/telemedicina/hooks/case-follow-up-chat-panel.blade.php');

    expect($source)
        ->toContain("#[Locked]\n    public string \$context")
        ->toContain('! CaseFollowUpChatManager::canAccessCase($user, $selectedCase, $this->context)')
        ->toContain('isset($this->unreadByCase[$this->selectedCaseId])')
        ->and($hook)->toContain('CaseFollowUpChatManager::CONTEXT_TELEMEDICINE');
});

it('«Mis tickets» solo muestra los tickets creados por el doctor', function (): void {
    [$doctorId, $otherUserId] = User::query()->orderBy('id')->limit(2)->pluck('id')->all();
    $now = now();
    $mine = DB::table('help_desks')->insertGetId(['description' => 'ZZ MIO', 'priority' => 'MEDIA', 'created_by_user_id' => $doctorId, 'created_at' => $now, 'updated_at' => $now]);
    $foreign = DB::table('help_desks')->insertGetId(['description' => 'ZZ AJENO', 'priority' => 'MEDIA', 'created_by_user_id' => $otherUserId, 'created_at' => $now, 'updated_at' => $now]);

    Auth::setUser(telemedicineUser(['id' => $doctorId, 'supplier_id' => 15, 'doctor_id' => 1]));

    expect(TelemedicineHelpdeskResource::getEloquentQuery()->whereKey([$mine, $foreign])->pluck('id')->all())->toBe([$mine])
        ->and(TelemedicineHelpdeskResource::canEdit(new HelpDesk))->toBeFalse()
        ->and(TelemedicineHelpdeskResource::canDelete(new HelpDesk))->toBeFalse();

    Auth::forgetUser();

    expect(TelemedicineHelpdeskResource::getEloquentQuery()->whereKey([$mine, $foreign])->count())->toBe(0);
});

it('el encabezado de «Mis tickets» resume solo los tickets del doctor y cuenta el plazo vencido de los abiertos', function (): void {
    [$doctorId, $otherUserId] = User::query()->orderBy('id')->limit(2)->pluck('id')->all();
    Auth::setUser(telemedicineUser(['id' => $doctorId, 'supplier_id' => 15, 'doctor_id' => 1]));
    $before = ListTelemedicineHelpdesks::summary();

    $now = now();
    $past = $now->copy()->subDay();
    $ticket = fn (int $userId, ?string $status, array $extra = []): int => DB::table('help_desks')->insertGetId([
        'description' => 'ZZ RESUMEN', 'priority' => 'MEDIA', 'status' => $status, 'created_by_user_id' => $userId,
        'created_at' => $now, 'updated_at' => $now, ...$extra,
    ]);

    $ticket($doctorId, HelpdeskTaskStatusOptions::STATUS_PENDING);
    $ticket($doctorId, HelpdeskTaskStatusOptions::STATUS_IN_PROGRESS, ['resolution_due_at' => $past]);
    $ticket($doctorId, HelpdeskTaskStatusOptions::STATUS_DONE, ['resolution_due_at' => $past, 'first_response_due_at' => $past]);
    $ticket($doctorId, HelpdeskTaskStatusOptions::STATUS_CANCELLED);
    $ticket($otherUserId, HelpdeskTaskStatusOptions::STATUS_IN_PROGRESS, ['resolution_due_at' => $past]);

    $after = ListTelemedicineHelpdesks::summary();

    expect($after['total'] - $before['total'])->toBe(4)
        ->and($after['open'] - $before['open'])->toBe(2)
        ->and($after['overdue'] - $before['overdue'])->toBe(1)
        ->and($after['done'] - $before['done'])->toBe(1);

    $html = view('filament.telemedicina.helpdesks.list-header', ['summary' => $after])->render();

    expect($html)->toContain('Mis tickets')->toContain('Plazo vencido')->toContain('Soporte de Operaciones');

    Auth::forgetUser();

    expect(ListTelemedicineHelpdesks::summary())->toBe(['total' => 0, 'open' => 0, 'overdue' => 0, 'done' => 0]);
});

it('solo permite asignar tickets a colaboradores del departamento OPERACIONES', function (): void {
    $operations = RrhhColaborador::query()->whereHas('departamento', fn ($q) => $q->where('description', 'OPERACIONES'))->value('id');
    $systems = RrhhColaborador::query()->whereHas('departamento', fn ($q) => $q->where('description', 'SISTEMAS'))->value('id');

    expect(HelpdeskFormSchema::TELEMEDICINE_ASSIGNEE_DEPARTMENTS)->toBe(['OPERACIONES'])
        ->and(HelpdeskFormSchema::colaboradorIdsOutsideDepartments([], ['OPERACIONES']))->toBe([]);

    if ($operations !== null && $systems !== null) {
        expect(HelpdeskFormSchema::colaboradorIdsOutsideDepartments([(int) $operations, (int) $systems], ['OPERACIONES']))
            ->toBe([(int) $systems]);
    }

    $scoped = HelpdeskFormSchema::applyAssigneeDepartmentScope(RrhhColaborador::query(), ['OPERACIONES']);

    expect($scoped->toSql())->toContain('exists')
        ->and($scoped->getBindings())->toBe(['OPERACIONES'])
        ->and(array_keys(HelpdeskFormSchema::rrhhColaboradorOptionsForHelpdeskMultiselect(['OPERACIONES'])))
        ->each->toBeInt();
});

it('el formulario y la creación de Telemedicina aplican la restricción a Operaciones', function (): void {
    $root = dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/Helpdesks';

    expect(file_get_contents($root.'/Schemas/HelpdeskForm.php'))
        ->toContain('assigneeDepartments: HelpdeskFormSchema::TELEMEDICINE_ASSIGNEE_DEPARTMENTS')
        ->and(file_get_contents($root.'/Pages/CreateHelpdesk.php'))
        ->toContain('$this->assertAssigneesBelongToOperationsOrHalt();')
        ->toContain('HelpdeskFormSchema::colaboradorIdsOutsideDepartments(')
        ->toContain('$this->helpdeskCcColaboradorIdsPendingValidation');
});

it('la barra de Telemedicina muestra solo «Crear ticket» y «Chat casos»', function (): void {
    Auth::setUser(telemedicineUser(['supplier_id' => 15, 'doctor_id' => 1, 'departament' => ['TELEMEDICINA', 'OPERACIONES']]));

    $items = InternalPanelsQuickNavigation::navigationItems('telemedicina');

    expect(array_column($items, 'kind'))->toBe(['ticket', 'operations-chat'])
        ->and($items[0]['url'])->toContain('/telemedicina/helpdesks/create');
});
