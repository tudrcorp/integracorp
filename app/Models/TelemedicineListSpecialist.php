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
    ];

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
