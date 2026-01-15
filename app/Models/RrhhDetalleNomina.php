<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RrhhDetalleNomina extends Model
{
    //
    protected $table = "rrhh_detalle_nominas";

    protected $fillable = [
        "colaborador_id",
        "nomina_id",
        "cargo_id",
        "departamento_id",
        "salario",
        "monto_descuento",
        "monto_bono",
        "monto_prestamo",
        "nro_cuota_cancelada",
        "monto_otros",
        "monto_total",
        "created_by",
    ];

    public function nomina()
    {
        return $this->belongsTo(RrhhNomina::class);
    }

    public function colaborador()
    {
        return $this->belongsTo(RrhhColaborador::class);
    }

    public function cargo()
    {
        return $this->belongsTo(RrhhCargo::class);
    }

    public function departamento()
    {
        return $this->belongsTo(RrhhDepartamento::class);
    }
}
