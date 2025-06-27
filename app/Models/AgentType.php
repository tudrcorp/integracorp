<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentType extends Model
{
    protected $table = 'agent_types';

    protected $fillable = [
        'id',
        'code',
        'definition',
        'status',
        'created_by'
    ];
}