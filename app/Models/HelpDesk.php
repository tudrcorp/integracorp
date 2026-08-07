<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\HelpdeskTeamMembersCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class HelpDesk extends Model
{
    protected $table = 'help_desks';

    protected $fillable = [
        'uid',
        'description',
        'image',
        'priority',
        'status',
        'created_by',
        'created_by_user_id',
        'updated_by',
        'observation',
        'latest_note_at',
        'latest_note_by',
        'latest_note_by_user_id',
        'cc_colaboradores',
        'ticket_type',
        'team',
        'team_members',
        'terms_accepted_at',
        'first_response_due_at',
        'resolution_due_at',
        'first_responded_at',
        'resolved_at',
        'cancelled_at',
        'sla_breached_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'cc_colaboradores' => 'array',
            'team_members' => HelpdeskTeamMembersCast::class,
            'latest_note_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'first_response_due_at' => 'datetime',
            'resolution_due_at' => 'datetime',
            'first_responded_at' => 'datetime',
            'resolved_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'sla_breached_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (HelpDesk $helpDesk): void {
            if (filled($helpDesk->uid)) {
                return;
            }

            $helpDesk->uid = static::generateUniqueUid();
        });
    }

    private static function generateUniqueUid(): string
    {
        do {
            $uid = 'TK-'.Str::upper((string) Str::ulid());
        } while (static::query()->where('uid', $uid)->exists());

        return $uid;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(HelpDeskEvent::class, 'help_desk_id')->orderByDesc('occurred_at');
    }

    public function csat(): HasOne
    {
        return $this->hasOne(HelpDeskCsat::class, 'help_desk_id');
    }

    public function noteReads(): HasMany
    {
        return $this->hasMany(HelpDeskNoteRead::class, 'help_desk_id');
    }

    /**
     * Colaboradores a los que se asignó el ticket (uno o varios).
     */
    public function rrhhColaboradores(): BelongsToMany
    {
        return $this->belongsToMany(RrhhColaborador::class, 'help_desk_rrhh_colaborador')
            ->withTimestamps();
    }
}
