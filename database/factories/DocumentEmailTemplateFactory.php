<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DocumentEmailAudience;
use App\Enums\DocumentEmailType;
use App\Models\DocumentEmailTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentEmailTemplate>
 */
class DocumentEmailTemplateFactory extends Factory
{
    protected $model = DocumentEmailTemplate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document' => DocumentEmailType::ReceiptPayment,
            'name' => 'Copy '.fake()->unique()->words(3, true),
            'audience' => DocumentEmailAudience::Affiliate,
            'subject' => 'Recibo de pago Nro. {nro_recibo}',
            'body' => '<p>Estimado(a) {afiliado}:</p><p>'.fake()->sentence().'</p>',
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 0,
            'created_by' => 'factory',
            'updated_by' => 'factory',
        ];
    }

    public function collectionNotice(): static
    {
        return $this->state(fn (): array => [
            'document' => DocumentEmailType::CollectionNotice,
            'subject' => 'Aviso de cobro Nro. {nro_aviso}',
        ]);
    }

    public function forResponsible(): static
    {
        return $this->state(fn (): array => ['audience' => DocumentEmailAudience::Responsible]);
    }

    public function forBoth(): static
    {
        return $this->state(fn (): array => ['audience' => DocumentEmailAudience::Both]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
