<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RrhhDeduccion extends Model
{
    //
    protected $table = "rrhh_deduccions";

    protected $fillable = [
        "name",
        "description",
        "monto",
        "cargo_id",
        "created_by",
        "updated_by",
    ];

    public function cargo()
    {
        return $this->belongsTo(RrhhCargo::class);
    }
}
