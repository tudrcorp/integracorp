<?php

namespace Database\Factories;

use App\Enums\CreditReconciliationEntityType;
use App\Models\CreditReconciliation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditReconciliation>
 */
class CreditReconciliationFactory extends Factory
{
    protected $model = CreditReconciliation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entity_type' => CreditReconciliationEntityType::WhiteCompany,
            'affiliation_kind' => 'individual',
            'affiliation_code' => 'AFF-'.$this->faker->unique()->numerify('####'),
            'affiliation_information' => 'Titular de prueba',
            'affiliates_count' => 1,
            'annual_amount' => 1200,
            'total_to_pay' => 300,
            'payment_frequency' => 'TRIMESTRAL',
            'collection_invoice_number' => 'ADP-'.$this->faker->numerify('###'),
            'plan_type' => 'BASICO',
            'created_by' => 'factory',
        ];
    }
}
