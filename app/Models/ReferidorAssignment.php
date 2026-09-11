<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferidorAssignment extends Model
{
    protected $table = 'referidor_assignments';

    protected $fillable = [
        'assignment_key',
        'referrer_agency_id',
        'referrer_agent_id',
        'referred_agency_id',
        'referred_agent_id',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'referrer_agency_id' => 'integer',
            'referrer_agent_id' => 'integer',
            'referred_agency_id' => 'integer',
            'referred_agent_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $assignment): void {
            if (! filled($assignment->assignment_key)) {
                $assignment->assignment_key = $assignment->resolveAssignmentKey();
            }
        });
    }

    public static function keyFor(Agency|Agent $referrer, Agency|Agent $referred): string
    {
        return self::partyKey($referrer).'|'.self::partyKey($referred);
    }

    public static function partyKey(Agency|Agent $party): string
    {
        $prefix = $party instanceof Agency ? 'agency' : 'agent';

        return $prefix.':'.(int) $party->id;
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForReferrer(Builder $query, Agency|Agent $referrer): Builder
    {
        if ($referrer instanceof Agent) {
            return $query->where('referrer_agent_id', (int) $referrer->id)
                ->whereNull('referrer_agency_id');
        }

        return $query->where('referrer_agency_id', (int) $referrer->id)
            ->whereNull('referrer_agent_id');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForReferred(Builder $query, Agency|Agent $referred): Builder
    {
        if ($referred instanceof Agent) {
            return $query->where('referred_agent_id', (int) $referred->id)
                ->whereNull('referred_agency_id');
        }

        return $query->where('referred_agency_id', (int) $referred->id)
            ->whereNull('referred_agent_id');
    }

    /**
     * @return array{referrer_agency_id: int|null, referrer_agent_id: int|null}
     */
    public static function referrerPayload(Agency|Agent $referrer): array
    {
        if ($referrer instanceof Agent) {
            return [
                'referrer_agency_id' => null,
                'referrer_agent_id' => (int) $referrer->id,
            ];
        }

        return [
            'referrer_agency_id' => (int) $referrer->id,
            'referrer_agent_id' => null,
        ];
    }

    /**
     * @return array{referred_agency_id: int|null, referred_agent_id: int|null}
     */
    public static function referredPayload(Agency|Agent $referred): array
    {
        if ($referred instanceof Agent) {
            return [
                'referred_agency_id' => null,
                'referred_agent_id' => (int) $referred->id,
            ];
        }

        return [
            'referred_agency_id' => (int) $referred->id,
            'referred_agent_id' => null,
        ];
    }

    public function referrerAgency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'referrer_agency_id');
    }

    public function referrerAgent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'referrer_agent_id');
    }

    public function referredAgency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'referred_agency_id');
    }

    public function referredAgent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'referred_agent_id');
    }

    public function referrer(): Agency|Agent|null
    {
        if (filled($this->referrer_agent_id)) {
            $agent = $this->relationLoaded('referrerAgent')
                ? $this->getRelation('referrerAgent')
                : $this->referrerAgent;

            return $agent instanceof Agent ? $agent : null;
        }

        if (filled($this->referrer_agency_id)) {
            $agency = $this->relationLoaded('referrerAgency')
                ? $this->getRelation('referrerAgency')
                : $this->referrerAgency;

            return $agency instanceof Agency ? $agency : null;
        }

        return null;
    }

    private function resolveAssignmentKey(): string
    {
        $referrer = filled($this->referrer_agent_id)
            ? 'agent:'.(int) $this->referrer_agent_id
            : 'agency:'.(int) $this->referrer_agency_id;
        $referred = filled($this->referred_agent_id)
            ? 'agent:'.(int) $this->referred_agent_id
            : 'agency:'.(int) $this->referred_agency_id;

        return $referrer.'|'.$referred;
    }
}
