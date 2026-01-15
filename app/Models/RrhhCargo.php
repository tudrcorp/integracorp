<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RrhhCargo extends Model
{
    //
    protected $table = "rrhh_cargos";

    protected $fillable = [
        "departamento_id",
        "description",
        "created_by",
        "updated_by",
    ];

    public function departamento()
    {
        return $this->belongsTo(RrhhDepartamento::class);
    }
}
