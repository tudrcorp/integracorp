<?php

namespace Database\Factories;

use App\Models\Fee;
use App\Models\WhiteCompany;
use App\Models\WhiteCompanyFee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhiteCompanyFee>
 */
class WhiteCompanyFeeFactory extends Factory
{
    protected $model = WhiteCompanyFee::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'white_company_id' => WhiteCompany::query()->value('id') ?? 1,
            'fee_id' => Fee::query()->value('id') ?? 1,
            'sale_price' => 180,
            'neta' => 96,
            'status' => 'ACTIVO',
            'created_by' => 'test',
        ];
    }
}
