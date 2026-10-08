<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CrmHandoffEnvelopeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CrmHandoffEnvelope extends Model
{
    /** @use HasFactory<CrmHandoffEnvelopeFactory> */
    use HasFactory;

    protected $fillable = [
        'handoff_id',
        'phone',
        'area',
        'motivo',
        'payload',
        'accepted_at',
        'taken_at',
        'taken_by',
        'released_at',
        'closed_at',
        'closed_by',
        'assigned_to',
        'assigned_by',
        'assigned_at',
        'moved_by',
        'moved_at',
        'last_customer_at',
        'last_customer_text',
        'copilot_feedback',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'accepted_at' => 'datetime',
            'taken_at' => 'datetime',
            'released_at' => 'datetime',
            'closed_at' => 'datetime',
            'assigned_at' => 'datetime',
            'moved_at' => 'datetime',
            'last_customer_at' => 'datetime',
            'copilot_feedback' => 'array',
        ];
    }
}
