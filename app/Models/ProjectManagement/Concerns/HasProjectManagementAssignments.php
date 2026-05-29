<?php

declare(strict_types=1);

namespace App\Models\ProjectManagement\Concerns;

use App\Models\ProjectManagement\ProjectAssignment;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasProjectManagementAssignments
{
    public function projectAssignments(): MorphMany
    {
        return $this->morphMany(ProjectAssignment::class, 'assignable');
    }
}
