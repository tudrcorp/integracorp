<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelemedicineListStudy extends Model
{
    protected $table = 'telemedicine_list_studies';

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
