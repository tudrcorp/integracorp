<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

use App\Filament\Business\Resources\ProspectAgents\Pages\ListProspectAgents;
use App\Filament\Business\Resources\ProspectAgents\Pages\ViewProspectAgent;
use App\Jobs\SendNotificacionWhatsApp;
use App\Mail\SendEmailCreateTicketAndAssigned;
use App\Models\HelpDesk;
use App\Models\HelpDeskEvent;
use App\Models\ProspectAgent;
use App\Models\RrhhColaborador;
use App\Models\User;
use App\Support\HelpdeskEventRecorder;
use App\Support\HelpdeskTaskStatusOptions;
use App\Support\HelpdeskTicketType;
use App\Support\Operations\Dashboard\OperationsDashboardPeriod;
use App\Support\ProspectAgents\Dashboard\ProspectDashboardMetrics;
use App\Support\ProspectAgents\ProspectAgentDeletion;
use App\Support\ProspectAgents\ProspectHelpdeskTicketCreator as Creator;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

/**
 * Todo lo que escribe va dentro de una transacción que siempre se revierte. La cola y el
 * correo están falsos: ningún WhatsApp ni correo sale de verdad.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    Queue::fake();
    Mail::fake();

    Filament::setCurrentPanel('business');

    $this->analyst = User::query()
        ->where('email', 'like', '%@tudrencasa.com')
        ->where('status', 'ACTIVO')
        ->get()
        ->first(fn (User $user): bool => in_array('SUPERADMIN', (array) ($user->departament ?? []), true));

    if ($this->analyst === null) {
        $this->markTestSkipped('No hay un analista SUPERADMIN activo en la base.');
    }

    $this->actingAs($this->analyst);

    $this->ana = RrhhColaborador::query()->create([
        'fullName' => 'Ana Pest Helpdesk',
        'status' => 'activo',
        'telefonoCorporativo' => '04121234567',
        'emailCorporativo' => 'ana.pest.helpdesk@tudrencasa.com',
    ]);
    $this->luis = RrhhColaborador::query()->create([
        'fullName' => 'Luis Pest Helpdesk',
        'status' => 'activo',
        'emailCorporativo' => 'luis.pest.helpdesk@tudrencasa.com',
    ]);

    $this->prospects = EloquentCollection::make(['Viajes Pest Uno', 'Viajes Pest Dos', 'Viajes Pest Tres'])
        ->map(fn (string $name): ProspectAgent => ProspectAgent::query()->create([
            'name' => $name,
            'type' => 'agencia-viajes',
            'status' => 'contacto-inicial',
            'reference_by' => 'otro',
            'phone_1' => '04120000000',
            'country_id' => 189,
            'created_by' => 'PEST',
        ]));
});

afterEach(fn () => DB::rollBack());

const HELPDESK_INSTRUCTIONS = 'Llamar al gerente comercial y enviar la propuesta corporativa por correo.';

it('crea un ticket del Helpdesk como Solicitud de servicio, con SLA, responsables, prospectos y evento', function (): void {
    $before = now();

    $ticket = Creator::create($this->prospects->modelKeys(), [$this->ana->getKey(), $this->luis->getKey()], HELPDESK_INSTRUCTIONS, 'ALTA', $this->analyst);

    expect($ticket)->toBeInstanceOf(HelpDesk::class)
        ->and($ticket->uid)->toStartWith('TK-')
        ->and($ticket->ticket_type)->toBe(HelpdeskTicketType::SERVICE_REQUEST)
        ->and($ticket->status)->toBe(HelpdeskTaskStatusOptions::STATUS_PENDING)
        ->and($ticket->priority)->toBe('ALTA')
        ->and($ticket->created_by_user_id)->toBe($this->analyst->getKey())
        ->and($ticket->first_response_due_at->diffInHours($before, true))->toBeGreaterThanOrEqual(3.9)->toBeLessThan(4.1)
        ->and($ticket->resolution_due_at->diffInHours($before, true))->toBeGreaterThanOrEqual(23.9)->toBeLessThan(24.1)
        ->and($ticket->rrhhColaboradores()->pluck('rrhh_colaboradors.id')->sort()->values()->all())->toBe(collect([$this->ana->getKey(), $this->luis->getKey()])->sort()->values()->all())
        ->and($ticket->prospectAgents()->pluck('prospect_agents.id')->sort()->values()->all())->toBe($this->prospects->modelKeys())
        ->and(HelpDeskEvent::query()->where('help_desk_id', $ticket->getKey())->where('type', HelpdeskEventRecorder::TYPE_SYSTEM)->value('body_html'))->toContain('desde Captación sobre 3 prospectos');
});

it('arma la descripción con las indicaciones escapadas y cada prospecto enlazado a su ficha', function (): void {
    $ticket = Creator::create([$this->prospects[0]->getKey()], [$this->ana->getKey()], "Paso 1 <script>alert(1)</script>\nPaso 2 de la gestión", 'MEDIA', $this->analyst);

    expect($ticket->description)->toContain('Ticket de trabajo de Captación')
        ->toContain('Paso 2 de la gestión')
        ->not->toContain('<script>')
        ->toContain('/business/prospect-agents/'.$this->prospects[0]->getKey())
        ->toContain('Viajes Pest Uno');
});

it('avisa a los responsables con los servicios del Helpdesk, y no avisa al migrar', function (): void {
    Creator::create($this->prospects->modelKeys(), [$this->ana->getKey(), $this->luis->getKey()], HELPDESK_INSTRUCTIONS, 'MEDIA', $this->analyst);

    Mail::assertQueued(SendEmailCreateTicketAndAssigned::class, 2);
    Queue::assertPushed(SendNotificacionWhatsApp::class, 1);

    $created = Carbon::parse('2026-11-02 13:19:11');
    $migrated = Creator::create([$this->prospects[0]->getKey()], [$this->ana->getKey()], HELPDESK_INSTRUCTIONS, 'MEDIA', $this->analyst, notify: false, createdAt: $created, originNote: 'Migrado de TKT-0031.');

    Mail::assertQueued(SendEmailCreateTicketAndAssigned::class, 2);
    Queue::assertPushed(SendNotificacionWhatsApp::class, 1);

    expect($migrated->created_at->equalTo($created))->toBeTrue()
        ->and($migrated->first_response_due_at->equalTo($created->copy()->addHours(24)))->toBeTrue()
        ->and(HelpDeskEvent::query()->where('help_desk_id', $migrated->getKey())->value('body_html'))->toContain('Migrado de TKT-0031.');
});

it('rechaza datos inválidos sin crear nada', function (Closure $prospects, Closure $colaboradores, string $instructions, string $priority): void {
    $before = HelpDesk::query()->count();

    expect(fn () => Creator::create($prospects($this), $colaboradores($this), $instructions, $priority, $this->analyst))
        ->toThrow(InvalidArgumentException::class);

    expect(HelpDesk::query()->count())->toBe($before);
    Mail::assertNothingQueued();
})->with([
    'sin prospectos' => [fn () => [], fn ($t) => [$t->ana->getKey()], HELPDESK_INSTRUCTIONS, 'MEDIA'],
    'prospecto inexistente' => [fn () => [999999999], fn ($t) => [$t->ana->getKey()], HELPDESK_INSTRUCTIONS, 'MEDIA'],
    'sin responsables' => [fn ($t) => $t->prospects->modelKeys(), fn () => [], HELPDESK_INSTRUCTIONS, 'MEDIA'],
    'indicaciones cortas' => [fn ($t) => $t->prospects->modelKeys(), fn ($t) => [$t->ana->getKey()], 'llamar', 'MEDIA'],
    'prioridad inválida' => [fn ($t) => $t->prospects->modelKeys(), fn ($t) => [$t->ana->getKey()], HELPDESK_INSTRUCTIONS, 'URGENTISIMA'],
]);

it('omite prospectos eliminados e ids repetidos', function (): void {
    ProspectAgentDeletion::delete([$this->prospects[2]], 'Eliminado para la prueba del Helpdesk', 'Pest');

    $ticket = Creator::create([...$this->prospects->modelKeys(), $this->prospects[0]->getKey(), 'x'], [$this->ana->getKey(), $this->ana->getKey()], HELPDESK_INSTRUCTIONS, 'BAJA', $this->analyst);

    expect($ticket->prospectAgents()->count())->toBe(2)
        ->and($ticket->rrhhColaboradores()->count())->toBe(1);
});

it('la acción masiva de la tabla crea el ticket en el Helpdesk con la prioridad elegida', function (): void {
    $selected = $this->prospects->take(2);
    $before = HelpDesk::query()->max('id');

    Livewire::test(ListProspectAgents::class)
        ->selectTableRecords($selected->modelKeys())
        ->mountAction(TestAction::make('workTicket')->table()->bulk())
        ->assertSet('mountedActions.0.data.priority', 'MEDIA')
        ->set('mountedActions.0.data.colaborador_ids', [$this->ana->getKey()])
        ->set('mountedActions.0.data.priority', 'BAJA')
        ->set('mountedActions.0.data.instructions', HELPDESK_INSTRUCTIONS)
        ->callMountedAction()
        ->assertHasNoErrors();

    $ticket = HelpDesk::query()->where('id', '>', (int) $before)->latest('id')->first();

    expect($ticket)->not->toBeNull()
        ->and($ticket->priority)->toBe('BAJA')
        ->and($ticket->prospectAgents()->pluck('prospect_agents.id')->sort()->values()->all())->toBe($selected->modelKeys());
});

it('la acción masiva exige responsables e indicaciones suficientes', function (): void {
    $before = HelpDesk::query()->count();

    Livewire::test(ListProspectAgents::class)
        ->selectTableRecords($this->prospects->modelKeys())
        ->mountAction(TestAction::make('workTicket')->table()->bulk())
        ->set('mountedActions.0.data.colaborador_ids', [])
        ->set('mountedActions.0.data.instructions', 'corto')
        ->callMountedAction()
        ->assertHasErrors(['mountedActions.0.data.colaborador_ids', 'mountedActions.0.data.instructions']);

    expect(HelpDesk::query()->count())->toBe($before);
});

it('Ver prospecto crea el ticket para ese prospecto y lo muestra en Seguimiento con enlace al Helpdesk', function (): void {
    $prospect = $this->prospects[0];

    Livewire::test(ViewProspectAgent::class, ['record' => $prospect->getKey()])
        ->mountAction('workTicket')
        ->set('mountedActions.0.data.colaborador_ids', [$this->luis->getKey()])
        ->set('mountedActions.0.data.instructions', HELPDESK_INSTRUCTIONS)
        ->callMountedAction()
        ->assertHasNoErrors();

    $ticket = $prospect->helpdeskTickets()->first();

    expect($ticket)->not->toBeNull();

    Livewire::test(ViewProspectAgent::class, ['record' => $prospect->getKey()])
        ->assertSee('Tickets de trabajo')
        ->assertSee('N.º '.$ticket->getKey())
        ->assertSee('Luis Pest Helpdesk')
        ->assertSee('/business/helpdesks/'.$ticket->getKey(), false);
});

it('el dashboard cuenta el ticket como asignado a cada responsable y resuelto al terminarse', function (): void {
    ProspectDashboardMetrics::flush();
    $period = OperationsDashboardPeriod::fromFilters(['period' => 'this_month']);

    $ticket = Creator::create($this->prospects->modelKeys(), [$this->ana->getKey(), $this->luis->getKey()], HELPDESK_INSTRUCTIONS, 'MEDIA', $this->analyst);
    ProspectDashboardMetrics::flush();
    $open = collect(ProspectDashboardMetrics::assignmentsByColaborador($period)['rows'])->keyBy('label');

    expect($open['Ana Pest Helpdesk'])->toMatchArray(['assigned' => 1, 'resolved' => 0])
        ->and($open['Luis Pest Helpdesk'])->toMatchArray(['assigned' => 1, 'resolved' => 0]);

    $ticket->forceFill(['status' => HelpdeskTaskStatusOptions::STATUS_DONE, 'resolved_at' => now()])->save();
    ProspectDashboardMetrics::flush();
    $done = collect(ProspectDashboardMetrics::assignmentsByColaborador($period)['rows'])->keyBy('label');

    expect($done['Ana Pest Helpdesk'])->toMatchArray(['assigned' => 1, 'resolved' => 1, 'rate' => 100.0])
        ->and(collect(ProspectDashboardMetrics::detail($period, 'assignee', (string) $this->ana->getKey())['rows'])->pluck('kind')->all())->toContain('Ticket Helpdesk N.º '.$ticket->getKey());

    ProspectDashboardMetrics::flush();
});
