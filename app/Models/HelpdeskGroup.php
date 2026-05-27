<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HelpdeskGroup extends Model
{
    protected $table = 'helpdesk_groups';

    protected $fillable = [
        'name',
        'status',
        'total_tickets_assigned',
        'team_members',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'team_members' => 'array',
        'total_tickets_assigned' => 'integer',
    ];
}
