<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RrhhAsignacion extends Model
{
    //
    protected $table = "rrhh_asignacions";

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
