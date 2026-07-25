<?php

namespace Database\Seeders;

use App\Models\OperationInventoryProductCategory;
use Illuminate\Database\Seeder;

class OperationInventoryProductCategorySeeder extends Seeder
{
    /**
     * @var list<array{name: string, description: string}>
     */
    private const CATEGORIES = [
        [
            'name' => 'Medicamento',
            'description' => 'Productos farmacéuticos y medicamentos.',
        ],
        [
            'name' => 'Equipo Medico',
            'description' => 'Equipos e insumos médicos.',
        ],
        [
            'name' => 'Mobiliario',
            'description' => 'Mobiliario clínico y de operaciones.',
        ],
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $category) {
            OperationInventoryProductCategory::query()->updateOrCreate(
                ['name' => $category['name']],
                [
                    'description' => $category['description'],
                    'is_active' => true,
                    'created_by' => 'system',
                ],
            );
        }
    }
}
