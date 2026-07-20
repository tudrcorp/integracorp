<?php

declare(strict_types=1);

namespace App\Models\ProjectManagement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SprintDailyMetric extends Model
{
    protected $table = 'sprint_daily_metrics';

    protected $fillable = [
        'sprint_id',
        'date',
        'committed_points',
        'remaining_points',
        'completed_points',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'committed_points' => 'integer',
            'remaining_points' => 'integer',
            'completed_points' => 'integer',
        ];
    }

    public function sprint(): BelongsTo
    {
        return $this->belongsTo(Sprint::class, 'sprint_id');
    }
}
