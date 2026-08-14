<?php

namespace Database\Factories;

use App\Models\WhiteCompany;
use App\Models\WhiteCompanyPlanDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhiteCompanyPlanDocument>
 */
class WhiteCompanyPlanDocumentFactory extends Factory
{
    protected $model = WhiteCompanyPlanDocument::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'white_company_id' => WhiteCompany::query()->value('id') ?? 1,
            'plan_id' => 1,
            'kind' => WhiteCompanyPlanDocument::KIND_CONDICIONADO,
            'path' => 'white-companies/condicionados/'.$this->faker->uuid().'.pdf',
        ];
    }
}
