<?php

declare(strict_types=1);

use App\Enums\OrgStructureVersionStatus;
use App\Filament\Administration\Pages\Organigrama as AdministrationOrganigrama;
use App\Filament\Business\Pages\EstructuraOrganizacional;
use App\Filament\Business\Pages\Organigrama as BusinessOrganigrama;
use App\Jobs\GenerateOrgChartExportJob;
use App\Models\OrgPosition;
use App\Models\OrgPositionAssignment;
use App\Models\OrgStructureVersion;
use App\Models\RrhhColaborador;
use App\Models\User;
use App\Support\OrgStructure\OrgStructureCache;
use App\Support\OrgStructure\OrgStructureDrafts;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Montan las páginas reales con Livewire. Escriben: transacción revertida y cola
 * simulada.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    Queue::fake();

    $user = User::factory()->make([
        'id' => 999_998_101,
        'name' => 'QA ORGANIGRAMA',
        'email' => 'qa.organigrama@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['SUPERADMIN', 'ADMINISTRACION', 'NEGOCIOS'],
    ]);
    $user->setRelation('permissions', new Collection);

    $this->actingAs($user);
});

afterEach(fn () => DB::rollBack());

function orgPagesCurrentVersion(): OrgStructureVersion
{
    $version = OrgStructureVersion::factory()->current()->create(['name' => 'Organigrama QA']);
    $root = OrgPosition::factory()->create(['version_id' => $version->id, 'title' => 'Presidencia QA', 'parent_id' => null]);
    $person = RrhhColaborador::query()->create(['fullName' => 'Persona QA', 'status' => 'activo', 'created_by' => 'pest']);
    OrgPositionAssignment::factory()->create(['version_id' => $version->id, 'position_id' => $root->id, 'rrhh_colaborador_id' => $person->id]);
    OrgPosition::factory()->create(['version_id' => $version->id, 'title' => 'Gerencia QA', 'parent_id' => $root->id]);

    return $version;
}

it('muestra el estado vacío cuando todavía no hay organigrama publicado', function (): void {
    Filament::setCurrentPanel('business');

    Livewire::test(BusinessOrganigrama::class)
        ->assertSuccessful()
        ->assertSee('Todavía no hay un organigrama publicado');
});

it('la página completa carga el script del organigrama en el head', function (): void {
    orgPagesCurrentVersion();

    $html = $this->withoutMiddleware(\App\Http\Middleware\DuplicatedSession::class)
        ->get('/business/organigrama')
        ->assertSuccessful()
        ->getContent();

    expect($html)->toContain('js/org-chart.js?v=')
        ->and(strpos($html, 'js/org-chart.js'))->toBeLessThan(strpos($html, '</head>'))
        ->and($html)->toContain('Presidencia QA');
});

it('entrega el organigrama vigente en el HTML y permite consultar una versión del historial', function (): void {
    Filament::setCurrentPanel('business');
    $version = orgPagesCurrentVersion();

    $component = Livewire::test(BusinessOrganigrama::class)
        ->assertSuccessful()
        ->assertSee('Presidencia QA')
        ->assertSee('Persona QA');

    $loaded = $component->instance()->loadVersion($version->id);

    expect($loaded['ok'])->toBeTrue()
        ->and(collect($loaded['payload']['nodes'])->pluck('title')->all())->toContain('Gerencia QA');

    $draft = OrgStructureVersion::factory()->create(['status' => OrgStructureVersionStatus::Draft]);

    expect($component->instance()->loadVersion($draft->id)['ok'])->toBeFalse();
});

it('pone en cola el PDF una sola vez por usuario y avisa si ya hay uno generándose', function (): void {
    Filament::setCurrentPanel('business');
    $version = orgPagesCurrentVersion();
    $page = Livewire::test(BusinessOrganigrama::class)->instance();

    $first = $page->requestPdfExport($version->id, ['depth' => 3]);
    $second = $page->requestPdfExport($version->id, []);

    expect($first['ok'])->toBeTrue()
        ->and($second['ok'])->toBeFalse()
        ->and($second['message'])->toContain('generándose');

    Queue::assertPushed(GenerateOrgChartExportJob::class, 1);

    GenerateOrgChartExportJob::releaseSlot(999_998_101);
});

it('abre el estudio con la lista y el editor de un borrador, y guarda cambios del editor', function (): void {
    Filament::setCurrentPanel('business');
    orgPagesCurrentVersion();

    Livewire::test(EstructuraOrganizacional::class)
        ->assertSuccessful()
        ->assertSee('Borradores en curso')
        ->assertSee('Organigrama QA');

    $draft = OrgStructureDrafts::createFromCurrent(auth()->user(), 'Borrador QA');
    $root = OrgPosition::query()->where('version_id', $draft->id)->whereNull('parent_id')->sole();

    $component = Livewire::withQueryParams(['borrador' => $draft->id])
        ->test(EstructuraOrganizacional::class)
        ->assertSuccessful()
        ->assertSee('Presidencia QA')
        ->assertSee('Publicar');

    $result = $component->instance()->addPosition(0, $root->id, 'Coordinación QA');

    expect($result['ok'])->toBeTrue()
        ->and($result['lock_version'])->toBe(1)
        ->and(collect($result['payload']['nodes'])->pluck('title')->all())->toContain('Coordinación QA');

    $stale = $component->instance()->addPosition(0, $root->id, 'Con lock viejo');

    expect($stale['ok'])->toBeFalse()
        ->and($stale['conflict'])->toBeTrue()
        ->and($stale['payload'])->not->toBeNull();

    $invalid = $component->instance()->addPosition(1, $root->id, 'X');

    expect($invalid['ok'])->toBeFalse()
        ->and($invalid['message'])->toContain('al menos 2 caracteres');
});

it('«Nuevo organigrama» crea un borrador vacío y abre el lienzo con un solo cuadro', function (): void {
    Filament::setCurrentPanel('business');
    $before = OrgStructureVersion::query()->count();

    Livewire::test(EstructuraOrganizacional::class)
        ->assertSuccessful()
        ->callAction('newOrgChart')
        ->assertRedirect();

    $draft = OrgStructureVersion::query()->latest('id')->firstOrFail();

    expect(OrgStructureVersion::query()->count())->toBe($before + 1)
        ->and($draft->name)->toBe('Organigrama '.now()->format('Y'))
        ->and(OrgPosition::query()->where('version_id', $draft->id)->count())->toBe(0);

    Livewire::withQueryParams(['borrador' => $draft->id])
        ->test(EstructuraOrganizacional::class)
        ->assertSuccessful()
        ->assertSee('Escribe el puesto principal…')
        ->assertDontSee('¿Cómo se llama el puesto que encabeza la empresa?');
});

it('abre el ejemplo del estudio sin crear nada en la base', function (): void {
    Filament::setCurrentPanel('business');
    $before = OrgStructureVersion::query()->count();

    Livewire::withQueryParams(['ejemplo' => 1])
        ->test(EstructuraOrganizacional::class)
        ->assertSuccessful()
        ->assertSee('Organigrama de ejemplo')
        ->assertSee('nada se guarda');

    expect(OrgStructureVersion::query()->count())->toBe($before);
});

it('el editor trae la paleta de RRHH y crea puestos desde un cargo', function (): void {
    Filament::setCurrentPanel('business');
    orgPagesCurrentVersion();
    $depto = \App\Models\RrhhDepartamento::query()->create(['description' => 'Paleta QA']);
    $cargo = \App\Models\RrhhCargo::query()->create(['departamento_id' => $depto->id, 'description' => 'Cargo Paleta QA', 'created_by' => 'pest']);
    $draft = OrgStructureDrafts::createFromCurrent(auth()->user(), 'Borrador Paleta QA');
    $root = OrgPosition::query()->where('version_id', $draft->id)->whereNull('parent_id')->sole();

    $component = Livewire::withQueryParams(['borrador' => $draft->id])->test(EstructuraOrganizacional::class);
    $config = $component->instance()->editorConfig();

    expect(collect($config['palette']['cargos'])->pluck('name')->all())->toContain('Cargo Paleta QA')
        ->and($config['publishAction'])->toBe('publish');

    $result = $component->instance()->addFromCargo(0, $root->id, $cargo->id);

    expect($result['ok'])->toBeTrue()
        ->and(collect($result['payload']['nodes'])->pluck('title')->all())->toContain('Cargo Paleta QA')
        ->and($component->instance()->positionHistory(OrgPosition::query()->findOrFail($result['position_id'])->position_key))->not->toBeEmpty();
});

it('niega el estudio a quien solo puede ver el organigrama', function (): void {
    $viewer = User::factory()->make([
        'id' => 999_998_102,
        'email' => 'qa.lector@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['ADMINISTRACION'],
    ]);
    $viewer->setRelation('permissions', new Collection);
    $this->actingAs($viewer);
    Filament::setCurrentPanel('business');
    OrgStructureCache::forgetCurrent();

    try {
        expect(EstructuraOrganizacional::canAccess())->toBeFalse()
            ->and(AdministrationOrganigrama::canAccess())->toBe(
                OrgStructureVersion::query()->current()->where('status', OrgStructureVersionStatus::Current)->exists()
            );
    } finally {
        OrgStructureCache::forgetCurrent();
    }
});

it('oculta el menú de consulta cuando no hay organigrama publicado y lo muestra en negocios', function (): void {
    Cache::put('org-structure:current-version', false, 600);

    try {
        expect(AdministrationOrganigrama::shouldRegisterNavigation())->toBeFalse()
            ->and(\App\Filament\Operations\Pages\Organigrama::shouldRegisterNavigation())->toBeFalse()
            ->and(\App\Filament\Marketing\Pages\Organigrama::shouldRegisterNavigation())->toBeFalse()
            ->and(\App\Filament\Projects\Pages\Organigrama::shouldRegisterNavigation())->toBeFalse()
            ->and(\App\Filament\Metrics\Pages\Organigrama::shouldRegisterNavigation())->toBeFalse()
            ->and(BusinessOrganigrama::shouldRegisterNavigation())->toBeTrue()
            ->and(file_exists(app_path('Filament/Telemedicina/Pages/Organigrama.php')))->toBeFalse()
            ->and(file_exists(app_path('Filament/Pages/Organigrama.php')))->toBeFalse();
    } finally {
        OrgStructureCache::forgetCurrent();
    }
});

it('en los paneles de consulta solo entrega el organigrama publicado', function (): void {
    Filament::setCurrentPanel('administration');
    $current = OrgStructureVersion::query()->current()->where('status', OrgStructureVersionStatus::Current)->first();

    if ($current === null) {
        $current = orgPagesCurrentVersion();
    }

    OrgStructureCache::forgetCurrent();
    $archived = OrgStructureVersion::factory()->create([
        'status' => OrgStructureVersionStatus::Archived,
        'name' => 'Archivada QA',
        'current_marker' => null,
    ]);

    try {
        $page = Livewire::test(AdministrationOrganigrama::class)->instance();
        $config = $page->chartConfig();

        expect($config['studioUrl'])->toBeNull()
            ->and($config['canExport'])->toBeFalse()
            ->and($config['canManageViews'])->toBeFalse()
            ->and($config['versions'])->toHaveCount(1)
            ->and($config['payload'])->not->toBeNull()
            ->and($page->loadVersion($archived->id)['ok'])->toBeFalse()
            ->and($page->loadVersion((int) $config['currentVersionId'])['ok'])->toBeTrue()
            ->and($page->requestPdfExport((int) $config['currentVersionId'], [])['ok'])->toBeFalse();
    } finally {
        OrgStructureCache::forgetCurrent();
    }
});
