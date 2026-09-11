<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StatusCuentaPorPagar;
use App\Models\OperationAccountsPayable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OperationAccountsPayable>
 */
class OperationAccountsPayableFactory extends Factory
{
    protected $model = OperationAccountsPayable::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $invoiceDate = fake()->dateTimeBetween('-6 months', 'now');

        return [
            'invoice_date' => $invoiceDate,
            'invoice_registration_date' => fake()->dateTimeBetween($invoiceDate, 'now'),
            'invoice_number' => (string) fake()->numberBetween(1000, 999999),
            'invoice_control_number' => '00-'.fake()->numberBetween(10000, 99999),
            'supplier_id' => null,
            'supplier_name' => mb_strtoupper(fake()->company()),
            'supplier_rif' => 'J-'.fake()->numberBetween(10000000, 99999999).'-'.fake()->numberBetween(0, 9),
            'business_unit_id' => null,
            'invoice_amount' => fake()->randomFloat(2, 50, 5000),
            'invoice_currency' => 'USD',
            'payment_status' => StatusCuentaPorPagar::PendientePorPagar->value,
            'payment_reference' => null,
            'payment_date' => null,
            'national_bank' => null,
            'international_bank' => null,
            'payment_amount_usd' => null,
            'payment_amount_ves' => null,
            'observations' => null,
            'created_by' => 'system',
            'updated_by' => null,
        ];
    }

    public function enGestion(): self
    {
        return $this->state(fn (): array => [
            'payment_status' => StatusCuentaPorPagar::EnGestion->value,
        ]);
    }

    public function pagada(): self
    {
        return $this->state(function (array $attributes): array {
            $amount = (float) ($attributes['invoice_amount'] ?? 100);

            return [
                'payment_status' => StatusCuentaPorPagar::Pagada->value,
                'payment_reference' => (string) fake()->numberBetween(100000, 999999),
                'payment_date' => fake()->dateTimeBetween('-2 months', 'now'),
                'national_bank' => 'BANESCO',
                'payment_amount_usd' => $amount,
                'payment_amount_ves' => round($amount * 36.5, 2),
            ];
        });
    }
}
