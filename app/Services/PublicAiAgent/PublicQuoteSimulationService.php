<?php

declare(strict_types=1);

namespace App\Services\PublicAiAgent;

use App\Models\Affiliation;
use App\Support\AffiliationAffiliateFeeCalculator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PublicQuoteSimulationService
{
    public function __construct(
        private readonly AffiliationAffiliateFeeCalculator $feeCalculator,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *   plan_id: int,
     *   coverage_id: int|null,
     *   lines: array<int, array<string, mixed>>,
     *   totals: array{annual: float, semiannual: float, quarterly: float, monthly: float},
     *   assumptions: array<int, string>
     * }
     *
     * @throws ValidationException
     */
    public function simulate(array $payload): array
    {
        $validator = Validator::make($payload, [
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'coverage_id' => [
                Rule::requiredIf(fn (): bool => (int) ($payload['plan_id'] ?? 0) !== 1),
                'nullable',
                'integer',
                'exists:coverages,id',
            ],
            'members' => ['required', 'array', 'min:1'],
            'members.*.age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'members.*.birth_date' => ['nullable', 'string', 'max:20'],
            'members.*.quantity' => ['nullable', 'integer', 'min:1', 'max:300'],
            'members.*.total_persons' => ['nullable', 'integer', 'min:1', 'max:300'],
        ], [
            'coverage_id.required' => 'La cobertura es obligatoria para este plan.',
        ]);

        $validated = $validator->validate();
        $planId = (int) $validated['plan_id'];
        $coverageId = isset($validated['coverage_id']) ? (int) $validated['coverage_id'] : null;

        $affiliation = new Affiliation([
            'plan_id' => $planId,
            'coverage_id' => $coverageId,
            'payment_frequency' => 'ANUAL',
        ]);

        $lines = [];
        $totalAnnual = 0.0;

        foreach ((array) $validated['members'] as $index => $member) {
            $age = $this->resolveAge($member, $index);
            $quantity = (int) ($member['quantity'] ?? $member['total_persons'] ?? 1);

            $fee = $this->feeCalculator->resolveFeeForAffiliateAge($affiliation, $age);

            if ($fee === null) {
                throw ValidationException::withMessages([
                    "members.{$index}.age" => 'No existe tarifa para la edad suministrada con el plan/cobertura seleccionada.',
                ]);
            }

            $annualPerPerson = round((float) $fee->price, 2);
            $subtotalAnnual = round($annualPerPerson * $quantity, 2);
            $subtotalSemiannual = round($subtotalAnnual / 2, 2);
            $subtotalQuarterly = round($subtotalAnnual / 4, 2);
            $subtotalMonthly = round($subtotalAnnual / 12, 2);

            $lines[] = [
                'line' => $index + 1,
                'age' => $age,
                'quantity' => $quantity,
                'age_range_id' => $fee->age_range_id !== null ? (int) $fee->age_range_id : null,
                'coverage_id' => $fee->coverage_id !== null ? (int) $fee->coverage_id : null,
                'annual_per_person' => $annualPerPerson,
                'subtotal_annual' => $subtotalAnnual,
                'subtotal_semiannual' => $subtotalSemiannual,
                'subtotal_quarterly' => $subtotalQuarterly,
                'subtotal_monthly' => $subtotalMonthly,
            ];

            $totalAnnual += $subtotalAnnual;
        }

        $totalAnnual = round($totalAnnual, 2);

        return [
            'plan_id' => $planId,
            'coverage_id' => $coverageId,
            'lines' => $lines,
            'totals' => [
                'annual' => $totalAnnual,
                'semiannual' => round($totalAnnual / 2, 2),
                'quarterly' => round($totalAnnual / 4, 2),
                'monthly' => round($totalAnnual / 12, 2),
            ],
            'assumptions' => [
                'Montos expresados en base anual y prorrateados por frecuencia.',
                'Las tarifas se calculan con la tabla de fees activa para el plan y cobertura seleccionados.',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $member
     *
     * @throws ValidationException
     */
    private function resolveAge(array $member, int $index): int
    {
        if (isset($member['age']) && $member['age'] !== null && $member['age'] !== '') {
            return (int) $member['age'];
        }

        $birthDate = (string) ($member['birth_date'] ?? '');
        if ($birthDate !== '') {
            return $this->resolveAgeFromBirthDate($birthDate, $index);
        }

        throw ValidationException::withMessages([
            "members.{$index}" => 'Cada miembro requiere una edad o fecha de nacimiento.',
        ]);
    }

    /**
     * @throws ValidationException
     */
    private function resolveAgeFromBirthDate(string $birthDate, int $index): int
    {
        $normalizedBirthDate = trim($birthDate);

        foreach (['d/m/Y', 'Y-m-d', 'd-m-Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $normalizedBirthDate)->age;
            } catch (\Throwable) {
                continue;
            }
        }

        throw ValidationException::withMessages([
            "members.{$index}.birth_date" => 'Formato de fecha inválido. Use d/m/Y o Y-m-d.',
        ]);
    }
}
