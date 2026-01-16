<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TravelAgent extends Model
{
    //
    protected $table = "travel_agents";

    protected $fillable = [
        "name",
        "email",
        "phone",
        "cargo",
        "fechaNacimiento",
        "created_by",
        "updated_by",
        "travel_agency_id",
    ];

    public function travelAgency()
    {
        return $this->belongsTo(TravelAgency::class);
    }
}
