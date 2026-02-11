<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProspectAgentObservation extends Model
{
    protected $table = 'prospect_agent_observations';
    
    protected $fillable = [
        'prospect_agent_id',
        'prospect_agent_task_id',
        'observation',
        'created_by',
    ];

    public function prospect_agent()
    {
        return $this->belongsTo(ProspectAgent::class);
    }

    public function created_by()
    {
        return $this->belongsTo(User::class);
    }

    public function prospect_agent_task()
    {
        return $this->belongsTo(ProspectAgentTask::class);
    }

}
