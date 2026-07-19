<?php

declare(strict_types=1);

namespace App\Models\ProjectManagement;

use App\Enums\ProjectManagement\SprintStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sprint extends Model
{
    protected $table = 'sprints';

    protected $fillable = [
        'project_id',
        'name',
        'goal',
        'starts_at',
        'ends_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'status' => SprintStatus::class,
        ];
    }

    public function isActive(): bool
    {
        return $this->status === SprintStatus::Active;
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class, 'sprint_id');
    }

    public function ceremonies(): HasMany
    {
        return $this->hasMany(SprintCeremony::class, 'sprint_id');
    }

    public function dailyMetrics(): HasMany
    {
        return $this->hasMany(SprintDailyMetric::class, 'sprint_id');
    }
}
