<?php

declare(strict_types=1);

use App\Filament\Concerns\PricesIndividualPreAffiliation;
use App\Filament\Forms\Components\IndividualPreAffiliationSummary;
use App\Models\Affiliate;
use App\Models\Affiliation;
use App\Models\Fee;
use App\Support\Affiliations\IndividualPreAffiliationPricing;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(Tests\TestCase::class);

/**
 * Montos de la pre-afiliación individual de la red comercial: por edad, en el servidor, sin tocar
 * la cotización y sin guardar nada si alguien queda sin tarifa. Escribe dentro de una transacción
 * que siempre se revierte. Las tarifas esperadas se leen del catálogo, no se fijan a mano.
 */
beforeEach(function (): void {
    DB::beginTransaction();

    $this->fee = static fn (int $planId, ?int $coverageId, int $age): float => (float) Fee::query()
        ->with('ageRange')
        ->forPlan($planId)
        ->when($coverageId === null, fn ($q) => $q->whereNull('coverage_id'), fn ($q) => $q->where('coverage_id', $coverageId))
        ->get()
        ->first(function (Fee $fee) use ($age): bool {
            preg_match_all('/\d+/', (string) $fee->ageRange?->range, $m);
            [$min, $max] = [min((int) $m[0][0], (int) ($m[0][1] ?? $m[0][0])), max((int) $m[0][0], (int) ($m[0][1] ?? $m[0][0]))];

            return $age >= $min && $age <= $max;
        })?->price;

    $this->birth = static fn (int $age): string => Carbon::today()->subYears($age)->subDays(10)->format('d-m-Y');
});

afterEach(function (): void {
    DB::rollBack();
});

function qaAffiliate(Affiliation $affiliation, string $name, string $relationship, string $birthDate, int $age): Affiliate
{
    $template = Affiliate::query()->orderByDesc('id')->firstOrFail();
    $affiliate = $template->replicate();
    $affiliate->forceFill([
        'affiliation_id' => $affiliation->getKey(),
        'full_name' => $name,
        'nro_identificacion' => (string) random_int(90000000, 99999999),
        'relationship' => $relationship,
        'birth_date' => $birthDate,
        'age' => $age,
        'status' => 'PRE-APROBADA',
        'plan_id' => null,
        'coverage_id' => null,
        'age_range_id' => null,
        'fee' => null,
        'total_amount' => null,
    ])->save();

    return $affiliate;
}

it('calcula por edad: Especial 5.000 con dos personas de 0-30 y una de 31-65', function (): void {
    $young = ($this->fee)(3, 11, 15);
    $adult = ($this->fee)(3, 11, 46);

    $result = IndividualPreAffiliationPricing::make()->price(3, 11, 'TRIMESTRAL', [
        ['name' => 'Titular', 'birth_date' => ($this->birth)(15)],
        ['name' => 'Hija', 'birth_date' => ($this->birth)(29)],
        ['name' => 'Madre', 'birth_date' => ($this->birth)(46)],
    ]);

    expect($result['problems'])->toBe([])
        ->and($result['persons'])->toBe(3)
        ->and(array_column($result['lines'], 'annual_fee'))->toBe([$young, $young, $adult])
        ->and($result['fee_anual'])->toBe(round(2 * $young + $adult, 2))
        ->and($result['total_amount'])->toBe(round((2 * $young + $adult) / 4, 2));
});

it('el plan paquete (Inicial) no usa cobertura', function (): void {
    $result = IndividualPreAffiliationPricing::make()->price(1, null, 'ANUAL', [
        ['name' => 'Titular', 'birth_date' => ($this->birth)(80)],
    ]);

    expect($result['problems'])->toBe([])
        ->and($result['fee_anual'])->toBe(($this->fee)(1, null, 80));
});

it('dice quién no tiene tarifa y por qué', function (): void {
    $result = IndividualPreAffiliationPricing::make()->price(3, 11, 'ANUAL', [
        ['name' => 'Abuelo', 'birth_date' => ($this->birth)(92)],
        ['name' => 'Sin fecha', 'birth_date' => ''],
        ['name' => 'Futuro', 'birth_date' => Carbon::tomorrow()->format('d-m-Y')],
    ]);

    expect($result['problems'])->toHaveCount(3)
        ->and($result['problems'][0])->toContain('Abuelo', '92 años')
        ->and($result['problems'][1])->toContain('fecha de nacimiento')
        ->and($result['problems'][2])->toContain('Futuro')
        ->and(IndividualPreAffiliationPricing::make()->price(3, null, 'ANUAL', [['name' => 'X', 'birth_date' => ($this->birth)(30)]])['problems'][0])->toContain('cobertura')
        ->and(IndividualPreAffiliationPricing::make()->price(null, null, 'ANUAL', [['name' => 'X', 'birth_date' => ($this->birth)(30)]])['problems'][0])->toContain('plan');
});

it('acepta las fechas en los formatos del formulario y una frecuencia desconocida cae en anual', function (): void {
    $pricing = IndividualPreAffiliationPricing::make();
    $date = Carbon::today()->subYears(40)->subDays(3);

    expect($pricing->age($date->format('d-m-Y')))->toBe(40)
        ->and($pricing->age($date->format('d/m/Y')))->toBe(40)
        ->and($pricing->age($date->format('Y-m-d')))->toBe(40)
        ->and($pricing->age('no es fecha'))->toBeNull()
        ->and(IndividualPreAffiliationPricing::frequency('trimestral'))->toBe('TRIMESTRAL')
        ->and(IndividualPreAffiliationPricing::frequency('<script>'))->toBe('ANUAL');
});

it('antes de guardar reemplaza los montos del navegador y solo cuenta afiliados si se incluyen', function (): void {
    $data = IndividualPreAffiliationPricing::make()->applyToFormData([
        'plan_id' => '3', 'coverage_id' => '11', 'payment_frequency' => 'SEMESTRAL',
        'full_name_ti' => 'TITULAR', 'birth_date_ti' => ($this->birth)(15),
        'feedback' => true,
        'affiliates' => [['full_name' => 'HIJA', 'birth_date' => ($this->birth)(29)]],
        'fee_anual' => 1, 'total_amount' => 1,
    ]);

    $young = ($this->fee)(3, 11, 15);

    expect($data['fee_anual'])->toBe(round(2 * $young, 2))
        ->and($data['total_amount'])->toBe(round($young, 2))
        ->and($data['family_members'])->toBe(2);

    $solo = IndividualPreAffiliationPricing::make()->applyToFormData([
        'plan_id' => 3, 'coverage_id' => 11, 'payment_frequency' => 'ANUAL',
        'full_name_ti' => 'TITULAR', 'birth_date_ti' => ($this->birth)(15),
        'feedback' => false,
        'affiliates' => [['full_name' => 'NO CUENTA', 'birth_date' => ($this->birth)(29)]],
    ]);

    expect($solo['family_members'])->toBe(1)
        ->and($solo['fee_anual'])->toBe($young);
});

it('no deja guardar si alguien queda sin tarifa', function (): void {
    expect(fn () => IndividualPreAffiliationPricing::make()->applyToFormData([
        'plan_id' => 3, 'coverage_id' => 11, 'payment_frequency' => 'ANUAL',
        'full_name_ti' => 'TITULAR', 'birth_date_ti' => ($this->birth)(92), 'feedback' => false,
    ]))->toThrow(ValidationException::class);
});

it('al crear asigna tarifa a cada afiliado, guarda la suma real, no toca la cotización y deja constancia de la diferencia', function (): void {
    $source = Affiliation::query()->whereNotNull('individual_quote_id')->where('plan_id', 3)->whereNotNull('coverage_id')->orderByDesc('id')->first();

    if (! $source instanceof Affiliation) {
        $this->markTestSkipped('No hay afiliaciones del Plan Especial con cotización.');
    }

    $affiliation = $source->replicate();
    $affiliation->forceFill(['code' => 'TDEC-IND-QA'.random_int(10000, 99999), 'coverage_id' => 11, 'payment_frequency' => 'TRIMESTRAL', 'fee_anual' => 9999, 'total_amount' => 9999, 'status' => 'PRE-APROBADA'])->save();

    foreach ([['TITULAR', 15], ['HIJA', 29]] as [$relationship, $age]) {
        qaAffiliate($affiliation, 'QA '.$relationship, $relationship, ($this->birth)($age), 0);
    }

    $quoteBefore = DB::table('detail_individual_quotes')->where('individual_quote_id', $affiliation->individual_quote_id)->orderBy('id')->get(['total_persons', 'subtotal_anual'])->toArray();
    $young = ($this->fee)(3, 11, 15);

    $result = IndividualPreAffiliationPricing::make()->applyToAffiliation($affiliation);
    $affiliates = Affiliate::query()->where('affiliation_id', $affiliation->getKey())->orderBy('id')->get();
    $quoted = IndividualPreAffiliationPricing::make()->quotedAnnual($affiliation);

    expect($result['fee_anual'])->toBe(round(2 * $young, 2))
        ->and((float) $affiliation->fresh()->fee_anual)->toBe(round(2 * $young, 2))
        ->and((float) $affiliation->fresh()->total_amount)->toBe(round(2 * $young / 4, 2))
        ->and((int) $affiliation->fresh()->family_members)->toBe(2)
        ->and($affiliates->pluck('fee')->map(fn ($v) => (float) $v)->all())->toBe([$young, $young])
        ->and($affiliates->pluck('plan_id')->map(fn ($v) => (int) $v)->unique()->all())->toBe([3])
        ->and($affiliates->pluck('coverage_id')->map(fn ($v) => (int) $v)->unique()->all())->toBe([11])
        ->and($affiliates->pluck('age')->map(fn ($v) => (int) $v)->all())->toBe([15, 29])
        ->and($affiliates->every(fn (Affiliate $a): bool => filled($a->age_range_id)))->toBeTrue()
        ->and(DB::table('detail_individual_quotes')->where('individual_quote_id', $affiliation->individual_quote_id)->orderBy('id')->get(['total_persons', 'subtotal_anual'])->toArray())->toEqual($quoteBefore);

    $log = DB::table('status_log_affiliations')->where('affiliation_id', $affiliation->getKey())->where('action', 'MONTOS CALCULADOS POR EDAD')->first();

    if ($quoted !== null && abs($quoted - 2 * $young) >= 0.01) {
        expect($log)->not->toBeNull()
            ->and($log->observation)->toContain(number_format($quoted, 2, ',', '.'));
    } else {
        expect($log)->toBeNull();
    }
});

it('si un afiliado queda sin tarifa al crear, falla para que se revierta todo', function (): void {
    $source = Affiliation::query()->where('plan_id', 3)->whereNotNull('coverage_id')->orderByDesc('id')->first();

    if (! $source instanceof Affiliation) {
        $this->markTestSkipped('No hay afiliaciones del Plan Especial.');
    }

    $affiliation = $source->replicate();
    $affiliation->forceFill(['code' => 'TDEC-IND-QA'.random_int(10000, 99999), 'coverage_id' => 11, 'status' => 'PRE-APROBADA'])->save();
    qaAffiliate($affiliation, 'QA ABUELO', 'TITULAR', ($this->birth)(92), 92);

    expect(fn () => IndividualPreAffiliationPricing::make()->applyToAffiliation($affiliation))->toThrow(ValidationException::class, 'QA ABUELO');
});

it('el aviso del formulario detiene el guardado y pide revertir la transacción', function (): void {
    $page = new class
    {
        use PricesIndividualPreAffiliation;

        public function halt(string $message): void
        {
            $this->haltIndividualPreAffiliation($message);
        }
    };

    try {
        $page->halt('prueba');
        $this->fail('Debió detenerse.');
    } catch (Halt $halt) {
        expect($halt->shouldRollbackDatabaseTransaction())->toBeTrue();
    }
});

it('el resumen del formulario muestra cada persona, el total y los avisos', function (): void {
    $ok = IndividualPreAffiliationSummary::html([
        'lines' => [['name' => '<b>Ana</b>', 'age' => 30, 'annual_fee' => 311.0, 'period_amount' => 77.75, 'problem' => null]],
        'problems' => [], 'fee_anual' => 311.0, 'total_amount' => 77.75, 'persons' => 1,
    ], 'TRIMESTRAL', 650.0);

    $blocked = IndividualPreAffiliationSummary::html([
        'lines' => [['name' => 'Abuelo', 'age' => 92, 'annual_fee' => null, 'period_amount' => null, 'problem' => 'sin tarifa']],
        'problems' => ['Abuelo: con 92 años no tiene tarifa en este plan y cobertura.'], 'fee_anual' => 0.0, 'total_amount' => 0.0, 'persons' => 1,
    ], 'ANUAL', null);

    expect($ok)->toContain('&lt;b&gt;Ana&lt;/b&gt;', 'US$ 311,00', 'US$ 77,75', 'Cuota trimestral', 'La cotización indicaba US$ 650,00')
        ->and($blocked)->toContain('No se podrá guardar así', '92 años');
});

it('las tres altas usan el cálculo por edad y ya no reescriben la cotización', function (string $panel): void {
    $root = dirname(__DIR__, 2);
    $page = (string) file_get_contents($root.'/app/Filament/'.$panel.'/Resources/Affiliations/Pages/CreateAffiliation.php');
    $form = (string) file_get_contents($root.'/app/Filament/'.$panel.'/Resources/Affiliations/Schemas/AffiliationForm.php');

    expect($page)->toContain('use PricesIndividualPreAffiliation;')
        ->and($page)->toContain('$this->priceIndividualPreAffiliationData($data)')
        ->and(substr_count($page, '$this->priceIndividualPreAffiliation($record);'))->toBe(2)
        ->and($page)->not->toContain('total_persons')
        ->and($page)->not->toContain('dd($th)')
        ->and($page)->toContain('catch (Halt $halt)')
        ->and($form)->toContain('IndividualPreAffiliationSummary::make()');
})->with(['Agents', 'Master', 'General']);
