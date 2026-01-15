<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RrhhPrestamo extends Model
{
    //
    protected $table = "rrhh_prestamos";

    protected $fillable = [
        "colaborador_id",
        "descripcion",
        "monto",
        "interes",
        "nro_cuotas",
        "nro_cuota_cancelada",
        "monto_cuota",
        "saldo",
        "status",
        "created_by",
    ];

    public function colaborador()
    {
        return $this->belongsTo(RrhhColaborador::class);
    }
}
