<?php

declare(strict_types=1);

namespace App\Models\ProjectManagement;

use App\Models\RrhhColaborador;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectScrumRole extends Model
{
    protected $table = 'project_scrum_roles';

    protected $fillable = [
        'project_id',
        'product_owner_id',
        'scrum_master_id',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function productOwner(): BelongsTo
    {
        return $this->belongsTo(RrhhColaborador::class, 'product_owner_id');
    }

    public function scrumMaster(): BelongsTo
    {
        return $this->belongsTo(RrhhColaborador::class, 'scrum_master_id');
    }
}
