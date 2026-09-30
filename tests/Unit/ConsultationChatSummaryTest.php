<?php

declare(strict_types=1);

use App\Jobs\PublishConsultationSummaryToCaseChatJob;
use App\Livewire\Operations\CaseFollowUpChatPanel;
use App\Models\TelemedicineCaseMessage;
use App\Models\TelemedicineConsultationPatient;
use App\Models\User;
use App\Observers\TelemedicineConsultationChatSummaryObserver;
use App\Support\Operations\CaseFollowUpChatManager;
use App\Support\Telemedicine\ConsultationChatSummary;
use App\Support\Telemedicine\ConsultationChatSummaryNotifier;
use App\Support\Telemedicine\ConsultationChatSummaryPublisher;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    /** Igual que el trait DatabaseTransactions: los `afterCommit` corren al nivel de la transacción del test. */
    $transactions = new Illuminate\Foundation\Testing\DatabaseTransactionsManager([DB::getDefaultConnection()]);
    app()->instance('db.transactions', $transactions);
    DB::connection()->setTransactionManager($transactions);

    DB::beginTransaction();

    $now = now();
    $this->doctorId = DB::table('telemedicine_doctors')->insertGetId([
        'full_name' => 'DOCTORA ZZ PRUEBA', 'nro_identificacion' => 'V-0', 'email' => 'zz-doctora@example.com',
        'created_at' => $now, 'updated_at' => $now,
    ]);
    $this->doctorUser = User::factory()->create(['doctor_id' => $this->doctorId, 'departament' => ['TELEMEDICINA']]);
    $this->analyst = User::factory()->create(['departament' => ['SUPERADMIN', 'OPERACIONES']]);

    $this->caseId = DB::table('telemedicine_cases')->insertGetId([
        'telemedicine_patient_id' => 0, 'code' => 'ZZSUM-001', 'patient_name' => 'PACIENTE ZZ',
        'status' => CaseFollowUpChatManager::FOLLOW_UP_STATUS, 'managed_by' => 'TDG',
        'created_at' => $now, 'updated_at' => $now->copy()->subDay(),
    ]);

    $this->consultation = function (array $attributes = []) use ($now): int {
        return DB::table('telemedicine_consultation_patients')->insertGetId([
            'telemedicine_case_id' => $this->caseId, 'telemedicine_case_code' => 'ZZSUM-001',
            'telemedicine_patient_id' => 0, 'telemedicine_doctor_id' => $this->doctorId,
            'status' => ConsultationChatSummary::STATUS_INITIAL,
            'created_at' => $now, 'updated_at' => $now,
            ...$attributes,
        ]);
    };
});

afterEach(fn () => DB::rollBack());

it('arma el resumen solo con los apartados que tienen datos', function (): void {
    $id = ($this->consultation)([
        'reason_consultation' => 'Dolor abdominal',
        'diagnostic_impression' => 'Gastritis aguda',
        'pa' => '120.00', 'fc' => '80.00', 'temp' => '37.50',
        'observations' => '<b>Reposo</b> 48 horas',
    ]);
    DB::table('telemedicine_patient_medications')->insert([
        'telemedicine_patient_id' => 0, 'telemedicine_case_id' => $this->caseId, 'telemedicine_doctor_id' => $this->doctorId,
        'telemedicine_consultation_patient_id' => $id, 'medicine' => 'OMEPRAZOL 20MG', 'indications' => '1 cada 12 horas',
        'duration' => 7, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $summary = ConsultationChatSummary::build(TelemedicineConsultationPatient::query()->findOrFail($id));
    $labels = array_column($summary['sections'], 'label');

    expect($summary['title'])->toBe('Resumen de consulta inicial')
        ->and($summary['subtitle'])->toContain('Dr(a). DOCTORA ZZ PRUEBA')->toContain('Caso ZZSUM-001')
        ->and($labels)->toBe(['Motivo de consulta', 'Signos vitales', 'Impresión diagnóstica', 'Medicamentos indicados', 'Observaciones'])
        ->and($summary['sections'][1]['value'])->toBe(['PA: 120 mmHg', 'FC: 80 lpm', 'Temperatura: 37.5 °C'])
        ->and($summary['sections'][3]['value'])->toBe(['OMEPRAZOL 20MG — 1 cada 12 horas · Duración: 7 días'])
        ->and($summary['sections'][4]['value'])->toBe('Reposo 48 horas')
        ->and($summary['body'])->toStartWith('INTEGRACORP · Resumen de consulta inicial');
});

it('usa el título según el tipo de consulta', function (?string $status, ?string $title): void {
    expect(ConsultationChatSummary::supportsStatus($status))->toBe($title !== null)
        ->and(ConsultationChatSummary::titles()[ConsultationChatSummary::normalizeStatus($status)] ?? null)->toBe($title);
})->with([
    'inicial' => ['CONSULTA INICIAL', 'Resumen de consulta inicial'],
    'seguimiento' => ['EN SEGUIMIENTO', 'Resumen de seguimiento'],
    'alta' => ['ALTA MEDICA', 'Resumen de alta médica'],
    'alta con acento' => ['alta médica', 'Resumen de alta médica'],
    'otro estado' => ['ANULADA', null],
    'nulo' => [null, null],
]);

it('publica el resumen una sola vez aunque se reintente', function (): void {
    $id = ($this->consultation)(['diagnostic_impression' => 'Faringitis']);

    $first = ConsultationChatSummaryPublisher::publish($id, $this->doctorUser->id);
    $second = ConsultationChatSummaryPublisher::publish($id, $this->doctorUser->id);

    expect($first)->toBeInstanceOf(TelemedicineCaseMessage::class)
        ->and($first->kind)->toBe(TelemedicineCaseMessage::KIND_CONSULTATION_SUMMARY)
        ->and($first->user_id)->toBe($this->doctorUser->id)
        ->and($first->meta['title'])->toBe('Resumen de consulta inicial')
        ->and($second->id)->toBe($first->id)
        ->and(TelemedicineCaseMessage::query()->where('telemedicine_consultation_patient_id', $id)->count())->toBe(1)
        ->and(DB::table('telemedicine_cases')->where('id', $this->caseId)->value('updated_at'))->toBeGreaterThan(now()->subMinute()->toDateTimeString());
});

it('sin sesión usa al usuario del doctor como autor y no publica si no hay autor', function (): void {
    $id = ($this->consultation)();

    expect(ConsultationChatSummaryPublisher::publish($id)->user_id)->toBe($this->doctorUser->id);

    $orphanDoctor = DB::table('telemedicine_doctors')->insertGetId([
        'full_name' => 'SIN USUARIO', 'nro_identificacion' => 'V-1', 'email' => 'zz-sin-usuario@example.com',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $orphan = ($this->consultation)(['telemedicine_doctor_id' => $orphanDoctor]);

    expect(ConsultationChatSummaryPublisher::publish($orphan))->toBeNull();
});

it('no publica consultas de otros estados', function (): void {
    $id = ($this->consultation)(['status' => 'ANULADA']);

    expect(ConsultationChatSummaryPublisher::publish($id, $this->doctorUser->id))->toBeNull();
});

it('el observer encola el resumen recién al confirmar la transacción y solo para los estados soportados', function (): void {
    Bus::fake([PublishConsultationSummaryToCaseChatJob::class]);
    Auth::setUser($this->doctorUser);

    $observer = new TelemedicineConsultationChatSummaryObserver;
    $discharge = TelemedicineConsultationPatient::query()->findOrFail(($this->consultation)(['status' => 'ALTA MEDICA']));

    DB::transaction(function () use ($observer, $discharge): void {
        $observer->created($discharge);

        Bus::assertNotDispatched(PublishConsultationSummaryToCaseChatJob::class);
    });

    $observer->created(TelemedicineConsultationPatient::query()->findOrFail(($this->consultation)(['status' => 'ANULADA'])));

    Bus::assertDispatchedTimes(PublishConsultationSummaryToCaseChatJob::class, 1);
    Bus::assertDispatched(
        PublishConsultationSummaryToCaseChatJob::class,
        fn (PublishConsultationSummaryToCaseChatJob $job): bool => $job->consultationId === (int) $discharge->getKey()
            && $job->authorUserId === $this->doctorUser->id
            && $job->afterCommit === true,
    );

    $attributes = (new ReflectionClass(TelemedicineConsultationPatient::class))->getAttributes(Illuminate\Database\Eloquent\Attributes\ObservedBy::class);
    expect($attributes[0]->getArguments()[0])->toContain(TelemedicineConsultationChatSummaryObserver::class);
});

it('el resumen llega como no leído a Operaciones, no a su autor, y se ve como tarjeta en el chat', function (): void {
    $id = ($this->consultation)([
        'reason_consultation' => 'Control', 'diagnostic_impression' => 'Estable',
        'observations' => 'Seguir dieta', 'background' => 'HTA',
    ]);
    ConsultationChatSummaryPublisher::publish($id, $this->doctorUser->id);

    expect(CaseFollowUpChatManager::unreadCountsForUser($this->analyst))->toHaveKey($this->caseId)
        ->and(CaseFollowUpChatManager::unreadCountsForUser($this->doctorUser))->not->toHaveKey($this->caseId);

    Auth::setUser($this->analyst);

    $component = Livewire::test(CaseFollowUpChatPanel::class)->call('openPanel');

    expect($component->get('selectedCaseId'))->toBe($this->caseId);

    $component->assertSee('Resumen de consulta inicial')
        ->assertSee('Ver resumen completo')
        ->assertSee('Estable');

    $preview = CaseFollowUpChatManager::latestMessagePreviewByCase(
        CaseFollowUpChatManager::casesByIdsForChatList($this->analyst, CaseFollowUpChatManager::CONTEXT_OPERATIONS, [$this->caseId])
    );

    expect($preview[$this->caseId]['user_name'])->toBe('INTEGRACORP')
        ->and($preview[$this->caseId]['body'])->toBe('Resumen de consulta inicial');
});

it('un fallo al encolar no interrumpe el guardado de la consulta', function (): void {
    Bus::shouldReceive('dispatch')->andThrow(new RuntimeException('cola caída'));

    TelemedicineConsultationChatSummaryObserver::dispatchSafely(123, null);

    expect(true)->toBeTrue();
});

function summaryRecipientUser(array $attributes): User
{
    return User::factory()->create([
        'email' => 'zz-'.uniqid('', true).'@tudrencasa.com',
        'status' => 'ACTIVO',
        'supplier_id' => null,
        'doctor_id' => null,
        ...$attributes,
    ]);
}

it('avisa a todos los que pueden ver el caso, sin incluir al autor ni a inactivos o ajenos', function (): void {
    $operations = summaryRecipientUser(['departament' => ['OPERACIONES']]);
    $inactive = summaryRecipientUser(['departament' => ['OPERACIONES'], 'status' => 'INACTIVO']);
    $tdgDoctor = summaryRecipientUser(['departament' => ['TELEMEDICINA'], 'doctor_id' => $this->doctorId + 1000]);
    $supplierId = App\Models\Supplier::query()->create(['name' => 'PROVEEDOR ZZ RESUMEN'])->id;
    $supplierDoctor = summaryRecipientUser(['departament' => ['TELEMEDICINA'], 'doctor_id' => $this->doctorId + 2000, 'supplier_id' => $supplierId]);
    $marketing = summaryRecipientUser(['departament' => ['MARKETING']]);
    $this->doctorUser->forceFill(['status' => 'ACTIVO', 'departament' => ['TELEMEDICINA']])->save();

    $case = App\Models\TelemedicineCase::query()->findOrFail($this->caseId);
    $ids = ConsultationChatSummaryNotifier::recipients($case, $this->doctorUser->id)->pluck('id')->all();

    expect($ids)->toContain($operations->id, $tdgDoctor->id)
        ->not->toContain($this->doctorUser->id, $inactive->id, $supplierDoctor->id, $marketing->id);

    DB::table('telemedicine_cases')->where('id', $this->caseId)->update(['supplier_id' => $supplierId]);
    $supplierCaseIds = ConsultationChatSummaryNotifier::recipients($case->refresh(), $this->doctorUser->id)->pluck('id')->all();

    expect($supplierCaseIds)->toContain($operations->id, $supplierDoctor->id)
        ->not->toContain($tdgDoctor->id);
});

it('publica y avisa en la campana una sola vez, con botón para abrir el chat del caso', function (): void {
    $operations = summaryRecipientUser(['departament' => ['OPERACIONES']]);
    $id = ($this->consultation)(['diagnostic_impression' => 'Control']);

    (new PublishConsultationSummaryToCaseChatJob($id, $this->doctorUser->id))->handle();
    (new PublishConsultationSummaryToCaseChatJob($id, $this->doctorUser->id))->handle();

    $notifications = DB::table('notifications')->where('notifiable_type', User::class)->where('notifiable_id', $operations->id)->get();
    $message = TelemedicineCaseMessage::query()->where('telemedicine_consultation_patient_id', $id)->firstOrFail();

    expect($notifications)->toHaveCount(1)
        ->and(DB::table('notifications')->where('notifiable_id', $this->doctorUser->id)->where('notifiable_type', User::class)->count())->toBe(0)
        ->and($message->meta['notified_at'] ?? null)->not->toBeNull()
        ->and($message->meta['notified_count'])->toBeGreaterThanOrEqual(1);

    $data = json_decode($notifications->first()->data, true);

    expect($data['title'])->toBe('Nuevo resumen de consulta · ZZSUM-001')
        ->and($data['body'])->toContain('INTEGRACORP publicó el resumen de consulta inicial')
        ->and($data['actions'][0]['event'])->toBe('operations-case-chat-open')
        ->and($data['actions'][0]['eventData'])->toBe(['caseId' => $this->caseId]);
});

it('no avisa si el caso ya no está en seguimiento', function (): void {
    summaryRecipientUser(['departament' => ['OPERACIONES']]);
    DB::table('telemedicine_cases')->where('id', $this->caseId)->update(['status' => 'CERRADO']);
    $id = ($this->consultation)(['status' => 'ALTA MEDICA']);

    $message = ConsultationChatSummaryPublisher::publish($id, $this->doctorUser->id);

    expect(ConsultationChatSummaryNotifier::notifyOnce($message))->toBe(0);
});

it('el aviso emergente distingue el resumen y ofrece abrir el chat del caso', function (): void {
    $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/operations/case-follow-up-chat-panel.blade.php');
    $component = file_get_contents(dirname(__DIR__, 2).'/app/Livewire/Operations/CaseFollowUpChatPanel.php');

    expect($view)
        ->toContain('Nuevo resumen de consulta · ${caseCode}')
        ->toContain(".dispatch('operations-case-chat-open', { caseId })")
        ->and($component)->toContain('isSummary: $isSummary');
});
