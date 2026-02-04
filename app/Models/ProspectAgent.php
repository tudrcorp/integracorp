<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProspectAgent extends Model
{
    protected $table = 'prospect_agents';
    protected $fillable = [
        'name',
        'type',
        'phone_1',
        'phone_2',
        'email',
        'state_id',
        'city_id',
        'country_id',
        'status',
        'created_by',
        'updated_by',
        'reference_by',
    ];

    public function prospect_agent_observations()
    {
        return $this->hasMany(ProspectAgentObservation::class)->orderBy('created_at', 'desc');
    }

    public function prospect_agent_tasks()
    {
        return $this->hasMany(ProspectAgentTask::class)->orderBy('created_at', 'desc');
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function created_by()
    {
        return $this->belongsTo(User::class);
    }

    public function updated_by()
    {
        return $this->belongsTo(User::class);
    }

    public function reference_by()
    {
        return $this->belongsTo(User::class);
    }
}
