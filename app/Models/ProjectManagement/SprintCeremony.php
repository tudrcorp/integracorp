<?php

declare(strict_types=1);

namespace App\Models\ProjectManagement;

use App\Enums\ProjectManagement\CeremonyType;
use App\Models\RrhhColaborador;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SprintCeremony extends Model
{
    protected $table = 'sprint_ceremonies';

    protected $fillable = [
        'sprint_id',
        'type',
        'scheduled_at',
        'ended_at',
        'notes',
        'facilitator_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => CeremonyType::class,
            'scheduled_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function sprint(): BelongsTo
    {
        return $this->belongsTo(Sprint::class, 'sprint_id');
    }

    public function facilitator(): BelongsTo
    {
        return $this->belongsTo(RrhhColaborador::class, 'facilitator_id');
    }
}
