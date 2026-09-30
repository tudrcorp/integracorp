<?php

declare(strict_types=1);

use App\Filament\Telemedicina\Widgets\CaseStats;
use App\Filament\Telemedicina\Widgets\TelemedicineCaseTableDash;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineConsultationPatient;
use App\Models\User;
use App\Support\Telemedicine\TelemedicineCaseFilamentListQuery;
use App\Support\Telemedicine\TelemedicineCaseFollowUpSchedule;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

uses(Tests\TestCase::class);

beforeEach(fn () => DB::beginTransaction());
afterEach(fn () => DB::rollBack());

/**
 * Médico real (sólo lectura) con casos en seguimiento, como usuario en memoria.
 */
function medicoDelEscritorio(bool $deProveedor = false): ?User
{
    $doctorId = TelemedicineCase::query()
        ->join('telemedicine_doctors as d', 'd.id', '=', 'telemedicine_cases.telemedicine_doctor_id')
        ->when($deProveedor, fn ($q) => $q->whereNotNull('d.supplier_id'), fn ($q) => $q->where('d.managed_by', '<>', 'TDG')->whereNull('d.supplier_id'))
        ->where('telemedicine_cases.status', 'EN SEGUIMIENTO')
        ->value('telemedicine_cases.telemedicine_doctor_id');

    if ($doctorId === null && ! $deProveedor) {
        $doctorId = TelemedicineCase::query()->where('status', 'EN SEGUIMIENTO')->value('telemedicine_doctor_id');
    }

    if ($doctorId === null) {
        return null;
    }

    return User::factory()->make([
        'id' => 999_999_301,
        'email' => 'qa.dashboard@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['TELEMEDICINA'],
        'doctor_id' => (int) $doctorId,
    ]);
}

/*
 * ---------------------------------------------------------------------------
 * Cálculo del próximo seguimiento
 * ---------------------------------------------------------------------------
 */

it('24, 48 y 72 son horas; el resto son minutos', function (mixed $valor, ?int $minutos): void {
    expect(TelemedicineCaseFollowUpSchedule::minutesFor($valor))->toBe($minutos);
})->with([
    [30, 30], [180, 180], [24, 1440], [48, 2880], [72, 4320], [null, null], [0, null], ['60', 60],
]);

it('describe el seguimiento según lo que falta', function (mixed $proximo, bool $pendiente, string $etiqueta, string $tono): void {
    $ahora = CarbonImmutable::parse('2026-09-28 10:00:00');

    $texto = TelemedicineCaseFollowUpSchedule::describe($proximo, $pendiente, $ahora);

    expect($texto['label'])->toBe($etiqueta)->and($texto['tone'])->toBe($tono);
})->with([
    'pendiente' => [null, true, 'Pendiente de primera consulta', TelemedicineCaseFollowUpSchedule::TONE_PENDING],
    'sin programar' => [null, false, 'Sin programar', TelemedicineCaseFollowUpSchedule::TONE_NONE],
    'vencido' => ['2026-09-28 07:30:00', false, 'Vencido hace 2 h 30 min', TelemedicineCaseFollowUpSchedule::TONE_OVERDUE],
    'vencido días' => ['2026-09-25 10:00:00', false, 'Vencido hace 3 días', TelemedicineCaseFollowUpSchedule::TONE_OVERDUE],
    'pronto' => ['2026-09-28 10:45:00', false, 'En 45 min', TelemedicineCaseFollowUpSchedule::TONE_SOON],
    'hoy' => ['2026-09-28 15:00:00', false, 'Hoy 03:00 PM', TelemedicineCaseFollowUpSchedule::TONE_SCHEDULED],
    'mañana' => ['2026-09-29 09:30:00', false, 'Mañana 09:30 AM', TelemedicineCaseFollowUpSchedule::TONE_SCHEDULED],
]);

it('el SQL calcula lo mismo que la regla: última consulta más el intervalo', function (): void {
    $casos = TelemedicineCaseFollowUpSchedule::withFollowUpColumns(TelemedicineCase::query())
        ->has('consultations')
        ->latest('id')
        ->limit(30)
        ->get();

    if ($casos->isEmpty()) {
        $this->markTestSkipped('No hay casos con consultas.');
    }

    foreach ($casos as $caso) {
        $ultima = TelemedicineConsultationPatient::query()->where('telemedicine_case_id', $caso->id)->orderByDesc('id')->first();
        $minutos = TelemedicineCaseFollowUpSchedule::minutesFor($ultima->priorityMonitoring);
        $esperado = $minutos === null ? null : Carbon::parse($ultima->created_at)->addMinutes($minutos)->format('Y-m-d H:i:s');

        expect($caso->next_follow_up_at === null ? null : Carbon::parse($caso->next_follow_up_at)->format('Y-m-d H:i:s'))->toBe($esperado)
            ->and((bool) $caso->pending_first_consultation)->toBeFalse();
    }
});

it('ordena: pendientes de primera consulta, el seguimiento más cercano y al final los sin programar', function (): void {
    $filas = TelemedicineCaseFollowUpSchedule::orderByNextFollowUp(
        TelemedicineCaseFollowUpSchedule::withFollowUpColumns(TelemedicineCase::query()->where('status', '!=', 'ALTA MEDICA'))
    )->limit(300)->get(['telemedicine_cases.id', 'telemedicine_cases.created_at']);

    if ($filas->count() < 2) {
        $this->markTestSkipped('Pocos casos.');
    }

    $rango = $filas->map(fn (TelemedicineCase $c): array => [
        (bool) $c->pending_first_consultation ? 0 : ($c->next_follow_up_at === null ? 2 : 1),
        (string) $c->next_follow_up_at,
    ])->all();

    $ordenado = $rango;
    usort($ordenado, fn (array $a, array $b): int => $a[0] <=> $b[0] ?: ($a[0] === 1 ? strcmp($a[1], $b[1]) : 0));

    expect(array_column($rango, 0))->toBe(array_column($ordenado, 0))
        ->and(collect($rango)->where(0, 1)->pluck(1)->all())->toBe(collect($ordenado)->where(0, 1)->pluck(1)->all());
});

it('vencidos son sólo los que tienen un seguimiento anterior a ahora', function (): void {
    $ahora = CarbonImmutable::parse('2026-09-28 10:00:00');
    $vencidos = TelemedicineCaseFollowUpSchedule::whereOverdue(
        TelemedicineCaseFollowUpSchedule::withFollowUpColumns(TelemedicineCase::query()),
        $ahora,
    )->limit(50)->get();

    foreach ($vencidos as $caso) {
        expect(Carbon::parse($caso->next_follow_up_at)->lessThan($ahora))->toBeTrue();
    }

    expect(true)->toBeTrue();
});

it('las consultas tienen índice por caso para el cálculo en cada refresco', function (): void {
    expect(Schema::hasIndex('telemedicine_consultation_patients', 'tcp_case_id_index'))->toBeTrue();
});

/*
 * ---------------------------------------------------------------------------
 * Tarjetas y tabla del escritorio
 * ---------------------------------------------------------------------------
 */

it('las tarjetas cuentan los mismos casos que ve la tabla', function (): void {
    $medico = medicoDelEscritorio() ?? $this->markTestSkipped('No hay médicos con casos.');
    $this->actingAs($medico);
    Filament::setCurrentPanel('telemedicina');

    $tarjetas = new CaseStats;
    $tabla = TelemedicineCaseFilamentListQuery::applyDashboardWidgetCaseConstraints(TelemedicineCase::query());

    expect($tarjetas->countFollowUp())->toBe((clone $tabla)->where('status', 'EN SEGUIMIENTO')->count())
        ->and($tarjetas->countAssigned())->toBe((clone $tabla)->where('status', 'ASIGNADO')->count())
        ->and($tarjetas->countOverdue())->toBe(TelemedicineCaseFollowUpSchedule::whereOverdue(clone $tabla)->count())
        ->and($tarjetas->countDischarged())->toBe(
            TelemedicineCaseFilamentListQuery::applyDashboardScope(TelemedicineCase::query(), includeDischarged: true)->where('status', 'ALTA MEDICA')->count()
        );
});

it('un médico de proveedor ve la tarjeta de traslados en ambulancia', function (): void {
    $medico = medicoDelEscritorio(deProveedor: true) ?? $this->markTestSkipped('No hay médicos de proveedor con casos.');
    $this->actingAs($medico);
    Filament::setCurrentPanel('telemedicina');

    expect((new CaseStats)->userDoctorBelongsToSupplier())->toBeTrue();

    Livewire::test(CaseStats::class)
        ->assertSee('TRASLADOS EN AMBULANCIA')
        ->assertSee('SEGUIMIENTOS VENCIDOS');
});

it('pulsar una tarjeta filtra la tabla y volver a pulsarla muestra todo', function (): void {
    $medico = medicoDelEscritorio() ?? $this->markTestSkipped('No hay médicos con casos.');
    $this->actingAs($medico);
    Filament::setCurrentPanel('telemedicina');

    $enSeguimiento = TelemedicineCaseFilamentListQuery::applyDashboardWidgetCaseConstraints(TelemedicineCase::query())
        ->where('status', 'EN SEGUIMIENTO')
        ->count();

    Livewire::test(TelemedicineCaseTableDash::class)
        ->call('applyDashboardFilter', CaseStats::FILTER_FOLLOW_UP)
        ->assertSet('dashboardFilter', CaseStats::FILTER_FOLLOW_UP)
        ->assertCountTableRecords($enSeguimiento)
        ->assertSee('sólo casos en seguimiento')
        ->call('applyDashboardFilter', 'valor-invalido')
        ->assertSet('dashboardFilter', null);

    Livewire::test(CaseStats::class)
        ->call('syncActiveFilter', CaseStats::FILTER_OVERDUE)
        ->assertSee('Filtrando la tabla');
});

it('la tabla muestra próximo seguimiento y línea de negocio', function (): void {
    $medico = medicoDelEscritorio() ?? $this->markTestSkipped('No hay médicos con casos.');
    $this->actingAs($medico);
    Filament::setCurrentPanel('telemedicina');

    Livewire::test(TelemedicineCaseTableDash::class)
        ->assertSuccessful()
        ->assertSee('Próximo seguimiento')
        ->assertSee('Línea de negocio')
        ->assertSee('Ordenados por próximo seguimiento');
});

it('la línea de negocio se ve como etiqueta de color con la unidad específica debajo', function (): void {
    $render = function (?string $linea, ?string $unidad): string {
        $paciente = new App\Models\TelemedicinePatient(['specific_business_unit' => $unidad]);
        $paciente->setRelation('businessLine', $linea === null ? null : new App\Models\BusinessLine(['definition' => $linea]));
        $caso = new TelemedicineCase;
        $caso->setRelation('telemedicinePatient', $paciente);

        return view('filament.telemedicina.tables.business-line-cell', ['getRecord' => fn (): TelemedicineCase => $caso])->render();
    };

    expect($render('CORPORATIVOS', 'PQQ'))->toContain('CORPORATIVOS')->toContain('bg-indigo-50')->toContain('PQQ')
        ->and($render('INDIVIDUALES', null))->toContain('bg-teal-50')->not->toContain('Unidad de negocio específica')
        ->and($render(null, null))->toContain('Sin línea de negocio');
});
