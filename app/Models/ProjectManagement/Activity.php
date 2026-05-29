<?php

declare(strict_types=1);

namespace App\Models\ProjectManagement;

use App\Models\ProjectManagement\Concerns\InteractsWithProjectManagementDocuments;
use App\Models\ProjectManagement\Concerns\InteractsWithProjectManagementNotes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Activity extends Model
{
    use InteractsWithProjectManagementDocuments;
    use InteractsWithProjectManagementNotes;

    protected $table = 'activities';

    protected $fillable = [
        'project_id',
        'subproject_id',
        'title',
        'description',
        'status',
        'priority',
        'color',
        'assignment_type',
        'assigned_collaborator_ids',
        'executor_type',
        'executor_id',
        'due_date',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'assigned_collaborator_ids' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function subproject(): BelongsTo
    {
        return $this->belongsTo(Subproject::class, 'subproject_id');
    }

    public function executor(): MorphTo
    {
        return $this->morphTo();
    }
}
