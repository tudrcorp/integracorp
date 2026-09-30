<?php

declare(strict_types=1);

use App\Filament\Telemedicina\Resources\TelemedicineCases\TelemedicineCaseResource;
use App\Filament\Telemedicina\Resources\TelemedicineDoctors\TelemedicineDoctorResource;
use App\Filament\Telemedicina\Resources\TelemedicinePatients\TelemedicinePatientResource;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicineDoctor;
use App\Models\TelemedicinePatient;
use App\Providers\Filament\TelemedicinaPanelProvider;
use App\Support\Telemedicine\TelemedicineCaseGlobalSearch;

/**
 * Buscador global del panel de telemedicina: casos por número, cédula, nombre
 * del paciente o diagnóstico, dentro del alcance del médico. Solo lee: arma
 * consultas sin ejecutarlas y modelos en memoria.
 */
uses(Tests\TestCase::class);

$basePath = dirname(__DIR__, 2);

it('parte el término en palabras, sin espacios sobrantes ni repetidas', function (): void {
    expect(TelemedicineCaseGlobalSearch::words('  quintero   genesis  quintero '))
        ->toBe(['quintero', 'genesis']);
});

it('no busca con menos de dos caracteres', function (string $term): void {
    expect(TelemedicineCaseGlobalSearch::words($term))->toBe([]);
})->with(['vacío' => '', 'espacios' => '   ', 'una letra' => 'a']);

it('limita la cantidad de palabras y el largo del término', function (): void {
    expect(TelemedicineCaseGlobalSearch::words('uno dos tres cuatro cinco seis siete ocho'))
        ->toHaveCount(TelemedicineCaseGlobalSearch::MAX_WORDS)
        ->and(mb_strlen(TelemedicineCaseGlobalSearch::normalizeTerm(str_repeat('x', 500))))
        ->toBe(TelemedicineCaseGlobalSearch::MAX_TERM_LENGTH);
});

it('una cédula escrita con espacios es una sola palabra', function (): void {
    expect(TelemedicineCaseGlobalSearch::words('V 22.171.244'))->toBe(['V22171244']);
});

it('extrae los dígitos de una cédula con prefijo, puntos o guiones', function (string $input): void {
    expect(TelemedicineCaseGlobalSearch::documentDigits($input))->toBe('22171244');
})->with([
    'solo dígitos' => '22171244',
    'con prefijo y guion' => 'V-22171244',
    'con puntos' => 'V-22.171.244',
    'minúscula' => 'v22171244',
]);

it('no trata como cédula un nombre ni un diagnóstico', function (string $input): void {
    expect(TelemedicineCaseGlobalSearch::documentDigits($input))->toBeNull();
})->with(['nombre' => 'genesis', 'diagnóstico' => 'dispepsico', 'número corto' => '0741']);

it('cada palabra se exige por separado y busca en código, nombre, cédula y diagnóstico', function (): void {
    $sql = TelemedicineCaseGlobalSearch::constrain(TelemedicineCase::query(), 'genesis dispepsico')->toRawSql();

    expect($sql)
        ->toContain("`telemedicine_cases`.`code` like '%genesis%'")
        ->toContain("`telemedicine_cases`.`patient_name` like '%genesis%'")
        ->toContain("`full_name` like '%genesis%'")
        ->toContain("`nro_identificacion` like '%genesis%'")
        ->toContain("`diagnostic_impression` like '%genesis%'")
        ->toContain("`diagnostic_impression` like '%dispepsico%'");
});

it('busca la cédula por sus dígitos aunque se escriba con prefijo y puntos', function (): void {
    $sql = TelemedicineCaseGlobalSearch::constrain(TelemedicineCase::query(), 'V-22.171.244')->toRawSql();

    expect($sql)->toContain("`nro_identificacion` like '%22171244%'");
});

it('escapa los comodines de LIKE que escriba el médico', function (): void {
    $sql = TelemedicineCaseGlobalSearch::constrain(TelemedicineCase::query(), '50%_x')->toRawSql();

    expect($sql)->toContain('50\\\\%\\\\_x')
        ->not->toContain("like '%50%_x%'");
});

it('un término vacío no devuelve nada', function (): void {
    expect(TelemedicineCaseGlobalSearch::constrain(TelemedicineCase::query(), ' ')->toRawSql())
        ->toContain('0 = 1')
        ->and(TelemedicineCaseGlobalSearch::results(''))->toBeEmpty();
});

it('ordena primero el código exacto, luego la cédula exacta y deja las altas al final', function (): void {
    $sql = TelemedicineCaseGlobalSearch::constrain(TelemedicineCase::query(), '55227-0741')->toRawSql();

    expect($sql)
        ->toContain("WHEN telemedicine_cases.code = '55227-0741' THEN 0")
        ->toContain('search_patient.nro_identificacion IN')
        ->toContain("WHEN telemedicine_cases.code LIKE '55227-0741%' THEN 2")
        ->toContain("WHEN telemedicine_cases.status = 'ALTA MEDICA' THEN 1");
});

it('arma el título con el código y el nombre del paciente', function (): void {
    $case = new TelemedicineCase(['code' => '55227-0741', 'patient_name' => 'NOMBRE VIEJO']);
    $case->setRelation('telemedicinePatient', new TelemedicinePatient(['full_name' => 'GENESIS  QUINTERO']));

    expect(TelemedicineCaseGlobalSearch::title($case))->toBe('55227-0741 · GENESIS QUINTERO');

    $withoutPatient = new TelemedicineCase(['code' => '55227-0741', 'patient_name' => 'NOMBRE DEL CASO']);
    $withoutPatient->setRelation('telemedicinePatient', null);

    expect(TelemedicineCaseGlobalSearch::title($withoutPatient))->toBe('55227-0741 · NOMBRE DEL CASO');
});

it('muestra cédula, estado, diagnóstico recortado y fecha de la última consulta', function (): void {
    $case = new TelemedicineCase(['code' => 'X', 'status' => 'ALTA MEDICA', 'telemedicine_doctor_id' => 5]);
    $case->setRelation('telemedicinePatient', new TelemedicinePatient(['nro_identificacion' => '22171244']));
    $case->setRelation('telemedicineDoctor', new TelemedicineDoctor(['full_name' => 'DRA. PRUEBA']));
    $case->setRelation('consultations', collect([
        new TelemedicineConsultationPatient(['diagnostic_impression' => "1. SINDROME\nDISPEPSICO ".str_repeat('LARGO ', 30)]),
    ]));
    $case->setAttribute('consultations_max_created_at', '2026-09-27 10:15:00');

    $details = TelemedicineCaseGlobalSearch::details($case);

    expect($details['Cédula'])->toBe('22171244')
        ->and($details['Estado'])->toBe('Alta médica')
        ->and($details['Diagnóstico'])->toStartWith('1. SINDROME DISPEPSICO')
        ->and(mb_strlen($details['Diagnóstico']))->toBeLessThanOrEqual(TelemedicineCaseGlobalSearch::DIAGNOSIS_PREVIEW_LENGTH + 3)
        ->and($details['Última consulta'])->toBe('27/09/2026')
        ->and($details)->not->toHaveKey('Médico');
});

it('muestra el médico solo al pool TDG y solo si el caso es de otro colega', function (): void {
    $case = new TelemedicineCase(['status' => 'EN SEGUIMIENTO', 'telemedicine_doctor_id' => 5]);
    $case->setRelation('telemedicinePatient', null);
    $case->setRelation('telemedicineDoctor', new TelemedicineDoctor(['full_name' => 'DRA. PRUEBA']));
    $case->setRelation('consultations', collect());

    expect(TelemedicineCaseGlobalSearch::details($case, true, 9))->toHaveKey('Médico', 'DRA. PRUEBA')
        ->and(TelemedicineCaseGlobalSearch::details($case, true, 5))->not->toHaveKey('Médico')
        ->and(TelemedicineCaseGlobalSearch::details($case, true, 9))->not->toHaveKeys(['Cédula', 'Diagnóstico', 'Última consulta']);
});

it('traduce los estados del caso', function (string $status, string $label): void {
    expect(TelemedicineCaseGlobalSearch::statusLabel($status))->toBe($label);
})->with([
    ['ALTA MEDICA', 'Alta médica'],
    ['EN SEGUIMIENTO', 'En seguimiento'],
    ['ASIGNADO', 'Asignado'],
    ['', 'Sin estado'],
    ['OTRO ESTADO', 'Otro estado'],
]);

it('los casos son buscables globalmente y delegan en el buscador del panel', function (): void {
    expect(TelemedicineCaseResource::getGloballySearchableAttributes())->not->toBeEmpty()
        ->and(TelemedicineCaseResource::getGlobalSearchResultsLimit())->toBe(TelemedicineCaseGlobalSearch::RESULTS_LIMIT);
});

it('pacientes y doctores salen del buscador: no respetaban el filtro por médico', function (): void {
    foreach ([TelemedicinePatientResource::class, TelemedicineDoctorResource::class] as $resource) {
        $property = (new ReflectionClass($resource))->getProperty('isGloballySearchable');

        expect($property->getValue())->toBeFalse();
    }
});

it('el buscador usa el alcance de la bitácora, que incluye las altas médicas', function () use ($basePath): void {
    $search = file_get_contents($basePath.'/app/Support/Telemedicine/TelemedicineCaseGlobalSearch.php');
    $listQuery = file_get_contents($basePath.'/app/Support/Telemedicine/TelemedicineCaseFilamentListQuery.php');

    expect($search)->toContain('TelemedicineCaseFilamentListQuery::applyTelemedicinaGlobalSearchConstraints($query)');

    preg_match('/function applyTelemedicinaGlobalSearchConstraints\(.*?\n    \}/s', $listQuery, $method);

    expect($method[0] ?? '')
        ->toContain('self::applyTelemedicinaBitacoraConstraints($query)')
        ->not->toContain('ALTA MEDICA');
});

it('el panel tiene atajo Ctrl/Cmd+K, espera de 300 ms y el texto guía del buscador', function () use ($basePath): void {
    $provider = file_get_contents($basePath.'/app/Providers/Filament/TelemedicinaPanelProvider.php');

    expect($provider)
        ->toContain("->globalSearchDebounce('300ms')")
        ->toContain("->globalSearchKeyBindings(['command+k', 'ctrl+k'])")
        ->toContain('->bootUsing(fn (): mixed => self::registerGlobalSearchPlaceholder())');
});

it('el texto guía reemplaza solo el placeholder y conserva las demás traducciones', function (): void {
    TelemedicinaPanelProvider::registerGlobalSearchPlaceholder();

    expect(__('filament-panels::global-search.field.placeholder'))->toBe(TelemedicinaPanelProvider::GLOBAL_SEARCH_PLACEHOLDER)
        ->and(__('filament-panels::global-search.no_results_message'))->not->toBe('filament-panels::global-search.no_results_message')
        ->and(__('filament-panels::global-search.field.label'))->not->toBe('filament-panels::global-search.field.label');
});
