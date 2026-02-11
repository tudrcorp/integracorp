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

    public function created_by()
    {
        return $this->belongsTo(User::class);
    }

    public function updated_by()
    {
        return $this->belongsTo(User::class);
    }

    public function prospect_agent_tasks()
    {
        return $this->hasMany(ProspectAgentTask::class);
    }

    public function prospect_agent_observations()
    {
        return $this->hasMany(ProspectAgentObservation::class);
    }
}
