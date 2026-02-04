<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProspectAgentTask extends Model
{
    protected $table = 'prospect_agent_tasks';
    
    protected $fillable = [
        'prospect_agent_id',
        'task',
        'created_by',
        'updated_by',
        'status',
    ];

    public function prospect_agent()
    {
        return $this->belongsTo(ProspectAgent::class);
    }

    public function created_by()
    {
        return $this->belongsTo(User::class);
    }

    public function updated_by()
    {
        return $this->belongsTo(User::class);
    }
}
