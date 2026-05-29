<?php

declare(strict_types=1);

namespace App\Models\ProjectManagement\Concerns;

use App\Models\ProjectManagement\NotesLog;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait InteractsWithProjectManagementNotes
{
    public function notesLogs(): MorphMany
    {
        return $this->morphMany(NotesLog::class, 'notable');
    }
}
