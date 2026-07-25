<?php

namespace Database\Seeders;

use App\Enums\OperationInventoryProductPresentation;
use App\Models\OperationInventoryProduct;
use App\Models\OperationInventoryProductCategory;
use App\Services\OperationInventoryProductCodeGenerator;
use Illuminate\Database\Seeder;
use RuntimeException;

class OperationInventoryProductMobiliarioSeeder extends Seeder
{
    /**
     * @var list<string>
     */
    private const PRODUCTS = [
        'ALFOMBRAS',
        'ESPEJOS',
        'JABONERAS',
        'BOTELLAS PLASTICAS',
        'CORTINA DE BAÑO',
        'KIT DE BAÑO (2 TOALLAS GRANDES, 2 TOALLAS PEQUEÑAS, 1 ALFOMBRA)',
        '3 SABANAS INDIVIDUALES',
        'KIT DE CEPILLO Y CREMA DENTAL',
        'REFRIGERADOR MINI BAR MIKLEXUS DE 80LTS',
        'DISPENSADOR DE AGUA',
        'VENTILADOR 16 PULGADAS RECARGABLE',
        'MOUSE INALAMBRICO',
        'SOPORTE DE PAPEL HIGIENICO',
        'SOPORTE PARA ESPONJA Y JABON',
        'LAPTOP COMPAC',
        'PORTA LAPICEROS',
        'CONTENEDORES DE VIDRIO DE 3 PIEZAS',
        'GANCHOS DE ROPA',
        'PAPELERA CON SOPORTE DE PARED',
        'CLOSET NEGRO ARMABLE DOBLE',
        'CAMARA DE SEGURIDAD',
        'TELEFONO REDMI 15',
        'LAMPARA DE SEGURIDAD',
        'UPS GRANDE',
        'REGLETA',
    ];

    public function run(): void
    {
        $category = OperationInventoryProductCategory::query()
            ->where('name', 'Mobiliario')
            ->first();

        if ($category === null) {
            throw new RuntimeException('No existe la categoría Mobiliario.');
        }

        $codeGenerator = app(OperationInventoryProductCodeGenerator::class);

        foreach (self::PRODUCTS as $name) {
            $exists = OperationInventoryProduct::query()
                ->where('operation_inventory_product_category_id', $category->id)
                ->where('name', $name)
                ->exists();

            if ($exists) {
                continue;
            }

            OperationInventoryProduct::query()->create([
                'operation_inventory_product_category_id' => $category->id,
                'code' => $codeGenerator->next(),
                'name' => $name,
                'cost' => '0.00',
                'unit' => 'UNIDAD',
                'presentation' => OperationInventoryProductPresentation::Unidad->value,
                'is_active' => true,
                'created_by' => 'INTEGRACORP',
            ]);
        }
    }
}
