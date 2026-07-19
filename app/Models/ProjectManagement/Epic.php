<?php

declare(strict_types=1);

namespace App\Models\ProjectManagement;

use App\Enums\ProjectManagement\EpicStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Epic extends Model
{
    protected $table = 'epics';

    protected $fillable = [
        'project_id',
        'name',
        'description',
        'status',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'status' => EpicStatus::class,
            'order' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class, 'epic_id');
    }
}
