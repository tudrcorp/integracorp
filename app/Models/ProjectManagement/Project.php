<?php

declare(strict_types=1);

namespace App\Models\ProjectManagement;

use App\Models\ProjectManagement\Concerns\InteractsWithProjectManagementDocuments;
use App\Models\ProjectManagement\Concerns\InteractsWithProjectManagementNotes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
}
