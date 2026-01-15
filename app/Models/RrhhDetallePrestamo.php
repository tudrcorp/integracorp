<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RrhhDetallePrestamo extends Model
{
    //
    protected $table = "rrhh_detalle_prestamos";

    protected $fillable = [
        "colaborador_id",
        "prestamo_id",
        "nro_cuota_cancelada",
        "monto_cuota",
        "saldo_deudor",
        "created_by",
    ];


    public function prestamo()
    {
        return $this->belongsTo(RrhhPrestamo::class);
    }

    public function colaborador()
    {
        return $this->belongsTo(RrhhColaborador::class);
    }
}
