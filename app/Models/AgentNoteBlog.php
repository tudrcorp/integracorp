<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentNoteBlog extends Model
{
    protected $table = 'agent_note_blogs';

    protected $fillable = ['agent_id', 'note', 'created_by'];

    public function agency()
    {
        return $this->belongsTo(Agent::class);
    }
}