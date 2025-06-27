<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentDocument extends Model
{
    protected $table = 'agent_documents';

    protected $fillable = [
        'agent_id',
        'title',
        'document',
        'image',
    ];

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }
}