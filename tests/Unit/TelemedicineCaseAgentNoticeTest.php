<?php

declare(strict_types=1);

use App\Enums\SystemNotificationKey;
use App\Jobs\NotifyAgentOfNewTelemedicineCaseJob;
use App\Jobs\SendNotificacionWhatsApp;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\ObservationCase;
use App\Models\SystemNotificationRecipientSetting;
use App\Models\TelemedicineCase;
use App\Models\TelemedicinePatient;
use App\Observers\TelemedicineCaseObserver;
use App\Support\Telemedicine\TelemedicineCaseAgentNotice;
use App\Support\Telemedicine\TelemedicineCaseFactory;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

uses(Tests\TestCase::class);

/**
 * Escribe casos, bitácora y configuración: todo dentro de una transacción que
 * siempre se revierte.
 */
beforeEach(fn () => DB::beginTransaction());
afterEach(fn () => DB::rollBack());

function pacienteIndividualConAgenteActivo(): ?TelemedicinePatient
{
    return TelemedicinePatient::query()
        ->whereHas('afilliation', fn ($affiliation) => $affiliation->whereHas(
            'agent',
            fn ($agent) => $agent->whereRaw('UPPER(TRIM(status)) = ?', ['ACTIVO'])->whereNotNull('phone')->where('phone', '!=', ''),
        ))
        ->first();
}

function agenciaActivaConTelefono(): ?Agency
{
    return Agency::query()
        ->whereRaw('UPPER(TRIM(status)) = ?', ['ACTIVO'])
        ->whereNotNull('phone')
        ->where('phone', '!=', '')
        ->get()
        ->first(fn (Agency $agency): bool => App\Services\HelpdeskTicketAssigneeWhatsAppService::normalizePhoneForWhatsApp($agency->phone) !== null);
}

function configurarAvisoDeCaso(bool $active, array $phones = []): void
{
    SystemNotificationRecipientSetting::for(SystemNotificationKey::TelemedicineCaseAgentNotice)->update([
        'is_active' => $active,
        'notification_emails' => [],
        'notification_phones' => $phones,
    ]);
}

it('la clave nueva responde en todas las secciones del Centro de notificaciones', function (): void {
    $key = SystemNotificationKey::TelemedicineCaseAgentNotice;
    $reflection = new ReflectionEnum(SystemNotificationKey::class);

    foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if ($method->isStatic() || $method->getNumberOfRequiredParameters() > 0 || $method->getDeclaringClass()->getName() !== SystemNotificationKey::class) {
            continue;
        }

        expect(fn () => $method->invoke($key))->not->toThrow(UnhandledMatchError::class);
    }

    expect(SystemNotificationKey::managed())->toContain($key)
        ->and($key->pausesScheduledTask())->toBeFalse()
        ->and($key->usesEmailRecipients())->toBeFalse()
        ->and(SystemNotificationKey::OperationsAffiliateUpdate->usesEmailRecipients())->toBeTrue()
        ->and($key->savedRecipientsMessage([], ['04140000000']))->toContain('copia');
});

it('avisa al agente activo de la afiliación con los datos del paciente y sin datos clínicos', function (): void {
    $patient = pacienteIndividualConAgenteActivo();

    if ($patient === null) {
        $this->markTestSkipped('No hay pacientes con afiliación individual y agente activo.');
    }

    $case = new TelemedicineCase(['code' => '99999-0001', 'reason' => 'DOLOR TORÁCICO SEVERO', 'assigned_by' => 'ANALISTA QA']);
    $case->id = 1;
    $case->setRelation('telemedicinePatient', $patient);

    $payload = TelemedicineCaseAgentNotice::payload($case);
    $body = TelemedicineCaseAgentNotice::ownerBody($payload);

    expect($payload['owner']['kind'])->toBe(TelemedicineCaseAgentNotice::RECIPIENT_AGENT)
        ->and($payload['owner']['phone'])->toStartWith('+58')
        ->and($payload['affiliation_type'])->toBe('Individual')
        ->and($body)
        ->toContain('Le informamos que se registró un caso de telemedicina para uno de sus afiliados.')
        ->toContain('Nro. de Caso: 99999-0001')
        ->toContain('Cédula: '.trim((string) $patient->nro_identificacion))
        ->toContain('Registrado por: ANALISTA QA')
        ->toContain('Plan: ')
        ->not->toContain('DOLOR TORÁCICO');
});

it('sin agente, o con el agente inactivo, avisa a la agencia', function (): void {
    $agency = agenciaActivaConTelefono();
    $inactiveAgent = Agent::query()->whereRaw('UPPER(TRIM(status)) = ?', ['INACTIVO'])->first();

    if ($agency === null) {
        $this->markTestSkipped('No hay agencias activas con teléfono.');
    }

    $withoutAgent = TelemedicineCaseAgentNotice::ownerOf(new Affiliation(['code_agency' => $agency->code]));

    expect($withoutAgent['kind'])->toBe(TelemedicineCaseAgentNotice::RECIPIENT_AGENCY)
        ->and($withoutAgent['code'])->toBe($agency->code);

    if ($inactiveAgent !== null) {
        $affiliation = new AffiliationCorporate(['code_agency' => $agency->code]);
        $affiliation->agent_id = $inactiveAgent->id;

        expect(TelemedicineCaseAgentNotice::ownerOf($affiliation)['kind'])->toBe(TelemedicineCaseAgentNotice::RECIPIENT_AGENCY);
    }

    expect(TelemedicineCaseAgentNotice::ownerOf(new Affiliation(['code_agency' => 'NO-EXISTE-999'])))->toBeNull();
});

it('nombra al destinatario sin inventar códigos', function (): void {
    expect(TelemedicineCaseAgentNotice::ownerLabel(['kind' => 'agent', 'name' => 'ANA', 'code' => 'AGT-000527', 'phone' => '+58']))->toBe('al agente *ANA* (AGT-000527)')
        ->and(TelemedicineCaseAgentNotice::ownerLabel(['kind' => 'agent', 'name' => 'ANA', 'code' => '', 'phone' => '+58']))->toBe('al agente *ANA*')
        ->and(TelemedicineCaseAgentNotice::ownerLabel(['kind' => 'agency', 'name' => 'AGENCIA X', 'code' => 'TDG-135', 'phone' => '+58']))->toBe('a la agencia *AGENCIA X* (TDG-135)');
});

it('un paciente sin afiliación no genera aviso', function (): void {
    $case = new TelemedicineCase(['code' => '99999-0002']);
    $case->setRelation('telemedicinePatient', new TelemedicinePatient(['full_name' => 'SIN AFILIACIÓN']));

    expect(TelemedicineCaseAgentNotice::payload($case))->toBeNull();
});

it('envía al agente y a las copias una sola vez, aunque el job corra de nuevo', function (): void {
    $patient = pacienteIndividualConAgenteActivo();

    if ($patient === null) {
        $this->markTestSkipped('No hay pacientes con afiliación individual y agente activo.');
    }

    configurarAvisoDeCaso(true, ['04140000001', '0414-000-0002', 'no-es-telefono']);
    $case = TelemedicineCaseFactory::createForPatient($patient, ['assigned_by' => 'ANALISTA QA']);

    Bus::fake([SendNotificacionWhatsApp::class]);

    (new NotifyAgentOfNewTelemedicineCaseJob($case->id))->handle();
    (new NotifyAgentOfNewTelemedicineCaseJob($case->id))->handle();

    Bus::assertDispatchedTimes(SendNotificacionWhatsApp::class, 3);
    Bus::assertDispatched(SendNotificacionWhatsApp::class, fn (SendNotificacionWhatsApp $job): bool => $job->phone === '+584140000001'
        && str_contains($job->body, 'Copia · Nuevo caso al agente de la afiliación'));
    Bus::assertDispatched(SendNotificacionWhatsApp::class, fn (SendNotificacionWhatsApp $job): bool => str_contains($job->body, 'Le informamos que se registró un caso'));

    expect($case->fresh()->agent_notice_sent_at)->not->toBeNull()
        ->and(ObservationCase::query()->where('telemedicine_case_id', $case->id)->where('description', 'like', 'Aviso de caso nuevo%')->count())->toBe(1);
});

it('con la notificación inactiva no envía nada ni marca el caso', function (): void {
    $patient = pacienteIndividualConAgenteActivo();

    if ($patient === null) {
        $this->markTestSkipped('No hay pacientes con afiliación individual y agente activo.');
    }

    configurarAvisoDeCaso(false, ['04140000001']);
    $case = TelemedicineCaseFactory::createForPatient($patient);

    Bus::fake([SendNotificacionWhatsApp::class]);

    (new NotifyAgentOfNewTelemedicineCaseJob($case->id))->handle();

    Bus::assertNotDispatched(SendNotificacionWhatsApp::class);
    expect($case->fresh()->agent_notice_sent_at)->toBeNull();
});

it('el caso se avisa al confirmar la transacción, no antes', function (): void {
    $patient = pacienteIndividualConAgenteActivo();

    if ($patient === null) {
        $this->markTestSkipped('No hay pacientes con afiliación individual y agente activo.');
    }

    Queue::fake();

    // La transacción del test nunca confirma: si se revierte, no hay caso que avisar.
    TelemedicineCaseFactory::createForPatient($patient);
    Queue::assertNotPushed(NotifyAgentOfNewTelemedicineCaseJob::class);

    TelemedicineCaseObserver::dispatchAgentNoticeSafely(123);
    Queue::assertPushed(NotifyAgentOfNewTelemedicineCaseJob::class, fn (NotifyAgentOfNewTelemedicineCaseJob $job): bool => $job->telemedicineCaseId === 123);
});

it('el Centro de notificaciones muestra solo teléfonos para esta notificación', function (): void {
    Filament\Facades\Filament::setCurrentPanel('business');
    $this->actingAs(App\Models\User::factory()->make([
        'id' => 999_999_401,
        'email' => 'qa.centro.notificaciones@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['SUPERADMIN', 'NEGOCIOS'],
    ]));

    $page = Livewire\Livewire::test(App\Filament\Business\Pages\ManageCompanyAssociateNotifications::class)
        ->assertSee('Correos electrónicos');

    $page->call('selectNotificationKey', SystemNotificationKey::TelemedicineCaseAgentNotice->value)
        ->assertOk()
        ->assertSee('Aviso de caso nuevo al agente')
        ->assertSee('Teléfonos WhatsApp')
        ->assertDontSee('Destinatarios por email para esta notificación');
});
