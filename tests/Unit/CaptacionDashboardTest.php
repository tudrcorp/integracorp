<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

use App\Filament\Business\Pages\DashboardCaptacion;
use App\Filament\Business\Resources\ProspectAgents\Pages\ListProspectAgents;
use App\Filament\Business\Resources\ProspectAgents\ProspectAgentLabels;
use App\Filament\Business\Widgets\Captacion\CaptacionAssignmentsChart;
use App\Filament\Business\Widgets\Captacion\CaptacionCollaboratorRanking;
use App\Filament\Business\Widgets\Captacion\CaptacionDetail;
use App\Filament\Business\Widgets\Captacion\CaptacionKpiTiles;
use App\Filament\Business\Widgets\Captacion\CaptacionRegistrationsTrend;
use App\Filament\Business\Widgets\Captacion\CaptacionTypeChart;
use App\Models\HelpDesk;
use App\Models\ProspectAgent;
use App\Models\ProspectAgentTask;
use App\Models\RrhhColaborador;
use App\Models\User;
use App\Support\Filament\DepartmentNavigationPermissionRegistry;
use App\Support\HelpdeskTaskStatusOptions;
use App\Support\Operations\Dashboard\OperationsDashboardPeriod;
use App\Support\ProspectAgents\Dashboard\ProspectDashboardMetrics as Metrics;
use App\Support\ProspectAgents\ProspectAgentDeletion;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * Período aislado (enero de 2020, contra diciembre de 2019) para no mezclarse con los datos
 * reales. Todo lo que escribe va dentro de una transacción que siempre se revierte.
 */
const CAPTACION_FILTERS = ['period' => 'custom', 'from' => '2020-01-01', 'to' => '2020-01-31', 'compare' => true];

beforeEach(function (): void {
    DB::beginTransaction();
    Metrics::flush();

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
    $this->period = OperationsDashboardPeriod::fromFilters(CAPTACION_FILTERS);
});

afterEach(function (): void {
    DB::rollBack();
    Metrics::flush();
});

function captacionProspect(array $attributes, string $createdAt): ProspectAgent
{
    $prospect = ProspectAgent::query()->create([
        'name' => 'Pest Captación '.uniqid(),
        'status' => 'contacto-inicial',
        'type' => 'otro',
        'reference_by' => 'otro',
        'phone_1' => '04120000000',
        'country_id' => 189,
        'created_by' => 'Ana Pest',
        ...$attributes,
    ]);

    $prospect->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();

    return $prospect;
}

it('cuenta las altas del período contra el anterior', function (): void {
    captacionProspect([], '2020-01-05 10:00:00');
    captacionProspect([], '2020-01-20 10:00:00');
    captacionProspect([], '2019-12-10 10:00:00');

    $registrations = Metrics::registrations($this->period);

    expect($registrations['current'])->toBe(2)
        ->and($registrations['previous'])->toBe(1)
        ->and(array_sum($registrations['sparkline']))->toBe(2);
});

it('agrupa por colaborador, tipo, referido, estado y clasificación como los gráficos de siempre', function (): void {
    $stateId = (int) DB::table('states')->where('country_id', 189)->orderBy('id')->value('id');
    $stateName = (string) DB::table('states')->where('id', $stateId)->value('definition');

    captacionProspect(['created_by' => 'Ana Pest', 'type' => 'agencia-viajes', 'reference_by' => 'otro', 'state_id' => $stateId, 'classification' => 'VIP'], '2020-01-05 10:00:00');
    captacionProspect(['created_by' => 'Ana Pest', 'type' => 'agencia-viajes', 'classification' => '  '], '2020-01-06 10:00:00');
    captacionProspect(['created_by' => 'Luis Pest', 'type' => 'freelance'], '2020-01-07 10:00:00');

    $ranking = Metrics::byCollaborator($this->period);

    expect($ranking['rows'][0])->toMatchArray(['label' => 'Ana Pest', 'value' => 2])
        ->and($ranking['total'])->toBe(3)
        ->and(Metrics::byType($this->period))->toBe(['Agencia de Viajes' => 2, 'Freelance' => 1])
        ->and(Metrics::byState($this->period))->toMatchArray([$stateName => 1, Metrics::NO_STATE => 2])
        ->and(Metrics::byClassification($this->period))->toMatchArray(['VIP' => 1, Metrics::NO_CLASSIFICATION => 2])
        ->and(Metrics::byReference($this->period))->toHaveKey(ProspectAgentLabels::referenceLabel('otro'));
});

it('suma al embudo los estatus viejos sin importar mayúsculas, como antes', function (): void {
    $before = Metrics::funnelSnapshot();
    Metrics::flush();

    captacionProspect(['status' => 'Captación'], '2020-01-05 10:00:00');
    captacionProspect(['status' => 'en-proceso'], '2020-01-05 10:00:00');

    $after = Metrics::funnelSnapshot();

    expect($after['total'])->toBe($before['total'] + 2)
        ->and($after['statuses'][ProspectAgentLabels::STATUS_CAPTACION_PROSPECCION])->toBe($before['statuses'][ProspectAgentLabels::STATUS_CAPTACION_PROSPECCION] + 1)
        ->and($after['statuses'][ProspectAgentLabels::STATUS_EN_PROCESO_NEGOCIACION])->toBe($before['statuses'][ProspectAgentLabels::STATUS_EN_PROCESO_NEGOCIACION] + 1);
});

it('no cuenta prospectos eliminados ni sus tareas', function (): void {
    $kept = captacionProspect([], '2020-01-05 10:00:00');
    $deleted = captacionProspect([], '2020-01-06 10:00:00');

    foreach ([$kept, $deleted] as $prospect) {
        $task = ProspectAgentTask::query()->create(['prospect_agent_id' => $prospect->getKey(), 'task' => 'Llamar', 'created_by' => 'Ana Pest']);
        $task->forceFill(['created_at' => '2020-01-10 10:00:00'])->saveQuietly();
    }

    ProspectAgentDeletion::delete([$deleted], 'Duplicado para la prueba del dashboard', 'Pest');
    Metrics::flush();

    expect(Metrics::registrations($this->period)['current'])->toBe(1)
        ->and(Metrics::assignmentsByColaborador($this->period)['totals']['assigned'])->toBe(1);
});

it('mide por responsable lo asignado frente a lo resuelto, con tareas y tickets del Helpdesk', function (): void {
    $prospect = captacionProspect([], '2020-01-05 10:00:00');
    $ana = RrhhColaborador::query()->create(['fullName' => 'Ana Pest Asignada', 'status' => 'activo']);
    $luis = RrhhColaborador::query()->create(['fullName' => 'Luis Pest Asignado', 'status' => 'activo']);

    $task = fn (array $attributes, string $createdAt) => tap(
        ProspectAgentTask::query()->create(['prospect_agent_id' => $prospect->getKey(), 'task' => 'Llamar', 'created_by' => 'Quien asigna', ...$attributes]),
        fn (ProspectAgentTask $task) => $task->forceFill(['created_at' => $createdAt])->saveQuietly(),
    );

    $task(['rrhh_colaborador_id' => $ana->getKey()], '2020-01-10 10:00:00');
    $task(['rrhh_colaborador_id' => $ana->getKey(), 'status' => 'RESUELTA'], '2020-01-11 10:00:00');
    $task(['rrhh_colaborador_id' => $luis->getKey(), 'status' => 'RESUELTA'], '2020-01-12 10:00:00');
    $task([], '2020-01-13 10:00:00');
    $task(['rrhh_colaborador_id' => $ana->getKey()], '2019-12-20 10:00:00');

    $ticket = HelpDesk::query()->create([
        'description' => '<p>Ticket Pest</p>', 'priority' => 'MEDIA', 'status' => HelpdeskTaskStatusOptions::STATUS_DONE,
        'ticket_type' => 'service_request', 'created_by' => 'Quien asigna',
    ]);
    $ticket->forceFill(['created_at' => '2020-01-15 10:00:00'])->saveQuietly();
    $ticket->rrhhColaboradores()->sync([$ana->getKey()]);
    $ticket->prospectAgents()->sync([$prospect->getKey()]);

    $cancelled = HelpDesk::query()->create([
        'description' => '<p>Cancelado Pest</p>', 'priority' => 'MEDIA', 'status' => HelpdeskTaskStatusOptions::STATUS_CANCELLED,
        'ticket_type' => 'service_request', 'created_by' => 'Quien asigna',
    ]);
    $cancelled->forceFill(['created_at' => '2020-01-16 10:00:00'])->saveQuietly();
    $cancelled->rrhhColaboradores()->sync([$luis->getKey()]);
    $cancelled->prospectAgents()->sync([$prospect->getKey()]);

    $data = Metrics::assignmentsByColaborador($this->period);
    $rows = collect($data['rows'])->keyBy('label');

    expect($rows['Ana Pest Asignada'])->toMatchArray(['key' => (string) $ana->getKey(), 'assigned' => 3, 'resolved' => 2, 'pending' => 1, 'rate' => 66.7])
        ->and($rows['Luis Pest Asignado'])->toMatchArray(['assigned' => 1, 'resolved' => 1, 'pending' => 0, 'rate' => 100.0])
        ->and($rows[Metrics::NO_ASSIGNEE])->toMatchArray(['key' => '0', 'assigned' => 1, 'resolved' => 0])
        ->and($data['rows'][0]['label'])->toBe('Ana Pest Asignada')
        ->and($data['totals'])->toBe(['assigned' => 5, 'resolved' => 3]);

    $detail = Metrics::detail($this->period, 'assignee', (string) $ana->getKey());

    expect($detail['total'])->toBe(3)
        ->and(collect($detail['rows'])->pluck('kind')->implode('|'))->toContain('Ticket Helpdesk N.º '.$ticket->getKey())
        ->and(collect($detail['rows'])->where('resolved', 'Sí')->count())->toBe(2)
        ->and(Metrics::detailLabel('assignee', (string) $ana->getKey()))->toBe('Asignado a Ana Pest Asignada')
        ->and(Metrics::detail($this->period, 'assignee', '0')['total'])->toBe(1)
        ->and(Metrics::detailLabel('assignee', '0'))->toBe('Asignado a '.Metrics::NO_ASSIGNEE);
});

it('el detalle traduce la etiqueta de la barra a todos sus valores guardados', function (): void {
    captacionProspect(['type' => 'agencia-viajes', 'name' => 'Pest Viajes Uno'], '2020-01-05 10:00:00');
    captacionProspect(['type' => 'freelance', 'name' => 'Pest Freelance'], '2020-01-06 10:00:00');

    $detail = Metrics::detail($this->period, 'type', 'Agencia de Viajes');

    expect($detail['total'])->toBe(1)
        ->and($detail['rows'][0])->toMatchArray(['case_code' => 'Pest Viajes Uno', 'type' => 'Agencia de Viajes', 'status' => 'Contacto inicial']);
});

it('el detalle de colaborador, sin colaborador y de tareas devuelve sus filas', function (): void {
    $prospect = captacionProspect(['created_by' => 'Ana Pest', 'name' => 'Pest Ana'], '2020-01-05 10:00:00');
    captacionProspect(['created_by' => '', 'name' => 'Pest Huérfano'], '2020-01-06 10:00:00');
    $task = ProspectAgentTask::query()->create(['prospect_agent_id' => $prospect->getKey(), 'task' => 'Llamar al gerente', 'created_by' => 'Ana Pest']);
    $task->forceFill(['created_at' => '2020-01-10 10:00:00'])->saveQuietly();

    expect(Metrics::detail($this->period, 'collaborator', 'Ana Pest')['rows'][0]['case_code'])->toBe('Pest Ana')
        ->and(Metrics::detail($this->period, 'collaborator', Metrics::NO_COLLABORATOR)['rows'][0]['case_code'])->toBe('Pest Huérfano')
        ->and(Metrics::detail($this->period, 'desconocida', 'x')['total'])->toBe(0);
});

it('la página abre para quien tiene acceso a Captación y la lista enlaza al dashboard', function (): void {
    expect(DepartmentNavigationPermissionRegistry::slugsFor(DashboardCaptacion::class))->toBe(['capacitacion'])
        ->and(DashboardCaptacion::canAccess())->toBeTrue();

    Livewire::test(DashboardCaptacion::class)->assertOk()->assertSee('Dashboard de Captación');

    Livewire::test(ListProspectAgents::class)
        ->assertActionVisible('dashboard')
        ->assertActionHasUrl('dashboard', DashboardCaptacion::getUrl());

    expect((new ReflectionMethod(ListProspectAgents::class, 'getHeaderWidgets'))->getDeclaringClass()->getName())
        ->not->toBe(ListProspectAgents::class);
});

it('los widgets se dibujan con el período de la página', function (): void {
    captacionProspect(['created_by' => 'Ana Pest', 'type' => 'freelance'], '2020-01-05 10:00:00');

    Livewire::test(CaptacionKpiTiles::class, ['pageFilters' => CAPTACION_FILTERS])
        ->assertSee('Altas del período')
        ->assertSee('Aliado activo')
        ->assertSee('Captación a hoy');

    Livewire::test(CaptacionCollaboratorRanking::class, ['pageFilters' => CAPTACION_FILTERS])
        ->assertSee('Ana Pest');

    $assignments = Livewire::test(CaptacionAssignmentsChart::class, ['pageFilters' => CAPTACION_FILTERS]);

    expect($assignments->instance()->getDescription())->toContain('No se asignaron tareas ni tickets');

    $chart = Livewire::test(CaptacionTypeChart::class, ['pageFilters' => CAPTACION_FILTERS]);

    $data = (new ReflectionMethod(CaptacionTypeChart::class, 'getData'))->invoke($chart->instance());

    expect($data['labels'])->toBe(['Freelance'])
        ->and($chart->html())->not->toContain('"onClick');
});

it('el detalle abre la lista y enlaza cada prospecto a su ficha', function (): void {
    $prospect = captacionProspect(['created_by' => 'Ana Pest', 'name' => 'Pest Ficha'], '2020-01-05 10:00:00');

    Livewire::test(CaptacionDetail::class, ['pageFilters' => CAPTACION_FILTERS])
        ->call('openDetail', 'collaborator', 'Ana Pest')
        ->assertDispatched('open-modal', id: CaptacionDetail::CAPTACION_MODAL_ID)
        ->assertSee('Registrados por Ana Pest')
        ->assertSee('Pest Ficha')
        ->assertSee('/business/prospect-agents/'.$prospect->getKey(), false);

    Livewire::test(CaptacionDetail::class, ['pageFilters' => CAPTACION_FILTERS])
        ->call('openDetail', 'no-permitida', 'x')
        ->assertNotDispatched('open-modal');
});

it('dibuja la evolución de las altas como barras, con el período anterior al lado', function (): void {
    captacionProspect([], '2020-01-05 10:00:00');
    captacionProspect([], '2019-12-05 10:00:00');

    $widget = Livewire::test(CaptacionRegistrationsTrend::class, ['pageFilters' => CAPTACION_FILTERS])->instance();
    $type = (new ReflectionMethod(CaptacionRegistrationsTrend::class, 'getType'))->invoke($widget);
    $data = (new ReflectionMethod(CaptacionRegistrationsTrend::class, 'getData'))->invoke($widget);
    $options = $widget->getOptionsForTest();

    expect($type)->toBe('bar')
        ->and($data['datasets'])->toHaveCount(2)
        ->and(array_sum($data['datasets'][0]['data']))->toEqual(1)
        ->and(array_sum($data['datasets'][1]['data']))->toEqual(1)
        ->and($data['datasets'][0])->not->toHaveKey('tension')
        ->and($data['datasets'][1]['role'])->toBe('previous')
        ->and($options)->toContain('bar: {')
        ->and($options)->not->toContain('line: {')
        ->and($options)->toContain('plugins:')
        ->and($options)->not->toContain('"');
});

it('no aparece en el menú y se abre desde el botón resaltado de la lista', function (): void {
    expect(DashboardCaptacion::shouldRegisterNavigation())->toBeFalse()
        ->and(DashboardCaptacion::canAccess())->toBeTrue();

    $source = file_get_contents(app_path('Filament/Business/Resources/ProspectAgents/Pages/ListProspectAgents.php'));

    expect($source)->toContain("Action::make('dashboard')")
        ->toContain("FilamentIosButton::extraClassForFilamentColor('primary')");

    Livewire::test(ListProspectAgents::class)
        ->assertActionVisible('dashboard')
        ->assertActionHasColor('dashboard', 'primary');
});

it('el gráfico de asignados dibuja dos series por responsable y abre el detalle por su clave', function (): void {
    $prospect = captacionProspect([], '2020-01-05 10:00:00');
    $ana = RrhhColaborador::query()->create(['fullName' => 'Ana Pest Grafico', 'status' => 'activo']);
    $task = ProspectAgentTask::query()->create(['prospect_agent_id' => $prospect->getKey(), 'task' => 'Llamar', 'created_by' => 'X', 'rrhh_colaborador_id' => $ana->getKey(), 'status' => 'RESUELTA']);
    $task->forceFill(['created_at' => '2020-01-10 10:00:00'])->saveQuietly();

    $widget = Livewire::test(CaptacionAssignmentsChart::class, ['pageFilters' => CAPTACION_FILTERS])->instance();
    $data = (new ReflectionMethod(CaptacionAssignmentsChart::class, 'getData'))->invoke($widget);
    $options = $widget->getOptionsForTest();

    expect($data['labels'])->toBe(['Ana Pest Grafico'])
        ->and($data['datasets'][0])->toMatchArray(['label' => 'Asignados', 'data' => [1], 'keys' => [(string) $ana->getKey()]])
        ->and($data['datasets'][1])->toMatchArray(['label' => 'Resueltos', 'data' => [1], 'role' => 'good'])
        ->and($widget->getDescription())->toContain('1 asignados · 1 resueltos (100,0 %)')
        ->and($options)->toContain("dimension: 'assignee'")
        ->and($options)->toContain('Respuesta: ')
        ->and($options)->not->toContain('"');

    Livewire::test(CaptacionDetail::class, ['pageFilters' => CAPTACION_FILTERS])
        ->call('openDetail', 'assignee', (string) $ana->getKey())
        ->assertSee('Asignado a Ana Pest Grafico')
        ->assertSee('Tarea N.º '.$task->getKey());
});
