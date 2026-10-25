<?php

declare(strict_types=1);

use App\Enums\TelemedicinePanelEventCategory;
use App\Enums\TelemedicinePanelEventLevel;
use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Pages\CreateTelemedicineConsultationPatient;
use App\Models\TelemedicinePanelEvent;
use App\Models\User;
use App\Support\Telemedicine\PanelLog\TelemedicinePanelClientEvent;
use App\Support\Telemedicine\PanelLog\TelemedicinePanelEventQueries;
use App\Support\Telemedicine\PanelLog\TelemedicinePanelLivewireMonitor;
use App\Support\Telemedicine\PanelLog\TelemedicinePanelLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Mechanisms\HandleComponents\ComponentContext;
use Tests\Support\TelemedicinePanelLog\FakeConsultationForm;
use Tests\Support\TelemedicinePanelLog\FakePanelPage;

use function Livewire\store;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    DB::beginTransaction();
    TelemedicinePanelLivewireMonitor::flushPending();
    session()->forget('filament.notifications');
    panelLogAt('/telemedicina/telemedicine-consultation-patients/create');
});

afterEach(function (): void {
    TelemedicinePanelLog::forceScopeForTesting(null);
    DB::rollBack();
    TelemedicinePanelLivewireMonitor::flushPending();
    app()->forgetScopedInstances();
});

/**
 * Nueva instancia de la bitácora como si la petición viniera de esa ruta.
 */
function panelLogAt(string $path): TelemedicinePanelLog
{
    app()->instance('request', Request::create($path));
    app()->forgetScopedInstances();

    return TelemedicinePanelLog::current();
}

function fakeConsultationForm(bool $busy = false): FakeConsultationForm
{
    return FakeConsultationForm::make($busy);
}

/**
 * @return list<array<string, mixed>>
 */
function pendingPanelEvents(): array
{
    return TelemedicinePanelLog::current()->pending();
}

it('reconoce solo las rutas del panel de Telemedicina', function (): void {
    expect(TelemedicinePanelLog::isTelemedicinePath('/telemedicina'))->toBeTrue()
        ->and(TelemedicinePanelLog::isTelemedicinePath('telemedicina/telemedicine-cases/5'))->toBeTrue()
        ->and(TelemedicinePanelLog::isTelemedicinePath('https://www.integracorp.test/telemedicina/x?y=1'))->toBeTrue()
        ->and(TelemedicinePanelLog::isTelemedicinePath('/telemedicina-falsa'))->toBeFalse()
        ->and(TelemedicinePanelLog::isTelemedicinePath('/operations/telemedicina'))->toBeFalse()
        ->and(TelemedicinePanelLog::isTelemedicinePath(null))->toBeFalse();
});

it('marca como error invisible un mensaje cuya clave ningún campo puede mostrar', function (): void {
    $form = fakeConsultationForm();

    TelemedicinePanelLivewireMonitor::onCall($form, 'create', []);
    TelemedicinePanelLivewireMonitor::onException($form, ValidationException::withMessages([
        'telemedicine_service_list_id' => 'Ese servicio no está incluido en el uso clínico de este plan.',
    ]));

    $event = pendingPanelEvents()[0];

    expect($event['event'])->toBe('validacion_invisible')
        ->and($event['level'])->toBe(TelemedicinePanelEventLevel::Error->value)
        ->and($event['category'])->toBe(TelemedicinePanelEventCategory::Validation->value)
        ->and($event['title'])->toBe('Mensaje que el médico NO pudo ver al pulsar «Registrar»')
        ->and($event['message'])->toContain('no está incluido en el uso clínico')
        ->and($event['context']['campos_sin_lugar_en_pantalla'])->toBe(['telemedicine_service_list_id'])
        ->and($event['telemedicine_service_list_id'])->toBe(25)
        ->and($event['action'])->toBe('create');
});

it('un mensaje sobre un campo del formulario es un tropiezo visible', function (): void {
    $form = fakeConsultationForm();

    TelemedicinePanelLivewireMonitor::onException($form, ValidationException::withMessages([
        'data.telemedicine_service_list_drift_id' => 'El campo tipo de servicio de deriva es obligatorio.',
    ]));

    $event = pendingPanelEvents()[0];

    expect($event['event'])->toBe('validacion')
        ->and($event['level'])->toBe(TelemedicinePanelEventLevel::Warning->value)
        ->and($event['fields'])->toBe(['data.telemedicine_service_list_drift_id' => ['El campo tipo de servicio de deriva es obligatorio.']])
        ->and($event['context'] ?? [])->toBe([]);
});

it('pulsar Registrar sin ningún resultado visible queda como sin respuesta', function (): void {
    $form = fakeConsultationForm();

    TelemedicinePanelLivewireMonitor::onCall($form, 'create', []);
    TelemedicinePanelLivewireMonitor::onDehydrate($form, new ComponentContext($form));

    $event = pendingPanelEvents()[0];

    expect($event['event'])->toBe('sin_respuesta')
        ->and($event['category'])->toBe(TelemedicinePanelEventCategory::NoResponse->value)
        ->and($event['title'])->toBe('Pulsó «Registrar» y no pasó nada visible')
        ->and($event['context']['formulario_ocupado'])->toBeFalse();
});

it('explica cuando Filament ignora el clic porque quedó marcado como guardando', function (): void {
    $form = fakeConsultationForm(busy: true);

    TelemedicinePanelLivewireMonitor::onCall($form, 'create', []);
    TelemedicinePanelLivewireMonitor::onDehydrate($form, new ComponentContext($form));

    expect(pendingPanelEvents()[0]['message'])->toContain('seguía marcado como «guardando»')
        ->and(pendingPanelEvents()[0]['context']['formulario_ocupado'])->toBeTrue();
});

it('registra el guardado cuando hubo redirección', function (): void {
    $form = fakeConsultationForm();

    TelemedicinePanelLivewireMonitor::onCall($form, 'create', []);
    store($form)->set('redirect', '/telemedicina/telemedicine-cases/5');
    session()->push('filament.notifications', ['title' => 'Telemedicina creada exitosamente', 'status' => 'success']);
    TelemedicinePanelLivewireMonitor::onDehydrate($form, new ComponentContext($form));

    $events = pendingPanelEvents();

    expect($events)->toHaveCount(1)
        ->and($events[0]['event'])->toBe('guardado')
        ->and($events[0]['level'])->toBe(TelemedicinePanelEventLevel::Info->value)
        ->and($events[0]['context']['redirigido'])->toBeTrue()
        ->and($events[0]['context']['avisos'])->toBe(['Telemedicina creada exitosamente']);
});

it('anota el aviso de error que vio el médico y no lo da por guardado', function (): void {
    $form = fakeConsultationForm();

    TelemedicinePanelLivewireMonitor::onCall($form, 'create', []);
    session()->push('filament.notifications', ['title' => 'Error al crear la telemedicina', 'body' => '<p>Intente de nuevo</p>', 'status' => 'danger']);
    TelemedicinePanelLivewireMonitor::onDehydrate($form, new ComponentContext($form));

    $events = pendingPanelEvents();

    expect($events)->toHaveCount(1)
        ->and($events[0]['event'])->toBe('aviso_error')
        ->and($events[0]['title'])->toBe('Aviso mostrado al pulsar «Registrar»: Error al crear la telemedicina')
        ->and($events[0]['message'])->toBe('Intente de nuevo');
});

it('solo cuenta los avisos de este clic, no los que ya estaban en sesión', function (): void {
    session()->push('filament.notifications', ['title' => 'Aviso viejo', 'status' => 'danger']);
    $form = fakeConsultationForm();

    TelemedicinePanelLivewireMonitor::onCall($form, 'create', []);
    TelemedicinePanelLivewireMonitor::onDehydrate($form, new ComponentContext($form));

    expect(pendingPanelEvents())->toHaveCount(1)
        ->and(pendingPanelEvents()[0]['event'])->toBe('sin_respuesta');
});

it('un error del servidor se registra una sola vez aunque llegue también al manejador global', function (): void {
    $form = fakeConsultationForm();
    $exception = new RuntimeException('Fallo de prueba');

    TelemedicinePanelLivewireMonitor::onCall($form, 'create', []);
    TelemedicinePanelLivewireMonitor::onException($form, $exception);
    TelemedicinePanelLog::captureReportedException($exception);
    TelemedicinePanelLivewireMonitor::onDehydrate($form, new ComponentContext($form));

    $events = pendingPanelEvents();

    expect($events)->toHaveCount(1)
        ->and($events[0]['event'])->toBe('error_servidor')
        ->and($events[0]['title'])->toBe('Error del servidor al pulsar «Registrar»: RuntimeException')
        ->and($events[0]['context']['exception'])->toBe(RuntimeException::class);
});

it('el manejador global registra errores del panel que no pasaron por Livewire', function (): void {
    TelemedicinePanelLog::captureReportedException(new LogicException('Página rota'));
    TelemedicinePanelLog::captureReportedException(ValidationException::withMessages(['x' => 'y']));

    expect(pendingPanelEvents())->toHaveCount(1)
        ->and(pendingPanelEvents()[0]['message'])->toBe('Página rota');
});

it('fuera del panel de Telemedicina no registra nada', function (): void {
    panelLogAt('/operations/telemedicine-cases');
    $form = fakeConsultationForm();

    TelemedicinePanelLivewireMonitor::onCall($form, 'create', []);
    TelemedicinePanelLivewireMonitor::onException($form, ValidationException::withMessages(['x' => 'y']));
    TelemedicinePanelLivewireMonitor::onDehydrate($form, new ComponentContext($form));
    TelemedicinePanelLog::captureReportedException(new LogicException('otro panel'));

    expect(pendingPanelEvents())->toBe([]);
});

it('ignora llamadas que no son clics del médico', function (): void {
    $form = fakeConsultationForm();

    foreach (['$refresh', '__dispatch', 'loadMore', 'callSchemaComponentMethod'] as $method) {
        TelemedicinePanelLivewireMonitor::onCall($form, $method, ['form.x', 'otroMetodo']);
        TelemedicinePanelLivewireMonitor::onDehydrate($form, new ComponentContext($form));
    }

    expect(pendingPanelEvents())->toBe([]);
});

it('anota la pantalla abierta solo al montar una página de Filament', function (): void {
    $page = FakePanelPage::make();
    $widget = fakeConsultationForm();

    TelemedicinePanelLivewireMonitor::onDehydrate($page, new ComponentContext($page, mounting: true));
    TelemedicinePanelLivewireMonitor::onDehydrate($widget, new ComponentContext($widget, mounting: true));
    TelemedicinePanelLivewireMonitor::onDehydrate($page, new ComponentContext($page));

    expect(pendingPanelEvents())->toHaveCount(1)
        ->and(pendingPanelEvents()[0]['event'])->toBe('pagina_abierta')
        ->and(pendingPanelEvents()[0]['livewire_id'])->toBe($page->getId());
});

it('nunca guarda el contenido del formulario', function (): void {
    $form = fakeConsultationForm();

    TelemedicinePanelLivewireMonitor::onCall($form, 'create', []);
    TelemedicinePanelLivewireMonitor::onDehydrate($form, new ComponentContext($form));

    expect(json_encode(pendingPanelEvents()))->not->toContain('NUNCA SE GUARDA');
});

it('escribe todo en un solo insert al terminar y la tabla lo devuelve', function (): void {
    $before = TelemedicinePanelEvent::query()->count();
    $form = fakeConsultationForm();

    TelemedicinePanelLivewireMonitor::onException($form, ValidationException::withMessages(['complements' => 'Cupo cubierto']));
    TelemedicinePanelLivewireMonitor::onCall($form, 'create', []);
    TelemedicinePanelLivewireMonitor::onDehydrate($form, new ComponentContext($form));
    TelemedicinePanelLog::current()->flush();

    $saved = TelemedicinePanelEvent::query()->latest('id')->limit(2)->get()->sortBy('id')->values();

    expect(TelemedicinePanelEvent::query()->count())->toBe($before + 2)
        ->and(TelemedicinePanelLog::current()->pending())->toBe([])
        ->and($saved[0]->fields)->toBe(['complements' => ['Cupo cubierto']])
        ->and($saved[0]->context)->toBe(['campos_sin_lugar_en_pantalla' => ['complements']])
        ->and($saved[0]->isProblem())->toBeTrue()
        ->and($saved[1]->event)->toBe('sin_respuesta')
        ->and($saved[1]->screen)->not->toBe('');
});

it('tiene un tope de eventos por petición', function (): void {
    $log = TelemedicinePanelLog::current();

    for ($i = 0; $i < TelemedicinePanelLog::MAX_EVENTS_PER_REQUEST + 10; $i++) {
        $log->record(TelemedicinePanelEventCategory::Action, TelemedicinePanelEventLevel::Info, 'x', 'Evento '.$i);
    }

    expect($log->pending())->toHaveCount(TelemedicinePanelLog::MAX_EVENTS_PER_REQUEST);
});

it('la secuencia agrupa por formulario y las consultas sin guardar excluyen las guardadas', function (): void {
    $base = ['level' => 'info', 'category' => 'navegacion', 'title' => 't', 'component' => CreateTelemedicineConsultationPatient::class];
    $old = now()->subHours(2);

    $abandoned = TelemedicinePanelEvent::query()->create($base + ['event' => 'pagina_abierta', 'livewire_id' => 'lw-abandonado', 'occurred_at' => $old]);
    TelemedicinePanelEvent::query()->create($base + ['event' => 'validacion', 'level' => 'warning', 'livewire_id' => 'lw-abandonado', 'occurred_at' => $old->copy()->addMinute()]);
    $saved = TelemedicinePanelEvent::query()->create($base + ['event' => 'pagina_abierta', 'livewire_id' => 'lw-guardado', 'occurred_at' => $old]);
    TelemedicinePanelEvent::query()->create($base + ['event' => 'guardado', 'livewire_id' => 'lw-guardado', 'occurred_at' => $old->copy()->addMinutes(5)]);
    $recent = TelemedicinePanelEvent::query()->create($base + ['event' => 'pagina_abierta', 'livewire_id' => 'lw-reciente', 'occurred_at' => now()]);

    $ids = TelemedicinePanelEventQueries::abandonedConsultationForms(TelemedicinePanelEvent::query())->pluck('id')->all();

    expect($ids)->toContain($abandoned->id)
        ->not->toContain($saved->id)
        ->not->toContain($recent->id)
        ->and(TelemedicinePanelEventQueries::sequenceFor($abandoned)->pluck('event')->all())->toBe(['pagina_abierta', 'validacion']);
});

it('purga solo lo que tiene más de 90 días', function (): void {
    $old = TelemedicinePanelEvent::query()->create(['level' => 'info', 'category' => 'accion', 'event' => 'x', 'title' => 'viejo', 'occurred_at' => now()->subDays(91)]);
    $kept = TelemedicinePanelEvent::query()->create(['level' => 'info', 'category' => 'accion', 'event' => 'x', 'title' => 'reciente', 'occurred_at' => now()->subDays(89)]);

    $prunable = (new TelemedicinePanelEvent)->prunable()->pluck('id')->all();

    expect($prunable)->toContain($old->id)->not->toContain($kept->id);
});

it('los ganchos funcionan dentro de Livewire real', function (): void {
    TelemedicinePanelLog::forceScopeForTesting(true);
    $lastId = (int) TelemedicinePanelEvent::query()->max('id');

    Livewire\Livewire::test(FakeConsultationForm::class)->call('create');
    Livewire\Livewire::test(FakeConsultationForm::class)
        ->set('failWith', 'telemedicine_service_list_id')
        ->call('create');
    TelemedicinePanelLog::current()->flush();

    $events = TelemedicinePanelEvent::query()->where('id', '>', $lastId)->orderBy('id')->get();

    expect($events->pluck('event')->all())->toBe(['sin_respuesta', 'validacion_invisible'])
        ->and($events[1]->title)->toBe('Mensaje que el médico NO pudo ver al pulsar «Registrar»')
        ->and($events[1]->component)->toBe(FakeConsultationForm::class)
        ->and($events[0]->livewire_id)->not->toBe($events[1]->livewire_id);
});

it('el navegador reporta fallos y solo se aceptan rutas del panel', function (): void {
    $this->withoutMiddleware([Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);
    $this->actingAs(User::factory()->make(['id' => 987654, 'name' => 'MÉDICO DE PRUEBA']));

    app()->forgetScopedInstances();
    $this->postJson('/tm/b', [
        'event' => TelemedicinePanelClientEvent::SESSION_EXPIRED,
        'path' => '/telemedicina/telemedicine-consultation-patients/create?token=secreto',
        'status' => 419,
        'methods' => ['create'],
        'components' => ['app.filament.telemedicina.resources.create-consultation'],
    ])->assertNoContent();

    $event = TelemedicinePanelEvent::query()->latest('id')->first();

    expect($event->event)->toBe('sesion_vencida')
        ->and($event->title)->toBe('La sesión venció al pulsar «Registrar»')
        ->and($event->category)->toBe(TelemedicinePanelEventCategory::Browser->value)
        ->and($event->url)->toBe('/telemedicina/telemedicine-consultation-patients/create')
        ->and($event->user_name)->toBe('MÉDICO DE PRUEBA')
        ->and($event->context['estado_http'])->toBe(419);

    $count = TelemedicinePanelEvent::query()->count();
    app()->forgetScopedInstances();
    $this->postJson('/tm/b', ['event' => 'error_navegador', 'path' => '/business/algo', 'message' => 'x'])->assertNoContent();
    expect(TelemedicinePanelEvent::query()->count())->toBe($count);

    $this->postJson('/tm/b', ['event' => 'inventado', 'path' => '/telemedicina'])->assertUnprocessable();
});

it('el navegador sin sesión no puede escribir en la bitácora', function (): void {
    $this->withoutMiddleware([Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);

    $this->postJson('/tm/b', ['event' => 'error_navegador', 'path' => '/telemedicina'])->assertUnauthorized();
});

it('queda conectada al panel, al menú de Operaciones, al permiso y a la purga', function (): void {
    $root = dirname(__DIR__, 2);

    expect(file_get_contents($root.'/app/Providers/Filament/TelemedicinaPanelProvider.php'))->toContain("view('filament.telemedicina.hooks.panel-log')")
        ->and(file_get_contents($root.'/app/Providers/AppServiceProvider.php'))
        ->toContain('TelemedicinePanelLivewireMonitor::register();')
        ->toContain('$this->app->scoped(TelemedicinePanelLog::class);')
        ->and(file_get_contents($root.'/bootstrap/app.php'))->toContain('TelemedicinePanelLog::captureReportedException($exception);')
        ->and(file_get_contents($root.'/routes/console.php'))->toContain("'--model' => [\\App\\Models\\TelemedicinePanelEvent::class]")
        ->and(App\Support\Filament\DepartmentNavigationPermissionRegistry::slugsFor(App\Filament\Operations\Resources\TelemedicinePanelEvents\TelemedicinePanelEventResource::class))
        ->toBe(['bitacora-panel-telemedicina'])
        ->and(App\Filament\Operations\Resources\TelemedicinePanelEvents\TelemedicinePanelEventResource::canCreate())->toBeFalse();
});
