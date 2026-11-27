<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Operations\RetailMainServiceCatalog;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio principal que se ofrece en el registro RETAIL. `name` es la clave guardada en las coordinaciones;
 * `label` es lo que ve el analista.
 */
class RetailMainService extends Model
{
    protected $fillable = [
        'name',
        'label',
        'is_active',
        'is_protected',
        'sort',
        'created_by',
        'updated_by',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => RetailMainServiceCatalog::flush());
        static::deleted(fn () => RetailMainServiceCatalog::flush());
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_protected' => 'boolean',
            'sort' => 'integer',
        ];
    }
}
