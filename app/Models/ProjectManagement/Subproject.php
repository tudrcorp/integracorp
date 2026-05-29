<?php

declare(strict_types=1);

namespace App\Models\ProjectManagement;

use App\Models\ProjectManagement\Concerns\InteractsWithProjectManagementDocuments;
use App\Models\ProjectManagement\Concerns\InteractsWithProjectManagementNotes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subproject extends Model
{
    use InteractsWithProjectManagementDocuments;
    use InteractsWithProjectManagementNotes;

    protected $table = 'subprojects';

    protected $fillable = [
        'project_id',
        'name',
        'description',
        'status',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class, 'subproject_id');
    }
}
