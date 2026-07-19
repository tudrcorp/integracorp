<?php

declare(strict_types=1);

namespace App\Models\ProjectManagement;

use App\Models\ProjectManagement\Concerns\InteractsWithProjectManagementDocuments;
use App\Models\ProjectManagement\Concerns\InteractsWithProjectManagementNotes;
use App\Models\ProjectManagement\Concerns\TracksActivityScrumMetrics;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Activity extends Model
{
    use InteractsWithProjectManagementDocuments;
    use InteractsWithProjectManagementNotes;
    use TracksActivityScrumMetrics;

    protected $table = 'activities';

    protected $fillable = [
        'project_id',
        'subproject_id',
        'epic_id',
        'sprint_id',
        'title',
        'description',
        'acceptance_criteria',
        'status',
        'priority',
        'story_points',
        'backlog_order',
        'color',
        'assignment_type',
        'assigned_collaborator_ids',
        'executor_type',
        'executor_id',
        'due_date',
        'kanban_archived_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'kanban_archived_at' => 'datetime',
            'completed_at' => 'datetime',
            'assigned_collaborator_ids' => 'array',
            'story_points' => 'integer',
            'backlog_order' => 'integer',
        ];
    }

    public function isArchivedFromKanban(): bool
    {
        return $this->kanban_archived_at !== null;
    }

    public function isInBacklog(): bool
    {
        return $this->sprint_id === null;
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function subproject(): BelongsTo
    {
        return $this->belongsTo(Subproject::class, 'subproject_id');
    }

    public function epic(): BelongsTo
    {
        return $this->belongsTo(Epic::class, 'epic_id');
    }

    public function sprint(): BelongsTo
    {
        return $this->belongsTo(Sprint::class, 'sprint_id');
    }

    public function executor(): MorphTo
    {
        return $this->morphTo();
    }
}
