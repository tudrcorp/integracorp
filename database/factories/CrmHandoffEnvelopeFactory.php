<?php

namespace Database\Factories;

use App\Models\CrmHandoffEnvelope;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CrmHandoffEnvelope>
 */
class CrmHandoffEnvelopeFactory extends Factory
{
    protected $model = CrmHandoffEnvelope::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $motivo = 'pide asesor';

        return [
            'handoff_id' => (string) fake()->unique()->numberBetween(100000, 999999999),
            'phone' => '58412'.fake()->numerify('#######'),
            'area' => 'comercial',
            'motivo' => $motivo,
            'payload' => [
                'name' => fake()->name(),
                'motivo' => $motivo,
                'necesidad' => 'plan familiar',
                'ultimo_mensaje' => 'quiero hablar con una persona',
                'cotizacion' => [
                    'control' => '0009012',
                    'total' => 120,
                ],
                'mensajes' => [
                    [
                        'direction' => 'in',
                        'text' => 'quiero hablar con una persona',
                        'hora' => '19:00',
                    ],
                ],
            ],
            'accepted_at' => now(),
        ];
    }
}
