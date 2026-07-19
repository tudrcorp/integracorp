<?php

declare(strict_types=1);

namespace App\Models\ProjectManagement;

use App\Enums\ProjectManagement\SprintStatus;
use App\Models\ProjectManagement\Concerns\InteractsWithProjectManagementDocuments;
use App\Models\ProjectManagement\Concerns\InteractsWithProjectManagementNotes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Project extends Model
{
    use InteractsWithProjectManagementDocuments;
    use InteractsWithProjectManagementNotes;

    protected $table = 'projects';

    protected $fillable = [
        'name',
        'description',
        'status',
        'color',
        'icon',
        'start_date',
        'end_date',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function subprojects(): HasMany
    {
        return $this->hasMany(Subproject::class, 'project_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ProjectAssignment::class, 'project_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class, 'project_id');
    }

    public function epics(): HasMany
    {
        return $this->hasMany(Epic::class, 'project_id');
    }

    public function sprints(): HasMany
    {
        return $this->hasMany(Sprint::class, 'project_id');
    }

    public function scrumRoles(): HasOne
    {
        return $this->hasOne(ProjectScrumRole::class, 'project_id');
    }

    public function activeSprint(): HasOne
    {
        return $this->hasOne(Sprint::class, 'project_id')
            ->where('status', SprintStatus::Active->value);
    }
}
