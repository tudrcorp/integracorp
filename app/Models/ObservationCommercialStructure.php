<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ObservationCommercialStructure extends Model
{
    //
    protected $table = 'observation_commercial_structures';

    protected $fillable = [
        'agency_id',
        'agent_id',
        'travel_agency_id',
        'travel_agent_id',
        'commercial_structure_id',
        'observation',
        'created_by',
        'date',
    ];

    public function observationAgency()
    {
        return $this->belongsTo(Agency::class);
    }

    public function observationAgent()
    {
        return $this->belongsTo(Agent::class);
    }

    public function observationTravelAgency()
    {
        return $this->belongsTo(TravelAgency::class);
    }

    public function observationTravelAgent()
    {
        return $this->belongsTo(TravelAgent::class);
    }

}
