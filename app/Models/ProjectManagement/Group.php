<?php

declare(strict_types=1);

namespace App\Models\ProjectManagement;

use App\Models\ProjectManagement\Concerns\HasProjectManagementAssignments;
use App\Models\ProjectManagement\Concerns\HasProjectManagementExecutions;
use App\Models\ProjectManagement\Concerns\InteractsWithProjectManagementDocuments;
use App\Models\ProjectManagement\Concerns\InteractsWithProjectManagementNotes;
use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    use HasProjectManagementAssignments;
    use HasProjectManagementExecutions;
    use InteractsWithProjectManagementDocuments;
    use InteractsWithProjectManagementNotes;

    protected $table = 'groups';

    protected $fillable = [
        'name',
        'description',
        'collaborator_ids',
    ];

    protected function casts(): array
    {
        return [
            'collaborator_ids' => 'array',
        ];
    }
}
