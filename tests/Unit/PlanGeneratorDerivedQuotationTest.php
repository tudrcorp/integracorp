<?php

declare(strict_types=1);

use App\Filament\Business\Resources\PlanGenerators\Pages\ListPlanGenerators;
use App\Models\PlanGenerator;
use App\Models\User;
use App\Support\PlanGenerators\PlanGeneratorMatrixState;
use App\Support\PlanGenerators\PlanGeneratorTemplateCloner;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * El analista usa un «plan generado» como plantilla: selecciona un registro,
 * ajusta la matriz en la modal y obtiene otra cotización colgada del mismo
 * registro base. Estos tests montan la tabla de verdad, porque lo único que
 * prueba que el editor escribe sobre el estado de la acción montada es
 * dispararlo.
 *
 * Todo lo que escribe va dentro de una transacción que siempre se revierte.
 */
beforeEach(function (): void {
    DB::beginTransaction();

    Filament::setCurrentPanel('business');

    $analista = User::query()
        ->where('email', 'like', '%@tudrencasa.com')
        ->where('status', 'ACTIVO')
        ->get()
        ->first(fn (User $user): bool => in_array('NEGOCIOS', (array) ($user->departament ?? []), true)
            || in_array('SUPERADMIN', (array) ($user->departament ?? []), true));

    if ($analista === null) {
        $this->markTestSkipped('No hay un analista de Negocios activo en la base para montar el panel.');
    }

    $this->analista = $analista;

    $this->plantilla = PlanGenerator::query()
        ->withCount(['columns', 'rows', 'rateRows'])
        ->having('columns_count', '>=', 2)
        ->having('rows_count', '>=', 2)
        ->having('rate_rows_count', '>=', 1)
        ->orderByDesc('rows_count')
        ->first();

    if ($this->plantilla === null) {
        $this->markTestSkipped('No hay un plan generado con matriz suficiente para derivar.');
    }
});

afterEach(function (): void {
    DB::rollBack();
});

/**
 * Suma de población declarada en los rangos etarios del estado montado. El
 * validador exige que el total coincida con esa suma.
 *
 * @param  array<string, array<string, mixed>>  $rateRows
 */
function poblacionDeRangos(array $rateRows): int
{
    return collect($rateRows)->sum(fn (array $rateRow): int => max(0, (int) ($rateRow['population'] ?? 0)));
}

/**
 * Registro mínimo propio del test. Los planes reales de la base ya tienen
 * derivadas creadas a mano, así que un test que asuma «-1 está libre» o
 * «este base no tiene derivadas» se rompe con el uso normal del módulo.
 */
function planGeneradoDePrueba(string $nombre, ?int $parentId = null, ?string $controlNumber = null): PlanGenerator
{
    return PlanGenerator::query()->create([
        'name' => $nombre,
        'parent_id' => $parentId,
        'control_number' => $controlNumber ?? 'PEST-'.uniqid(),
        'client_data' => 'CLIENTE '.$nombre,
        'issued_at' => now(),
        'agent_name' => 'PEST',
        'population_summary' => '1',
        'status' => 'PRE-APROBADO',
    ]);
}

it('conserva las columnas sin encabezado para poder renombrarlas en el editor', function (): void {
    $columns = [
        ['column_key' => 'col-a', 'header_label' => 'IDEAL 5K'],
        ['column_key' => 'col-b', 'header_label' => ''],
        ['header_label' => 'SIN LLAVE'],
    ];

    // `normalizeColumns()` descarta la columna sin nombre; si el editor usara
    // esa lista, borrar el texto del encabezado haría desaparecer la columna.
    expect(PlanGeneratorMatrixState::normalizeColumns($columns))->toHaveCount(1)
        ->and(PlanGeneratorMatrixState::keyedColumns($columns))->toHaveCount(2)
        ->and(PlanGeneratorMatrixState::keyedColumns($columns)[1])
        ->toBe([
            'column_key' => 'col-b',
            'header_label' => '',
            // Porcentaje del ajuste global de tarifas; nulo si no hay ajuste.
            'rate_adjustment_percent' => null,
        ]);
});

it('sugiere el primer Nro. Control libre de la familia', function (): void {
    $base = planGeneradoDePrueba('BASE SUGERENCIA PEST');
    $sugerido = PlanGeneratorTemplateCloner::suggestControlNumber($base);

    expect($sugerido)->toBe($base->control_number.'-1');

    planGeneradoDePrueba('DERIVADA SUGERENCIA PEST', $base->getKey(), $sugerido);

    expect(PlanGeneratorTemplateCloner::suggestControlNumber($base))->toBe($base->control_number.'-2');
});

it('abre la modal precargada con la matriz de la plantilla', function (): void {
    $plantilla = $this->plantilla;

    $componente = Livewire::actingAs($this->analista)
        ->test(ListPlanGenerators::class)
        ->set('selectedTableRecords', [(string) $plantilla->getKey()])
        ->mountAction(TestAction::make('deriveQuotation')->table()->bulk())
        ->assertOk();

    $estado = $componente->get('mountedActions.0.data');

    // No se asume el sufijo: la plantilla puede tener derivadas previas.
    expect($estado['control_number'])->toBe(PlanGeneratorTemplateCloner::suggestControlNumber($plantilla->templateBase()))
        ->and($estado['client_data'])->toBe((string) $plantilla->client_data)
        ->and($estado['columns'])->toHaveCount((int) $plantilla->columns_count)
        ->and($estado['rows'])->toHaveCount((int) $plantilla->rows_count)
        ->and($estado['rate_rows'])->toHaveCount((int) $plantilla->rate_rows_count);

    // El editor de matrices se pinta dentro de la modal (el HTML de una modal
    // de Filament no sale en `html()`, hay que pedírselo a la acción montada) y
    // trae los controles de columnas y los bindings al estado de la acción.
    $componente
        ->assertMountedActionModalSee('Agregar columna')
        ->assertMountedActionModalSee('Beneficios del Plan')
        ->assertMountedActionModalSeeHtml('mountedActions.0.data.rows.');
});

it('no abre la modal con más de un registro seleccionado', function (): void {
    $otro = PlanGenerator::query()
        ->whereKeyNot($this->plantilla->getKey())
        ->first();

    if ($otro === null) {
        $this->markTestSkipped('Hace falta un segundo plan generado para probar la selección múltiple.');
    }

    Livewire::actingAs($this->analista)
        ->test(ListPlanGenerators::class)
        ->set('selectedTableRecords', [
            (string) $this->plantilla->getKey(),
            (string) $otro->getKey(),
        ])
        ->mountAction(TestAction::make('deriveQuotation')->table()->bulk())
        ->assertNotified('Seleccione un solo registro')
        ->assertActionNotMounted();
});

it('crea la cotización derivada con la matriz ajustada y la cuelga del registro base', function (): void {
    Storage::fake('public');

    $plantilla = $this->plantilla;
    $columnasPrevias = (int) $plantilla->columns_count;
    $beneficiosPrevios = (int) $plantilla->rows_count;

    $componente = Livewire::actingAs($this->analista)
        ->test(ListPlanGenerators::class)
        ->set('selectedTableRecords', [(string) $plantilla->getKey()])
        ->mountAction(TestAction::make('deriveQuotation')->table()->bulk());

    $estado = $componente->get('mountedActions.0.data');

    $columnaAQuitar = (string) $estado['columns'][0]['column_key'];
    $beneficioAQuitar = (string) array_key_first($estado['rows']);

    $componente
        ->call('removeMatrixColumn', $columnaAQuitar, 'mountedActions.0.data')
        ->call('removeMatrixRow', $beneficioAQuitar, 'mountedActions.0.data')
        ->set('mountedActions.0.data.client_data', 'CLIENTE DERIVADO PEST')
        ->set('mountedActions.0.data.name', 'PLAN DERIVADO PEST')
        ->set('mountedActions.0.data.population_summary', (string) poblacionDeRangos($estado['rate_rows']))
        ->callMountedAction()
        ->assertHasNoErrors()
        ->assertNotified('Cotización creada');

    $derivada = PlanGenerator::query()
        ->where('client_data', 'CLIENTE DERIVADO PEST')
        ->withCount(['columns', 'rows', 'rateRows', 'quotationPages'])
        ->firstOrFail();

    expect($derivada->parent_id)->toBe($plantilla->getKey())
        ->and($derivada->control_number)->toBe($estado['control_number'])
        ->and($derivada->name)->toBe('PLAN DERIVADO PEST')
        // La columna y el beneficio quitados en la modal no se guardan.
        ->and($derivada->columns_count)->toBe($columnasPrevias - 1)
        ->and($derivada->rows_count)->toBe($beneficiosPrevios - 1)
        // El cuerpo del PDF se hereda de la plantilla.
        ->and($derivada->quotation_page_count)->toBe($plantilla->quotation_page_count)
        ->and($derivada->plan_page_number)->toBe($plantilla->plan_page_number);

    // Cada celda guardada apunta a una columna que sigue existiendo.
    $columnasVivas = $derivada->columns()->pluck('id')->all();

    foreach ($derivada->rows as $fila) {
        expect($fila->cells->pluck('plan_generator_column_id')->all())
            ->each->toBeIn($columnasVivas);
    }

    // La plantilla no se toca: sigue con su matriz completa.
    expect($plantilla->fresh()->columns()->count())->toBe($columnasPrevias)
        ->and($plantilla->fresh()->rows()->count())->toBe($beneficiosPrevios);
});

it('no guarda la cotización si la matriz quedó incompleta', function (): void {
    $plantilla = $this->plantilla;
    $antes = PlanGenerator::query()->count();

    $componente = Livewire::actingAs($this->analista)
        ->test(ListPlanGenerators::class)
        ->set('selectedTableRecords', [(string) $plantilla->getKey()])
        ->mountAction(TestAction::make('deriveQuotation')->table()->bulk());

    $estado = $componente->get('mountedActions.0.data');

    // El analista borra el nombre de una columna: `normalizeColumns()` la
    // descartaría al guardar y la cotización saldría con una columna menos sin
    // que nadie se enterara.
    $componente
        ->set('mountedActions.0.data.columns.0.header_label', '')
        ->set('mountedActions.0.data.client_data', 'CLIENTE INCOMPLETO PEST')
        ->set('mountedActions.0.data.population_summary', (string) poblacionDeRangos($estado['rate_rows']))
        ->callMountedAction()
        ->assertNotified('La matriz está incompleta');

    expect(PlanGenerator::query()->count())->toBe($antes)
        ->and(PlanGenerator::query()->where('client_data', 'CLIENTE INCOMPLETO PEST')->exists())->toBeFalse();
});

it('el borrado masivo libera a las derivadas en vez de dejarlas huérfanas', function (): void {
    $tabla = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/PlanGenerators/Tables/PlanGeneratorsTable.php');

    // Sin `fetchSelectedRecords()` Filament puede resolver el borrado masivo
    // con un `DELETE` de query, que no dispara eventos de modelo.
    expect($tabla)->toContain('->fetchSelectedRecords()');

    $base = PlanGenerator::query()->create([
        'name' => 'BASE MASIVO PEST',
        'control_number' => 'PEST-MASIVO-'.uniqid(),
        'client_data' => 'CLIENTE BASE MASIVO',
        'issued_at' => now(),
        'agent_name' => 'PEST',
        'population_summary' => '1',
        'status' => 'PRE-APROBADO',
    ]);

    $derivada = PlanGenerator::query()->create([
        'name' => 'DERIVADA MASIVO PEST',
        'parent_id' => $base->getKey(),
        'control_number' => $base->control_number.'-1',
        'client_data' => 'CLIENTE DERIVADO MASIVO',
        'issued_at' => now(),
        'agent_name' => 'PEST',
        'population_summary' => '1',
        'status' => 'PRE-APROBADO',
    ]);

    Livewire::actingAs($this->analista)
        ->test(ListPlanGenerators::class)
        ->set('selectedTableRecords', [(string) $base->getKey()])
        ->callAction(TestAction::make('delete')->table()->bulk());

    expect(PlanGenerator::query()->whereKey($base->getKey())->exists())->toBeFalse()
        ->and($derivada->fresh())->not->toBeNull()
        ->and($derivada->fresh()->parent_id)->toBeNull();
});

it('derivar de una derivada la cuelga del mismo registro base', function (): void {
    $base = $this->plantilla;

    $derivada = PlanGenerator::query()->create([
        'name' => 'DERIVADA INTERMEDIA',
        'parent_id' => $base->getKey(),
        'control_number' => $base->control_number.'-9',
        'client_data' => 'CLIENTE INTERMEDIO',
        'issued_at' => now(),
        'agent_name' => 'PEST',
        'population_summary' => '1',
        'status' => 'PRE-APROBADO',
    ]);

    expect(PlanGeneratorTemplateCloner::baseFor($derivada)->getKey())->toBe($base->getKey());
});

it('borrar el registro base deja a sus derivadas como registros base independientes', function (): void {
    $base = PlanGenerator::query()->create([
        'name' => 'BASE A BORRAR',
        'control_number' => 'PEST-BASE-'.uniqid(),
        'client_data' => 'CLIENTE BASE',
        'issued_at' => now(),
        'agent_name' => 'PEST',
        'population_summary' => '1',
        'status' => 'PRE-APROBADO',
    ]);

    $derivada = PlanGenerator::query()->create([
        'name' => 'DERIVADA HUÉRFANA',
        'parent_id' => $base->getKey(),
        'control_number' => $base->control_number.'-1',
        'client_data' => 'CLIENTE DERIVADO',
        'issued_at' => now(),
        'agent_name' => 'PEST',
        'population_summary' => '1',
        'status' => 'PRE-APROBADO',
    ]);

    $base->delete();

    expect($derivada->fresh())->not->toBeNull()
        ->and($derivada->fresh()->parent_id)->toBeNull();
});

it('la tabla muestra el registro base con sus derivadas debajo, agrupadas y en orden', function (): void {
    // Familia propia: los planes reales ya tienen derivadas creadas a mano y el
    // conteo de la cabecera dependería de ellas.
    $base = planGeneradoDePrueba('BASE AGRUPADO PEST');
    planGeneradoDePrueba('DERIVADA AGRUPADA PEST', $base->getKey(), $base->control_number.'-1');

    $consultas = 0;
    Event::listen(\Illuminate\Database\Events\QueryExecuted::class, function () use (&$consultas): void {
        $consultas++;
    });

    $html = Livewire::actingAs($this->analista)
        ->test(ListPlanGenerators::class)
        ->assertOk()
        ->assertSee('DERIVADA AGRUPADA PEST')
        // Cabecera de familia: «# control · nombre» y el conteo de derivadas.
        ->assertSeeHtml('fi-ta-group-header')
        ->assertSee('# '.$base->control_number.' · '.$base->name)
        ->assertSee('Registro base + 1 cotización derivada')
        ->assertSee('CLIENTE BASE AGRUPADO PEST')
        // La columna «Origen» distingue el base de sus derivadas.
        ->assertSee('↳ Derivada')
        // Y el registro base va resaltado para no confundirse al tildar.
        ->assertSeeHtml('pg-family-base-row')
        ->assertSeeHtml('pg-family-derived-row')
        ->html();

    // El registro base va primero dentro de su familia; las derivadas debajo.
    expect(strpos($html, 'BASE AGRUPADO PEST'))
        ->toBeLessThan(strpos($html, 'DERIVADA AGRUPADA PEST'));

    // El eager load de `parent` evita que cada derivada consulte a su base para
    // armar el título y el conteo de la cabecera.
    expect($consultas)->toBeLessThan(40);
});

it('la cabecera de familia no ofrece check de selección', function (): void {
    $theme = file_get_contents(dirname(__DIR__, 2).'/resources/css/filament/admin/theme.css');

    // Filament pinta la casilla de la cabecera de grupo siempre que la tabla
    // tenga selección, y la única palanca nativa es `maxSelectableRecords(1)`,
    // que rompería el borrado masivo. Se oculta por CSS y se conserva la celda
    // para no desalinear las columnas.
    expect($theme)
        ->toContain('.fi-resource-plan-generators .fi-ta-group-checkbox')
        ->toContain('.fi-resource-plan-generators .fi-ta-row.pg-family-base-row');
});

it('la tabla agrupa por familia y ofrece la acción de derivar', function (): void {
    $tabla = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/PlanGenerators/Tables/PlanGeneratorsTable.php');
    $accion = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/PlanGenerators/Tables/Actions/DeriveQuotationBulkAction.php');
    $recurso = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/PlanGenerators/PlanGeneratorResource.php');
    $editor = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/business/plan-generators/stacked-matrices-editor.blade.php');

    expect($tabla)
        ->toContain('DeriveQuotationBulkAction::make()')
        ->toContain("Group::make('family')")
        ->toContain('COALESCE(plan_generators.parent_id, plan_generators.id)')
        ->toContain('->collapsible()')
        ->toContain('->defaultGroup(self::familyGroup())')
        // Las familias arrancan cerradas.
        ->toContain('->collapsedGroupsByDefault()')
        // El base se resalta y las derivadas se indentan; el estilo y el
        // ocultado del check de la cabecera del grupo viven en el theme.css
        // del panel, porque Filament no expone esa casilla como opción.
        ->toContain("'pg-family-derived-row'")
        ->toContain("'pg-family-base-row'")
        ->toContain("TextColumn::make('origin')")
        ->toContain("TernaryFilter::make('derived')");

    expect($accion)
        ->toContain("BulkAction::make('deriveQuotation')")
        ->toContain('Generar cotización desde plantilla')
        ->toContain('$action->halt()')
        ->toContain("Hidden::make('columns')")
        ->toContain("Hidden::make('rows')")
        ->toContain("Hidden::make('rate_rows')")
        ->toContain("'manageColumns' => true")
        ->toContain('stacked-matrices-editor')
        ->toContain('PlanGeneratorPopulationValidator::validationMessage');

    // La agrupación resuelve el título desde el registro base: sin eager load
    // cada fila derivada consultaría a su padre por separado.
    expect($recurso)
        ->toContain("->with(['parent' => fn (BelongsTo \$query): BelongsTo => \$query->withCount('derivedQuotations')])")
        ->toContain("'derivedQuotations'");

    // El mismo editor sirve al formulario y a la modal.
    expect($editor)
        ->toContain('$matrixStatePath = (isset($getStatePath) && $getStatePath instanceof \\Closure)')
        ->toContain('{{ $matrixStatePath }}.rows.')
        ->toContain('{{ $matrixStatePath }}.rate_rows.')
        ->toContain("addMatrixColumn('{{ \$matrixStatePath }}')")
        // `$statePath` no se puede usar como variable de la vista: Filament
        // inyecta un closure por cada método público del componente y
        // `statePath()` es uno de ellos.
        ->not->toContain('wire:model.live="{{ $statePath }}');
});
