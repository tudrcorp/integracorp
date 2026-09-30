<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TelemedicineAmdPhysicalExam;
use App\Support\Telemedicine\TelemedicineAmdPhysicalExamTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Examen físico AMD con los hallazgos normales de la plantilla. Los ids de
 * paciente, caso y consulta los fija quien lo usa.
 *
 * @extends Factory<TelemedicineAmdPhysicalExam>
 */
class TelemedicineAmdPhysicalExamFactory extends Factory
{
    protected $model = TelemedicineAmdPhysicalExam::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            ...TelemedicineAmdPhysicalExamTemplate::defaults(),
            'heart_rate' => (string) fake()->numberBetween(60, 100),
            'respiratory_rate' => (string) fake()->numberBetween(12, 20),
            'blood_pressure' => fake()->numberBetween(100, 130).'/'.fake()->numberBetween(60, 85),
            'pulse' => (string) fake()->numberBetween(60, 100),
            'oxygen_saturation' => (string) fake()->numberBetween(94, 100),
        ];
    }
}
