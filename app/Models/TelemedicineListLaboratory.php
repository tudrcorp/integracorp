<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelemedicineListLaboratory extends Model
{
    protected $table = 'telemedicine_list_laboratories';

    protected $fillable = [
        'name',
        'type',
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
}
