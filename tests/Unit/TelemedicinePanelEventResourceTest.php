<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\TelemedicinePanelEvents\Pages\ListTelemedicinePanelEvents;
use App\Filament\Operations\Resources\TelemedicinePanelEvents\Pages\ViewTelemedicinePanelEvent;
use App\Filament\Operations\Resources\TelemedicinePanelEvents\TelemedicinePanelEventResource;
use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Pages\CreateTelemedicineConsultationPatient;
use App\Models\TelemedicinePanelEvent;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    DB::beginTransaction();
    Filament::setCurrentPanel('operations');
});

afterEach(fn () => DB::rollBack());

function actingAsPanelLogUser(array $departments): void
{
    test()->actingAs(User::factory()->make([
        'id' => 999_999_031,
        'name' => 'QA BITACORA TELEMEDICINA',
        'email' => 'qa.bitacora.telemedicina@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => $departments,
    ]));
}

/**
 * @param  array<string, mixed>  $attributes
 */
function panelEvent(array $attributes = []): TelemedicinePanelEvent
{
    return TelemedicinePanelEvent::query()->create($attributes + [
        'occurred_at' => now(),
        'level' => 'error',
        'category' => 'tropiezo',
        'event' => 'validacion_invisible',
        'title' => 'Mensaje que el médico NO pudo ver al pulsar «Registrar»',
        'message' => 'Ese servicio no está incluido en el uso clínico de este plan.',
        'user_name' => 'DRA. QA',
        'telemedicine_case_code' => 'QA-'.uniqid(),
        'livewire_id' => 'lw-'.uniqid(),
        'component' => CreateTelemedicineConsultationPatient::class,
        'fields' => ['telemedicine_service_list_id' => ['Ese servicio no está incluido en el uso clínico de este plan.']],
        'context' => ['campos_sin_lugar_en_pantalla' => ['telemedicine_service_list_id']],
    ]);
}

it('el SUPERADMIN ve la bitácora y la lista abre en «Problemas»', function (): void {
    actingAsPanelLogUser(['SUPERADMIN', 'OPERACIONES']);
    $problem = panelEvent();
    $info = panelEvent(['level' => 'info', 'category' => 'accion', 'event' => 'guardado', 'title' => 'Pulsó «Registrar» y se guardó']);

    expect(TelemedicinePanelEventResource::canAccess())->toBeTrue();

    Livewire::test(ListTelemedicinePanelEvents::class)
        ->assertSuccessful()
        ->assertSet('activeTab', 'problems')
        ->searchTable($problem->telemedicine_case_code)
        ->assertCanSeeTableRecords([$problem])
        ->searchTable($info->telemedicine_case_code)
        ->assertCanNotSeeTableRecords([$info])
        ->set('activeTab', 'all')
        ->assertCanSeeTableRecords([$info]);
});

it('las pestañas separan sin respuesta, mensajes invisibles y navegador', function (): void {
    actingAsPanelLogUser(['SUPERADMIN']);
    $code = 'QA-'.uniqid();
    $noResponse = panelEvent(['category' => 'sin_respuesta', 'event' => 'sin_respuesta', 'title' => 'Pulsó «Registrar» y no pasó nada visible', 'telemedicine_case_code' => $code]);
    $invisible = panelEvent(['telemedicine_case_code' => $code]);
    $browser = panelEvent(['category' => 'navegador', 'event' => 'sesion_vencida', 'title' => 'La sesión venció', 'telemedicine_case_code' => $code]);

    Livewire::test(ListTelemedicinePanelEvents::class)
        ->searchTable($code)
        ->set('activeTab', 'no_response')
        ->assertCanSeeTableRecords([$noResponse])
        ->assertCanNotSeeTableRecords([$invisible, $browser])
        ->set('activeTab', 'invisible')
        ->assertCanSeeTableRecords([$invisible])
        ->assertCanNotSeeTableRecords([$noResponse, $browser])
        ->set('activeTab', 'browser')
        ->assertCanSeeTableRecords([$browser])
        ->assertCanNotSeeTableRecords([$noResponse, $invisible]);
});

it('el detalle muestra el mensaje, los campos y la secuencia del formulario', function (): void {
    actingAsPanelLogUser(['SUPERADMIN']);
    $livewireId = 'lw-'.uniqid();
    panelEvent(['level' => 'info', 'category' => 'navegacion', 'event' => 'pagina_abierta', 'title' => 'Abrió la pantalla', 'livewire_id' => $livewireId, 'occurred_at' => now()->subMinutes(3)]);
    $event = panelEvent(['livewire_id' => $livewireId]);

    Livewire::test(ViewTelemedicinePanelEvent::class, ['record' => $event->getKey()])
        ->assertSuccessful()
        ->assertSee('Mensaje que el médico NO pudo ver')
        ->assertSee('Ese servicio no está incluido en el uso clínico de este plan.')
        ->assertSee('Abrió la pantalla')
        ->assertSee('telemedicine_service_list_id');
});

it('un analista de Operaciones sin el permiso no la ve', function (): void {
    actingAsPanelLogUser(['OPERACIONES']);

    expect(TelemedicinePanelEventResource::canAccess())->toBeFalse()
        ->and(TelemedicinePanelEventResource::canCreate())->toBeFalse()
        ->and(TelemedicinePanelEventResource::canEdit(new TelemedicinePanelEvent))->toBeFalse()
        ->and(TelemedicinePanelEventResource::canDelete(new TelemedicinePanelEvent))->toBeFalse();
});
