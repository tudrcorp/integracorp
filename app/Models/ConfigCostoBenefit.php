<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfigCostoBenefit extends Model
{
    protected $table = 'config_costo_benefits';

    protected $fillable = [
        'porcen_comision',
        'porcen_utilidad',
        'porcen_acu_adi',
    ];
}
