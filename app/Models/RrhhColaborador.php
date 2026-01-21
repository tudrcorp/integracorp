<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RrhhColaborador extends Model
{
    //
    protected $table = "rrhh_colaboradors";

    protected $fillable = [
        "fullName",
        "departmento_id",
        "cargo_id",
        "cedula",
        "sexo",
        "fechaNacimiento",
        "fechaIngreso",
        "telefono",
        "telefonoCorporativo",
        "emailCorporativo",
        "emailAlternativo",
        "emailPersonal",
        "direccion",
        "nroHijos",
        "nroHijoDependiente",
        "tallaCamisa",
        "banck_id",
        "nroCta",
        "codigoCta",
        "tipoCta",
        "status",
        "created_by",
        "updated_by",
    ];

    public function departamento()
    {
        return $this->belongsTo(RrhhDepartamento::class, 'departmento_id');
    }

    public function cargo()
    {
        return $this->belongsTo(RrhhCargo::class, 'cargo_id');
    }
}
