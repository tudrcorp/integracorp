<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RrhhNomina extends Model
{
    //
    protected $table = "rrhh_nominas";

    protected $fillable = [
        "total_salarios",
        "total_descuentos",
        "total_asignaciones",
        "total_neto",
        "created_by",
    ];

    public function detalleNomina()
    {
        return $this->hasMany(RrhhDetalleNomina::class);
    }
}
