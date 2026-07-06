<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\HelpdeskTeamMembersCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'updated_by',
        'observation',
        'latest_note_at',
        'latest_note_by',
        'cc_colaboradores',
        'ticket_type',
        'team',
        'team_members',
    ];

    protected function casts(): array
    {
        return [
            'cc_colaboradores' => 'array',
            'team_members' => HelpdeskTeamMembersCast::class,
            'latest_note_at' => 'datetime',
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

    public function help_desk_category(): BelongsTo
    {
        return $this->belongsTo(HelpDeskCategory::class);
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
