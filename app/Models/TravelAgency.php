<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TravelAgency extends Model
{
    //
    protected $table = "travel_agencies";

    protected $fillable = [
        'status',
        'fechaIngreso',
        'representante',
        'idRepresentante',
        'FechaNacimientoRepresentante',
        'name',
        'typeIdentification',
        'numberIdentification',
        'userPortalWeb',
        'aniversary',
        'country',
        'state',
        'city',
        'address',
        'phone',
        'phoneAdditional',
        'email',
        'userInstagram',
        'classification',
        'comision',
        'montoCreditoAprobado',
        'nivel',
        'agenteSuperiorNivel3',
        'agenciaSuperiorNivel2',
        'agenciaPpalNivel1',
        'createdBy',
        'updatedBy',
    ];

    public function travelAgents()
    {
        return $this->hasMany(TravelAgent::class);
    }
}
