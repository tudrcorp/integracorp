<?php

declare(strict_types=1);

namespace App\Models\ProjectManagement\Concerns;

use App\Models\ProjectManagement\Activity;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasProjectManagementExecutions
{
    public function executedActivities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'executor');
    }
}
