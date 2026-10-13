<?php

declare(strict_types=1);

use App\Models\AgeRange;
use App\Models\TelemedicinePatient;
use App\Services\PublicAiAgent\IntentSlotFiller;
use App\Support\Filament\PatientAgeDisplay;
use App\Support\PatientAge;
use App\Support\Plans\PlanInfantEligibility;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

/**
 * Solo lee la base; la transacción revertida garantiza que nada quede escrito.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    PatientAge::flush();
});

afterEach(fn () => DB::rollBack());

it('arma la edad legible según la fecha de nacimiento', function (string $birthDate, string $expected): void {
    expect(PatientAge::label($birthDate, null, Carbon::parse('2026-10-12')))->toBe($expected);
})->with([
    'nació hoy' => ['2026-10-12', 'Recién nacido'],
    '1 día' => ['2026-10-11', '1 día'],
    '29 días' => ['2026-09-13', '29 días'],
    '1 mes justo' => ['2026-09-12', '1 mes'],
    '7 meses' => ['2026-03-05', '7 meses'],
    '11 meses' => ['2025-10-13', '11 meses'],
    '1 año justo' => ['2025-10-12', '1 año'],
    '1 año y 3 meses' => ['2025-07-03', '1 año y 3 meses'],
    '1 año y 11 meses' => ['2024-10-13', '1 año y 11 meses'],
    '2 años' => ['2024-10-12', '2 años'],
    'adulto' => ['1992-01-01', '34 años'],
    'formato d/m/Y' => ['03/07/2025', '1 año y 3 meses'],
    'formato d-m-Y' => ['05-03-2026', '7 meses'],
]);

it('sin fecha usa la edad guardada y nunca dice «0 años»', function (): void {
    expect(PatientAge::label(null, 0))->toBe('Menor de 1 año')
        ->and(PatientAge::label('', '0'))->toBe('Menor de 1 año')
        ->and(PatientAge::label(null, '1'))->toBe('1 año')
        ->and(PatientAge::label(null, 34))->toBe('34 años')
        ->and(PatientAge::label(null, null))->toBe('—')
        ->and(PatientAge::label(null, '-3'))->toBe('—')
        ->and(PatientAge::label(null, 'abc'))->toBe('—')
        ->and(PatientAge::labelOrNull(null, null))->toBeNull();
});

it('una fecha futura o ilegible cae en la edad guardada', function (): void {
    expect(PatientAge::label('2030-01-01', 5, Carbon::parse('2026-10-12')))->toBe('5 años')
        ->and(PatientAge::label('no-es-fecha', 0))->toBe('Menor de 1 año');
});

it('la fecha de nacimiento manda sobre una edad guardada vieja', function (): void {
    // La edad guardada no se actualiza sola: un bebé registrado con 0 hoy puede tener 1 año.
    expect(PatientAge::label('2025-07-03', 0, Carbon::parse('2026-10-12')))->toBe('1 año y 3 meses')
        ->and(PatientAge::isUnderOneYear('2025-07-03', 0, Carbon::parse('2026-10-12')))->toBeFalse()
        ->and(PatientAge::isUnderOneYear('2026-05-01', 3, Carbon::parse('2026-10-12')))->toBeTrue()
        ->and(PatientAge::isUnderOneYear(null, 0))->toBeTrue()
        ->and(PatientAge::isUnderOneYear(null, null))->toBeFalse();
});

it('las plantillas resuelven la edad con el id del paciente de telemedicina', function (): void {
    $patient = TelemedicinePatient::query()->whereNotNull('birth_date')->where('birth_date', '!=', '')->first(['id', 'birth_date', 'age']);

    if ($patient === null) {
        $this->markTestSkipped('No hay pacientes con fecha de nacimiento.');
    }

    expect(PatientAge::forTelemedicinePatientId($patient->id, 99))->toBe(PatientAge::label($patient->birth_date, 99))
        ->and(PatientAge::forTelemedicinePatientId(0, 0))->toBe('Menor de 1 año')
        ->and(PatientAge::forTelemedicinePatientId(null, null))->toBe('—');
});

it('el formateador de Filament lee la fecha del registro o de su relación', function (): void {
    $patient = new TelemedicinePatient(['birth_date' => now()->subMonths(5)->format('d/m/Y')]);
    $case = new App\Models\TelemedicineCase;
    $case->setRelation('telemedicinePatient', $patient);

    expect(PatientAgeDisplay::formatter('birth_date')(0, $patient))->toBe('5 meses')
        ->and(PatientAgeDisplay::formatter('telemedicinePatient.birth_date')('0', $case))->toBe('5 meses')
        ->and(PatientAgeDisplay::formatter(null)('0', $case))->toBe('Menor de 1 año')
        ->and(PatientAgeDisplay::formatter('birth_date')('40', new TelemedicinePatient))->toBe('40 años');
});

it('rechaza a un bebé en los planes cuyo primer rango empieza en 1 año', function (): void {
    $baby = now()->subMonths(4)->format('d/m/Y');
    $planFromOne = AgeRange::query()->select('plan_id')->groupBy('plan_id')->havingRaw('MIN(age_init) >= 1')->value('plan_id');
    $planFromZero = AgeRange::query()->where('age_init', 0)->value('plan_id');

    if ($planFromOne === null || $planFromZero === null) {
        $this->markTestSkipped('No hay planes con y sin rango desde 0 años.');
    }

    $message = PlanInfantEligibility::rejectionMessage($planFromOne, $baby);

    expect($message)
        ->toContain('no admite afiliados menores de 1 año')
        ->toContain('su primer rango de edad empieza en')
        ->toContain('Elija otro plan o solicite a Negocios un rango de edad desde 0 años.')
        ->and(PlanInfantEligibility::rejectionMessage($planFromZero, $baby))->toBeNull()
        ->and(PlanInfantEligibility::rejectionMessage($planFromOne, '01/01/1990'))->toBeNull()
        ->and(PlanInfantEligibility::rejectionMessage($planFromOne, null, 0))->not->toBeNull()
        ->and(PlanInfantEligibility::rejectionMessage(null, $baby))->toBeNull();
});

it('no bloquea planes sin rangos configurados', function (): void {
    $planWithoutRanges = App\Models\Plan::query()->whereDoesntHave('ageRanges')->value('id');

    if ($planWithoutRanges === null) {
        $this->markTestSkipped('Todos los planes tienen rangos.');
    }

    expect(PlanInfantEligibility::rejectionMessage($planWithoutRanges, now()->subMonth()->format('d/m/Y')))->toBeNull();
});

it('la regla de Filament falla con el mensaje y deja pasar a un adulto', function (): void {
    $planFromOne = AgeRange::query()->select('plan_id')->groupBy('plan_id')->havingRaw('MIN(age_init) >= 1')->value('plan_id');

    if ($planFromOne === null) {
        $this->markTestSkipped('No hay planes cuyo primer rango empiece en 1 año.');
    }

    $rule = PlanInfantEligibility::rule(fn (): int => (int) $planFromOne);
    $errors = [];
    $fail = function (string $message) use (&$errors): void {
        $errors[] = $message;
    };

    $rule('birth_date', now()->subMonths(2)->format('d/m/Y'), $fail);
    $rule('birth_date', '01/01/1990', $fail);

    expect($errors)->toHaveCount(1)
        ->and($errors[0])->toContain('no admite afiliados menores de 1 año');
});

it('el chat público entiende meses, bebés y «1 año y 3 meses»', function (string $message, array $expected): void {
    expect(IntentSlotFiller::membersFromFreeText($message))->toEqualCanonicalizing($expected);
})->with([
    'adulto y bebé' => ['mi esposa de 34 años y mi bebé de 6 meses', [['age' => 34, 'quantity' => 1], ['age' => 0, 'quantity' => 1]]],
    'recién nacido' => ['un recién nacido', [['age' => 0, 'quantity' => 1]]],
    'año y meses' => ['mi hijo de 1 año y 3 meses', [['age' => 1, 'quantity' => 1]]],
    'semanas' => ['bebe de 3 semanas y abuela 70 años', [['age' => 70, 'quantity' => 1], ['age' => 0, 'quantity' => 1]]],
    'solo adultos' => ['somos de 30 años', [['age' => 30, 'quantity' => 1]]],
]);

it('los formularios de afiliados validan la fecha de nacimiento contra el plan', function (string $path): void {
    expect(file_get_contents(dirname(__DIR__, 2).'/'.$path))
        ->toContain('->rule(PlanInfantEligibility::filamentBirthDateRule())');
})->with([
    'app/Filament/Business/Resources/Affiliations/RelationManagers/AffiliatesRelationManager.php',
    'app/Filament/Business/Resources/AffiliationCorporates/RelationManagers/CorporateAffiliatesRelationManager.php',
    'app/Filament/Business/Resources/Affiliations/Schemas/AffiliationForm.php',
    'app/Filament/Administration/Resources/Affiliations/RelationManagers/AffiliatesRelationManager.php',
    'app/Filament/Administration/Resources/AffiliationCorporates/RelationManagers/CorporateAffiliatesRelationManager.php',
    'app/Filament/Agents/Resources/Affiliations/RelationManagers/AffiliatesRelationManager.php',
    'app/Filament/Agents/Resources/Affiliations/Schemas/AffiliationForm.php',
    'app/Filament/Master/Resources/Affiliations/RelationManagers/AffiliatesRelationManager.php',
    'app/Filament/Master/Resources/Affiliations/Schemas/AffiliationForm.php',
    'app/Filament/General/Resources/Affiliations/RelationManagers/AffiliatesRelationManager.php',
    'app/Filament/General/Resources/Affiliations/Schemas/AffiliationForm.php',
    'app/Filament/Resources/Affiliations/RelationManagers/AffiliatesRelationManager.php',
    'app/Filament/Resources/Affiliations/Schemas/AffiliationForm.php',
    'app/Filament/Operations/Support/AffiliatePersonalDataFields.php',
    'app/Livewire/IndividualPreAffiliation.php',
]);

it('los documentos de telemedicina ya no imprimen «años» pegado a la edad', function (string $view): void {
    $source = file_get_contents(dirname(__DIR__, 2)."/resources/views/documents/partials/{$view}.blade.php");

    expect($source)->toContain('PatientAge::forTelemedicinePatientId')
        ->not->toContain("(\$data['age_patient'] ?? null)) }} años");
})->with(['informe-medico-homologado', 'telemedicine-recipe-homologado', 'telemedicine-orden-homologada']);

/**
 * Get de Filament con valores fijos por ruta, para probar cómo el adaptador
 * encuentra el plan sin montar un formulario.
 *
 * @param  array<string, mixed>  $values
 */
function getConValores(array $values): Filament\Schemas\Components\Utilities\Get
{
    $get = Mockery::mock(Filament\Schemas\Components\Utilities\Get::class);
    $get->shouldReceive('__invoke')->andReturnUsing(fn (string $path = ''): mixed => $values[$path] ?? null);

    return $get;
}

it('el adaptador de Filament toma el plan del formulario, del repeater o del registro dueño', function (): void {
    $planFromOne = (int) AgeRange::query()->select('plan_id')->groupBy('plan_id')->havingRaw('MIN(age_init) >= 1')->value('plan_id');
    $planFromZero = (int) AgeRange::query()->where('age_init', 0)->value('plan_id');

    if ($planFromOne === 0 || $planFromZero === 0) {
        $this->markTestSkipped('No hay planes con y sin rango desde 0 años.');
    }

    $baby = now()->subMonths(3)->format('d/m/Y');
    $owner = new class($planFromOne)
    {
        public function __construct(private int $planId) {}

        public function getOwnerRecord(): App\Models\Affiliation
        {
            return new App\Models\Affiliation(['plan_id' => $this->planId]);
        }
    };

    $failures = function (Filament\Schemas\Components\Utilities\Get $get, mixed $livewire) use ($baby): array {
        $errors = [];
        $rule = PlanInfantEligibility::filamentBirthDateRule()($get, $livewire);
        $rule('birth_date', $baby, function (string $message) use (&$errors): void {
            $errors[] = $message;
        });

        return $errors;
    };

    expect($failures(getConValores(['plan_id' => $planFromOne]), null))->toHaveCount(1)
        ->and($failures(getConValores(['../../plan_id' => $planFromOne]), null))->toHaveCount(1)
        ->and($failures(getConValores([]), $owner))->toHaveCount(1)
        // El plan del formulario manda sobre el del registro dueño.
        ->and($failures(getConValores(['plan_id' => $planFromZero]), $owner))->toBe([])
        ->and($failures(getConValores([]), null))->toBe([]);
});
