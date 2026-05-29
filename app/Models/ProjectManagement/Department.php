<?php

declare(strict_types=1);

namespace App\Models\ProjectManagement;

use App\Models\ProjectManagement\Concerns\HasProjectManagementAssignments;
use App\Models\ProjectManagement\Concerns\HasProjectManagementExecutions;
use App\Models\ProjectManagement\Concerns\InteractsWithProjectManagementDocuments;
use App\Models\ProjectManagement\Concerns\InteractsWithProjectManagementNotes;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasProjectManagementAssignments;
    use HasProjectManagementExecutions;
    use InteractsWithProjectManagementDocuments;
    use InteractsWithProjectManagementNotes;

    protected $table = 'departments';

    protected $fillable = [
        'name',
        'description',
    ];
}
