<?php

declare(strict_types=1);

use App\Support\AffiliationCorporates\BillingAdjustments\PopulationBillingCalculator as Calc;
use Carbon\CarbonImmutable;

/**
 * Motor puro del ajuste por población: sin base de datos ni contenedor.
 */
function billingMovement(string $kind, float $annual = 163.0, int $id = 1): array
{
    return ['item_id' => $id, 'affiliate_id' => $id, 'kind' => $kind, 'movement' => $kind === Calc::KIND_ADD ? 'Alta nueva' : 'Baja', 'name' => 'PERSONA '.$id, 'document' => (string) $id, 'annual_cents' => (int) round($annual * 100)];
}

/**
 * @param  array<int, string>  $states  índice de período => estado
 */
function billingInstallments(CarbonImmutable $start, int $months, array $states, int $amountCents = 10_863_950): array
{
    $rows = [];

    foreach ($states as $index => $state) {
        $rows[$index] = ['period' => $index, 'id' => 100 + $index, 'invoice' => 'A-'.$index, 'due' => $start->addMonthsNoOverflow($index * $months), 'amount_cents' => $amountCents, 'persons' => 2681, 'state' => $state];
    }

    return $rows;
}

it('redondea la tarifa por cuota y la prorrata a centavos, mitad hacia arriba', function (): void {
    expect(Calc::periodCents(16_300, 4))->toBe(4_075)
        ->and(Calc::periodCents(10_000, 12))->toBe(833)
        ->and(Calc::periodCents(10_001, 2))->toBe(5_001)
        ->and(Calc::prorateCents(4_075, 36, 92))->toBe(1_595)
        ->and(Calc::prorateCents(4_075, 92, 92))->toBe(4_075)
        ->and(Calc::prorateCents(4_075, 200, 92))->toBe(4_075)
        ->and(Calc::prorateCents(4_075, 0, 92))->toBe(0)
        ->and(Calc::prorateCents(0, 10, 92))->toBe(0);
});

it('arma los períodos del ciclo con fin exclusivo, también con fin de mes y año bisiesto', function (): void {
    $periods = Calc::periods(CarbonImmutable::create(2026, 8, 26), 3, 4);

    expect(array_map(fn (array $p): string => $p['start']->format('d/m/Y').'-'.$p['days'], $periods))
        ->toBe(['26/08/2026-92', '26/11/2026-92', '26/02/2027-89', '26/05/2027-92']);

    $monthly = Calc::periods(CarbonImmutable::create(2028, 1, 31), 1, 12);

    expect($monthly[1]['start']->format('d/m/Y'))->toBe('29/02/2028')
        ->and($monthly[0]['days'])->toBe(29);
});

it('caso MAPFRE: altas cobran por días el período inicial y las cuotas por pagar suman la diferencia neta', function (): void {
    $start = CarbonImmutable::create(2026, 8, 26);
    $movements = [
        ...array_map(fn (int $i): array => billingMovement(Calc::KIND_ADD, 163, $i), range(1, 24)),
        ...array_map(fn (int $i): array => billingMovement(Calc::KIND_REMOVE, 163, 100 + $i), range(1, 4)),
    ];
    $installments = billingInstallments($start, 3, [1 => Calc::STATE_PENDING, 2 => Calc::STATE_PENDING, 3 => Calc::STATE_PENDING]);

    $result = Calc::calculate(CarbonImmutable::create(2026, 10, 21), $movements, Calc::periods($start, 3, 4), $installments, 4);

    expect($result['charge_cents'])->toBe(24 * 1_595)
        ->and($result['installment_delta_cents'])->toBe(20 * 4_075)
        ->and($result['persons_added'])->toBe(24)
        ->and($result['persons_removed'])->toBe(4)
        ->and(array_column($result['adjusted'], 'after_cents'))->toBe([10_945_450, 10_945_450, 10_945_450])
        ->and(array_column($result['adjusted'], 'persons_after'))->toBe([2701, 2701, 2701])
        ->and($result['charged_periods'][0]['covered_days'])->toBe(36)
        ->and($result['charged_periods'][0]['state'])->toBe(Calc::STATE_INITIAL)
        ->and($result['lines'][0]['charge_cents'])->toBe(1_595)
        ->and($result['lines'][0]['covered_until']->format('d/m/Y'))->toBe('25/11/2026')
        ->and(array_sum(array_column($result['lines'], 'charge_cents')))->toBe($result['charge_cents']);
});

it('si la fecha efectiva es el inicio de una cuota por pagar no cobra aparte: todo va en la cuota', function (): void {
    $start = CarbonImmutable::create(2026, 8, 26);
    $installments = billingInstallments($start, 3, [1 => Calc::STATE_PENDING, 2 => Calc::STATE_PENDING, 3 => Calc::STATE_PENDING]);

    $result = Calc::calculate(CarbonImmutable::create(2026, 11, 26), [billingMovement(Calc::KIND_ADD)], Calc::periods($start, 3, 4), $installments, 4);

    expect($result['charge_cents'])->toBe(0)
        ->and($result['charged_periods'])->toBe([])
        ->and(array_column($result['adjusted'], 'invoice'))->toBe(['A-1', 'A-2', 'A-3'])
        ->and($result['installment_delta_cents'])->toBe(4_075);
});

it('una cuota futura ya pagada por adelantado se cobra completa a las altas y no se toca su monto', function (): void {
    $start = CarbonImmutable::create(2026, 8, 26);
    $installments = billingInstallments($start, 3, [1 => Calc::STATE_PAID, 2 => Calc::STATE_PENDING, 3 => Calc::STATE_PENDING]);

    $result = Calc::calculate(CarbonImmutable::create(2026, 10, 21), [billingMovement(Calc::KIND_ADD)], Calc::periods($start, 3, 4), $installments, 4);

    expect(array_column($result['charged_periods'], 'covered_days'))->toBe([36, 92])
        ->and($result['charge_cents'])->toBe(1_595 + 4_075)
        ->and(array_column($result['adjusted'], 'invoice'))->toBe(['A-2', 'A-3']);
});

it('una cuota ya en curso y sin pagar conserva su monto: el alta se cobra aparte por los días que faltan', function (): void {
    $start = CarbonImmutable::create(2026, 8, 26);
    $installments = billingInstallments($start, 3, [1 => Calc::STATE_PENDING, 2 => Calc::STATE_PENDING, 3 => Calc::STATE_PENDING]);

    $result = Calc::calculate(CarbonImmutable::create(2026, 12, 10), [billingMovement(Calc::KIND_ADD)], Calc::periods($start, 3, 4), $installments, 4);

    expect($result['charged_periods'][0]['period'])->toBe(1)
        ->and($result['charged_periods'][0]['state'])->toBe(Calc::STATE_PENDING)
        ->and($result['charged_periods'][0]['covered_days'])->toBe(78)
        ->and($result['charge_cents'])->toBe(Calc::prorateCents(4_075, 78, 92))
        ->and(array_column($result['adjusted'], 'invoice'))->toBe(['A-2', 'A-3'])
        ->and(collect($result['untouched'])->pluck('period')->all())->toBe([0]);
});

it('las bajas no generan crédito: solo bajan las cuotas por pagar', function (): void {
    $start = CarbonImmutable::create(2026, 8, 26);
    $installments = billingInstallments($start, 3, [1 => Calc::STATE_PENDING, 2 => Calc::STATE_PENDING, 3 => Calc::STATE_PENDING]);

    $result = Calc::calculate(CarbonImmutable::create(2026, 10, 21), [billingMovement(Calc::KIND_REMOVE), billingMovement(Calc::KIND_REMOVE, 163, 2)], Calc::periods($start, 3, 4), $installments, 4);

    expect($result['charge_cents'])->toBe(0)
        ->and($result['installment_delta_cents'])->toBe(-8_150)
        ->and($result['lines'][0]['charge_cents'])->toBe(0)
        ->and($result['adjusted'][0]['after_cents'])->toBe(10_863_950 - 8_150)
        ->and($result['adjusted'][0]['persons_after'])->toBe(2679);
});

it('una alta y una baja con la misma tarifa dejan las cuotas igual y solo cobran los días del alta', function (): void {
    $start = CarbonImmutable::create(2026, 8, 26);
    $installments = billingInstallments($start, 3, [1 => Calc::STATE_PENDING, 2 => Calc::STATE_PENDING, 3 => Calc::STATE_PENDING]);

    $result = Calc::calculate(CarbonImmutable::create(2026, 10, 21), [billingMovement(Calc::KIND_ADD), billingMovement(Calc::KIND_REMOVE, 163, 2)], Calc::periods($start, 3, 4), $installments, 4);

    expect($result['installment_delta_cents'])->toBe(0)
        ->and($result['charge_cents'])->toBe(1_595)
        ->and($result['adjusted'][0]['after_cents'])->toBe(10_863_950);
});

it('en frecuencia anual el período único ya está pagado: solo hay cobro aparte', function (): void {
    $start = CarbonImmutable::create(2026, 1, 1);

    $result = Calc::calculate(CarbonImmutable::create(2026, 7, 2), [billingMovement(Calc::KIND_ADD, 365)], Calc::periods($start, 12, 1), [], 1);

    expect($result['charged_periods'][0]['days'])->toBe(365)
        ->and($result['charged_periods'][0]['covered_days'])->toBe(183)
        ->and($result['charge_cents'])->toBe(18_300)
        ->and($result['adjusted'])->toBe([])
        ->and($result['installment_delta_cents'])->toBe(0);
});

it('formatea los montos en español con signo', function (): void {
    expect(Calc::money(38_280))->toBe('US$ 382,80')
        ->and(Calc::money(-8_150))->toBe('−US$ 81,50')
        ->and(Calc::money(10_945_450))->toBe('US$ 109.454,50');
});
