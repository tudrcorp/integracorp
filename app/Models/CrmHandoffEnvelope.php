<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\CrmInbox\CrmInboxPulse;
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
        'outcome',
        'outcome_note',
        'outcome_by',
        'outcome_at',
        'follow_up_at',
        'follow_up_note',
        'follow_up_by',
        'follow_up_notified_at',
        'sla_alerted_at',
        'stale_alerted_at',
        'first_reply_at',
    ];

    /**
     * Cualquier cambio en un caso despierta a las bandejas abiertas sin que consulten la base.
     */
    protected static function booted(): void
    {
        static::saved(fn () => CrmInboxPulse::bump());
        static::deleted(fn () => CrmInboxPulse::bump());
    }

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
            'outcome_at' => 'datetime',
            'follow_up_at' => 'datetime',
            'follow_up_notified_at' => 'datetime',
            'sla_alerted_at' => 'datetime',
            'stale_alerted_at' => 'datetime',
            'first_reply_at' => 'datetime',
        ];
    }
}
