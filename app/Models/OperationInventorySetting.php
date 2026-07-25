<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationInventorySetting extends Model
{
    protected $table = 'operation_inventory_settings';

    protected $fillable = [
        'low_stock_threshold',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'low_stock_threshold' => 'integer',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(
            [],
            [
                'low_stock_threshold' => 3,
            ],
        );
    }

    public function lowStockThreshold(): int
    {
        return max(0, (int) $this->low_stock_threshold);
    }
}
