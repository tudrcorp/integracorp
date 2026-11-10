<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class TelemedicineListSpecialist extends Model
{
    protected $table = 'telemedicine_list_specialists';

    protected $fillable = [
        'name',
        'type',
        'type_two',
        'cost',
        'profit_percentage',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cost' => 'decimal:2',
            'profit_percentage' => 'decimal:2',
        ];
    }

    /**
     * @return Collection<string, string>
     */
    public static function uncoveredNames(): Collection
    {
        $query = static::query()->where('type', 'NO CUBIERTO');

        if (Schema::hasColumn((new static)->getTable(), 'type_two')) {
            $query->orWhere('type_two', 'NO CUBIERTO');
        }

        return $query->pluck('name', 'name');
    }
}
