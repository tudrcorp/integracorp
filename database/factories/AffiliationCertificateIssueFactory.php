<?php

namespace Database\Factories;

use App\Models\AffiliationCertificateIssue;
use App\Support\Affiliations\Certificates\CertificateVerificationKey;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AffiliationCertificateIssue>
 */
class AffiliationCertificateIssueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $from = now()->subMonths(2)->startOfDay();

        return [
            'verification_key' => CertificateVerificationKey::generate(),
            'affiliation_type' => AffiliationCertificateIssue::TYPE_INDIVIDUAL,
            'affiliation_id' => fake()->numberBetween(1, 9999),
            'affiliation_code' => 'TDEC-IND-'.fake()->numerify('######'),
            'plan_name' => 'PLAN IDEAL',
            'valid_from' => $from->toDateString(),
            'valid_until' => $from->copy()->addYear()->toDateString(),
            'paid_until' => $from->copy()->addMonths(3)->toDateString(),
            'current_period_paid' => true,
            'affiliates_count' => 1,
            'carnets_count' => 1,
            'issued_by' => null,
            'issued_by_name' => fake()->name(),
        ];
    }
}
