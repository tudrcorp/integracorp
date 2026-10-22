<?php

declare(strict_types=1);

use App\Enums\TdgCalendarGuardShift;
use App\Enums\TdgCalendarOffice;
use App\Http\Controllers\TdgCalendarSchedulePdfController;
use App\Models\Permission;
use App\Models\RrhhColaborador;
use App\Models\TdgCalendarDay;
use App\Models\TdgCalendarGuardAssignment;
use App\Models\TdgCalendarOfficeAssignment;
use App\Models\User;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\TdgCalendar\TdgCalendarSchedulePdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(Tests\TestCase::class);

beforeEach(fn () => DB::beginTransaction());
afterEach(fn () => DB::rollBack());

function schedulePdfUser(string $name, array $departments, array $permissions = [], int $id = 987650001): User
{
    $user = new User([
        'name' => $name,
        'email' => 'guardia-pdf-'.$id.'@tudrencasa.com',
        'departament' => $departments,
    ]);
    $user->id = $id;

    $loaded = new Collection;

    foreach ($permissions as $permission) {
        $loaded->push(new Permission([
            'slug' => $permission['slug'],
            'module' => $permission['module'],
        ]));
    }

    $user->setRelation('permissions', $loaded);

    return $user;
}

it('deja bajar a todo el equipo de oficinas a superadmin y a quien tiene el permiso de edición', function (): void {
    $superadmin = schedulePdfUser('Super', ['SUPERADMIN', 'NEGOCIOS'], id: 987650011);
    $editor = schedulePdfUser('Editor', ['NEGOCIOS'], [
        ['slug' => BusinessFilamentActionPermissionRegistry::EDIT_OFFICE_GUARDS, 'module' => 'NEGOCIOS'],
    ], 987650012);
    $viewer = schedulePdfUser('Lector', ['NEGOCIOS'], [
        ['slug' => 'calendarios-tdg', 'module' => 'NEGOCIOS'],
    ], 987650013);

    expect(TdgCalendarSchedulePdf::canDownloadEveryone($superadmin, TdgCalendarSchedulePdf::OFFICES))->toBeTrue()
        ->and(TdgCalendarSchedulePdf::canDownloadEveryone($editor, TdgCalendarSchedulePdf::OFFICES))->toBeTrue()
        ->and(TdgCalendarSchedulePdf::canDownloadEveryone($viewer, TdgCalendarSchedulePdf::OFFICES))->toBeFalse()
        ->and(TdgCalendarSchedulePdf::canDownloadEveryone($editor, TdgCalendarSchedulePdf::OPERATIONS))->toBeFalse()
        ->and(TdgCalendarSchedulePdf::canView($viewer, TdgCalendarSchedulePdf::OFFICES))->toBeTrue()
        ->and(TdgCalendarSchedulePdf::canDownload($viewer, TdgCalendarSchedulePdf::OFFICES))->toBeFalse();
});

it('deja bajar todas las guardias de operaciones a becky y a un superadministrador', function (): void {
    $becky = schedulePdfUser('Becky Acosta', ['OPERACIONES'], [
        ['slug' => 'guardias-operaciones', 'module' => 'OPERACIONES'],
    ], 987650021);
    $otro = schedulePdfUser('Otra persona', ['OPERACIONES'], [
        ['slug' => 'guardias-operaciones', 'module' => 'OPERACIONES'],
    ], 987650022);

    expect(TdgCalendarSchedulePdf::canDownloadEveryone($becky, TdgCalendarSchedulePdf::OPERATIONS))->toBeTrue()
        ->and(TdgCalendarSchedulePdf::canDownloadEveryone($otro, TdgCalendarSchedulePdf::OPERATIONS))->toBeFalse()
        ->and(TdgCalendarSchedulePdf::canView($otro, TdgCalendarSchedulePdf::OPERATIONS))->toBeTrue();
});

it('obliga a quien solo consulta a descargar su propia ficha', function (): void {
    $viewer = schedulePdfUser('Lector propio', ['ADMINISTRACION'], [
        ['slug' => 'calendarios-tdg', 'module' => 'ADMINISTRACION'],
    ], 987650031);
    $own = RrhhColaborador::query()->create([
        'fullName' => 'Lector Propio PDF',
        'status' => 'activo',
        'created_by' => 'pest',
        'user_id' => $viewer->id,
    ]);
    $other = RrhhColaborador::query()->create([
        'fullName' => 'Otra Persona PDF',
        'status' => 'activo',
        'created_by' => 'pest',
    ]);

    expect(TdgCalendarSchedulePdf::resolvedColaboradorId($viewer, TdgCalendarSchedulePdf::OFFICES, (int) $other->id))->toBe((int) $own->id)
        ->and(TdgCalendarSchedulePdf::canDownload($viewer, TdgCalendarSchedulePdf::OFFICES))->toBeTrue();
});

it('arma el almanaque del mes y el detalle de una sola persona', function (): void {
    $month = Carbon::parse('2099-03-01');
    $ana = RrhhColaborador::query()->create(['fullName' => 'Ana Almanaque PDF', 'status' => 'activo', 'created_by' => 'pest']);
    $luis = RrhhColaborador::query()->create(['fullName' => 'Luis Almanaque PDF', 'status' => 'activo', 'created_by' => 'pest']);
    $monday = TdgCalendarDay::query()->firstOrCreate(['calendar_date' => '2099-03-02']);
    $tuesday = TdgCalendarDay::query()->firstOrCreate(['calendar_date' => '2099-03-03']);

    TdgCalendarOfficeAssignment::query()->create([
        'tdg_calendar_day_id' => $monday->id,
        'office' => TdgCalendarOffice::CentralLido,
        'rrhh_colaborador_id' => $ana->id,
    ]);
    TdgCalendarOfficeAssignment::query()->create([
        'tdg_calendar_day_id' => $monday->id,
        'office' => TdgCalendarOffice::HomeOffice,
        'rrhh_colaborador_id' => $luis->id,
    ]);
    TdgCalendarGuardAssignment::query()->create([
        'tdg_calendar_day_id' => $tuesday->id,
        'guard_shift' => TdgCalendarGuardShift::Nocturna,
        'rrhh_colaborador_id' => $ana->id,
    ]);

    $everyone = TdgCalendarSchedulePdf::document(TdgCalendarSchedulePdf::OFFICES, $month, null);
    $onlyAna = TdgCalendarSchedulePdf::document(TdgCalendarSchedulePdf::OFFICES, $month, (int) $ana->id);
    $operations = TdgCalendarSchedulePdf::document(TdgCalendarSchedulePdf::OPERATIONS, $month, (int) $ana->id);

    expect($everyone['title'])->toBe('Guardia Oficinas')
        ->and($everyone['scope_label'])->toBe('Todos los colaboradores')
        ->and(collect($everyone['people'])->pluck('name')->all())->toBe(['Ana Almanaque PDF', 'Luis Almanaque PDF'])
        ->and($everyone['weeks'])->not->toBeEmpty()
        ->and($onlyAna['scope_label'])->toBe('Ana Almanaque PDF')
        ->and(collect($onlyAna['people'])->pluck('name')->all())->toBe(['Ana Almanaque PDF'])
        ->and($onlyAna['people'][0]['days'][0]['places'])->toBe('Centro Lido')
        ->and($operations['title'])->toBe('Guardias Operaciones')
        ->and($operations['people'][0]['days'][0]['places'])->toContain('Nocturna');

    $flatLines = collect($onlyAna['weeks'])->flatten(1)->flatMap(fn (array $day): array => $day['lines'])->implode(' ');

    expect($flatLines)->toContain('Ana Almanaque PDF')
        ->and($flatLines)->not->toContain('Luis Almanaque PDF');

    $html = view('documents.tdg-calendar-schedule', [
        'document' => $onlyAna,
        'generatedAt' => '22/10/2026 10:00',
    ])->render();

    expect($html)->toContain('Guardia Oficinas')
        ->toContain('Ana Almanaque PDF')
        ->toContain('Centro Lido')
        ->not->toContain('Luis Almanaque PDF');

    $binary = TdgCalendarSchedulePdf::binary($onlyAna);

    expect($binary)->toStartWith('%PDF')
        ->and(TdgCalendarSchedulePdf::filename(TdgCalendarSchedulePdf::OFFICES, $month, 'Ana Almanaque PDF'))
        ->toBe('guardia-oficinas-2099-03-ana-almanaque-pdf.pdf');
});

it('rechaza la descarga a quien no puede ver la guardia', function (): void {
    $stranger = schedulePdfUser('Ajeno', ['NEGOCIOS'], id: 987650041);
    $request = Request::create('/guardias/cronograma/pdf?tipo=oficinas&mes=2099-03', 'GET');
    $request->setUserResolver(fn (): User => $stranger);

    expect(fn () => app(TdgCalendarSchedulePdfController::class)($request))
        ->toThrow(HttpException::class);
});

it('muestra la descarga en el calendario de oficinas y en el de operaciones', function (): void {
    $shell = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/business/pages/partials/corporate-calendar-shell.blade.php');
    $partial = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/business/pages/partials/tdg-calendar-schedule-pdf.blade.php');
    $trait = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Pages/Concerns/InteractsWithTdgHybridCalendar.php');

    expect($shell)->toContain('tdg-calendar-schedule-pdf')
        ->and($partial)->toContain('Descargar todos')
        ->and($partial)->toContain('Descargar esta persona')
        ->and($partial)->toContain('Descargar mi cronograma')
        ->and($trait)->toContain('function showsTdgSchedulePdfAction')
        ->and($trait)->toContain('function tdgSchedulePdfKind')
        ->and(route('tdg-calendar.schedule-pdf', ['tipo' => 'operaciones', 'mes' => '2099-03']))
        ->toContain('/guardias/cronograma/pdf');
});
