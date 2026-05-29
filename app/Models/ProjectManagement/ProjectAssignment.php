<?php

declare(strict_types=1);

namespace App\Models\ProjectManagement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ProjectAssignment extends Model
{
    protected $table = 'project_assignments';

    protected $fillable = [
        'project_id',
        'assignable_type',
        'assignable_id',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function assignable(): MorphTo
    {
        return $this->morphTo();
    }
}
