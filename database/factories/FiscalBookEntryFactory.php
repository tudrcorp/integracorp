<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FiscalBook;
use App\Models\FiscalBookEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FiscalBookEntry>
 */
class FiscalBookEntryFactory extends Factory
{
    protected $model = FiscalBookEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = (string) fake()->unique()->numberBetween(100000, 999999);
        $amount = fake()->randomFloat(2, 1000, 500000);

        return [
            'book' => FiscalBook::Sales,
            'period' => now()->startOfMonth()->toDateString(),
            'document_date' => now()->toDateString(),
            'document_type' => FiscalBookEntry::TYPE_INVOICE,
            'document_number' => $number,
            'document_number_key' => FiscalBookEntry::documentNumberKey($number),
            'control_number' => $number,
            'counterparty_name' => fake()->name(),
            'counterparty_rif' => 'V-'.fake()->numberBetween(5_000_000, 30_000_000),
            'taxpayer_type' => FiscalBookEntry::TAXPAYER,
            'status' => FiscalBookEntry::STATUS_ACTIVE,
            'total_amount' => $amount,
            'exempt_amount' => $amount,
            'source' => FiscalBookEntry::SOURCE_MANUAL,
        ];
    }

    public function purchases(): static
    {
        return $this->state(fn (): array => ['book' => FiscalBook::Purchases]);
    }
}
