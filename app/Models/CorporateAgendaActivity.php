<?php

namespace App\Models;

use App\Enums\CorporateAgendaActivityType;
use App\Enums\CorporateAgendaDepartment;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CorporateAgendaActivity extends Model
{
    protected $fillable = [
        'creator_user_id',
        'activity_date',
        'start_time',
        'end_time',
        'activity_type',
        'department',
        'has_google_meet',
        'google_meet_url',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'activity_date' => 'date',
            'start_time' => 'string',
            'end_time' => 'string',
            'activity_type' => CorporateAgendaActivityType::class,
            'department' => CorporateAgendaDepartment::class,
            'has_google_meet' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_user_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(CorporateAgendaActivityParticipant::class, 'activity_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(CorporateAgendaActivityNote::class, 'activity_id');
    }

    protected function shortDescription(): Attribute
    {
        return Attribute::get(function (): string {
            $plain = trim(html_entity_decode(strip_tags((string) $this->description), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            if (mb_strlen($plain) <= 52) {
                return $plain;
            }

            return mb_substr($plain, 0, 49).'...';
        });
    }
}
