<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\OperationNonCoveredMedicationAlert;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OperationNonCoveredMedicationAlert>
 */
class OperationNonCoveredMedicationAlertFactory extends Factory
{
    protected $model = OperationNonCoveredMedicationAlert::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'operation_coordination_service_id' => fake()->unique()->numberBetween(1, 9_999_999),
            'telemedicine_case_id' => fake()->numberBetween(1, 99_999),
            'telemedicine_patient_id' => fake()->numberBetween(1, 99_999),
            'plan_id' => 3,
            'coverage_id' => 9,
            'coverage_amount' => 40000,
            'patient_name' => fake()->name(),
            'patient_document' => (string) fake()->numberBetween(5_000_000, 35_000_000),
            'plan_name' => 'PLAN ESPECIAL',
            'medication_ids' => [fake()->numberBetween(1, 99_999)],
            'status' => OperationNonCoveredMedicationAlert::STATUS_PENDING,
        ];
    }

    public function managed(): static
    {
        return $this->state(fn (): array => [
            'status' => OperationNonCoveredMedicationAlert::STATUS_MANAGED,
            'managed_at' => now(),
        ]);
    }
}
