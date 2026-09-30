<?php

declare(strict_types=1);

use App\Models\TelemedicineCase;
use App\Support\Telemedicine\TelemedicineCaseDerivedService;
use Illuminate\Support\Carbon;

/**
 * Columna «Derivado por atender» del escritorio del médico: el servicio
 * derivado que indicó la última consulta del caso. Solo arma SQL y describe
 * valores: no escribe en la base.
 */
uses(Tests\TestCase::class);

$basePath = dirname(__DIR__, 2);

it('un caso sin consultas queda pendiente de primera consulta', function (): void {
    expect(TelemedicineCaseDerivedService::describe(null, null, true))
        ->toBe(['label' => 'Pendiente de primera consulta', 'detail' => null, 'tone' => TelemedicineCaseDerivedService::TONE_PENDING]);
});

it('muestra el derivado de la última consulta con su fecha relativa', function (): void {
    $now = Carbon::parse('2026-09-29 12:00');

    expect(TelemedicineCaseDerivedService::describe('SEGUIMIENTO MÉDICO/LECTURA DE RESULTADOS', '2026-09-27 10:00:00', false, $now))
        ->toBe([
            'label' => 'SEGUIMIENTO MÉDICO/LECTURA DE RESULTADOS',
            'detail' => 'Consulta del 27/09/2026 · hace 2 días',
            'tone' => TelemedicineCaseDerivedService::TONE_DERIVED,
        ]);
});

it('marca como crítico el traslado en ambulancia y el ingreso a clínica', function (string $name): void {
    expect(TelemedicineCaseDerivedService::describe($name, '2026-09-27 10:00:00', false)['tone'])
        ->toBe(TelemedicineCaseDerivedService::TONE_CRITICAL);
})->with(['TRASLADO EN AMBULANCIA', 'Ingreso a Clínica']);

it('con consultas pero sin derivado lo dice en vez de dejar la celda vacía', function (): void {
    $described = TelemedicineCaseDerivedService::describe('  ', '2026-09-27 10:00:00', false, Carbon::parse('2026-09-28 10:00'));

    expect($described['label'])->toBe('Sin derivado indicado')
        ->and($described['tone'])->toBe(TelemedicineCaseDerivedService::TONE_NONE)
        ->and($described['detail'])->toStartWith('Consulta del 27/09/2026');
});

it('toma el derivado de la última consulta del caso, en una subconsulta', function (): void {
    $sql = TelemedicineCaseDerivedService::withDerivedServiceColumns(TelemedicineCase::query())->toRawSql();

    expect($sql)
        ->toContain('dsl.id = dlc.telemedicine_service_list_drift_id')
        ->toContain('select max(dmx.id) from telemedicine_consultation_patients dmx where dmx.telemedicine_case_id = telemedicine_cases.id')
        ->toContain('as latest_drift_name')
        ->toContain('as latest_consultation_at')
        ->toContain('`telemedicine_cases`.*');
});

it('filtra por nombre de derivado y por casos sin derivado', function (): void {
    $byName = TelemedicineCaseDerivedService::whereDerivedService(TelemedicineCase::query(), 'TRASLADO EN AMBULANCIA')->toRawSql();
    $none = TelemedicineCaseDerivedService::whereDerivedService(TelemedicineCase::query(), TelemedicineCaseDerivedService::FILTER_NONE)->toRawSql();
    $all = TelemedicineCaseDerivedService::whereDerivedService(TelemedicineCase::query(), null)->toRawSql();

    expect($byName)->toContain("= 'TRASLADO EN AMBULANCIA'")
        ->and($none)->toContain('exists (select 1 from telemedicine_consultation_patients fcx')
        ->and($none)->toContain(') is null')
        ->and($all)->not->toContain('latest')
        ->and($all)->not->toContain('dsl.name');
});

it('el filtro ofrece la opción «Sin derivado indicado»', function (): void {
    expect(TelemedicineCaseDerivedService::filterOptions())
        ->toHaveKey(TelemedicineCaseDerivedService::FILTER_NONE, 'Sin derivado indicado');
});

it('la tabla del escritorio muestra la columna, el filtro y carga los derivados en la consulta', function () use ($basePath): void {
    $widget = file_get_contents($basePath.'/app/Filament/Telemedicina/Widgets/TelemedicineCaseTableDash.php');

    expect($widget)
        ->toContain("TextColumn::make('latest_drift_name')")
        ->toContain("->label('Derivado por atender')")
        ->toContain('TelemedicineCaseDerivedService::withDerivedServiceColumns(TelemedicineCaseFollowUpSchedule::withFollowUpColumns(')
        ->toContain("SelectFilter::make('derived_service')")
        ->toContain('TelemedicineCaseDerivedService::TONE_CRITICAL => \'danger\'');
});

it('la insignia del derivado se parte en líneas en vez de cortarse con puntos suspensivos', function () use ($basePath): void {
    $widget = file_get_contents($basePath.'/app/Filament/Telemedicina/Widgets/TelemedicineCaseTableDash.php');
    $theme = file_get_contents($basePath.'/resources/css/filament/admin/theme.css');

    expect($widget)->toContain("->extraAttributes(['class' => 'telemedicine-derived-service-cell'])");

    preg_match('/\.telemedicine-derived-service-cell \.fi-badge \{(.*?)\}/s', $theme, $badge);

    expect($badge[1] ?? '')
        ->toContain('white-space: normal')
        ->toContain('overflow: visible')
        ->toContain('text-overflow: clip');
});

it('el menú de acciones (⋮) va a la izquierda de la tabla y se despliega hacia la derecha', function () use ($basePath): void {
    $widget = file_get_contents($basePath.'/app/Filament/Telemedicina/Widgets/TelemedicineCaseTableDash.php');

    expect($widget)
        ->toContain('], position: RecordActionsPosition::BeforeColumns)')
        ->toContain("->dropdownPlacement('bottom-start')")
        ->toContain('use Filament\Tables\Enums\RecordActionsPosition;');
});
