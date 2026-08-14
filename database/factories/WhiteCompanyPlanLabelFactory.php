<?php

namespace Database\Factories;

use App\Models\WhiteCompany;
use App\Models\WhiteCompanyPlanLabel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhiteCompanyPlanLabel>
 */
class WhiteCompanyPlanLabelFactory extends Factory
{
    protected $model = WhiteCompanyPlanLabel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'white_company_id' => WhiteCompany::query()->value('id') ?? 1,
            'plan_id' => 3,
            'display_name' => 'Plan Bienestar',
            'short_label' => 'BIENESTAR',
        ];
    }
}
